<?php
namespace kitpress\library;

use kitpress\Kitpress;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Lang;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    /**
     * 插件命名空间
     */
    private string $namespace = '';

    public function setNamespace(string $namespace)
    {
        $this->namespace = $namespace;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function key(): string
    {
        return $this->namespace;
    }

    public function getRootPath(): string
    {
        return Kitpress::getRootPath($this->namespace);
    }

    public function getRootFile(): string
    {
        $rootPath = self::getRootPath();
        $file_name = $rootPath . basename($rootPath) . '.php';

        // 如果没找到，抛出异常
        if( !file_exists($file_name) ){
            ErrorHandler::die(Lang::kit('框架路径错误：无法在 ' . $file_name . ' 目录下找到有效的插件主文件'));
        }
        return $file_name;
    }
}