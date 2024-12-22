<?php
namespace kitpress\utils;

use kitpress\library\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 计划任务管理类
 */
class Cron {
    /**
     * 初始化定时任务
     */
    public static function init() {
        // 注册自定义时间间隔
        \add_filter('cron_schedules', [self::class, 'addCustomIntervals']);

        // 注册所有计划任务
        self::registerTasks();
    }

    /**
     * 验证任务配置
     * @param array $task 任务配置
     * @return bool
     */
    private static function validateTask($task) {
        // 检查必要字段
        if (!isset($task['recurrence'], $task['callback'])) {
            return false;
        }

        // 验证回调可调用性
        if (is_array($task['callback'])) {
            // 检查数组格式回调
            if (count($task['callback']) !== 2) {
                return false;
            }
            // 如果第一个元素是字符串，假定是静态方法调用
            if (is_string($task['callback'][0])) {
                return is_callable($task['callback']);
            }
            // 如果第一个元素是对象，检查方法是否存在
            return method_exists($task['callback'][0], $task['callback'][1]);
        }

        return is_callable($task['callback']);
    }

    /**
     * 生成任务钩子名称
     * @param string $taskKey 任务键名
     * @return string
     */
    private static function generateHookName($taskKey) {
        return KITPRESS_NAME . '_' . Helper::key() . '_' . $taskKey;
    }

    /**
     * 注册所有计划任务
     */
    private static function registerTasks() {
        $tasks = Config::get('cron.tasks', []);

        foreach ($tasks as $taskKey => $task) {
            // 未开启 session
            if ($taskKey === 'session_cleanup' && Config::get('app.session.enabled') == false ) {
                continue;
            }
            if (!self::validateTask($task)) {
                Log::error("Invalid task configuration for {$taskKey}");
                continue;
            }

            $hookName = self::generateHookName($taskKey);

            // 注册回调
            \add_action($hookName, function() use ($task) {
                try {
                    if (is_array($task['callback'])) {
                        // 处理 [类,方法] 格式
                        if (is_string($task['callback'][0])) {
                            // 静态方法调用
                            call_user_func_array($task['callback'], $task['args'] ?? []);
                        } else {
                            // 实例方法调用
                            $instance = $task['callback'][0];
                            $method = $task['callback'][1];
                            call_user_func_array([$instance, $method], $task['args'] ?? []);
                        }
                    } else {
                        // 处理字符串格式的回调
                        call_user_func_array($task['callback'], $task['args'] ?? []);
                    }
                } catch (\Exception $e) {
                    Log::error("Task execution failed: {$hookName} - " . $e->getMessage());
                }
            });

            // 调度任务
            if (!\wp_next_scheduled($hookName)) {
                \wp_schedule_event(time(), $task['recurrence'], $hookName);
            }
        }
    }

    /**
     * 添加自定义时间间隔
     * @param array $schedules 现有的计划任务间隔
     * @return array
     */
    public static function addCustomIntervals($schedules) {
        $intervals = Config::get('cron.intervals', []);

        foreach ($intervals as $name => $interval) {
            if (!isset($schedules[$name])) {
                $schedules[$name] = [
                    'interval' => $interval['interval'] * MINUTE_IN_SECONDS,
                    'display' => Lang::kit($interval['display'])
                ];
            }
        }

        return $schedules;
    }

    /**
     * 清理所有计划任务
     */
    public static function deactivate() {
        $tasks = Config::get('cron.tasks', []);

        foreach ($tasks as $taskKey => $task) {
            $hookName = self::generateHookName($taskKey);
            $timestamp = \wp_next_scheduled($hookName);
            if ($timestamp) {
                \wp_unschedule_event($timestamp, $hookName);
            }
        }
    }

    /**
     * 手动执行任务
     * @param string $taskKey 任务键名
     * @return bool|mixed
     * @throws \Exception
     */
    public static function runTask($taskKey) {
        $tasks = Config::get('cron.tasks', []);

        if (!isset($tasks[$taskKey])) {
            throw new \Exception("Task not found: {$taskKey}");
        }

        $task = $tasks[$taskKey];
        if (!self::validateTask($task)) {
            throw new \Exception("Invalid task configuration: {$taskKey}");
        }

        try {
            if (is_array($task['callback'])) {
                return call_user_func_array($task['callback'], $task['args'] ?? []);
            }
            return call_user_func_array($task['callback'], $task['args'] ?? []);
        } catch (\Exception $e) {
            Log::error("Failed to run task {$taskKey}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取任务状态
     * @param string $taskKey 任务键名
     * @return array|null
     */
    public static function getTaskStatus($taskKey) {
        $tasks = Config::get('cron.tasks', []);

        if (!isset($tasks[$taskKey])) {
            return null;
        }

        $task = $tasks[$taskKey];
        $hookName = self::generateHookName($taskKey);
        $nextRun = \wp_next_scheduled($hookName);

        return [
            'is_scheduled' => (bool)$nextRun,
            'next_run' => $nextRun ? \get_date_from_gmt(date('Y-m-d H:i:s', $nextRun)) : null,
            'description' => $task['description'] ?? '',
            'recurrence' => $task['recurrence']
        ];
    }
}