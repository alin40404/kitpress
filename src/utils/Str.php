<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}

class Str{

    /**
     * 转换字符串为中划线命名（kebab-case）
     * 例如：userName -> user-name, user_name -> user-name
     */
    public static function kebab(string $value): string
    {
        return str_replace('_', '-', static::snake($value));
    }

    /**
     * 转换字符串为下划线命名（snake_case）
     * 例如：userName -> user_name, user-name -> user_name
     */
    public static function snake(string $value): string
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $value));
        }
        return str_replace('-', '_', $value);
    }

    /**
     * 转换字符串为大驼峰命名（StudlyCase）
     * 例如：user_name -> UserName, user-name -> UserName
     */
    public static function studly(string $value): string
    {
        $words = explode(' ', str_replace(['-', '_'], ' ', $value));

        $studlyWords = array_map(function ($word) {
            return ucfirst(strtolower($word));
        }, $words);

        return implode('', $studlyWords);
    }

    /**
     * 转换字符串为小驼峰命名（camelCase）
     * 例如：user_name -> userName, user-name -> userName
     */
    public static function camel(string $value): string
    {
        return lcfirst(static::studly($value));
    }

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