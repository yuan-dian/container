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
namespace yuandian\Container\Tests;
use yuandian\Container\Attributes\SingletonScoped;

#[SingletonScoped]
class Cache
{
    public string $cc;
    public string $dd;

}