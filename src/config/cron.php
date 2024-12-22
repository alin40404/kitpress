<?php

if (!defined('ABSPATH')) {
    exit;
}


return [
    // 定时任务列表
    'tasks' => [
        'daily' => [
            'session_cleanup' => [
                'hook' => 'kitpress_daily_session_cleanup',
                'recurrence' => 'daily',
                'callback' => 'kitpress\library\Session::cleanExpiredSessions',
                'args' => [],
                'description' => '清理过期的会话数据'
            ],
        ],
    ],

    // 自定义时间间隔
    'intervals' => [
        'twice_daily' => [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => '每天两次'
        ],
        'every_6_hours' => [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => '每6小时'
        ]
    ]
];