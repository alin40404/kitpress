<?php
namespace kitpress\utils;
if (!defined('ABSPATH')) {
    exit;
}

class Loader {
    /**
     * 注册加载器
     */
    public static function register() {
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * 自动加载类
     *
     * @param string $class 完整的类名（包含命名空间）
     * @return void
     */
    public static function autoload($class) {

        // 处理两个不同的命名空间
        if (stripos($class, KITPRESS_CORE_NAMESPACE . '\\') === 0) {
            // kitpress 命名空间的类
            $relative_class = substr($class, strlen(KITPRESS_CORE_NAMESPACE . '\\'));
            $file = self::getFilePath($relative_class, 'kitpress');
        } elseif (stripos($class, Config::get('app.namespace') . '\\') === 0) {
            // 外部命名空间
            // kitpress_plugin 命名空间的类
            $relative_class = substr($class, strlen(Config::get('app.namespace') . '\\'));
            $file = self::getFilePath($relative_class, 'plugin');
        } else {
            return;
        }

        // 如果文件存在则加载
        if (file_exists($file)) {
            require_once $file;
        } else {
            Log::error("File not found: " . $file);
            return;
        }

        // 添加调试信息
        if( Config::get('app.features.debug_mode') )  Log::error("正在尝试加载类: " . $class);
    }


    /**
     * 获取类文件的路径
     *
     * @param string $class 相对类名
     * @param string $type 命名空间类型 ('kitpress' 或 'plugin')
     * @return string 文件完整路径
     */
    private static function getFilePath($class, $type) {
        // 将命名空间分隔符转换为目录分隔符
        $path_parts = explode('\\', $class);

        // 获取文件名（最后一个部分）
        $file_name = array_pop($path_parts);

        // 处理目录路径
        $directory = '';
        foreach ($path_parts as $part) {
            // 将下划线转换为连字符，并转换为小写
            $part = str_replace('_', '-', strtolower($part));
            $directory .= $part . DIRECTORY_SEPARATOR;
        }

        // 根据类型确定基础路径
        $base_path = KITPRESS_PLUGIN_PATH;
        if ($type === 'kitpress') {
            $base_path = KITPRESS_PATH;
        }

        // 组合完整路径
        return $base_path . $directory . $file_name . '.php';
    }
}