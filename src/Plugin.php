<?php
namespace kitpress;

use kitpress\core\abstracts\Initializable;
use kitpress\core\abstracts\Singleton;
use kitpress\library\Backend;
use kitpress\library\Frontend;
use kitpress\library\Config;
use kitpress\utils\Cron;
use kitpress\utils\Lang;

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
        Frontend::getInstance()->init();

        // 在 init 注册时，注册后台路由
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

}