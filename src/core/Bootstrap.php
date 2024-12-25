<?php
namespace kitpress\core;

use kitpress\core\abstracts\Facade;
use kitpress\core\abstracts\Initializable;
use kitpress\core\exceptions\BootstrapException;
use kitpress\core\Facades\Plugin;
use kitpress\core\interfaces\ProviderInterface;
use kitpress\Kitpress;
use kitpress\library\Config;
use kitpress\utils\Lang;
use kitpress\utils\Loader;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap {

    /**
     * 配置实例
     */
    private static $config;

    /**
     * 可初始化的实例容器
     */
    private static array $initializables = [];

    /**
     * 插件命名空间
     */
    private static string $namespace = '';

    /**
     * 插件版本
     */
    private static string $version = '';

    /**
     * 设置插件信息
     * @param string $namespace 插件命名空间
     * @param string $version 插件版本
     */
    public static function configurePlugin(string $namespace, string $version): void {
        self::$namespace = $namespace;
        self::$version = $version;
    }

    /**
     * 框架引导启动
     * 初始化基础设施（工具类、配置等）
     * @return bool
     * @throws BootstrapException
     */
    public static function initialize(): bool
    {
        if (empty(self::$namespace)) {
            throw new BootstrapException('插件命名空间必须在初始化之前设置');
        }

        try {
            // 获取或创建对应命名空间的容器实例
            $container = Container::getInstance(self::$namespace, self::$version);

            // 检查该容器是否已经初始化
            if ($container->isInitialized()) {
                return true;
            }

            // 初始化基础容器
            self::initializeBaseContainer();

            // 加载配置
            self::loadConfiguration();

            //  初始化容器
            self::initializeContainer();

            // 保存插件命名空间，以便在其他地方调用
            Plugin::setNamespace(self::$namespace);

            //  初始化工具类
            self::initializeUtils();

            // 加载语言包
            self::loadLanguage();


            // 开启调试模式，打印框架运行轨迹
            Log::debug('Kitpress 正在初始化 [Namespace: ' . self::$namespace . ', Version: ' . self::$version . ']');
            Log::debug('插件根目录：' . Plugin::getRootPath());
            Log::debug('初始化基础容器');
            Log::debug('加载核心配置文件');

            Log::debug('注册服务提供者');
            Log::debug('注册核心服务');
            Log::debug('将初始化类注册为服务');
            Log::debug('初始化所有服务');
            Log::debug('启动服务提供者');

            Log::debug('初始化工具类');
            Log::debug('加载语言包');

        } catch (\Exception $e) {
            throw new BootstrapException(
                "Kitpress初始化失败，请检查配置文件: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        return true;
    }

    /**
     * 初始化基础容器
     * 在框架启动最开始时执行，确保 Facade 可用
     */
    private static function initializeBaseContainer(): void
    {
        // 使用框架的命名空间和版本初始化容器
        $container = Container::getInstance(self::$namespace, self::$version);
        Facade::setContainer($container);
    }

    /**
     * 加载语言包
     * @return void
     */
    public static function loadLanguage(): void {
        // 在插件初始化时加载文本域，使用绝对路径。相对路径函数 load_plugin_textdomain 始终不生效。
        $text_domain = self::$config->get('app.text_domain');
        $locale = \determine_locale();

        \load_textdomain($text_domain, Kitpress::getRootPath() . 'languages/' . $text_domain . '-' . $locale . '.mo');
        \load_textdomain(KITPRESS_TEXT_DOMAIN, KITPRESS_PATH  . 'languages/' . KITPRESS_TEXT_DOMAIN . '-' . $locale . '.mo');
    }

    /**
     * 加载配置文件
     */
    private static function loadConfiguration() {
        // 载入通用配置文件
        $config = new Config();
        $config -> load([
            'app',
            'cron',
            'service',
        ],self::$namespace);
        self::$config = $config;
    }

    /**
     * 初始化容器
     */
    private static function initializeContainer() {
        $container = Container::getInstance(self::$namespace, self::$version);

        // 1. 首先注册服务提供者（最高优先级）
        $providers = self::registerProviders($container);

        // 2. 注册核心服务（次高优先级）
        self::registerCoreServices($container);

        // 3. 从配置中注册初始化类（作为服务注册）
        self::registerInitializableServices($container);

        // 4. 初始化所有服务
        $container->initializeServices();

        // 5. 启动服务提供者
        self::bootProviders($container, $providers);

    }

    /**
     * 注册核心服务
     */
    private static function registerCoreServices(Container $container) {
        // 注册配置服务
        $container->singleton('config', function() {
            return self::$config;
        }, [
            'priority' => 1
        ]);

        // 从配置文件加载服务定义
        $services = self::$config->get('service', []);
        foreach ($services as $name => $definition) {
            // 服务ID会自动添加命名空间前缀
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
     * 将初始化类注册为服务
     */
    private static function registerInitializableServices(Container $container): void {
        $initClasses = self::$config->get('app.init');
        if ($initClasses) {
            foreach ($initClasses as $className) {
                if (class_exists($className) && is_subclass_of($className, Initializable::class)) {
                    // 服务ID会自动添加命名空间前缀
                    $container->singleton($className, $className, [
                        'priority' => 20,
                        'boot' => function($instance) {
                            self::registerInitializable($instance);
                        }
                    ]);
                }
            }
        }
    }

    /**
     * 注册一个可初始化的实例
     */
    public static function registerInitializable(Initializable $instance): void {
        if (!in_array($instance, self::$initializables, true)) {
            self::$initializables[] = $instance;
        }
    }

    /**
     * 初始化所有注册的类
     */
    public static function initializeAll(): void {
        foreach (self::$initializables as $initializable) {
            try {
                $initializable->init();
            } catch (\Exception $e) {
                // 错误处理，避免一个初始化失败影响其他初始化
                Log::error('Failed to initialize: ' . get_class($initializable) . ' - ' . $e->getMessage());
            }
        }
    }

    /**
     * 禁止实例化
     */
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}
}