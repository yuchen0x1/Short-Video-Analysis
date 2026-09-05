package com.yuchen.qushuiyin;

import android.content.Context;
import android.net.Uri;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.InetAddress;
import java.net.ServerSocket;
import java.net.Socket;
import java.net.URI;
import java.net.URISyntaxException;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Arrays;
import java.util.Collections;
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/**
 * 本地媒体代理：App 内起一个仅监听 127.0.0.1 的迷你 HTTP 服务，
 * 为媒体请求注入 Referer/UA 绕过防盗链（复刻 PHP MediaProxy 行为），
 * 支持 Range 透传实现边下边播。WebView 的 <video>/<img> 与
 * DownloadBridge 的下载统一走本代理。
 */
@CapacitorPlugin(name = "MediaProxyPlugin")
public class MediaProxyPlugin extends Plugin {

    private static final String PROXY_UA =
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                    + "(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36";

    // 单次转发上限，与服务端 MEDIA_PROXY_MAX_SIZE 默认值一致（500MB）
    static final long MAX_PROXY_BYTES = 524288000L;
    static final int MAX_REDIRECTS = 5;

    // 与 PHP MediaProxy::FORWARD_HEADERS 一致的响应头白名单
    private static final Set<String> FORWARD_HEADERS = new HashSet<>();

    static {
        FORWARD_HEADERS.add("content-type");
        FORWARD_HEADERS.add("content-length");
        FORWARD_HEADERS.add("content-range");
        FORWARD_HEADERS.add("accept-ranges");
        FORWARD_HEADERS.add("content-disposition");
        FORWARD_HEADERS.add("etag");
        FORWARD_HEADERS.add("last-modified");
        FORWARD_HEADERS.add("cache-control");
    }

    private ServerSocket serverSocket;
    private int port;
    private final ExecutorService pool = Executors.newCachedThreadPool();

    @PluginMethod
    public void start(PluginCall call) {
        if (serverSocket != null && !serverSocket.isClosed()) {
            JSObject ready = new JSObject();
            ready.put("port", port);
            call.resolve(ready);
            return;
        }

        try {
            // 仅监听回环地址：媒体代理只供本 App 使用，不暴露到网络
            serverSocket = new ServerSocket(0, 8, InetAddress.getByName("127.0.0.1"));
            port = serverSocket.getLocalPort();
            acceptLoop();
            JSObject ready = new JSObject();
            ready.put("port", port);
            call.resolve(ready);
        } catch (Exception e) {
            call.reject("本地媒体代理启动失败: " + e.getMessage());
        }
    }

    private void acceptLoop() {
        Thread acceptor = new Thread(() -> {
            while (serverSocket != null && !serverSocket.isClosed()) {
                try {
                    Socket client = serverSocket.accept();
                    pool.execute(() -> handle(client));
                } catch (IOException ignored) {
                    // 服务关闭时的正常退出
                }
            }
        }, "media-proxy-accept");
        acceptor.setDaemon(true);
        acceptor.start();
    }

    /** 返回可被 <video>/<img>/DownloadManager 直接使用的本地代理地址 */
    @PluginMethod
    public void url(PluginCall call) {
        String target = call.getString("url");
        if (target == null || target.isEmpty()) {
            call.reject("缺少媒体地址");
            return;
        }
        if (!isAllowedMediaUrl(target)) {
            call.reject("不允许的媒体地址");
            return;
        }
        if (serverSocket == null || serverSocket.isClosed()) {
            call.reject("媒体代理尚未启动");
            return;
        }
        Uri proxyUri = Uri.parse("http://127.0.0.1:" + port + "/");
        Uri.Builder builder = proxyUri.buildUpon();
        builder.appendQueryParameter("url", target);
        String filename = call.getString("filename");
        if (filename != null && !filename.isEmpty()) {
            builder.appendQueryParameter("filename", filename);
        }
        JSObject result = new JSObject();
        result.put("url", builder.build().toString());
        call.resolve(result);
    }

