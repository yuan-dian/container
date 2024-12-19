<?php

namespace yuandian\Container;

use Closure;
use Swow\Context\CoroutineContext;
use Swow\Coroutine;

class Context
{
    public static function set(string $id, mixed $value, ?Coroutine $coroutine = null): mixed
    {
        Context::getContext($coroutine)[$id] = $value;
        return $value;
    }

    public static function get(string $id, mixed $default = null, ?Coroutine $coroutine = null): mixed
    {
        return Context::getContext($coroutine)[$id] ?? $default;
    }

    public static function has(string $id, ?Coroutine $coroutine = null): bool
    {
        return isset(Context::getContext($coroutine)[$id]);
    }

    /**
     * Release the context when you are not in coroutine environment.
     */
    public static function destroy(string $id, ?Coroutine $coroutine = null): void
    {
        unset(Context::getContext($coroutine)[$id]);
    }

    /**
     * Copy the context from a coroutine to current coroutine.
     * This method will delete the origin values in current coroutine.
     */
    public static function copy(Coroutine $fromCoroutine, array $keys = []): void
    {
        $from = Context::getContext($fromCoroutine);

        $current = Context::getContext();

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
    public static function override(string $id, Closure $closure, ?Coroutine $coroutine = null): mixed
    {
        $value = null;

        if (self::has($id, $coroutine)) {
            $value = self::get($id, null, $coroutine);
        }

        $value = $closure($value);

        self::set($id, $value, $coroutine);

        return $value;
    }

    /**
     * Retrieve the value and store it if not exists.
     */
    public static function getOrSet(string $id, mixed $value, ?Coroutine $coroutine = null): mixed
    {
        if (!self::has($id, $coroutine)) {
            return self::set($id, self::value($value), $coroutine);
        }

        return self::get($id, null, $coroutine);
    }

    public static function getContext(?Coroutine $coroutine = null): \Swow\Context\Context
    {
        return CoroutineContext::get($coroutine);
    }

    public static function getCurrent(): Coroutine
    {
        return Coroutine::getCurrent();
    }

    private static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
