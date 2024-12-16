<?php

if (!defined('ABSPATH')) {
    exit;
}


//return [
//    // 定时任务列表
//    'tasks' => [
//        'daily' => [
//            'survey_cleanup' => [
//                'hook' => 'kitpress_daily_survey_cleanup',
//                'recurrence' => 'daily',
//                'callback' => 'KitpressPlugin\core\Cron\SurveyTasks::cleanup',
//                'args' => [],
//                'description' => '清理过期的调查数据'
//            ],
//            'notification' => [
//                'hook' => 'kitpress_daily_notification',
//                'recurrence' => 'daily',
//                'callback' => 'KitpressPlugin\core\Cron\NotificationTasks::send_daily_report',
//                'args' => [],
//                'description' => '发送每日报告'
//            ]
//        ],
//        'weekly' => [
//            'stats_report' => [
//                'hook' => 'kitpress_weekly_stats_report',
//                'recurrence' => 'weekly',
//                'callback' => 'KitpressPlugin\core\Cron\ReportTasks::generate_weekly_stats',
//                'args' => [],
//                'description' => '生成每周统计报告'
//            ]
//        ],
//        'custom' => [
//            'survey_reminder' => [
//                'hook' => 'kitpress_survey_reminder',
//                'recurrence' => 'twice_daily',
//                'callback' => 'KitpressPlugin\core\Cron\SurveyTasks::send_reminders',
//                'args' => [],
//                'description' => '发送调查提醒'
//            ]
//        ]
//    ],
//
//    // 自定义时间间隔
//    'intervals' => [
//        'twice_daily' => [
//            'interval' => 12 * HOUR_IN_SECONDS,
//            'display' => '每天两次'
//        ],
//        'every_6_hours' => [
//            'interval' => 6 * HOUR_IN_SECONDS,
//            'display' => '每6小时'
//        ]
//    ]
//];