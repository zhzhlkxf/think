<?php
/**
 * Created by PhpStorm.
 * User: zhzhl
 * Date: 2017/12/14
 * Time: 16:19
 */

namespace think;

use think\exception\HttpException;
use think\exception\ClassNotFoundException;
use think\exception\HttpResponseException;

class App
{
    protected static $dispatch;
    /**
     * @var bool 是否初始化过
     */
    protected static $init = false;
    /**
     * @var bool 应用调试模式
     */
    public static $debug = false;
    /**
     * @var bool 应用类库后缀
     */
    public static $suffix = false;
    /**
     * @var string 应用类库命名空间
     */
    public static $namespace = 'app';
    protected static $file = [];

    /**
     * 执行应用程序
     */
    public static function run(Request $request = null)
    {
        is_null($request) && $request = Request::instance();

        try {
            $config = self::initCommon();
            $request->filter($config['default_filter']); //默认过虑

            // 获取应用调度信息
            $dispatch = self::$dispatch;
            if (empty($dispatch)) {
                // 进行URL路由检测
                $dispatch = self::routeCheck($request, $config);
            }
            // 记录当前调度信息
            $request->dispatch($dispatch);

            $data = self::exec($dispatch, $config);
        } catch (HttpResponseException $exception) {
            $data = $exception->getResponse();
        }

        // 清空类的实例化
        Loader::clearInstance();

        // 输出数据到客户端
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 默认自动识别响应输出类型
            $isAjax   = $request->isAjax();
            $type     = $isAjax ? Config::get('default_ajax_return') : Config::get('default_return_type');
            $response = Response::create($data, $type);
        } else {
            $response = Response::create();
        }

