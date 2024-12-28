<?php

namespace kitpress;

use kitpress\core\abstracts\Singleton;
use kitpress\core\Bootstrap;
use kitpress\core\Container;
use kitpress\core\exceptions\BootstrapException;
use kitpress\utils\Cron;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Helper;

if (!defined('ABSPATH')) {
    exit;
}

// 定义框架基础常量
define('KITPRESS_VERSION', '1.0.2');
define('KITPRESS_NAME', 'kitpress');
define('KITPRESS___FILE__', __FILE__);
// 框架根目录
define('KITPRESS_PATH', \plugin_dir_path(KITPRESS___FILE__));
// 框架命名空间
define('KITPRESS_CORE_NAMESPACE', KITPRESS_NAME);
define('KITPRESS_TEXT_DOMAIN', md5(KITPRESS_NAME));

/**
 * 框架唯一入口类，提供外部调用。
 * 执行时刻没有限制
 */
class Kitpress extends Singleton
{

    /**
     * 插件根目录
     * @var null
     */
    private static array $rootPaths = [];

    /**
     * 添加命名空间标识
     * @var string
     */
    private static string $namespace = '';


    /**
     * 当前容器
     * @var Container
     */
    private Container $container;

    /**
     * 构造函数
     * @param string $rootPath 插件根目录路径
     */
    protected function __construct() {
        parent::__construct();
    }


    /**
     * 获取插件实例
     * @return static
     */
    public static function getInstance(): self
    {
        if(empty(self::$namespace)){
            throw new \RuntimeException('Kitpress框架初始化失败，请检查插件路径是否设置正确');
        }
        return parent::getInstance();
    }

    /**
     * 创建或获取实例并初始化根路径
     * @param string $rootPath 插件根目录路径
     * @return static
     */
    public static function boot(string $rootPath): Kitpress
    {
        self::setRootPath($rootPath);
        self::includes($rootPath);

        $instance = self::getInstance();
        $instance->container = Container::getInstance(self::$namespace,KITPRESS_VERSION);

        // 框架引导启动
        try {
            Bootstrap::boot($instance->container)->start();
            // error_log('命名空间： ' . $instance->container->getNamespace());
            // error_log('注册了服务：' . json_encode($instance->container->getServices()));
        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }

        return $instance;
    }

    /**
     * 初始化钩子
     * @return void
     */
    private function initHooks()
    {
        // $this->container->get('log')->error('容器服务：' . json_encode($this->container->getServices()));
        // $this->container->get('log')->error('初始化钩子' . $this->container->getNamespace());
        // WordPress 默认优先级是 10
        // 优先级数字越小越早执行，越大越晚执行
        \add_action('init', array($this, 'init'), 10);
        \add_action('admin_init', array($this, 'adminInit'), 10);
        \add_action('admin_menu', array($this, 'registerAdminMenus'), 10);
        \add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'), 10);
        \add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'), 10);
    }

    /**
     * 前端初始化
     * @return void
     */
    public function init()
    {
        // 初始化前台路由
        $this->container->get('frontend')->init();

        // 初始化接口路由
        $this->container->get('restapi')->init();

        // 注册后台路由
        $this->container->get('backend')->registerRoutes();

        // 初始化所有可初始化类
        Bootstrap::boot($this->container)->initializeAll();

        // 初始化计划任务
        Cron::init();
    }

    /**
     * 后台初始化
     * @return void
     */
    public function adminInit()
    {
        $this->container->get('backend')->init();
    }

    public function registerAdminMenus()
    {
        // 注册后台管理菜单
        $this->container->get('backend')->registerAdminMenus();
    }

    public function enqueueScripts()
    {
        $this->container->get('frontend')->registerAssets();
    }

    public function enqueueAdminScripts($hook)
    {
        $this->container->get('backend')->registerAssets($hook);
    }

    private static function includes(string $rootPath){
        $files = [
            'function',
        ];

        // 移除末尾的斜杠
        $rootPath = rtrim($rootPath, '/\\');

        // 引入助手函数
        foreach ($files as $file) {

            if(file_exists($rootPath . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . $file . '.php')){
                require_once $rootPath . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . $file . '.php';
            }

            if(file_exists(KITPRESS_PATH . 'functions' . DIRECTORY_SEPARATOR . $file . '.php')){
                require_once KITPRESS_PATH . 'functions' . DIRECTORY_SEPARATOR . $file . '.php';
            }
        }
    }

    private static function setRootPath($rootPath)
    {
        if (empty($rootPath) || !is_dir($rootPath)) ErrorHandler::die('插件根目录不正确');

        self::$namespace = Helper::key($rootPath);

        if( isset( self::$rootPaths[self::$namespace]) ) return;

        self::$rootPaths[self::$namespace] = $rootPath;
    }

    public static function getRootPath(string $namespace)
    {
        if( !isset(self::$rootPaths[$namespace])) throw new \Exception('插件目录不存在');
        return self::$rootPaths[$namespace];
    }

    public function activate(){
        $this->container->get('installer')->register();
    }

    /**
     * 加载插件
     * @return void
     */
    public function loaded()
    {
        $this->activate();

        \add_action('plugins_loaded', function () {
            $this->run();
        }, 20);
    }

    /**
     * 运行
     * @param $rootPath 插件根目录
     * @return Kitpress|mixed
     */
    public function run()
    {
        try{
            // 初始化钩子
            $this->initHooks();
            $this->shutdown();

        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
        return;
    }

    public function shutdown()
    {
        // WordPress 钩子系统
        \add_action('shutdown', function () {
            $this->container->get('session')->saveSession();
            $this->container->get('log')->debug('Kitpress已执行完毕');
        });
    }

    public static function getNamespace(): string
    {
        return self::$namespace;
    }

    public static function getContainer(): Container
    {
        return self::getInstance()->container;
    }

    public static function setContainer(): void
    {
        $instance = self::getInstance();
        $instance->container = Container::getInstance(self::$namespace,KITPRESS_VERSION);
        return;
    }

    /**
     * 切换当前使用的容器命名空间
     * @param string $namespace
     */
    public static function useNamespace(string $namespace): void
    {

        try {
            // 检查容器是否已经设置
            Container::checkContainer($namespace);
            self::$namespace = $namespace;
            self::setContainer();
        }catch (\RuntimeException $e){
            ErrorHandler::die($e->getMessage());
        }

    }

}

