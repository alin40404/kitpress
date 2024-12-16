<?php
namespace kitpress\core\abstracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 单例抽象类
 */
abstract class Singleton {
    private static $instances = [];

    public static function getInstance() {
        // 后期静态绑定
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    protected function __construct() {}

    // 防止克隆
    private function __clone() {}

    // 防止反序列化
    private function __wakeup() {
        throw new \Exception(__('无法反序列化单例对象', KITPRESS_TEXT_DOMAIN));
    }

    // 防止通过 serialize() 序列化
    private function __sleep() {
        throw new \Exception(__('无法序列化单例对象', KITPRESS_TEXT_DOMAIN));
    }
}