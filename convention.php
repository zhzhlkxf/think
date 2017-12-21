<?php
/**
 * Created by PhpStorm.
 * User: zhzhl
 * Date: 2017/12/14
 * Time: 16:03
 */
return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------

    // 应用调试模式
    'app_debug'              => false,
    // 默认Host地址
    'app_host'               => '',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => '',
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT],

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => true,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 控制器类后缀
    'controller_suffix'      => false,
    // 操作方法后缀
    'action_suffix'          => '',
    // 应用类库后缀
    'class_suffix'           => false,
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // 表单ajax伪装变量
    'var_ajax'               => '_ajax',
    // 表单pjax伪装变量
    'var_pjax'               => '_pjax',

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认的空控制器名
    'empty_controller'       => 'Error',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',
    // 是否记录trace信息到日志
    'record_trace'           => false,
    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',
    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,

    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached(暂不支持)
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
        'httponly'       => true,
        'secure'         => false,
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],
];