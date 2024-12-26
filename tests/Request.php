<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare(strict_types=1);
namespace yuandian\Container\Tests;

use yuandian\Container\Attributes\RequestScoped;


/**
 * 请求管理类
 * @package think
 */
#[RequestScoped]
class Request
{
    public string $name;
    public string $bb;
}
