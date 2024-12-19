# KitPress - WordPress 插件开发框架

## 概述

**KitPress** 是一个轻量级且灵活的 WordPress 插件开发框架，旨在帮助开发者快速创建、管理和扩展 WordPress 插件。无论您是构建一个小型插件还是复杂的解决方案，KitPress 都能为快速开发和未来可扩展的代码提供坚实的基础。

## 特性

- **模块化结构**：使用可重用的模块和组件构建插件
- **易于扩展**：通过钩子和过滤器轻松添加新功能
- **简化设置**：清晰易懂的代码库，加快开发速度
- **支持定制**：灵活的配置选项满足您的需求
- **内置工具**：提供插件开发必需的工具，如设置页面、自定义文章类型等

## 安装

1. 下载或克隆代码库
2. 将 `kitpress` 文件夹上传到您的 `/wp-content/plugins/` 目录
3. 在 WordPress 管理后台激活插件

## 使用方法

### 使用 KitPress 创建插件

按照以下步骤创建新插件：

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

4. 使用提供的类和方法添加功能、设置等来自定义您的插件

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