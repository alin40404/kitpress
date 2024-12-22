<?php
namespace kitpress\models;

use kitpress\core\Model;

if (!defined('ABSPATH')) {
    exit;
}

class SessionModel extends Model {
    protected $table_name = 'sessions';

    /**
     * 获取会话数据
     */
    public function getSessionData($session_id) {
        return $this->where('session_id', $session_id)
                   ->where('session_expiry', '>', time())
                   ->get();
    }

    /**
     * 保存会话数据
     */
    public function saveSessionData($session_id, $key, $value, $expiry) {
        return $this->insert(
            [
                'session_id'    => $session_id,
                'session_key'   => $key,
                'session_value' => \maybe_serialize($value),
                'session_expiry' => $expiry
            ],
            ['%s', '%s', '%s', '%d']
        );
    }

    /**
     * 删除过期会话
     */
    public function cleanExpiredSessions() {
        return $this->where('session_expiry', '<', time())->delete();
    }

    /**
     * 删除指定会话
     */
    public function deleteSession($session_id) {
        return $this->where('session_id', $session_id)->delete();
    }
} 