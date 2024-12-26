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

use RuntimeException;

class ContextManager
{

    /**
     * 获取当前协程上下文
     * @return ContextInterface
     */
    public static function getInstance(): ContextInterface
    {
        if (extension_loaded('swow')) {
            return new SwowContext();
        }
        if (extension_loaded('swoole')) {
            return new SwooleContext();
        }
        throw new RuntimeException('No supported coroutine runtime detected (Swow or Swoole).');
    }
}