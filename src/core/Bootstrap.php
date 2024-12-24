<?php
namespace kitpress\core;

use kitpress\core\abstracts\Facade;
use kitpress\core\exceptions\BootstrapException;
use kitpress\core\interfaces\ProviderInterface;
use kitpress\library\Config;
use kitpress\utils\Lang;
use kitpress\utils\Loader;

if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap {

    /**
     * 标记是否已初始化
     */
    private static $initialized = false;

    /**
     * 配置实例
     */
    private static $config;

    /**
     * 框架引导启动
     * 初始化基础设施（工具类、配置等）
     * @return bool
     * @throws BootstrapException
     */
    public static function initialize(): bool
    {
        if (self::$initialized) {
            return self::$initialized;
        }

        try {
            // 加载配置
            self::loadConfiguration();

            //  初始化工具类
            self::initializeUtils();

            //  初始化容器
            self::initializeContainer();


            self::$initialized = true;
        } catch (\Exception $e) {
            throw new BootstrapException(
                "Framework initialization failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        return self::$initialized;
    }

    /**
     * 加载配置文件
     */
    private static function loadConfiguration() {
        // 载入通用配置文件
        $config = new Config();
        $config -> load([
            'app',
            'database',
            'menu',
            'cron',
            'service',
        ]);
        self::$config = $config;
    }

    /**
     * 初始化容器
     */
    private static function initializeContainer() {
        $container = Container::getInstance();
        Facade::setContainer($container);

        // 注册完所有服务后，按优先级初始化
        self::registerCoreServices($container);
        $providers = self::registerProviders($container);

        $container->initializeServices();

        // 启动所有服务提供者
        self::bootProviders($container,$providers);
    }

    /**
     * 注册核心服务
     */
    private static function registerCoreServices(Container $container) {
        // $container = Container::getInstance();

        $container->singleton('config', function() {
            return self::$config;
        }, [
            'priority' => 1  // 最高优先级，
        ]);

        // 从配置文件加载服务定义
        $services = self::$config->get('service', []);
        foreach ($services as $name => $definition) {
            $container->singleton($name, function() use ($definition) {
                $class = $definition['class'];
                return new $class();
            }, [
                'priority' => $definition['priority'] ?? 10,
                'dependencies' => $definition['dependencies'] ?? []
            ]);
        }

    }

    /**
     * 注册服务提供者
     * @return array 返回注册的服务提供者实例数组
     */
    private static function registerProviders(Container $container): array
    {
        $providers = [];

        // 获取所有服务提供者
        $providerClasses = self::$config->get('app.providers', []);

        if(!empty($providerClasses)) {
            // 注册每个服务提供者
            foreach ($providerClasses as $providerClass) {
                if (class_exists($providerClass)) {
                    $provider = new $providerClass();
                    if ($provider instanceof ProviderInterface) {
                        $provider->register($container);
                        $providers[] = $provider; // 保存提供者实例
                    }
                }
            }
        }

        return $providers;
    }

    /**
     * 启动所有服务提供者
     * @param Container $container
     * @param array $providers
     */
    private static function bootProviders(Container $container, array $providers): void {
        if(!empty($providers)) {
            foreach ($providers as $provider) {
                $provider->boot($container);
            }
        }
    }

    /**
     * 初始化工具类
     */
    private static function initializeUtils() {
        // 注册加载器
        Loader::register();

        // 初始化语言工具
        Lang::init();
    }

    /**
     * 禁止实例化
     */
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}
}