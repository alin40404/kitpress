<?php
namespace kitpress\core\abstracts;

use kitpress\utils\Str;
use \WP_CLI;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Command {
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
    ];


    /**
     * 初始化项目结构
     *
     * ## OPTIONS
     *
     * <path>
     * : 项目根目录路径
     *
     * [--force]
     * : 强制创建（如果目录已存在则覆盖）
     *
     * ## EXAMPLES
     *     wp kitpress init /path/to/project
     *     wp kitpress init . --force
     */
    public function __invoke($args, $assoc_args)
    {
        $force = isset($assoc_args['force']);

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
     */
    private function createStructure(string $base_path, array $items, string $current_path = '', bool $force = false)
    {
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
                // 如果是字符串且以.php结尾，创建文件
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
     */
    private function createPhpFile(string $file_path, string $filename)
    {
        // 确保目录存在
        \wp_mkdir_p(dirname($file_path));

        // 根据文件名生成不同的内容
        $content = $this->getFileContent($filename);

        file_put_contents($file_path, $content);
    }

    /**
     * 根据文件名获取对应的内容模板
     */
    private function getFileContent(string $filename): string
    {
        $base_content = "<?php\n\nif (!defined('ABSPATH')) {\n    exit;\n}\n\n";

        switch ($filename) {
            case 'app.php':
                return $base_content . "return [\n    'version' => '1.0.0',\n    'name' => 'KitPress',\n];";

            case 'database.php':
                return $base_content . "return [\n    'prefix' => 'wp_',\n    'charset' => 'utf8mb4',\n];";

            case 'menu.php':
                return $base_content . "return [\n    'admin' => [],\n    'frontend' => [],\n];";

            case 'function.php':
                return $base_content . "// Add your helper functions here\n";

            case 'api.php':
                return $base_content . "// Register your API routes here\n";

            case 'backend.php':
                return $base_content . "// Register your backend routes here\n";

            case 'frontend.php':
                return $base_content . "// Register your frontend routes here\n";

            default:
                return $base_content . "// " . basename($filename, '.php') . " content here\n";
        }
    }
    
    abstract public function getRootPath() : string;


}