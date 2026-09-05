<?php

namespace App\Utils;

/**
 * 文件日志工具
 *
 * 支持 LOG_LEVEL 级别过滤与按大小轮转（保留 logging.max_files 个历史文件）。
 */
class Logger
{
    private const LOG_FILE = __DIR__ . '/../../storage/logs/app.log';

    /** 单个日志文件上限，超过后轮转为 app.log.Ymd_His */
    private const MAX_LOG_BYTES = 5242880;

    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    /**
     * 确保日志目录存在
     */
    private static function ensureLogDir(): void
    {
        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * 当前配置允许记录的最低级别
     */
    private static function minLevel(): int
    {
        $configured = strtolower((string) Config::get('logging.level', 'error'));
        return self::LEVELS[$configured] ?? self::LEVELS['error'];
    }

    /**
     * 单文件超过上限时轮转，并清理超出保留数量的历史文件
     */
    private static function rotateIfNeeded(): void
    {
        if (!is_file(self::LOG_FILE) || (int) @filesize(self::LOG_FILE) < self::MAX_LOG_BYTES) {
            return;
        }

        @rename(self::LOG_FILE, self::LOG_FILE . '.' . date('Ymd_His'));

        $maxFiles = max(1, (int) Config::get('logging.max_files', 30));
        $rotated = glob(self::LOG_FILE . '.*');
        if (is_array($rotated) && count($rotated) > $maxFiles) {
            sort($rotated);
            foreach (array_slice($rotated, 0, count($rotated) - $maxFiles) as $oldest) {
                @unlink($oldest);
            }
        }
    }

    /**
     * 写入日志
     *
     * @param string $level 日志级别（debug/info/warning/error）
     * @param string $message 消息
     * @param array $context 上下文
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[strtolower($level)] ?? self::LEVELS['error']) < self::minLevel()) {
            return;
        }

        self::ensureLogDir();
        self::rotateIfNeeded();

        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = date('Y-m-d H:i:s') . " [{$level}] {$message}{$contextStr}" . PHP_EOL;

        @file_put_contents(self::LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * 记录调试日志（默认 LOG_LEVEL=error 时不落盘）
     */
    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    /**
     * 记录错误日志
     *
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * 记录异常
     *
     * @param \Throwable $exception 异常对象
     * @param array $context 上下文信息
     */
    public static function exception(\Throwable $exception, array $context = []): void
    {
        $context['exception'] = get_class($exception);
        $context['file'] = $exception->getFile();
        $context['line'] = $exception->getLine();
        $context['trace'] = $exception->getTraceAsString();

        self::write('EXCEPTION', $exception->getMessage(), $context);
    }
}
