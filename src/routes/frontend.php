<?php
if (!defined('ABSPATH')) {
    exit;
}

return [
    // 注册短码
    'shortcodes' => [
    ],
    // 表单处理数据
    'post' => [
        // 需要登录的 AJAX 请求
        'private' => [
        ],
        // 不需要登录的 AJAX 请求
        'public' => [
        ]
    ],
    // Ajax处理路由
    'ajax' => [
        // 需要登录的 AJAX 请求
        'private' => [
        ],
        // 不需要登录的 AJAX 请求
        'public' => [
        ]
    ]
];