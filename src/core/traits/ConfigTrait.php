<?php
namespace kitpress\core\traits;

use kitpress\Kitpress;
use kitpress\utils\Config;

if (!defined('ABSPATH')) {
    exit;
}

trait ConfigTrait {
    /**
     * 配置存储
     * @var array
     */
    private static $items = [];

    /**
     * 已加载的配置文件记录
     * @var array
     */
    private static $loaded = [];

    /**
     * 框架默认路径
     * @var string
     */
    private static $defaultPath = null;

    /**
     * 自定义路径
     * @var string
     */
    private static $customPath = null;

    /**
     * 插件根目录
     * @var null
     */
    private static $rootPath = null;


    /**
     * 初始化配置路径
     */
    private static function init($module)
    {
        self::$rootPath = Kitpress::getRootPath();

        $defaultPath = KITPRESS_PATH . $module;
        $customPath = self::$rootPath . $module;

        self::$defaultPath = rtrim($defaultPath, '/') . '/';
        self::$customPath = rtrim($customPath, '/') . '/';

    }

    /**
     * 加载配置文件
     */
    public static function load($names, $module) {
        // 初始化路径
        self::init($module);

        foreach ((array)$names as $name) {
            if (isset(self::$loaded[$name])) {
                continue;
            }

            // 加载默认配置
            $default = self::loadFile(self::$defaultPath . $name . '.php');

            // 加载自定义配置
            $custom = self::loadFile(self::$customPath . $name . '.php');

            // 合并配置
            self::$items[$name] = self::merge((array)$default, (array)$custom);
            self::$loaded[$name] = true;
        }
    }

    /**
     * 获取配置值
     */
    public static function get($key = null, $default = null) {
        if (is_null($key)) {
            return self::$items;
        }
        return self::getValue(self::$items, $key, $default);
    }

    /**
     * 设置配置值
     */
    public static function set($key, $value) {
        self::setValue(self::$items, $key, $value);
    }

    /**
     * 检查配置是否存在
     */
    public static function has($key) {
        return self::getValue(self::$items, $key) !== null;
    }

    /**
     * 加载配置文件
     */
    private static function loadFile($path) {
        return file_exists($path) ? require $path : [];
    }

    /**
     * 递归合并配置数组
     */
    private static function merge($default, $custom) {
        $merged = $default;
        if (is_array($custom) && !empty($custom)) {
            foreach ($custom as $key => $value) {
                if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = self::merge($merged[$key], $value);
                } else {
                    $merged[$key] = $value;
                }
            }
        }
        return $merged;
    }

    /**
     * 使用点号路径获取数组值
     */
    private static function getValue($array, $key, $default = null) {
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
     */
    private static function setValue(&$array, $key, $value) {
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

    /**
     * 重置配置（用于测试）
     */
    public static function reset() {
        self::$items = [];
        self::$loaded = [];
    }
}