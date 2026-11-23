<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * 应用版本解析
 *
 * 单一版本来源：优先读取仓库根的 composer.json。
 * Composer 生成的 vendor/composer/installed.php 只有在执行过 composer install/update 后才会更新，
 * 仅改 composer.json 并刷新 lock 时会残留旧版本号（曾导致 /api/v1/health 返回过期版本）。
 * 因此这里以 composer.json 为准，InstalledVersions 仅作为 composer.json 不可读时的回退。
 */
final class AppVersion
{
    private const PACKAGE = 'yuchen0x1/short-video-watermark-remover';

    private static ?string $cached = null;

    public static function version(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $version = self::fromComposerJson() ?? self::fromInstalledVersions();

        return self::$cached = ($version !== null && $version !== '') ? $version : 'dev';
    }

    /**
     * 伪装某些上游要求的 UA 时复用同一版本号，避免再次出现硬编码漂移。
     */
    public static function userAgent(string $product = 'qushuiyin-web-updater'): string
    {
        return sprintf('%s/%s (+https://github.com/yuchen0x1/Short-Video-Analysis)', $product, self::version());
    }

    private static function fromComposerJson(): ?string
    {
        $file = dirname(__DIR__, 2) . '/composer.json';
        if (!is_readable($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['version']) || !is_string($data['version'])) {
            return null;
        }
        $version = trim($data['version']);

        return preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) === 1 ? $version : null;
    }

    private static function fromInstalledVersions(): ?string
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return null;
        }
        try {
            $version = \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE);
        } catch (\Throwable $e) {
            return null;
        }

        return is_string($version) ? $version : null;
    }
}
