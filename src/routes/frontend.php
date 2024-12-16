<?php


return [
    // 注册短码
    'shortcodes' => [
        // 'demo' => 'DemoController@demo',
    ],
    // 表单处理数据
    'post' => [
        // 需要登录的 AJAX 请求
        'private' => [
            // 'demo' => 'DemoController@demo',
        ],
        // 不需要登录的 AJAX 请求
        'public' => [
        ]
    ],
    // Ajax处理路由
    'ajax' => [
        // 需要登录的 AJAX 请求
        'private' => [
           // 'demo' => 'DemoController@demo',
        ],
        // 不需要登录的 AJAX 请求
        'public' => [
        ]
    ]
];