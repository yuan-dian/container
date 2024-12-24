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
use ReflectionFunction;
use ReflectionMethod;
use yuandian\Container\Attributes\Inject;
use yuandian\Container\Attributes\RequestScoped;
use yuandian\Container\Attributes\SingletonScoped;
use yuandian\Container\Exception\ClassNotFoundException;
use yuandian\Container\Exception\FuncNotFoundException;

/**
 * 容器对象
 */
class Container implements ContainerInterface
{
    private LifecycleManager $lifecycleManager;
    private ParametersResolver $parametersResolver;

    /**
     * 缓存已反射的类，以避免重复创建
     * @var array
     */
    private array $reflectionCache = [];

    /**
     * 容器绑定标识
     * @var array
     */
    protected array $bind = [];

    /**
     * 容器对象实例
     * @var Container|Closure
     */
    protected static $instance;

    public function __construct()
    {
        $this->lifecycleManager = new LifecycleManager();
        $this->parametersResolver = new ParametersResolver();
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
     * @param object $instance
     * @return void
     */
    public static function setInstance($instance): void
    {
        static::$instance = $instance;
    }


    public function getLifecycleManager(): LifecycleManager
    {
        return $this->lifecycleManager;
    }

    /**
     * 根据别名获取真实类名
     * @param string $abstract
     * @return string
     */
    public function getAlias(string $abstract): string
    {
        if (isset($this->bind[$abstract])) {
            return $this->bind[$abstract];
        }

        return $abstract;
    }

    /**
     * 绑定一个类实例到全局容器
     * @access public
     * @param string $abstract 类名或者标识
     * @param object $instance 类的实例
     * @return $this
     */
    public function instanceGlobal(string $abstract, object $instance): static
    {
        $abstract = $this->getAlias($abstract);

        $this->lifecycleManager->setGlobal($abstract, $instance);

        return $this;
    }

    /**
     * 绑定一个类实例到请求容器
     * @param string $abstract
     * @param object $instance
     * @return $this
     * @date 2024/12/20 13:47
     * @author 原点 467490186@qq.com
     */
    public function instanceRequest(string $abstract, object $instance): static
    {
        $abstract = $this->getAlias($abstract);

        $this->lifecycleManager->setRequest($abstract, $instance);

        return $this;
    }


    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed $concrete 要绑定的类、闭包或者实例
     * @return $this
     */
    public function bind(string|array $abstract, mixed $concrete = null): static
    {
        if (is_array($abstract)) {
            foreach ($abstract as $key => $val) {
                $this->bind($key, $val);
            }
            return $this;
        }

        // 判断是否是绑定闭包
        if ($concrete instanceof Closure) {
            $this->bind[$abstract] = $concrete;
            return $this;
        }

        // 判断是否是绑定对象
        if (is_object($concrete)) {
            $className = get_class($concrete);
            // 判断生命周期类型（缓存判断结果）
            $isRequestScope = $this->lifecycleManager->getCachedLifecycle($className);
            if ($isRequestScope === null) {
                $isRequestScope = $this->isRequestScope($className);
                $this->lifecycleManager->cacheLifecycle($className, $isRequestScope);
            }
            if ($isRequestScope && $this->lifecycleManager->isRequestCoroutine()) {
                $this->instanceRequest($abstract, $concrete);
            } else {
                $this->instanceGlobal($abstract, $concrete);
            }
            return $this;
        }

        $abstract = $this->getAlias($abstract);
        if ($abstract != $concrete) {
            $this->bind[$abstract] = $concrete;
        }
        return $this;
    }


    /**
     * 获取对象的反射信息
     *
     * @param string $className
     * @return ReflectionClass
     * @date 2024/12/20 09:48
     * @author 原点 467490186@qq.com
     */
    private function getReflectionClass(string $className): ReflectionClass
    {
        if (!isset($this->reflectionCache[$className])) {
            try {
                $this->reflectionCache[$className] = new ReflectionClass($className);
            } catch (ReflectionException $e) {
                throw new ClassNotFoundException('class not exists: ' . $className, $e);
            }
        }
        return $this->reflectionCache[$className];
    }

    /**
     * 创建类的实例 已经存在则直接获取
     * @template T
     * @param string|class-string<T> $className 类名或者标识
     * @param array $vars 变量
     * @return T|object
     * @throws ClassNotFoundException|ReflectionException|InvalidArgumentException
     */
    public function make(string $className, array $vars = []): ?object
    {
        $className = $this->getAlias($className);

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
                $requestInstance = $this->invoke($className, $vars); // 自动创建
                $this->lifecycleManager->setRequest($className, $requestInstance);
            }
            return $requestInstance;
        }

