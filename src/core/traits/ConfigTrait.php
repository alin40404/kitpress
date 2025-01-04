<?php
namespace kitpress\core\traits;

use kitpress\core\exceptions\NotFoundException;
use kitpress\Kitpress;
use kitpress\utils\Helper;

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
    protected array $items = [];

    /**
     * 已加载的配置文件记录
     * @var array
     */
    protected array $loaded = [];

    /**
     * 框架默认路径
     * @var string
     */
    private ?string $defaultPath = null;

    /**
     * 自定义路径
     * @var string
     */
    private ?string $customPath = null;

    /**
     * 插件根目录
     * @var string|null
     */
    private ?string $rootPath = null;


    /**
     * 受保护的配置文件（不允许外部修改）
     * @var array
     */
    protected array $protectedFiles = ['service'];

    /**
     * 插件命名空间
     */
    private string $namespace = '';

    /**
     * 初始化配置路径
     * @param string $module 模块名称
     */
    protected function init(string $module): void
    {
        $this->rootPath = $this->rootPath ?: Kitpress::getRootPath($this->namespace);

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
    protected function loadResource($names, string $module)
    {
        // 初始化路径
        $this->init($module);

        if(!empty($names)){

            foreach ((array)$names as $name) {
                if (isset($this->loaded[$name])) {
                    continue;
                }

                // 加载默认配置
                $default = $this->loadFile($this->defaultPath . $name . '.php');

                $custom = [];

                // 检查是否是受保护的配置文件
                if(!in_array($name, $this->protectedFiles, true)){

                    // 路由配置文件 按需加载，严格检查文件是否存在
                    /*if ($module == 'routes' && !file_exists($this->customPath . $name . '.php')) {
                        throw new NotFoundException("{$module} file", $name);
                    }*/

                    // 加载自定义配置
                    $custom = $this->loadFile($this->customPath . $name . '.php');

                    if (!is_array($custom)) {
                        throw new \RuntimeException("{$module} file must return array: {$name}");
                    }
                }

                // 合并配置
                $this->items[$name] = $this->merge((array)$default, (array)$custom);
                $this->loaded[$name] = true;
            }
        }
    }

    /**
     * 加载配置文件
     * @param string $path 文件路径
     * @return array
     */
    private function loadFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $config = require_once $path;

        if (!is_array($config)) {
            throw new \RuntimeException("配置文件必须返回数组: {$path}");
        }

        return $config;
    }

    /**
     * 递归合并配置数组
     * @param array $default 默认配置
     * @param array $custom 自定义配置
     * @return array
     */
    private function merge(array $default, array $custom): array
    {
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
    protected function getValue(array $array, ?string $key, $default = null)
    {
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
    protected function setValue(array &$array, string $key, $value) {
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