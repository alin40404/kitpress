<?php
namespace kitpress\core\interfaces;

use kitpress\core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 服务提供者接口
 *
 * 定义了服务提供者的标准行为。服务提供者负责向容器注册服务，
 * 并在服务启动时执行必要的初始化操作。这是实现模块化和解耦的重要机制。
 *
 * @package kitpress\core\interfaces
 * @author Allan
 * @since 1.0.0
 */
interface ProviderInterface {

    /**
     * 注册服务到容器
     *
     * 在这个方法中向容器注册服务，但不要执行任何初始化操作。
     * 注册的服务可以是：
     * - 类的绑定
     * - 单例的绑定
     * - 接口到实现的绑定
     * - 配置信息的注册等
     *
     * @param Container $container 依赖注入容器实例
     * @return void
     * @throws \RuntimeException 当服务注册失败时
     */
    public function register(Container $container): void;

    /**
     * 服务启动时的初始化
     *
     * 在所有服务注册完成后执行初始化操作，可以在这里：
     * - 注册钩子
     * - 添加过滤器
     * - 注册短代码
     * - 设置路由
     * - 加载配置等
     *
     * @param Container $container 依赖注入容器实例
     * @return void
     * @throws \RuntimeException 当初始化失败时
     */
    public function boot(Container $container): void;
}