<?php
namespace kitpress;

use kitpress\core\abstracts\Singleton;
use kitpress\core\Bootstrap;
use kitpress\core\Container;
use kitpress\core\exceptions\BootstrapException;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Helper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 框架唯一入口类，提供外部调用。
 */
class Kitpress extends Singleton
{
    /**
     * 插件根目录路径数组
     * @var array 键为命名空间，值为路径
     */
    private static array $rootPaths = [];

    /**
     * 当前插件命名空间标识
     * @var string
     */
    private static string $namespace = '';

    /**
     * 当前容器实例
     * @var Container
     */
    private Container $container;

    /**
     * 构造函数
     * @param string $rootPath 插件根目录路径
     */
    protected function __construct() {
        parent::__construct();
    }


    /**
     * 获取插件实例
     * 如果命名空间未设置会抛出异常
     * 
     * @return static
     * @throws \RuntimeException
     */
    public static function getInstance(): self
    {
        if(empty(self::$namespace)){
            throw new \RuntimeException('Kitpress框架初始化失败，请检查插件路径是否设置正确');
        }

        $key = self::getInstanceKey();

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new static();
        }

        return self::$instances[$key];
    }

    /**
     * 获取实例存储键名
     *
     * @return string
     */
    private static function getInstanceKey(): string
    {
        return md5(self::$namespace . '\\' . static::class);
    }

    /**
     * 定义框架基础常量
     * 初始化框架所需的全局常量
     *
     * @return void
     */
    public static function constants(): void
    {
        !defined('KITPRESS_VERSION') && define('KITPRESS_VERSION', '1.0.2');
        !defined('KITPRESS_NAME') && define('KITPRESS_NAME', 'kitpress');
        !defined('KITPRESS___FILE__') && define('KITPRESS___FILE__', __FILE__);
        !defined('KITPRESS_PATH') && define('KITPRESS_PATH', \plugin_dir_path(__FILE__));
        !defined('KITPRESS_CORE_NAMESPACE') && define('KITPRESS_CORE_NAMESPACE', KITPRESS_NAME);
        !defined('KITPRESS_TEXT_DOMAIN') && define('KITPRESS_TEXT_DOMAIN', md5(KITPRESS_NAME));
    }

    /**
     * 创建或获取实例并初始化根路径
     * 框架入口方法，用于初始化插件
     * 
     * @param string $rootPath 插件根目录路径
     * @return static
     */
    public static function boot(string $rootPath): Kitpress
    {
        self::constants();
        self::setRootPath($rootPath);
        self::includes($rootPath);

        $instance = self::getInstance();
        $instance->container = Container::getInstance(self::$namespace);

        return $instance;
    }

    /**
     * 引入助手函数文件
     * 加载框架和插件的函数文件
     * 
     * @param string $rootPath 插件根目录路径
     * @return void
     */
    private static function includes(string $rootPath): void
    {
        $files = [
            'function',
        ];

        // 移除末尾的斜杠
        $rootPath = rtrim($rootPath, '/\\');

        // 引入助手函数
        foreach ($files as $file) {

            if(file_exists($rootPath . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . $file . '.php')){
                require_once $rootPath . DIRECTORY_SEPARATOR . 'functions' . DIRECTORY_SEPARATOR . $file . '.php';
            }

            if(file_exists(KITPRESS_PATH . 'functions' . DIRECTORY_SEPARATOR . $file . '.php')){
                require_once KITPRESS_PATH . 'functions' . DIRECTORY_SEPARATOR . $file . '.php';
            }
        }
    }

    /**
     * 设置插件根目录路径
     * 生成并存储插件的命名空间标识
     * 
     * @param string $rootPath 插件根目录路径
     * @return void
     * @throws \RuntimeException
     */
    private static function setRootPath(string $rootPath): void
    {
        if (empty($rootPath) || !is_dir($rootPath)) ErrorHandler::die('插件根目录不正确');

        self::$namespace = Helper::key($rootPath);

        if( isset( self::$rootPaths[self::$namespace]) ) return;

        self::$rootPaths[self::$namespace] = $rootPath;
    }

    /**
     * 获取指定命名空间的插件根目录路径
     * 
     * @param string $namespace 插件命名空间
     * @return string
     * @throws \Exception
     */
    public static function getRootPath(string $namespace): string
    {
        if( !isset(self::$rootPaths[$namespace])) throw new \Exception('插件目录不存在');
        return self::$rootPaths[$namespace];
    }

    /**
     * 插件激活回调
     * 执行插件激活时的必要操作
     *
     * @return void
     * @throws BootstrapException
     */
    public function activate(): void
    {
        try{
            Bootstrap::boot($this->container)->start();
            $this->container->get('installer')->activate();
        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
    }

    /**
     * 插件停用回调
     * 执行插件停用时的清理操作
     *
     * @return void
     * @throws BootstrapException
     */
    public function deactivate(): void
    {
        try{
            Bootstrap::boot($this->container)->start();
            $this->container->get('installer')->deactivate();
        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
    }

    /**
     * 加载插件
     * 注册plugins_loaded钩子以启动插件
     *
     * @return void
     */
    public function loaded(): void
    {
        // 保存当前命名空间
        $currentNamespace = self::$namespace;

        \add_action('plugins_loaded', function () use ($currentNamespace) {
            self::useNamespace($currentNamespace);
            $this->run();
        }, 20);
    }

    /**
     * 运行插件
     * 执行插件的主要初始化流程
     *
     * @return void
     */
    public function run(): void
    {
        try{
            // 框架引导启动
            $bootstap = Bootstrap::boot($this->container);
            $bootstap->start();
            $bootstap->run();
            $bootstap->shutdown();

        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
    }

    /**
     * 获取当前插件的命名空间
     *
     * @return string
     */
    public static function getNamespace(): string
    {
        return self::$namespace;
    }

    /**
     * 获取当前容器实例
     *
     * @return Container
     */
    public static function getContainer(): Container
    {
        return self::getInstance()->container;
    }

    /**
     * 设置当前容器实例
     * 根据当前命名空间重新创建容器
     *
     * @return void
     */
    public static function setContainer(): void
    {
        $instance = self::getInstance();
        $instance->container = Container::getInstance(self::$namespace);
    }

   /**
     * 切换当前使用的容器命名空间
     * 用于在多插件环境中切换上下文
     * 
     * @param string $namespace 目标命名空间
     * @return void
     * @throws \RuntimeException
     */
    public static function useNamespace(string $namespace): void
    {
        try {
            // 检查容器是否已经设置
            Container::checkContainer($namespace);
            self::$namespace = $namespace;
            self::setContainer();
        }catch (\RuntimeException $e){
            ErrorHandler::die($e->getMessage());
        }
    }

}

