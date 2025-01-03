<?php
namespace kitpress\core\interfaces;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * 可初始化接口
 *
 * 定义了可初始化对象的标准行为。实现此接口的类需要提供初始化方法，
 * 用于在对象创建后执行必要的初始化操作。
 *
 * @package kitpress\core\interfaces
 * @author Allan
 * @since 1.0.0
 */
interface InitializableInterface {

    /**
     * 初始化对象
     *
     * 在对象创建后执行必要的初始化操作，如：
     * - 注册钩子
     * - 加载依赖
     * - 设置初始状态
     * - 注册路由等
     *
     * @return void
     * @throws \RuntimeException 当初始化失败时
     */
	public function init();
}