<?php
namespace kitpress\core\abstracts;

use kitpress\core\Container;
use kitpress\core\interfaces\CommandInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 命令抽象基类
 *
 * 所有的命令类都应该继承这个基类，它提供了基础的命令行功能和依赖注入容器。
 *
 * @package kitpress\core\abstracts
 * @author Your Name
 * @since 1.0.0
 */
abstract class Command implements CommandInterface
{
    /**
     * 依赖注入容器实例
     *
     * @var Container
     */
    protected Container $container;
}