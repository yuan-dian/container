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

use Swow\Coroutine;

/**
 * 请求生命周期内创建协程，自动继承上下文
 */
class RequestScopedCoroutine
{
    /**
     * 创建一个继承上下文的子协程
     */
    public static function run(callable $callable): Coroutine
    {
        $pid = Context::getCurrent();
        return Coroutine::run(function () use ($callable, $pid) {
            // 继承父上下文标记
            Context::copy($pid);

            // 执行子协程逻辑
            $callable();
        });
    }
}