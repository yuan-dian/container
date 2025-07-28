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
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionFunction;
use yuandian\Container\Attributes\Inject;
use yuandian\Container\Attributes\RequestScoped;
use yuandian\Container\Attributes\SingletonScoped;
use yuandian\Container\Exception\ClassNotFoundException;
use yuandian\Container\Exception\FuncNotFoundException;
use yuandian\Tools\reflection\ClassReflector;

/**
 * 容器对象
 */
class Container implements ContainerInterface
{

    /**
     * 容器绑定标识
     * @var array
     */
    protected array $bind = [];

    /**
     * 容器对象实例
     * @var Container|Closure
     */
    protected static Closure|Container $instance;

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
     * @param object|callable $instance
     * @return void
     */
    public static function setInstance(object|callable $instance): void
    {
        static::$instance = $instance;
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
        LifecycleManager::setGlobal($abstract, $instance);
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

        LifecycleManager::setRequest($abstract, $instance);

        return $this;
    }


    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed $concrete 要绑定的类、闭包或者实例
     * @return $this
     * @throws ReflectionException
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
            // 判断生命周期类型
            $isRequestScope = $this->isRequestScope($className);
            if ($isRequestScope) {
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
     * 创建类的实例 已经存在则直接获取
     * @template T
     * @param string|class-string<T> $className 类名或者标识
     * @param array $vars 变量
     * @param bool $newInstance
     * @return T
     * @throws ReflectionException
     */
    public function make(string $className, array $vars = [], bool $newInstance = false): object
    {
        $className = $this->getAlias($className);

        // 如果指定创建新实例，直接调用
        if ($newInstance) {
            return $this->invoke($className, $vars);
        }

        // 检查全局容器是否存在实例
        if ($instance = LifecycleManager::getGlobal($className)) {
            return $instance;
        }
        // 检查请求生命周期容器是否存在实例
        if ($instance = LifecycleManager::getRequest($className)) {
            return $instance;
        }

        // 判断生命周期类型
        $isRequestScope = $this->isRequestScope($className);

        // 实例化实例
        $instance = $this->invoke($className, $vars);

        if ($isRequestScope) {
            // 存储在请求生命周期容器
            LifecycleManager::setRequest($className, $instance);
        } else {
            // 存储在全局生命周期容器
            LifecycleManager::setGlobal($className, $instance);
        }
        return $instance;
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @template T
     * @param string|class-string<T> $className 类名
     * @param array $vars 参数
     * @return T|null
     * @throws ClassNotFoundException|ReflectionException
     */
    public function invokeClass(string $className, array $vars = []): ?object
    {
        $classReflector = new ClassReflector($className);

        if ($classReflector->hasMethod('initialize')) {
            $method = $classReflector->getMethod('initialize');
            if ($method->isPublic() && $method->isStatic()) {
                $args = ParametersResolver::getArguments($method, $vars);
                return $method->invokeArgs(null, $args);
            }
        }

        $constructor = $classReflector->getConstructor();

        $instance = $constructor === null
            ? $classReflector->newInstanceWithoutConstructor()
            : $classReflector->newInstanceArgs(ParametersResolver::getArguments($constructor, $vars));

        // 自动注入
        foreach ($classReflector->getProperties() as $property) {
            if (!$property->isInitialized($instance) && $property->hasAttribute(Inject::class)) {
                $property->setValue($instance, $this->make($property->getType()->getName()));
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

        $args = ParametersResolver::getArguments($reflect, $vars);

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
        $classReflector = new ClassReflector($class);
        if (!$classReflector->hasMethod($method)) {
            $class = is_object($class) ? $class::class : $class;
            throw new FuncNotFoundException('method not exists: ' . $class . '::' . $method . '()');
        }

        $methodReflector =  $classReflector->getMethod($method);

        $args = ParametersResolver::getArguments($methodReflector, $vars);

        return $methodReflector->invokeArgs(is_object($class) ? $class : null, $args);
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
     * @template T
     * @param string|class-string<T> $id
     * @return T
     * @throws ReflectionException
     * @date 2024/12/18 14:08
     * @author 原点 467490186@qq.com
     */
    public function get(string $id): mixed
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
     * @throws ClassNotFoundException|ReflectionException
     * @date 2024/12/18 14:07
     * @author 原点 467490186@qq.com
     */
    private function isRequestScope(string|Closure $className): bool
    {
        $isRequestScope = LifecycleManager::getCachedLifecycle($className);

        if ($isRequestScope !== null) {
            return $isRequestScope;
        }
        // 闭包默认是请求级别
        if ($className instanceof Closure) {
            return true;
        }
        // 非请求协程，直接判断部署请求生命周期
        $isRequestScope = LifecycleManager::isRequestCoroutine();
        // 非请求协程，直接判定为全局生命周期
        if ($isRequestScope === false) {
            return false;
        }

        $classReflector = new ClassReflector($className);

        // 检查类注解和 initialize 方法生命周期注解
        $isRequestScope = $this->getScopedAttribute($classReflector);

        // 隐式推断：未标记情况下，若为请求协程，默认为请求级别
        return LifecycleManager::cacheLifecycle($className, $isRequestScope);
    }

    /**
     * 获取类或其 `initialize` 方法的生命周期注解
     * @param ClassReflector $classReflector
     * @return bool
     * @date 2025/7/28 上午11:47
     * @author 原点 467490186@qq.com
     */
    private function getScopedAttribute(ClassReflector $classReflector): bool
    {
        if ($classReflector->hasAttribute(RequestScoped::class)) {
            return true;
        }
        if ($classReflector->hasAttribute(SingletonScoped::class)) {
            return false;
        }
        // 检查 initialize 方法注解
        if ($classReflector->hasMethod('initialize')) {
            $initialize = $classReflector->getMethod('initialize');
            if ($initialize->hasAttribute(RequestScoped::class)) {
                return true;
            }
            if ($initialize->hasAttribute(SingletonScoped::class)) {
                return false;
            }
        }
        return false;
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
        return LifecycleManager::getGlobal($className) !== null
            || LifecycleManager::getRequest($className) !== null;
    }

    /**
     * 魔术方法
     * @param $name
     * @param $value
     * @date 2024/12/26 14:40
     * @throws ReflectionException
     * @author 原点 467490186@qq.com
     */
    public function __set($name, $value)
    {
        $this->bind($name, $value);
    }

    /**
     * 魔术方法
     * @param $name
     * @return mixed|object|string|null
     * @throws ReflectionException
     * @date 2024/12/26 14:39
     * @author 原点 467490186@qq.com
     */
    public function __get($name)
    {
        return $this->get($name);
    }
}