<?php
/**
 * Created by PhpStorm.
 * User: zhzhl
 * Date: 2017/12/14
 * Time: 11:11
 */

namespace think;


class Loader
{
    protected static $instance = [];

    private static $fallbackDirsPsr4  = [];
    private static $prefixLengthsPsr4 = [];
    private static $prefixDirsPsr4    = [];

    /**
     * 自动加载
     * @param $class
     */
    public static function autoload($class)
    {
        if ($file = self::findFile($class)) {
            // Win环境严格区分大小写
            if (IS_WIN && pathinfo($file, PATHINFO_FILENAME) != pathinfo(realpath($file), PATHINFO_FILENAME)) {
                return false;
            }

            __include_file($file);
            return true;
        }
    }

    /**
     * 注册自动加载机制
     * @param string $autoload
     */
    public static function register($autoload = '')
    {
        // 注册系统自动加载
        spl_autoload_register($autoload ?: 'think\\Loader::autoload', true, true);

        self::addNamespace('think\\', rtrim(CORE_PATH, DS), true);
    }

    /**
     * 注册命名空间
     */
    public static function addNamespace($namespace, $paths, $prepend = false)
    {
        if (!$namespace) { //注册root目录的命名空间
            if ($prepend) {
                self::$fallbackDirsPsr4 = array_merge(
                    (array) $paths,
                    self::$fallbackDirsPsr4
                );
            } else {
                self::$fallbackDirsPsr4 = array_merge(
                    self::$fallbackDirsPsr4,
                    (array) $paths
                );
            }
        } elseif (!isset(self::$prefixDirsPsr4[$namespace])) { //注册新目录的命名空间
            $length = strlen($namespace);
            if ('\\' !== $namespace[$length - 1]) {
                throw new \Exception("A non-empty PSR-4 prefix must end with a namespace separator.");
            }
            self::$prefixLengthsPsr4[$namespace[0]][$namespace] = $length;
            self::$prefixDirsPsr4[$namespace] = (array) $paths;
        } elseif ($prepend) { // 插入已注册的命名空间.
            self::$prefixDirsPsr4[$namespace] = array_merge(
                (array) $paths,
                self::$prefixDirsPsr4[$namespace]
            );
        } else { // 添加到已注册的命名空间.
            self::$prefixDirsPsr4[$namespace] = array_merge(
                self::$prefixDirsPsr4[$namespace],
                (array) $paths
            );
        }
    }

    /**
     * 查找文件
     * @param $class
     */
    private static function findFile($class)
    {
        $logicalPathPsr4 = strtr($class, '\\', DS) . EXT;
        $first = $class[0];
        if (isset(self::$prefixLengthsPsr4[$first])) {
            foreach (self::$prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach (self::$prefixDirsPsr4[$prefix] as $dir) {
                        if (is_file($file = $dir . DS . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-0
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                . strtr(substr($logicalPathPsr4, $pos + 1), '_', DS);
        } else {
            // PEAR-like class name
            $logicalPathPsr0 = strtr($class, '_', DS) . EXT;
        }
        if (is_file($logicalPathPsr0)) return $logicalPathPsr0;

        return false;
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string  $name 字符串
     * @param integer $type 转换类型
     * @param bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    /**
     * 实例化（分层）控制器 格式：[模块名/]控制器名
     * @param string $name         资源地址
     * @param string $layer        控制层名称
     * @param bool   $appendSuffix 是否添加类名后缀
     * @param string $empty        空控制器名称
     * @return object
     * @throws ClassNotFoundException
     */
    public static function controller($name, $layer = 'controller', $appendSuffix = false, $empty = '')
    {
        $class = self::parseClass($layer, $name, $appendSuffix);
        if (class_exists($class)) {
            return App::invokeClass($class);
        } elseif ($empty && class_exists($emptyClass = self::parseClass($layer, $empty, $appendSuffix))) {
            return new $emptyClass(Request::instance());
        } else {
            throw new ClassNotFoundException('class not exists:' . $class, $class);
        }
    }

    /**
     * 解析应用类的类名
     * @param string $module 模块名
     * @param string $layer  层名 controller model ...
     * @param string $name   类名
     * @param bool   $appendSuffix
     * @return string
     */
    public static function parseClass($layer, $name, $appendSuffix = false)
    {
        $name  = str_replace(['/', '.'], '\\', $name);
        $array = explode('\\', $name);
        $class = array_pop($array) . (App::$suffix || $appendSuffix ? ucfirst($layer) : '');
        $path  = $array ? implode('\\', $array) . '\\' : '';
        return App::$namespace . '\\' . $layer . '\\' . $path . $class;
    }

    /**
     * 远程调用模块的操作方法 参数格式 [模块/控制器/]操作
     * @param string       $url          调用地址
     * @param string|array $vars         调用参数 支持字符串和数组
     * @param string       $layer        要调用的控制层名称
     * @param bool         $appendSuffix 是否添加类名后缀
     * @return mixed
     */
    public static function action($url, $vars = [], $layer = 'controller', $appendSuffix = false)
    {
        $info   = pathinfo($url);
        $action = $info['basename'];
        $module = '.' != $info['dirname'] ? $info['dirname'] : Request::instance()->controller();
        $class  = self::controller($module, $layer, $appendSuffix);
        if ($class) {
            if (is_scalar($vars)) {
                if (strpos($vars, '=')) {
                    parse_str($vars, $vars);
                } else {
                    $vars = [$vars];
                }
            }
            return App::invokeMethod([$class, $action . Config::get('action_suffix')], $vars);
        }
    }

    /**
     * 初始化类的实例
     * @return void
     */
    public static function clearInstance()
    {
        self::$instance = [];
    }
}

/**
 * 作用范围隔离
 *
 * @param $file
 * @return mixed
 */
function __include_file($file)
{
    return include $file;
}