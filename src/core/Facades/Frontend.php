<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Facade 类
 *
 * @method static void init() 初始化前台功能
 * @method static void registerRoutes() 注册前台路由
 * @method static void registerAssets() 注册前台资源
 * @method static void handleRoute(string $type, string $action) 处理路由请求
 *
 * @see \kitpress\library\Frontend 实际的前台功能实现类
 */
class Frontend extends Facade {
    protected static function getFacadeAccessor() {
        return 'frontend';
    }
}