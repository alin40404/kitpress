<?php
namespace kitpress\core;
use kitpress\library\Config;
use kitpress\utils\Cron;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Helper;
use kitpress\utils\Lang;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

class Installer {

    public static function getPluginsName(){
       return Helper::getMainPluginFile();
    }


    /**
     * 加载配置文件
     * @return void
     */
    private static function loadConfig()
    {
        Config::load('database');
    }

    /**
     * 注册插件的激活和停用钩子
     * @return void
     */
    public static function register() {

        register_activation_hook(
            self::getPluginsName() ,
            [self::class, 'activate']
        );

        register_deactivation_hook(
            self::getPluginsName(),
            [self::class, 'deactivate']
        );
    }

    /**
     * 激活插件时执行
     */
    public static function activate() {
        try {
            Log::debug('Installer::activate 执行开始');

            self::loadConfig();

            $db_version = str_replace('.','' ,Config::get('app.db_version'));
            $kp_version = str_replace('.','' ,KITPRESS_VERSION);

            // 1. 检查系统要求
            self::check_requirements();

            // 2. 创建数据表
            self::create_tables(Config::get('database.versions.'. $db_version .'.tables', []));
            self::create_tables(Config::get('database.versions.kp_'. $kp_version .'.tables', []));

            // 3. 插入默认数据
            self::insert_default_data(Config::get('database.versions.'. $db_version .'.default_data', []));

            // 4. 创建默认选项
            self::create_options();

            // 5. 创建必要的目录
            self::create_directories();

            // 6. 设置角色权限
            self::setup_roles();

            // 7. 更新数据库版本号
            update_option(
                Config::get('app.options.db_version_key'),
                Config::get('app.db_version')
            );

            // 8. 记录激活时间
            update_option(Config::get('app.options.activated_time_key'), time());

            // 9. 清理缓存
            wp_cache_flush();

            Log::debug('Installer::activate 执行完成');
        } catch (\Exception $e) {
            deactivate_plugins(self::getPluginsName());
            ErrorHandler::die(Lang::kit('插件激活失败：') . $e->getMessage());
        }
    }

    /**
     * 卸载插件
     */
    public static function deactivate() {
        self::loadConfig();

        // 检查是否允许删除数据
        if (!get_option(Config::get('app.options.uninstall_key'), Config::get('app.features.delete_data_on_uninstall'))) {
            return;
        }

        try {
            $db_version = str_replace('.','' ,Config::get('app.db_version'));
            $kp_version = str_replace('.','' ,KITPRESS_VERSION);

            // 1. 删除数据表
            self::drop_tables(Config::get('database.versions.'. $db_version .'.tables', []));
            self::drop_tables(Config::get('database.versions.kp_'. $kp_version .'.tables', []));

            // 2. 删除选项
            self::delete_options();

            // 3. 删除上传的文件
            self::delete_uploaded_files();

            // 4. 删除用户权限
            self::remove_capabilities();

            // 5. 清理缓存
            wp_cache_flush();

            // 计划任务
            Cron::deactivate();

        } catch (\Exception $e) {
            ErrorHandler::die(Lang::kit('插件卸载失败：') . $e->getMessage());
        }
    }


    /**
     * 检查系统要求
     */
    private static function check_requirements() {
        // PHP 版本检查
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            throw new \Exception(Lang::kit('需要 PHP 7.4 或更高版本'));
        }

        // WordPress 版本检查
        if (version_compare($GLOBALS['wp_version'], '5.0', '<')) {
            throw new \Exception(Lang::kit('需要 WordPress 5.0 或更高版本'));
        }

