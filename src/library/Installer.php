<?php
namespace kitpress\library;
use kitpress\Kitpress;
use kitpress\utils\Cron;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Helper;
use kitpress\utils\Lang;


if (!defined('ABSPATH')) {
    exit;
}

class Installer {

    private ?Config $config = null;
    private ?Plugin $plugin = null;
    private ?Log $log = null;

    public function __construct(Log $log = null) {
        $this->plugin = $log->plugin;
        $this->config = $log->config;
        $this->log = $log;
    }

    /**
     * 激活插件时执行
     */
    public function activate() {
        try {

            $this->log->debug('Installer::activate 执行开始');

            $db_version = str_replace('.','' ,$this->config->get('app.db_version'));
            $kp_version = str_replace('.','' ,KITPRESS_VERSION);

            // 1. 检查系统要求
            $this->checkRequirements();

            // 2. 创建数据表
            $this->createTables($this->config->get('database.versions.'. $db_version .'.tables', []));
            $this->createTables($this->config->get('database.versions.kp.tables', []));
            $this->createTables($this->config->get('database.versions.kp_'. $kp_version .'.tables', []));

            // 3. 插入默认数据
            $this->insertDefaultData($this->config->get('database.versions.'. $db_version .'.default_data', []));

            // 4. 创建默认选项
            $this->createOptions();

            // 5. 创建必要的目录
            $this->createDirectories();

            // 6. 设置角色权限
            $this->setupRoles();

            // 7. 更新数据库版本号
            \update_option(
                $this->config->get('app.options.db_version_key'),
                $this->config->get('app.db_version')
            );

            // 8. 记录激活时间
            \update_option($this->config->get('app.options.activated_time_key'), time());

            // 9. 清理缓存
            \wp_cache_flush();

            // 初始化计划任务
             Cron::init();

            $this->log->debug('Installer::activate 执行完成');
        } catch (\Exception $e) {
            // 确保在管理后台环境中才调用 deactivate_plugins
            if (\is_admin()) {
                \deactivate_plugins($this->plugin->getRootFile());
            }
            ErrorHandler::die(Lang::kit('插件激活失败：') . $e->getMessage());
        }
    }

    /**
     * 卸载插件
     */
    public function deactivate() {

        // 检查是否允许删除数据
        if (!get_option($this->config->get('app.options.uninstall_key'), $this->config->get('app.features.delete_data_on_uninstall'))) {
            return;
        }

        try {
            $db_version = str_replace('.','' ,$this->config->get('app.db_version'));
            $kp_version = str_replace('.','' ,KITPRESS_VERSION);

            // 1. 删除数据表
            $this->dropTables($this->config->get('database.versions.'. $db_version .'.tables', []));
            $this->dropTables($this->config->get('database.versions.kp.tables', []));
            $this->dropTables($this->config->get('database.versions.kp_'. $kp_version .'.tables', []));

            // 2. 删除选项
            $this->deleteOptions();

            // 3. 删除上传的文件
            $this->deleteUploadedFiles();

            // 4. 删除用户权限
            $this->removeCapabilities();

            // 5. 清理缓存
            \wp_cache_flush();

            // 切换容器，保证执行在同一个容器
            Kitpress::useNamespace($this->log->plugin->getNamespace());
            // 计划任务
            Cron::deactivate();

        } catch (\Exception $e) {
            ErrorHandler::die(Lang::kit('插件卸载失败：') . $e->getMessage());
        }
    }


