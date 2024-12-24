<?php
namespace kitpress\core;

use kitpress\core\abstracts\Facade;
use kitpress\core\exceptions\BootstrapException;
use kitpress\library\Cache;
use kitpress\library\Config;
use kitpress\utils\Lang;
use kitpress\utils\Loader;

if (!defined('ABSPATH')) {
    exit;
}

class Bootstrap {
    /**
     * 标记是否已初始化
     */
    private static $initialized = false;


    /**
     * 框架引导启动
     * 初始化基础设施（工具类、配置等）
     * @return bool
     * @throws BootstrapException
     */
    public static function initialize() {
        if (self::$initialized) {
            return self::$initialized;
        }

        try {
            // 加载配置
            self::loadConfiguration();

            //  初始化工具类
            self::initializeUtils();

            //  初始化容器
            self::initializeContainer();

            //  注册核心服务
            self::registerCoreServices();


            self::$initialized = true;
        } catch (\Exception $e) {
            throw new BootstrapException(
                "Framework initialization failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        return self::$initialized;
    }

    /**
     * 加载配置文件
     */
    private static function loadConfiguration() {
        // 载入通用配置文件
        Config::load([
            'app',
            'database',
            'menu',
            'cron',
        ]);
    }

    /**
     * 初始化容器
     */
    private static function initializeContainer() {
        $container = Container::getInstance();
        Facade::setContainer($container);
    }

    /**
     * 注册核心服务
     */
    private static function registerCoreServices() {
        $container = Container::getInstance();

        // 注册缓存服务
        $container->singleton('cache', function() {
            return Cache::getInstance();
        });

    }

    /**
     * 初始化工具类
     */
    private static function initializeUtils() {
        // 注册加载器
        Loader::register();

        // 初始化语言工具
        Lang::init();
    }

    /**
     * 禁止实例化
     */
    private function __construct() {}
    private function __clone() {}
    private function __wakeup() {}
}