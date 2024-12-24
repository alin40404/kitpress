<?php

if (!defined('ABSPATH')) {
    exit;
}

return [
    'cache' => [
        'class' => \kitpress\library\Cache::class,
        'singleton' => true,
        'priority' => 2,
        'dependencies' => ['config']
    ],

    'router' => [
        'class' => \kitpress\library\Router::class,
        'singleton' => true,
        'priority' => 3,
        'dependencies' => ['config']
    ],

    'session' => [
        'class' => \kitpress\library\Session::class,
        'singleton' => true,
        'priority' => 2,
        'dependencies' => ['config']
    ]
];