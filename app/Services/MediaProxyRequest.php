<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class MediaProxyRequest
{
    private function __construct(
        public string $url,
        public bool $download,
        public string $filename,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        $url = self::normalizeUrl((string) ($query['url'] ?? ''));
        $download = ($query['download'] ?? '') === '1';
        $filename = self::sanitizeFilename((string) ($query['filename'] ?? ''));

        return new self($url, $download, $filename);
    }

    private static function normalizeUrl(string $value): string
    {
        $url = trim($value);
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $ipHost = trim($host, '[]');
        $normalizedIp = filter_var($ipHost, FILTER_VALIDATE_IP);
        $isNumericIpv4 = preg_match('/^(?:0[xX][0-9a-fA-F]+|0[0-7]+|\d+)$/', $ipHost) === 1;

        if ($url === '' || !is_array($parts) || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array($scheme, ['http', 'https'], true) || $host === '' || ($isNumericIpv4 && $normalizedIp === false)) {
            throw new InvalidArgumentException('无效的媒体链接');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('拒绝访问该媒体地址');
        }

        if (filter_var($ipHost, FILTER_VALIDATE_IP)
            && !filter_var($ipHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('拒绝访问该媒体地址');
        }

        return $url;
    }

    private static function sanitizeFilename(string $value): string
    {
        $filename = trim($value);
        if ($filename === '') {
            return 'video.mp4';
        }

        $filename = (string) preg_replace('/[\x00-\x1F\x7F\\\\\/:*?"<>|]+/', '_', $filename);
        $filename = (string) preg_replace('/\s+/', ' ', $filename);
        $filename = str_replace('..', '.', $filename);
        $filename = trim($filename, " \t\n\r\0\x0B.");

        return $filename === '' ? 'video.mp4' : substr($filename, 0, 120);
    }
}
