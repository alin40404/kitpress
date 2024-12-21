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
        'backend' => true,     // 后台是否启用会话
        'frontend' => false,   // 前台是否启用会话
        'name' => 'kitsessid',
        'lifetime' => 7 * 24 * 3600,  // 7天，单位：秒
        'gc' => [
            'probability' => 0,        // 禁用 PHP 自带的垃圾回收
            'divisor' => 100
        ],
        'cookie' => [
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    ],

    'database' => [
        'prefix' => 'kitpress_plugin_',
    ],

    // 默认值
    'features' => [
        'auto_load' => true,
        'debug_mode' => false,
        'per_page' => 10,
        'delete_data_on_uninstall' => false,
        'requires_license' => false,
    ],

    'messages' => [
        'errors' => [
            'custom_error' => [
                'message' => '发生错误：%s 和 %s',
                'title' => '自定义错误',
                'type' => 'warning'
            ],
            'php_version' => [
                'message' => '【' . KITPRESS_NAME . '】需要PHP 7.4或更高版本',
                'title' => '版本检查失败',
                'type' => 'error',
            ],
            'wp_version' => [
                'message' => '【' . KITPRESS_NAME . '】需要WordPress 5.0或更高版本',
                'title' => '版本检查失败',
                'type' => 'error',
            ]
        ]
    ],

	'init' => [
	],

];
