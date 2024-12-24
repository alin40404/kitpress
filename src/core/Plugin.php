<?php
namespace kitpress\core;

use InvalidArgumentException;
use kitpress\core\abstracts\Initializable;
use kitpress\core\containers\ServiceContainer;


if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    private static $instance = null;
    private $container;
    private $initialized = false;

    private function __construct() {
        $this->container = ServiceContainer::getInstance();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 注册插件
     */
    public function register(string $id, string $class, array $config = []) {
        if (!is_subclass_of($class, Initializable::class)) {
            throw new InvalidArgumentException(
                "Class must implement Initializable interface: $class"
            );
        }

        $this->container->register($id, $class, $config);
    }

    /**
     * 初始化所有插件
     */
    public function initializeAll() {
        if ($this->initialized) {
            return;
        }

        $this->container->triggerHook('before_init');

        // 获取所有绑定并按优先级排序
        $bindings = $this->container->getBindings();
        uasort($bindings, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        // 初始化插件
        foreach ($bindings as $id => $binding) {
            try {
                $instance = $this->container->resolve($id);
                if ($instance instanceof Initializable) {
                    $this->container->triggerHook('before_init_plugin', [
                        'id' => $id,
                        'instance' => $instance
                    ]);

                    $instance->init();

                    $this->container->triggerHook('after_init_plugin', [
                        'id' => $id,
                        'instance' => $instance
                    ]);
                }
            } catch (\Exception $e) {
                // 记录错误但继续执行
                error_log("Failed to initialize plugin $id: " . $e->getMessage());
            }
        }

        $this->container->triggerHook('after_init');
        $this->initialized = true;
    }

    /**
     * 获取插件实例
     */
    public function get(string $id) {
        return $this->container->resolve($id);
    }

    /**
     * 加载插件配置
     */
    public function loadPlugins(array $config) {
        foreach ($config as $id => $pluginConfig) {
            if (isset($pluginConfig['class'])) {
                $this->register($id, $pluginConfig['class'], $pluginConfig);
            }
        }
    }
}