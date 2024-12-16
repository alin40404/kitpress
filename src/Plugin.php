<?php
namespace kitpress;

use kitpress\core\abstracts\Singleton;
use kitpress\library\Backend;
use kitpress\library\Frontend;
use kitpress\utils\Config;
use kitpress\utils\Session;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件唯一入口类，在所有插件加载完成后执行
 */
class Plugin extends Singleton {

    protected function __construct() {
        parent::__construct();
        $this->loaded_plugins();
        $this->init_hooks();
    }


    private function loaded_plugins()
    {
        // 插件加载后触发
        $this->loadLanguage();
    }
    private function init_hooks() {
        // 同级别注册钩子
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'registerAdminMenus'));
        // 加载前台前台
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        // 加载前台后台
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * 前端初始化
     * @return void
     */
    public function init() {
        Frontend::getInstance()->init();
        // 在 init 注册时，注册后台路由
        Backend::getInstance() -> registerRoutes();
    }

    /**
     * 后台初始化
     * @return void
     */
    public function admin_init() {
        Backend::getInstance() -> init();
    }

    public function registerAdminMenus() {
        // 注册后台管理菜单
        Backend::getInstance() -> registerAdminMenus();
    }

    public function enqueue_scripts() {
        Frontend::getInstance() -> registerAssets();
    }

    public function enqueue_admin_scripts($hook) {
        Backend::getInstance() -> registerAssets($hook);
    }

    /**
     * 加载语言包
     * @return void
     */
    public function loadLanguage() {
        // 在插件初始化时加载文本域
        load_plugin_textdomain(Config::get('text_domain'), false, KITPRESS_PATH . 'languages/');
        load_plugin_textdomain(KITPRESS_TEXT_DOMAIN, false, KITPRESS_PATH . KITPRESS_CORE_NAMESPACE . '/languages/');
    }

}