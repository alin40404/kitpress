# 插件生命周期管理

## 目录
1. [概述](#概述)
2. [生命周期流程](#生命周期流程)
3. [实现细节](#实现细节)
4. [最佳实践](#最佳实践)
5. [示例代码](#示例代码)

## 概述

KitPress 框架的插件生命周期管理主要包含四个阶段：
1. 插件加载（Loading）
2. 插件激活（Activation）
3. 插件运行（Running）
4. 插件停用（Deactivation）

## 生命周期流程

### 1. 插件加载流程

```php
// your-plugin.php
require_once __DIR__ . '/vendor/autoload.php';

$kitpress = Kitpress::boot(__DIR__);
$kitpress->loaded();
```

加载过程详解：

1. **框架初始化**
```php
public static function boot(string $rootPath): Kitpress
{
    // 1. 定义框架常量
    self::constants();
    
    // 2. 设置插件根路径
    self::setRootPath($rootPath);
    
    // 3. 加载辅助函数
    self::includes($rootPath);
    
    // 4. 初始化容器
    $instance = self::getInstance();
    $instance->container = Container::getInstance(self::$namespace);
    
    return $instance;
}
```

2. **插件加载**
```php
public function loaded(): void
{
    $currentNamespace = self::$namespace;
    
    \add_action('plugins_loaded', function () use ($currentNamespace) {
        // 切换到当前插件的命名空间
        self::useNamespace($currentNamespace);
        // 运行插件
        $this->run();
    }, 20);
}
```

### 2. 插件激活流程

```php
// your-plugin.php
register_activation_hook(__FILE__, function() {
    Kitpress::boot(__DIR__)->activate();
});
```

激活过程详解：

```php
public function activate(): void
{
    try {
        // 1. 引导框架启动
        Bootstrap::boot($this->container)->start();
        
        // 2. 执行安装程序
        $this->container->get('installer')->activate();
        
    } catch (BootstrapException $e) {
        ErrorHandler::die($e->getMessage());
    }
}
```

### 3. 插件运行流程

```php
public function run(): void
{
    try {
        // 1. 框架引导启动
        $bootstrap = Bootstrap::boot($this->container);
        
        // 2. 初始化
        $bootstrap->start();
        
        // 3. 运行
        $bootstrap->run();
        
        // 4. 关闭
        $bootstrap->shutdown();
        
    } catch (BootstrapException $e) {
        ErrorHandler::die($e->getMessage());
    }
}
```

### 4. 插件停用流程

```php
// your-plugin.php
register_deactivation_hook(__FILE__, function() {
    Kitpress::boot(__DIR__)->deactivate();
});
```

停用过程详解：

```php
public function deactivate(): void
{
    try {
        // 1. 引导框架启动
        Bootstrap::boot($this->container)->start();
        
        // 2. 执行停用程序
        $this->container->get('installer')->deactivate();
        
    } catch (BootstrapException $e) {
        ErrorHandler::die($e->getMessage());
    }
}
```

## 实现细节

### 命名空间管理

框架支持多插件共存，通过命名空间隔离不同插件：

```php
public static function useNamespace(string $namespace): void
{
    try {
        // 检查容器是否已设置
        Container::checkContainer($namespace);
        // 切换命名空间
        self::$namespace = $namespace;
        // 重置容器
        self::setContainer();
    } catch (\RuntimeException $e) {
        ErrorHandler::die($e->getMessage());
    }
}
```

### 错误处理

框架使用统一的错误处理机制：

```php
class ErrorHandler
{
    public static function die(string $message): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::error($message);
        } else {
            wp_die($message);
        }
    }
}
```

## 最佳实践

1. **插件入口文件配置**
```php
// your-plugin.php
<?php
/**
 * Plugin Name: Your Plugin
 * Version: 1.0.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// 初始化框架
$kitpress = Kitpress::boot(__DIR__);

// 注册激活钩子
register_activation_hook(__FILE__, function() use ($kitpress) {
    $kitpress->activate();
});

// 注册停用钩子
register_deactivation_hook(__FILE__, function() use ($kitpress) {
    $kitpress->deactivate();
});

// 加载插件
$kitpress->loaded();
```

2. **自定义安装程序**
```php
class YourPluginInstaller implements InstallerInterface
{
    public function activate(): void
    {
        // 1. 创建数据表
        $this->createTables();
        
        // 2. 初始化选项
        $this->initOptions();
        
        // 3. 添加默认数据
        $this->seedData();
    }
    
    public function deactivate(): void
    {
        // 1. 保存状态
        $this->saveState();
        
        // 2. 清理临时数据
        $this->cleanup();
    }
}
```

## 示例代码

### 1. 完整的插件结构

```
your-plugin/
├── src/
│   ├── Installer.php          # 安装程序
│   ├── Bootstrap.php          # 引导程序
│   └── Plugin.php            # 插件主类
├── config/
│   └── app.php               # 配置文件
├── database/
│   └── migrations/           # 数据库迁移
├── your-plugin.php          # 入口文件
└── composer.json
```

### 2. 自定义引导程序

```php
class Bootstrap extends \kitpress\core\Bootstrap
{
    public function start(): void
    {
        parent::start();
        // 添加自定义初始化逻辑
    }
    
    public function run(): void
    {
        // 添加自定义运行逻辑
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'adminInit']);
    }
}
```

### 3. 错误处理示例

```php
try {
    // 执行可能出错的操作
    $this->riskyOperation();
} catch (PluginException $e) {
    // 记录错误
    error_log($e->getMessage());
    // 显示错误
    ErrorHandler::die($e->getMessage());
}
```