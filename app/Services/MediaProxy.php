<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use App\Utils\HttpClient;

/**
 * 服务端媒体代理
 *
 * 短视频平台的 CDN 直链通常带有 Referer/CORS 防盗链限制，
 * 浏览器直接播放会失败。该代理在服务端转发媒体流并支持 Range 请求。
 */
class MediaProxy
{
    private const FORWARD_HEADERS = [
        'content-type',
        'content-length',
        'content-range',
        'accept-ranges',
        'content-disposition',
        'content-encoding',
        'etag',
        'last-modified',
        'cache-control',
        'expires',
        'date',
        'age',
        'vary',
    ];

    /**
     * 流式转发已验证的远程媒体请求
     */
    public static function streamRequest(MediaProxyRequest $request): void
    {
        self::stream($request->url, $request->download, $request->filename);
    }

    /**
     * 流式转发远程媒体
     *
     * @param string $url 远程媒体地址
     * @param bool $download 是否以附件形式下载
     * @param string $filename 下载文件名
     */
    private static function stream(string $url, bool $download = false, string $filename = 'video.mp4'): void
    {
        if (!Config::get('media_proxy.enabled', true)) {
            self::respondError(503, '媒体代理已关闭');
            return;
        }

        if (!self::isAllowedUrl($url)) {
            self::respondError(403, '拒绝访问该地址');
            return;
        }

        @set_time_limit(0);

        // 清空输出缓冲，保证媒体流可以边下载边播放
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $currentUrl = $url;
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        $maxFileSize = max(0, (int) Config::get('media_proxy.max_file_size', 524288000));
        // m3u8 播放列表走改写模式：不透传上游头，正文缓冲后把分片/子列表 URI 改写回本代理，
        // 让整条 HLS 链路都经过同一套防盗链头注入与逐跳安全校验。
        $playlistMode = !$download
            && str_ends_with(strtolower((string) (parse_url($url, PHP_URL_PATH) ?? '')), '.m3u8');

        // 不让 cURL 自动跟随重定向；每一跳都重新执行 SSRF、私网 IP 和域名白名单校验。
        for ($redirectCount = 0; $redirectCount <= 5; $redirectCount++) {
            $resolvedIps = self::resolveHostIps($currentUrl);
            if (!self::isAllowedUrl($currentUrl, $resolvedIps)) {
                self::respondError(403, '拒绝访问该地址');
                return;
            }

            $parts = parse_url($currentUrl);
            $referer = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
            $requestHeaders = [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                'Accept: */*',
                'Referer: ' . $referer,
            ];
            $state = [
                'forward' => false,
                'status' => 0,
                'redirectUrl' => null,
                'acceptRangesSeen' => false,
                'bytesWritten' => 0,
                'maxFileSize' => $maxFileSize,
                'maxExceeded' => false,
                'playlist' => $playlistMode,
                'playlistBuffer' => '',
            ];

            $curlOptions = [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_BUFFERSIZE => 131072,
                CURLOPT_NOPROGRESS => true,
                CURLOPT_LOW_SPEED_LIMIT => 1,
                CURLOPT_LOW_SPEED_TIME => 30,
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_HEADERFUNCTION => static function ($ch, string $headerLine) use (&$state, $download, $filename): int {
                    self::handleUpstreamHeader($headerLine, $state, $download, $filename);
                    return strlen($headerLine);
                },
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$state): int {
                    // 超限后立即中止连接；重定向响应体则直接丢弃。
                    if ($state['maxExceeded']) {
                        return 0;
                    }
                    if (!$state['forward']) {
                        return strlen($chunk);
                    }
                    if (!empty($state['playlist'])) {
                        // 播放列表缓冲（上限 2MB，防伪播放列表拖垮内存），改写后统一输出
                        if (strlen($state['playlistBuffer']) + strlen($chunk) > 2097152) {
                            $state['maxExceeded'] = true;
                            return 0;
                        }
                        $state['playlistBuffer'] .= $chunk;
                        return strlen($chunk);
                    }

                    $length = strlen($chunk);
                    $limit = $state['maxFileSize'];
                    if ($limit > 0 && $state['bytesWritten'] + $length > $limit) {
                        $remaining = max(0, $limit - $state['bytesWritten']);
                        if ($remaining > 0) {
                            echo substr($chunk, 0, $remaining);
                            $state['bytesWritten'] += $remaining;
                        }
                        $state['maxExceeded'] = true;
                        return 0;
                    }

                    echo $chunk;
                    $state['bytesWritten'] += $length;
                    return $length;
                },
            ];

            $resolveEntries = self::buildResolveEntries($currentUrl, $resolvedIps);
            if ($resolveEntries !== []) {
                $curlOptions[CURLOPT_RESOLVE] = $resolveEntries;
            }

            $ch = curl_init($currentUrl);
            curl_setopt_array($ch, array_replace($curlOptions, HttpClient::getSslOptions()));

            if ($range !== '' && preg_match('/^bytes=\d*-\d*(,\d*-\d*)*$/i', $range)) {
                curl_setopt($ch, CURLOPT_RANGE, substr($range, 6));
            }

            $result = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($state['maxExceeded']) {
                if (!headers_sent()) {
                    self::respondError(413, '媒体文件超过大小限制');
                }
                return;
            }

            if ($result === false) {
                if (!headers_sent()) {
                    http_response_code(502);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo '媒体源连接失败：' . $curlError;
                }
                return;
            }

            if ($httpCode >= 300 && $httpCode < 400) {
                $location = (string) ($state['redirectUrl'] ?? '');
                $nextUrl = self::resolveRedirectUrl($currentUrl, $location);
                if ($nextUrl === false) {
                    self::respondError(502, '媒体源重定向地址无效');
                    return;
                }
                $currentUrl = $nextUrl;
                continue;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                if (!headers_sent()) {
                    $safeStatus = $httpCode >= 400 && $httpCode <= 599 ? $httpCode : 502;
                    http_response_code($safeStatus);
                    header('Content-Type: text/plain; charset=utf-8');
                }
                echo $httpCode >= 400 && $httpCode <= 599
                    ? '媒体源返回 HTTP ' . $httpCode
                    : '媒体源响应无效';
                return;
            }

            if ($playlistMode) {
                if (!headers_sent()) {
                    $isPlaylist = str_starts_with(ltrim($state['playlistBuffer']), '#EXTM3U');
                    header('Content-Type: ' . ($isPlaylist ? 'application/vnd.apple.mpegurl' : 'application/octet-stream'));
                    header('Cache-Control: no-store');
                }
                echo self::rewritePlaylist($state['playlistBuffer'], $currentUrl);
                return;
            }

            return;
        }

