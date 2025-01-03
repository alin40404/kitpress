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
        $this->rules = $rules;
        $this->validate();
    }

    /**
     * 执行验证
     *
     * @return bool 验证是否通过
     */
    protected function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $this->validateField($field, $ruleString);
        }
        return empty($this->errors);
    }

    /**
     * 验证单个字段
     *
     * @param string $field 字段名
     * @param string $ruleString 规则字符串
     */
    protected function validateField(string $field, string $ruleString): void
    {
        $rules = explode('|', $ruleString);
        $value = $this->data[$field] ?? null;

        // 如果字段不存在且不是必需的，跳过验证
        if (!isset($this->data[$field]) && !in_array('required', $rules)) {
            return;
        }

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }

    /**
     * 应用验证规则
     *
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $rule 规则
     */
    protected function applyRule(string $field, $value, string $rule): void
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
                $this->addError($field, $this->getErrorMessage($field, $ruleName, $parameter));
            }
        }
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
        if (is_string($value)) {
            return strlen($value) >= $parameter;
        }
        return is_numeric($value) && $value >= $parameter;
    }

    protected function validateMax($value, $parameter): bool
    {
        if (is_string($value)) {
            return strlen($value) <= $parameter;
        }
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
