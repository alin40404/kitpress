<?php
namespace kitpress\core\interfaces;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 命令接口
 *
 * 定义了命令类的基本行为。所有的命令类都必须实现这个接口，
 * 以提供统一的命令行操作方式。
 *
 * @package kitpress\core\interfaces
 * @author Allan
 * @since 1.0.0
 */
interface CommandInterface{

    /**
     * 获取命令执行的根路径
     *
     * 返回命令需要操作的项目根目录路径。这个路径将作为所有文件操作的基准目录。
     *
     * @return string 项目根目录的绝对路径
     * @throws \RuntimeException 当路径不存在或不可访问时
     */
    public function getRootPath() : string;
}