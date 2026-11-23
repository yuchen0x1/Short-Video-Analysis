<?php

namespace App\Utils;

/**
 * API 响应工具类
 * 统一处理 REST API 响应格式
 */
class Response
{
    private static ?string $requestId = null;

    /**
     * 稳定错误码：客户端按 code 分支，message 只用于展示。
     *
     * @var array<int, string>
     */
    private const ERROR_CODES = [
        400 => 'invalid_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        422 => 'invalid_input',
        429 => 'rate_limited',
        500 => 'internal_error',
        502 => 'upstream_error',
        503 => 'service_unavailable',
        504 => 'upstream_timeout',
    ];

    private const RETRYABLE_CODES = [
        'rate_limited',
        'internal_error',
        'upstream_error',
        'service_unavailable',
        'upstream_timeout',
    ];

    private static function getRequestId(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }

        $incoming = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        self::$requestId = preg_match('/^[A-Za-z0-9._-]{1,80}$/', $incoming)
            ? $incoming
            : bin2hex(random_bytes(12));

        return self::$requestId;
    }

    private static function addRequestHeaders(): void
    {
        header('X-Request-ID: ' . self::getRequestId());
    }

    /**
     * 成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $httpCode HTTP 状态码
     * @return void
     */
    public static function success($data = null, string $message = 'success', int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        self::addRequestHeaders();
        
        $response = [
            'success' => true,
            'message' => $message,
            'request_id' => self::getRequestId(),
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * 错误响应
     *
     * @param string $message 错误消息
     * @param int $httpCode HTTP 状态码
     * @param mixed $errors 详细错误信息（可选）
     * @return void
     */
    public static function error(
        string $message,
        int $httpCode = 400,
        $errors = null,
        ?string $code = null,
        ?bool $retryable = null,
        ?int $retryAfter = null
    ): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        self::addRequestHeaders();

        $payload = self::errorPayload($message, $httpCode, $errors, $code, $retryable, $retryAfter);
        if (isset($payload['retry_after'])) {
            header('Retry-After: ' . $payload['retry_after']);
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * 构造错误响应体。
     *
     * 与 error() 拆开是为了能在不 exit 的前提下被单元测试直接断言，
     * 避免只对源码做字符串匹配而漏掉真实取值逻辑（如 retry_after 钳制、retryable 推导）。
     *
     * @param mixed $errors
     * @return array<string, mixed>
     */
    public static function errorPayload(
        string $message,
        int $httpCode = 400,
        $errors = null,
        ?string $code = null,
        ?bool $retryable = null,
        ?int $retryAfter = null
    ): array {
        $resolvedCode = $code ?? (self::ERROR_CODES[$httpCode] ?? 'error');
        $resolvedRetryable = $retryable ?? in_array($resolvedCode, self::RETRYABLE_CODES, true);

        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $resolvedCode,
            'retryable' => $resolvedRetryable,
            'request_id' => self::getRequestId(),
        ];

        if ($retryAfter !== null) {
            // 至少 1 秒且不超过 1 小时，避免异常配置产生不可用的超长等待。
            $payload['retry_after'] = min(max(1, $retryAfter), 3600);
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $payload;
    }

    /**
     * 429 响应：告诉客户端何时可以再次尝试。
     */
    public static function rateLimited(string $message, int $resetTime): void
    {
        $retryAfter = max(1, $resetTime - time());
        self::error($message, 429, null, 'rate_limited', true, $retryAfter);
    }

    /**
     * 验证错误响应
     *
     * @param array $errors 验证错误数组，格式为 ['field' => 'message']
     * @return void
     */
    public static function validationError(array $errors): void
    {
        // 提取第一个错误消息作为主要消息
        $message = !empty($errors) ? reset($errors) : '参数验证失败';
        self::error($message, 422, $errors);
    }
}

