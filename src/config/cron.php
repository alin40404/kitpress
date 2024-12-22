<?php

if (!defined('ABSPATH')) {
    exit;
}


return [
    // 定时任务列表
    'tasks' => [
        'session_cleanup' => [
            'hook' => 'kitpress_daily_session_cleanup',
            'recurrence' => 'daily',
            'callback' => ['kitpress\library\Session', 'cleanExpiredSessions'],
            'args' => [],
            'description' => '清理过期的会话数据'
        ],

        'custom_task' => [
            'hook' => 'kitpress_custom_task',
            'recurrence' => 'hourly',
            'callback' => [$someInstance, 'methodName'],
            'args' => ['param1', 'param2'],
            'description' => '自定义任务'
        ],

        'another_task' => [
            'hook' => 'kitpress_another_task',
            'recurrence' => 'twice_daily',
            'callback' => 'kitpress\library\SomeClass::someMethod',
            'description' => '另一个任务'
        ]
    ],

    // 自定义时间间隔
    'intervals' => [
        'twice_daily' => [
            'interval' => 12 * 60,
            'display' => '每天两次'
        ],
        'every_6_hours' => [
            'interval' => 6 * 60,
            'display' => '每6小时'
        ]
    ]
];