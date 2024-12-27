<?php
namespace kitpress\controllers;

use kitpress\core\abstracts\Controller;
if (!defined('ABSPATH')) {
    exit;
}


abstract class BackendController extends Controller {

    public function __construct() {
        parent::__construct();
        // $this->init();  // 添加这一行
    }

    /**
     * 在admin_init钩子之后加载
     * @return void
     */
    protected function init() {
        parent::init();

        $this->viewPath = 'backend/views';
        $this->layout = 'default';
        $this->viewData = [];

        // 初始化前台控制器
        $this->setupHooks();
    }

    protected function setupHooks() {

    }

    /**
     * 加载前台资源
     */
    abstract public function enqueueAssets($hook);

}