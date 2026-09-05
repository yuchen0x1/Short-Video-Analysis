<?php

namespace App\Utils;

use App\Utils\Config;
use App\Utils\Logger;

/**
 * HTTP 客户端工具
 */
class HttpClient
{
    /**
     * 发送 HTTP 请求（带重试机制）
     * 有 $data 则为 POST，无 $data 则为 GET
     *
     * @param string $url 请求 URL
     * @param string|array|null $data POST 数据，为 null 则为 GET 请求
     * @param array $headers 自定义请求头，格式为 ['Header' => 'value']
     * @param int|null $maxRetries 最大重试次数，null 则使用配置值
     * @param string|null $responseHeaders 通过引用返回原始响应头
     * @return array 返回 ['success' => bool, 'data' => string, 'error' => string, 'http_code' => int]
     */
    public static function request(string $url, $data = null, array $headers = [], ?int $maxRetries = null, ?string &$responseHeaders = null): array
    {
        if (!extension_loaded('curl')) {
            Logger::error('HTTP 请求失败', [
                'url' => $url,
                'error' => 'PHP curl 扩展未启用',
            ]);

            return [
                'success' => false,
                'data' => '',
                'error' => '服务器缺少 PHP curl 扩展，请启用后重试',
                'http_code' => 0
            ];
        }

        $timeout = Config::get('curl.timeout', 10);
        $connectTimeout = Config::get('curl.connect_timeout', 5);
        $maxRetries = $maxRetries ?? Config::get('curl.max_retries', 3);
        
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($url);
            
            // 基础配置
            curl_setopt_array($ch, array_replace([
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'ShortVideoWatermarkRemover/2.0 (+https://github.com/yuchen0x1/short-video-watermark-remover)',
            ], self::getSslOptions()));

            if ($responseHeaders !== null) {
                curl_setopt($ch, CURLOPT_HEADER, true);
            }
            
            // 设置请求头
            if (!empty($headers)) {
                $headerArray = [];
                foreach ($headers as $key => $value) {
                    $headerArray[] = "$key: $value";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            }
            
            // 有数据则为 POST
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            
            $response = curl_exec($ch);
            if ($responseHeaders !== null && $response !== false) {
                $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                if ($headerSize > 0 && strlen($response) >= $headerSize) {
                    $responseHeaders = substr($response, 0, $headerSize);
                    $response = substr($response, $headerSize);
                } else {
                    $responseHeaders = '';
                }
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // 成功返回
            if ($response !== false && $httpCode === 200) {
                return [
                    'success' => true,
                    'data' => $response,
                    'error' => '',
                    'http_code' => $httpCode
                ];
            }
            
            // 判断是否需要重试（仅对网络错误或5xx错误重试）
            $shouldRetry = ($response === false || $httpCode === 0) || ($httpCode >= 500 && $httpCode < 600);
            
            if (!$shouldRetry || $attempt >= $maxRetries) {
                $lastError = $error ?: ($httpCode > 0 ? "HTTP $httpCode" : '网络请求失败');
                
                // 记录服务器错误日志（非4xx客户端错误）
                if ($httpCode >= 500 || $httpCode === 0 || $response === false) {
                    Logger::error('HTTP 请求失败', [
                        'url' => $url,
                        'http_code' => $httpCode,
                        'error' => $lastError,
                        'attempt' => $attempt + 1
                    ]);
                }
                
                return [
                    'success' => false,
                    'data' => $response ?: '',
                    'error' => $lastError,
                    'http_code' => $httpCode
                ];
            }
            
            // 指数退避重试
            usleep(pow(2, $attempt) * 1000000);
        }
        
        return [
            'success' => false,
            'data' => '',
            'error' => '请求失败（已重试）',
            'http_code' => 0
        ];
    }

    /**
     * 获取 cURL TLS 校验配置
     *
     * @return array<int, mixed>
     */
    public static function getSslOptions(): array
    {
        if (!defined('CURLOPT_SSL_VERIFYPEER') || !defined('CURLOPT_SSL_VERIFYHOST')) {
            return [];
        }

        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $caFile = (string) Config::get('curl.cafile', '');
        if ($caFile === '') {
            $caFile = (string) Config::env('CURL_CA_BUNDLE', '');
        }

        if ($caFile !== '' && is_file($caFile)) {
            $options[CURLOPT_CAINFO] = $caFile;
        }

        return $options;
    }

    /**
     * 获取重定向后的地址
     *
     * @param string $url 原始 URL
     * @return string|false 重定向后的 URL，失败返回 false
     */
    public static function getLocation(string $url): string|false
    {
        if (!extension_loaded('curl')) {
            Logger::error('HTTP 重定向解析失败', [
                'url' => $url,
                'error' => 'PHP curl 扩展未启用',
            ]);

            return false;
        }

        $timeout = Config::get('curl.timeout', 10);
        $connectTimeout = Config::get('curl.connect_timeout', 5);
        $currentUrl = $url;

        // 手动跟随有限次数的重定向，便于每一跳使用统一的 URL 解析规则。
        for ($redirectCount = 0; $redirectCount <= 5; $redirectCount++) {
            $ch = curl_init($currentUrl);
            curl_setopt_array($ch, array_replace([
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_TIMEOUT => $timeout,
            ], self::getSslOptions()));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 部分分享短链不支持 HEAD（例如小红书会对 HEAD 返回 404，GET 才返回 302）。
            // 仅在这类明确不兼容的响应下回退 GET，避免常规重定向额外下载页面内容。
            if ($response !== false && in_array($httpCode, [404, 405], true)) {
                $ch = curl_init($currentUrl);
                curl_setopt_array($ch, array_replace([
                    CURLOPT_HEADER => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_HTTPGET => true,
                    CURLOPT_RANGE => '0-0',
                ], self::getSslOptions()));
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }

            if ($response === false) {
                return false;
            }

            if ($httpCode >= 300 && $httpCode < 400) {
                if (!preg_match('/Location:\s*(.*?)\r?\n/i', $response, $matches)) {
                    return false;
                }
                $nextUrl = self::resolveLocation($currentUrl, trim($matches[1]));
                if ($nextUrl === false) {
                    return false;
                }
                $currentUrl = $nextUrl;
                continue;
            }

            return $httpCode === 200 ? $currentUrl : false;
        }

        return false;
    }

    /**
     * 解析相对重定向地址
     *
     * @param string $base 原始请求 URL
     * @param string $location Location 头值
     * @return string|false 解析后的绝对 URL，失败返回 false
     */
    public static function resolveLocation(string $base, string $location): string|false
    {
        $location = trim($location);
        if ($location === '') {
            return false;
        }

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $location, $schemeMatch)) {
            return in_array(strtolower($schemeMatch[1]), ['http', 'https'], true) ? $location : false;
        }