    private void handle(Socket client) {
        try {
            client.setSoTimeout(20000);
            InputStream clientInput = client.getInputStream();
            String requestLine = readLine(clientInput);
            if (requestLine == null || !requestLine.startsWith("GET ")) {
                client.close();
                return;
            }
            String[] requestParts = requestLine.split(" ");
            if (requestParts.length < 2) {
                respond(client, 400, "invalid request", null);
                return;
            }
            String rawTarget = requestParts[1];
            Uri requestUri = Uri.parse(rawTarget);
            String target = requestUri.getQueryParameter("url");
            String filename = requestUri.getQueryParameter("filename");
            if (target == null || target.isEmpty()) {
                respond(client, 422, "missing url", null);
                return;
            }
            if (!isAllowedMediaUrl(target)) {
                respond(client, 403, "不允许的媒体地址", null);
                return;
            }

            // 丢弃请求头，但保留合法的 Range 请求。
            String line;
            String rangeHeader = null;
            while ((line = readLine(clientInput)) != null && !line.isEmpty()) {
                if (line.toLowerCase(Locale.ROOT).startsWith("range:")) {
                    rangeHeader = line.substring(6).trim();
                }
            }

            String currentTarget = target;
            for (int redirectCount = 0; redirectCount <= MAX_REDIRECTS; redirectCount++) {
                URI currentUri = parseUri(currentTarget);
                if (currentUri == null) {
                    respond(client, 403, "不允许的媒体地址", null);
                    return;
                }

                InetAddress[] addresses;
                try {
                    addresses = InetAddress.getAllByName(currentUri.getHost());
                } catch (IOException e) {
                    respond(client, 403, "媒体地址解析失败", null);
                    return;
                }
                // 每一跳都重新解析并校验全部地址，失败关闭以避免 DNS rebinding 绕过。
                if (!MediaProxySecurity.isAllowedMediaUrl(currentTarget, addresses)) {
                    respond(client, 403, "不允许的媒体地址", null);
                    return;
                }

                URL upstream = currentUri.toURL();
                HttpURLConnection conn = (HttpURLConnection) upstream.openConnection();
                conn.setConnectTimeout(10000);
                conn.setReadTimeout(30000);
                // 重定向必须逐跳解析并重新校验，不能让 URLConnection 自动跳出白名单。
                conn.setInstanceFollowRedirects(false);
                conn.setRequestProperty("User-Agent", PROXY_UA);
                conn.setRequestProperty("Accept", "*/*");
                // Referer 取媒体地址自身的源（与 PHP MediaProxy 一致），用于绕过防盗链
                conn.setRequestProperty("Referer", upstream.getProtocol() + "://" + upstream.getHost());
                if (rangeHeader != null && rangeHeader.matches("bytes=\\d*-\\d*")) {
                    conn.setRequestProperty("Range", rangeHeader);
                }

                try {
                    int status = conn.getResponseCode();
                    if (isRedirectStatus(status)) {
                        String location = conn.getHeaderField("Location");
                        if (redirectCount >= MAX_REDIRECTS) {
                            respond(client, 508, "媒体源重定向次数过多", null);
                            return;
                        }
                        URI next = MediaProxySecurity.resolveRedirect(currentUri, location);
                        if (next == null || !isAllowedMediaUrl(next.toString())) {
                            respond(client, 502, "媒体源重定向地址无效", null);
                            return;
                        }
                        currentTarget = next.toString();
                        continue;
                    }

                    if (status >= 400) {
                        respond(client, status >= 500 ? 502 : status, "upstream " + status, null);
                        return;
                    }

                    String contentLengthHeader = conn.getHeaderField("Content-Length");
                    if (!MediaProxySecurity.isContentLengthHeaderValid(contentLengthHeader)) {
                        respond(client, 502, "媒体源 Content-Length 无效", null);
                        return;
                    }
                    Long contentLength = MediaProxySecurity.parseContentLength(contentLengthHeader);
                    if (!MediaProxySecurity.isResponseLengthAllowed(
                            contentLength == null ? -1L : contentLength, MAX_PROXY_BYTES)) {
                        respond(client, 413, "媒体文件超过大小限制", null);
                        return;
                    }

                    OutputStream out = client.getOutputStream();
                    StringBuilder head = new StringBuilder();
                    head.append("HTTP/1.1 ").append(status).append(" ").append(reasonPhrase(status)).append("\r\n");
                    boolean hasAcceptRanges = false;
                    for (String name : FORWARD_HEADERS) {
                        String value;
                        if (name.equals("content-length")) {
                            // 未知长度的流不能伪造 Content-Length，否则客户端会看到不一致的响应。
                            if (contentLength == null) {
                                continue;
                            }
                            value = Long.toString(contentLength);
                        } else {
                            value = conn.getHeaderField(name);
                        }
                        if (value == null || value.isEmpty()) continue;
                        if (name.equals("accept-ranges")) hasAcceptRanges = true;
                        head.append(name).append(": ").append(value).append("\r\n");
                    }
                    if (!hasAcceptRanges) {
                        head.append("accept-ranges: bytes\r\n");
                    }
                    if (filename != null && !filename.isEmpty()) {
                        head.append("content-disposition: attachment; filename*=UTF-8''")
                                .append(Uri.encode(filename)).append("\r\n");
                    }
                    head.append("connection: close\r\n\r\n");
                    out.write(head.toString().getBytes(StandardCharsets.UTF_8));

                    InputStream body = status == 204 || status == 304
                            ? null : conn.getInputStream();
                    if (body != null) {
                        // 未知长度的响应在超过上限时只能中止连接，不能再补发 413。
                        long expectedLength = contentLength == null ? -1L : contentLength;
                        copyBody(body, out, MAX_PROXY_BYTES, expectedLength);
                        body.close();
                    }
                    out.close();
                    return;
                } finally {
                    conn.disconnect();
                }
            }
        } catch (Exception ignored) {
            // 单个连接异常不影响代理整体
        } finally {
            try {
                client.close();
            } catch (IOException ignored) {
            }
        }
    }

