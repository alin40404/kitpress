<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}

class Http {
    /**
     * 发送 GET 请求
     * @param string $url 请求地址
     * @param array $params 请求参数
     * @param array $headers 请求头
     * @return array|WP_Error 响应结果或错误
     */
    public static function get($url, $params = [], $headers = []) {
        if (!empty($params)) {
            $url = \add_query_arg($params, $url);
        }

        return \wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 30
        ]);
    }

    /**
     * 发送 POST 请求
     * @param string $url 请求地址
     * @param array|string $data 请求数据
     * @param array $headers 请求头
     * @return array|WP_Error 响应结果或错误
     */
    public static function post($url, $data = [], $headers = []) {
        return \wp_remote_post($url, [
            'headers' => $headers,
            'body' => $data,
            'timeout' => 30
        ]);
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
    public static function isSuccess($response) {
        if (\is_wp_error($response)) {
            return false;
        }
        $code = self::getCode($response);
        return $code >= 200 && $code < 300;
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