<?php

namespace kitpress;

use kitpress\core\Container;
use kitpress\core\exceptions\BootstrapException;
use kitpress\core\Facades\Backend;
use kitpress\core\Facades\Bootstrap;
use kitpress\core\Facades\Frontend;
use kitpress\core\Facades\Log;
use kitpress\core\Facades\RestApi;
use kitpress\utils\Cron;
use kitpress\utils\ErrorHandler;
use kitpress\core\Facades\Session;
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
class Kitpress
{
    private static $instances = [];

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
     * 容器池
     * @var array
     */
    private static array $containers = [];

    /**
     * 当前容器
     * @var Container
     */
    private Container $container;

    /**
     * 构造函数
     * @param string $rootPath 插件根目录路径
     */
    protected function __construct(string $rootPath) {
        $this->setRootPath($rootPath);

        // 初始化容器
        $container = self::$containers[self::$namespace] ?? null;
        if( is_null($container) ) {
            $container = new Container(self::$namespace,KITPRESS_VERSION);
            self::$containers[self::$namespace] = $container;
        }

        $this->container = $container;

        // 框架引导启动
        \kitpress\core\Bootstrap::getInstance($container)->start();
    }

    /**
     * 创建或获取实例并初始化根路径
     * @param string $rootPath 插件根目录路径
     * @return static
     */
    public static function boot(string $rootPath): Kitpress
    {

        $class = static::class;
        if (!isset(static::$instances[$class])) {
            static::$instances[$class] = new static($rootPath);
        }
        return static::$instances[$class];
    }

    /**
     * 初始化钩子
     * @return void
     */
    private function initHooks()
    {
        Log::debug('初始化钩子');
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
        Frontend::init();

        // 初始化接口路由
        RestApi::init();

        // 注册后台路由
        Backend::registerRoutes();

        // 初始化所有可初始化类
        Bootstrap::initializeAll();

        // 初始化计划任务
        Cron::init();
    }

    /**
     * 后台初始化
     * @return void
     */
    public function adminInit()
    {
        Backend::init();
    }

    public function registerAdminMenus()
    {
        // 注册后台管理菜单
        Backend::registerAdminMenus();
    }

    public function enqueueScripts()
    {
        Frontend::registerAssets();
    }

    public function enqueueAdminScripts($hook)
    {
        Backend::registerAssets($hook);
    }

    public function setRootPath($rootPath)
    {
        if (empty($rootPath)) ErrorHandler::die('插件根目录不正确');
        if (!is_dir($rootPath)) ErrorHandler::die('插件根目录不正确');

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
        return $this;
    }

    public function shutdown()
    {
        // WordPress 钩子系统
        \add_action('shutdown', function () {
            Session::saveSession();
            Log::debug('Kitpress 已执行完毕');
        });
    }

    public static function getContainer(): Container
    {
        $container = self::$containers[self::$namespace] ?? null;
        if( is_null($container) ) {
            $container = new Container(self::$namespace,KITPRESS_VERSION);
        }
        return $container;
    }

    // 防止克隆
    private function __clone() {}

    // 防止反序列化
    private function __wakeup() {
        throw new \Exception('无法反序列化单例对象');
    }

    // 防止通过 serialize() 序列化
    private function __sleep() {
        throw new \Exception('无法序列化单例对象');
    }
}

