<?php
namespace kitpress\utils;

use kitpress\core\abstracts\Singleton;


if (!defined('ABSPATH')) {
    exit;
}

class Lang extends Singleton {

    public function __construct() {
        parent::__construct();
        self::init();
    }

    /**
     * 存储当前文本域
     * @var string
     */
    private $textDomain = '';

    /**
     * 初始化文本域
     * @param $domain
     * @return static
     */
    public static function init($domain = '') {
        $instance = self::getInstance();
        $instance->textDomain = $domain ?: Config::get('app.text_domain');
        return $instance;
    }

    /**
     * 翻译文本
     * @param string $text 需要翻译的文本
     * @return string
     */
    public static function __($text) {
        return \__($text, self::getInstance()->textDomain);
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
        \_e($text, self::getInstance()->textDomain);
    }

    /**
     * 翻译文本（支持复数形式）
     * @param string $single 单数形式文本
     * @param string $plural 复数形式文本
     * @param int $number 数量
     * @return string
     */
    public static function _n($single, $plural, $number) {
        return \_n($single, $plural, $number, self::getInstance()->textDomain);
    }

    /**
     * 获取当前文本域
     * @return string
     */
    public static function getDomain() {
        return self::getInstance()->textDomain;
    }
}