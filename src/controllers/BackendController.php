<?php
namespace kitpress\controllers;

use kitpress\core\abstracts\Controller;
use kitpress\library\Config;
use kitpress\library\Model;
use kitpress\library\Plugin;
use kitpress\utils\ErrorHandler;
use kitpress\utils\Lang;
use kitpress\utils\Str;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 后台控制器基类
 *
 * 提供WordPress后台页面的基础功能实现，包括：
 * - 资源管理（CSS/JS文件加载）
 * - 页面路由和权限控制
 * - AJAX请求处理
 * - 数据列表和分页
 * - 视图渲染
 *
 * @since 1.0.0
 * @package kitpress\controllers
 *
 * @property-read Plugin $plugin 插件实例
 * @property-read Config $config 配置管理器
 * @property-read Model $model 数据模型实例
 *
 *
 * @example
 * class UserController extends BackendController {
 *     public function index() {
 *         return $this->render('user/index');
 *     }
 * }
 */
class BackendController extends Controller {

    /**
     * 后台样式文件列表
     *
     * 存储需要加载的额外CSS文件路径
     * 键名为样式句柄，键值为样式文件路径
     *
     * @since 1.0.0
     * @access protected
     * @var array
     */
    protected array $styles = [];

    /**
     * 后台脚本文件列表
     *
     * 存储需要加载的额外JavaScript文件路径
     * 键名为脚本句柄，键值为脚本文件路径
     *
     * @since 1.0.0
     * @access protected
     * @var array
     */
    protected array $scripts = [];

    /**
     * 列表筛选条件
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * 页面标识符
     *
     * WordPress后台页面的唯一标识符
     * 用于构建菜单URL和权限检查
     * 格式通常为：{plugin_prefix}-{controller_name}
     *
     * @since 1.0.0
     * @access protected
     * @var string
     */
    protected string $page = '';

    /**
     * 控制器原始名称
     *
     * 存储当前控制器的类名（不含Controller后缀）
     * 例如：对于 UserController 类，该值为 'User'
     *
     * @since 1.0.0
     * @access protected
     * @var string
     */
    protected string $controllerName = '';

    /**
     * 格式化后的控制器名称
     *
     * 存储控制器名称的格式化版本（短横线格式）
     * 例如：UserProfile 转换为 'user-profile'
     * 用于构建URL和资源文件路径
     *
     * @since 1.0.0
     * @access protected
     * @var string
     */
    protected string $formatControllerName = '';

    public function __construct() {
        parent::__construct();
    }

    /**
     * 在admin_init钩子之后加载
     * @return void
     */
    protected function init() {
        parent::init();

        $this->viewPath = 'backend/views';
        $this->layout = 'default';
        $this->viewData = [];

        $this->setControllerName();

        $this->page = $this->plugin->getPrefix() . $this->formatControllerName;

        $this->initFilters();
    }

    /**
     * 初始化筛选条件
     * 从请求中获取并清理筛选参数
     *
     * @return void
     */
    protected function initFilters(): void
    {
        // 从请求中获取筛选参数
        $this->filters = $this->input('filters', []);

        // 确保 filters 是数组
        if (!is_array($this->filters)) {
            $this->filters = [];
            return;
        }

        // 过滤和清理输入
        array_walk_recursive($this->filters, function(&$value) {
            $value = \sanitize_text_field($value);
        });
    }

    /**
     * 显示列表页面
     *
     * 渲染默认的列表视图，并传入必要的URL参数。
     * 这是后台管理页面的默认入口点，通常用于显示数据列表。
     *
     * @since 1.0.0
     * @access public
     *
     * @uses \admin_url() 生成WordPress后台URL
     * @uses render() 渲染视图模板
     *
     * @return string|null 返回渲染后的HTML内容，如果渲染失败返回null
     */
    public function index(): ?string
    {
        return $this->render('index',[
            'addUrl' => \admin_url('admin.php?page=' . $this->page . '&action=add'),
        ]);
    }

    /**
     * 构建列表查询条件
     * @return array 查询条件数组
     */
    protected function buildListWhere(): array
    {
        return [];
    }

