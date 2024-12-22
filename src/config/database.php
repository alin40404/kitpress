<?php
if (!defined('ABSPATH')) {
    exit;
}
return [
    'versions' => [
        // 框架核心表 (kp_开头)
        'kp_101' => [
            'tables' => [
                'sessions' => [
                    'name' => 'sessions',
                    'columns' => [
                        'session_id' => 'VARCHAR(40) NOT NULL',
                        'session_key' => 'VARCHAR(255) NOT NULL',
                        'session_value' => 'LONGTEXT NOT NULL',
                        'session_expiry' => 'BIGINT UNSIGNED NOT NULL',
                        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                        'PRIMARY KEY' => '(session_id, session_key)',
                        'KEY session_expiry' => '(session_expiry)'
                    ]
                ]
            ]
        ],
    ]
];