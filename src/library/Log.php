<?php
namespace kitpress\library;

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
     * @var string 当前请求的ID
     */
    private $requestId = null;
    private $config = null;
    private $plugin = null;

    public function __construct(Config $config, Plugin $plugin)
    {
        $this->config = $config;
        $this->plugin = $plugin;
    }

    /**
     * 获取当前请求的ID
     * @return string
     */
    protected function getRequestId()
    {
        if ($this->requestId === null) {
            $this->requestId = substr(uniqid(), -6) . mt_rand(100, 999);
        }
        return $this->requestId;
    }

    /**
     * 记录调试信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function debug($message, array $context = [])
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * 记录信息性消息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function info($message, array $context = [])
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * 记录通知消息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function notice($message, array $context = [])
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * 记录警告信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function warning($message, array $context = [])
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * 记录错误信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function error($message, array $context = [])
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * 记录严重错误信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function critical($message, array $context = [])
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * 记录需要立即处理的信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function alert($message, array $context = [])
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * 记录系统不可用信息
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    public function emergency($message, array $context = [])
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * 记录日志的核心方法
     * @param string $level 日志级别
     * @param mixed $message 日志消息（支持字符串、数组、对象）
     * @param array $context 上下文数据
     */
    protected function log($level, $message, array $context = [])
    {
        // 只在调试模式下记录 DEBUG 级别的日志
        if ($level === self::DEBUG && !Config::get('app.features.debug_mode')) {
            return;
        }

        // 处理消息内容
        $message = $this->formatMessage($message);

        // 格式化上下文数据
        $message = $this->interpolate($message, $context);

        // 添加时间戳和级别
        $log_entry = sprintf(
            '[%s] [%s] %s: %s',
            date('Y-m-d H:i:s'),
            $this->getRequestInfo(),
            strtoupper($level),
            $message
        );

        // 获取日志文件路径
        $log_file = $this->getLogFile($level);

        // 写入日志
        error_log($log_entry . PHP_EOL, 3, $log_file);

        // 对于严重错误，同时写入 WordPress 错误日志
        if (in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR])) {
            error_log($log_entry);
        }
    }

    /**
     * 获取请求的详细信息
     * @return string
     */
    protected function getRequestInfo()
    {
        $info = [
            $this->getRequestId(),
            isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI'
        ];

        // 添加简化的路径信息
        if (PHP_SAPI === 'cli') {
            $info[] = basename($_SERVER['PHP_SELF']);
        } else {
            $path = isset($_SERVER['REQUEST_URI']) ?
                parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '-';
            // 只保留最后两段路径
            $pathParts = array_slice(array_filter(explode('/', $path)), -2);
            $info[] = implode('/', $pathParts);
        }

        return implode('|', $info);
    }

    /**
     * 格式化消息内容
     * @param mixed $message
     * @return string
     */
    protected function formatMessage($message)
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_array($message)) {
            return $this->formatArray($message);
        }

        if (is_object($message)) {
            return $this->formatObject($message);
        }

        if (is_bool($message)) {
            return $message ? 'true' : 'false';
        }

        if (is_null($message)) {
            return 'null';
        }

        return (string) $message;
    }

    /**
     * 格式化数组
     * @param array $array
     * @param int $depth 当前深度
     * @param int $maxDepth 最大深度
     * @return string
     */
    protected function formatArray(array $array, $depth = 0, $maxDepth = 3)
    {
        if ($depth >= $maxDepth) {
            return '[Array...]';
        }

        $output = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->formatArray($value, $depth + 1, $maxDepth);
            } elseif (is_object($value)) {
                $value = $this->formatObject($value, $depth + 1, $maxDepth);
            } else {
                $value = $this->formatMessage($value);
            }
            $output[] = "$key: $value";
        }

        return '{ ' . implode(', ', $output) . ' }';
    }

    /**
     * 格式化对象
     * @param object $object
     * @param int $depth 当前深度
     * @param int $maxDepth 最大深度
     * @return string
     */
    protected function formatObject($object, $depth = 0, $maxDepth = 3)
    {
        if ($depth >= $maxDepth) {
            return get_class($object) . '{...}';
        }

        // 如果对象实现了 __toString 方法
        if (method_exists($object, '__toString')) {
            return (string) $object;
        }

        // 如果是异常对象
        if ($object instanceof \Throwable) {
            return sprintf(
                '%s: %s in %s:%d',
                get_class($object),
                $object->getMessage(),
                $object->getFile(),
                $object->getLine()
            );
        }

        // 处理普通对象
        $className = get_class($object);
        $attributes = get_object_vars($object);

        return sprintf(
            '%s%s',
            $className,
            $this->formatArray($attributes, $depth + 1, $maxDepth)
        );
    }

    /**
     * 替换消息中的上下文变量
     * @param string $message 消息模板
     * @param array $context 上下文数据
     * @return string
     */
    protected function interpolate($message, array $context = [])
    {
        if (empty($context)) {
            return $message;
        }

        $replace = [];
        $unmatchedPairs = [];
        foreach ($context as $key => $val) {
            // 格式化值
            $formattedVal = (!is_array($val) && (!is_object($val) || method_exists($val, '__toString')))
                ? (string)$val
                : $this->formatMessage($val);

            // 检查是否有对应的占位符
            $placeholder = '{' . $key . '}';
            if (strpos($message, $placeholder) !== false) {
                $replace[$placeholder] = $formattedVal;
            } else {
                // 没有找到占位符，将 key 和 value 存储起来
                $unmatchedPairs[] = $key . '=' . $formattedVal;
            }
        }

        // 先替换占位符
        $result = strtr($message, $replace);

        // 如果有未匹配的键值对，添加到消息末尾
        if (!empty($unmatchedPairs)) {
            $result .= ' [' . implode(', ', $unmatchedPairs) . ']';
        }

        return $result;
    }


    /**
     * 在指定目录创建保护文件
     * @param string $dir 目录路径
     */
    protected function createProtectionFiles($dir)
    {
        // 只在 kitpress-logs 目录下创建保护文件
        if (strpos($dir, 'kitpress-logs') === false) {
            return;
        }

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

        // 创建 .gitignore 文件
        $gitignore = $dir . '/.gitignore';
        if (!file_exists($gitignore)) {
            file_put_contents($gitignore, "*.log\n");
        }
    }

    /**
     * 递归创建目录并添加保护文件
     * @param string $path 目标路径
     */
    protected function createSecureDirectory($path)
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
            if (is_dir($current) && $this->isInWordPressPath($current)) {
                $this->createProtectionFiles($current);
            }
        }
    }

    /**
     * 检查路径是否在 WordPress 目录范围内
     * @param string $path 要检查的路径
     * @return bool
     */
    protected function isInWordPressPath($path)
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
     * 获取日志目录路径
     * @return string
     * @throws \Exception
     */
    public function getLogDir()
    {
        try {
            // 获取上传目录
            $upload_dir = wp_upload_dir();
            if (is_wp_error($upload_dir)) {
                throw new \Exception('Failed to get WordPress upload directory');
            }

            // 构建并标准化路径
            $plugin_name = sanitize_file_name(basename($this->plugin->getRootPath()));
            $log_dir = wp_normalize_path(
                trailingslashit($upload_dir['basedir']) . 'kitpress-logs/' . $plugin_name
            );

            // 确保路径在 WordPress 允许的范围内
            if (!$this->isInWordPressPath($log_dir)) {
                throw new \Exception('Log directory is outside WordPress directory');
            }

            // 确保目录存在并受保护
            if (!is_dir($log_dir)) {
                $this->createSecureDirectory($log_dir);
            }

            return $log_dir;
        } catch (\Exception $e) {
            // 记录错误并返回默认目录
            error_log('Log directory creation failed: ' . $e->getMessage());
            return trailingslashit(WP_CONTENT_DIR) . 'logs';
        }
    }

    /**
     * 获取日志文件路径
     * @param string $level 日志级别
     * @return string
     */
    protected function getLogFile($level)
    {
        try {
            // 获取日志目录
            $log_dir = $this->getLogDir();

            // 构建日志文件名
            $filename = sprintf(
                '%s-%s.log',
                date('Y-m-d'),
                sanitize_file_name($level)
            );

            // 构建完整路径
            $log_file = wp_normalize_path($log_dir . '/' . $filename);

            // 确保父目录存在
            $parent_dir = dirname($log_file);
            if (!is_dir($parent_dir)) {
                $this->createSecureDirectory($parent_dir);
            }

            // 确保文件可写
            if (!is_file($log_file)) {
                touch($log_file);
                chmod($log_file, 0644);
            }

            // 最后一次验证路径安全性
            if (!$this->isInWordPressPath($log_file)) {
                throw new \Exception('Log file path is outside WordPress directory');
            }

            return $log_file;
        } catch (\Exception $e) {
            // 如果出现任何错误，使用 WordPress 默认日志
            error_log('Log file creation failed: ' . $e->getMessage());
            return WP_CONTENT_DIR . '/debug.log';
        }
    }


    /**
     * 获取指定插件的所有日志文件
     * @return array
     */
    public function getLogFiles()
    {
        $log_dir = $this->getLogDir();
        if (!is_dir($log_dir)) {
            return [];
        }

        return glob($log_dir . '/*.log');
    }

    /**
     * 清理指定插件的旧日志文件
     * @param int $days 保留天数
     */
    public function cleanOldLogs($days = 30)
    {
        $files = $this->getLogFiles();
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