    /**
     * 检查系统要求
     */
    public function checkRequirements() {
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
    public function checkVersion($rootPath) {

        $current_version = $this->config->get('app.db_version');
        $installed_version = \get_option($this->config->get('app.options.db_version_key'));

        if (!$installed_version) {
            $this->activate();
            return;
        }

        if (version_compare($installed_version, $current_version, '<')) {
            $this->upgrade($installed_version);
        }
    }

    /**
     * 执行升级
     */
    public function upgrade($from_version) {
        $versions = $this->config->get('database.versions', []);

        foreach ($versions as $version => $schema) {
            if (version_compare($from_version, $version, '<')) {
                // 更新数据表
                if (!empty($schema['tables'])) {
                    foreach ($schema['tables'] as $definition) {
                        $this->updateTable($definition);
                    }
                }

                // 插入新版本的默认数据
                if (!empty($schema['default_data'])) {
                    $this->insertDefaultData($schema['default_data']);
                }
            }
        }

        \update_option(
            $this->config->get('app.options.db_version_key'),
            $this->config->get('app.db_version')
        );
    }

    /**
     * 创建数据表
     */
    public function createTables($tables) {
        if( empty($tables) ) return;

        global $wpdb;
        require(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($tables as  $definition) {
            $table_name = $wpdb->prefix . $this->config->get('app.database.prefix') . $definition['name'];
            $columns = $definition['columns'];

            if( $definition['name'] == 'sessions' && $this->config->get('app.session.enabled',false) == false ){
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
    public function updateTable($definition) {
        if(empty($definition)) return;

        global $wpdb;
        require(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_name = $wpdb->prefix . $this->config->get('app.database.prefix') . $definition['name'];
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
            $this->log->error("更新表 {$table_name} 失败: " . $wpdb->last_error);
        }
    }

    /**
     * 检查表是否存在
     * @param string $table_name 完整的表名
     * @return bool
     */
    public function tableExists($table_name,$is_full = false): bool
    {
        global $wpdb;

        if($is_full == false) $table_name = $this->getFullTableName($table_name);

        $query = $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($table_name)
        );
        return $wpdb->get_var($query) === $table_name;
    }

    private function getFullTableName($table_name): string
    {
        global $wpdb;
        return  $wpdb->prefix . $this->config->get('app.database.prefix') . $table_name;
    }

    /**
     * 插入默认数据
     */
    private function insertDefaultData($default_data) {
        global $wpdb;

        foreach ($default_data as $table => $records) {
            $table_name = $wpdb->prefix . $this->config->get('app.database.prefix') . $table;

            // 检查表是否为空
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

            if ($count == 0) {
                foreach ($records as $record) {
                    $wpdb->insert(
                        $table_name,
                        $record,
                        $this->getColumnFormats($record)
                    );

                    if ($wpdb->last_error) {
                        $this->log->error("插入数据到 {$table_name} 失败: " . $wpdb->last_error);
                    }
                }
            }
        }
    }

    /**
     * 创建默认选项
     * @throws \Exception 如果选项创建失败
     */
    private function createOptions() {
        try {
            // 获取所有默认配置
            $features = $this->config->get('app.features');
            $options = $this->config->get('app.options');

            if(!isset($options['db_version'])) $options['db_version'] = $this->config->get('app.db_version');
            if(!isset($options['uninstall'])) $options['uninstall'] = $features['delete_data_on_uninstall'] ?? false;
            if(!isset($options['meta'])){
                $options['meta'] = [
                    'version' => $this->config->get('app.version'),
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
            $this->log->error('创建选项失败: ' . $e->getMessage());
            throw new \Exception(Lang::kit('创建插件选项失败'));
        }
    }

    /**
     * 创建必要的目录
     */
    private function createDirectories() {
        $upload_dir = \wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/' . $this->config->get('app.database.prefix');

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
    private function setupRoles() {
        $admin = \get_role('administrator');

        $roleKey = KITPRESS_NAME;

        $capabilities = $this->config->get('app.capabilities', [
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
    private function getColumnFormats($record): array
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
    private function dropTables($tables = []) {
        global $wpdb;

        if(empty($tables)){
            $db_version = str_replace('.','' ,$this->config->get('app.db_version'));
            $tables = $this->config->get('database.versions.'. $db_version .'.tables', []);
        }

        if(empty($tables)) return;

        foreach ($tables as $table => $definition) {
            $table_name = $wpdb->prefix . $this->config->get('app.database.prefix') . $definition['name'];
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }
    }


    /**
     * 删除选项
     */
    private function deleteOptions() {

        // 删除选项
        $options = $this->config->get('app.options');
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
        $prefix = $this->config->get('app.database.prefix');
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
    private function deleteUploadedFiles() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = $upload_dir['basedir'] . '/' . $this->config->get('app.database.prefix');

        if (is_dir($plugin_dir)) {
            $this->deleteDirectory($plugin_dir);
        }
    }


    /**
     * 递归删除目录及其内容
     * @param string $dir 要删除的目录路径
     * @return bool 是否成功删除
     */
    private function deleteDirectory(string $dir): bool
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

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * 移除用户权限
     */
    private function removeCapabilities() {
        $admin = get_role('administrator');

        $capabilities = $this->config->get('app.capabilities', []);
        foreach ($capabilities as $cap) {
            $admin->remove_cap($cap);
        }
    }
}