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

use yuandian\Container\Coroutine\ContextInterface;
use yuandian\Container\Coroutine\ContextManager;

/**
 * 生命周期管理
 */
class LifecycleManager
{
    private array $globalInstances = [];
    private array $cache = []; // 缓存生命周期判断结果

    private ContextInterface $context;

    public function __construct()
    {
        $this->context = (new ContextManager())->getContext();
    }

    /**
     * 获取全局实例
     */
    public function getGlobal(string $id)
    {
        return $this->globalInstances[$id] ?? null;
    }

    /**
     * 设置全局实例
     */
    public function setGlobal(string $id, $instance): void
    {
        $this->globalInstances[$id] = $instance;
    }

    /**
     * 获取请求级别实例
     */
    public function getRequest(string $id)
    {
        $context = $this->context::getContext();
        return $context[$id] ?? null;
    }

    /**
     * 设置请求级别实例
     */
    public function setRequest(string $id, $instance): void
    {
        $context = $this->context::getContext();
        $context[$id] = $instance;
    }

    /**
     * 判断当前是否是请求协程或其子协程
     */
    public function isRequestCoroutine(): bool
    {
        $context = $this->context::getContext();
        return $context['is_request_coroutine'] ?? false;
    }

    /**
     * 显式标记请求协程（包括子协程）
     */
    public function markRequestCoroutine(): void
    {
        $context = $this->context::getContext();
        $context['is_request_coroutine'] = true;
    }

    /**
     * 缓存生命周期判断结果
     */
    public function cacheLifecycle(string $id, bool $isRequestScope): void
    {
        $this->cache[$id] = $isRequestScope;
    }

    /**
     * 获取缓存的生命周期判断结果
     */
    public function getCachedLifecycle(string $id): ?bool
    {
        return $this->cache[$id] ?? null;
    }

    /**
     * 创建协程并继承父上下文
     * @param callable $callable
     * @param mixed ...$data
     * @return mixed
     * @date 2024/12/24 09:28
     * @author 原点 467490186@qq.com
     */
    public function run(callable $callable, mixed ...$data)
    {
        return $this->context::run($callable, $data);
    }
}