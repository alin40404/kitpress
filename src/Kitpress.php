<?php
namespace kitpress;

use kitpress\core\abstracts\Initializable;
use kitpress\core\abstracts\Singleton;
use kitpress\core\Installer;
use kitpress\library\Backend;
use kitpress\library\Config;
use kitpress\library\Frontend;
use kitpress\library\RestApi;
use kitpress\utils\Cron;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Lang;
use kitpress\utils\Loader;
use kitpress\library\Session;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

// 定义框架基础常量
define('KITPRESS_VERSION', '1.1.0');
define('KITPRESS_NAME', 'kitpress');
define('KITPRESS___FILE__', __FILE__ );

// 框架根目录
define('KITPRESS_PATH', plugin_dir_path(KITPRESS___FILE__));
// 框架命名空间
define('KITPRESS_CORE_NAMESPACE', KITPRESS_NAME);
define('KITPRESS_TEXT_DOMAIN', md5(KITPRESS_NAME));

/**
 * 框架唯一入口类，提供外部调用。
 * 执行时刻没有限制
 */
class Kitpress extends Singleton {

    protected function __construct() {
        parent::__construct();
    }


    /**
     * 初始化钩子
     * @return void
     */
    public function initHooks() {
        // 同级别注册钩子
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'adminInit'));
        add_action('admin_menu', array($this, 'registerAdminMenus'));
        // 加载前台资源
        add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
        // 加载后台资源
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));
    }

    /**
     * 前端初始化
     * @return void
     */
    public function init() {
        // 初始化前台路由
        Frontend::getInstance()->init();
        // 初始化接口路由
        RestApi::getInstance()->init();

        // 注册后台路由
        Backend::getInstance() -> registerRoutes();

        // 初始化所有可初始化类
        self::initializeAll();

        // 初始化计划任务
        Cron::init();
    }

    /**
     * 后台初始化
     * @return void
     */
    public function adminInit() {
        Backend::getInstance() -> init();
    }

    public function registerAdminMenus() {
        // 注册后台管理菜单
        Backend::getInstance() -> registerAdminMenus();
    }

    public function enqueueScripts() {
        Frontend::getInstance() -> registerAssets();
    }

    public function enqueueAdminScripts($hook) {
        Backend::getInstance() -> registerAssets($hook);
    }
    /**
     * 加载语言包
     * @return void
     */
    public function loadLanguage() {
        // 在插件初始化时加载文本域，使用绝对路径。相对路径函数 load_plugin_textdomain 始终不生效。
        $text_domain = Config::get('app.text_domain');
        $locale = determine_locale();

        load_textdomain($text_domain, Kitpress::getRootPath() . 'languages/' . $text_domain . '-' . $locale . '.mo');
        load_textdomain(KITPRESS_TEXT_DOMAIN, KITPRESS_PATH  . 'languages/' . KITPRESS_TEXT_DOMAIN . '-' . $locale . '.mo');

        // 初始化语言包
        Lang::init();
    }


    /**
     * 插件根目录
     * @var null
     */
    private static $rootPath = null;

    private static $initialized = false;


    public static function setRootPath($rootPath){
        self::$rootPath = $rootPath;
    }

    public static function getRootPath(){
        return self::$rootPath;
    }

    /**
     * 确保类已经初始化
     * @return void
     */
    private static function ensureInitialized($rootPath = null)
    {
        if (!self::$initialized) {
            if(is_null(self::$rootPath)) self::setRootPath($rootPath);
            self::init();
            self::$initialized = true;
        }
    }

    /**
     * 初始化
     * @return void
     */
    private static function init()
    {
        // 注册自动加载类
         Loader::register();

        if(empty(self::$rootPath)) ErrorHandler::die(Lang::kit('插件根目录不正确'));

        // 载入通用配置文件
        Config::load([
            'app',
            'database',
            'menu',
            'cron',
        ]);

        // 开启调试模式，打印框架运行轨迹
        Log::debug('Kitpress 正在初始化...');

	    // 注入初始化类
	    $initClasses = Config::get('app.init');
	    if ($initClasses) {
		    foreach ($initClasses as $className) {
			    if (class_exists($className) && is_subclass_of($className, Initializable::class)) {
				    Plugin::registerInitializable(new $className());
			    }
		    }
	    }
    }

    /**
     * 激活
     * @return void
     */
    public static function activate($rootPath = null){
        self::ensureInitialized($rootPath);
        Installer::register();
    }

    /**
     * 手动执行 plugins_loaded 钩子
     * @return void
     */
    public static function loaded($rootPath = null) {
        // 激活钩子，必须在 plugins_loaded 钩子之前执行
        self::activate($rootPath);
        add_action('plugins_loaded', function () use ($rootPath){
            self::run($rootPath);
        }, 20);
    }


    /**
     * 直接执行
     * @return Plugin|mixed
     */
    public static function run($rootPath = null){
        self::ensureInitialized($rootPath);
        $instance = Plugin::getInstance();
        self::shutdown();
        return $instance;
    }

    public static function shutdown()
    {
        // WordPress 钩子系统
        add_action('shutdown', function () {
            Session::getInstance()->saveSession();
            Log::debug('Kitpress 已执行完毕');
        });
    }
}