    static boolean isRedirectStatus(int status) {
        return status >= 300 && status < 400 && status != 304;
    }

    /** 只允许配置域名本身或以点分隔的合法子域名。 */
    static boolean isAllowedMediaUrl(String value) {
        return MediaProxySecurity.isAllowedMediaUrl(value);
    }

    /** 在 DNS 解析后校验地址，任一解析结果为非公网地址都失败关闭。 */
    static boolean isAllowedMediaUrl(String value, InetAddress[] addresses) {
        return MediaProxySecurity.isAllowedMediaUrl(value, addresses);
    }

    static boolean isAllowedHost(String value) {
        return MediaProxySecurity.isAllowedHost(value);
    }

    static String normalizeHost(String value) {
        return MediaProxySecurity.normalizeHost(value);
    }

    static URI parseUri(String value) {
        return MediaProxySecurity.parseUri(value);
    }

    static boolean isAbnormalPort(URI uri) {
        return MediaProxySecurity.isAbnormalPort(uri);
    }

    static boolean arePublicAddresses(InetAddress[] addresses) {
        return MediaProxySecurity.arePublicAddresses(addresses);
    }

    static boolean isForbiddenAddress(String value) {
        return MediaProxySecurity.isForbiddenAddress(value);
    }

    static boolean isForbiddenAddress(InetAddress address) {
        return MediaProxySecurity.isForbiddenAddress(address);
    }

    static Long parseContentLength(String value) {
        return MediaProxySecurity.parseContentLength(value);
    }

    static boolean isContentLengthHeaderValid(String value) {
        return MediaProxySecurity.isContentLengthHeaderValid(value);
    }

    static boolean isResponseLengthAllowed(long contentLength, long maxBytes) {
        return MediaProxySecurity.isResponseLengthAllowed(contentLength, maxBytes);
    }

    static URI resolveRedirect(URI base, String location) {
        return MediaProxySecurity.resolveRedirect(base, location);
    }

    private static boolean copyBody(InputStream body, OutputStream out, long maxBytes, long expectedLength)
            throws IOException {
        byte[] buffer = new byte[64 * 1024];
        long total = 0;
        int read;
        while ((read = body.read(buffer)) != -1) {
            if (expectedLength >= 0 && total > expectedLength - read) {
                long remaining = expectedLength - total;
                if (remaining > 0) {
                    out.write(buffer, 0, (int) remaining);
                    out.flush();
                }
                return false;
            }
            if (maxBytes > 0 && total > maxBytes - read) {
                long remaining = maxBytes - total;
                if (remaining > 0) {
                    out.write(buffer, 0, (int) remaining);
                    out.flush();
                }
                return false;
            }
            out.write(buffer, 0, read);
            out.flush();
            total += read;
        }
        return expectedLength < 0 || total == expectedLength;
    }

