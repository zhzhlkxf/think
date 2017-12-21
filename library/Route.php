<?php
/**
 * Created by PhpStorm.
 * User: zhzhl
 * Date: 2017/12/14
 * Time: 17:42
 */

namespace think;


class Route
{
    // 路由规则
    private static $rules = [
        'get'     => [],
        'post'    => [],
        'put'     => [],
        'delete'  => [],
        'patch'   => [],
        'head'    => [],
        'options' => [],
        '*'       => [],
        'alias'   => [],
        'domain'  => [],
        'pattern' => [],
        'name'    => [],
    ];

    /**
     * 解析模块的URL地址 [模块/控制器/操作?]参数1=值1&参数2=值2...
     * @access public
     * @param string    $url URL地址
     * @param string    $depr URL分隔符
     * @param bool      $autoSearch 是否自动深度搜索控制器
     * @return array
     */
    public static function parseUrl($url, $depr = '/', $autoSearch = false)
    {
        $url = str_replace($depr, '|', $url);
        list($path, $var) = self::parseUrlPath($url);
        $route = [null, null];
        if (isset($path)) {
            // 解析控制器
            $controller = !empty($path) ? array_shift($path) : null;
            // 解析操作
            $action = !empty($path) ? array_shift($path) : null;
            // 解析额外参数
            self::parseUrlParams(empty($path) ? '' : implode('|', $path));
            // 封装路由
            $route = [$controller, $action];
            // 检查地址是否被定义过路由
            $name  = strtolower(Loader::parseName($controller, 1) . '/' . $action);

            if (isset(self::$rules['name'][$name])) {
                throw new HttpException(404, 'invalid request:' . str_replace('|', $depr, $url));
            }
        }
        return ['type' => 'module', 'module' => $route];
    }

    /**
     * 解析URL的pathinfo参数和变量
     * @access private
     * @param string    $url URL地址
     * @return array
     */
    private static function parseUrlPath($url)
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');
        $var = [];
        if (false !== strpos($url, '?')) {
            // [模块/控制器/操作?]参数1=值1&参数2=值2...
            $info = parse_url($url);
            $path = explode('/', $info['path']);
            parse_str($info['query'], $var);
        } elseif (strpos($url, '/')) {
            // [模块/控制器/操作]
            $path = explode('/', $url);
        } else {
            $path = [$url];
        }
        return [$path, $var];
    }

    /**
     * 解析URL地址中的参数Request对象
     * @access private
     * @param string    $url 路由规则
     * @param array     $var 变量
     * @return void
     */
    private static function parseUrlParams($url, &$var = [])
    {
        if ($url) {
            if (Config::get('url_param_type')) {
                $var += explode('|', $url);
            } else {
                preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                    $var[$match[1]] = strip_tags($match[2]);
                }, $url);
            }
        }
        // 设置当前请求的参数
        Request::instance()->route($var);
    }
}