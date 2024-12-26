<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backend Facade 类
 *
 * @method static void init() 初始化后台功能
 * @method static void registerRoutes() 注册后台路由
 * @method static void registerAdminMenus() 注册后台菜单
 * @method static void registerAssets(string $hook) 注册后台资源
 * @method static void handleMenuCallback() 处理菜单回调
 * @method static void handleRoute(string $type, string $action) 处理路由请求
 * @method static void handleRequest(string $handler, string $action = '') 处理页面请求
 *
 * @see \kitpress\library\Backend 实际的后台功能实现类
 */
class Backend extends Facade {
    protected static function getFacadeAccessor(): string
    {
        return 'backend';
    }
}