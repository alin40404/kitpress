<?php
namespace kitpress\core\exceptions;

if (!defined('ABSPATH')) {
    exit;
}

class KitpressException extends \Exception {
    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}