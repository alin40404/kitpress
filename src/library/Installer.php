<?php
namespace kitpress\library;
use kitpress\core\abstracts\Singleton;
use kitpress\core\Bootstrap;
use kitpress\core\exceptions\BootstrapException;
use kitpress\core\Facades\Config;
use kitpress\utils\Cron;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Helper;
use kitpress\utils\Lang;
use kitpress\utils\Log;


if (!defined('ABSPATH')) {
    exit;
}

class Installer extends Singleton {
    /**
     * 插件根目录映射
     * @var array
     */
    protected $rootPaths = [];  // 新增静态属性存储多个插件路径

    /**
     * 插件根目录
     * @var null
     */
    protected $rootPath;

    public function setRootPath($rootPath){

        $pluginId = Helper::key($rootPath);

        $this->rootPaths[$pluginId] = $rootPath;
    }


    protected function getPluginFile($rootPath): string
    {
        $this->setRootPath($rootPath);
        $pluginId = Helper::key($rootPath);

        if (!isset($this->rootPaths[$pluginId])) {
            ErrorHandler::die(Lang::kit('未找到插件路径：' . $pluginId));
        }

        $rootPath = $this->rootPaths[$pluginId];
        $file_name = $rootPath . basename($rootPath) . '.php';

        // 如果没找到，抛出异常
        if( !file_exists($file_name) ){
            ErrorHandler::die(Lang::kit('框架路径错误：无法在 ' . $file_name . ' 目录下找到有效的插件主文件'));
        }
        return $file_name;
    }

    /**
     * 加载配置文件
     * @return void
     */
    protected static function init($rootPath)
    {
        try {
            Bootstrap::initialize();
            self::getInstance()->setRootPath($rootPath);
            Config::setRootPath($rootPath);
            Config::load('database');

        } catch (BootstrapException $e) {
            ErrorHandler::die($e->getMessage());
        }
    }

    /**
     * 注册插件的激活和停用钩子
     * @return void
     */
    public function register($rootPath) {

        \register_activation_hook(
            $this->getPluginFile($rootPath) ,
            function() use ($rootPath) {
                self::activate($rootPath);
            }
        );

        \register_deactivation_hook(
            $this->getPluginFile($rootPath),
            function() use ($rootPath) {
                self::deactivate($rootPath);
            }
        );
    }

    /**
     * 激活插件时执行
     */
    public static function activate($rootPath) {
        try {
            Log::debug('Installer::activate 执行开始');

            self::init($rootPath);

            $db_version = str_replace('.','' ,Config::get('app.db_version'));
            $kp_version = str_replace('.','' ,KITPRESS_VERSION);

            // 1. 检查系统要求
            self::checkRequirements();

            // 2. 创建数据表
            self::createTables(Config::get('database.versions.'. $db_version .'.tables', []));
            self::createTables(Config::get('database.versions.kp.tables', []));
            self::createTables(Config::get('database.versions.kp_'. $kp_version .'.tables', []));

            // 3. 插入默认数据
            self::insertDefaultData(Config::get('database.versions.'. $db_version .'.default_data', []));

            // 4. 创建默认选项
            self::createOptions();

            // 5. 创建必要的目录
            self::createDirectories();

            // 6. 设置角色权限
            self::setupRoles();

            // 7. 更新数据库版本号
            \update_option(
                Config::get('app.options.db_version_key'),
                Config::get('app.db_version')
            );

            // 8. 记录激活时间
            \update_option(Config::get('app.options.activated_time_key'), time());

            // 9. 清理缓存
            \wp_cache_flush();

            Log::debug('Installer::activate 执行完成');
        } catch (\Exception $e) {
            \deactivate_plugins(self::getInstance()->rootPath);
            ErrorHandler::die(Lang::kit('插件激活失败：') . $e->getMessage());
        }
    }

    /**
     * 卸载插件
     */
    public static function deactivate($rootPath) {
        self::init($rootPath);

        // 检查是否允许删除数据
        if (!get_option(Config::get('app.options.uninstall_key'), Config::get('app.features.delete_data_on_uninstall'))) {
            return;
        }

        try {
            $db_version = str_replace('.','' ,Config::get('app.db_version'));
            $kp_version = str_replace('.','' ,KITPRESS_VERSION);

            // 1. 删除数据表
            self::dropTables(Config::get('database.versions.'. $db_version .'.tables', []));
            self::dropTables(Config::get('database.versions.kp.tables', []));
            self::dropTables(Config::get('database.versions.kp_'. $kp_version .'.tables', []));

            // 2. 删除选项
            self::deleteOptions();

            // 3. 删除上传的文件
            self::deleteUploadedFiles();

            // 4. 删除用户权限
            self::removeCapabilities();

            // 5. 清理缓存
            \wp_cache_flush();

            // 计划任务
            Cron::deactivate();

        } catch (\Exception $e) {
            ErrorHandler::die(Lang::kit('插件卸载失败：') . $e->getMessage());
        }
    }


