/**
 * Douyin a_bogus signing service
 *
 * Provides a persistent HTTP-based signing service to avoid
 * the overhead of starting a new Node.js process per request.
 */

const http = require("http");

// Reuse the existing algorithm (a_bogus.js exports { get_ab })
const { get_ab } = require("./a_bogus.js");

const PORT = parseInt(process.env.A_BOGUS_PORT || "9876", 10);
const MAX_BODY_BYTES = 64 * 1024;
const REQUEST_TIMEOUT_MS = parseInt(process.env.A_BOGUS_REQUEST_TIMEOUT_MS || "10000", 10);
const ERROR_HEADERS = {
    "Content-Type": "application/json",
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff",
};

function sendError(res, status, code, close = false) {
    if (res.headersSent || res.writableEnded) return false;
    if (close) res.setHeader("Connection", "close");
    res.writeHead(status, ERROR_HEADERS);
    res.end(JSON.stringify({ error: code }));
    return true;
}

const server = http.createServer((req, res) => {
    // Health check endpoint: GET /health -> 200 when the signing service is up
    if (req.method === "GET" && req.url === "/health") {
        res.writeHead(200, { "Content-Type": "application/json", "X-Content-Type-Options": "nosniff" });
        res.end('{"ok":true,"service":"a_bogus"}');
        return;
    }

    if (req.method !== "POST") {
        res.writeHead(405, { "Content-Type": "text/plain" });
        res.end("Method Not Allowed");
        return;
    }

    let settled = false;
    let bodyBytes = 0;
    const chunks = [];

    const respond = (status, headers, body, close = false) => {
        if (settled || res.headersSent || res.writableEnded) return false;
        settled = true;
        if (close) res.setHeader("Connection", "close");
        res.writeHead(status, headers);
        res.end(body);
        return true;
    };

    const sendRequestError = (status, code) => respond(status, ERROR_HEADERS, JSON.stringify({ error: code }), true);

    const closeAfterResponse = () => {
        if (!req.destroyed) {
            process.nextTick(() => req.destroy());
        }
    };

    req.setTimeout(REQUEST_TIMEOUT_MS, () => {
        if (sendRequestError(408, "request_timeout")) closeAfterResponse();
    });

    const declaredLength = req.headers["content-length"];
    if (declaredLength !== undefined) {
        const contentLength = Number(declaredLength);
        if (!Number.isSafeInteger(contentLength) || contentLength < 0) {
            sendRequestError(400, "invalid_input");
            closeAfterResponse();
            return;
        }
        if (contentLength > MAX_BODY_BYTES) {
            sendRequestError(413, "body_too_large");
            closeAfterResponse();
            return;
        }
    }

    req.on("data", (chunk) => {
        if (settled) return;
        bodyBytes += chunk.length;
        if (bodyBytes > MAX_BODY_BYTES) {
            chunks.length = 0;
            if (sendRequestError(413, "body_too_large")) closeAfterResponse();
            return;
        }
        chunks.push(chunk);
    });

    req.on("aborted", () => {
        settled = true;
        chunks.length = 0;
    });

    req.on("error", () => {
        if (!settled) {
            settled = true;
            chunks.length = 0;
        }
    });

    req.on("end", () => {
        if (settled) return;
        settled = true;

        let input;
        try {
            input = JSON.parse(Buffer.concat(chunks, bodyBytes).toString("utf8"));
        } catch {
            sendError(res, 400, "invalid_input");
            return;
        }

        if (
            !input ||
            typeof input !== "object" ||
            Array.isArray(input) ||
            typeof input.query !== "string" ||
            typeof input.ua !== "string" ||
            input.query.length > 8192 ||
            input.ua.length > 1024
        ) {
            sendError(res, 400, "invalid_input");
            return;
        }

        let result;
        try {
            result = get_ab(input.query, input.ua);
        } catch {
            sendError(res, 500, "signing_failed");
            return;
        }

        res.writeHead(200, {
            "Content-Type": "text/plain",
            "Cache-Control": "no-store",
        });
        res.end(result);
    });
});

server.listen(PORT, "127.0.0.1", () => {
    console.error(`a_bogus signing server listening on port ${PORT}`);
});
