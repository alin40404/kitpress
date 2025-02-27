# KitPress - WordPress 插件开发框架

## 概述

**KitPress** 是一个轻量级且灵活的 WordPress 插件开发框架，旨在帮助开发者快速创建、管理和扩展 WordPress 插件。无论您是构建一个小型插件还是复杂的解决方案，KitPress 都能为快速开发和未来可扩展的代码提供坚实的基础。

## 特性

- **模块化结构**：使用可重用的模块和组件构建插件
- **易于扩展**：通过钩子和过滤器轻松添加新功能
- **简化设置**：清晰易懂的代码库，加快开发速度
- **支持定制**：灵活的配置选项满足您的需求
- **内置工具**：提供插件开发必需的工具，如设置页面、自定义文章类型等
- **Composer 支持**：通过 Composer 轻松管理依赖和安装


## 系统要求

- PHP 7.4 或更高版本
- WordPress 5.0 或更高版本
- Composer 2.0 或更高版本（如果使用 Composer 安装）

## 开发指南

### 目录结构
```
your-plugin/             # 你的插件目录
├── api/                 # API 接口定义
├── backend/             # 后端业务逻辑
├── config/              # 配置文件
├── frontend/            # 前端业务逻辑
├── functions/           # 辅助函数
├── languages/           # 多语言文件
├── library/             # 核心库文件
├── routes/              # 路由定义
├── utils/               # 工具类
├── vendor/              # Composer 依赖
├── .gitignore
├── composer.json        # Composer 配置文件
├── composer.lock        # Composer 锁定文件
├── license.txt          # 许可证文件
├── readme.txt           # 说明文档
└── your-plugin.php      # 插件主文件
```

每个目录的主要职责：

- **api/**: 存放 API 接口定义和处理逻辑
- **backend/**: 包含后端管理界面和业务处理逻辑
- **frontend/**: 前端展示和交互逻辑，如模板、样式和脚本
- **config/**: 存放配置文件，如数据库配置、应用配置等
- **functions/**: 通用函数和助手函数
- **languages/**: 多语言翻译文件
- **library/**: 框架核心库文件
- **routes/**: API 路由定义和路由处理器
- **utils/**: 工具类和通用功能
- **vendor/**: Composer 安装的第三方依赖包


### 插件主文件示例 (your-plugin.php)
```php
<?php
/**
 * Plugin Name: Your Plugin Name
 * Plugin URI: https://your-plugin-website.com
 * Description: Your plugin description
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: your-plugin
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 引入 Composer 自动加载器
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// 初始化插件
function your_plugin_init() {
    // 插件初始化代码
}
add_action('plugins_loaded', 'your_plugin_init');
```


## 安装

### 通过 Composer 安装（推荐）

1. 在项目根目录运行：
```bash
composer require alin40404/kitpress
```

2. 在您的插件中引入 Composer 的自动加载器：
```php
require_once __DIR__ . '/vendor/autoload.php';
```

### 手动安装

1. 下载或克隆代码库
2. 将 `kitpress` 文件夹上传到您的 `/wp-content/plugins/` 目录
3. 在 WordPress 管理后台激活插件

## 使用方法

### 使用 Composer 创建新插件

1. 创建新的插件目录并初始化 Composer：
```bash
mkdir my-plugin
cd my-plugin
composer init
```

2. 添加 KitPress 依赖：
```bash
composer require alin40404/kitpress
```

3. 创建主插件文件 `plugin-name.php`：

```php
<?php
/**
 * Plugin Name: 我的插件
 * Description: 插件简短描述
 * Version: 1.0
 * Author: 您的名字
 * License: GPL2
 */

// 引入 Composer 自动加载器
require_once __DIR__ . '/vendor/autoload.php';

// 初始化插件
new My_Plugin_Class();
```

### 使用 KitPress 创建插件（传统方式）

1. 在 `/wp-content/plugins/` 下创建新文件夹
2. 在该文件夹内创建名为 `plugin-name.php` 的文件
3. 通过在插件文件顶部添加以下代码来引入 KitPress：

```php
<?php
/**
 * Plugin Name: 我的插件
 * Description: 插件简短描述
 * Version: 1.0
 * Author: 您的名字
 * License: GPL2
 */

// 引入 KitPress 核心
require_once plugin_dir_path(__FILE__) . 'path/to/kitpress/core.php';

// 初始化插件
new My_Plugin_Class();
```

### 扩展 KitPress
您可以通过创建自己的模块或使用现有模块来扩展 KitPress：

```php
<?php
// 示例：添加自定义文章类型
class My_Custom_Post_Type extends KitPress_Module {
    public function __construct() {
        parent::__construct();
        // 在此注册您的自定义文章类型
    }
}
```

### 使用 Composer 包

KitPress 支持使用任何 Composer 包来扩展功能。例如：

```bash
# 添加一个 Composer 包
composer require vendor/package-name

# 更新所有依赖
composer update
```

### 文档
有关如何使用 KitPress 的详细文档，请访问 https://kitpress-docs.com

### 贡献
如果您想为 KitPress 做出贡献，欢迎 fork 代码库并创建 pull request。请确保您的贡献符合代码标准和最佳实践。

### 行为准则
参与本项目即表示您同意遵守我们的行为准则。

### 许可证
KitPress 使用 GPLv2 或更高版本许可证。

### 支持
如果您需要帮助或有任何问题，欢迎在 GitHub 代码库上提出 issue，或通过官方支持论坛联系社区。
