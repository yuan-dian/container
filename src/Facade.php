<?php
// +----------------------------------------------------------------------
// |
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2024/12/26
// +----------------------------------------------------------------------
namespace yuandian\Container;

/**
 * Facade管理类
 */
class Facade
{
    /**
     * 始终创建新的对象实例
     * @var bool
     */
    protected static bool $alwaysNewInstance = false;

    /**
     * 创建Facade实例
     * @static
     * @access protected
     * @param string $class 类名或标识
     * @param array $args 变量
     * @param bool $newInstance 是否每次创建新的实例
     * @return object|null
     * @throws \ReflectionException
     */
    protected static function createFacade(string $class = '', array $args = [], bool $newInstance = false): ?object
    {
        $class = $class ?: static::class;

        $facadeClass = static::getFacadeClass();

        if ($facadeClass) {
            $class = $facadeClass;
        }

        if (static::$alwaysNewInstance) {
            $newInstance = true;
        }

        return Container::getInstance()->make($class, $args, $newInstance);
    }

    /**
     * 获取当前Facade对应类名
     * @access protected
     * @return string
     */
    protected static function getFacadeClass() : string
    {
        return '';
    }

    /**
     * 带参数实例化当前Facade类
     * @access public
     * @param mixed ...$args
     * @return object|null
     * @throws \ReflectionException
     */
    public static function instance(...$args): ?object
    {
        if (__CLASS__ != static::class) {
            return self::createFacade('', $args);
        }
        return null;
    }

    /**
     * 调用类的实例
     * @access public
     * @param string $class 类名或者标识
     * @param array $args 变量
     * @param bool $newInstance 是否每次创建新的实例
     * @return object|null
     * @throws \ReflectionException
     */
    public static function make(string $class, array $args = [], bool $newInstance = false): ?object
    {
        if (__CLASS__ != static::class) {
            return self::__callStatic('make', func_get_args());
        }

        return self::createFacade($class, $args, $newInstance);
    }

    /**
     * 调用实际类的方法
     * @param $method
     * @param $params
     * @return mixed
     * @throws \ReflectionException
     * @date 2024/12/26 16:53
     * @author 原点 467490186@qq.com
     */
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([static::createFacade(), $method], $params);
    }
}
