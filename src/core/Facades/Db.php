<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}


class Db extends Facade {
    /**
     * 获取组件的注册名称
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'db';
    }
}