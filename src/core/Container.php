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
        $this->validateBinding($id, $concrete);  // 添加验证
        $this->registerBinding($id, $concrete, $config);
    }

    /**
     * 受保护的辅助方法
     * @param string $id
     * @param $concrete
     * @return void
     */
    protected function validateBinding(string $id, $concrete) {
        if (empty($id)) {
            throw new InvalidArgumentException('Service ID cannot be empty');
        }

        if (!is_string($concrete) && !($concrete instanceof Closure)) {
            throw new InvalidArgumentException(
                'Service concrete must be string or Closure'
            );
        }
    }

    /**
     * 注册服务绑定到容器
     *
     * @param string $id 服务标识符
     * @param string|Closure $concrete 具体的服务实现（类名或闭包）
     * @param array $config 服务配置参数
     *      - singleton: bool 是否为单例
     *      - priority: int 优先级（默认10）
     *      - dependencies: array 依赖服务列表
     *
     * @throws InvalidArgumentException 当配置参数无效时
     *
     * @example
     * // 注册普通类
     * $this->registerBinding('cache', Cache::class, [
     *     'singleton' => true,
     *     'priority' => 5
     * ]);
     *
     * // 注册带依赖的服务
     * $this->registerBinding('userService', UserService::class, [
     *     'dependencies' => ['db', 'cache']
     * ]);
     *
     * // 注册闭包
     * $this->registerBinding('logger', function($container) {
     *     return new Logger($container->resolve('config'));
     * });
     */
    protected function registerBinding(string $id, $concrete, array $config) {
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'singleton' => $config['singleton'] ?? false,
            'priority' => $config['priority'] ?? 10,
            'dependencies' => $config['dependencies'] ?? []
        ];

        $this->dependencies[$id] = $config['dependencies'] ?? [];

        $this->triggerHook('service.registered', $id);
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
            throw new InvalidArgumentException("服务未找到: $id");
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

    /**
     * 按优先级获取已注册的服务
     * @return array
     */
    public function getOrderedBindings(): array
    {
        $bindings = $this->bindings;

        // 使用 uasort 保持键值对关系
        uasort($bindings, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        return $bindings;
    }

    /**
     * 初始化所有服务
     * 按优先级顺序初始化
     */
    public function initializeServices() {
        foreach ($this->getOrderedBindings() as $id => $binding) {
            if ($binding['singleton'] && !isset($this->instances[$id])) {
                $this->resolve($id);
            }
        }
    }

    /**
     * 获取所有已注册的服务
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * 获取所有已实例化的服务
     * @return array
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

}
