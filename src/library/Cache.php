<?php
namespace kitpress\library;

use kitpress\core\abstracts\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 缓存管理类
 * 基于 WordPress Transients API,自动支持 Object Cache(Redis/Memcached)
 */
class Cache extends Singleton {
    /**
     * 缓存前缀
     */
    private $prefix;

    protected function __construct() {
        parent::__construct();
        $this->prefix = 'kp_' . Config::get('app.database.prefix', '');
    }

    /**
     * 获取缓存
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get($key, $default = null) {
        $value = \get_transient($this->prefix . $key);
        return $value !== false ? $value : $default;
    }

    /**
     * 设置缓存
     * @param string $key 缓存键名
     * @param mixed $value 缓存值
     * @param int $expires 过期时间(秒)
     * @return bool
     */
    public function set($key, $value, $expires = 3600) {
        return \set_transient($this->prefix . $key, $value, $expires);
    }

    /**
     * 删除缓存
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete($key) {
        return \delete_transient($this->prefix . $key);
    }

    /**
     * 清空所有缓存
     * @return bool
     */
    public function flush() {
        global $wpdb;
        return $wpdb->query(
            "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_" . $this->prefix . "%'"
        );
    }

    /**
     * 检查缓存是否存在
     * @param string $key 缓存键名
     * @return bool
     */
    public function has($key) {
        return $this->get($key) !== null;
    }

    /**
     * 递增
     * @param string $key 缓存键名
     * @param int $value 增加的值
     * @return int 新值
     */
    public function increment($key, $value = 1) {
        $current = $this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        return $new;
    }

    /**
     * 递减
     * @param string $key 缓存键名
     * @param int $value 减少的值
     * @return int 新值
     */
    public function decrement($key, $value = 1) {
        return $this->increment($key, -$value);
    }

    /**
     * 获取或设置缓存
     * @param string $key 缓存键名
     * @param callable $callback 回调函数
     * @param int $expires 过期时间(秒)
     * @return mixed
     */
    public function remember($key, $callback, $expires = 3600) {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $expires);
        return $value;
    }
} 