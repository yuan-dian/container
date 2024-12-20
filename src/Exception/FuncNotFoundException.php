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

namespace yuandian\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

class FuncNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        $this->message = $message;

        parent::__construct($message, 0, $previous);
    }

}