        return $response;
    }

    /**
     * URL路由检测（根据PATH_INFO)
     * @access public
     * @param  \think\Request $request
     * @param  array          $config
     * @return array
     * @throws \think\Exception
     */
    public static function routeCheck($request, array $config)
    {
        $path   = $request->path();
        $depr   = $config['pathinfo_depr'];
        $result = false;

        //检测是否开启路由
        //..

        if (false === $result) {
            // 路由无效 解析模块/控制器/操作/参数... 支持控制器自动搜索
            $result = Route::parseUrl($path, $depr);
        }
        return $result;
    }

    protected static function exec($dispatch, $config)
    {
        switch ($dispatch['type']) {
            case 'redirect':
                // 执行重定向跳转
                $data = Response::create($dispatch['url'], 'redirect')->code($dispatch['status']);
                break;
            case 'module':
                // 模块/控制器/操作
                $data = self::module($dispatch['module'], $config, isset($dispatch['convert']) ? $dispatch['convert'] : null);
                break;
            case 'controller':
                // 执行控制器操作
                $vars = array_merge(Request::instance()->param(), $dispatch['var']);
                $data = Loader::action($dispatch['controller'], $vars, $config['url_controller_layer'], $config['controller_suffix']);
                break;
            case 'method':
                // 执行回调方法
                $vars = array_merge(Request::instance()->param(), $dispatch['var']);
                $data = self::invokeMethod($dispatch['method'], $vars);
                break;
            case 'function':
                // 执行闭包
                $data = self::invokeFunction($dispatch['function']);
                break;
            case 'response':
                $data = $dispatch['response'];
                break;
            default:
                throw new \InvalidArgumentException('dispatch type not support');
        }
        return $data;
    }

    /**
     * 执行模块
     * @access public
     * @param array $result 模块/控制器/操作
     * @param array $config 配置参数
     * @param bool  $convert 是否自动转换控制器和操作名
     * @return mixed
     */
    public static function module($result, $config, $convert = null)
    {
        if (is_string($result)) {
            $result = explode('/', $result);
        }
        $request = Request::instance();

        // 单一模块部署
        $module = '';
        $request->module($module);

        // 是否自动转换控制器和操作名
        $convert = is_bool($convert) ? $convert : $config['url_convert'];
        // 获取控制器名
        $controller = strip_tags($result[0] ?: $config['default_controller']);
        $controller = $convert ? strtolower($controller) : $controller;

        // 获取操作名
        $actionName = strip_tags($result[1] ?: $config['default_action']);
        $actionName = $convert ? strtolower($actionName) : $actionName;

        // 设置当前请求的控制器、操作
        $request->controller(Loader::parseName($controller, 1))->action($actionName);

        try {
            $instance = Loader::controller($controller, $config['url_controller_layer'], $config['controller_suffix'], $config['empty_controller']);
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        // 获取当前操作名
        $action = $actionName . $config['action_suffix'];

        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$actionName];
        } else {
            // 操作不存在
            throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
        }

        return self::invokeMethod($call, $vars);
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param string|array $method 方法
     * @param array        $vars   变量
     * @return mixed
     */
    public static function invokeMethod($method, $vars = [])
    {
        if (is_array($method)) {
            $class   = is_object($method[0]) ? $method[0] : self::invokeClass($method[0]);
            $reflect = new \ReflectionMethod($class, $method[1]);
        } else {
            // 静态方法
            $reflect = new \ReflectionMethod($method);
        }
        $args = self::bindParams($reflect, $vars);

        return $reflect->invokeArgs(isset($class) ? $class : null, $args);
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string    $class 类名
     * @param array     $vars  变量
     * @return mixed
     */
    public static function invokeClass($class, $vars = [])
    {
        $reflect     = new \ReflectionClass($class);
        $constructor = $reflect->getConstructor();
        if ($constructor) {
            $args = self::bindParams($constructor, $vars);
        } else {
            $args = [];
        }
        return $reflect->newInstanceArgs($args);
    }

    /**
     * 绑定参数
     * @access private
     * @param \ReflectionMethod|\ReflectionFunction $reflect 反射类
     * @param array                                 $vars    变量
     * @return array
     */
    private static function bindParams($reflect, $vars = [])
    {
        if (empty($vars)) {
            // 自动获取请求变量
            if (Config::get('url_param_type')) {
                $vars = Request::instance()->route();
            } else {
                $vars = Request::instance()->param();
            }
        }
        $args = [];
        if ($reflect->getNumberOfParameters() > 0) {
            // 判断数组类型 数字数组时按顺序绑定参数
            reset($vars);
            $type   = key($vars) === 0 ? 1 : 0;
            $params = $reflect->getParameters();
            foreach ($params as $param) {
                $args[] = self::getParamValue($param, $vars, $type);
            }
        }
        return $args;
    }

    /**
     * 获取参数值
     * @access private
     * @param \ReflectionParameter  $param
     * @param array                 $vars    变量
     * @param string                $type
     * @return array
     */
    private static function getParamValue($param, &$vars, $type)
    {
        $name  = $param->getName();
        $class = $param->getClass();
        if ($class) {
            $className = $class->getName();
            $bind      = Request::instance()->$name;
            if ($bind instanceof $className) {
                $result = $bind;
            } else {
                if (method_exists($className, 'invoke')) {
                    $method = new \ReflectionMethod($className, 'invoke');
                    if ($method->isPublic() && $method->isStatic()) {
                        return $className::invoke(Request::instance());
                    }
                }
                $result = method_exists($className, 'instance') ? $className::instance() : new $className;
            }
        } elseif (1 == $type && !empty($vars)) {
            $result = array_shift($vars);
        } elseif (0 == $type && isset($vars[$name])) {
            $result = $vars[$name];
        } elseif ($param->isDefaultValueAvailable()) {
            $result = $param->getDefaultValue();
        } else {
            throw new \InvalidArgumentException('method param miss:' . $name);
        }
        return $result;
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|array|\Closure $function 函数或者闭包
     * @param array                 $vars     变量
     * @return mixed
     */
    public static function invokeFunction($function, $vars = [])
    {
        $reflect = new \ReflectionFunction($function);
        $args    = self::bindParams($reflect, $vars);

        return $reflect->invokeArgs($args);
    }

    /**
     * 初始化应用
     */
    public static function initCommon()
    {
        if (empty(self::$init)) {
            if (defined('APP_NAMESPACE')) {
                self::$namespace = APP_NAMESPACE;
            }
            Loader::addNamespace(self::$namespace . '\\', rtrim(APP_PATH, DS));
            // 初始化应用
            $config       = self::init();
            self::$suffix = $config['class_suffix'];
            self::$debug = Config::get('app_debug');

            // 加载额外文件
            if (!empty($config['extra_file_list'])) {
                foreach ($config['extra_file_list'] as $file) {
                    $file = strpos($file, '.') ? $file : APP_PATH . $file . EXT;
                    if (is_file($file) && !isset(self::$file[$file])) {
                        include $file;
                        self::$file[$file] = true;
                    }
                }
            }

            // 设置系统时区
            date_default_timezone_set($config['default_timezone']);
            self::$init = true;
        }

        return Config::get();
    }

    /**
     * 初始化配置
     */
    public static function init()
    {
        $path = APP_PATH;
        // 加载模块配置
        Config::load(CONF_PATH . 'config' . CONF_EXT);
        // 读取数据库配置文件
        $filename = CONF_PATH . 'database' . CONF_EXT;
        Config::load($filename, 'database');
        // 读取扩展配置文件
        if (is_dir(CONF_PATH . 'extra')) {
            $dir   = CONF_PATH . 'extra';
            $files = scandir($dir);
            foreach ($files as $file) {
                if ('.' . pathinfo($file, PATHINFO_EXTENSION) === CONF_EXT) {
                    $filename = $dir . DS . $file;
                    Config::load($filename, pathinfo($file, PATHINFO_FILENAME));
                }
            }
        }
        // 加载公共文件
        if (is_file($path . 'common' . EXT)) {
            include $path . 'common' . EXT;
        }

        return Config::get();
    }
}