    /**
     * 获取数据列表
     *
     * 处理 AJAX 请求，返回分页后的数据列表。
     * 包含基础的安全验证、分页处理和数据过滤功能。
     *
     * @return array|bool
     *     返回JSON格式的数据列表和分页信息
     *
     *     @type array  $items    数据项列表
     *     @type int    $total    总记录数
     *     @type int    $page     当前页码
     *     @type int    $per_page 每页显示数量
     * }
     *@throws \Exception 当安全验证失败时抛出异常
     *
     * @uses buildListWhere() 构建查询条件
     * @uses buildListQuery() 构建数据库查询
     *
     * @since 1.0.0
     * @access public
     *
     * @uses verifyNonce() 验证请求的安全性
     */
    public function getList(): bool
    {
        $this->verifyNonce($this->page . '-nonce');

        // 获取提示词类型列表
        $page = max(1, intval($this->input('page', 1)));
        $per_page = max(10, intval($this->input('per_page', $this->config->get('app.features.per_page'))));

        $where = $this->buildListWhere();
        $total = $this->model->where($where)->count();
        $items = $this->buildListQuery($where, $page, $per_page)->get();

        if(!empty($items)) {
            foreach ($items as &$item) {
                $item->edit_url = \admin_url('admin.php?page='. $this->page .'&action=edit&id=' . $item->id);
                $item->view_url = \admin_url('admin.php?page='. $this->page .'&action=view&id=' . $item->id);
            }
        }

        return $this->success(
            [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                // 'sql' => $this->model->getLastSql(),
            ]
        );
    }

    /**
     * 构建列表查询
     * @param array $where 查询条件
     * @param int $page 当前页码
     * @param int $per_page 每页数量
     * @return Model 查询构建器实例
     */
    protected function buildListQuery(array $where, int $page, int $per_page): Model
    {
        $query = $this->model;

        // 应用 JOIN
        foreach ($this->getJoins() as $join) {
            list($table,$condition,$type) = $join;
            $query = $query->join(
                $table,
                $condition,
                $type ?? 'LEFT'
            );
        }

        // 应用查询条件和分页
        return $query->where($where)
            ->field($this->getSelectFields())
            ->page($page, $per_page)
            ->order($this->getDefaultSort());
    }

    /**
     * 获取要查询的字段
     * @return array 字段列表
     */
    protected function getSelectFields(): array
    {
        return [$this->model->getTable() . '.*'];
    }

    /**
     * 获取 JOIN 配置
     * @return array JOIN 配置数组
     */
    protected function getJoins(): array
    {
        return [];
    }

    /**
     * 获取默认排序规则
     * @return array 排序规则数组
     */
    protected function getDefaultSort(): array
    {
        return [
            ['id', 'DESC']
        ];
    }

    /**
     * 验证 nonce
     *
     * 如果是 AJAX/JSON 请求，验证失败时返回 JSON 错误响应
     * 如果是普通请求，直接返回验证结果
     *
     * @param string $action nonce action
     * @param string $nonce_key nonce 参数名
     * @param bool $stop 验证失败是否停止执行
     * @return bool
     */
    protected function verifyNonce(string $action, string $nonce_key = 'nonce', bool $stop = false): bool
    {
        // 先执行父类的验证
        $result = parent::verifyNonce($action, $nonce_key, $stop);

        if ($result) {
            return true;
        }

        // 验证失败时，检查是否是 AJAX 请求
        if ($this->isAjax() || $this->isJsonRequest()) {
            return $this->error(Lang::kit('安全验证失败'));
        }

        return false;
    }

    protected function createEmptyModel(): \stdClass
    {
        return new \stdClass();
    }

