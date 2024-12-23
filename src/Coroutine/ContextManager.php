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
    private string $runtime;

    public function __construct()
    {
        if (extension_loaded('swow')) {
            $this->runtime = 'swow';
        } elseif (extension_loaded('swoole')) {
            $this->runtime = 'swoole';
        } else {
            throw new RuntimeException('No supported coroutine runtime detected (Swow or Swoole).');
        }
    }

    /**
     * 获取当前协程上下文
     * @return ContextInterface
     */
    public function getContext(): ContextInterface
    {
        if ($this->runtime === 'swow') {
            return new SwowContext();
        } elseif ($this->runtime === 'swoole') {
            return new SwooleContext();
        }

        throw new RuntimeException('Unsupported coroutine runtime.');
    }
}