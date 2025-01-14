<?php
namespace kitpress\library;

use kitpress\utils\Lang;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 数据验证器
 *
 * 提供数据验证功能，支持多种验证规则和自定义验证
 *
 * @since 1.0.0
 * @package kitpress\library
 */
class Validator
{
    /**
     * 验证错误信息
     * @var array
     */
    protected array $errors = [];

    /**
     * 验证数据
     * @var array
     */
    protected array $data;

    /**
     * 验证规则
     * @var array
     */
    protected array $rules = [];

    /**
     * 构造函数
     *
     * @param array $data 待验证数据
     * @param array $rules 验证规则
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $this->parseRules($rules);
        $this->validate();
    }

    /**
     * 执行验证
     *
     * @example 基础用法：
     * $rules = [
     *     'username' => 'required|string|min:3',
     *     'email' => 'required|email'
     * ];
     *
     * @example 使用通用错误消息：
     * $rules = [
     *     'username' => [
     *         'rules' => 'required|string|min:3',
     *         'message' => '用户名格式不正确'
     *     ]
     * ];
     *
     * @example 使用独立规则错误消息：
     * $rules = [
     *     'username' => [
     *         'rules' => 'required|string|min:3',
     *         'messages' => [
     *             'required' => '用户名不能为空',
     *             'string' => '用户名必须是字符串',
     *             'min' => '用户名最少需要3个字符'
     *         ]
     *     ]
     * ];
     *
     * @example 完整验证规则示例：
     * $rules = [
     *     'code' => [
     *         'rules' => 'required|string',
     *         'messages' => [
     *             'required' => '语言代码不能为空',
     *             'string' => '语言代码必须是字符串格式'
     *         ]
     *     ],
     *     'name' => [
     *         'rules' => 'required|string',
     *         'message' => '语言名称格式不正确'
     *     ],
     *     'native_name' => 'required|string',
     *     'sort_order' => [
     *         'rules' => 'required|integer',
     *         'messages' => [
     *             'required' => '排序不能为空',
     *             'integer' => '排序必须是整数'
     *         ]
     *     ],
     *     'is_active' => [
     *         'rules' => 'required|boolean',
     *         'message' => '状态值不正确'
     *     ]
     * ];
     *
     * $data = [
     *     'code' => 'zh_CN',
     *     'name' => '简体中文',
     *     'native_name' => '简体中文',
     *     'sort_order' => 1,
     *     'is_active' => true
     * ];
     *
     * $validator = new Validator($data, $rules);
     * if ($validator->fails()) {
     *     $errors = $validator->errors();
     * }
     *
     * @return bool 验证是否通过
     */
    protected function validate(): bool
    {
        foreach ($this->rules as $field => $ruleConfig) {
            if (!isset($this->data[$field]) && !$this->isFieldRequired($ruleConfig['rules'])) {
                continue;
            }
            $this->validateField($field, $ruleConfig);
        }
        return empty($this->errors);
    }

    /**
     * 检查字段是否必需
     *
     * @param string $rules 规则字符串
     * @return bool
     */
    protected function isFieldRequired(string $rules): bool
    {
        return strpos($rules, 'required') !== false;
    }

    /**
     * 解析规则数组
     *
     * @param array $rules 原始规则数组
     * @return array 处理后的规则数组
     */
    protected function parseRules(array $rules): array
    {
        $parsed = [];
        foreach ($rules as $field => $rule) {
            if (is_array($rule)) {
                // 验证规则数组格式
                if (!isset($rule['rules'])) {
                    throw new \InvalidArgumentException("验证规则 '{$field}' 缺少 'rules' 键");
                }
                if (!is_string($rule['rules'])) {
                    throw new \InvalidArgumentException("字段 '{$field}' 的 'rules' 必须是字符串");
                }

                // 验证 message 和 messages 字段
                if (isset($rule['message']) && !is_string($rule['message'])) {
                    throw new \InvalidArgumentException("字段 '{$field}' 的 'message' 必须是字符串");
                }
                if (isset($rule['messages']) && !is_array($rule['messages'])) {
                    throw new \InvalidArgumentException("字段 '{$field}' 的 'messages' 必须是数组");
                }

                $parsed[$field] = [
                    'rules' => $rule['rules'],
                    'messages' => $this->parseMessages($rule)
                ];
            } else {
                $parsed[$field] = [
                    'rules' => $rule,
                    'messages' => []
                ];
            }
        }
        return $parsed;
    }

