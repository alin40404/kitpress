<?php
namespace kitpress;

use kitpress\core\Installer;
use kitpress\utils\Config;
use kitpress\utils\Loader;
use kitpress\utils\Session;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

// 定义框架基础常量
define('KITPRESS_VERSION', '1.0.0');
define('KITPRESS_NAME', 'kitpress');
define('KITPRESS___FILE__', __FILE__ );

// 框架根目录
define('KITPRESS_PATH', plugin_dir_path(KITPRESS___FILE__));
// 插件根目录
defined('KITPRESS_PLUGIN_PATH') || define('KITPRESS_PLUGIN_PATH', plugin_dir_path(dirname(KITPRESS___FILE__)));
// 插件URL
defined('KITPRESS_PLUGIN_URL') || define('KITPRESS_PLUGIN_URL', plugin_dir_url(dirname(KITPRESS___FILE__)));

// 框架命名空间
define('KITPRESS_CORE_NAMESPACE', KITPRESS_NAME);
define('KITPRESS_TEXT_DOMAIN', md5(KITPRESS_NAME));

// 引入核心文件
(function($files = []){
    try{
        foreach ($files as $file) {
            $fullPathFile = KITPRESS_PATH  . $file . '.php';
            if( !file_exists($fullPathFile) ) throw new \Exception($fullPathFile);
            require_once $fullPathFile;
        }
    }catch (\Exception $e){
        wp_die(
            esc_html__('Unable to load Kitpress framework core library file:', KITPRESS_TEXT_DOMAIN) .  $e->getMessage() ,  // 错误消息
            esc_html__('Kitpress Framework Error', KITPRESS_TEXT_DOMAIN),  // 标题
            array(
                'response' => 500,
                'back_link' => true
            )
        );
    }
})([
    'core/abstracts/Singleton',
    'core/traits/ConfigTrait',
    'utils/Log',
    'utils/Config',
//    'utils/Loader',
]);


/**
 * 框架唯一入口类，提供外部调用。
 * 执行时刻没有限制
 */
class Kitpress{

    private static $initialized = false;

    /**
     * 确保类已经初始化
     * @return void
     */
    private static function ensureInitialized()
    {
        if (!self::$initialized) {
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

        // 载入通用配置文件
        Config::load('app');

        // 开启调试模式，打印框架运行轨迹
        if( Config::get('app.features.debug_mode') ) Log::error('Kitpress 正在初始化...');

        // 配置开启会话
        if (self::needsSession()) {
            Session::start();
        }
    }

    /**
     * 判断是否需要开启会话
     * @return boolean
     */
    private static function needsSession()
    {
         // 判断是否在后台
         $isAdmin = is_admin();
        
         // 根据前后台分别获取对应的会话配置
         return $isAdmin 
             ? Config::get('app.session.backend', true)    // 后台默认开启
             : Config::get('app.session.frontend', false); // 前台默认关闭
    }

    /**
     * 激活
     * @return void
     */
    public static function activate(){
        self::ensureInitialized();
        Installer::register();
    }

    /**
     * 手动执行 plugins_loaded 钩子
     * @return void
     */
    public static function pluginsLoaded() {
        // 激活钩子，必须在 plugins_loaded 钩子之前执行
        self::activate();
        add_action('plugins_loaded', [__CLASS__, 'run'], 20);
    }


    /**
     * 直接执行
     * @return Plugin|mixed
     */
    public static function run(){
        self::ensureInitialized();
        $instance = Plugin::getInstance();
        self::shutdown();
        return $instance;
    }

    public static function shutdown()
    {
        // 在合适的位置添加关闭钩子
        register_shutdown_function(function() {
            if(Config::get('app.features.debug_mode')) {
                Log::error('Kitpress 已执行完毕');
            }
        });
    }
}

