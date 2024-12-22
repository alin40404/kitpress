<?php
namespace kitpress\utils;
if (!defined('ABSPATH')) {
    exit;
}
class Cron {
    /**
     * 初始化定时任务
     */
    public static function init() {
        // 注册自定义时间间隔
        add_filter('cron_schedules', [self::class, 'addCustomIntervals']);

        // 在插件激活时注册定时任务
        add_action('kitpress_plugin_activated', [self::class, 'scheduleTasks']);

        // 在插件停用时清理定时任务
        add_action('kitpress_plugin_deactivated', [self::class, 'clearTasks']);
    }

    /**
     * 验证任务配置
     */
    private static function validateTask($task) {
        return isset($task['hook']) &&
            isset($task['recurrence']) &&
            isset($task['callback']) &&
            is_callable($task['callback']);
    }

    /**
     * 注册所有定时任务
     */
    public static function scheduleTasks() {
        $tasks = Config::get('cron.tasks', []);

        foreach ($tasks as $period => $periodTasks) {
            foreach ($periodTasks as $taskKey => $task) {
                try {
                    if (!self::validateTask($task)) {
                        error_log("Invalid task configuration for {$taskKey}");
                        continue;
                    }

                    // 注册回调函数
                    add_action($task['hook'], function() use ($task) {
                        try {
                            call_user_func_array($task['callback'], $task['args']);
                        } catch (\Exception $e) {
                            error_log("Task execution failed: {$task['hook']} - " . $e->getMessage());
                        }
                    });

                    // 调度任务
                    if (!wp_next_scheduled($task['hook'], $task['args'])) {
                        wp_schedule_event(
                            time(),
                            $task['recurrence'],
                            $task['hook'],
                            $task['args']
                        );
                    }
                } catch (\Exception $e) {
                    error_log("Failed to schedule task {$taskKey}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * 添加自定义时间间隔
     */
    public static function addCustomIntervals($schedules) {
        $intervals = Config::get('cron.intervals', []);

        foreach ($intervals as $name => $interval) {
            if (!isset($schedules[$name])) {
                $schedules[$name] = [
                    'interval' => $interval['interval'],
                    'display' => $interval['display']
                ];
            }
        }

        return $schedules;
    }

    /**
     * 注册所有定时任务
     */
    public static function scheduleTasks() {
        $tasks = Config::get('cron.tasks', []);

        foreach ($tasks as $period => $periodTasks) {
            foreach ($periodTasks as $task) {
                if (!wp_next_scheduled($task['hook'], $task['args'])) {
                    wp_schedule_event(
                        time(),
                        $task['recurrence'],
                        $task['hook'],
                        $task['args']
                    );
                }
            }
        }
    }

    /**
     * 清理所有定时任务
     */
    public static function clearTasks() {
        $tasks = Config::get('cron.tasks', []);

        foreach ($tasks as $period => $periodTasks) {
            foreach ($periodTasks as $task) {
                $timestamp = wp_next_scheduled($task['hook'], $task['args']);
                if ($timestamp) {
                    wp_unschedule_event($timestamp, $task['hook'], $task['args']);
                }
            }
        }
    }

    /**
     * 重新安排特定任务
     */
    public static function rescheduleTask($taskKey, $period) {
        $tasks = Config::get('cron.tasks', []);
        if (isset($tasks[$period][$taskKey])) {
            $task = $tasks[$period][$taskKey];
            $timestamp = wp_next_scheduled($task['hook'], $task['args']);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $task['hook'], $task['args']);
            }
            wp_schedule_event(time(), $task['recurrence'], $task['hook'], $task['args']);
        }
    }

    /**
     * 获取任务状态
     */
    public static function getTaskStatus($taskKey, $period = null) {
        $tasks = Config::get('cron.tasks', []);

        if ($period && isset($tasks[$period][$taskKey])) {
            $task = $tasks[$period][$taskKey];
            $nextRun = wp_next_scheduled($task['hook'], $task['args']);

            return [
                'is_scheduled' => (bool)$nextRun,
                'next_run' => $nextRun ? get_date_from_gmt(date('Y-m-d H:i:s', $nextRun)) : null,
                'description' => $task['description'] ?? '',
                'recurrence' => $task['recurrence']
            ];
        }

        // 如果没有指定 period，搜索所有周期
        foreach ($tasks as $p => $periodTasks) {
            if (isset($periodTasks[$taskKey])) {
                return self::getTaskStatus($taskKey, $p);
            }
        }

        return null;
    }

    /**
     * 手动触发任务
     */
    public static function runTaskNow($taskKey, $period = null) {
        $tasks = Config::get('cron.tasks', []);

        try {
            if ($period && isset($tasks[$period][$taskKey])) {
                $task = $tasks[$period][$taskKey];
                if (self::validateTask($task)) {
                    return call_user_func_array($task['callback'], $task['args']);
                }
            } else {
                // 搜索所有周期
                foreach ($tasks as $p => $periodTasks) {
                    if (isset($periodTasks[$taskKey])) {
                        return self::runTaskNow($taskKey, $p);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to run task {$taskKey}: " . $e->getMessage());
            throw $e;
        }

        return false;
    }
}