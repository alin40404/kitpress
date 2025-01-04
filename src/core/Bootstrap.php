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

    protected function __construct(Container $container = null) {
        parent::__construct();
        $this -> container = $container;
    }

    /**
     * 获取插件实例
     * 如果命名空间未设置会抛出异常
     *
     * @return static
     * @throws \RuntimeException
     */
    public static function getInstance(Container $container = null): self
    {
        if(empty($container)){
            throw new \RuntimeException('Bootstrap 容器设置不正确');
        }

        $key = md5($container->getNamespace() . '\\' . static::class);

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new static($container);
        }

        return self::$instances[$key];
    }

    public static function boot(Container $container = null): Bootstrap
    {
        $instance = self::getInstance($container);
        if ($container !== null ) {
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
     * @return void
     *
     */
    private function loadConfiguration(): void
    {
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
            return new \kitpress\library\Loader($container->get('log'));
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
                $container->singleton($name, function($container) use ($definition) {
                    $class = $definition['class'];

                    // 获取构造函数的依赖
                    $dependencies = [];
                    if (!empty($definition['dependencies'])) {
                        foreach ($definition['dependencies'] as $dep) {
                            $dependencies[] = $container->get($dep);
                        }
                    }

                    // 使用依赖创建实例
                    return new $class(...$dependencies);
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
        Lang::init($this->container->getNamespace());

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
    private function registerInitializable(Initializable $instance): void {
        if (!in_array($instance, $this->initializables, true)) {
            $this->initializables[] = $instance;
        }
    }

    /**
     * 初始化所有注册的类
     */
    private function initializeAll(): void {
        foreach ($this->initializables as $initializable) {
            try {
                $initializable->init();
            } catch (\Exception $e) {
                // 错误处理，避免一个初始化失败影响其他初始化
                Log::error('Failed to initialize: ' . get_class($initializable) . ' - ' . $e->getMessage());
            }
        }
    }

    /**
     * 初始化WordPress钩子
     * 注册所有需要的WordPress动作和过滤器
     *
     * @return void
     */
    public function run() : void
    {
        // $this->container->get('log')->error('容器服务：' . json_encode($this->container->getServices()));
        // $this->container->get('log')->error('初始化钩子' . $this->container->getNamespace());
        // WordPress 默认优先级是 10
        // 优先级数字越小越早执行，越大越晚执行
        \add_action('init', array($this, 'init'), 10);
        if( \is_admin() ){
            \add_action('admin_init', array($this, 'adminInit'), 10);
            \add_action('admin_menu', array($this, 'registerAdminMenus'), 10);
            \add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'), 10);
        }
        \add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'), 10);
    }

    /**
     * WordPress init钩子回调
     * 初始化前台路由、REST API和后台路由
     *
     * @return void
     */
    public function init()
    {
        // 初始化前台路由
        $this->container->get('frontend')->init();

        // 初始化接口路由
        $this->container->get('restapi')->init();

        if ( \is_admin() ) {
            // 注册后台路由
            $this->container->get('backend')->registerRoutes();
        }

        // 初始化所有可初始化类
        $this->initializeAll();

    }

    /**
     * WordPress admin_init钩子回调
     * 初始化后台功能
     *
     * @return void
     */
    public function adminInit()
    {
        $this->container->get('backend')->init();
    }

    /**
     * 注册后台菜单
     * WordPress admin_menu钩子回调
     *
     * @return void
     */
    public function registerAdminMenus()
    {
        // 注册后台管理菜单
        $this->container->get('backend')->registerAdminMenus();
    }

    /**
     * 注册前端资源
     * WordPress wp_enqueue_scripts钩子回调
     *
     * @return void
     */
    public function enqueueScripts()
    {
        $this->container->get('frontend')->registerAssets();
    }

    /**
     * 注册后台资源
     * WordPress admin_enqueue_scripts钩子回调
     *
     * @param string $hook 当前后台页面的钩子名称
     * @return void
     */
    public function enqueueAdminScripts($hook)
    {
        $this->container->get('backend')->registerAssets($hook);
    }


    /**
     * 注册WordPress关闭时的回调函数
     * 在请求结束时执行会话保存和日志记录
     *
     * 该方法会在WordPress的shutdown钩子中：
     * 1. 保存会话数据
     * 2. 记录框架执行完成的日志
     *
     * @see add_action() WordPress钩子API
     * @see Container::get() 容器服务获取
     * @return void
     */
    public function shutdown(): void
    {
        // WordPress 钩子系统
        \add_action('shutdown', function () {
            $this->container->get('session')->saveSession();
            $this->container->get('log')->debug('Kitpress已执行完毕');
        });
    }

}