<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}

class Session
{
    /**
     * @var bool 会话初始化标志
     */
    private static $initialized = false;

    /**
     * @var string 会话数据基础前缀
     */
    private static $basePrefix = 'kitpress_';

    /**
     * @var string 完整的会话前缀
     */
    private static $prefix;

    /**
     * 初始化前缀
     */
    private static function initPrefix()
    {
        if (empty(self::$prefix)) {
            self::$prefix = self::$basePrefix . Config::get('app.key', '');
        }
    }

    /**
     * 初始化会话保存路径
     * @return string 最终使用的会话路径
     */
    private static function initSessionPath()
    {
        $currentPath = session_save_path();

        // 如果当前路径为空或不可写
        if (empty($currentPath) || !is_writable($currentPath)) {
            $tempDir = sys_get_temp_dir();
            $sessionPath = $tempDir . DIRECTORY_SEPARATOR . 'kitpress_sessions';

            // 创建目录（如果不存在）
            if (!file_exists($sessionPath)) {
                wp_mkdir_p($sessionPath);
                Log::debug('Session directory created', [
                    'path' => $sessionPath,
                    'original_path' => $currentPath
                ]);
            }

            session_save_path($sessionPath);
            Log::debug('Session path changed', [
                'from' => $currentPath ?: 'empty',
                'to' => $sessionPath
            ]);
        } else {
            Log::debug('Using existing session path', [
                'path' => $currentPath
            ]);
        }

        return session_save_path();
    }

    /**
     * 初始化会话
     */
    public static function start()
    {
        if (!self::$initialized) {
            self::initSessionPath();
            self::initPrefix();

            if (!session_id() && !headers_sent()) {
                session_start();
            }

            if (!isset($_SESSION[self::$prefix])) {
                $_SESSION[self::$prefix] = [];
            }

            Log::debug('Session 已开启');

            self::$initialized = true;
        }
    }

    /**
     * 获取会话值，支持点号连接符
     * @param string $key 键名 (支持 foo.bar 格式)
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        self::start();

        $segments = explode('.', $key);
        $data = $_SESSION[self::$prefix];

        foreach ($segments as $segment) {
            if (!isset($data[$segment])) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * 设置会话值，支持点号连接符
     * @param string $key 键名 (支持 foo.bar 格式)
     * @param mixed $value 值
     */
    public static function set($key, $value)
    {
        self::start();

        $segments = explode('.', $key);
        $data = &$_SESSION[self::$prefix];

        // 遍历除最后一个段以外的所有段
        $lastSegment = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }
            $data = &$data[$segment];
        }

        $data[$lastSegment] = $value;
    }

    /**
     * 删除指定的会话值
     * @param string $key 键名 (支持 foo.bar 格式)
     */
    public static function delete($key)
    {
        self::start();

        $segments = explode('.', $key);
        $data = &$_SESSION[self::$prefix];

        // 遍历除最后一个段以外的所有段
        $lastSegment = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($data[$segment])) {
                return;
            }
            $data = &$data[$segment];
        }

        unset($data[$lastSegment]);
    }

    /**
     * 检查会话键是否存在
     * @param string $key 键名 (支持 foo.bar 格式)
     * @return bool
     */
    public static function has($key)
    {
        self::start();

        $segments = explode('.', $key);
        $data = $_SESSION[self::$prefix];

        foreach ($segments as $segment) {
            if (!isset($data[$segment])) {
                return false;
            }
            $data = $data[$segment];
        }

        return true;
    }

    /**
     * 获取所有会话数据
     * @return array
     */
    public static function all()
    {
        self::start();
        return $_SESSION[self::$prefix] ?? [];
    }

    /**
     * 清空所有会话数据
     */
    public static function clear()
    {
        self::start();
        $_SESSION[self::$prefix] = [];
    }

    /**
     * 销毁会话
     */
    public static function destroy()
    {
        if (session_id()) {
            session_destroy();
            self::$initialized = false;
            $_SESSION = [];
        }
    }

    /**
     * 获取会话保存路径
     * @return string
     */
    public static function getPath()
    {
        return session_save_path();
    }
}