        // 4. 否则，创建并存储在全局容器
        $instance = $this->invoke($className, $vars);
        $this->lifecycleManager->setGlobal($className, $instance);
        // 删除全局对象反射缓存，后续不需要重新创建对象
        unset($this->reflectionCache[$className]);
        return $instance;
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $className 类名
     * @param array $vars 参数
     * @return object|null
     * @throws ClassNotFoundException|ReflectionException
     */
    public function invokeClass(string $className, array $vars = []): ?object
    {
        $classReflector = $this->getReflectionClass($className);

        if ($classReflector->hasMethod('initialize')) {
            $method = $classReflector->getMethod('initialize');
            if ($method->isPublic() && $method->isStatic()) {
                $args = $this->parametersResolver::getArguments($method, $vars);
                return $method->invokeArgs(null, $args);
            }
        }

        $constructor = $classReflector->getConstructor();

        $instance = $constructor === null
            ? $classReflector->newInstanceWithoutConstructor()
            : $classReflector->newInstanceArgs($this->parametersResolver::getArguments($constructor, $vars));

        // 自动注入
        foreach ($classReflector->getProperties() as $property) {
            if (!$property->isInitialized($instance) && $property->getAttributes(Inject::class) !== []) {
                $property->setValue($instance, $this->get($property->getType()->getName()));
            }
        }
        return $instance;
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|Closure $function 函数或者闭包
     * @param array $vars 参数
     * @return mixed
     * @throws ReflectionException|FuncNotFoundException
     */
    public function invokeFunction(string|Closure $function, array $vars = []): mixed
    {
        try {
            $reflect = new ReflectionFunction($function);
        } catch (ReflectionException $e) {
            throw new FuncNotFoundException("function not exists: {$function}()", $e);
        }

        $args = $this->parametersResolver::getArguments($reflect, $vars);

        return $function(...$args);
    }

    /**
     * 调用反射执行类的方法 支持参数绑定
     * @param $method
     * @param array $vars
     * @return mixed
     * @throws ReflectionException
     * @date 2024/12/24 11:47
     * @author 原点 467490186@qq.com
     */
    public function invokeMethod($method, array $vars = []): mixed
    {
        if (is_array($method)) {
            [$class, $method] = $method;

            $class = is_object($class) ? $class : $this->invokeClass($class);
        } else {
            // 静态方法
            [$class, $method] = explode('::', $method);
        }

        try {
            $reflect = new ReflectionMethod($class, $method);
        } catch (ReflectionException $e) {
            $class = is_object($class) ? $class::class : $class;
            throw new FuncNotFoundException('method not exists: ' . $class . '::' . $method . '()', $e);
        }

        $args = $this->parametersResolver::getArguments($reflect, $vars);

        return $reflect->invokeArgs(is_object($class) ? $class : null, $args);
    }

    /**
     * 执行实例化
     * @param string|Closure $abstract
     * @param array $vars
     * @return mixed|object|null
     * @throws ReflectionException
     * @date 2024/12/20 11:34
     * @author 原点 467490186@qq.com
     */
    public function invoke(string|Closure $abstract, array $vars = []): mixed
    {
        if ($abstract instanceof Closure) {
            return $this->invokeFunction($abstract, $vars);
        }
        return $this->invokeClass($abstract, $vars);
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
     * @param string|Closure $className
     * @return bool
     * @throws ClassNotFoundException
     * @date 2024/12/18 14:07
     * @author 原点 467490186@qq.com
     */
    private function isRequestScope(string|Closure $className): bool
    {
        if ($className instanceof Closure) {
            return $this->lifecycleManager->isRequestCoroutine();
        }
        $classReflector = $this->getReflectionClass($className);

        $attributes = $classReflector->getAttributes(RequestScoped::class);
        if (!empty($attributes)) {
            return true;
        }

        $attributes = $classReflector->getAttributes(SingletonScoped::class);
        if (!empty($attributes)) {
            return false;
        }

        if ($classReflector->hasMethod('initialize')) {
            $method = $classReflector->getMethod('initialize');
            if (!empty($method->getAttributes(RequestScoped::class))) {
                return true;
            }
            if (!empty($method->getAttributes(SingletonScoped::class))) {
                return false;
            }
        }
        // 隐式推断：未标记情况下，若为请求协程，默认为请求级别
        return $this->lifecycleManager->isRequestCoroutine();
    }

    /**
     * 检查服务是否存在
     */
    public function has(string $id): bool
    {
        if (isset($this->bind[$id])) {
            return true;
        }
        $className = $this->getAlias($id);
        return $this->lifecycleManager->getGlobal($className) !== null
            || $this->lifecycleManager->getRequest($className) !== null;
    }
}