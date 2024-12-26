<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log Facade
 *
 * @see \kitpress\library\Log
 *
 * @method static void debug(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void emergency(string $message, array $context = [])
 * @method static array getLogFiles()
 * @method static void cleanOldLogs(int $days = 30)
 */
class Log extends Facade {
    protected static function getFacadeAccessor(): string
    {
        return 'log';
    }
}