<?php
namespace kitpress\core\interfaces;

if (!defined('ABSPATH')) {
	exit;
}

interface InitializableInterface {
	public function init();
}