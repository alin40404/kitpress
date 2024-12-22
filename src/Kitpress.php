<?php
namespace kitpress;

use kitpress\core\abstracts\Initializable;
use kitpress\core\Installer;
use kitpress\library\Config;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Lang;
use kitpress\utils\Loader;
use kitpress\library\Session;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

// 定义框架基础常量
define('KITPRESS_VERSION', '1.0.1');
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
class Kitpress{

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