    /**
     * 生成并验证唯一的 slug
     *
     * 如果没有提供 slug，则根据 name 生成
     * 如果生成的 slug 已存在，则自动添加数字后缀
     *
     * @since 1.0.0
     * @access protected
     *
     * @param array $modelData 模型数据
     * @param string $field 用于生成 slug 的字段名，默认为 'name'
     * @return string 生成的唯一 slug
     */
    protected function generateUniqueSlug(array &$modelData, string $field = 'title'): string
    {
        // 如果没有提供 slug，根据指定字段生成
        if (empty($modelData['slug'])) {
            $modelData['slug'] = \sanitize_title($modelData[$field]);
        }

        // 检查 slug 是否已存在
        $exists = $this->model->where('slug', $modelData['slug'])->first();
        if ($exists) {
            $i = 1;
            $originalSlug = $modelData['slug'];
            while ($exists) {
                $modelData['slug'] = $originalSlug . '-' . $i;
                $exists = $this->model->where('slug', $modelData['slug'])->first();
                $i++;
            }
        }

        return $modelData['slug'];
    }

    /**
     * 过滤模型数据
     *
     * 清理和格式化提交的数据，确保数据安全性和一致性
     *
     * @since 1.0.0
     * @access protected
     *
     * @param array $modelData 原始模型数据
     * @return array 过滤后的数据
     */
    protected function filterModelData(array $modelData): array
    {
        // 基础过滤
        $filtered = $modelData;

        if (isset($filtered['slug'])) {
            if( isset($modelData['name']) ) {
                $this->generateUniqueSlug($modelData,'name');
            }else{
                $this->generateUniqueSlug($modelData);
            }
        }

        // 类型转换
        if (isset($filtered['is_active'])) {
            $filtered['is_active'] = (int)$filtered['is_active'];
        }
        if (isset($filtered['status'])) {
            $filtered['status'] = (int)$filtered['status'];
        }
        if (isset($filtered['sort_order'])) {
            $filtered['sort_order'] = (int)$filtered['sort_order'];
        }

        if(isset($filtered['created_at'])) unset($filtered['created_at']);
        if(isset($filtered['updated_at'])) unset($filtered['updated_at']);

        return $filtered;
    }

    /**
     * 获取验证规则
     *
     * @since 1.0.0
     * @access protected
     *
     * @return array 验证规则数组
     */
    protected function validationRules(): array
    {
        return [];
    }

    /**
     * 验证模型数据
     *
     * 验证数据是否符合规则要求
     *
     * @param array $data
     * @return bool|array 验证通过返回true，失败返回错误信息数组
     * @since 1.0.0
     * @access protected
     *
     */
    protected function validateModelData(array $data): bool
    {
        // 获取验证规则
        $rules = $this->validationRules();
        if (empty($rules)) {
            return true;
        }

        // 执行验证
        $validator = $this->validate($data, $rules);
        if ($validator->fails()) {
            return $validator->errors();
        }

        return true;
    }

    /**
     * 添加记录
     *
     * 处理新记录的添加操作，包括：
     * - 表单提交的处理
     * - 数据验证
     * - 记录保存
     * - 结果反馈
     *
     * @return string|null 成功返回成功消息，失败返回错误消息，或返回编辑表单视图
     * @throws \Exception
     *
     * @since 1.0.0
     * @access public
     *
     * @uses verifyNonce() 验证请求安全性
     * @uses filterModelData() 过滤和格式化数据
     * @uses validateModelData() 验证数据有效性
     * @uses Model::insert() 插入新记录
     *
     */
    public function add(): ?string
    {
        // 处理表单提交
        if($this->isPost()) {
            $this->verifyNonce($this->page . '-nonce');

            // 获取并过滤数据
            $modelData = $this->filterModelData(
                $this->input('model', [])
            );

            // 验证数据
            $validation = $this->validateModelData($modelData);
            if ($validation !== true) {
                return $this->error(Lang::kit('数据验证失败'), $validation);
            }

            $result = $this->model->insert($modelData);
            if (!$result) {
                return $this->error(Lang::kit('添加失败'));
            }

            return $this->success([
                'listUrl' =>  \admin_url('admin.php?page=' . $this->page ),
            ], Lang::kit('添加成功'));
        }

        return $this->render('edit',[
            'detail' => $this->createEmptyModel(),
            'listUrl' => \admin_url('admin.php?page='. $this->page ),
        ]);
    }