        // 检查必要的PHP扩展
        $required_extensions = ['mysqli', 'json', 'curl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new \Exception(sprintf(Lang::kit('需要 PHP %s 扩展'), $ext));
            }
        }
    }

    /**
     * 检查并执行数据库更新
     */
    public static function check_version() {
        self::loadConfig();

        $current_version = Config::get('app.db_version');
        $installed_version = get_option(Config::get('app.options.db_version_key'));

        if (!$installed_version) {
            self::activate();
            return;
        }

        if (version_compare($installed_version, $current_version, '<')) {
            self::upgrade($installed_version);
        }
    }

    /**
     * 执行升级
     */
    private static function upgrade($from_version) {
        $versions = Config::get('database.versions', []);

        foreach ($versions as $version => $schema) {
            if (version_compare($from_version, $version, '<')) {
                // 更新数据表
                if (!empty($schema['tables'])) {
                    foreach ($schema['tables'] as $table => $definition) {
                        self::update_table($table, $definition);
                    }
                }

                // 插入新版本的默认数据
                if (!empty($schema['default_data'])) {
                    self::insert_default_data($schema['default_data']);
                }
            }
        }

        update_option(
            Config::get('app.options.db_version_key'),
            Config::get('app.db_version')
        );
    }

    /**
     * 创建数据表
     */
    private static function create_tables($tables) {

        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($tables as  $definition) {
            $table_name = $wpdb->prefix . Config::get('app.database.prefix') . $definition['name'];
            $columns = $definition['columns'];

            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (";
            foreach ($columns as $column => $spec) {
                $sql .= "\n{$column} {$spec},";
            }
            $sql = rtrim($sql, ',') . "\n) {$wpdb->get_charset_collate()};";

            dbDelta($sql);

            if ($wpdb->last_error) {
                throw new \Exception("创建表 {$table_name} 失败: " . $wpdb->last_error);
            }
        }
    }


    /**
     * 更新数据表
     */
    private static function update_table($table, $definition) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $wpdb->prefix . Config::get('app.database.prefix') . $definition['name'];
        $columns = $definition['columns'];

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (";
        foreach ($columns as $column => $spec) {
            if ($column !== 'PRIMARY KEY') {
                $sql .= "\n{$column} {$spec},";
            }
        }

        if (isset($columns['PRIMARY KEY'])) {
            $sql .= "\nPRIMARY KEY " . $columns['PRIMARY KEY'];
        }

        $sql .= "\n) {$wpdb->get_charset_collate()};";

        dbDelta($sql);

        if ($wpdb->last_error) {
            Log::error("更新表 {$table_name} 失败: " . $wpdb->last_error);
        }
    }

    /**
     * 检查表是否存在
     * @param string $table_name 完整的表名
     * @return bool
     */
    private static function table_exists($table_name) {
        global $wpdb;
        $query = $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($table_name)
        );
        return $wpdb->get_var($query) === $table_name;
    }


    /**
     * 插入默认数据
     */
    private static function insert_default_data($default_data) {
        global $wpdb;

        foreach ($default_data as $table => $records) {
            $table_name = $wpdb->prefix . Config::get('app.database.prefix') . $table;

            // 检查表是否为空
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

            if ($count == 0) {
                foreach ($records as $record) {
                    $wpdb->insert(
                        $table_name,
                        $record,
                        self::get_column_formats($record)
                    );

                    if ($wpdb->last_error) {
                        Log::error("插入数据到 {$table_name} 失败: " . $wpdb->last_error);
                    }
                }
            }
        }
    }

    /**
     * 创建默认选项
     * @throws \Exception 如果选项创建失败
     */
    private static function create_options() {
        try {
            // 获取所有默认配置
            $features = Config::get('app.features');
            $options = Config::get('app.options');

            if(!isset($options['db_version'])) $options['db_version'] = Config::get('app.db_version');
            if(!isset($options['uninstall'])) $options['uninstall'] = $features['delete_data_on_uninstall'] ?? false;
            if(!isset($options['meta'])){
                $options['meta'] = [
                    'version' => Config::get('app.version'),
                    'installed_at' => current_time('timestamp'),
                    'last_updated' => current_time('timestamp')
                ];
            }
            if(!isset($options['license'])) $options['license'] = '';
            $options['settings'] = $features;

            foreach ($options as $key => $default_value) {
                $key = Helper::optionKey($key);
                if (false === get_option($key)) {
                    add_option($key, $default_value, '', 'no');
                }
            }

        } catch (\Exception $e) {
            Log::error('创建选项失败: ' . $e->getMessage());
            throw new \Exception(Lang::kit('创建插件选项失败'));
        }
    }

    /**
     * 创建必要的目录
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/' . Config::get('app.database.prefix');

        if (!file_exists($plugin_dir)) {
            wp_mkdir_p($plugin_dir);

            // 创建 .htaccess 文件保护目录
            $htaccess_file = $plugin_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "deny from all\n");
            }
        }
    }

    /**
     * 设置角色权限
     */
    private static function setup_roles() {
        $admin = get_role('administrator');

        $roleKey = KITPRESS_NAME;

        $capabilities = Config::get('app.capabilities', [
            'create_' . $roleKey,
            'edit_' . $roleKey,
            'delete_' . $roleKey,
            'view_' . $roleKey . '_results',
            'manage_' . $roleKey . '_settings'
        ]);

        foreach ($capabilities as $cap) {
            $admin->add_cap($cap);
        }
    }

    /**
     * 获取列的格式
     */
    private static function get_column_formats($record) {
        $formats = [];
        foreach ($record as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }

    /**
     * 删除数据表
     */
    private static function drop_tables($tables = []) {
        global $wpdb;

        if(empty($tables)){
            $db_version = str_replace('.','' ,Config::get('app.db_version'));
            $tables = Config::get('database.versions.'. $db_version .'.tables', []);
        }

        foreach ($tables as $table => $definition) {
            $table_name = $wpdb->prefix . Config::get('app.database.prefix') . $definition['name'];
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
    }


    /**
     * 删除选项
     */
    private static function delete_options() {
        // 删除版本号
        delete_option(Config::get('app.options.db_version_key'));

        // 删除设置
        delete_option(Config::get('app.options.settings_key'));

        // 删除卸载选项
        delete_option(Config::get('app.options.uninstall_key'));

        // 删除激活时间
        delete_option(Config::get('app.options.activated_time_key'));

        // 删除所有以插件前缀开头的选项
        global $wpdb;
        $prefix = Config::get('app.database.prefix');
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix . '%'
            )
        );
    }

    /**
     * 删除上传的文件
     */
    private static function delete_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/' . Config::get('app.database.prefix');

        if (is_dir($plugin_dir)) {
            self::delete_directory($plugin_dir);
        }
    }


    /**
     * 递归删除目录及其内容
     * @param string $dir 要删除的目录路径
     * @return bool 是否成功删除
     */
    private static function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!self::delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * 移除用户权限
     */
    private static function remove_capabilities() {
        $admin = get_role('administrator');

        $capabilities = Config::get('app.capabilities', []);
        foreach ($capabilities as $cap) {
            $admin->remove_cap($cap);
        }
    }
}