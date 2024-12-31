<?php
namespace kitpress\controllers;

use kitpress\core\abstracts\Controller;
use function kitpress\functions\kp;

if (!defined('ABSPATH')) {
    exit;
}


abstract class BackendController extends Controller {

    protected array $styles = [];
    protected array $scripts = [];
    protected string $page = '';
    protected string $controllerName = '';
    protected string $formatControllerName = '';

    public function __construct() {
        parent::__construct();
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

        $this->setControllerName();

        $this->page = $this->plugin->getPrefix() . $this->formatControllerName;
    }

    protected function setControllerName(): void
    {
        // 获取基础类名（不含命名空间）
        $className = basename(static::class);

        // 移除 Controller 后缀
        $this->controllerName = str_replace('Controller', '', $className);

        // 将驼峰式转换为-格式
        $formatted = preg_replace('/(?<!^)[A-Z]/', '-$0', $this->controllerName);

        // 转换为小写
        $this->formatControllerName = strtolower($formatted);

    }

    /**
     * 生成前端 JavaScript 对象的名称
     * 将前缀从 kebab-case 或 snake_case 转换为 camelCase，并拼接控制器名和Admin后缀
     * 例如：kit-press -> kitPressModelsAdmin
     *
     * @return string 返回驼峰格式的脚本对象名称
     */
    protected function scriptObjectName() : string
    {
        // 获取前缀并转换格式
        $prefix = $this->plugin->getPrefix();
        // 移除可能存在的连字符和下划线
        $prefix = str_replace(['-', '_'], ' ', $prefix);
        // 将单词转为首字母大写
        $prefix = ucwords($prefix);
        // 移除空格并确保首字母小写
        $prefix = lcfirst(str_replace(' ', '', $prefix));

        // 返回格式: {camelCasePrefix}{controllerName}Admin
        // 例如: kit-press -> kitPressModelsAdmin
        return $prefix . $this->controllerName . 'Admin';
    }

    /**
     * 设置本地化脚本数据
     * 用于前端 AJAX 请求的配置数据
     *
     * @return array 包含 AJAX URL、操作名称和 nonce 的数组
     */
    protected function setL10n() : array
    {
        return [
            'ajaxurl' => \admin_url('admin-ajax.php'),
            'action_list' => $this->page . '-list',
            'action_create' => $this->page . '-create',
            'action_update' => $this->page . '-update',
            //'action_status' => $this->page . '-status',
            //'action_sort' => $this->page . '-sort',
            'nonce' => \wp_create_nonce($this->page .'-nonce')
        ];
    }

    /**
     * 本地化脚本
     * 将 PHP 变量传递给 JavaScript
     */
    protected function localizeScript()
    {
        // 添加必要的数据
        \wp_localize_script(
            $this->page,
            $this->scriptObjectName() ,
            $this->setL10n()
        );
    }

    /**
     * 设置并加载 CSS 样式文件
     */
    protected function setupCss()
    {
        // 加载多个样式文件
        $styles = [
            $this->page . '-common' => 'backend/assets/component/common.css',
            $this->page  => 'backend/assets/css/'. $this->formatControllerName .'.css',
        ];

        $styles = array_merge($styles, $this->styles);

        foreach ($styles as $handle => $path) {
            // 检查是否为绝对路径（以 http:// 或 https:// 开头）
            if (!preg_match('/^https?:\/\//', $path)) {
                $path = $this->plugin->getRootUrl() . $path;
            }

            \wp_enqueue_style(
                $handle . '-style',
                $path,
                [],
                $this->config->get('app.version')
            );
        }
    }

    /**
     * 设置并加载 Vue.js 组件
     */
    protected function setupJsComponent()
    {
        // 先加载 Vue
        \wp_enqueue_script(
            'vue',
            $this->plugin->getRootUrl() . 'backend/assets/component/vue/vue.min.js',
            [],
            '2.6.14',
            true
        );
    }

    /**
     * 设置并加载主要的 JavaScript 文件
     */
    protected function setupJs()
    {
        // 加载多个样式文件
        $scripts = [
            $this->page . '-common' => 'backend/assets/component/common.js',
            $this->page  => 'backend/assets/js/'. $this->formatControllerName .'.js',
        ];

        $scripts = array_merge($scripts, $this->scripts);

        foreach ($scripts as $handle => $path) {
            if (!preg_match('/^https?:\/\//', $path)) {
                $path = $this->plugin->getRootUrl() . $path;
            }

            // 设置依赖关系
            $deps = ['vue', 'jquery'];
            // 如果不是公共脚本，添加对公共脚本的依赖
            if ($handle !==  $this->page . '-common') {
                $deps[] =  $this->page . '-common-script';
            }

            \wp_enqueue_script(
                $handle . '-script',
                $path,
                $deps,
                $this->config->get('app.version'),
                true
            );
        }
    }

    /**
     * 设置并加载所有前端资源
     * 包括 CSS、JavaScript 组件和主要 JavaScript 文件
     */
    protected function setupAssets() {
        $this->setupCss();
        $this->setupJsComponent();
        $this->setupJs();
        $this->localizeScript();
    }

    /**
     * 加载前台资源
     */
    abstract public function enqueueAssets($hook);

}