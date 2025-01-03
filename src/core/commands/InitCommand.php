<?php
namespace kitpress\core\commands;

use kitpress\core\abstracts\Command;
use kitpress\utils\Str;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 初始化命令类
 *
 * 用于创建插件的基础目录结构，包括控制器、视图、路由等必要的目录和文件。
 *
 * @package kitpress\core\commands
 * @author Allan
 * @since 1.0.0
 */
abstract class InitCommand extends Command
{
    /**
     * 项目基础目录结构
     */
    protected array $directories = [
        'api' => [
            'controllers',
        ],
        'frontend' => [
            'assets',
            'controllers',
            'views',
        ],
        'backend' => [
            'assets',
            'controllers',
            'views',
        ],
        'config' => [
            'app.php',
            'database.php',
            'menu.php',
            'cron.php',
        ],
        'functions' => [
            'function.php'
        ],
        'languages',
        'library',
        'routes' => [
            'api.php',
            'backend.php',
            'frontend.php',
        ],
        'utils',
        'commands',
    ];

    /**
     * 执行初始化命令
     *
     * ## OPTIONS
     *
     * [--force]
     * : 强制创建（如果目录已存在则覆盖）
     *
     * ## EXAMPLES
     *     wp kitpress init
     *     wp kitpress init --force
     *
     * @param array $args 位置参数
     * @param array $assoc_args 关联参数
     * @return void
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $force = \WP_CLI\Utils\get_flag_value($assoc_args, 'force', false);
        $root_path = $this->getRootPath();

        if (!is_dir($root_path)) {
            \WP_CLI::error("文件路径不存在: {$root_path}");
            return;
        }

        \WP_CLI::line("正在创建插件目录结构...");
        $this->createStructure($root_path, $this->directories,'',  $force);

        \WP_CLI::success("插件目录结构创建成功!");
    }

    /**
     * 递归创建目录和文件
     *
     * @param string $base_path 基础路径
     * @param array $items 目录和文件列表
     * @param string $current_path 当前相对路径
     * @param bool $force 是否强制创建
     * @return void
     */
    private function createStructure(string $base_path, array $items, string $current_path = '', bool $force = false)
    {
        if (!is_writable($base_path)) {
            \WP_CLI::error("目录无写入权限: {$base_path}");
            return;
        }

        foreach ($items as $name => $item) {
            $path = $base_path . $current_path;

            // 如果是数组，可能是目录包含子项或文件列表
            if (is_array($item)) {
                // 创建目录
                $dir_path = $path . $name;
                if (!is_dir($dir_path)) {
                    mkdir($dir_path, 0755, true);
                    \WP_CLI::line("创建文件夹: " . str_replace($base_path, '', $dir_path));
                }

                // 递归处理子项
                $this->createStructure($base_path, $item, $current_path . $name . '/');
            } else {

                if (is_string($item) && Str::endsWith($item, '.php')) {
                    $file_path = $path . $item;
                    if (!file_exists($file_path) || $force) {
                        $this->createPhpFile($file_path, $item);
                        \WP_CLI::line("创建文件: " . str_replace($base_path, '', $file_path));
                    }
                } else {
                    // 创建普通目录
                    $dir_path = $path . $item;
                    if (!is_dir($dir_path)) {
                        mkdir($dir_path, 0755, true);
                        \WP_CLI::line("创建文件夹: " . str_replace($base_path, '', $dir_path));
                    }
                }
            }
        }
    }

    /**
     * 创建 PHP 文件并添加基础内容
     *
     * @param string $file_path 文件路径
     * @param string $filename 文件名
     * @return void
     */
    private function createPhpFile(string $file_path, string $filename): void
    {
        try {
            // 确保目录存在
            \wp_mkdir_p(dirname($file_path));

            // 根据文件名生成不同的内容
            $content = $this->getFileContent($filename);

            file_put_contents($file_path, $content);
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    /**
     * 获取文件内容模板
     *
     * @param string $filename 文件名
     * @return string 文件内容
     */
    private function getFileContent(string $filename): string
    {
        $stub_path = dirname(__DIR__) . '/templates/stubs/';
        $base_content = "<?php\n\nif (!defined('ABSPATH')) {\n    exit;\n}\n\n";

        // 获取不带扩展名的文件名
        $name = basename($filename, '.php');

        // 检查是否存在对应的模板文件
        if (file_exists($stub_path . $name . '.stub')) {
            return file_get_contents($stub_path . $name . '.stub');
        }

        // 如果没有找到模板文件，返回默认内容
        return $base_content . "// " . $name . " content here\n";
    }
}
