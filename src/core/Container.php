<?php
namespace kitpress\core;

use InvalidArgumentException;
use Closure;
use kitpress\core\abstracts\Singleton;
use kitpress\core\interfaces\ContainerInterface;
use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 服务容器类
 * 负责管理所有服务的注册和解析
 */
class Container extends Singleton implements ContainerInterface {

    private array $bindings = [];      // 注册的服务
    private static array $containers = [];  // 存储容器实例
    private array $dependencies = [];  // 依赖关系
    private array $hooks = [];         // 生命周期钩子
    private string $namespace = '';      // 添加命名空间标识
    private string $version = '';        // 添加版本标识
    private array $serviceInstances = [];  // 服务实例缓存

    /**
     * 容器初始化状态
     */
    private bool $initialized = false;

    /**
     * 检查容器是否已经初始化
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * 标记容器为已初始化状态
     */
    public function setInitialized(): void
    {
        $this->initialized = true;
    }

    /**
     * 初始化容器
     * @param string $namespace 插件命名空间
     */
    protected function __construct(string $namespace = '') {
        $this->namespace = $namespace;
        parent::__construct();
    }

    /**
     * 获取容器实例，增加命名空间支持
     * @param string $namespace 插件命名空间
     * @param string $version 框架版本
     */
    public static function getInstance(string $namespace = ''): self {
        $key = $namespace ?: 'default';
        if (!isset(static::$containers[$key])) {
            static::$containers[$key] = new static($namespace);
        }
        return static::$containers[$key];
    }

    /**
     * 检查容器是否已设置
     * @param string $namespace
     * @return bool
     */
    public static function checkContainer(string $namespace): bool
    {
        if (!isset(static::$containers[$namespace])) {
            throw new \RuntimeException("Container for namespace '{$namespace}' not found");
        }
        return true;
    }



    /**
     * 生成带命名空间的服务ID
     */
    protected function getNamespacedId(string $id): string
    {
        // 检查ID是否已经包含命名空间前缀
        if ($this->namespace && strpos($id, $this->namespace . '.') !== 0) {
            return "{$this->namespace}.{$id}";
        }
        return $id;
    }

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
            'dependencies' => array_map(
                [$this, 'getNamespacedId'],
                $config['dependencies'] ?? []
            )
        ];

        $this->dependencies[$id] = array_map(
            [$this, 'getNamespacedId'],
            $config['dependencies'] ?? []
        );

        $this->triggerHook('service.registered', $id);
    }

    /**
     * 注册单例服务
     */
    public function singleton(string $id, $concrete, array $config = []) {
        $namespacedId = $this->getNamespacedId($id);
        $config['singleton'] = true;
        $this->bind($namespacedId, $concrete, $config);
    }

    /**
     * 解析服务
     */
    public function resolve(string $id) {
        $namespacedId = $this->getNamespacedId($id);

        if (isset($this->serviceInstances[$namespacedId])) {
            return $this->serviceInstances[$namespacedId];
        }

        if (!isset($this->bindings[$namespacedId])) {
            throw new InvalidArgumentException("服务未找到: $namespacedId");
        }

        $binding = $this->bindings[$namespacedId];
        $concrete = $binding['concrete'];

        $dependencies = array_map(
            [$this, 'resolve'],
            $this->dependencies[$namespacedId]
        );

        $instance = $concrete instanceof Closure
            ? $concrete($this, ...$dependencies)
            : new $concrete(...$dependencies);

        if ($binding['singleton']) {
            $this->serviceInstances[$namespacedId] = $instance;
        }

        return $instance;
    }


    /**
     * 获取服务实例（resolve的别名方法）
     * @param string $id 服务标识
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function get(string $id) {
        return $this->resolve($id);
    }

    public function service(string $id)
    {
        return $this->resolve($id);
    }

    /**
     * 魔术方法：通过属性方式获取服务
     * @param string $name 服务名称
     * @return mixed
     */
    public function __get(string $name) {
        return $this->resolve($name);
    }

    /**
     * 检查服务是否已注册
     * @param string $id 服务标识
     * @return bool
     */
    public function has(string $id): bool {
        $namespacedId = $this->getNamespacedId($id);
        return isset($this->bindings[$namespacedId]);
    }

    /**
     * 添加生命周期钩子
     */
    public function addHook(string $event, callable $callback) {
        $namespacedEvent = $this->getNamespacedId($event);
        $this->hooks[$namespacedEvent][] = $callback;
    }

    /**
     * 触发钩子
     */
    public function triggerHook(string $event, $data = null) {
        $namespacedEvent = $this->getNamespacedId($event);
        if (isset($this->hooks[$namespacedEvent])) {
            foreach ($this->hooks[$namespacedEvent] as $callback) {
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
    public function getOrderedBindings(): array {
        $bindings = $this->getNamespacedBindings();
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
            if ($binding['singleton'] && !isset($this->serviceInstances[$id])) {
                $this->resolve($id);
            }
        }
    }


    /**
     * 获取所有已注册的服务
     * @return array
     */
    public function getBindings(): array {
        return $this->getNamespacedBindings();
    }

    /**
     * 获取所有已实例化的服务
     * @return array
     */
    public function getServices(): array {
        return $this->serviceInstances;
    }

    /**
     * 获取当前命名空间下的所有绑定
     * @return array
     */
    protected function getNamespacedBindings(): array {
        return $this->bindings;
    }

    /**
     * 获取当前容器的命名空间
     * @return string
     */
    public function getNamespace(): string {
        return $this->namespace;
    }

    /**
     * 获取当前容器的版本
     * @return string
     */
    public function getVersion(): string {
        return $this->version ?: KITPRESS_VERSION;
    }
}
