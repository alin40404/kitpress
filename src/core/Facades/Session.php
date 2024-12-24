<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session Facade
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static \kitpress\library\Session set(string $key, mixed $value)
 * @method static \kitpress\library\Session delete(string $key)
 * @method static bool has(string $key)
 * @method static \kitpress\library\Session clear()
 * @method static array all()
 * @method static void destroy()
 * @method static \kitpress\library\Session regenerate()
 * @method static void saveSession()
 * @method static void cleanExpiredSessions()
 *
 * @see \kitpress\library\Session
 */
class Session extends Facade {
    protected static function getFacadeAccessor() {
        return 'session';
    }
}