        self::respondError(502, '媒体源重定向次数过多');
    }

    /**
     * 把 m3u8 文本中的分片、子播放列表与密钥 URI 改写为本代理地址。
     * 相对 URI 基于播放列表自身地址解析；改写后的每一跳都经过 action=media 的完整校验。
     */
    public static function rewritePlaylist(string $playlist, string $playlistUrl): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $playlist) ?: [];
        $rewritten = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $rewritten[] = $line;
                continue;
            }

            if ($trimmed[0] === '#') {
                // 仅改写标签属性中的 URI="..."（EXT-X-KEY / EXT-X-MAP 等）
                $rewritten[] = preg_replace_callback('/URI="([^"]+)"/i', static function (array $match) use ($playlistUrl): string {
                    return 'URI="' . self::proxiedPlaylistUrl($playlistUrl, $match[1]) . '"';
                }, $line) ?? $line;
                continue;
            }

            $rewritten[] = self::proxiedPlaylistUrl($playlistUrl, $trimmed);
        }

        return implode("\n", $rewritten);
    }

    private static function proxiedPlaylistUrl(string $playlistUrl, string $uri): string
    {
        if (!preg_match('#^https?://#i', $uri)) {
            $resolved = HttpClient::resolveLocation($playlistUrl, $uri);
            $uri = $resolved !== false ? $resolved : $uri;
        }

        return '/index.php?action=media&url=' . rawurlencode($uri);
    }

    private static function handleUpstreamHeader(string $line, array &$state, bool $download, string $filename): void
    {
        $line = trim($line);

        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $match)) {
            $status = (int) $match[1];
            // 重定向由 stream() 手动完成，不向浏览器转发 3xx。
            $state['status'] = $status;
            $state['forward'] = $status >= 200 && $status !== 204;
            $state['acceptRangesSeen'] = false;
            if ($state['forward']) {
                http_response_code($status);
            }
            return;
        }

        // 播放列表模式：不透传任何上游头（含 content-length/content-disposition），仅捕获重定向地址。
        if (!empty($state['playlist'])) {
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $headerName = strtolower(trim(substr($line, 0, $colonPos)));
                $headerValue = trim(substr($line, $colonPos + 1));
                if ($state['status'] >= 300 && $state['status'] < 400 && $headerName === 'location') {
                    $state['redirectUrl'] = $headerValue;
                }
            }
            return;
        }

        if ($state['forward'] && $line === '') {
            if ($state['maxExceeded']) {
                if (!headers_sent()) {
                    http_response_code(413);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo '媒体文件超过大小限制';
                }
                return;
            }

            if (!$state['acceptRangesSeen']) {
                header('Accept-Ranges: bytes');
            }
            if ($download) {
                header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));
            }
            return;
        }

        $colon = strpos($line, ':');
        if ($colon === false) {
            return;
        }

        $name = trim(substr($line, 0, $colon));
        $value = trim(substr($line, $colon + 1));
        $lower = strtolower($name);

        if ($state['status'] >= 300 && $state['status'] < 400 && $lower === 'location') {
            $state['redirectUrl'] = $value;
        }

        if (!$state['forward']) {
            return;
        }

        if ($lower === 'content-length' && $state['maxFileSize'] > 0 && ctype_digit($value)
            && (int) $value > $state['maxFileSize']) {
            $state['maxExceeded'] = true;
            $state['forward'] = false;
            if (!headers_sent()) {
                http_response_code(413);
                header('Content-Type: text/plain; charset=utf-8');
            }
            return;
        }

        if (!in_array($lower, self::FORWARD_HEADERS, true)) {
            return;
        }

        if ($lower === 'accept-ranges') {
            $state['acceptRangesSeen'] = true;
        }

        if ($lower === 'content-disposition' && $download) {
            return;
        }

        header($name . ': ' . $value);
    }

    private static function respondError(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }

    private static function isAllowedUrl(string $url, ?array $resolvedIps = null): bool
    {
        if (!preg_match('/^https?:\/\//i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        $allowedDomains = Config::get('media_proxy.allowed_domains', []);
        if (is_array($allowedDomains) && $allowedDomains !== [] && !self::matchesDomain($host, $allowedDomains)) {
            return false;
        }

        if ((bool) Config::get('media_proxy.strict_mode', false)
            && (!is_array($allowedDomains) || $allowedDomains === [])) {
            return false;
        }

        if ($resolvedIps === null) {
            $resolvedIps = self::resolveHostIps($url);
        }

        if ($resolvedIps === null || $resolvedIps === []) {
            return false;
        }

        foreach ($resolvedIps as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 解析 URL 主机的 A/AAAA 记录，并返回所有地址。
     * 解析失败时返回 null，代理采用失败关闭策略。
     *
     * @return array<string>|null
     */
    private static function resolveHostIps(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }

        if ($ips === []) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        $ips = array_values(array_unique($ips));
        return $ips === [] ? null : $ips;
    }

    /**
     * 将已校验的 DNS 结果固定给 cURL，降低 DNS rebinding 风险。
     *
     * @param array<string>|null $resolvedIps
     * @return array<string>
     */
    private static function buildResolveEntries(string $url, ?array $resolvedIps): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) || $resolvedIps === null) {
            return [];
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?? ($scheme === 'https' ? 443 : 80));
        $entries = [];
        foreach ($resolvedIps as $ip) {
            $entries[] = $host . ':' . $port . ':' . $ip;
        }

        return $entries;
    }

    private static function resolveRedirectUrl(string $baseUrl, string $location): string|false
    {
        return HttpClient::resolveLocation($baseUrl, $location);
    }

    private static function matchesDomain(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            $domain = strtolower(trim((string) $domain));
            $domain = ltrim($domain, '.');
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain))) {
                return true;
            }
        }

        return false;
    }
}
