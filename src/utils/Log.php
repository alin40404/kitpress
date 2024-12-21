<?php
namespace kitpress\utils;

use kitpress\Kitpress;

if (!defined('ABSPATH')) {
    exit;
}

class Log
{
    // 日志级别常量
    const EMERGENCY = 'emergency'; // 系统不可用
    const ALERT     = 'alert';     // 必须立即采取行动
    const CRITICAL  = 'critical';  // 紧急情况
    const ERROR     = 'error';     // 运行时错误
    const WARNING   = 'warning';   // 警告但不是错误
    const NOTICE    = 'notice';    // 一般性通知
    const INFO      = 'info';      // 信息性消息
    const DEBUG     = 'debug';     // 调试信息

    /**
     * 记录调试信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function debug($message, array $context = [])
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * 记录信息性消息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function info($message, array $context = [])
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * 记录通知消息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function notice($message, array $context = [])
    {
        self::log(self::NOTICE, $message, $context);
    }

    /**
     * 记录警告信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function warning($message, array $context = [])
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * 记录错误信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function error($message, array $context = [])
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * 记录严重错误信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function critical($message, array $context = [])
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * 记录需要立即处理的信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function alert($message, array $context = [])
    {
        self::log(self::ALERT, $message, $context);
    }

    /**
     * 记录系统不可用信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public static function emergency($message, array $context = [])
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    /**
     * 记录日志的核心方法
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    protected static function log($level, $message, array $context = [])
    {
        // 只在调试模式下记录 DEBUG 级别的日志
        if ($level === self::DEBUG && !Config::get('app.features.debug_mode')) {
            return;
        }

        // 格式化消息
        $message = self::interpolate($message, $context);

        // 添加时间戳和级别
        $log_entry = sprintf(
            '[%s] %s: %s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );

        // 获取日志文件路径
        $log_file = self::getLogFile($level);

        // 写入日志
        error_log($log_entry . PHP_EOL, 3, $log_file);

        // 对于严重错误，同时写入 WordPress 错误日志
        if (in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR])) {
            error_log($log_entry);
        }
    }

    /**
     * 替换消息中的上下文变量
     * @param string $message 消息模板
     * @param array $context 上下文数据
     * @return string
     */
    protected static function interpolate($message, array $context = [])
    {
        if (empty($context)) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }


    /**
     * 在指定目录创建保护文件
     * @param string $dir 目录路径
     */
    protected static function createProtectionFiles($dir)
    {
        // 创建 .htaccess 文件
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, 'Deny from all');
        }

        // 创建 index.php 文件
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
    }

    /**
     * 递归创建目录并添加保护文件
     * @param string $path 目标路径
     */
    protected static function createSecureDirectory($path)
    {
        // 统一目录分隔符并去除末尾的斜杠
        $path = rtrim(str_replace('\\', '/', $path), '/');

        // 获取所有父级目录
        $parts = explode('/', $path);
        $current = '';

        // Windows系统下的盘符处理 (如 C:/)
        if (isset($parts[0]) && strpos($parts[0], ':') !== false) {
            $current = array_shift($parts) . '/';
        }
        // Unix系统下的根目录处理
        elseif ($path[0] === '/') {
            $current = '/';
        }

        // 逐级创建目录并添加保护文件
        foreach ($parts as $part) {
            if (empty($part)) continue;

            $current .= $part . '/';

            if (!file_exists($current)) {
                wp_mkdir_p($current);
            }

            // 只在 WordPress 目录范围内创建保护文件
            if (is_dir($current) && self::isInWordPressPath($current)) {
                self::createProtectionFiles($current);
            }
        }
    }

    /**
     * 检查路径是否在 WordPress 目录范围内
     * @param string $path 要检查的路径
     * @return bool
     */
    protected static function isInWordPressPath($path)
    {
        $wp_root = str_replace('\\', '/', ABSPATH);
        $wp_content = str_replace('\\', '/', WP_CONTENT_DIR);
        $path = str_replace('\\', '/', $path);

        return (
            strpos($path, $wp_root) === 0 ||
            strpos($path, $wp_content) === 0
        );
    }

    /**
     * 获取日志文件路径
     * @param string $level 日志级别
     * @return string
     */
    protected static function getLogFile($level)
    {
        $upload_dir = wp_upload_dir();
        $plugin_name = basename(Kitpress::getRootPath());
        $log_dir = $upload_dir['basedir'] . '/kitpress-logs/' . $plugin_name;

        // 创建安全的目录结构
        self::createSecureDirectory($log_dir);

        // 按日期和级别分文件
        return sprintf(
            '%s/%s-%s.log',
            $log_dir,
            date('Y-m-d'),
            $level
        );
    }

    /**
     * 获取日志目录路径
     * @return string
     */
    public static function getLogDir()
    {
        $upload_dir = wp_upload_dir();
        $plugin_name = basename(Kitpress::getRootPath());
        return $upload_dir['basedir'] . '/kitpress-logs/' . $plugin_name;
    }

    /**
     * 获取指定插件的所有日志文件
     * @return array
     */
    public static function getLogFiles()
    {
        $log_dir = self::getLogDir();
        if (!is_dir($log_dir)) {
            return [];
        }

        return glob($log_dir . '/*.log');
    }

    /**
     * 清理指定插件的旧日志文件
     * @param int $days 保留天数
     */
    public static function cleanOldLogs($days = 30)
    {
        $files = self::getLogFiles();
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 86400 * $days) {
                    unlink($file);
                }
            }
        }
    }
}