<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;
use kitpress\utils\Lang;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend extends Singleton {
    private $routes = [];
    private $namespace;

    protected function __construct() {
        parent::__construct();
        $this->loadRoutes();
    }

    public function init() {
        $this->initNamespace();
        $this->registerHooks();
    }

    private function loadRoutes() {
        Router::load('frontend');
        // 加载前台路由配置文件
        $this->routes = Router::get('frontend');
    }

    public function registerHooks() {
        $this->registerShortcodes();
        // 注册 AJAX 处理程序
        $this->registerAjaxHandlers();
        // 注册 POST 处理程序
        $this->registerPostHandlers();
    }

    public function registerShortcodes() {
        if( isset($this->routes['shortcodes']) && is_array($this->routes['shortcodes']) ) {
            foreach ($this->routes['shortcodes'] as $tag => $handler) {
                add_shortcode($tag, function($atts) use ($handler) {
                    return $this->handleShortcode($handler, $atts);
                });
            }
        }
    }

    public function registerAjaxHandlers() {

        if ( isset($this->routes['ajax']) && !empty($this->routes['ajax']) && is_array($this->routes['ajax'])) {
            // 注册需要登录的 AJAX 处理程序
            if ( isset($this->routes['ajax']['private']) && !empty($this->routes['ajax']['private']) && is_array($this->routes['ajax']['private']) ) {
                foreach ($this->routes['ajax']['private'] as $action => $handler) {
                    add_action('wp_ajax_' . $action, function() use ($handler) {
                        $this->handleAjaxRequest($handler);
                    });
                }
            }

            // 注册公共（不需要登录）的 AJAX 处理程序
            if ( isset($this->routes['ajax']['public']) &&!empty($this->routes['ajax']['public']) && is_array($this->routes['ajax']['public']) ) {
                foreach ($this->routes['ajax']['public'] as $action => $handler) {
                    // 登录用户可访问
                    add_action('wp_ajax_' . $action, function() use ($handler) {
                        $this->handleAjaxRequest($handler);
                    });
                    // 未登录用户可访问
                    add_action('wp_ajax_nopriv_' . $action, function() use ($handler) {
                        $this->handleAjaxRequest($handler);
                    });
                }
            }
        }
    }

    public function registerPostHandlers() {

        if ( isset($this->routes['post']) && !empty($this->routes['post']) && is_array($this->routes['post'])) {
            // 注册需要登录的 AJAX 处理程序
            if ( isset($this->routes['post']['private']) && !empty($this->routes['post']['private']) && is_array($this->routes['post']['private']) ) {
                foreach ($this->routes['post']['private'] as $action => $handler) {
                    add_action('admin_post_' . $action, function() use ($handler) {
                        $this->handleAjaxRequest($handler);
                    });
                }
            }

            // 注册公共（不需要登录）的 AJAX 处理程序
            if ( isset($this->routes['post']['public']) &&!empty($this->routes['post']['public']) && is_array($this->routes['post']['public']) ) {
                foreach ($this->routes['post']['public'] as $action => $handler) {
                    // 登录用户可访问
                    add_action('admin_post_' . $action, function() use ($handler) {
                        $this->handleAjaxRequest($handler);
                    });
                    // 未登录用户可访问
                    add_action('admin_post_nopriv_' . $action, function() use ($handler) {
                        $this->handleAjaxRequest($handler);
                    });
                }
            }
        }
    }


    /**
     * 注册和加载前端资源
     */
    public function registerAssets() {

        if( isset($this->routes['shortcodes']) && is_array($this->routes['shortcodes']) ) {
            foreach ($this->routes['shortcodes'] as $tag => $handler) {
                try {
                    list($controller, $method) = $this->parseHandler($handler);
                    $controllerClass = $this->namespace . $controller;

                    if (!class_exists($controllerClass)) {
                        throw new \Exception(sprintf(Lang::kit('控制器未找到： %s'), $controller));
                    }

                    $instance = new $controllerClass();

                    // 检查并调用 enqueueAssets 方法
                    if (method_exists($instance, 'enqueueAssets')) {
                        $instance->enqueueAssets();
                    }

                } catch (\Exception $e) {
                    // 在开发环境显示错误
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        Log::error($e->getMessage());
                    }
                }
            }
        }

    }

    /**
     * 处理短代码请求
     * @param string $handler 处理器（格式：Controller@method）
     * @param array $atts 短代码属性
     * @param string|null $content 短代码内容
     * @return string
     */
    private function handleShortcode($handler, $atts = [], $content = null) {
        try {
            list($controller, $method) = $this->parseHandler($handler);
            $controllerClass = $this->namespace . $controller;

            if (!class_exists($controllerClass)) {
                throw new \Exception(sprintf(Lang::kit('控制器未找到： %s'), $controller));
            }

            $instance = new $controllerClass();

            if (!method_exists($instance, $method)) {
                throw new \Exception(sprintf(Lang::kit('方法未找到：%s'), $method));
            }

            // 调用控制器方法，传入短代码属性和内容
            return call_user_func_array([$instance, $method], [$atts, $content]);

        } catch (\Exception $e) {
            // 在开发环境显示错误，生产环境返回空
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return $e->getMessage();
            }
            return '';
        }
    }

    /**
     * 处理 AJAX 请求
     * @param string $handler 处理器（格式：Controller@method）
     */
    private function handleAjaxRequest($handler) {
        try {
            list($controller, $method) = $this->parseHandler($handler);
            $controllerClass = $this->namespace . $controller;

            if (!class_exists($controllerClass)) {
                throw new \Exception(sprintf(Lang::kit('控制器未找到： %s'), $controller));
            }

            $instance = new $controllerClass();

            if (!method_exists($instance, $method)) {
                throw new \Exception(sprintf(Lang::kit('方法未找到：%s'), $method));
            }

            // 调用控制器方法
            call_user_func([$instance, $method]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    /**
     * 解析处理器字符串
     * @param string $handler 处理器字符串 (格式: Controller@method)
     * @return array [控制器名, 方法名]
     * @throws \Exception
     */
    private function parseHandler($handler) {
        if (!is_string($handler)) {
            throw new \Exception(sprintf(
            /* translators: %1$s: handler type, %2$s: expected format */
                Lang::kit('控制器类型无效。预期为字符串，实际为 %1$s。控制器格式应为：%2$s'),
                gettype($handler),
                'MyController@myMethod'
            ));
        }

        if (strpos($handler, '@') === false) {
            throw new \Exception(sprintf(
            /* translators: %1$s: invalid handler, %2$s: expected format */
                Lang::kit('控制器格式无效："%1$s"。控制器格式应为：%2$s'),
                $handler,
                'MyController@myMethod'
            ));
        }

        $parts = explode('@', $handler);
        if (count($parts) !== 2) {
            throw new \Exception(sprintf(
            /* translators: %1$s: invalid handler, %2$s: expected format */
                Lang::kit('控制器格式无效："%1$s"。包含过多的 "@" 符号。控制器格式应为：%2$s'),
                $handler,
                'MyController@myMethod'
            ));
        }

        list($controller, $method) = $parts;
        if (empty($controller) || empty($method)) {
            throw new \Exception(sprintf(
            /* translators: %1$s: invalid handler, %2$s: expected format */
                Lang::kit('控制器格式无效："%1$s"。控制器名称或方法名称为空。控制器格式应为：%2$s'),
                $handler,
                'MyController@myMethod'
            ));
        }

        return $parts;
    }


    protected function initNamespace()
    {
        // 默认命名空间
        $this->namespace =  Config::get('app.namespace') . '\\frontend\\controllers\\';
    }
}