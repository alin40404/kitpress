<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}

class Str{
    /**
     * 检查字符串是否以指定后缀结尾
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * 检查字符串是否以指定前缀开始
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) === 0;
    }
}