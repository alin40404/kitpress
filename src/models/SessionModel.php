<?php
namespace kitpress\models;

use kitpress\core\Installer;
use kitpress\library\Model;
use kitpress\core\Facades\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SessionModel extends Model {
    protected $table_name = 'sessions';

    /**
     * 检查并创建会话表
     */
    public function ensureTableExists() {

        if( Installer::tableExists($this->table_name) == false ) {
            $kp_version = str_replace('.','' ,KITPRESS_VERSION);

            Installer::createTables([
                $this->table_name => Config::get('database.versions.kp_'. $kp_version .'.tables.sessions', [])
            ]);
        }

    }

    /**
     * 获取会话数据
     */
    public function getSessionData($session_id) {
        $this->ensureTableExists();
        return $this->where('session_id', $session_id)
                   ->where('session_expiry', '>', time())
                   ->get();
    }

    /**
     * 保存会话数据
     */
    public function saveSessionData($session_id, $key, $value, $expiry) {
        $this->ensureTableExists();
        return $this->insertOrUpdate(
            [
                'session_id'    => $session_id,
                'session_key'   => $key,
                'session_value' => \maybe_serialize($value),
                'session_expiry' => $expiry
            ],
            ['session_value', 'session_expiry']
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