    /**
     * 检查系统要求
     */
    public static function checkRequirements() {
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
    public static function checkVersion($rootPath) {
        self::init();

        $current_version = Config::get('app.db_version');
        $installed_version = \get_option(Config::get('app.options.db_version_key'));

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
    public static function upgrade($from_version) {
        $versions = Config::get('database.versions', []);

        foreach ($versions as $version => $schema) {
            if (version_compare($from_version, $version, '<')) {
                // 更新数据表
                if (!empty($schema['tables'])) {
                    foreach ($schema['tables'] as $definition) {
                        self::updateTable($definition);
                    }
                }

                // 插入新版本的默认数据
                if (!empty($schema['default_data'])) {
                    self::insertDefaultData($schema['default_data']);
                }
            }
        }

        \update_option(
            Config::get('app.options.db_version_key'),
            Config::get('app.db_version')
        );
    }

    /**
     * 创建数据表
     */
    public static function createTables($tables) {
        if( empty($tables) ) return;

        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($tables as  $definition) {
            $table_name = $wpdb->prefix . Config::get('app.database.prefix') . $definition['name'];
            $columns = $definition['columns'];

            if( $definition['name'] == 'sessions' && Config::get('app.session.enabled',false) == false ){
                // 只有开启 Session，才安装 sessions 表
                continue;
            }

            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (";
            foreach ($columns as $column => $spec) {
                $sql .= "\n{$column} {$spec},";
            }
            $sql = rtrim($sql, ',') . "\n) {$wpdb->get_charset_collate()}";

            // 如果定义中存在表注释,则添加 COMMENT 子句
            if (isset($definition['comment']) && !empty($definition['comment'])) {
                $sql .= " COMMENT='" . esc_sql($definition['comment']) . "'";
            }

            $sql .= ";";

            \dbDelta($sql);

            if ($wpdb->last_error) {
                throw new \Exception("创建表 {$table_name} 失败: " . $wpdb->last_error);
            }
        }
    }


    /**
     * 更新数据表
     */
    public static function updateTable($definition) {
        if(empty($definition)) return;

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

        \dbDelta($sql);

        if ($wpdb->last_error) {
            Log::error("更新表 {$table_name} 失败: " . $wpdb->last_error);
        }
    }

    private static function getFullTableName($table_name): string
    {
        global $wpdb;
        return  $wpdb->prefix . Config::get('app.database.prefix') . $table_name;
    }

    /**
     * 检查表是否存在
     * @param string $table_name 完整的表名
     * @return bool
     */
    public static function tableExists($table_name,$is_full = false): bool
    {
        global $wpdb;

        if($is_full == false) $table_name = self::getFullTableName($table_name);

        $query = $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($table_name)
        );
        return $wpdb->get_var($query) === $table_name;
    }


    /**
     * 插入默认数据
     */
    private static function insertDefaultData($default_data) {
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
                        self::getColumnFormats($record)
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
    private static function createOptions() {
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
                if (false === \get_option($key)) {
                    \add_option($key, $default_value, '', 'no');
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
    private static function createDirectories() {
        $upload_dir = \wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/' . Config::get('app.database.prefix');

        if (!file_exists($plugin_dir)) {
            \wp_mkdir_p($plugin_dir);

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
    private static function setupRoles() {
        $admin = \get_role('administrator');

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
    private static function getColumnFormats($record): array
    {
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
    private static function dropTables($tables = []) {
        global $wpdb;

        if(empty($tables)){
            $db_version = str_replace('.','' ,Config::get('app.db_version'));
            $tables = Config::get('database.versions.'. $db_version .'.tables', []);
        }

        if(empty($tables)) return;

        foreach ($tables as $table => $definition) {
            $table_name = $wpdb->prefix . Config::get('app.database.prefix') . $definition['name'];
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
    }


    /**
     * 删除选项
     */
    private static function deleteOptions() {

        // 删除选项
        $options = Config::get('app.options');
        if(!isset($options['db_version'])) $options['db_version'] = [];
        if(!isset($options['uninstall'])) $options['uninstall'] = false;
        if(!isset($options['meta']))  $options['meta'] = [];
        if(!isset($options['license'])) $options['license'] = '';
        if(!isset($options['license'])) $options['settings'] = [];

        foreach ($options as $key => $default_value) {
            $key = Helper::optionKey($key);
            \delete_option($key);
        }

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
    private static function deleteUploadedFiles() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/' . Config::get('app.database.prefix');

        if (is_dir($plugin_dir)) {
            self::deleteDirectory($plugin_dir);
        }
    }


    /**
     * 递归删除目录及其内容
     * @param string $dir 要删除的目录路径
     * @return bool 是否成功删除
     */
    private static function deleteDirectory(string $dir): bool
    {
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

            if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * 移除用户权限
     */
    private static function removeCapabilities() {
        $admin = get_role('administrator');

        $capabilities = Config::get('app.capabilities', []);
        foreach ($capabilities as $cap) {
            $admin->remove_cap($cap);
        }
    }
}