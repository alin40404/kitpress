<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @method static \kitpress\library\Model table(string $table, bool $isFullName = false)
 * @method static \kitpress\library\Model resetTable()
 *
 * // 查询构建方法
 * @method static \kitpress\library\Model select(string|array $columns)
 * @method static \kitpress\library\Model field(string|array $columns)
 * @method static \kitpress\library\Model where(mixed $conditions, mixed $operatorOrValue = null, mixed $value = null, string $chainOperator = 'AND')
 * @method static \kitpress\library\Model whereIn(string $column, array $values)
 * @method static \kitpress\library\Model join(string $table, string $condition, string $type = 'INNER', bool $isFullName = false)
 * @method static \kitpress\library\Model orderBy(string $column, string $direction = '')
 * @method static \kitpress\library\Model order(string $column, string $direction = '')
 * @method static \kitpress\library\Model orderByField(string $field, array|string $values)
 * @method static \kitpress\library\Model groupBy(string $column)
 * @method static \kitpress\library\Model having(string $condition)
 * @method static \kitpress\library\Model limit(int $limit, int $offset = 0)
 * @method static \kitpress\library\Model page(int $page = 1, int $perPage = 10)
 *
 * // 查询执行方法
 * @method static array|object get()
 * @method static object|null first()
 * @method static array getCol(string $column)
 * @method static array pluck(string $column)
 * @method static int count(string $column = '*')
 * @method static object|null find(int $id)
 * @method static array all()
 *
 * // 数据操作方法
 * @method static int|false insert(array $data, array $format = null)
 * @method static bool|int insertOrUpdate(array $data, array $update_fields = [])
 * @method static false|int update(array $data, array|int $conditions = null, array $format = null, array $where_format = null)
 * @method static false|int delete(array|int $conditions = null, array $format = null)
 *
 * // 调试相关方法
 * @method static \kitpress\library\Model debug(bool $debug = true)
 * @method static string getLastSql()
 * @method static \kitpress\library\Model dumpSql()
 * @method static array ddSql()
 *
 * // 获取器方法
 * @method static string getTable()
 * @method static string getTableName()
 * @method static \wpdb getWpdb()
 */
class DB extends Facade {
    /**
     * 获取组件的注册名称
     * @return string
     */
    protected static function getFacadeAccessor() {
        return 'db';
    }

    /**
     * 开启事务
     * @return bool
     */
    public static function beginTransaction() {
        return static::getFacadeRoot()->getWpdb()->query('START TRANSACTION');
    }

    /**
     * 提交事务
     * @return bool
     */
    public static function commit() {
        return static::getFacadeRoot()->getWpdb()->query('COMMIT');
    }

    /**
     * 回滚事务
     * @return bool
     */
    public static function rollBack() {
        return static::getFacadeRoot()->getWpdb()->query('ROLLBACK');
    }

    /**
     * 执行事务
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    public static function transaction(callable $callback) {
        self::beginTransaction();

        try {
            $result = $callback(static::getFacadeRoot());
            self::commit();
            return $result;
        } catch (\Exception $e) {
            self::rollBack();
            throw $e;
        }
    }

    /**
     * 执行原生SQL查询
     * @param string $sql
     * @param array $bindings
     * @return array|object|null
     */
    public static function raw($sql, array $bindings = []) {
        $wpdb = static::getFacadeRoot()->getWpdb();
        if (!empty($bindings)) {
            $sql = $wpdb->prepare($sql, $bindings);
        }
        return $wpdb->get_results($sql);
    }

    /**
     * 批量插入数据
     * @param string $table
     * @param array $data
     * @param array $updateFields
     * @return bool|int
     */
    public static function insertBatch($table, array $data, array $updateFields = []) {
        return static::table($table)->insertOrUpdate($data, $updateFields);
    }

    /**
     * 获取最后插入的ID
     * @return int
     */
    public static function lastInsertId() {
        return static::getFacadeRoot()->getWpdb()->insert_id;
    }

    /**
     * 获取受影响的行数
     * @return int
     */
    public static function affectedRows() {
        return static::getFacadeRoot()->getWpdb()->rows_affected;
    }

    /**
     * 执行查询并获取第一列
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public static function column($sql, array $bindings = []) {
        $wpdb = static::getFacadeRoot()->getWpdb();
        if (!empty($bindings)) {
            $sql = $wpdb->prepare($sql, $bindings);
        }
        return $wpdb->get_col($sql);
    }

    /**
     * 执行查询并获取单个值
     * @param string $sql
     * @param array $bindings
     * @return mixed
     */
    public static function value($sql, array $bindings = []) {
        $wpdb = static::getFacadeRoot()->getWpdb();
        if (!empty($bindings)) {
            $sql = $wpdb->prepare($sql, $bindings);
        }
        return $wpdb->get_var($sql);
    }

    /**
     * 执行查询并获取单行数据
     * @param string $sql
     * @param array $bindings
     * @return object|null
     */
    public static function row($sql, array $bindings = []) {
        $wpdb = static::getFacadeRoot()->getWpdb();
        if (!empty($bindings)) {
            $sql = $wpdb->prepare($sql, $bindings);
        }
        return $wpdb->get_row($sql);
    }

    /**
     * 检查表是否存在
     * @param string $table
     * @return bool
     */
    public static function tableExists($table) {
        $wpdb = static::getFacadeRoot()->getWpdb();
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . $table
        ));
        return !empty($result);
    }

    /**
     * 获取表前缀
     * @return string
     */
    public static function getPrefix() {
        return static::getFacadeRoot()->getWpdb()->prefix;
    }
}