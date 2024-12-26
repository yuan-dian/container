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

declare (strict_types=1);

namespace yuandian\Container\Coroutine;

use Closure;
use Swow\Context\CoroutineContext;
use Swow\Coroutine;

class SwowContext implements ContextInterface
{

    public static function set(string $id, mixed $value, $coroutineId = null): mixed
    {
        SwowContext::getContext($coroutineId)[$id] = $value;
        return $value;
    }

    public static function get(string $id, mixed $default = null, $coroutineId = null): mixed
    {
        return SwowContext::getContext($coroutineId)[$id] ?? $default;
    }

    public static function has(string $id, $coroutineId = null): bool
    {
        return isset(SwowContext::getContext($coroutineId)[$id]);
    }

    /**
     * Release the context when you are not in coroutine environment.
     */
    public static function destroy(string $id, $coroutineId = null): void
    {
        unset(SwowContext::getContext($coroutineId)[$id]);
    }

    /**
     * Copy the context from a coroutine to current coroutine.
     * This method will delete the origin values in current coroutine.
     */
    public static function copy($fromCoroutineId, array $keys = []): void
    {
        if (!$fromCoroutineId instanceof Coroutine) {
            throw new \TypeError('fromCoroutine 参数应该是Coroutine类型');
        }
        $from = SwowContext::getContext($fromCoroutineId);

        $current = SwowContext::getContext();

        if ($keys) {
            $map = array_intersect_key($from->getArrayCopy(), array_flip($keys));
        } else {
            $map = $from->getArrayCopy();
        }

        $current->exchangeArray($map);
    }

    /**
     * Retrieve the value and override it by closure.
     */
    public static function override(string $id, Closure $closure, $coroutineId = null): mixed
    {
        $value = null;

        if (self::has($id, $coroutineId)) {
            $value = self::get($id, null, $coroutineId);
        }

        $value = $closure($value);

        self::set($id, $value, $coroutineId);

        return $value;
    }

    /**
     * Retrieve the value and store it if not exists.
     */
    public static function getOrSet(string $id, mixed $value, $coroutineId = null): mixed
    {
        if (!self::has($id, $coroutineId)) {
            return self::set($id, self::value($value), $coroutineId);
        }

        return self::get($id, null, $coroutineId);
    }

    public static function getContext($coroutineId = null)
    {
        return CoroutineContext::get($coroutineId);
    }

    public static function getCurrent(): Coroutine
    {
        return Coroutine::getCurrent();
    }

    private static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    public static function run(callable $callable, mixed ...$data)
    {
        $pid = SwowContext::getCurrent();
        return Coroutine::run(function () use ($callable, $pid, $data) {
            // 继承父上下文标记
            SwowContext::copy($pid);

            // 执行子协程逻辑
            $callable($data);
        });
    }
}