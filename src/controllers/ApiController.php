<?php
namespace kitpress\controllers;

use kitpress\core\abstracts\Controller;
use kitpress\utils\Lang;

if (!defined('ABSPATH')) {
    exit;
}

abstract class ApiController extends Controller{

    // 错误消息映射
    protected array $error_messages = [];

    public function init() {
        parent::init();

        $this->error_messages = [
            'rest_missing_callback_param' => Lang::kit('缺少必需的参数'),
            'rest_invalid_param' => Lang::kit('无效的参数'),
            'rest_forbidden' => Lang::kit('没有权限访问'),
            'rest_no_route' => Lang::kit('接口路径不存在'),
        ];

        \add_filter('rest_post_dispatch', [$this, 'translateRestErrors'], 10, 3);

    }

    public function translateRestErrors($response, $handler, $request) {
        if ($response instanceof \WP_Error) {
            $error_code = $response->get_error_code();
            $error_data = $response->get_error_data();

            if (isset($error_messages[$error_code])) {
                // 获取具体的参数名
                $param_name = isset($error_data['params']) ? key($error_data['params']) : '';
                $message = $error_messages[$error_code];
                if ($param_name) {
                    $message .= sprintf('：%s', $param_name);
                }

                $response = new \WP_Error(
                    $error_code,
                    $message,
                    $error_data
                );
            }
        }

        return $response;
    }
}
