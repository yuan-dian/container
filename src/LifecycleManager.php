<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2024/12/18
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace yuandian\Container;

use yuandian\Container\Coroutine\ContextManager;

/**
 * 生命周期管理
 */
class LifecycleManager
{
    private static array $globalInstances = [];
    private static array $cache = []; // 缓存生命周期判断结果

    /**
     * 获取全局实例
     */
    public static function getGlobal(string $id)
    {
        return self::$globalInstances[$id] ?? null;
    }

    /**
     * 设置全局实例
     */
    public static function setGlobal(string $id, $instance): void
    {
        self::$globalInstances[$id] = $instance;
    }

    /**
     * 获取请求级别实例
     */
    public static function getRequest(string $id)
    {
        $context = ContextManager::getInstance()->getContext();
        return $context[$id] ?? null;
    }

    /**
     * 设置请求级别实例
     */
    public static function setRequest(string $id, $instance): void
    {
        $context = ContextManager::getInstance()->getContext();
        $context[$id] = $instance;
    }

    /**
     * 判断当前是否是请求协程或其子协程
     */
    public static function isRequestCoroutine(): bool
    {
        $context = ContextManager::getInstance()->getContext();
        return $context['is_request_coroutine'] ?? false;
    }

    /**
     * 显式标记请求协程（包括子协程）
     */
    public static function markRequestCoroutine(): void
    {
        $context = ContextManager::getInstance()->getContext();
        $context['is_request_coroutine'] = true;
    }

    /**
     * 缓存生命周期判断结果
     */
    public static function cacheLifecycle(string $id, bool $isRequestScope): bool
    {
        self::$cache[$id] = $isRequestScope;
        return $isRequestScope;
    }

    /**
     * 获取缓存的生命周期判断结果
     */
    public static function getCachedLifecycle(string $id): ?bool
    {
        return self::$cache[$id] ?? null;
    }

    /**
     * 创建协程并继承父上下文
     * @param callable $callable
     * @param mixed ...$data
     * @return mixed
     * @date 2024/12/24 09:28
     * @author 原点 467490186@qq.com
     */
    public static function run(callable $callable, mixed ...$data): mixed
    {
        return ContextManager::getInstance()::run($callable, $data);
    }
}