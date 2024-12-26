<?php
namespace kitpress\core;

use kitpress\core\abstracts\Facade;
use kitpress\core\abstracts\Initializable;
use kitpress\core\abstracts\Singleton;
use kitpress\core\exceptions\BootstrapException;
use kitpress\core\Facades\Log;
use kitpress\core\interfaces\ProviderInterface;
use kitpress\utils\Lang;



if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap extends Singleton {
    private ?Container $container;
    /**
     * 可初始化的实例服务
     */
    private array $initializables = [];

    protected function __construct(Container $container) {
        parent::__construct();
        $this->container = $container;
    }

    public static function getInstance(Container $container = null)
    {
        $instance = parent::getInstance();
        if ($container !== null && !isset($instance->container)) {
            $instance->container = $container;
        }
        return $instance;
    }

    /**
     * 框架引导启动
     * 初始化基础设施（工具类、配置等）
     * @return bool
     * @throws BootstrapException
     */
    public function start(): bool
    {
        $container = $this->container;

        if (empty( $container->getNamespace() )) {
            throw new BootstrapException('插件命名空间必须在初始化之前设置');
        }

        try {

            // 检查该容器是否已经初始化
            if ($container->isInitialized()) {
                return true;
            }

            // 初始化基础容器
            $this->initializeBaseContainer();

            //  初始化容器
            $this->initializeContainer();

            //  初始化工具类
            $this->initializeUtils();

            // 加载语言包
            $this->loadLanguage();

            // 从配置中注册初始化类（作为服务注册）
            $this->registerInitializableServices();

            // 开启调试模式，打印框架运行轨迹
            Log::debug('Kitpress 正在初始化 [Namespace: ' . $container->getNamespace() . ', Version: ' . $container->getVersion() . ']');
            Log::debug('插件根目录：' . $container->plugin->getRootPath());
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
    private function initializeBaseContainer(): void
    {
        // 首先将自身注册到容器中
        $this->container->singleton('bootstrap', function() {
            return $this;
        });

        // 使用框架的命名空间和版本初始化容器
        Facade::setContainer($this->container);

        // 加载配置
        $this->loadConfiguration();
    }

    /**
     * 加载语言包
     * @return void
     */
    private function loadLanguage(): void {
        $config = $this->container->config;
        $plugin = $this->container->plugin;

        // 在插件初始化时加载文本域，使用绝对路径。相对路径函数 load_plugin_textdomain 始终不生效。
        $text_domain = $config->get('app.text_domain');
        $locale = \determine_locale();

        \load_textdomain($text_domain, $plugin->getRootPath() . 'languages/' . $text_domain . '-' . $locale . '.mo');
        \load_textdomain(KITPRESS_TEXT_DOMAIN, KITPRESS_PATH  . 'languages/' . KITPRESS_TEXT_DOMAIN . '-' . $locale . '.mo');
    }

    /**
     * 加载配置文件
     */
    private function loadConfiguration() {
        $container = $this->container;

        // 注册 Plugin 服务
        $container->singleton('plugin', function($container) {
            return new \kitpress\library\Plugin($container->getNamespace());
        });

        $container->singleton('config', function($container) {
            return new \kitpress\library\Config($container->get('plugin'));
        });

        $container->singleton('log', function($container) {
            return new \kitpress\library\Log($container->get('plugin'), $container->get('config'));
        });

        $container->singleton('loader', function($container) {
            return new \kitpress\library\Loader($container->get('plugin'), $container->get('config'), $container->get('log'));
        });

        // 载入通用配置文件
        $container->get('config')->load([
            'app',
            'cron',
            'service',
        ]);

        $container->get('loader')->register();
    }

    /**
     * 初始化容器
     */
    private function initializeContainer() {

        //  首先注册服务提供者（最高优先级）
        $providers = $this->registerProviders();

        //  注册核心服务（次高优先级）
        $this->registerCoreServices();

        //  初始化所有服务
        $this->container->initializeServices();

        //  启动服务提供者
        $this->bootProviders($providers);

    }

    /**
     * 注册核心服务
     */
    private function registerCoreServices() {

        $container = $this->container;
        // 注册配置服务
        $config = $container->get('config');

        // 从配置文件加载服务定义
        $services = $config->get('service', []);
        foreach ($services as $name => $definition) {
            // 检查服务是否已经注册
            if (!$container->has($name)) {
                $container->singleton($name, function() use ($definition) {
                    $class = $definition['class'];
                    return new $class();
                }, [
                    'priority' => $definition['priority'] ?? 10,
                    'dependencies' => $definition['dependencies'] ?? []
                ]);
            }
        }

    }

    /**
     * 注册服务提供者
     * @return array 返回注册的服务提供者实例数组
     */
    private function registerProviders(): array
    {
        $container = $this->container;

        $providers = [];

        // 获取所有服务提供者
        $providerClasses = $container->get('config')->get('app.providers', []);

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
     * @param array $providers
     */
    private function bootProviders(array $providers): void {
        if(!empty($providers)) {
            foreach ($providers as $provider) {
                $provider->boot($this->container);
            }
        }
    }

    /**
     * 初始化工具类
     */
    private function initializeUtils() {

        // 初始化语言工具
        Lang::init();

    }

    /**
     * 将初始化类注册为服务
     */
    private function registerInitializableServices(): void {
        $container = $this->container;

        $initClasses = $container->config->get('app.init');
        if ($initClasses) {
            foreach ($initClasses as $className) {
                if (class_exists($className) && is_subclass_of($className, Initializable::class)) {
                    $this->registerInitializable(new $className());
                }
            }
        }
    }

    /**
     * 注册一个可初始化的实例
     */
    public function registerInitializable(Initializable $instance): void {
        if (!in_array($instance, $this->initializables, true)) {
            $this->initializables[] = $instance;
        }
    }

    /**
     * 初始化所有注册的类
     */
    public function initializeAll(): void {
        foreach ($this->initializables as $initializable) {
            try {
                $initializable->init();
            } catch (\Exception $e) {
                // 错误处理，避免一个初始化失败影响其他初始化
                Log::error('Failed to initialize: ' . get_class($initializable) . ' - ' . $e->getMessage());
            }
        }
    }
}