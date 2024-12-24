<?php
namespace kitpress\core\interfaces;

if (!defined('ABSPATH')) {
    exit;
}

interface ContainerInterface {
    public function bind(string $id, $concrete, array $config = []);
    public function singleton(string $id, $concrete, array $config = []);
    public function resolve(string $id);
}