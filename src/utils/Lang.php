<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}

class Lang {
    /**
     * 存储当前文本域
     * @var string
     */
    private static $textDomain = '';

    /**
     * 初始化文本域
     * @param $domain
     * @return static
     */
    public static function init($domain = '') {
        self::$textDomain = $domain ?: Config::get('app.text_domain');
    }

    /**
     * 翻译文本
     * @param string $text 需要翻译的文本
     * @return string
     */
    public static function __($text) {
        return \__($text, self::$textDomain);
    }

    /**
     * 框架翻译文本
     * @param string $text 需要翻译的文本
     * @return string
     */
    public static function kit($text) {
        return \__($text,KITPRESS_TEXT_DOMAIN);
    }

    /**
     * 翻译文本并输出
     * @param string $text 需要翻译的文本
     */
    public static function _e($text) {
        \_e($text, self::$textDomain);
    }

    /**
     * 翻译文本（支持复数形式）
     * @param string $single 单数形式文本
     * @param string $plural 复数形式文本
     * @param int $number 数量
     * @return string
     */
    public static function _n($single, $plural, $number) {
        return \_n($single, $plural, $number, self::$textDomain);
    }

    /**
     * 获取当前文本域
     * @return string
     */
    public static function getDomain() {
        return self::$textDomain;
    }
}