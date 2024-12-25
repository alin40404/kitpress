<?php
namespace kitpress\library;

use kitpress\core\traits\ConfigTrait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 配置管理类
 */
class Config {
    use ConfigTrait ;

    /**
     * 加载配置文件（静态代理方法）
     * @param string|array $names 配置文件名
     */
    public function load($names) {
        $this->loadResource($names, 'config');
    }

    /**
     * 获取配置值（静态代理方法）
     * @param string|null $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key = null, $default = null) {
        return $this->getValue(
            $this->items[$this->pluginId],
            $key,
            $default
        );
    }

    /**
     * 设置配置值（静态代理方法）
     * @param string $key 配置键名
     * @param mixed $value 配置值
     */
    public function set($key, $value) {
        $this->setValue(
            $this->items[$this->pluginId],
            $key,
            $value
        );
    }

    /**
     * 检查配置是否存在（静态代理方法）
     * @param string $key 配置键名
     * @return bool
     */
    public function has($key) {
        return $this->getValue(
                $this->items[$this->pluginId],
                $key
            ) !== null;
    }

    /**
     * 重置配置（静态代理方法）
     */
    public function reset() {
        $this->items[$this->pluginId] = [];
        $this->loaded[$this->pluginId] = [];
    }
} 