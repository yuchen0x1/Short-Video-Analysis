/**
 * Signing sidecar services
 *
 * - POST /        Douyin a_bogus signing (body: {query, ua})
 * - POST /douyu   Douyu live stream signing (body: {fn, rid, did, tt})
 * - GET  /health  Health check
 *
 * Persistent HTTP service to avoid starting a new Node.js process per request.
 * Binds to 127.0.0.1 only.
 */

const http = require("http");
const crypto = require("crypto");

// Reuse the existing algorithm (a_bogus.js exports { get_ab })
const { get_ab } = require("./a_bogus.js");

const PORT = parseInt(process.env.A_BOGUS_PORT || "9876", 10);
const MAX_BODY_BYTES = 320 * 1024;
const REQUEST_TIMEOUT_MS = parseInt(process.env.A_BOGUS_REQUEST_TIMEOUT_MS || "10000", 10);
const ERROR_HEADERS = {
    "Content-Type": "application/json",
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff",
};

// 斗鱼签名函数生成的代码只用到 CryptoJS.MD5(...).toString()（hex 输出），
// 用 Node 内置 crypto 等价实现，避免引入 crypto-js 依赖。
function buildCryptoJsShim() {
    return {
        MD5(value) {
            return {
                toString() {
                    return crypto.createHash("md5").update(String(value)).digest("hex");
                },
            };
        },
    };
}

// 斗鱼拉流签名：eval 房间页下发的签名函数（函数文本来自 douyu 服务端，
// 经 ub98484234 标识与长度上限约束后在本进程内执行）。
function douyuSign(fnText, rid, did, tt) {
    if (typeof fnText !== "string" || fnText.indexOf("ub98484234") === -1) {
        throw new Error("invalid_sign_function");
    }
    const result = new Function(
        "CryptoJS",
        "rid",
        "did",
        "tt",
        fnText + "\n;return ub98484234(rid, did, tt);"
    )(buildCryptoJsShim(), rid, did, tt);
    if (typeof result !== "string" || result === "") {
        throw new Error("signing_failed");
    }
    return result;
}

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

        if (!input || typeof input !== "object" || Array.isArray(input)) {
            sendError(res, 400, "invalid_input");
            return;
        }

        // POST /douyu：斗鱼拉流签名
        if (req.url === "/douyu") {
            if (
                typeof input.fn !== "string" ||
                input.fn.length === 0 ||
                input.fn.length > MAX_BODY_BYTES ||
                typeof input.rid !== "string" ||
                !/^\d{1,20}$/.test(input.rid) ||
                typeof input.did !== "string" ||
                !/^[0-9a-zA-Z]{1,64}$/.test(input.did) ||
                typeof input.tt !== "string" ||
                !/^\d{1,16}$/.test(input.tt)
            ) {
                sendError(res, 400, "invalid_input");
                return;
            }

            let result;
            try {
                result = douyuSign(input.fn, input.rid, input.did, input.tt);
            } catch {
                sendError(res, 500, "signing_failed");
                return;
            }

            res.writeHead(200, {
                "Content-Type": "text/plain",
                "Cache-Control": "no-store",
            });
            res.end(result);
            return;
        }

        if (
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
