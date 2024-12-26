<?php
namespace kitpress\library;
use kitpress\core\abstracts\Singleton;
use kitpress\core\Facades\Config;
use kitpress\utils\Log;

if (!defined('ABSPATH')) {
    exit;
}

class Model extends Singleton {
    protected $wpdb;
    protected $table;
    protected $prefix;
    protected $plugin_prefix;
    protected $table_name;
    protected $original_table;
    protected $select = '*';
    protected $joins = [];
    protected $orderBy;
    protected $groupBy;
    protected $having;
    protected $limit;
    protected $where;
    protected $values;
    protected $debug = false;
    protected $lastSql = '';


    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
        $this->plugin_prefix = Config::get('app.database.prefix');

        // 设置表名
        $this->setTableName();
        
        parent::__construct();
    }

    public function getWpdb()
    {
        return $this->wpdb;
    }

    /**
     * 设置表名
     */
    protected function setTableName() {
        // 如果已经设置了完整的表名，则直接返回
        if (!empty($this->table)) {
            return;
        }

        // 如果显式指定了表名（不包含前缀），则使用指定的表名
        if (!empty($this->table_name)) {
            $this->table = $this->prefix . $this->plugin_prefix . $this->table_name;
            return;
        }

        // 根据类名自动推断表名
        $className = (new \ReflectionClass($this))->getShortName();
        // 移除末尾的Model
        $className = preg_replace('/Model$/', '', $className);
        
        // 将驼峰命名转换为下划线命名
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        
        $this->table = $this->prefix . $this->plugin_prefix . $tableName;
    }

    public function getTable() {
        return $this->table;
    }

    public function getTableName() {
        // 如果显式设置了 table_name，直接返回
        if (!empty($this->table_name)) {
            return $this->table_name;
        }

        // 如果设置了完整表名，移除两个前缀
        if (!empty($this->table)) {
            $table = $this->table;
            // 移除 WordPress 前缀
            if (strpos($table, $this->prefix) === 0) {
                $table = substr($table, strlen($this->prefix));
            }
            // 移除插件前缀
            if (strpos($table, $this->plugin_prefix) === 0) {
                $table = substr($table, strlen($this->plugin_prefix));
            }
            return $table;
        }

        return '';
    }

    public function find($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ));
    }

    public function all() {
        return $this->wpdb->get_results("SELECT * FROM {$this->table}");
    }

    /**
     * 插入或更新数据
     * @param array $data 要插入/更新的数据
     * @param array $update_fields 需要更新的字段
     * @return bool|int
     */
    public function insertOrUpdate(array $data, array $update_fields = []) {
        global $wpdb;

        if (empty($data)) {
            return false;
        }
        $table = $this->getTable();
        $fields = array_keys($data);
        $formats = $this->getFieldFormats($data);
        $values = array_values($data);

        // 构建 INSERT 部分
        $sql = "INSERT INTO {$table} (`" . implode('`, `', $fields) . "`) VALUES (";
        $sql .= implode(', ', $formats) . ")";

        // 构建 UPDATE 部分
        if (!empty($update_fields)) {
            $sql .= " ON DUPLICATE KEY UPDATE ";
            $updates = [];
            foreach ($update_fields as $field) {
                $updates[] = "`{$field}` = VALUES(`{$field}`)";
            }
            $sql .= implode(', ', $updates);
        }

        return $wpdb->query($wpdb->prepare($sql, $values));
    }

    /**
     * 获取字段格式
     * @param array $data
     * @return array
     */
    private function getFieldFormats(array $data) {
        $formats = [];
        foreach ($data as $value) {
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
     * 插入数据
     * @param array $data 要插入的数据
     * @param array $format 数据格式
     * @return int|false 返回插入的ID或false
     */
    public function insert($data, $format = null) {
        $result = $this->wpdb->insert(
            $this->table,
            $data,
            $format
        );

        return $result !== false ? $this->wpdb->insert_id : false;
    }



    /**
     * 更新数据
     * @param array $data 要更新的数据
     * @param array|int $conditions 更新条件
     * @param array $format 数据格式
     * @param array $where_format 条件格式
     * @return false|int
     */
    public function update($data, $conditions = null, $format = null, $where_format = null) {
        if (is_numeric($conditions)) {
            // 如果是单个ID，使用简单更新
            return $this->wpdb->update(
                $this->table,
                $data,
                ['id' => $conditions],
                $format,
                $where_format ?? ['%d']
            );
        }

        if (is_array($conditions)) {
            // 如果是条件数组，直接使用 wpdb->update
            return $this->wpdb->update(
                $this->table,
                $data,
                $conditions,
                $format,
                $where_format
            );
        }

        // 使用 where 条件的情况
        if (!empty($this->where)) {
            $set_parts = [];
            $values = [];

            foreach ($data as $key => $value) {
                $set_parts[] = "$key = " . ($format[$key] ?? '%s');
                $values[] = $value;
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $set_parts);
            $sql .= " WHERE " . $this->where;

            // 合并 SET 子句的值和 WHERE 子句的值
            $values = array_merge($values, $this->values);

            $result = $this->wpdb->query(
                $this->wpdb->prepare($sql, $values)
            );

            $this->where = null;
            $this->values = [];
            return $result;
        }

        return false;
    }

    /**
     * 删除数据
     * @param array|int $conditions 删除条件
     * @param array $format 条件格式
     * @return false|int
     */
    public function delete($conditions = null, $format = null) {
        if (is_numeric($conditions)) {
            // 如果是单个ID，使用简单删除
            return $this->wpdb->delete(
                $this->table,
                ['id' => $conditions],
                ['%d']
            );
        }

        if (is_array($conditions)) {
            // 如果是条件数组，直接使用 wpdb->delete
            return $this->wpdb->delete(
                $this->table,
                $conditions,
                $format
            );
        }

        // 使用 where 条件的情况
        if (!empty($this->where)) {
            $sql = "DELETE FROM {$this->table} WHERE " . $this->where;
            $result = $this->wpdb->query(
                $this->wpdb->prepare($sql, $this->values)
            );
            $this->where = null;
            $this->values = [];
            return $result;
        }

        return false;
    }


    /**
     * 设置查询字段
     * @param string|array $columns 查询字段
     * @return $this
     */
    public function field($columns) {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    /**
     * 添加JOIN查询
     * @param string $table 要连接的表名
     * @param string $condition 连接条件
     * @param string $type 连接类型 (INNER, LEFT, RIGHT)
     * @param bool $isFullName 是否是完整表名（true则不添加前缀）
     * @return $this
     */
    public function join($table, $condition, $type = 'INNER', $isFullName = false) {
        // 如果不是完整表名，且不是 WordPress 核心表，则添加插件前缀
        if (!$isFullName && !strpos($table, $this->prefix)) {
            $table = $this->prefix . $this->plugin_prefix . $table;
        }

        $this->joins[] = strtoupper($type) . " JOIN {$table} ON {$condition}";
        return $this;
    }

    /**
     * 添加分组
     * @param string $column 分组字段
     * @return $this
     */
    public function groupBy($column) {
        $this->groupBy = $column;
        return $this;
    }

    /**
     * 添加HAVING条件
     * @param string $condition HAVING条件
     * @return $this
     */
    public function having($condition) {
        $this->having = $condition;
        return $this;
    }

    /**
     * WHERE条件查询
     * 支持以下调用方式:
     * 1. where(['column' => 'value'])                     // 数组形式，默认使用 AND
     * 2. where(['column', 'operator', 'value'])          // 数组形式，指定运算符
     * 3. where('column', 'value')                        // 键值对形式
     * 4. where('column', 'operator', 'value')           // 指定运算符形式
     * 5. where(['column' => 'value'], 'OR')             // 数组形式，指定连接符
     * 
     * @param array|string $conditions 查询条件
     * @param string|mixed $operatorOrValue 运算符或值
     * @param mixed $value 值(可选)
     * @param string $chainOperator 条件连接符 (AND/OR)
     * @return $this
     */
    public function where($conditions, $operatorOrValue = null, $value = null, $chainOperator = 'AND') {
        // 如果是第一次调用where，重置条件
        if (empty($this->where)) {
            $this->where = '';
            $this->values = [];
        }

        // 处理数组形式的调用
        if (is_array($conditions)) {
            // 处理 where(['column', 'operator', 'value']) 形式
            if (isset($conditions[0]) && isset($conditions[1])) {
                $column = $conditions[0];
                $operator = $conditions[1];
                $value = $conditions[2] ?? null;
                $this->addWhereCondition($column, $operator, $value, $chainOperator);
            } 
            // 处理 where(['column' => 'value']) 形式
            else {
                foreach ($conditions as $key => $condition) {
                    if (is_array($condition)) {
                        // 支持嵌套数组条件: ['column', 'operator', 'value']
                        $this->addWhereCondition(
                            $condition[0], 
                            $condition[1], 
                            $condition[2], 
                            $chainOperator
                        );
                    } else {
                        // 普通键值对
                        $this->addWhereCondition($key, '=', $condition, $chainOperator);
                    }
                }
            }

            // 如果第二个参数是连接符
            if (is_string($operatorOrValue) && in_array(strtoupper($operatorOrValue), ['AND', 'OR'])) {
                $chainOperator = $operatorOrValue;
            }
        }
        // 处理 where('column', 'value') 或 where('column', 'operator', 'value') 形式
        else {
            if ($value === null) {
                // where('column', 'value') 形式
                $this->addWhereCondition($conditions, '=', $operatorOrValue, $chainOperator);
            } else {
                // where('column', 'operator', 'value') 形式
                $this->addWhereCondition($conditions, $operatorOrValue, $value, $chainOperator);
            }
        }

        return $this;
    }

    /**
     * 添加WHERE条件
     * @param string $column 字段名
     * @param string $operator 运算符
     * @param mixed $value 值
     * @param string $chainOperator 条件连接符 (AND/OR)
     */
    protected function addWhereCondition($column, $operator, $value, $chainOperator = 'AND') {
        // 验证运算符
        $validOperators = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'];
        $operator = strtoupper($operator);
        
        if (!in_array($operator, $validOperators)) {
            // 如果不是有效的运算符，假定它是值，使用等号运算符
            $value = $operator;
            $operator = '=';
        }

        // 添加连接符
        if (!empty($this->where)) {
            $this->where .= " $chainOperator ";
        }

        // 特殊处理 IN 和 NOT IN
        if (in_array($operator, ['IN', 'NOT IN'])) {
            if (!is_array($value)) {
                $value = [$value];
            }
            $placeholders = implode(',', array_fill(0, count($value), '%s'));
            $this->where .= "$column $operator ($placeholders)";
            $this->values = array_merge($this->values, $value);
        }
        // 特殊处理 BETWEEN 和 NOT BETWEEN
        else if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'])) {
            if (!is_array($value) || count($value) !== 2) {
                throw new \InvalidArgumentException("BETWEEN operator requires an array with exactly 2 values");
            }
            $this->where .= "$column $operator %s AND %s";
            $this->values = array_merge($this->values, $value);
        }
        // 处理其他运算符
        else {
            $this->where .= "$column $operator %s";
            $this->values[] = $value;
        }
    }

    /**
     * WHERE IN 条件查询
     * @param string $column 字段名
     * @param array $values 值数组
     * @return $this
     */
    public function whereIn($column, array $values) {
        $placeholders = implode(',', array_fill(0, count($values), '%d'));
        $this->where = "$column IN ($placeholders)";
        $this->values = $values;
        return $this;
    }

    /**
     * 设置排序
     * @param string $column 排序字段
     * @param string $direction 排序方向 (ASC/DESC)
     * @return $this
     */
    public function order($column, $direction = '') {
        $this->orderBy = "$column $direction";
        return $this;
    }

    /**
     * 设置限制
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return $this
     */
    public function limit($limit, $offset = 0) {
        $this->limit = $offset ? "$offset, $limit" : $limit;
        return $this;
    }

    /**
     * 分页查询
     * @param int $page 页码数（从1开始）
     * @param int $perPage 每页数量
     * @return $this
     */
    public function page($page = 1, $perPage = 10) {
        $page = max(1, (int)$page); // 确保页码至少为1
        $offset = ($page - 1) * $perPage;
        return $this->limit($perPage, $offset);
    }

    /**
     * 动态设置表名并返回当前实例
     * @param string $tableName 表名
     * @param bool $isFullName 是否是完整表名（true则不添加前缀）
     * @return $this
     */
    public static function table($tableName, $isFullName = false) {
        $instance = self::getInstance();

        // 保存原始表名，以便后续可能需要恢复
        if (!isset($instance->original_table)) {
            $instance->original_table = $instance->table;
        }

        // 根据 isFullName 参数决定是否添加前缀
        if ($isFullName) {
            $instance->table = $tableName;
        } else {
            $instance->table = $instance->prefix . $instance->plugin_prefix . $tableName;
        }

        // 重置where条件
        $instance->where = null;
        $instance->values = [];

        return $instance;
    }

    /**
     * 重置表名为原始值
     * @return $this
     */
    public function resetTable() {
        if (isset($this->original_table)) {
            $this->table = $this->original_table;
        }
        return $this;
    }

    /**
     * 析构函数中恢复原始表名
     */
    public function __destruct() {
        $this->resetTable();
    }

    /**
     * 执行查询并返回结��
     * @return array
     */
    public function get() {
        $sql = $this->buildSql();

        // 如果开启了调试模式
        if ($this->debug) {
            Log::error("Executing SQL: " . $sql);
            if (is_admin()) {
                echo "<pre>Executing SQL: " . esc_html($sql) . "</pre>";
            }
        }

        // 执行查询
        $results = $this->wpdb->get_results($sql);

        // 重置所有查询条件
        $this->resetQuery();

        return $results;
    }

    /**
     * 重置查询条件
     */
    protected function resetQuery() {
        $this->select = '*';
        $this->joins = [];
        $this->where = null;
        $this->values = [];
        $this->orderBy = null;
        $this->limit = null;
        $this->debug = false;
    }

    /**
     * 构建 SQL 语句
     * @return string
     */
    protected function buildSql() {
        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }

        if (!empty($this->where)) {
            $sql .= " WHERE " . $this->where;
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . $this->orderBy;
        }

        if (!empty($this->limit)) {
            $sql .= " LIMIT " . $this->limit;
        }

        // 如果有预处理参数，进行替换
        if (!empty($this->values)) {
            $sql = $this->wpdb->prepare($sql, $this->values);
        }

        $this->lastSql = $sql;
        return $sql;
    }

    /**
     * 获取查询结果的数量
     * @param string $column 要统计的字段，默认为 '*'
     * @return int
     */
    public function count($column = '*') {
        // 保存原来的 select
        $originalSelect = $this->select;

        // 设置 COUNT 查询
        $this->select = "COUNT($column) as count";

        // 执行查询
        $result = $this->get();

        // 恢复原来的 select
        $this->select = $originalSelect;

        // 返回计数结果
        return isset($result[0]->count) ? (int)$result[0]->count : 0;
    }

    /**
     * 获取单条记录
     * @return object|null
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }

    /**
     * 获取单列数据
     * @param string $column 要获取的列名
     * @return array
     */
    public function getCol($column) {
        $originalSelect = $this->select;
        $this->select = $column;

        $sql = "SELECT {$this->select} FROM {$this->table}";

        if (!empty($this->where)) {
            $sql .= " WHERE " . $this->where;
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . $this->orderBy;
        }

        if (!empty($this->limit)) {
            $sql .= " LIMIT " . $this->limit;
        }

        // 执行查询
        if (!empty($this->values)) {
            $results = $this->wpdb->get_col(
                $this->wpdb->prepare($sql, $this->values)
            );
        } else {
            $results = $this->wpdb->get_col($sql);
        }

        // 重置所有查询条件
        $this->resetQuery();
        // 重置查询条件
        $this->select = $originalSelect;

        return $results;
    }


    public function orderByField($field, $values) {
        if (is_array($values)) {
            $values = implode(',', array_map('intval', $values));
        }
        $this->orderBy = "FIELD({$field}, {$values})";
        return $this;
    }


    public function pluck($column) {
        $results = $this->get();
        return array_map(function($row) use ($column) {
            return $row->$column;
        }, $results);
    }

    /**
     * 开启/关闭调试模式
     * @param bool $debug
     * @return $this
     */
    public function debug($debug = true) {
        $this->debug = $debug;
        return $this;
    }

    /**
     * 获取最后执行的 SQL
     * @return string
     */
    public function getLastSql() {
        return $this->lastSql;
    }

    /**
     * 打印 SQL 并继续执行
     * @return $this
     */
    public function dumpSql() {
        $this->debug = true;

        // 构建 SQL 语句
        $sql = $this->buildSql();

        // 输出 SQL 到错误日志
        Log::error("Debug SQL: " . $sql);

        // 如果在管理后台，直接输出SQL
        if (is_admin()) {
            echo "<pre>Debug SQL: " . esc_html($sql) . "</pre>";
        }

        return $this;
    }

    /**
     * 打印 SQL 并停止执行
     */
    public function ddSql() {
        $this->debug = true;
        $result = $this->get();
        wp_die($this->lastSql);
        return $result;
    }

    /**
     * 执行查询并记录 SQL
     */
    protected function executeQuery($sql, $values = []) {
        if (!empty($values)) {
            $sql = $this->wpdb->prepare($sql, $values);
        }
        $this->lastSql = $sql;

        if ($this->debug) {
            Log::error("SQL: " . $sql);
        }

        return $this->wpdb->get_results($sql);
    }
}