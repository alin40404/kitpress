<?php
namespace kitpress\core\traits;

use kitpress\Kitpress;
use kitpress\library\Config;

trait ViewTrait {
    /** @var string 视图文件的基础路径 */
    protected string $viewPath = '';

    /** @var string 布局模板名称 */
    protected string $layout = 'default';

    /** @var array<string, mixed> 视图数据数组 */
    protected array $viewData = [];

    /**
     * 渲染视图
     * @param string $view 视图名称
     * @param array $data 视图数据
     * @param bool $return 是否返回而不是输出
     * @return string|void
     */
    protected function render($view = null, $data = [], $return = false) {
        // 获取调用类的反射信息
        $calledClass = get_called_class();
        $reflection = new \ReflectionClass($calledClass);
        $className = strtolower($reflection->getShortName());

	    // 如果视图为空，获取当前方法名
	    if (empty($view)) {
		    $view = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
	    }

        // 如果视图路径包含 '/'，说明是完整路径，否则使用类名构建路径
        if (strpos($view, '/') === false) {
            $baseName = str_replace('controller', '', $className);
            $view = $baseName . '/' . $view;
        }

        $this->viewData = array_merge($this->viewData, $data);
        $viewFile = $this->getViewFile($view);

        if (!file_exists($viewFile)) {
            wp_die("View file not found: {$view}");
        }

        if ($return) {
            ob_start();
            $this->renderView($viewFile);
            return ob_get_clean();
        }

        $this->renderView($viewFile);
    }

    /**
     * 获取视图文件路径
     * @param string $view
     * @return string
     */
    protected function getViewFile($view) {
        return Kitpress::getRootPath() . $this->viewPath . '/' . $view . '.php';
    }

    /**
     * 渲染视图文件
     * @param string $viewFile
     */
    protected function renderView($viewFile) {
        extract($this->viewData);
        include $viewFile;
    }

    /**
     * 设置布局
     * @param string $layout
     */
    protected function setLayout($layout) {
        $this->layout = $layout;
    }

    /**
     * 分配变量到视图
     * @param string|array $key
     * @param mixed $value
     */
    protected function assign($key, $value = null) {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }
    }
}