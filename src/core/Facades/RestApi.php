<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RestApi Facade 类
 * 用于处理 WordPress REST API 相关功能
 *
 * @method static void registerRoutes() 注册 REST API 路由
 * @method static void handleRoute(string $endpoint, string $method) 处理 API 请求
 * @method static void registerEndpoints() 注册 API 端点
 * @method static mixed response(mixed $data, int $status = 200) 返回 API 响应
 * @method static mixed error(string $message, int $code = 400) 返回错误响应
 *
 * @see \kitpress\library\RestApi 实际的 REST API 功能实现类
 */
class RestApi extends Facade {
    protected static function getFacadeAccessor() {
        return 'restapi';
    }
}