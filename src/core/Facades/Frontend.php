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
 * @method static void registerHooks() 注册所有钩子（包括短代码、AJAX和POST处理器）
 * @method static void registerShortcodes() 注册短代码
 * @method static void registerAjaxHandlers() 注册AJAX处理器
 * @method static void registerPostHandlers() 注册POST处理器
 * @method static void registerAssets() 注册和加载前端资源
 *
 * @see \kitpress\library\Frontend 实际的前台功能实现类
 */
class Frontend extends Facade {
    protected static function getFacadeAccessor() {
        return 'frontend';
    }
}