<?php
namespace kitpress\library;


use kitpress\core\exceptions\BootstrapException;
use kitpress\core\traits\ConfigTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 路由管理类
 */
class Router {
    use ConfigTrait ;

    private $log = null;

    public function __construct(Plugin $plugin,Log $log) {
        $this->log = $log;
        $this->namespace = $plugin->getNamespace();
    }

    /**
     * 加载配置文件
     * @param string|array $names 配置文件名
     * @param string $namespace 插件命名空间
     * @return void
     */
    public function load($names) {
        try {
            $this->loadResource($names, 'routes');
        } catch (BootstrapException $e) {
            $this->log->debug($e->getMessage());
        }
    }

    /**
     * 获取路由配置（静态代理方法）
     * @param string|null $key 路由键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key = null, $default = null) {
        return $this->getValue(
            $this->items,
            $key,
            $default
        );
    }

    /**
     * 设置路由配置（静态代理方法）
     * @param string $key 路由键名
     * @param mixed $value 路由配置
     */
    public function set($key, $value) {
        $this->setValue(
            $this->items,
            $key,
            $value
        );
    }


    /**
     * 检查路由是否存在（静态代理方法）
     * @param string $key 路由键名
     * @return bool
     */
    public function has($key) {
        return $this->getValue(
                $this->items,
                $key
            ) !== null;
    }

    /**
     * 重置路由（静态代理方法）
     */
    public function reset() {
        $this->items = [];
        $this->loaded = [];
    }

}