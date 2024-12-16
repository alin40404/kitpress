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
     * 初始化会话
     */
    public static function start()
    {
        if (!self::$initialized) {
            if (!session_id() && !headers_sent()) {
                session_start();
            }
            if( Config::get('app.features.debug_mode') ) Log::error('Session 已开启');
            self::$initialized = true;
        }
    }

    /**
     * 获取会话值
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 设置会话值
     * @param string $key 键名
     * @param mixed $value 值
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * 删除指定的会话值
     * @param string $key 键名
     */
    public static function delete($key)
    {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * 检查会话键是否存在
     * @param string $key 键名
     * @return bool
     */
    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * 获取所有会话数据
     * @return array
     */
    public static function all()
    {
        self::start();
        return $_SESSION;
    }

    /**
     * 清空所有会话数据
     */
    public static function clear()
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * 销毁会话
     */
    public static function destroy()
    {
        if (session_id()) {
            session_destroy();
            self::$initialized = false;
        }
    }
}