    private static String reasonPhrase(int status) {
        switch (status) {
            case 200: return "OK";
            case 206: return "Partial Content";
            case 400: return "Bad Request";
            case 403: return "Forbidden";
            case 404: return "Not Found";
            case 416: return "Range Not Satisfiable";
            case 422: return "Unprocessable Entity";
            case 502: return "Bad Gateway";
            case 508: return "Loop Detected";
            case 413: return "Payload Too Large";
            case 500: return "Internal Server Error";
            default: return "";
        }
    }

    private void respond(Socket client, int status, String message, String contentType) {
        try {
            OutputStream out = client.getOutputStream();
            byte[] body = message.getBytes(StandardCharsets.UTF_8);
            StringBuilder head = new StringBuilder();
            head.append("HTTP/1.1 ").append(status).append(" ").append(reasonPhrase(status)).append("\r\n");
            head.append("content-type: ").append(contentType == null ? "text/plain" : contentType).append("\r\n");
            head.append("content-length: ").append(body.length).append("\r\n");
            head.append("connection: close\r\n\r\n");
            out.write(head.toString().getBytes(StandardCharsets.UTF_8));
            out.write(body);
            out.flush();
            out.close();
        } catch (IOException ignored) {
        }
    }

    private static String readLine(InputStream in) throws IOException {
        StringBuilder builder = new StringBuilder();
        int c;
        while ((c = in.read()) != -1) {
            if (c == '\n') break;
            builder.append((char) c);
        }
        if (builder.length() == 0 && c == -1) return null;
        int length = builder.length();
        if (length > 0 && builder.charAt(length - 1) == '\r') {
            builder.setLength(length - 1);
        }
        return builder.toString();
    }

    @Override
    protected void handleOnDestroy() {
        pool.shutdownNow();
        try {
            if (serverSocket != null) serverSocket.close();
        } catch (IOException ignored) {
        }
        super.handleOnDestroy();
    }
}

/** Pure media target checks kept separate from the Android plugin lifecycle for JVM tests. */
final class MediaProxySecurity {

    static final Set<String> ALLOWED_MEDIA_DOMAINS = Collections.unmodifiableSet(
            new HashSet<>(Arrays.asList(
                    "douyinvod.com",
                    "douyinvod.com.cn",
                    "bytecdn.cn",
                    "ibytedtos.com",
                    "bilivideo.com",
                    "mcdn.bilivideo.cn",
                    "ixigua.com",
                    "ixiguavideo.com",
                    "video.weishi.qq.com",
                    "ippzone.com",
                    "pipix.com"
            )));

    static boolean isAllowedMediaUrl(String value) {
        URI uri = parseUri(value);
        return uri != null && isAllowedHost(uri.getHost());
    }

    static boolean isAllowedMediaUrl(String value, InetAddress[] addresses) {
        URI uri = parseUri(value);
        return uri != null && isAllowedHost(uri.getHost()) && arePublicAddresses(addresses);
    }

    static boolean isAllowedHost(String value) {
        String host = normalizeHost(value);
        if (host.isEmpty() || "localhost".equals(host) || host.endsWith(".localhost")) {
            return false;
        }
        for (String domain : ALLOWED_MEDIA_DOMAINS) {
            if (host.equals(domain) || host.endsWith("." + domain)) {
                return true;
            }
        }
        return false;
    }

    static String normalizeHost(String value) {
        if (value == null) return "";
        String host = value.trim().toLowerCase(Locale.ROOT);
        while (host.endsWith(".")) {
            host = host.substring(0, host.length() - 1);
        }
        return host;
    }

    static URI parseUri(String value) {
        if (value == null || value.isEmpty()) return null;
        try {
            URI uri = new URI(value);
            String scheme = uri.getScheme();
            String host = uri.getHost();
            if (scheme == null || host == null || uri.getRawAuthority() == null) return null;
            scheme = scheme.toLowerCase(Locale.ROOT);
            if (!("http".equals(scheme) || "https".equals(scheme))) return null;
            if (uri.getUserInfo() != null || uri.getRawAuthority().indexOf('@') >= 0) return null;
            if (isAbnormalPort(uri)) return null;
            return uri;
        } catch (URISyntaxException | IllegalArgumentException ignored) {
            return null;
        }
    }

