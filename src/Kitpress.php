<?php

namespace kitpress;

use kitpress\core\abstracts\Singleton;
use kitpress\core\Bootstrap;
use kitpress\core\exceptions\BootstrapException;
use kitpress\library\Installer;
use kitpress\library\Backend;
use kitpress\library\Frontend;
use kitpress\library\RestApi;
use kitpress\utils\Cron;
use kitpress\utils\ErrorHandler;
use kitpress\core\Facades\Session;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

// 定义框架基础常量
define('KITPRESS_VERSION', '1.1.0');
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
    private $rootPath;


    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * 初始化钩子
     * @return void
     */
    public function initHooks()
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
        Frontend::getInstance()->init();

        // 初始化接口路由
        RestApi::getInstance()->init();

        // 注册后台路由
        Backend::getInstance()->registerRoutes();

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
        Backend::getInstance()->init();
    }

    public function registerAdminMenus()
    {
        // 注册后台管理菜单
        Backend::getInstance()->registerAdminMenus();
    }

    public function enqueueScripts()
    {
        Frontend::getInstance()->registerAssets();
    }

    public function enqueueAdminScripts($hook)
    {
        Backend::getInstance()->registerAssets($hook);
    }

    private static function setRootPath($rootPath)
    {

        if (empty($rootPath)) ErrorHandler::die('插件根目录不正确');
        if (!is_dir($rootPath)) ErrorHandler::die('插件根目录不正确');

        self::getInstance()->rootPath = $rootPath;
    }

    public static function getRootPath()
    {
        return self::getInstance()->rootPath;
    }

    public static function activate($rootPath){
        Installer::activate($rootPath);
    }

    /**
     * 加载插件
     * @return void
     */
    public static function loaded($rootPath)
    {
        self::getInstance()->activate($rootPath);

        \add_action('plugins_loaded', function () use ($rootPath) {
            self::run($rootPath);
        }, 20);
    }

    /**
     * 运行
     * @param $rootPath 插件根目录
     * @return Kitpress|mixed
     */
    public static function run($rootPath)
    {
        $instance = self::getInstance();
        try {

            $instance->setRootPath($rootPath);
            // 使用 Bootstrap 初始化框架
            Bootstrap::initialize();
            // 初始化钩子
            $instance->initHooks();
            $instance->shutdown();

        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
        return $instance;
    }

    public static function shutdown()
    {
        // WordPress 钩子系统
        \add_action('shutdown', function () {
            Session::saveSession();
            Log::debug('Kitpress 已执行完毕');
        });
    }
}

