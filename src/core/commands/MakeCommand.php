<?php
namespace kitpress\core\commands;

use kitpress\core\abstracts\Command;
use kitpress\core\Bootstrap;
use kitpress\core\Container;
use kitpress\utils\Helper;
use kitpress\utils\Str;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 模块创建命令类
 *
 * 用于创建新的模块，包括控制器、视图和相关资源文件。
 * 支持从数据库表结构自动生成相关代码。
 *
 * @package kitpress\core\commands
 * @author Allan
 * @since 1.0.0
 */
class MakeCommand extends Command
{
    /**
     * 模块名称（大驼峰格式）
     */
    protected string $studlyName;

    /**
     * 模块名称（中划线格式）
     */
    protected string $kebabName;

    /**
     * 模块名称（下划线格式）
     */
    protected string $snakeName;

    protected string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        try {
            // 加载容器
            $this->container = Container::getInstance(Helper::key($rootPath));
            Bootstrap::boot($this->container)->start();
            $this->container->config->load('database');
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * 创建后台模块
     *
     * ## OPTIONS
     *
     * <name>
     * : 模块名称
     *
     * [--force]
     * : 强制创建（如果已存在则覆盖）
     *
     * ## EXAMPLES
     *     wp kitpress make posts
     *     wp kitpress make settings --force
     *
     * @param array $args 位置参数
     * @param array $assoc_args 关联参数
     * @return void
     */
    public function __invoke(array $args, array $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error('请指定模块名称');
            return;
        }

        try {
            // 验证模块名称
            $this->validateModuleName($args[0]);

            // 处理各种命名格式
            $originalName = $args[0];
            $this->studlyName = Str::studly($originalName);    // 大驼峰: UserProfile
            $this->kebabName = Str::kebab($originalName);      // 中划线: user-profile
            $this->snakeName = Str::snake($originalName);      // 下划线: user_profile

            $force = \WP_CLI\Utils\get_flag_value($assoc_args, 'force', false);
            $root_path = $this->getRootPath();

            // 创建模块结构
            $this->createModuleStructure($root_path, $force);

            // 创建视图文件
            $this->createViews($root_path, $this->kebabName, $force);

            // 创建控制器
            $this->createController($root_path, $force);

            \WP_CLI::success("模块 {$this->studlyName} 创建成功！");
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    /**
     * 验证模块名称
     */
    protected function validateModuleName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name)) {
            throw new \InvalidArgumentException(
                '模块名称只能包含字母、数字、下划线和中划线，且必须以字母开头'
            );
        }
    }

    /**
     * 创建模块基础结构
     */
    protected function createModuleStructure(string $root_path, bool $force): void
    {
        // 创建目录结构
        $structure = [
            'backend' => [
                'controllers' => [],
                'views' => [
                    $this->kebabName => []
                ],
                'assets' => [
                    'component' => [
                        'vue' => [
                            'vue.min.js',
                        ],
                        'common.js',
                        'common.css',
                        'utils.js',
                    ],
                    'js' => [],
                    'css' => []
                ]
            ]
        ];
        \WP_CLI::line("正在创建模块 {$this->studlyName} ...");
        $this->createStructure($root_path, $structure, '', $force);
    }

    /**
     * 创建目录结构
     *
     * @param string $base_path 基础路径
     * @param array $items 目录和文件列表
     * @param string $current_path 当前相对路径
     * @param bool $force 是否强制创建
     * @return void
     * @throws \RuntimeException 当目录创建失败时
     */
    private function createStructure(string $base_path, array $items, string $current_path = '', bool $force = false)
    {
        if (!is_writable($base_path)) {
            throw new \RuntimeException("目录无写入权限: {$base_path}");
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
                if(!empty($item)) $this->createStructure($base_path, $item, $current_path . $name . '/');
            } else {

                if( $item == 'vue.min.js' || $item == 'utils.js' || $item == 'common.js' || $item == 'common.css' ){
                    $folder = $item == 'vue.min.js' ? 'vue/' : '';
                    if( !file_exists($base_path .'backend/assets/component/' . $folder . $item) ){
                        $content = file_get_contents( KITPRESS_PATH . 'core/templates/assets/' . $item . '.stub');
                        file_put_contents($base_path .'backend/assets/component/'. $folder . $item, $content);
                    }
                    continue;
                }

                // 创建普通目录
                $dir_path = $path . $item;
                if (!is_dir($dir_path)) {
                    mkdir($dir_path, 0755, true);
                    \WP_CLI::line("创建文件夹: " . str_replace($base_path, '', $dir_path));
                }
            }
        }
    }

    /**
     * 创建控制器文件
     */
    protected function createController(string $root_path, bool $force): void
    {
        $controller_path = $root_path . "/backend/controllers/{$this->studlyName}Controller.php";

        if (!file_exists($controller_path) || $force) {
            $controller_content = $this->getControllerContent(
                $this->studlyName,
                $this->kebabName,
                $this->snakeName
            );

            if (file_put_contents($controller_path, $controller_content) === false) {
                throw new \RuntimeException("无法写入控制器文件: {$controller_path}");
            }

            \WP_CLI::line("创建控制器: backend/controllers/{$this->studlyName}Controller.php");
        }
    }

    /**
     * 创建控制器文件的具体内容
     */
    private function getControllerContent(string $studlyName, string $kebabName, string $snakeName): string
    {
        $stub_path = KITPRESS_PATH . 'core/templates/controllers/backend-controller.stub';
        $content = file_get_contents($stub_path);

        // 获取数据库表结构
        $db_version = str_replace('.', '', $this->container->config->get('app.db_version'));
        $table = $this->container->config->get('database.versions.' . $db_version . '.tables.' . $snakeName, []);

        // 生成 createEmptyModel 方法的内容
        $modelProperties = $this->generateModelProperties($table);
        $buildListWhere = $this->generateBuildListWhere($table);
        $validationRules = $this->generateValidationRules($table);

        // 替换模板中的变量
        $replacements = [
            '{{NAME}}' => $studlyName,
            '{{NAME_KEBAB}}' => $kebabName,
            '{{NAME_SNAKE}}' => $snakeName,
            '{{MODEL_PROPERTIES}}' => $modelProperties,
            '{{BUILD_LIST_WHERE}}' => $buildListWhere,
            '{{VALIDATION_RULES}}' => $validationRules,
            '{{NAMESPACE}}' => $this->container->config->get('app.namespace')
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * 根据数据库表结构生成模型属性
     */
    private function generateModelProperties(array $table): string
    {
        $columns = $this->extractTableColumns($table);
        if (empty($columns)) {
            return '$detail->id = 0;';
        }

        $properties = [];
        foreach ($columns as $column => $definition) {
            $defaultValue = $this->getDefaultValue($definition,$column);
            $comment = $definition['comment'] ? "// {$definition['comment']}" : '';
            $properties[] = "\$detail->{$column} = {$defaultValue}; {$comment}";
        }

        return implode("\n        ", $properties);
    }

    /**
     * 生成构建查询条件的代码
     */
    private function generateBuildListWhere(array $table): string
    {
        $columns = $this->extractTableColumns($table);
        $conditions = [];

        foreach ($columns as $column => $definition) {
            // 跳过不需要搜索的字段
            if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            // 根据字段类型生成不同的查询条件
            switch ($definition['type']) {
                case 'tinyint':
                    if (in_array($column, ['status', 'is_active', 'enabled'])) {
                        $conditions[] = <<<CODE
        if (isset(\$this->filters['{$column}']) && \$this->filters['{$column}'] !== '') {
            \$where[] = ['{$column}', '=', (int)\$this->filters['{$column}']];
        }
CODE;
                    }
                    break;

                case 'varchar':
                case 'text':
                    $conditions[] = <<<CODE
        if (!empty(\$this->filters['{$column}'])) {
            \$where[] = ['{$column}', 'LIKE', '%' . \$this->filters['{$column}'] . '%'];
        }
CODE;
                    break;
            }
        }

        return implode("\n\n", $conditions);
    }

    /**
     * 生成验证规则
     */
    private function generateValidationRules(array $table): string
    {
        $columns = $this->extractTableColumns($table);
        $rules = [];

        foreach ($columns as $column => $definition) {
            // 跳过不需要验证的字段
            if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $ruleSet = [];

            // 根据字段类型添加验证规则
            switch ($definition['type']) {
                case 'int':
                case 'bigint':
                case 'tinyint':
                    $ruleSet[] = 'required';
                    $ruleSet[] = 'integer';
                    break;

                case 'varchar':
                    $ruleSet[] = 'required';
                    $ruleSet[] = 'string';
                    // 如果有默认值，则不是必填
                    if ($definition['default'] !== null) {
                        array_shift($ruleSet); // 移除 required
                    }
                    break;

                case 'text':
                    $ruleSet[] = 'string';
                    break;
            }

            if (!empty($ruleSet)) {
                $rulesStr = implode('|', $ruleSet);
                $rules[] = <<<CODE
        '{$column}' => [
            'rules' => '{$rulesStr}',
            'message' => '{$definition['comment']}不正确'
        ],
CODE;
            }
        }

        return implode("\n", $rules);
    }

    /**
     * 从表定义中提取字段信息，排除所有索引、键和约束定义
     *
     * @param array $table 表结构定义
     * @return array 处理后的字段信息
     */
    private function extractTableColumns(array $table): array
    {
        $columns = $table['columns'] ?? [];
        $validColumns = [];

        // MySQL 保留关键字列表 - 用于排除非字段定义
        $reservedKeywords = [
            'PRIMARY KEY',
            'UNIQUE KEY',
            'UNIQUE',
            'KEY',
            'INDEX',
            'FULLTEXT KEY',
            'FULLTEXT',
            'SPATIAL KEY',
            'SPATIAL',
            'FOREIGN KEY',
            'CONSTRAINT'
        ];

        foreach ($columns as $column => $definition) {
            // 跳过空定义
            if (empty($column) || empty($definition)) {
                continue;
            }

            // 1. 检查是否为索引、键或约束定义
            if (preg_match('/^(PRIMARY\s+KEY|UNIQUE(?:\s+KEY)?|KEY|INDEX|FULLTEXT(?:\s+KEY)?|SPATIAL(?:\s+KEY)?|FOREIGN\s+KEY|CONSTRAINT)\s/i', $column) ||
                in_array(strtoupper($column), $reservedKeywords)) {
                continue;
            }

            // 2. 检查字段定义是否包含有效的 MySQL 数据类型
            if (!preg_match('/(tinyint|smallint|mediumint|int|bigint|decimal|float|double|char|varchar|tinytext|text|mediumtext|longtext|date|datetime|timestamp|time|year|enum|set|binary|varbinary|blob|json)/i', $definition)) {
                continue;
            }

            try {
                // 3. 解析字段定义
                $columnInfo = $this->parseColumnDefinition($definition);

                // 4. 确保解析结果包含必要的信息
                if (!isset($columnInfo['type'])) {
                    continue;
                }

                $validColumns[$column] = $columnInfo;
            } catch (\Exception $e) {
                // 如果解析出错，跳过该字段
                \WP_CLI::debug("解析字段 {$column} 失败: " . $e->getMessage());
                continue;
            }
        }

        return $validColumns;
    }

    /**
     * 解析字段定义
     *
     * @param string $definition 字段定义字符串
     * @return array 解析后的字段信息
     */
    private function parseColumnDefinition(string $definition): array
    {
        $result = [
            'type' => '',
            'length' => null,
            'unsigned' => false,
            'nullable' => false,
            'default' => null,
            'auto_increment' => false,
            'comment' => ''
        ];

        // 提取数据类型和长度
        if (preg_match('/^(\w+)(?:\(([^)]+)\))?/', $definition, $matches)) {
            $result['type'] = strtolower($matches[1]);
            if (isset($matches[2])) {
                $result['length'] = $matches[2];
            }
        }

        // 检查是否无符号
        $result['unsigned'] = (bool) preg_match('/\bunsigned\b/i', $definition);

        // 检查是否可空
        $result['nullable'] = !preg_match('/\bNOT\s+NULL\b/i', $definition);

        // 提取默认值
        if (preg_match('/DEFAULT\s+(?:\'([^\']+)\'|(\d+)|NULL)/i', $definition, $matches)) {
            $result['default'] = $matches[1] ?? $matches[2] ?? null;
        }

        // 检查是否自增
        $result['auto_increment'] = (bool) preg_match('/\bauto_increment\b/i', $definition);

        // 提取注释
        if (preg_match('/COMMENT\s+[\'"]([^\'"]+)[\'"]/', $definition, $matches)) {
            $result['comment'] = $matches[1];
        }

        return $result;
    }

    /**
     * 获取字段默认值
     *
     * @param array $definition 字段定义
     * @param string $column 字段名
     * @return string 默认值
     */
    private function getDefaultValue(array $definition, string $column): string
    {
        $type = $definition['type'];
        $default = $definition['default'];

        // 如果字段定义了默认值，优先使用定义的默认值
        if ($default !== null && $default !== '') {
            if ($default === 'CURRENT_TIMESTAMP') {
                return 'date("Y-m-d H:i:s")';
            }
            // 对于数字类型，直接返回数值
            if (in_array($type, ['bigint', 'int', 'tinyint'])) {
                return $default;
            }
            // 对于字符串类型，添加引号
            return "'" . $default . "'";
        }

        // 特殊字段的默认值处理
        if ($column === 'sort_order') {
            return '0'; // 排序字段默认为0
        }
        if (in_array($column, ['is_active', 'status', 'enabled'])) {
            return '1'; // 状态字段默认为1（启用）
        }

        // 没有默认值时，根据字段类型设置合理的默认值
        switch ($type) {
            case 'bigint':
            case 'int':
            case 'tinyint':
                return '0';

            case 'decimal':
            case 'float':
            case 'double':
                return '0.00';

            case 'varchar':
            case 'char':
            case 'text':
            case 'mediumtext':
            case 'longtext':
                return "''";

            case 'datetime':
            case 'timestamp':
                return 'date("Y-m-d H:i:s")';

            case 'date':
                return 'date("Y-m-d")';

            case 'time':
                return 'date("H:i:s")';

            default:
                return 'null';
        }
    }

    /**
     * 创建视图文件
     *
     * 根据模块名创建对应的视图文件，包括：
     * - index.php：列表视图
     * - create.php：创建表单视图
     * - edit.php：编辑表单视图
     * - view.php：详情视图
     *
     * @param string $root_path 项目根路径
     * @param string $kebabName 模块名称（中划线格式）
     * @param bool $force 是否强制覆盖已存在的文件
     * @return void
     * @throws \RuntimeException 当视图创建失败时
     */
    protected function createViews(string $root_path, string $kebabName, bool $force): void
    {
        try {
            // 视图目录路径
            $view_path = $root_path . "/backend/views/{$kebabName}";

            // 确保视图目录存在
            if (!is_dir($view_path)) {
                if (!mkdir($view_path, 0755, true)) {
                    throw new \RuntimeException("无法创建视图目录: {$view_path}");
                }
                \WP_CLI::line("创建视图目录: backend/views/{$kebabName}");
            }

            // 获取数据库表结构（用于生成表单字段）
            $db_version = str_replace('.', '', $this->container->config->get('app.db_version'));
            $table = $this->container->config->get('database.versions.' . $db_version . '.tables.' . $this->snakeName, []);
            $table_comment = $this->formatTableComment($table['comment'] ?? $this->studlyName);
            $columns = $this->extractTableColumns($table);

            // 定义要创建的视图文件
            $views = [
                'index' => [
                    'title' => "{$table_comment}列表",
                    'description' => "显示{$table_comment}的列表页面，支持搜索、排序和分页"
                ],
               /* 'create' => [
                    'title' => "新增{$table_comment}",
                    'description' => "创建新的{$table_comment}记录的表单页面"
                ],*/
                'edit' => [
                    'title' => "编辑{$table_comment}",
                    'description' => "编辑现有{$table_comment}记录的表单页面"
                ],
                'view' => [
                    'title' => "{$table_comment}详情",
                    'description' => "显示{$table_comment}记录的详细信息"
                ]
            ];

            // 创建每个视图文件
            foreach ($views as $view => $info) {
                $file_path = "{$view_path}/{$view}.php";

                // 如果文件已存在且不强制覆盖，则跳过
                if (file_exists($file_path) && !$force) {
                    \WP_CLI::line("跳过已存在的视图: {$view}.php");
                    continue;
                }

                // 获取视图模板内容
                $content = $this->getViewContent($view);

                // 替换模板变量
                $replacements = [
                    '{{NAME}}' => $table_comment,  // 使用表注释
                    '{{NAME_KEBAB}}' => $this->kebabName,
                    '{{NAME_SNAKE}}' => $this->snakeName,
                    '{{NAME_CAMEL}}' => Str::camel($this->kebabName),
                    '{{PAGE_TITLE}}' => $info['title'],
                    '{{DESCRIPTION}}' => $info['description'],
                    '{{LIST_URL}}' => "admin_url('admin.php?page={$this->kebabName}')",
                    '{{TABLE_HEADERS}}' => $this->generateTableHeaders($columns),
                    '{{TABLE_ROWS}}' => $this->generateTableRows($columns),
                    '{{FORM_FIELDS}}' => $this->generateFormFields($columns),
                    '{{VIEW_FIELDS}}' => $this->generateViewFields($columns),
                    '{{SEARCH_FIELDS}}' => $this->generateSearchFields($columns),
                    '{{SORT_FIELDS}}' => $this->generateSortFields($columns)
                ];

                $content = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $content
                );

                // 写入文件
                if (file_put_contents($file_path, $content) === false) {
                    throw new \RuntimeException("无法写入视图文件: {$file_path}");
                }

                \WP_CLI::line("创建视图: backend/views/{$kebabName}/{$view}.php");
            }

            // 创建相关的资源文件
            $this->createViewAssets($root_path, $kebabName, $force);

        } catch (\Exception $e) {
            throw new \RuntimeException("创建视图文件失败: " . $e->getMessage());
        }
    }

    /**
     * 处理表注释
     *
     * @param string $comment 原始表注释
     * @return string 处理后的注释
     */
    private function formatTableComment(string $comment): string
    {
        // 移除末尾的"表"字
        return rtrim($comment, '表');
    }

    /**
     * 创建视图相关的资源文件
     */
    protected function createViewAssets(string $root_path, string $kebabName, bool $force): void
    {
        $assets = [
            'js' => [
                'content' => $this->getAssetContent('js'),
                'path' => "backend/assets/js/{$kebabName}.js"
            ],
            'css' => [
                'content' => $this->getAssetContent('css'),
                'path' => "backend/assets/css/{$kebabName}.css"
            ]
        ];

        foreach ($assets as $type => $asset) {
            $file_path = $root_path . $asset['path'];

            if (!is_dir(dirname($file_path))) {
                mkdir(dirname($file_path), 0755, true);
            }

            if (!file_exists($file_path) || $force) {
                if (file_put_contents($file_path, $asset['content']) === false) {
                    throw new \RuntimeException("无法写入{$type}文件: {$file_path}");
                }
                \WP_CLI::line("创建{$type}文件: " . str_replace($root_path, '', $file_path));
            }
        }
    }

    /**
     * 获取资源文件内容
     *
     * @param string $type 资源类型 (js|css)
     * @return string 资源文件内容
     */
    protected function getAssetContent(string $type): string
    {
        $stub_path = KITPRESS_PATH . 'core/templates/assets/' . $type . '.stub';

        if (!file_exists($stub_path)) {
            return '';
        }

        $content = file_get_contents($stub_path);

        $camelNamespace = Str::camel($this->container->get('plugin')->getNamespace());

        // 替换 js 文件中的变量名
        if ($type === 'js') {
            $replacements = [
                '{{ADMIN}}' => $camelNamespace . $this->studlyName . 'Admin',
                '{{CAMEL_NAME}}' => Str::camel($this->kebabName),
                '{{STUDLY_NAME}}' => $this->studlyName ,
            ];

            $content = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $content
            );
        }

        return $content;
    }

    /**
     * 生成搜索字段
     */
    protected function generateSearchFields(array $columns): string
    {
        // 定义需要搜索的字段及其类型
        $searchConfig = [
            // text 类型的搜索字段
            //'text' => ['name', 'title', 'description', 'content', 'slug'],
            // select 类型的搜索字段及其选项
            'select' => [
                'type' => [
                ],
                'status' => [
                    '1' => '启用',
                    '0' => '禁用',
                ],
                'is_active' => [
                    '1' => '启用',
                    '0' => '禁用',
                ]
            ]
        ];

        $fields = [];

        foreach ($columns as $column => $definition) {
            $label = $definition['comment'] ?: $this->formatLabel($column);

            // 处理 select 类型的搜索字段
            if (isset($searchConfig['select'][$column])) {
                $options = $searchConfig['select'][$column];
                $optionsHtml = "                <option value=\"\">所有{$label}</option>\n";
                foreach ($options as $value => $text) {
                    $optionsHtml .= "                <option value=\"{$value}\">{$text}</option>\n";
                }

                $fields[] = <<<HTML
                <select id="search_{$column}" 
                        v-model="filters.{$column}" 
                        @change="loadData">
{$optionsHtml}                </select>
HTML;
                continue;
            }

            // 处理文本类型的搜索字段
            $fields[] = <<<HTML
                <input type="text" 
                       id="search_{$column}" 
                       v-model="filters.{$column}" 
                       @input="loadData" placeholder="{$label}">
HTML;

        }

        return implode("\n            ", $fields);

    }

    /**
     * 生成排序字段
     */
    protected function generateSortFields(array $columns): string
    {
        $sortable = ['id', 'name', 'sort_order', 'created_at', 'updated_at'];
        $fields = [];

        foreach ($columns as $column => $definition) {
            if (in_array($column, $sortable)) {
                $label = $definition['comment'] ?: $this->formatLabel($column);
                $fields[] = <<<HTML
            <th class="sortable" 
                @click="handleSort('{$column}')" 
                :class="{ active: sortField === '{$column}' }">
                {$label}
                <span class="sort-icon" v-if="sortField === '{$column}'">
                    {{ sortOrder === 'asc' ? '↑' : '↓' }}
                </span>
            </th>
HTML;
            }
        }

        return implode("\n            ", $fields);
    }

    private function formatLabel(string $column): string
    {
        return ucfirst(str_replace('_', ' ', $column));
    }

    private function generateFormInput(string $column, array $definition): string
    {
        $type = $definition['type'];
        $label = $definition['comment'] ?: $this->formatLabel($column);

        if ($column === 'type') {
            return <<<HTML
            <select id="{$column}" v-model="form.{$column}" class="regular-text">
                <option value="">请选择</option>
            </select>
HTML;
        }
        if (in_array($column, ['is_active', 'status'])) {
            return '<input type="checkbox" v-model="form.' . $column . '" placeholder="'. $label .'">';
        }

        if (in_array($type, ['tinyint', 'int', 'bigint'])) {
            return '<input type="number" id="' . $column . '" v-model.number="form.' . $column . '" class="regular-text" placeholder="'. $label .'">';
        }

        if ($type === 'text') {
            return '<textarea id="' . $column . '" v-model="form.' . $column . '" class="large-text" rows="6"></textarea>';
        }

        return '<input type="text" id="' . $column . '" v-model="form.' . $column . '" class="regular-text" placeholder="'. $label .'">';
    }

    private function generateTableHeaders(array $columns): string
    {
        // 处理 ID 列
        if (isset($columns['id'])) {
            // $rows[] = "<td>{{ item.id }}</td>";
            unset($columns['id']); // 从后续处理中移除 ID 列
        }

        $headers = [];
        $hasStatusColumn = false;

        foreach ($columns as $column => $definition) {
            if (in_array($column, ['status', 'is_active', 'enabled'])) {
                $hasStatusColumn = true;
            }

            $label = $definition['comment'] ?: $this->formatLabel($column);
            $headers[] = "<th>{$label}</th>";
        }

        // 如果有状态字段，添加操作列
        if ($hasStatusColumn) {
            $headers[] = "<th>操作</th>";
        }

        return implode("\n            ", $headers);
    }

    /**
     * 生成表格行
     *
     * @param array $columns 数据库表字段信息
     * @return string HTML行代码
     */
    private function generateTableRows(array $columns): string
    {
        $rows = [];
        $sortFields = ['sort_order', 'order', 'weight']; // 定义需要排序处理的字段
        $hasStatusColumn = false;
        $statusHtml = '';

        // 处理 ID 列
        if (isset($columns['id'])) {
            // $rows[] = "<td>{{ item.id }}</td>";
            unset($columns['id']); // 从后续处理中移除 ID 列
        }

        // 处理第一列（标题列）
        $firstColumn = array_key_first($columns);
        $rows[] = <<<HTML
            <td class="title column-title has-row-actions">
                <strong>{{ item.{$firstColumn} }}</strong>
                <div class="row-actions">
                    <span class="edit">
                        <a :href="item.edit_url">编辑</a> |
                    </span>
                    <span class="view">
                         <a :href="item.view_url">查看详情</a> |
                    </span>
                    <span class="delete">
                        <a @click.prevent="deleteItem(item)" href="#" class="submitdelete">删除</a>
                    </span>
                </div>
            </td>
HTML;

        // 处理其余列
        foreach (array_slice($columns, 1) as $column => $definition) {
            // 跳过不需要显示的字段
            if (in_array($column, ['deleted_at', 'password'])) {
                continue;
            }

            // 处理排序字段
            if (in_array($column, $sortFields)) {
                $rows[] = <<<HTML
            <td>
                <input
                    type="number"
                    v-model.number="item.{$column}"
                    class="small-text"
                    min="0"
                    @change="updateSort(item)"
                    @keyup.enter="updateSort(item)"
                    :disabled="isSorting(item.id)"
                    style="width: 60px; text-align: center;"
                >
                <span v-if="isSorting(item.id)" class="spinner is-active"></span>
            </td>
HTML;
                continue;
            }

            // 根据字段类型格式化显示
            switch ($definition['type']) {

                case 'tinyint':
                    if (in_array($column, ['status', 'is_active', 'enabled'])) {
                        $hasStatusColumn = true;
                        $rows[] = <<<HTML
                    <td>
                        <span :class="['status-badge', item.{$column} == 1 ? 'success' : 'error']">
                            {{ item.{$column} == 1 ? '启用' : '禁用' }}
                        </span>
                    </td>
HTML;
                        $statusHtml = <<<HTML
                    <td>
                        <button
                            :class="item.{$column} == 1 ? 'button-secondary' : 'button-primary'"
                            class="button"
                            @click.stop="toggleStatus(item)"
                            :disabled="isProcessing(item.id)"
                        >
                            {{ item.{$column} == 1 ? '禁用' : '启用' }}
                            <span v-if="isProcessing(item.id)" class="spinner is-active"></span>
                        </button>
                    </td>
HTML;

                    } else {
                        $rows[] = "<td>{{ item.{$column} }}</td>";
                    }
                    break;

                case 'text':
                case 'longtext':
                    $rows[] = "<td>{{ utils.truncate(item.{$column}, 50) }}</td>";
                    break;

                default:
                    $rows[] = "<td>{{ item.{$column} }}</td>";
            }
        }

        // 如果有状态列，添加操作列
        if ($hasStatusColumn) {
            $rows[] = $statusHtml;
        }

        return implode("\n            ", $rows);
    }

    private function generateFormFields(array $columns): string
    {
        $fields = [];
        foreach ($columns as $column => $definition) {
            if (in_array($column, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            $label = $definition['comment'] ?: $this->formatLabel($column);
            $input = $this->generateFormInput($column, $definition);

            $fields[] = <<<HTML
            <tr>
                <th scope="row"><label for="{$column}">{$label}</label></th>
                <td>{$input}</td>
            </tr>
HTML;
        }
        return implode("\n            ", $fields);
    }

    private function generateViewFields(array $columns): string
    {
        $fields = [];
        foreach ($columns as $column => $definition) {
            $label = $definition['comment'] ?: $this->formatLabel($column);
            $fields[] = <<<HTML
            <tr>
                <th scope="row">{$label}</th>
                <td><?php echo esc_html(\$detail->{$column}); ?></td>
            </tr>
HTML;
        }
        return implode("\n            ", $fields);
    }

    private function getViewContent(string $template_name): string
    {
        $stub_path = KITPRESS_PATH . 'core/templates/views/' . $template_name . '.stub';
        if (!file_exists($stub_path)) {
            return "<?php\n// Template not found: {$template_name}";
        }
        return file_get_contents($stub_path);
    }
}