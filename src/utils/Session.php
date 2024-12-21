<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}

class Session
{
    const DAY_IN_SECONDS = 86400;    // 1天
    const HOUR_IN_SECONDS = 3600;    // 1小时
    const MINUTE_IN_SECONDS = 60;    // 1分钟

    private static $initialized = false;
    private static $basePrefix = 'kitpress_';
    private static $prefix;
    private static $sessionName;     // 新增 session name 属性

    /**
     * 初始化 session name
     */
    private static function initSessionName()
    {
        if (empty(self::$sessionName)) {
            // 从配置获取，如果没有配置则使用默认值
            self::$sessionName = Config::get('session.name', 'kitsessid');

            Log::debug('Session name initialized: {name}', [
                'name' => self::$sessionName
            ]);
        }
    }


    /**
     * 初始化前缀
     */
    private static function initPrefix()
    {
        if (empty(self::$prefix)) {
            self::$prefix = self::$basePrefix . Config::get('app.key', '');;
        }
    }

    /**
     * 启动 session
     */
    public static function start()
    {
        if (!self::$initialized) {
            self::initPrefix();
            self::initSessionName();  // 初始化 session name

            // 使用配置的 session name
            session_name(self::$sessionName);

            if (!session_id()) {
                if (headers_sent()) {
                    Log::warning('Headers already sent, cannot start session');
                    return;
                }

                $lifetime = Config::get('session.lifetime', 7 * self::DAY_IN_SECONDS);

                // 应用所有 session 配置
                ini_set('session.gc_probability', Config::get('session.gc.probability', 0));
                ini_set('session.gc_divisor', Config::get('session.gc.divisor', 100));
                ini_set('session.gc_maxlifetime', $lifetime);  // 使用相同的 lifetime
                ini_set('session.cookie_path', Config::get('session.cookie.path', '/'));
                ini_set('session.cookie_domain', Config::get('session.cookie.domain', ''));
                ini_set('session.cookie_secure', Config::get('session.cookie.secure', false));
                ini_set('session.cookie_httponly', Config::get('session.cookie.httponly', true));
                ini_set('session.cookie_samesite', Config::get('session.cookie.samesite', 'Lax'));

                session_start([
                    'cookie_lifetime' => $lifetime,
                    'read_and_close'  => false,
                ]);

                Log::debug('Session started with lifetime: {details}', [
                        'details' => json_encode([
                            'session_id' => session_id(),
                            'session_name' => session_name(),
                            'lifetime' => $lifetime,
                            'cookie_path' => ini_get('session.cookie_path'),
                            'cookie_secure' => ini_get('session.cookie_secure'),
                            'cookie_httponly' => ini_get('session.cookie_httponly'),
                            'cookie_samesite' => ini_get('session.cookie_samesite')
                        ]),
                    ]
                );
            }

            if (!isset($_SESSION[self::$prefix])) {
                $_SESSION[self::$prefix] = [];
            }

            self::$initialized = true;
        }
    }

    /**
     * 设置 session 值
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value)
    {
        self::start();

        $segments = explode('.', $key);
        $data = &$_SESSION[self::$prefix];

        $lastSegment = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($data[$segment]) || !is_array($data[$segment])) {
                $data[$segment] = [];
            }
            $data = &$data[$segment];
        }

        $data[$lastSegment] = $value;

        Log::debug('Session data set: {key} = {value}, session_id = {session_id}', [
            'key' => $key,
            'value' => is_array($value) ? json_encode($value) : $value,
            'session_id' => session_id()
        ]);
    }

    /**
     * 获取 session 值
     * @param string $key
     * @param mixed $default
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
     * 删除 session 值
     * @param string $key
     */
    public static function delete($key)
    {
        self::start();

        $segments = explode('.', $key);
        $data = &$_SESSION[self::$prefix];

        $lastSegment = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($data[$segment])) {
                return;
            }
            $data = &$data[$segment];
        }

        unset($data[$lastSegment]);

        Log::debug('Session data deleted: {key}, session_id = {session_id}', [
            'key' => $key,
            'session_id' => session_id()
        ]);
    }

    /**
     * 清除所有 session 数据
     */
    public static function clear()
    {
        self::start();
        $_SESSION[self::$prefix] = [];

        Log::debug('Session data cleared, session_id = {session_id}', [
            'session_id' => session_id()
        ]);
    }

    /**
     * 销毁 session
     */
    public static function destroy()
    {
        if (session_id()) {
            session_destroy();
            self::$initialized = false;
            $_SESSION = [];

            Log::debug('Session destroyed');
        }
    }

    /**
     * 清理过期的 session 文件
     * @param int $maxLifetime 最大生命周期（秒）
     * @return int 清理的文件数量
     */
    public static function gc($maxLifetime = null)
    {
        if ($maxLifetime === null) {
            $maxLifetime = Config::get('session.lifetime', 7 * self::DAY_IN_SECONDS);
        }

        $sessionPath = session_save_path();
        $now = time();
        $count = 0;

        foreach (glob($sessionPath . "/sess_*") as $file) {
            $content = file_get_contents($file, false, null, 0, 100);
            if (strpos($content, self::$basePrefix) === false) {
                continue;
            }

            if ($now - filemtime($file) > $maxLifetime) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        Log::debug('Session garbage collection completed: {details}', [
            'cleaned_files' => $count,
            'lifetime' => $maxLifetime,
            'session_path' => $sessionPath
        ]);

        return $count;
    }

    /**
     * 注册定期清理任务
     */
    public static function registerGcTask()
    {
        if (!wp_next_scheduled('kitpress_session_gc')) {
            wp_schedule_event(time(), 'daily', 'kitpress_session_gc');
        }
        add_action('kitpress_session_gc', [self::class, 'gc']);
    }

    /**
     * 在插件激活时注册清理任务
     */
    public static function activate()
    {
        self::registerGcTask();
    }

    /**
     * 在插件停用时移除清理任务
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('kitpress_session_gc');
    }

    /**
     * 检查键是否存在
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        return self::get($key) !== null;
    }
}