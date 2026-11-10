import assert from "node:assert/strict";
import { once } from "node:events";
import http from "node:http";
import net from "node:net";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import test from "node:test";

const __dirname = dirname(fileURLToPath(import.meta.url));
const serverPath = join(__dirname, "a_bogus_server.js");

async function getFreePort() {
    const probe = net.createServer();
    probe.listen(0, "127.0.0.1");
    await once(probe, "listening");
    const port = probe.address().port;
    probe.close();
    await once(probe, "close");
    return port;
}

function request(port, options = {}, body) {
    return new Promise((resolve, reject) => {
        const req = http.request({
            hostname: "127.0.0.1",
            port,
            ...options,
        }, (res) => {
            const chunks = [];
            res.on("data", (chunk) => chunks.push(chunk));
            res.on("end", () => resolve({
                statusCode: res.statusCode,
                headers: res.headers,
                body: Buffer.concat(chunks).toString("utf8"),
            }));
        });
        req.on("error", reject);
        req.end(body);
    });
}

async function startServer() {
    const port = await getFreePort();
    const child = spawn(process.execPath, [serverPath], {
        env: {
            ...process.env,
            A_BOGUS_PORT: String(port),
            A_BOGUS_REQUEST_TIMEOUT_MS: "200",
        },
        stdio: ["ignore", "ignore", "pipe"],
    });

    const deadline = Date.now() + 5000;
    while (Date.now() < deadline) {
        if (child.exitCode !== null) {
            throw new Error("a_bogus server exited before becoming ready");
        }
        try {
            const response = await request(port, { method: "GET", path: "/health" });
            if (response.statusCode === 200) {
                // 以服务端上报的真实上限为准，避免测试常量与实现漂移
                const health = JSON.parse(response.body);
                const maxBodyBytes = Number(health.max_body_bytes);
                if (!Number.isSafeInteger(maxBodyBytes) || maxBodyBytes <= 0) {
                    throw new Error("health 未返回有效的 max_body_bytes");
                }
                return { child, port, maxBodyBytes };
            }
        } catch {
            // The child may still be starting its listener.
        }
        await new Promise((resolve) => setTimeout(resolve, 25));
    }
    child.kill();
    throw new Error("timed out waiting for a_bogus server");
}

async function stopServer(child) {
    if (child.exitCode !== null) return;
    child.kill();
    await once(child, "exit");
}

async function withServer(t) {
    const state = await startServer();
    t.after(() => stopServer(state.child));
    return state;
}

test("rejects a Content-Length above the body limit with one 413 response", async (t) => {
    const { port, maxBodyBytes } = await withServer(t);
    const response = await request(port, {
        method: "POST",
        path: "/",
        headers: {
            "Content-Type": "application/json",
            "Content-Length": maxBodyBytes + 1,
        },
    }, Buffer.alloc(maxBodyBytes + 1, 0x61));

    assert.equal(response.statusCode, 413);
    assert.deepEqual(JSON.parse(response.body), { error: "body_too_large" });
});

test("bounds chunked request accumulation and rejects an oversized body", async (t) => {
    const { port, maxBodyBytes } = await withServer(t);
    const response = await new Promise((resolve, reject) => {
        const req = http.request({
            hostname: "127.0.0.1",
            port,
            method: "POST",
            path: "/",
            headers: { "Content-Type": "application/json" },
        }, (res) => {
            const chunks = [];
            res.on("data", (chunk) => chunks.push(chunk));
            res.on("end", () => resolve({
                statusCode: res.statusCode,
                headers: res.headers,
                body: Buffer.concat(chunks).toString("utf8"),
            }));
        });
        req.on("error", reject);
        req.write(Buffer.alloc(maxBodyBytes, 0x61));
        req.end(Buffer.from("x"));
    });

    assert.equal(response.statusCode, 413);
    assert.equal(response.headers.connection, "close");
    assert.deepEqual(JSON.parse(response.body), { error: "body_too_large" });
});

test("returns invalid_input for malformed JSON and invalid fields", async (t) => {
    const { port } = await withServer(t);
    const cases = [
        "{",
        JSON.stringify({ query: 42, ua: "ua" }),
        JSON.stringify({ query: "q".repeat(8193), ua: "ua" }),
        JSON.stringify({ query: "q", ua: "u".repeat(1025) }),
    ];

    for (const body of cases) {
        const response = await request(port, {
            method: "POST",
            path: "/",
            headers: { "Content-Type": "application/json" },
        }, body);
        assert.equal(response.statusCode, 400);
        assert.deepEqual(JSON.parse(response.body), { error: "invalid_input" });
    }
});

test("keeps signing failures distinct from invalid input and preserves valid signing", async (t) => {
    const { port } = await withServer(t);
    const signingFailure = await request(port, {
        method: "POST",
        path: "/",
        headers: { "Content-Type": "application/json" },
    }, JSON.stringify({ query: "\ud800", ua: "u" }));
    assert.equal(signingFailure.statusCode, 500);
    assert.deepEqual(JSON.parse(signingFailure.body), { error: "signing_failed" });

    const valid = await request(port, {
        method: "POST",
        path: "/",
        headers: { "Content-Type": "application/json" },
    }, JSON.stringify({
        query: "aweme_id=123",
        ua: "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
    }));
    assert.equal(valid.statusCode, 200);
    assert.match(valid.body, /^[A-Za-z0-9+/_=-]+$/);
    assert.ok(valid.body.length > 0);
});

test("closes an incomplete request after the read timeout", async (t) => {
    const { port } = await withServer(t);
    const rawResponse = await new Promise((resolve, reject) => {
        const socket = net.createConnection({ host: "127.0.0.1", port });
        const chunks = [];
        socket.setTimeout(2000, () => {
            socket.destroy();
            reject(new Error("server did not time out the request"));
        });
        socket.on("connect", () => {
            socket.write(
                "POST / HTTP/1.1\r\n" +
                `Host: 127.0.0.1:${port}\r\n` +
                "Content-Type: application/json\r\n" +
                "Content-Length: 100\r\n" +
                "Connection: close\r\n\r\n" +
                "{",
            );
        });
        socket.on("data", (chunk) => chunks.push(chunk));
        socket.on("error", reject);
        socket.on("close", () => resolve(Buffer.concat(chunks).toString("utf8")));
    });

    assert.match(rawResponse, /^HTTP\/1\.1 408 /);
    assert.match(rawResponse, /\{"error":"request_timeout"\}/);
});

test("continues serving requests after a client aborts a request", async (t) => {
    const { port } = await withServer(t);
    await new Promise((resolve, reject) => {
        const req = http.request({
            hostname: "127.0.0.1",
            port,
            method: "POST",
            path: "/",
            headers: {
                "Content-Type": "application/json",
                "Content-Length": 100,
            },
        });
        req.on("error", () => resolve());
        req.on("response", () => reject(new Error("aborted request unexpectedly received a response")));
        req.write("{");
        req.destroy();
    });

    const health = await request(port, { method: "GET", path: "/health" });
    assert.equal(health.statusCode, 200);
});

