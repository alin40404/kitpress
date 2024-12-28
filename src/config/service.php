<?php

if (!defined('ABSPATH')) {
    exit;
}

return [
    'plugin' => [
        'class' => \kitpress\library\Plugin::class,
        'singleton' => true,
        'priority' => 1,
        'dependencies' => []
    ],
    'config' => [
        'class' => \kitpress\library\Config::class,
        'singleton' => true,
        'priority' => 2,
        'dependencies' => ['plugin']
    ],
    'log' => [
        'class' => \kitpress\library\Log::class,
        'singleton' => true,
        'priority' => 3,
        'dependencies' => ['plugin','config']
    ],

    'loader' => [
        'class' => \kitpress\library\Loader::class,
        'singleton' => true,
        'priority' => 4,
        'dependencies' => ['log']
    ],

    'cache' => [
        'class' => \kitpress\library\Cache::class,
        'singleton' => true,
        'priority' => 10,
        'dependencies' => ['log']
    ],

    'model' => [
        'class' => \kitpress\library\Model::class,
        'singleton' => true,
        'priority' => 10,
        'dependencies' => ['log']
    ],

    // 添加 DB 服务
    'db' => [
        'class' => \kitpress\library\Model::class,
        'singleton' => true,
        'priority' => 10, // 设置较高优先级，因为其他服务可能依赖它
        'dependencies' => ['log']
    ],

    'installer' => [
        'class' => \kitpress\library\Installer::class,
        'singleton' => true,
        'priority' => 12,
        'dependencies' => ['log']
    ],

    'session' => [
        'class' => \kitpress\library\Session::class,
        'singleton' => true,
        'priority' => 12,
        'dependencies' => ['log','cache']
    ],

    'router' => [
        'class' => \kitpress\library\Router::class,
        'singleton' => true,
        'priority' => 15,
        'dependencies' => ['log']
    ],

    'frontend' => [
        'class' => \kitpress\library\Frontend::class,
        'singleton' => true,
        'priority' => 20,
        'dependencies' => ['log','router']
    ],
    'backend' => [
        'class' => \kitpress\library\Backend::class,
        'singleton' => true,
        'priority' => 20,
        'dependencies' => ['log','router']
    ],
    'restapi' => [
        'class' => \kitpress\library\RestApi::class,
        'singleton' => true,
        'priority' => 20,
        'dependencies' => ['log','router']
    ],
];