<?php
namespace kitpress\utils;

use kitpress\core\abstracts\Singleton;

if (!defined('ABSPATH')) {
    exit;
}

class Log extends Singleton {
    private $requestId;

    protected function __construct()
    {
        $this->init();
    }

    private function init() {
        // 生成UUID
        $this->requestId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // 简化版
        /*$this->requestId = sprintf('%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );*/
    }

    public static function error($message) {

        $instance = self::getInstance();

        $logMessage = sprintf('[%s] %s',
            $instance->requestId,
            $message
        );
        error_log($logMessage);
    }
}