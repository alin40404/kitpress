<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件安装器门面
 *
 * @see \kitpress\library\Installer
 *
 * @method static void register() 注册插件的激活和停用钩子
 * @method static void activate() 激活插件时执行
 * @method static void deactivate() 卸载插件
 * @method static void checkRequirements() 检查系统要求
 * @method static void checkVersion(string $rootPath) 检查并执行数据库更新
 * @method static void upgrade(string $fromVersion) 执行升级
 * @method static void createTables(array $tables) 创建数据表
 * @method static void updateTable(array $definition) 更新数据表
 * @method static bool tableExists(string $tableName, bool $isFull = false) 检查表是否存在
 * @method static void createOptions() 创建默认选项
 * @method static void createDirectories() 创建必要的目录
 * @method static void setupRoles() 设置角色权限
 * @method static void dropTables(array $tables = []) 删除数据表
 * @method static void deleteOptions() 删除选项
 * @method static void deleteUploadedFiles() 删除上传的文件
 * @method static bool deleteDirectory(string $dir) 递归删除目录及其内容
 * @method static void removeCapabilities() 移除用户权限
 */
class Installer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'installer';
    }
}