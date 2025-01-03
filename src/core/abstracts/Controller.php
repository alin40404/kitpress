<?php
namespace kitpress\core\abstracts;

use kitpress\core\Container;
use kitpress\core\traits\ViewTrait;
use kitpress\library\Config;
use kitpress\library\Model;
use kitpress\library\Plugin;
use kitpress\library\Validator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 控制器基类
 *
 * 提供基础的控制器功能，包括：
 * - 请求处理（GET/POST）
 * - 数据验证和清理
 * - 视图渲染
 * - 响应处理
 * - 安全检查
 * - 用户权限验证
 *
 * @package kitpress\core\abstracts
 * @since 1.0.0
 *
 * @property-read Container $container 容器实例
 * @property-read Plugin $plugin 插件实例
 * @property-read Model $model 模型实例
 * @property-read Config $config 配置实例
 *
 * @method string render(string $view, array $data = []) 渲染视图
 */
abstract class Controller {
    use ViewTrait;

    /**
     * 模型实例
     * @var mixed
     */
    protected ?Model $model = null;

    /**
     * 配置实例
     * @var mixed
     */
    protected ?Config $config = null;

    /**
     * 当前容器
     * @var Container
     */
    protected Container $container;

    /**
     * 构造函数
     */
    public function __construct() {
        // $this->init();
    }

    /**
     * 设置容器实例
     *
     * @param Container $container 容器实例
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
        $this->init();
    }

    /**
     * 初始化方法
     * 子类可以重写此方法以添加自定义初始化逻辑
     *
     * @return void
     */
    protected function init() {
        $this->plugin = $this->container->get('plugin');
        $this->model = $this->container->get('model');
        $this->config = $this->container->get('config');
    }

    /**
     * 验证 nonce
     *
     * @param string $action 动作名称
     * @param string $nonce_key nonce键名
     * @param bool $stop 验证失败时是否停止执行
     * @return bool
     */
    protected function verifyNonce(string $action, string $nonce_key = 'nonce', bool $stop = true): bool
    {
        return \check_ajax_referer($action, $nonce_key, $stop);
    }

    /**
     * 获取请求参数
     *
     * 从 GET 或 POST 请求中获取并清理参数
     *
     * @param string $key 参数键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function input($key, $default = null) {
        if (isset($_POST[$key])) {
            return $this->sanitize($_POST[$key]);
        }
        if (isset($_GET[$key])) {
            return $this->sanitize($_GET[$key]);
        }
        return $default;
    }

    /**
     * 清理输入数据
     *
     * 递归清理数组或字符串数据，防止 XSS 攻击
     *
     * @param mixed $data 需要清理的数据
     * @return mixed
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return \sanitize_text_field($data);
    }

    /**
     * 验证数据
     *
     * 使用验证器验证数据是否符合规则
     *
     * @param array $data 待验证的数据
     * @param array $rules 验证规则
     * @return Validator
     */
    protected function validate(array $data, array $rules) : Validator
    {
        return new Validator($data, $rules);
    }

    /**
     * 发送 JSON 响应
     *
     * @param mixed $data 响应数据
     * @param int $code 响应代码
     * @param string $message 响应消息
     * @return bool
     */
    protected function json(array $data = null, int $code = 1, string $message = ''): bool
    {
        \wp_send_json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
        return $code == 1;
    }

    /**
     * 发送成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 成功消息
     * @return bool
     */
    protected function success(array $data = null, string $message = ''): bool
    {
        return $this->json($data, 1, $message);
    }

    /**
     * 发送错误响应
     *
     * @param string $message 错误消息
     * @param mixed $data 错误数据
     * @return bool
     */
    protected function error(string $message = '', $data = null): bool
    {
        return $this->json($data, 0, $message);
    }

    /**
     * 获取当前用户
     * @return \WP_User|null
     */
    protected function getCurrentUser(): ?\WP_User
    {
        return \wp_get_current_user();
    }

    /**
     * 检查用户是否登录
     * @return bool
     */
    protected function isUserLoggedIn() {
        return \is_user_logged_in();
    }

    /**
     * 检查用户权限
     * @param string $capability
     * @return bool
     */
    protected function checkCapability(string $capability): bool
    {
        return \current_user_can($capability);
    }

    /**
     * 重定向
     *
     * @param string $url 目标URL
     * @param int $status HTTP状态码
     * @return void
     */
    protected function redirect(string $url, int $status = 302) {
        \wp_redirect($url, $status);
        exit;
    }

    /**
     * 获取请求方法
     * @return string
     */
    protected function getMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * 检查是否是 AJAX 请求
     * @return bool
     */
    protected function isAjax(): bool
    {
        return \wp_doing_ajax();
    }

    /**
     * 检查是否是 POST 请求
     * @return bool
     */
    protected function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * 检查是否是 GET 请求
     * @return bool
     */
    protected function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * 检查是否是 JSON 请求
     *
     * 通过检查 Content-Type 或 Accept 头来判断
     *
     * @return bool
     */
    protected function isJsonRequest(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return (
            strpos($contentType, 'application/json') !== false ||
            strpos($accept, 'application/json') !== false
        );
    }
}