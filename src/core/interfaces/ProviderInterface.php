<?php
namespace kitpress\core\interfaces;

use kitpress\core\Container;

if (!defined('ABSPATH')) {
    exit;
}
interface ProviderInterface {
    /**
     * 注册服务到容器
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void;

    /**
     * 服务启动时的初始化
     * @param Container $container
     * @return void
     */
    public function boot(Container $container): void;
}