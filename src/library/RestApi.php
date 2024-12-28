<?php
namespace kitpress\library;

use kitpress\utils\Lang;
use function kitpress\functions\kp;


if (!defined('ABSPATH')) {
    exit;
}

class RestApi {
    private array $routes = [];
    /**
     * 命名空间
     * @var string
     */
    private string $namespace;
    /**
     * 命名空间路径
     * @var string
     */
    private string $namespacePath;
    private ?Plugin $plugin = null;
    private ?Config $config = null;

    private ?Log $log = null;
    private ?Router $router = null;
    private array $defaultConfig = [
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
    public function __construct(Log $log,Router $router) {
        $this->plugin = $log->plugin;
        $this->config = $log->config;
        $this->log = $log;

        $this->router = $router;
    }


    /**
     * 初始化 REST API：设置命名空间并注册路由
     */
    public function init() {
        // 添加多个错误处理过滤器
        \add_filter('rest_pre_dispatch', [$this, 'handlePreDispatch'], 10, 3);
        \add_filter('rest_request_before_callbacks', [$this, 'handlePreDispatch'], 10, 3);
        \add_filter('rest_request_after_callbacks', [$this, 'handlePreDispatch'], 10, 3);
        \add_filter('rest_post_dispatch', [$this, 'handlePreDispatch'], 10, 3);

        // 添加 REST API 初始化钩子
        \add_action('rest_api_init',function(){
            $this->loadRoutes();
            $this->initNamespace();
            $this->registerRoutes();
        });
    }

    /**
     * 加载路由配置
     * 从路由配置文件中读取 API 路由设置
     */
    private function loadRoutes() {
        if( $this->routes ) return;

        $this->router->load('api');
        // 加载前台路由配置文件
        $this->routes = $this->router->get('api');
    }

    /**
     * 注册所有路由
     * 遍历路由配置并注册到 WordPress REST API
     */
    private function registerRoutes() {
        if(empty($this->routes)) return;

        foreach ($this->routes as $version => $endpoints) {
            $this->validateVersion($version);
            $fullNamespace = $this->namespace . '/' . $version;

            foreach ($endpoints as $endpoint => $config) {
                $this->validateEndpointConfig($config);
                $routeConfig = $this->buildRouteConfig($config);

                // 确保endpoint以/开头和结尾
                $endpoint = '/' . trim($endpoint, '/') . '/';

                \register_rest_route($fullNamespace, $endpoint, $routeConfig);
            }
        }
    }

    /**
     * 验证API版本格式
     * @param string $version API版本（例如：v1, v2）
     * @throws InvalidArgumentException 当版本格式无效时抛出异常
     */
    private function validateVersion(string $version): void {
        if (!preg_match('/^v[0-9]+$/', $version)) {
            throw new \InvalidArgumentException(
                Lang::kit('无效的API版本格式。版本应该类似于: "v1"')
            );
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
     * 将错误处理逻辑移到单独的方法
     * @param $response
     * @param $server
     * @param $request
     * @return WP_Error|mixed|\WP_Error
     */
    public function handlePreDispatch($response, $server, $request) {
        if ($response !== null && \is_wp_error($response)) {
            return $this->translateRestErrors($response);
        }
        return $response;
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
                $controllerClass = $this->namespacePath . $controller;

                if (!class_exists($controllerClass)) {
                    throw new \RuntimeException(sprintf(Lang::kit('控制器未找到： %s'), $controllerClass));
                }

                $controller = new $controllerClass();
                $controller->setContainer(kp($this->plugin->getNamespace()));

                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException(sprintf(Lang::kit('方法未找到：%s'), $method));
                }

               return $controller->$method($request);

            } catch (\Throwable $e) {
                return $this->translateRestErrors(new \WP_Error(
                    500,
                    $e->getMessage(),
                    ['wp_code' => 'rest_api_error']
                ));
            }
        };
    }

    /**
     * 翻译 REST API 错误信息
     * @param WP_Error $error 错误对象
     * @return WP_Error 处理后的错误对象
     */
    private function translateRestErrors(\WP_Error $response): \WP_Error {
        $error_data = $response->get_error_data();
        $code = $error_data['status'] ?? 400;
        $message = $response->get_error_message();

        // 获取原始请求对象
        $request = $error_data['request'] ?? null;

        // 获取语言参数
        $lang = null;
        if ($request instanceof \WP_REST_Request) {
            // 从请求参数中获取语言设置
            $lang = $request->get_param('lang');
        }

        // 如果指定了语言，临时切换
        if ($lang) {
            $current_locale = \get_locale();
            \switch_to_locale($lang);
        }

        // 如果是验证错误，可能包含多个错误信息
        if ($code === 400 && is_array($message)) {
            $messages = [];
            foreach ($message as $key => $msg) {
                if (is_array($msg)) {
                    $messages[$key] = reset($msg);
                } else {
                    $messages[$key] = $msg;
                }
            }
            $message = implode('; ', $messages);
        }

        // 如果之前切换了语言，恢复原来的语言设置
        if ($lang) {
            \switch_to_locale($current_locale);
        }

        // 返回标准化的错误响应
        return new \WP_Error(
            $code,
            $message,
            [
                'lang' => $lang ?? \get_locale(),
                'wp_code' => $response->get_error_code()
            ]
        );
    }

    /**
     * 初始化命名空间
     * 设置控制器类的默认命名空间
     */
    protected function initNamespace()
    {
        $this->namespace =  $this->plugin->getNamespace();
        $this->namespacePath = $this->config->get('app.namespace') . '\\api\\controllers\\';
    }
}