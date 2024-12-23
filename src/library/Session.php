<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;
use kitpress\core\Installer;
use kitpress\models\SessionModel;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 会话管理类
 * 支持数据库存储和缓存存储
 */
class Session extends Singleton {
    const DAY_IN_SECONDS = 86400;    // 1天
    const HOUR_IN_SECONDS = 3600;    // 1小时
    const MINUTE_IN_SECONDS = 60;    // 1分钟

    /**
     * Cookie名称
     */
    private $cookie;

    /**
     * 会话ID
     */
    private $session_id;

    /**
     * 会话数据
     */
    private $data = [];

    /**
     * 缓存实例
     */
    private $cache;

    /**
     * 会话模型实例
     */
    private $model;

    /**
     * Cookie过期时间
     */
    private $cookie_expires;

    protected function __construct() {
        parent::__construct();
        
        $this->cookie = Config::get('app.session.cookie', 'kp_session');
        $this->cookie_expires = Config::get('app.session.expires', 48 * self::HOUR_IN_SECONDS);
        $this->cache = Cache::getInstance();
        $this->model = new SessionModel();

        $this->init();
    }

    /**
     * 初始化会话
     */
    private function init() {
        // 获取或创建会话ID
        $this->session_id = $this->getCookie();
        if (!$this->session_id) {
            $this->session_id = $this->generateSessionId();
            $this->setCookie();
        }

        // 加载会话数据
        $this->loadSession();

    }

    /**
     * 生成会话ID
     */
    private function generateSessionId() {
        return \wp_generate_password(32, false);
    }

    /**
     * 获取Cookie
     */
    private function getCookie() {
        return isset($_COOKIE[$this->cookie]) ? \sanitize_text_field($_COOKIE[$this->cookie]) : '';
    }

    /**
     * 设置Cookie
     */
    private function setCookie() {
        if (!\headers_sent()) {
            $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
            \setcookie($this->cookie, $this->session_id, [
                'expires'  => time() + $this->cookie_expires,
                'secure'   => \is_ssl(),
                'httponly' => true,
                'path'     => $cookie_path,
            ]);
        }
    }

    /**
     * 加载会话数据
     */
    private function loadSession() {
        // 首先尝试从缓存获取
        $this->data = $this->cache->get('session_' . $this->session_id);

        if ($this->data === null) {
            // 从数据库加载
            $results = $this->model->getSessionData($this->session_id);
            
            $this->data = [];
            foreach ($results as $row) {
                $this->data[$row->session_key] = \maybe_unserialize($row->session_value);
            }

            // 存入缓存
            $this->cache->set('session_' . $this->session_id, $this->data, 3600);
        }
    }

    /**
     * 保存会话数据（优化版）
     */
    public static function saveSession() {
        if( Config::get('app.session.enabled', false) ) return;

        $instance = self::getInstance();

        $instance->model->ensureTableExists();

        foreach ($instance->data as $key => $value) {
            $instance->model->saveSessionData(
                $instance->session_id,
                $key,
                $value,
                time() + $instance->cookie_expires
            );
        }

        $instance->cache->set('session_' . $instance->session_id, $instance->data, 3600);
    }

    /**
     * 清理过期会话（新方法）
     * 建议通过计划任务调用此方法
     */
    public static function cleanExpiredSessions() {
        $instance = self::getInstance();
        $instance->model->cleanExpiredSessions();
    }

    /**
     * 获取会话数据，支持点号分隔的多级键值
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        $instance = self::getInstance();
        $segments = explode('.', $key);
        $data = $instance->data;

        foreach ($segments as $segment) {
            if (!is_array($data) || !isset($data[$segment])) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * 设置会话数据，支持点号分隔的多级键值
     */
    public static function set($key, $value) {
        $instance = self::getInstance();
        $segments = explode('.', $key);
        $data = &$instance->data;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $data[$segment] = $value;
            } else {
                if (!isset($data[$segment]) || !is_array($data[$segment])) {
                    $data[$segment] = [];
                }
                $data = &$data[$segment];
            }
        }

        return $instance;
    }


    /**
     * 删除会话数据，支持点号分隔的多级键值
     */
    public static function delete($key) {
        $instance = self::getInstance();
        $segments = explode('.', $key);
        $data = &$instance->data;

        $lastSegment = array_pop($segments);
        foreach ($segments as $segment) {
            if (!is_array($data) || !isset($data[$segment])) {
                return $instance;
            }
            $data = &$data[$segment];
        }

        unset($data[$lastSegment]);
        return $instance;
    }


    /**
     * 检查会话数据是否存在，支持点号分隔的多级键值
     */
    public static function has($key) {
        $instance = self::getInstance();
        $segments = explode('.', $key);
        $data = $instance->data;

        foreach ($segments as $segment) {
            if (!is_array($data) || !isset($data[$segment])) {
                return false;
            }
            $data = $data[$segment];
        }

        return true;
    }

    /**
     * 清空会话数据
     */
    public static function clear() {
        $instance = self::getInstance();
        $instance->data = [];
        return $instance;
    }


    /**
     * 获取所有会话数据
     */
    public static function all() {
        $instance = self::getInstance();
        return $instance->data;
    }

    /**
     * 销毁会话
     */
    public static function destroy() {
        $instance = self::getInstance();
        $instance->clear();
        \wp_delete_cookie($instance->cookie);

        $instance->model->deleteSession($instance->session_id);
        $instance->cache->delete('session_' . $instance->session_id);
    }

    /**
     * 重新生成会话ID
     */
    public static function regenerate() {
        $instance = self::getInstance();
        $instance->destroy();
        $instance->session_id = $instance->generateSessionId();
        $instance->setCookie();
        return $instance;
    }

}