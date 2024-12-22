<?php

if (!defined('ABSPATH')) {
    exit;
}

return [
    // 外部命名空间
    'namespace' => 'KitpressPlugin',
    // 当前版本
    'version' => '1.0.0',
    // 数据库版本
    'db_version' => '1.0.0',
    // 语言包标识
    'text_domain' => 'kitpress-plugin',

    'options' => [// 保存到数据库的常量字段名
        'settings_key' => 'kitpress_plugin_settings',
        'db_version_key' => 'kitpress_plugin_db_version',
        'uninstall_key' => 'kitpress_plugin_uninstall_settings',
        'plugin_meta_key' => 'kitpress_plugin_meta',
        'license_key' => 'kitpress_plugin_license',
    ],
    'custom_options' => [
       // 'custom_option_1' => 'default_value_1',
       // 'custom_option_2' => 'default_value_2'
    ],

    'session' => [
        'enabled' => false, // 是否启用 session
        'table' => 'sessions',
        'cookie' => 'kp_session',
        'expires' => 48 * 60,
    ],

    'database' => [
        'prefix' => 'kitpress_plugin_',
    ],

    // 默认值
    'features' => [
        'debug_mode' => false,
        'per_page' => 10,
        'delete_data_on_uninstall' => false,
        'requires_license' => false,
    ],

	'init' => [
	],

];
