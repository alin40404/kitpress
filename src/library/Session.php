<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;
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
            \wp_set_cookie($this->cookie, $this->session_id, [
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
    public function saveSession() {
        // 移除清理操作，只保存当前会话
        foreach ($this->data as $key => $value) {
            $this->model->saveSessionData(
                $this->session_id,
                $key,
                $value,
                time() + $this->cookie_expires
            );
        }

        // 更新缓存
        $this->cache->set('session_' . $this->session_id, $this->data, 3600);
    }

    /**
     * 清理过期会话（新方法）
     * 建议通过计划任务调用此方法
     */
    public function cleanExpiredSessions() {
        $this->model->cleanExpiredSessions();
    }

    /**
     * 获取会话数据
     */
    public function get($key, $default = null) {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * 设置会话数据
     */
    public function set($key, $value) {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * 删除会话数据
     */
    public function delete($key) {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * 检查会话数据是否存在
     */
    public function has($key) {
        return isset($this->data[$key]);
    }

    /**
     * 清空会话数据
     */
    public function clear() {
        $this->data = [];
        return $this;
    }

    /**
     * 获取所有会话数据
     */
    public function all() {
        return $this->data;
    }

    /**
     * 销毁会话
     */
    public function destroy() {
        $this->clear();
        \wp_delete_cookie($this->cookie);
        
        $this->model->deleteSession($this->session_id);
        $this->cache->delete('session_' . $this->session_id);
    }

    /**
     * 重新生成会话ID
     */
    public function regenerate() {
        $this->destroy();
        $this->session_id = $this->generateSessionId();
        $this->setCookie();
        return $this;
    }
}