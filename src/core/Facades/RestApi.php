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
 * @method static void init() 初始化 REST API，设置命名空间并注册路由
 *
 * @see \kitpress\library\RestApi 实际的 REST API 功能实现类
 */
class RestApi extends Facade {
    protected static function getFacadeAccessor() {
        return 'restapi';
    }
}