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
    ],
    // 添加 DB 服务
    'db' => [
        'class' => \kitpress\library\Model::class,
        'singleton' => true,
        'priority' => 2, // 设置较高优先级，因为其他服务可能依赖它
        'dependencies' => ['config']
    ],
    'frontend' => [
        'class' => \kitpress\library\Frontend::class,
        'singleton' => true,
        'priority' => 4,
        'dependencies' => ['config','router']
    ],
    'backend' => [
        'class' => \kitpress\library\Backend::class,
        'singleton' => true,
        'priority' => 4,
        'dependencies' => ['config','router']
    ],
    'restapi' => [
        'class' => \kitpress\library\RestApi::class,
        'singleton' => true,
        'priority' => 4,
        'dependencies' => ['config','router']
    ],
];