    /**
     * 编辑记录
     *
     * 处理现有记录的更新操作，包括：
     * - 表单提交的处理
     * - 数据验证
     * - 记录更新
     * - 结果反馈
     *
     * @return string|null 成功返回成功消息，失败返回错误消息，或返回编辑表单视图
     * @throws \Exception
     * @uses filterModelData() 过滤和格式化数据
     * @uses validateModelData() 验证数据有效性
     * @uses Model::update() 更新记录
     * @uses ErrorHandler::die() 处理错误情况
     *
     * @since 1.0.0
     * @access public
     *
     * @uses verifyNonce() 验证请求安全性
     */
    public function edit(): ?string
    {
        // 处理表单提交
        if($this->isPost()) {

            $this->verifyNonce($this->page . '-nonce');

            $modelData = $this->input('model', []);
            $id = trim($modelData[$this->model->getPrimaryKey()] ?? 0);

            // 获取并过滤数据
            $modelData = $this->filterModelData(
                $this->input('model', [])
            );

            // 验证数据
            $validation = $this->validateModelData($modelData);
            if ($validation !== true) {
                return $this->error(Lang::kit('数据验证失败'), $validation);
            }

            if (!$id || !$this->model->find($id)) {
                return $this->error(Lang::kit('记录不存在'));
            }

            $result = $this->model->where($this->model->getPrimaryKey(), $id)->update($modelData);
            if (!$result) {
                return $this->error(Lang::kit('更新失败'));
            }

            return $this->success([
                'listUrl' =>  \admin_url('admin.php?page=' . $this->page ),
            ], Lang::kit('更新成功'));
        }

        $id = trim($_GET[$this->model->getPrimaryKey()] ?? 0);
        if (!$id) {
            ErrorHandler::die(Lang::kit('无效的ID'));
        }

        $detail = $this->model->find($id);
        if (!$detail) {
            ErrorHandler::die(Lang::kit('记录不存在'));
        }

        return $this->render('edit',[
            'detail' => $detail,
            'listUrl' => \admin_url('admin.php?page='. $this->page ),
        ]);
    }

    /**
     * 查看记录详情
     *
     * 显示单条记录的详细信息，包括：
     * - ID验证
     * - 记录存在性检查
     * - 详情页面渲染
     *
     * @since 1.0.0
     * @access public
     *
     * @uses Model::find() 查找单条记录
     * @uses ErrorHandler::die() 处理错误情况
     *
     * @return string|null 返回详情视图
     */
    public function view(): ?string
    {
        $id = trim($_GET[$this->model->getPrimaryKey()] ?? 0);
        if (!$id) {
            ErrorHandler::die(Lang::kit('无效的ID'));
        }

        $detail = $this->model->find($id);
        if (!$detail) {
            ErrorHandler::die(Lang::kit('记录不存在'));
        }

        return $this->render('view',[
            'detail' => $detail,
            'editUrl' => \admin_url('admin.php?page='. $this->page .'&action=edit&id=' . $id),
            'listUrl' => \admin_url('admin.php?page='. $this->page ),
        ]);
    }

    /**
     * 删除记录
     *
     * 处理记录的删除操作，包括：
     * - 安全性验证
     * - ID验证
     * - 记录存在性检查
     * - 执行删除
     * - 结果反馈
     *
     * @since 1.0.0
     * @access public
     *
     * @uses verifyNonce() 验证请求安全性
     * @uses Model::find() 查找记录
     * @uses Model::delete() 删除记录
     *
     * @return bool|null 成功返回true，失败返回false，验证失败返回null
     */
    public function delete(): ?bool
    {
        $this->verifyNonce($this->page . '-nonce');

        // 获取要删除的ID
        $id = trim($this->input($this->model->getPrimaryKey()));
        if (!$id) {
            return $this->error(Lang::kit('无效的ID'));
        }

        // 检查记录是否存在
        $model = $this->model->find($id);
        if (!$model) {
            return $this->error(Lang::kit('记录不存在'));
        }

        // 执行删除
        $result = $this->model->where($this->model->getPrimaryKey(), $id)->delete();
        if (!$result) {
            return $this->error(Lang::kit('删除失败'));
        }

        return $this->success(null,Lang::kit('删除成功'));
    }

