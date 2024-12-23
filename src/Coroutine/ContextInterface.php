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
    public static function set(string $id, mixed $value, $coroutineId = null): mixed;


    public static function get(string $id, mixed $default = null, $coroutineId = null): mixed;

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

    public static function getContext($coroutineId = null);


    public static function getCurrent();

    public static function run(callable $callable, mixed ...$data);
}