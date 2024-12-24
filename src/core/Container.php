<?php
namespace kitpress\core;

use InvalidArgumentException;
use Closure;
use kitpress\core\abstracts\Singleton;
use kitpress\core\interfaces\ContainerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 服务容器类
 * 负责管理所有服务的注册和解析
 */
class Container extends Singleton implements ContainerInterface {

    private $bindings = [];      // 注册的服务
    private $instances = [];     // 已实例化的服务
    private $dependencies = [];  // 依赖关系
    private $hooks = [];         // 生命周期钩子


    /**
     * 注册服务
     * @param string $id 服务标识
     * @param string|Closure $concrete 具体实现（类名或闭包）
     * @param array $config 配置参数
     */
    public function bind(string $id, $concrete, array $config = []) {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'singleton' => $config['singleton'] ?? false,
            'priority' => $config['priority'] ?? 10,
            'dependencies' => $config['dependencies'] ?? []
        ];

        $this->dependencies[$id] = $config['dependencies'] ?? [];
    }

    /**
     * 注册单例服务
     */
    public function singleton(string $id, $concrete, array $config = []) {
        $config['singleton'] = true;
        $this->bind($id, $concrete, $config);
    }

    /**
     * 解析服务
     */
    public function resolve(string $id) {
        // 如果是单例且已实例化，直接返回实例
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new InvalidArgumentException("Service not found: $id");
        }

        $binding = $this->bindings[$id];
        $concrete = $binding['concrete'];

        // 解析依赖
        $dependencies = array_map(
            [$this, 'resolve'],
            $this->dependencies[$id]
        );

        // 创建实例
        $instance = $concrete instanceof Closure
            ? $concrete($this, ...$dependencies)
            : new $concrete(...$dependencies);

        // 如果是单例，保存实例
        if ($binding['singleton']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * 添加生命周期钩子
     */
    public function addHook(string $event, callable $callback) {
        $this->hooks[$event][] = $callback;
    }

    /**
     * 触发钩子
     */
    public function triggerHook(string $event, $data = null) {
        if (isset($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $callback) {
                $callback($data);
            }
        }
    }

    /**
     * 批量加载配置
     */
    public function loadConfig(array $config) {
        foreach ($config as $id => $serviceConfig) {
            $concrete = $serviceConfig['class'] ?? $serviceConfig['concrete'] ?? null;
            if ($concrete) {
                $this->bind($id, $concrete, $serviceConfig);
            }
        }
    }
}
