<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;
use kitpress\utils\Lang;

if (!defined('ABSPATH')) {
    exit;
}

class RestApi extends Singleton {
    private $routes = [];
    private $namespace;
    private $defaultConfig = [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'show_in_index' => true,
        'accept_json' => true,
        'allow_batch' => [
            'v1' => true
        ],
        '_links' => [],
        'args' => []
    ];

    /**
     * 构造函数：初始化并加载路由配置
     */
    protected function __construct() {
        parent::__construct();
        $this->loadRoutes();
    }


    /**
     * 初始化 REST API：设置命名空间并注册路由
     */
    public function init() {
        $this->initNamespace();
        $this->registerRoutes();
    }

    /**
     * 加载路由配置
     * 从路由配置文件中读取 API 路由设置
     */
    private function loadRoutes() {
        Router::load('api');
        // 加载前台路由配置文件
        $this->routes = Router::get('api');
    }

    /**
     * 注册所有路由
     * 遍历路由配置并注册到 WordPress REST API
     */
    public function registerRoutes() {
        if(empty($this->routes)) return;

        foreach ($this->routes as $version => $endpoints) {
            $this->validateVersion($version);
            foreach ($endpoints as $endpoint => $config) {
                $this->validateEndpointConfig($config);
                $routeConfig = $this->buildRouteConfig($config);
                \register_rest_route($version, '/' . $endpoint . '/', $routeConfig);
            }
        }
    }

    /**
     * 验证端点配置
     * 检查回调函数是否存在且格式是否正确
     * @throws InvalidArgumentException 当配置无效时抛出异常
     */
    private function validateEndpointConfig(array $config): void {
        if (!isset($config['callback'])) {
            throw new \InvalidArgumentException(Lang::kit('端点回调函数是必需的'));
        }

        if (isset($config['callback']) && strpos($config['callback'], '@') === false) {
            throw new \InvalidArgumentException(Lang::kit('无效的回调函数格式，请使用 "Controller@method"'));
        }
    }

    /**
     * 构建路由配置数组
     * 合并默认配置和用户自定义配置
     * @param array $config 用户提供的配置
     * @return array 完整的路由配置
     */
    private function buildRouteConfig(array $config): array {
        return array_merge(
            $this->defaultConfig,
            [
                'methods' => $config['methods'] ?? $this->defaultConfig['methods'],
                'callback' => [$this, 'resolveCallback']($config['callback']),
                'permission_callback' => [$this, 'resolvePermissionCallback']($config['permission_callback'] ?? $this->defaultConfig['permission_callback']),
            ],
            array_diff_key($config, [
                'methods' => '',
                'callback' => '',
                'permission_callback' => ''
            ])
        );
    }

    /**
     * 解析权限回调函数
     * 处理自定义权限检查逻辑
     * @param mixed $action 权限回调函数或字符串
     * @return mixed 处理后的权限回调函数
     */
    private function resolvePermissionCallback($action) {
        if( $action == $this->defaultConfig['permission_callback'] ) return $action;
        return $this->resolveCallback($action);
    }

    /**
     * 解析控制器回调函数
     * 将字符串格式的控制器方法转换为可调用的函数
     * @param string $action 控制器方法（格式：Controller@method）
     * @return callable 可调用的回调函数
     * @throws RuntimeException 当控制器或方法不存在时抛出异常
     */
    private function resolveCallback(string $action): callable {
        return function($request) use ($action) {
            try {
                [$controller, $method] = explode('@', $action);
                $controllerClass = $this->namespace . $controller;

                if (!class_exists($controllerClass)) {
                    throw new \RuntimeException(sprintf(Lang::kit('控制器未找到： %s'), $controllerClass));
                }

                $controller = new $controllerClass();

                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException(sprintf(Lang::kit('方法未找到：%s'), $method));
                }

                return $controller->$method($request);
            } catch (\Throwable $e) {
                return new \WP_Error(
                    404,
                    $e->getMessage(),
                    ['status' => 404]
                );
            }
        };
    }

    /**
     * 初始化命名空间
     * 设置控制器类的默认命名空间
     */
    protected function initNamespace()
    {
        // 默认命名空间
        $this->namespace =  Config::get('app.namespace') . '\\api\\controllers\\';
    }
}