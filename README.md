# container CI容器


# 安装

``` composer require yuandian/container ```

# 特性
- 支持长生命周期与短生命周期的管理（短生命周期使用swow|swoole协程实现）
- 支持PSR-11规范
- 支持依赖注入
- 支持通过```#[Inject]```注解实现属性注入
- 支持容器对象绑定
- 支持闭包绑定
- 支持接口绑定

# Container

```php
// 获取容器实例
$container = \yuandian\Container\Container::getInstance();
// 绑定一个类、闭包、实例、接口实现到容器
$container->bind('cache', '\app\common\Cache');
// 判断是否存在对象实例
$container->has('cache');
// 从容器中获取对象实例
$container->get('cache');
// 从容器中获取对象，没有则自动实例化
$container->make('cache');

// 从容器中获取对象，没有则自动实例化【没有绑定标识】
$container->make(Cache::class);

// 绑定接口到具体实现
$container->bind(LoggerInterface::class, FileLogger::class);

// 执行某个方法或者闭包 支持依赖注入
$container->invoke($callable, $vars);
// 执行某个类的实例化 支持依赖注入
$container->invokeClass($class, $vars);

// 绑定一个类实例到全局容器
$container->instanceGlobal($class, $instance)
// 绑定一个类实例到请求容器
$container->instanceRequest($class, $instance)
```


## 捐献

![](./wechat.png)
![](./alipay.png)