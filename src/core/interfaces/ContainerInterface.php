<?php
namespace kitpress\core\interfaces;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 容器接口
 *
 * 定义了依赖注入容器的基本行为。容器负责管理类的依赖关系和实例化过程。
 *
 * @package kitpress\core\interfaces
 * @author Allan
 * @since 1.0.0
 */
interface ContainerInterface {
    /**
     * 绑定一个实现到容器
     *
     * @param string $id      服务标识符
     * @param mixed  $concrete 具体的实现（可以是类名、闭包或对象实例）
     * @param array  $config   配置参数
     * @return void
     */
    public function bind(string $id, $concrete, array $config = []);

    /**
     * 绑定一个单例到容器
     *
     * @param string $id      服务标识符
     * @param mixed  $concrete 具体的实现（可以是类名、闭包或对象实例）
     * @param array  $config   配置参数
     * @return void
     */
    public function singleton(string $id, $concrete, array $config = []);

    /**
     * 解析并返回一个服务实例
     *
     * @param string $id 服务标识符
     * @return mixed    解析后的实例
     * @throws \Exception 当无法解析服务时抛出异常
     */
    public function resolve(string $id);
}