    /**
     * 解析错误消息
     *
     * @param array $rule 规则配置
     * @return array 处理后的消息数组
     */
    protected function parseMessages(array $rule): array
    {
        $messages = [];

        // 处理单个通用消息
        if (isset($rule['message']) && is_string($rule['message'])) {
            return ['default' => $rule['message']];
        }

        // 处理针对具体规则的消息
        if (isset($rule['messages']) && is_array($rule['messages'])) {
            return $rule['messages'];
        }

        return $messages;
    }

    /**
     * 验证单个字段
     *
     * @param string $field 字段名
     * @param array $ruleData 规则数据
     */
    protected function validateField(string $field, array $ruleData): void
    {
        $rules = explode('|', $ruleData['rules']);
        $value = $this->data[$field] ?? null;

        // 如果字段不存在且不是必需的，跳过验证
        if (!isset($this->data[$field]) && !in_array('required', $rules)) {
            return;
        }

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule, $ruleData['messages']);
        }
    }

    /**
     * 应用验证规则
     *
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $rule 规则
     * @param array $messages 错误消息数组
     */
    protected function applyRule(string $field, $value, string $rule, array $messages = []): void
    {
        // 解析规则和参数
        if (strpos($rule, ':') !== false) {
            [$ruleName, $parameter] = explode(':', $rule);
        } else {
            $ruleName = $rule;
            $parameter = null;
        }

        $method = 'validate' . ucfirst($ruleName);
        if (method_exists($this, $method)) {
            $result = $this->$method($value, $parameter);
            if (!$result) {
                $message = $this->getCustomMessage($ruleName, $messages, $field, $parameter);
                $this->addError($field, $message);
            }
        }
    }

    /**
     * 获取自定义错误消息
     *
     * @param string $ruleName 规则名称
     * @param array $messages 自定义消息数组
     * @param string $field 字段名
     * @param string|null $parameter 参数
     * @return string
     */
    protected function getCustomMessage(string $ruleName, array $messages, string $field, ?string $parameter): string
    {
        // 优先使用针对具体规则的消息
        if (isset($messages[$ruleName])) {
            return $messages[$ruleName];
        }

        // 其次使用默认消息
        if (isset($messages['default'])) {
            return $messages['default'];
        }

        // 最后使用系统默认消息模板
        return $this->getErrorMessage($field, $ruleName, $parameter);
    }

    /**
     * 添加错误信息
     *
     * @param string $field 字段名
     * @param string $message 错误信息
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * 获取错误信息
     *
     * @param string $field 字段名
     * @param string $rule 规则名
     * @param string|null $parameter 参数
     * @return string
     */
    protected function getErrorMessage(string $field, string $rule, ?string $parameter = null): string
    {
        $messages = [
            'required' => '%s 字段是必需的',
            'string' => '%s 必须是字符串',
            'integer' => '%s 必须是整数',
            'boolean' => '%s 必须是布尔值',
            'min' => '%s 不能小于 %s',
            'max' => '%s 不能大于 %s',
            'email' => '%s 必须是有效的邮箱地址',
            'url' => '%s 必须是有效的URL',
            'date' => '%s 必须是有效的日期',
            'numeric' => '%s 必须是数字'
        ];

        $message = $messages[$rule] ?? '%s 验证失败';
        return sprintf(Lang::kit($message), $field, $parameter);
    }

    /**
     * 验证是否失败
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * 获取错误信息
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    // 验证规则方法
    protected function validateRequired($value): bool
    {
        return !empty($value) || $value === '0' || $value === 0;
    }

    protected function validateString($value): bool
    {
        return is_string($value);
    }

    protected function validateInteger($value): bool
    {
        return is_numeric($value) && (int)$value == $value;
    }

    protected function validateBoolean($value): bool
    {
        return is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1';
    }

    protected function validateMin($value, $parameter): bool
    {
        return is_numeric($value) && $value >= $parameter;
    }

    protected function validateMax($value, $parameter): bool
    {
        return is_numeric($value) && $value <= $parameter;
    }

    protected function validateEmail($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function validateUrl($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateDate($value): bool
    {
        return strtotime($value) !== false;
    }

    protected function validateNumeric($value): bool
    {
        return is_numeric($value);
    }
}
