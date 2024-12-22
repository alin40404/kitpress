<?php
namespace kitpress\utils;
if (!defined('ABSPATH')) {
    exit;
}
class ErrorHandler {
    /**
     * 预定义的错误消息
     * @var array<string,array{message:string,title:string,type:string}>
     */
    private static $errorMessages = [
        'permission_denied' => [
            'message' => '您没有执行此操作的权限',
            'title' => '权限错误',
            'type' => 'error'
        ],
        'invalid_request' => [
            'message' => '无效的请求',
            'title' => '请求错误',
            'type' => 'error'
        ],
        'php_version' => [
            'message' => '【%s】需要PHP 7.4或更高版本',
            'title' => '版本检查失败',
            'type' => 'error',
        ],
        'wp_version' => [
            'message' => '【%s】需要WordPress 5.0或更高版本',
            'title' => '版本检查失败',
            'type' => 'error',
        ]
    ];

    /**
     * 显示管理员通知
     * @param string $error_code 错误代码
     * @param array $context 上下文数据
     * @param int $priority 优先级
     * @return void
     */
    public static function showAdminNotice($error_code, array $context = [], $priority = 10) {
        add_action('admin_notices', function() use ($error_code, $context) {
            $error = self::getErrorConfig($error_code, $context);
            self::renderNotice($error);
        }, $priority);
    }

    /**
     * 获取错误配置
     * @param string $error_code 错误代码
     * @param array<string,mixed> $context 上下文数据
     * @return array{message: string, title: string, type: string}
     */
    protected static function getErrorConfig(string $error_code, array $context = []): array {
        // 定义默认错误配置
        $default_config = [
            'message' => Lang::kit('Error'),
            'title' => Lang::kit('Error'),
            'type' => 'error'
        ];

        // 从预定义错误消息中获取
        $error = self::$errorMessages[$error_code] ?? $default_config;

        // 翻译消息和标题
        if (isset($error['message'])) {
            $error['message'] = Lang::kit($error['message']);
        }
        if (isset($error['title'])) {
            $error['title'] = Lang::kit($error['title']);
        }

        // 处理上下文数据
        if (!empty($context) && is_string($error['message'])) {
            try {
                $error['message'] = vsprintf($error['message'], $context);
            } catch (\Throwable $e) {
                self::logError('Error message formatting failed', [
                    'error_code' => $error_code,
                    'message' => $error['message'],
                    'context' => $context,
                    'exception' => $e->getMessage()
                ]);
            }
        }

        return $error;
    }

    /**
     * 渲染通知
     * @param array $error
     * @return void
     */
    protected static function renderNotice(array $error) {
        $allowed_types = ['error', 'warning', 'success', 'info'];
        $type = in_array($error['type'], $allowed_types) ? $error['type'] : 'error';
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p>
                <?php if (!empty($error['title'])): ?>
                    <strong><?php echo esc_html($error['title']); ?>：</strong>
                <?php endif; ?>
                <?php echo wp_kses_post($error['message']); ?>
            </p>
        </div>
        <?php
    }

    /**
     * 记录错误日志
     * @param string $message
     * @param array $context
     * @return void
     */
    public static function logError($message, array $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            Log::error(sprintf(
                'Kitpress Framework Error: %s | Context: %s',
                $message,
                json_encode($context, JSON_UNESCAPED_UNICODE)
            ));
        }
    }

    /**
     * 统一处理错误并终止执行
     *
     * @param string $message 错误信息
     * @param string $title 错误标题(可选)
     * @param int $status HTTP状态码(可选)
     * @param bool $showBackLink 是否显示返回链接(可选)
     */
    public static function die($message, $title = '错误', $status = 403, $showBackLink = true) {
        wp_die(
            $message,
            $title,
            array(
                'response' => $status,
                'back_link' => $showBackLink,
                'exit' => true
            )
        );
    }
} 