    /**
     * 批量操作处理方法
     *
     * 处理后台列表页面的批量操作请求，支持以下操作类型：
     * - delete: 批量删除记录
     * - enable: 批量启用记录
     * - disable: 批量禁用记录
     * - 自定义操作: 通过 operation 和 value 参数进行自定义字段更新
     *
     * @since 1.0.0
     * @access public
     *
     * @uses verifyNonce() 验证请求安全性
     * @uses Model::whereIn() 批量更新数据
     *
     * @return bool|null 操作成功返回 true，失败返回 false，验证失败返回 null
     *
     * @example
     * // 基础用法 - 批量删除
     * $.post(ajaxurl, {
     *     action: 'your-action',
     *     ids: [1, 2, 3],
     *     operation: 'delete',
     *     nonce: nonce
     * });
     *
     * // 自定义操作 - 更新状态
     * $.post(ajaxurl, {
     *     action: 'your-action',
     *     ids: [1, 2, 3],
     *     operation: 'status',
     *     status: 1,
     *     nonce: nonce
     * });
     */
    public function batch(): ?bool
    {
        $this->verifyNonce($this->page . '-nonce');

        $ids = $this->input('ids', []);
        $operation = $this->input('operation');
        $value = $this->input($operation,null);

        if (empty($ids) || !is_array($ids)) {
            return $this->error(Lang::kit('请选择要操作的项目'));
        }

        // 清理并验证ID
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return $this->error(Lang::kit('无效的ID'));
        }

        switch ($operation) {
            case 'delete':
                $result = $this->model->whereIn($this->model->getPrimaryKey(), $ids)->delete();
                // $message = '删除';
                break;

            case 'enable':
                $result = $this->model->whereIn($this->model->getPrimaryKey(), $ids)->update([
                    'is_active' => 1,
                ]);
                // $message = '启用';
                break;

            case 'disable':
                $result = $this->model->whereIn($this->model->getPrimaryKey(), $ids)->update([
                    'is_active' => 0,
                ]);
                // $message = '禁用';
                break;

            default:
                if( !empty($operation) && !is_null($value) ) {
                    $result = $this->model->whereIn($this->model->getPrimaryKey(), $ids)->update([
                        $operation => $value,
                    ]);
                    // $message = '修改';
                }else{
                    return $this->error(Lang::kit('未知的操作类型'));
                }

        }

        if (!$result) {
            return $this->error(Lang::kit('操作失败'));
        }

