<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}
class Http {

    /**
     * 默认配置
     * @var array
     */
    private static array $defaults = [
        'headers' => [],
        'timeout' => 30,
        'redirection' => 5,
        'httpversion' => '1.1',
        'sslverify' => false,
        'data_type' => 'json',    // json, form, multipart
        'debug' => false,
        'cookies' => [],
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'blocking' => true,
    ];

    /**
     * 发送 GET 请求
     * @param string $url 请求地址
     * @param array $params 查询参数
     * @param array $options 请求配置
     * @return array|WP_Error
     */
    public static function get(string $url, array $params = [], array $options = [])
    {
        // 处理 URL 参数
        if (!empty($params)) {
            $url = \add_query_arg($params, $url);
        }

        return self::request('GET', $url, null, $options);
    }

    /**
     * 发送 POST 请求
     * @param string $url 请求地址
     * @param array|string $data 请求数据
     * @param array $options 请求配置
     * @return array|WP_Error
     */
    public static function post(string $url, $data = [], array $options = [])
    {
        return self::request('POST', $url, $data, $options);
    }

    /**
     * 统一请求方法
     * @param string $method 请求方法
     * @param string $url 请求地址
     * @param array|string|null $data 请求数据
     * @param array $options 请求配置
     * @return array|WP_Error
     */
    private static function request(string $method, string $url, $data = null, array $options = [])
    {
        // 合并配置
        $options = array_merge(self::$defaults, $options);

        // 处理请求头
        $headers = array_merge([
            'Accept' => 'application/json',
            'User-Agent' => $options['user_agent']
        ], $options['headers']);

        // 处理请求体
        $body = self::prepareBody($data, $options['data_type']);
        if ($options['data_type'] === 'json' && $data !== null) {
            $headers['Content-Type'] = 'application/json';
        } elseif ($options['data_type'] === 'form' && $data !== null) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        // 构建请求参数
        $args = [
            'method' => strtoupper($method),
            'timeout' => $options['timeout'],
            'redirection' => $options['redirection'],
            'httpversion' => $options['httpversion'],
            'headers' => $headers,
            'cookies' => $options['cookies'],
            'sslverify' => $options['sslverify'],
            'blocking' => $options['blocking']
        ];

        // 添加请求体（如果有）
        if ($data !== null) {
            $args['body'] = $body;
        }

        // 调试模式
        if ($options['debug']) {
            self::debug('请求信息', [
                'url' => $url,
                'method' => $method,
                'args' => $args
            ]);
        }

        // 发送请求
        $response = \wp_remote_request($url, $args);

        // 调试响应
        if ($options['debug']) {
            self::debug('响应信息', $response);
        }

        return $response;
    }

    /**
     * 准备请求体
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    private static function prepareBody($data, string $type)
    {
        if ($data === null) {
            return null;
        }

        switch ($type) {
            case 'json':
                return is_array($data) ? json_encode($data) : $data;
            case 'form':
                return is_array($data) ? self::buildQuery($data) : $data;
            case 'multipart':
                return $data;
            default:
                return $data;
        }
    }

    /**
     * 调试输出
     * @param string $title
     * @param mixed $data
     */
    private static function debug(string $title, $data)
    {
        error_log(sprintf(
            "\n=== %s ===\n%s\n================\n",
            $title,
            print_r($data, true)
        ));
    }

    /**
     * 获取响应内容
     * @param array|WP_Error $response 响应结果
     * @param bool $assoc 是否转为关联数组
     * @return mixed 响应内容
     */
    public static function getBody($response, $assoc = true) {
        if (\is_wp_error($response)) {
            return $response;
        }
        $body = \wp_remote_retrieve_body($response);
        return json_decode($body, $assoc);
    }

    /**
     * 获取响应状态码
     * @param array|WP_Error $response 响应结果
     * @return int 状态码
     */
    public static function getCode($response) {
        if (\is_wp_error($response)) {
            return 500;
        }
        return \wp_remote_retrieve_response_code($response);
    }

    /**
     * 检查请求是否成功
     * @param array|WP_Error $response 响应结果
     * @return bool
     */
    public static function isSuccess($response): bool
    {
        if (\is_wp_error($response)) {
            return false;
        }
        return true;
    }

    /**
     * 构建查询字符串
     * @param array $params 参数数组
     * @return string
     */
    public static function buildQuery($params) {
        return http_build_query($params);
    }

    /**
     * 解析 URL
     * @param string $url URL
     * @return array
     */
    public static function parseUrl($url) {
        return parse_url($url);
    }
}