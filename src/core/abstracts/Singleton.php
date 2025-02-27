<?php
namespace kitpress\core\abstracts;

use kitpress\utils\Lang;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 单例抽象类
 */
abstract class Singleton {
    protected static array $instances = [];

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

    /**
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \Exception(Lang::kit('无法反序列化单例对象'));
    }

    // 防止通过 serialize() 序列化

    /**
     * @throws \Exception
     */
    public function __sleep()
    {
        throw new \Exception(Lang::kit('无法序列化单例对象'));
    }
}