        return $this->success(null, Lang::kit('操作成功'));
    }

    /**
     * 更新记录状态
     *
     * 切换单条记录的启用/禁用状态，包括：
     * - 安全性验证
     * - ID验证
     * - 记录存在性检查
     * - 状态切换（0变1，1变0）
     * - 执行更新
     * - 结果反馈
     *
     * @since 1.0.0
     * @access protected
     *
     * @uses verifyNonce() 验证请求安全性
     * @uses Model::find() 查找记录
     * @uses Model::update() 更新记录状态
     *
     * @return bool|null 操作成功返回 true，失败返回 false，验证失败返回 null
     *
     * @example
     * // AJAX 请求示例
     * $.post(ajaxurl, {
     *     action: 'your-action',
     *     id: 123,
     *     nonce: nonce
     * });
     */
    protected function updateStatus(): ?bool
    {
        $this->verifyNonce($this->page . '-nonce');

        // 获取ID和状态
        $id = trim($this->input($this->model->getPrimaryKey()));
        if (!$id) {
            return $this->error(Lang::kit('无效的ID'));
        }

        // 检查记录是否存在
        $model = $this->model->find($id);
        if (!$model) {
            return $this->error(Lang::kit('记录不存在'));
        }

        // 切换状态（0变1，1变0）
        $newStatus = $model->is_active ? 0 : 1;

        // 更新状态
        $result = $this->model->where($this->model->getPrimaryKey(), $id)->update([
            'is_active' => $newStatus,
        ]);

        if (!$result) {
            return $this->error(Lang::kit('状态更新失败'));
        }

        return $this->success(null,Lang::kit('状态更新成功'));
    }

    /**
     * 更新记录排序
     *
     * 更新单条记录的排序值，包括：
     * - 安全性验证
     * - ID和排序值验证
     * - 记录存在性检查
     * - 排序值规范化（确保非负）
     * - 执行更新
     * - 结果反馈
     *
     * @since 1.0.0
     * @access protected
     *
     * @uses verifyNonce() 验证请求安全性
     * @uses Model::find() 查找记录
     * @uses Model::update() 更新排序值
     *
     * @return bool|null 操作成功返回 true，失败返回 false，验证失败返回 null
     *
     * @example
     * // AJAX 请求示例
     * $.post(ajaxurl, {
     *     action: 'your-action',
     *     id: 123,
     *     sort_order: 10,
     *     nonce: nonce
     * });
     */
    protected function updateSort(): ?bool
    {
        $this->verifyNonce($this->page . '-nonce');

        // 获取ID和新的排序值
        $id = trim($this->input($this->model->getPrimaryKey()));
        $sortOrder = intval($this->input('sort_order'));

        if (!$id) {
            return $this->error(Lang::kit('无效的ID'));
        }

        // 检查记录是否存在
        $model = $this->model->find($id);
        if (!$model) {
            return $this->error(Lang::kit('记录不存在'));
        }

        // 确保排序值为非负数
        $sortOrder = max(0, $sortOrder);

        // 更新排序值
        $result = $this->model->where($this->model->getPrimaryKey(), $id)->update([
            'sort_order' => $sortOrder,
        ]);

        if (!$result) {
            return $this->error(Lang::kit('排序更新失败'));
        }

        return $this->success(null, Lang::kit('排序更新成功'));
    }

    /**
     * 设置控制器名称
     *
     * 根据当前控制器类名生成三种格式的控制器名称：
     * 1. controllerName: 移除 Controller 后缀的类名
     * 2. formatControllerName: 转换为小写中划线格式
     *
     * 转换规则示例：
     * - UserProfileController -> UserProfile -> user-profile
     * - ArticleTagController -> ArticleTag -> article-tag
     *
     * @since 1.0.0
     * @access protected
     *
     * @uses basename() 获取类名（不含命名空间）
     * @uses str_replace() 移除 Controller 后缀
     * @uses preg_replace() 驼峰转中划线格式
     * @uses strtolower() 转换为小写
     *
     * @example
     * // 原始类名: UserProfileController
     * $this->controllerName = 'UserProfile';
     * $this->formatControllerName = 'user-profile';
     *
     * // 原始类名: ArticleTagController
     * $this->controllerName = 'ArticleTag';
     * $this->formatControllerName = 'article-tag';
     *
     * @return void
     */
    protected function setControllerName(): void
    {
        // 获取基础类名（不含命名空间）
        $className = basename(static::class);

        // 移除 Controller 后缀
        $this->controllerName = str_replace('Controller', '', $className);

        // 转换为小写
        $this->formatControllerName = Str::kebab($this->controllerName);

    }

    /**
     * 生成前端 JavaScript 对象的名称
     * 将前缀从 kebab-case 或 snake_case 转换为 camelCase，并拼接控制器名和Admin后缀
     * 例如：kit-press -> kitPressModelsAdmin
     *
     * @return string 返回驼峰格式的脚本对象名称
     */
    protected function scriptObjectName() : string
    {
        // 获取前缀并转换格式
        $prefix = $this->plugin->getPrefix();
        // 移除可能存在的连字符和下划线
        $prefix = str_replace(['-', '_'], ' ', $prefix);
        // 将单词转为首字母大写
        $prefix = ucwords($prefix);
        // 移除空格并确保首字母小写
        $prefix = lcfirst(str_replace(' ', '', $prefix));

        // 返回格式: {camelCasePrefix}{controllerName}Admin
        // 例如: kit-press -> kitPressModelsAdmin
        return $prefix . $this->controllerName . 'Admin';
    }

    /**
     * 设置本地化脚本数据
     * 用于前端 AJAX 请求的配置数据
     *
     * @return array 包含 AJAX URL、操作名称和 nonce 的数组
     */
    protected function setL10n() : array
    {
        return [
            'ajaxurl' => \admin_url('admin-ajax.php'),
            'posturl' => \admin_url('admin-post.php'),
            'action_list' => $this->page . '-list',       // ajax
            'action_add' => $this->page . '-add',         // post
            'action_edit' => $this->page . '-edit',       // post
            'action_delete' => $this->page . '-delete',   // ajax
            'action_batch' => $this->page . '-batch',     // ajax
            'action_status' => $this->page . '-status',   // ajax
            'action_sort' => $this->page . '-sort',       // ajax
            'nonce' => \wp_create_nonce($this->page .'-nonce'),
            'perPage' => $this->config->get('app.features.per_page') ?? 10,
        ];
    }

    /**
     * 本地化脚本
     * 将 PHP 变量传递给 JavaScript
     */
    protected function localizeScript(): void
    {
        // 添加必要的数据
        \wp_localize_script(
            $this->page . '-common-script',
            $this->scriptObjectName() ,
            $this->setL10n()
        );
    }

    /**
     * 设置并加载 CSS 样式文件
     */
    protected function setupCss(): void
    {
        // 加载多个样式文件
        $styles = [
            $this->page . '-common' => 'backend/assets/component/common.css',
            $this->page  => 'backend/assets/css/'. $this->formatControllerName .'.css',
        ];

        $styles = array_merge($styles, $this->styles);

        foreach ($styles as $handle => $path) {
            // 检查是否为绝对路径（以 http:// 或 https:// 开头）
            if (!preg_match('/^https?:\/\//', $path)) {
                $path = $this->plugin->getRootUrl() . $path;
            }

            \wp_enqueue_style(
                $handle . '-style',
                $path,
                [],
                $this->config->get('app.version')
            );
        }
    }

    /**
     * 设置并加载 Vue.js 组件
     */
    protected function setupJsComponent(): void
    {
        // 先加载 Vue
        \wp_enqueue_script(
            'vue',
            $this->plugin->getRootUrl() . 'backend/assets/component/vue/vue.min.js',
            [],
            '2.6.14',
            true
        );

        // 加载工具
        \wp_enqueue_script(
            'utils',
            $this->plugin->getRootUrl() . 'backend/assets/component/utils.js',
            ['vue', 'jquery'],
            $this->config->get('app.version'),
            true
        );
    }

    /**
     * 设置并加载主要的 JavaScript 文件
     */
    protected function setupJs(): void
    {
        // 加载多个样式文件
        $scripts = [
            $this->page . '-common' => 'backend/assets/component/common.js',
            $this->page  => 'backend/assets/js/'. $this->formatControllerName .'.js',
        ];

        $scripts = array_merge($scripts, $this->scripts);

        foreach ($scripts as $handle => $path) {
            if (!preg_match('/^https?:\/\//', $path)) {
                $path = $this->plugin->getRootUrl() . $path;
            }

            // 设置依赖关系
            $deps = ['vue', 'jquery','utils'];
            // 如果不是公共脚本，添加对公共脚本的依赖
            if ($handle !==  $this->page . '-common') {
                $deps[] =  $this->page . '-common-script';
            }

            \wp_enqueue_script(
                $handle . '-script',
                $path,
                $deps,
                $this->config->get('app.version'),
                true
            );
        }
    }

    /**
     * 设置并加载所有前端资源
     * 包括 CSS、JavaScript 组件和主要 JavaScript 文件
     */
    protected function setupAssets(): void
    {
        $this->setupCss();
        $this->setupJsComponent();
        $this->setupJs();
        $this->localizeScript();
    }

    /**
     * 加载前台资源
     */
    public function enqueueAssets($hook): void
    {

        if (stripos($hook,  $this->page) === false) {
            return;
        }

        $this->setupAssets();
    }

}