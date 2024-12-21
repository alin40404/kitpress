<?php
namespace kitpress\utils;

use kitpress\Kitpress;

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
            self::$prefix = self::$basePrefix . Helper::key();
        }
    }

    /**
     * 初始化 session 路径
     * @return bool 是否成功初始化
     */
    private static function initSessionPath()
    {
        // 1. 首先尝试使用 PHP 配置的 session 目录
        $currentPath = session_save_path();
        if (!empty($currentPath) && is_writable($currentPath)) {
            Log::debug('Using PHP configured session path: {details}', [
                'details' => json_encode([
                    'path' => $currentPath,
                    'writable' => true,
                    'permissions' => substr(sprintf('%o', fileperms($currentPath)), -4)
                ])
            ]);
            return true;
        }

        // 2. 如果默认路径不可用，使用系统临时目录
        $tempDir = sys_get_temp_dir();
        $sessionPath = $tempDir . DIRECTORY_SEPARATOR . 'kitpress_sessions';

        // 3. 创建或检查 session 目录
        if (!file_exists($sessionPath)) {
            // 保存当前的 umask
            $oldUmask = umask(0022);  // 设置目录权限为 755，文件权限为 644

            try {
                // 创建目录
                if (!wp_mkdir_p($sessionPath)) {
                    Log::error('Failed to create session directory: {details}', [
                        'details' => json_encode([
                            'path' => $sessionPath,
                            'temp_dir' => $tempDir,
                            'error' => error_get_last()
                        ])
                    ]);
                    return false;
                }

                // 创建保护文件
                self::createProtectionFiles($sessionPath);

                Log::debug('Session directory created: {details}', [
                    'details' => json_encode([
                        'path' => $sessionPath,
                        'permissions' => substr(sprintf('%o', fileperms($sessionPath)), -4)
                    ])
                ]);
            } finally {
                // 恢复原来的 umask
                umask($oldUmask);
            }
        }

        // 4. 确保目录可写
        if (!is_writable($sessionPath)) {
            Log::error('Session directory is not writable: {details}', [
                'details' => json_encode([
                    'path' => $sessionPath,
                    'current_permissions' => substr(sprintf('%o', fileperms($sessionPath)), -4),
                    'owner' => function_exists('posix_getpwuid') ?
                        posix_getpwuid(fileowner($sessionPath))['name'] : fileowner($sessionPath),
                    'group' => function_exists('posix_getgrgid') ?
                        posix_getgrgid(filegroup($sessionPath))['name'] : filegroup($sessionPath)
                ])
            ]);
            return false;
        }

        // 5. 设置 session 保存路径
        if (!session_save_path($sessionPath)) {
            Log::error('Failed to set session save path: {details}', [
                'details' => json_encode([
                    'path' => $sessionPath,
                    'error' => error_get_last()
                ])
            ]);
            return false;
        }

        // 6. 记录成功信息
        Log::debug('Session path initialized: {details}', [
            'details' => json_encode([
                'from' => $currentPath ?: 'empty',
                'to' => $sessionPath,
                'dir_permissions' => substr(sprintf('%o', fileperms($sessionPath)), -4),
                'writable' => true,
                'save_path' => session_save_path()
            ])
        ]);

        return true;
    }

    /**
     * 创建保护文件
     */
    private static function createProtectionFiles($path)
    {
        // 添加 .htaccess
        $htaccess = $path . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            $content = "Deny from all\n";
            file_put_contents($htaccess, $content);
            chmod($htaccess, 0644);
        }

        // 添加 index.html
        $index = $path . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($index)) {
            file_put_contents($index, '');
            chmod($index, 0644);
        }

        // 添加 .gitignore
        $gitignore = $path . DIRECTORY_SEPARATOR . '.gitignore';
        if (!file_exists($gitignore)) {
            $content = "*\n!.gitignore\n!.htaccess\n!index.html\n";
            file_put_contents($gitignore, $content);
            chmod($gitignore, 0644);
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

            // 初始化 session 路径
            if (!self::initSessionPath()) {
                Log::error('Failed to initialize session path');
                return;
            }

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
     * 注册垃圾回收任务
     * @return void
     */
    public static function registerGcTask()
    {
        // 注册自定义计划任务间隔
        add_filter('cron_schedules', function($schedules) {
            $schedules['kitpress_twice_daily'] = [
                'interval' => 12 * HOUR_IN_SECONDS,
                'display' => 'Twice Daily'
            ];
            return $schedules;
        });

        // 注册任务钩子
        add_action('kitpress_session_gc_' . Helper::key()  , [self::class, 'gc']);

        // 如果任务未安排，则安排它
        if (!wp_next_scheduled('kitpress_session_gc_' . Helper::key())) {
            $timestamp = strtotime('today 2:00am');
            if (time() > $timestamp) {
                $timestamp = strtotime('tomorrow 2:00am');
            }
            wp_schedule_event($timestamp, 'kitpress_twice_daily', 'kitpress_session_gc_' . Helper::key());
        }
    }



    /**
     * 清理过期的 session 文件
     * @param int $maxLifetime 最大生命周期（秒）
     * @return int 清理的文件数量
     */
    public static function gc($maxLifetime = null)
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log('Starting session garbage collection...');
        }

        $startTime = microtime(true);

        try {
            if ($maxLifetime === null) {
                $maxLifetime = Config::get('session.lifetime', 7 * self::DAY_IN_SECONDS);
            }

            $sessionPath = session_save_path();
            if (empty($sessionPath) || !is_dir($sessionPath)) {
                $sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kitpress_sessions';
            }

            if (!is_dir($sessionPath)) {
                return 0;
            }

            $now = time();
            $count = 0;
            $batchSize = 100;
            $processed = 0;
            $files = [];

            $dir = dir($sessionPath);
            while (false !== ($file = $dir->read())) {
                if (strpos($file, 'sess_') === 0) {
                    $files[] = $sessionPath . DIRECTORY_SEPARATOR . $file;
                    $processed++;

                    // 分批处理
                    if (count($files) >= $batchSize) {
                        $count += self::processBatch($files, $now, $maxLifetime);
                        $files = [];

                        if (defined('WP_CLI') && WP_CLI) {
                            WP_CLI::log("Processed {$processed} files...");
                        }
                    }
                }
            }
            $dir->close();

            // 处理剩余的文件
            if (!empty($files)) {
                $count += self::processBatch($files, $now, $maxLifetime);
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Session garbage collection completed: {details}', [
                'cleaned' => $count,
                'processed' => $processed,
                'duration' => $duration . 's',
                'path' => $sessionPath
            ]);

            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::success(
                    "Garbage collection completed in {$duration}s. " .
                    "Processed {$processed} files, cleaned {$count} files."
                );
            }

            return $count;

        } catch (\Exception $e) {
            Log::error('Session garbage collection failed: {error}, trace: {trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (defined('WP_CLI') && WP_CLI) {
                WP_CLI::error($e->getMessage());
            }

            return false;
        }
    }

    /**
     * 批量处理文件
     */
    private static function processBatch($files, $now, $maxLifetime)
    {
        $count = 0;
        foreach ($files as $file) {
            try {
                // 首先检查文件时间
                if ($now - filemtime($file) <= $maxLifetime) {
                    continue;
                }

                // 只对过期文件进行内容检查
                $fp = fopen($file, 'rb');
                if ($fp) {
                    $content = fread($fp, 100);
                    fclose($fp);

                    if (strpos($content, self::$basePrefix) !== false) {
                        if (@unlink($file)) {
                            $count++;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to process session file: {file}', [
                    'file' => basename($file),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        return $count;
    }

    /**
     * 在插件激活时注册计划任务
     */
    public static function activate()
    {
        self::registerGcTask();
    }

    /**
     * 在插件停用时清理计划任务
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('kitpress_session_gc_' . Helper::key());
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