    static boolean isAbnormalPort(URI uri) {
        int port = uri.getPort();
        if (port < 0) return false;
        String scheme = uri.getScheme().toLowerCase(Locale.ROOT);
        return ("http".equals(scheme) && port != 80)
                || ("https".equals(scheme) && port != 443);
    }

    static boolean arePublicAddresses(InetAddress[] addresses) {
        if (addresses == null || addresses.length == 0) return false;
        for (InetAddress address : addresses) {
            if (address == null || isForbiddenAddress(address)) return false;
        }
        return true;
    }

    static boolean isForbiddenAddress(String value) {
        try {
            return isForbiddenAddress(InetAddress.getByName(value));
        } catch (Exception ignored) {
            return true;
        }
    }

    static boolean isForbiddenAddress(InetAddress address) {
        if (address.isAnyLocalAddress()
                || address.isLoopbackAddress()
                || address.isLinkLocalAddress()
                || address.isSiteLocalAddress()
                || address.isMulticastAddress()) {
            return true;
        }

        byte[] bytes = address.getAddress();
        if (bytes.length == 4) {
            return isForbiddenIpv4(bytes, 0);
        }

        // IPv4-mapped IPv6 addresses must receive the same IPv4 checks.
        boolean mapped = true;
        for (int i = 0; i < 10; i++) {
            if (bytes[i] != 0) {
                mapped = false;
                break;
            }
        }
        if (mapped && (bytes[10] & 0xff) == 0xff && (bytes[11] & 0xff) == 0xff) {
            return isForbiddenIpv4(bytes, 12);
        }

        int first = bytes[0] & 0xff;
        int second = bytes[1] & 0xff;
        // Unique-local, link-local, multicast and documentation IPv6 ranges.
        if ((first & 0xfe) == 0xfc || (first == 0xfe && (second & 0xc0) == 0x80)
                || first == 0xff || (first == 0x20 && second == 0x01
                && (bytes[2] & 0xff) == 0x0d && (bytes[3] & 0xff) == 0xb8)) {
            return true;
        }
        return false;
    }

    private static boolean isForbiddenIpv4(byte[] bytes, int offset) {
        int first = bytes[offset] & 0xff;
        int second = bytes[offset + 1] & 0xff;
        int third = bytes[offset + 2] & 0xff;
        if (first == 0 || first == 10 || first == 127 || first >= 224) return true;
        if (first == 100 && second >= 64 && second <= 127) return true;
        if (first == 169 && second == 254) return true;
        if (first == 172 && second >= 16 && second <= 31) return true;
        if (first == 192 && (second == 0 || second == 168)) return true;
        if (first == 192 && second == 88 && third == 99) return true;
        if (first == 192 && second == 31 && third == 196) return true;
        if (first == 192 && second == 52 && third == 193) return true;
        if (first == 192 && second == 175 && third == 48) return true;
        if (first == 198 && (second == 18 || second == 19)) return true;
        if (first == 198 && second == 51 && third == 100) return true;
        return first == 203 && second == 0 && third == 113;
    }

    static Long parseContentLength(String value) {
        if (value == null) return null;
        String trimmed = value.trim();
        if (trimmed.isEmpty() || !trimmed.matches("\\d+")) return null;
        try {
            return Long.valueOf(trimmed);
        } catch (NumberFormatException ignored) {
            return null;
        }
    }

    static boolean isContentLengthHeaderValid(String value) {
        return value == null || value.trim().isEmpty() || parseContentLength(value) != null;
    }

    static boolean isResponseLengthAllowed(long contentLength, long maxBytes) {
        return maxBytes <= 0 || contentLength < 0 || contentLength <= maxBytes;
    }

    static URI resolveRedirect(URI base, String location) {
        if (base == null || location == null || location.isEmpty()) return null;
        try {
            URI resolved = base.resolve(new URI(location));
            URI parsed = parseUri(resolved.toString());
            return parsed != null && isAllowedMediaUrl(parsed.toString()) ? parsed : null;
        } catch (URISyntaxException | IllegalArgumentException ignored) {
            return null;
        }
    }
}
