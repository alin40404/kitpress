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
}