<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * 从用户粘贴的分享文案中提取第一个 http(s) URL。
 * 仅负责输入规范化，不负责判断平台或发起网络请求。
 */
final class SharedUrl
{
    public static function extract(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/https?:\/\/[^\s"\'<>，。；：！？【】（）《》]+/iu', $value, $match) !== 1) {
            return '';
        }

        return rtrim($match[0], ")].,!?;:'\"。！？；，》】）");
    }
}
