<?php
namespace kitpress\utils;
if (!defined('ABSPATH')) {
    exit;
}
class ErrorHandler {
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
     * @param string $error_code
     * @param array $context
     * @return array
     */
    protected static function getErrorConfig($error_code, array $context = []) {
        $error = Config::get('app.messages.errors.' . $error_code, [
            'message' => $error_code,
            'title' => __('Error', KITPRESS_TEXT_DOMAIN),
            'type' => 'error'
        ]);

        // 合并上下文数据
        if (!empty($context)) {
            $error['message'] = vsprintf($error['message'], $context);
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
                '[Kitpress Error] %s | Context: %s',
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