        $parsed = parse_url($base);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        if (strpos($location, '//') === 0) {
            return $parsed['scheme'] . ':' . $location;
        }

        $path = $location;
        $suffix = '';
        $queryPos = strpos($location, '?');
        $fragmentPos = strpos($location, '#');
        if ($queryPos !== false && ($fragmentPos === false || $queryPos < $fragmentPos)) {
            $suffix = substr($location, $queryPos);
            $path = substr($location, 0, $queryPos);
        } elseif ($fragmentPos !== false) {
            $suffix = substr($location, $fragmentPos);
            $path = substr($location, 0, $fragmentPos);
        }

        if ($path === '') {
            $path = $parsed['path'] ?? '/';
        } elseif ($path[0] !== '/') {
            $basePath = $parsed['path'] ?? '/';
            $path = rtrim(substr($basePath, 0, (int) strrpos($basePath, '/')), '/') . '/' . $path;
        }

        $trailingSlash = substr($path, -1) === '/';
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $normalized = '/' . implode('/', $segments);
        if ($trailingSlash && $normalized !== '/') {
            $normalized .= '/';
        }

        $authority = $parsed['host'];
        if (isset($parsed['port'])) {
            $authority .= ':' . $parsed['port'];
        }

        return $parsed['scheme'] . '://' . $authority . $normalized . $suffix;
    }
}
