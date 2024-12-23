<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Lang;

if (!defined('ABSPATH')) {
    exit;
}

class Backend extends Singleton {
    private $routes = [];
    private $menus = [];
    private $namespace;

    protected function __construct() {
        parent::__construct();
        $this->loadConfigs();
    }

    public function init() {
        $this->initNamespace();
    }

    private function loadConfigs() {
        // 加载后台路由和菜单配置
        Config::load('menu');
        Router::load('backend');
        $this->menus = Config::get('menu');
        $this->routes = Router::get('backend');
    }

    public function registerRoutes() {
        if (is_admin()) {
            // 注册后台路由
            if ($this->routes) {
                if (isset($this->routes['post']) && !empty($this->routes['post'])) {
                    foreach ($this->routes['post'] as $action => $handler) {
                        add_action('admin_post_' . $action, function() use ($action) {
                            $this->handleRoute('post', $action);
                        });
                    }
                }
                if (isset($this->routes['ajax']) && !empty($this->routes['ajax'])) {
                    foreach ($this->routes['ajax'] as $action => $handler) {
                        add_action('wp_ajax_' . $action, function() use ($action) {
                            $this->handleRoute('ajax', $action);
                        });
                    }
                }
            }
        }
    }

    public function registerAdminMenus() {

        if (empty($this->menus)) {
            return;
        }
        foreach ($this->menus as $menu) {
            if (!isset($menu['parent_slug']) || is_null($menu['parent_slug'])) {
                add_menu_page(
                    $menu['page_title'] ?? '',
                    $menu['menu_title'] ?? '',
                    $menu['capability'] ?? 'manage_options',
                    $menu['menu_slug'] ?? '',
                    [$this, 'handleMenuCallback'],
                    $menu['icon'] ?? '',
                    $menu['position'] ?? null
                );
            } else {
                add_submenu_page(
                    $menu['parent_slug'] ?? '',
                    $menu['page_title'] ?? '',
                    $menu['menu_title'] ?? '',
                    $menu['capability'] ?? 'manage_options',
                    $menu['menu_slug'] ?? '',
                    [$this, 'handleMenuCallback']
                );
            }
        }
    }

    public function registerAssets($hook) {
        // 获取当前页面的 page 参数
        $page = $_GET['page'] ?? '';

        if (!isset($this->routes['page'])) {
            // 不报错
            return;
            if(false) return ErrorHandler::die(sprintf(
            /* translators: %s: page route key */
                Lang::kit('后台路由配置错误，缺少路由键：%s'),
                'page'
            ));
        }

        // 如果在路由配置中存在该页面
        if (isset($this->routes['page'][$page])) {
            try {
                // 获取处理器配置
                $handler = $this->routes['page'][$page];

                // 解析控制器和方法
                list($controller, $method) = $this->parseHandler($handler);

                // 构建完整的控制器类名
                $controllerClass = $this->namespace . $controller;

                // 检查控制器类是否存在
                if (!class_exists($controllerClass)) {
                    throw new \Exception(sprintf(Lang::kit('控制器未找到： %s'), $controller));
                }

                // 实例化控制器
                $instance = new $controllerClass();

                // 如果控制器有 enqueueAssets 方法，则调用它，并传入 $hook 参数
                if (method_exists($instance, 'enqueueAssets')) {
                    call_user_func([$instance, 'enqueueAssets'], $hook);
                }

            } catch (\Exception $e) {
                return;
            }
        }
    }

    /**
     * 处理页面链接
     * @return void
     */
    public function handleMenuCallback() {
        // 控制器
        $page = $_GET['page'] ?? '';
        // 方法
        $action = $_GET['action'] ?? '';

        if (!isset($this->routes['page'])) {
            return ErrorHandler::die(sprintf(
                /* translators: %s: page route key */
                Lang::kit('后台路由配置错误，缺少路由键：%s'),
                'page'
            ));
        }

        if (isset($this->routes['page'][$page])) {
            $handler = $this->routes['page'][$page];
            $this->handleRequest($handler,$action);
        }else{// 未配置
            return ErrorHandler::die(sprintf(
                /* translators: %s: page slug */
                Lang::kit('未找到页面的路由配置: %s'),
                $page
            ));
        }
    }

    /**
     * 处理请求模块功能
     * @param string $type 路由类型 (post/ajax)
     * @param string $action 动作名称
     * @return void
     */
    public function handleRoute($type, $action) {
        try {
            // 获取对应类型的路由配置
            if (!isset($this->routes[$type][$action])) {
                throw new \Exception(sprintf(Lang::kit('Route not found: %s'), $action));
            }

            $handler = $this->routes[$type][$action];
            list($controller, $method) = $this->parseHandler($handler);
            $controllerClass = $this->namespace . $controller;

            if (!class_exists($controllerClass)) {
                throw new \Exception(sprintf(Lang::kit('控制器未找到： %s'), $controllerClass));
            }

            $instance = new $controllerClass();
            if (!method_exists($instance, $method)) {
                throw new \Exception(sprintf(Lang::kit('方法未找到：%s'), $method));
            }

            call_user_func([$instance, $method]);

        } catch (\Exception $e) {
            $message = Lang::kit('发生错误');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $message = $e->getMessage();
            }

            if ($type === 'ajax') {
                wp_send_json_error(['message' => $message]);
            } else {
                ErrorHandler::die($message);
            }
        }
    }

    private function handleRequest($handler,$action = '') {
        try {
            list($controller, $method) = $this->parseHandler($handler);
            $controllerClass = $this->namespace . $controller;

            if (!class_exists($controllerClass)) {
                throw new \Exception(sprintf(Lang::kit('控制器未找到： %s'), $controllerClass ));
            }

            // 处理方法名称
            if ($action) {
                // 将横杠转换为下划线
                $underscoreMethod = str_replace('-', '_', $action);
                
                if (strpos($action, '-') !== false || strpos($action, '_') !== false) {
                    // 只有当动作名称包含横杠时才需要检查驼峰式
                    // 转换为驼峰式
                    $camelMethod = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $underscoreMethod))));
                    
                    $instance = new $controllerClass();
                    
                    // 优先检查下划线风格
                    if (method_exists($instance, $underscoreMethod)) {
                        $method = $underscoreMethod;
                    }
                    // 其次检查驼峰式风格
                    elseif (method_exists($instance, $camelMethod)) {
                        $method = $camelMethod;
                    }
                    // 两种风格都不存在时抛出异常
                    else {
                        throw new \Exception(sprintf(
                            Lang::kit('方法未找到：%s 或 %s'),
                            $underscoreMethod,
                            $camelMethod
                        ));
                    }
                } else {
                    // 如果动作名称中没有横杠，直接使用转换后的方法名
                    $instance = new $controllerClass();
                    if (method_exists($instance, $underscoreMethod)) {
                        $method = $underscoreMethod;
                    } else {
                        throw new \Exception(sprintf(
                            Lang::kit('方法未找到：%s'),
                            $underscoreMethod
                        ));
                    }
                }
            } else {
                $instance = new $controllerClass();
                if (!method_exists($instance, $method)) {
                    throw new \Exception(sprintf(Lang::kit('方法未找到：%s'), $method));
                }
            }

            call_user_func([$instance, $method]);

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                ErrorHandler::die($e->getMessage());
            }
            ErrorHandler::die(Lang::kit('发生错误，请稍后重试。'));
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

    protected function initNamespace() {
        $this->namespace = Config::get('app.namespace') . '\\backend\\controllers\\';
    }
}