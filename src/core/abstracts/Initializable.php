<?php
namespace kitpress\core\abstracts;

use kitpress\core\interfaces\InitializableInterface;

if (!defined('ABSPATH')) {
	exit;
}
abstract class Initializable implements InitializableInterface {
	// 这个类可以包含所有可初始化类的通用逻辑
}