<?php

if (!defined('ABSPATH')) {
    exit;
}

return [
    /// recurrence  默认支持
    /// hourly - 每小时执行一次
    /// daily - 每天执行一次
    /// twicedaily - 每天执行两次

    // 定时任务列表
    'tasks' => [
        'session_cleanup' => [
            'recurrence' => 'daily',
            'callback' => ['\kitpress\library\Session', 'cleanExpiredSessions'],
            'args' => [],
            'description' => '清理过期的会话数据'
        ],

    ],

    // 自定义时间间隔
    'intervals' => [
        'every_30_minutes' => [
            'interval' => 30,
            'display' => '每30分钟'
        ],
        'every_6_hours' => [
            'interval' => 6 * 60,
            'display' => '每6小时'
        ],
        'weekly' => [
            'interval' => 7 * 24 * 60,
            'display' => '每周一次'
        ],
        'monthly' => [
            'interval' => 30 * 24 * 60,
            'display' => '每月一次'
        ],
    ]
];