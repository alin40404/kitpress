<?php

namespace kitpress;

use kitpress\core\abstracts\Singleton;
use kitpress\core\Bootstrap;
use kitpress\core\Container;
use kitpress\core\exceptions\BootstrapException;
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
 */
class Kitpress extends Singleton
{

    /**
     * 插件根目录路径数组
     * @var array 键为命名空间，值为路径
     */
    private static array $rootPaths = [];

    /**
     * 当前插件命名空间标识
     * @var string
     */
    private static string $namespace = '';


    /**
     * 当前容器实例
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
     * 如果命名空间未设置会抛出异常
     * 
     * @return static
     * @throws \RuntimeException
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
     * 框架入口方法，用于初始化插件
     * 
     * @param string $rootPath 插件根目录路径
     * @return static
     */
    public static function boot(string $rootPath): Kitpress
    {
        self::setRootPath($rootPath);
        self::includes($rootPath);

        $instance = self::getInstance();
        $instance->container = Container::getInstance(self::$namespace,KITPRESS_VERSION);

        return $instance;
    }

    /**
     * 初始化WordPress钩子
     * 注册所有需要的WordPress动作和过滤器
     * 
     * @return void
     */
    private function initHooks()
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
        Bootstrap::boot($this->container)->initializeAll();

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
     * 引入助手函数文件
     * 加载框架和插件的函数文件
     * 
     * @param string $rootPath 插件根目录路径
     * @return void
     */
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

    /**
     * 设置插件根目录路径
     * 生成并存储插件的命名空间标识
     * 
     * @param string $rootPath 插件根目录路径
     * @return void
     * @throws \RuntimeException
     */
    private static function setRootPath($rootPath)
    {
        if (empty($rootPath) || !is_dir($rootPath)) ErrorHandler::die('插件根目录不正确');

        self::$namespace = Helper::key($rootPath);

        if( isset( self::$rootPaths[self::$namespace]) ) return;

        self::$rootPaths[self::$namespace] = $rootPath;
    }

    /**
     * 获取指定命名空间的插件根目录路径
     * 
     * @param string $namespace 插件命名空间
     * @return string
     * @throws \Exception
     */
    public static function getRootPath(string $namespace)
    {
        if( !isset(self::$rootPaths[$namespace])) throw new \Exception('插件目录不存在');
        return self::$rootPaths[$namespace];
    }

    /**
     * 插件激活回调
     * 执行插件激活时的必要操作
     *
     * @return void
     * @throws BootstrapException
     */
    public function activate(){
        try{
            Bootstrap::boot($this->container)->start();
            $this->container->get('installer')->activate();
        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
    }

    /**
     * 插件停用回调
     * 执行插件停用时的清理操作
     *
     * @return void
     * @throws BootstrapException
     */
    public function deactivate()
    {
        try{
            Bootstrap::boot($this->container)->start();
            $this->container->get('installer')->deactivate();
        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
    }

    /**
     * 加载插件
     * 注册plugins_loaded钩子以启动插件
     *
     * @return void
     */
    public function loaded(): void
    {
        \add_action('plugins_loaded', function () {
            $this->run();
        }, 20);
    }

    /**
     * 运行插件
     * 执行插件的主要初始化流程
     *
     * @return void
     * @throws BootstrapException
     */
    public function run(): void
    {
        try{
            // 框架引导启动
            Bootstrap::boot($this->container)->start();

            // 初始化钩子
            $this->initHooks();
            $this->shutdown();

        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
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
    private function shutdown(): void
    {
        // WordPress 钩子系统
        \add_action('shutdown', function () {
            $this->container->get('session')->saveSession();
            $this->container->get('log')->debug('Kitpress已执行完毕');
        });
    }

    /**
     * 获取当前插件的命名空间
     *
     * @return string
     */
    public static function getNamespace(): string
    {
        return self::$namespace;
    }

    /**
     * 获取当前容器实例
     *
     * @return Container
     */
    public static function getContainer(): Container
    {
        return self::getInstance()->container;
    }

    /**
     * 设置当前容器实例
     * 根据当前命名空间重新创建容器
     *
     * @return void
     */
    public static function setContainer(): void
    {
        $instance = self::getInstance();
        $instance->container = Container::getInstance(self::$namespace,KITPRESS_VERSION);
    }

   /**
     * 切换当前使用的容器命名空间
     * 用于在多插件环境中切换上下文
     * 
     * @param string $namespace 目标命名空间
     * @return void
     * @throws \RuntimeException
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

