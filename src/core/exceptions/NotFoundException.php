<?php
namespace kitpress\core\exceptions;

if (!defined('ABSPATH')) {
    exit;
}

class NotFoundException extends KitpressException {
    /**
     * @param string $type 资源类型（如：service, config, plugin 等）
     * @param string $name 资源名称
     * @param string $additional 额外信息
     */
    public function __construct(
        string $type,
        string $name,
        string $additional = "",
        int $code = 0,
        \Throwable $previous = null
    ) {
        $message = sprintf('%s "%s" not found', $type, $name);
        if ($additional) {
            $message .= ': ' . $additional;
        }

        parent::__construct($message, $code, $previous);
    }
}