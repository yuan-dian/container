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

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use yuandian\Container\Attributes\Inject;
use yuandian\Container\Attributes\RequestScoped;
use yuandian\Container\Exception\ClassNotFoundException;

/**
 * 容器对象
 */
class Container implements ContainerInterface
{
    private LifecycleManager $lifecycleManager;

    /**
     * 容器对象实例
     * @var Container|Closure
     */
    protected static $instance;

    public function __construct()
    {
        $this->lifecycleManager = new LifecycleManager();
    }

    /**
     * 获取当前容器的实例（单例）
     * @return object
     * @date 2024/12/18 16:02
     * @author 原点 467490186@qq.com
     */
    public static function getInstance(): object
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        if (static::$instance instanceof Closure) {
            return (static::$instance)();
        }

        return static::$instance;
    }

    /**
     * 设置当前容器的实例
     * @access public
     * @param object|Closure $instance
     * @return void
     */
    public static function setInstance($instance): void
    {
        static::$instance = $instance;
    }

    /**
     * 创建类的实例 已经存在则直接获取
     * @template T
     * @param string|class-string<T> $className 类名或者标识
     * @param array $vars 变量
     * @param bool $newInstance 是否每次创建新的实例
     * @return T|object
     * @throws ClassNotFoundException|ReflectionException|InvalidArgumentException
     */
    public function make(string $className, array $vars = [], bool $newInstance = false): ?object
    {
        if ($newInstance) {
            return $this->invokeClass($className, $vars);
        }
        // 1. 检查全局容器是否存在实例
        $globalInstance = $this->lifecycleManager->getGlobal($className);
        if ($globalInstance !== null) {
            return $globalInstance;
        }

        // 2. 判断生命周期类型（缓存判断结果）
        $isRequestScope = $this->lifecycleManager->getCachedLifecycle($className);
        if ($isRequestScope === null) {
            $isRequestScope = $this->isRequestScope($className);
            $this->lifecycleManager->cacheLifecycle($className, $isRequestScope);
        }

        // 3. 如果是请求级别并处于请求协程中，使用请求容器
        if ($isRequestScope && $this->lifecycleManager->isRequestCoroutine()) {
            $requestInstance = $this->lifecycleManager->getRequest($className);
            if ($requestInstance === null) {
                $requestInstance = $this->invokeClass($className, $vars); // 自动创建
                $this->lifecycleManager->setRequest($className, $requestInstance);
            }
            return $requestInstance;
        }

        // 4. 否则，创建并存储在全局容器
        $instance = $this->invokeClass($className, $vars);
        $this->lifecycleManager->setGlobal($className, $instance);
        return $instance;
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $class 类名
     * @param array $vars 参数
     * @return object|null
     * @throws ClassNotFoundException|ReflectionException
     */
    public function invokeClass(string $class, array $vars = []): ?object
    {
        try {
            $classReflector = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ClassNotFoundException('class not exists: ' . $class, $e);
        }

        if ($classReflector->hasMethod('__make')) {
            $method = $classReflector->getMethod('__make');
            if ($method->isPublic() && $method->isStatic()) {
                $args = $this->bindParams($method, $vars);
                return $method->invokeArgs(null, $args);
            }
        }

        $constructor = $classReflector->getConstructor();

        $instance = $constructor === null
            ? $classReflector->newInstanceWithoutConstructor()
            : $classReflector->newInstanceArgs($this->bindParams($constructor, $vars));

        // 自动注入
        foreach ($classReflector->getProperties() as $property) {
            if (!$property->isInitialized($instance) && $property->getAttributes(Inject::class) !== []) {
                $property->setValue($instance, $this->get($property->getType()->getName()));
            }
        }
        return $instance;
    }

    /**
     * 绑定参数
     * @access protected
     * @param ReflectionFunctionAbstract $reflect 反射类
     * @param array $vars 参数
     * @return array
     * @throws ReflectionException|InvalidArgumentException
     */
    protected function bindParams(ReflectionFunctionAbstract $reflect, array $vars = []): array
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        $type = array_is_list($vars) ? 1 : 0;
        $params = $reflect->getParameters();
        $args = [];

        foreach ($params as $param) {
            $name = $param->getName();
            $reflectionType = $param->getType();

            if ($param->isVariadic()) {
                return array_merge($args, array_values($vars));
            } elseif ($reflectionType instanceof ReflectionNamedType && $reflectionType->isBuiltin() === false) {
                $args[] = $this->getObjectParam($reflectionType->getName(), $vars, $param);
            } elseif (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && array_key_exists($name, $vars)) {
                $args[] = $vars[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new InvalidArgumentException('method param miss:' . $name);
            }
        }

        return $args;
    }

    /**
     * 获取对象类型的参数值
     * @access protected
     * @param string $className 类名
     * @param array $vars 参数
     * @param ReflectionParameter $param
     * @return mixed
     * @throws ClassNotFoundException|ReflectionException
     */
    protected function getObjectParam(string $className, array &$vars, ReflectionParameter $param): mixed
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : $this->make($className);
        }

        return $result;
    }


    /**
     * 获取实例
     *
     * @param string $id
     * @return object|null
     * @throws ClassNotFoundException|ReflectionException|InvalidArgumentException
     * @date 2024/12/18 14:08
     * @author 原点 467490186@qq.com
     */
    public function get(string $id): ?object
    {
        if ($this->has($id)) {
            return $this->make($id);
        }
        throw new ClassNotFoundException('class not exists: ' . $id);
    }

    /**
     * 判断类是否为短生命周期
     * @param string $className
     * @return bool
     * @throws ClassNotFoundException
     * @date 2024/12/18 14:07
     * @author 原点 467490186@qq.com
     */
    private function isRequestScope(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            throw new ClassNotFoundException('class not exists: ' . $className, $e);
        }

        $attributes = $reflection->getAttributes(RequestScoped::class);
        if (!empty($attributes)) {
            return true;
        }
        // 隐式推断：未标记情况下，若为请求协程，默认为请求级别
        return $this->lifecycleManager->isRequestCoroutine();
    }

    /**
     * 检查服务是否存在
     */
    public function has(string $id): bool
    {
        return $this->lifecycleManager->getGlobal($id) !== null || $this->lifecycleManager->getRequest($id) !== null;
    }
}