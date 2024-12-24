<?php
namespace kitpress\core\abstracts;

use kitpress\core\Model;
use kitpress\core\traits\ViewTrait;
use kitpress\utils\Lang;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Controller {
    use ViewTrait;

    protected $model = null;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->init();
    }

    /**
     * 初始化方法，子类可以重写
     * @return void
     */
    protected function init() {
        $this->model = Model::getInstance();
    }

    /**
     * 验证 nonce
     * @param string $action
     * @param string $nonce_key
     * @return bool
     */
    protected function verifyNonce($action, $nonce_key = 'nonce',$stop = true) {
        return check_ajax_referer($action, $nonce_key, $stop);
    }

    /**
     * 获取请求参数
     * @param string $key
     * @param mixed $default
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
     * @param mixed $data
     * @return mixed
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return sanitize_text_field($data);
    }

    /**
     * 验证数据
     * @param array $data
     * @param array $rules
     * @return array
     */
    protected function validate($data, $rules) {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $rules_array = explode('|', $rule);
            foreach ($rules_array as $single_rule) {
                if ($error = $this->validateField($field, $data[$field] ?? null, $single_rule)) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }
        return $errors;
    }

    /**
     * 验证单个字段
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return string|null
     */
    protected function validateField($field, $value, $rule) {
        switch ($rule) {
            case 'required':
                if (empty($value)) {
                    return sprintf(Lang::kit('%s is required' ), $field);
                }
                break;
            case 'email':
                if (!empty($value) && !is_email($value)) {
                    return sprintf(Lang::kit('%s must be a valid email'), $field);
                }
                break;
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    return sprintf(Lang::kit('%s must be numeric'), $field);
                }
                break;
        }
        return null;
    }

    /**
     * 发送 JSON 响应
     * @param mixed $data
     * @param bool $success
     * @param string $message
     */
    protected function json($data = null, $code = 1, $message = '') {
        wp_send_json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * 发送成功响应
     * @param mixed $data
     * @param string $message
     */
    protected function success($data = null, $message = '') {
        $this->json($data, 1, $message);
    }

    /**
     * 发送错误响应
     * @param string $message
     * @param mixed $data
     */
    protected function error($message = '', $data = null) {
        $this->json($data, 0, $message);
    }

    /**
     * 获取当前用户
     * @return \WP_User|null
     */
    protected function getCurrentUser() {
        return wp_get_current_user();
    }

    /**
     * 检查用户是否登录
     * @return bool
     */
    protected function isUserLoggedIn() {
        return is_user_logged_in();
    }

    /**
     * 检查用户权限
     * @param string $capability
     * @return bool
     */
    protected function checkCapability($capability) {
        return current_user_can($capability);
    }

    /**
     * 重定向
     * @param string $url
     * @param int $status
     */
    protected function redirect($url, $status = 302) {
        wp_redirect($url, $status);
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
    protected function isAjax() {
        return wp_doing_ajax();
    }

    /**
     * 检查是否是 POST 请求
     * @return bool
     */
    protected function isPost() {
        return $this->getMethod() === 'POST';
    }

    /**
     * 检查是否是 GET 请求
     * @return bool
     */
    protected function isGet() {
        return $this->getMethod() === 'GET';
    }

}