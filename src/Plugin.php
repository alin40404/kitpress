<?php
namespace kitpress;

use kitpress\core\abstracts\Initializable;
use kitpress\core\abstracts\Singleton;
use kitpress\library\Backend;
use kitpress\library\Frontend;
use kitpress\utils\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件唯一入口类，在所有插件加载完成后执行
 */
class Plugin extends Singleton {
	private static $container = [];

    protected function __construct() {
        parent::__construct();
        $this->loadedPlugins();
        $this->initHooks();
    }

	/**
	 * 注册一个可初始化的类
	 * @param Initializable $instance
	 */
	public static function registerInitializable(Initializable $instance) {
		// 检查实例是否已经注册
		if (!in_array($instance, self::$container, true)) {
			self::$container[] = $instance;
		}
	}

	/**
	 * 初始化所有注册的类
	 */
	private static function initializeAll() {
		foreach (self::$container as $initializable) {
			$initializable->init();
		}
	}

    private function loadedPlugins()
    {
        // 插件加载后触发
        $this->loadLanguage();
    }
    private function initHooks() {
        // 同级别注册钩子
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'adminInit'));
        add_action('admin_menu', array($this, 'registerAdminMenus'));
        // 加载前台前台
        add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
        // 加载前台后台
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminScripts'));
    }

    /**
     * 前端初始化
     * @return void
     */
    public function init() {
        Frontend::getInstance()->init();

        // 在 init 注册时，注册后台路由
        Backend::getInstance() -> registerRoutes();

	    // 初始化所有可初始化类
	    self::initializeAll();
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
        // 在插件初始化时加载文本域，使用相对路径。注意函数 load_textdomain 使用绝对路径和文件名
        load_plugin_textdomain(Config::get('app.text_domain'), false, dirname(plugin_basename(Kitpress::getRootPath())) . 'languages/');
        load_plugin_textdomain(KITPRESS_TEXT_DOMAIN, false, dirname(plugin_basename(KITPRESS_PATH))  . 'languages/');
    }

}