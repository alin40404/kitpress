<?php
namespace kitpress\core\traits;

use kitpress\Kitpress;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 配置管理特性
 */
trait ConfigTrait {
    /**
     * 配置存储
     * @var array
     */
    protected $items = [];

    /**
     * 已加载的配置文件记录
     * @var array
     */
    protected $loaded = [];

    /**
     * 框架默认路径
     * @var string
     */
    private $defaultPath = null;

    /**
     * 自定义路径
     * @var string
     */
    private $customPath = null;

    /**
     * 插件根目录
     * @var string|null
     */
    private $rootPath = null;

    /**
     * 初始化配置路径
     * @param string $module 模块名称
     */
    protected function init($module) {
        $this->rootPath = Kitpress::getRootPath();

        $defaultPath = KITPRESS_PATH . $module;
        $customPath = $this->rootPath . $module;

        $this->defaultPath = rtrim($defaultPath, '/') . '/';
        $this->customPath = rtrim($customPath, '/') . '/';
    }

    /**
     * 加载配置文件
     * @param string|array $names 配置文件名
     * @param string $module 模块名称
     */
    protected function load($names, $module) {
        // 初始化路径
        $this->init($module);

        foreach ((array)$names as $name) {
            if (isset($this->loaded[$name])) {
                continue;
            }

            // 加载默认配置
            $default = $this->loadFile($this->defaultPath . $name . '.php');

            // 加载自定义配置
            $custom = $this->loadFile($this->customPath . $name . '.php');

            // 合并配置
            $this->items = $this->merge((array)$default, (array)$custom);
            $this->loaded[$name] = true;
        }
    }

    /**
     * 加载配置文件
     * @param string $path 文件路径
     * @return array
     */
    private function loadFile($path) {
        return file_exists($path) ? require $path : [];
    }

    /**
     * 递归合并配置数组
     * @param array $default 默认配置
     * @param array $custom 自定义配置
     * @return array
     */
    private function merge($default, $custom) {
        $merged = $default;
        if (is_array($custom) && !empty($custom)) {
            foreach ($custom as $key => $value) {
                if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = $this->merge($merged[$key], $value);
                } else {
                    $merged[$key] = $value;
                }
            }
        }
        return $merged;
    }

    /**
     * 使用点号路径获取数组值
     * @param array $array 数组
     * @param string|null $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getValue($array, $key, $default = null) {
        if (is_null($key)) {
            return $array;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * 使用点号路径设置数组值
     * @param array $array 数组
     * @param string $key 键名
     * @param mixed $value 值
     */
    protected function setValue(&$array, $key, $value) {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }

            $current = &$current[$key];
        }

        $current[array_shift($keys)] = $value;
    }
}