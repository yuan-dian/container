<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2024/12/24
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace yuandian\Container;

use InvalidArgumentException;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;

class ParametersResolver
{
    /**
     * 获取并解析所有参数值。
     *
     * @param ReflectionFunctionAbstract $functionAbstract
     * @param array $vars
     * @return array 包含解析后的参数值的数组。
     * @throws \ReflectionException
     */
    public static function getArguments(ReflectionFunctionAbstract $functionAbstract, array $vars = []): array
    {

        if ($functionAbstract->getNumberOfParameters() == 0) {
            return [];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        $isList = array_is_list($vars);
        $params = $functionAbstract->getParameters();
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();
            $reflectionType = $param->getType();

            // 可变参数处理
            if ($param->isVariadic()) {
                return array_merge($args, array_values($vars));
            }

            // 非标量类型参数（类/对象）
            if ($reflectionType instanceof ReflectionNamedType && $reflectionType->isBuiltin() === false) {
                $className = $reflectionType->getName();
                if ($className == 'self') {
                    $className = $param->getDeclaringClass()->getName();
                }
                $args[] = self::getClassInstance($className, $vars, $param);
                continue;
            }

            // 按位置绑定
            if ($isList && !empty($vars)) {
                $args[] = array_shift($vars);
                continue;
            }
            // 按名称绑定
            if (!$isList && array_key_exists($name, $vars)) {
                $args[] = $vars[$name];
                continue;
            }
            // 默认值处理
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }
            throw new InvalidArgumentException('method param miss:' . $name);
        }

        return $args;
    }

    /**
     * @param string $className
     * @param array $vars
     * @param ReflectionParameter $param
     * @return mixed
     * @throws \ReflectionException
     * @date 2024/12/24 13:51
     * @author 原点 467490186@qq.com
     */
    protected static function getClassInstance(string $className, array &$vars, ReflectionParameter $param): mixed
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : Container::getInstance()->get($className);
        }

        return $result;
    }
}