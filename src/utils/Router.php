<?php
namespace kitpress\utils;


use kitpress\core\traits\ConfigTrait;

if (!defined('ABSPATH')) {
    exit;
}

class Router {
    use ConfigTrait {
        load as protected loadResource;
    }

    /**
     * 加载路由配置
     */
    public static function load($names) {
        static::loadResource($names, 'routes');
    }

    // 防止实例化
    private function __construct() {}

    // 防止克隆
    private function __clone() {}

    // 防止反序列化
    private function __wakeup() {}
}