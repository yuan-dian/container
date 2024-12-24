<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2024/12/20
// +----------------------------------------------------------------------

namespace yuandian\Container\Coroutine;

use Closure;

interface ContextInterface
{
    /**
     * 设置上下文
     * @param string $id
     * @param mixed $value
     * @param $coroutineId
     * @return mixed
     * @date 2024/12/24 09:29
     * @author 原点 467490186@qq.com
     */
    public static function set(string $id, mixed $value, $coroutineId = null): mixed;


    /**
     * 获取上下文
     * @param string $id
     * @param mixed|null $default
     * @param $coroutineId
     * @return mixed
     * @date 2024/12/24 09:29
     * @author 原点 467490186@qq.com
     */
    public static function get(string $id, mixed $default = null, $coroutineId = null): mixed;

    /**
     * 判断上下文是否存在
     * @param string $id
     * @param $coroutineId
     * @return bool
     * @date 2024/12/24 09:29
     * @author 原点 467490186@qq.com
     */
    public static function has(string $id, $coroutineId = null): bool;

    /**
     * Release the context when you are not in coroutine environment.
     */
    public static function destroy(string $id, $coroutineId = null): void;

    /**
     * Copy the context from a coroutine to current coroutine.
     * This method will delete the origin values in current coroutine.
     */
    public static function copy($fromCoroutineId, array $keys = []): void;

    /**
     * Retrieve the value and override it by closure.
     */
    public static function override(string $id, Closure $closure, $coroutineId = null): mixed;

    /**
     * Retrieve the value and store it if not exists.
     */
    public static function getOrSet(string $id, mixed $value, $coroutineId = null): mixed;

    /**
     * @param $coroutineId
     * @return mixed
     * @date 2024/12/24 09:30
     * @author 原点 467490186@qq.com
     */
    public static function getContext($coroutineId = null);

    /**
     * @return mixed
     * @date 2024/12/24 09:29
     * @author 原点 467490186@qq.com
     */
    public static function getCurrent();

    /**
     * 创建协程并继承父上下文
     * @param callable $callable
     * @param mixed ...$data
     * @return mixed
     * @date 2024/12/24 09:28
     * @author 原点 467490186@qq.com
     */
    public static function run(callable $callable, mixed ...$data): mixed;
}