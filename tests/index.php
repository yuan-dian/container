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
use Swow\Coroutine;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Psr7\Server\Server;
use Swow\Socket;
use Swow\SocketException;
use yuandian\Container\Container;
use yuandian\Container\LifecycleManager;
use yuandian\Container\Tests\Cache;
use yuandian\Container\Tests\Request;

require __DIR__ . '/../vendor/autoload.php';

\Swow\Debug\Debugger\Debugger::runOnTTY();
$container = new Container();
$container->instanceGlobal(Container::class, $container);
$server = new Server();
$server->bind('127.0.0.1', 9502, Socket::BIND_FLAG_REUSEPORT)->listen();
while (true) {
    try {
        $connection = $server->acceptConnection();
        Coroutine::run(function () use ($connection, $container) {
            LifecycleManager::markRequestCoroutine();
            try {
                while (true) {
                    $request = null;
                    try {
//                        sleep(5);
                        $request = $connection->recvHttpRequest();

                        if ($request->getUri() == '/favicon.ico') {
                            $connection->respond();
                            return;
                        }
                        $res = $container->make(Request::class);
                        $cache = $container->make(Cache::class);
                        $res->name = $request->getQueryParams()['name'] ?? '';
                        $res->bb = $request->getQueryParams()['bb'] ?? '';
                        if (isset($request->getQueryParams()['cc'])) {
                            $cache->cc = $request->getQueryParams()['cc'];
                        }
                        if (isset($request->getQueryParams()['dd'])) {
                            $cache->dd = $request->getQueryParams()['dd'];
                        }
                        $data = [$res, $cache];
                        $connection->respond(
                            json_encode($data),
                            200,
                            ['Content-Type' => 'application/json; charset=utf-8']
                        );
                    } catch (\Throwable $exception) {
                        $connection->respond($exception->getMessage());
                        break;
                    }
                    if (!$connection->shouldKeepAlive()) {
                        break;
                    }
                }
            } catch (\Throwable $exception) {
                $connection->respond(
                    $exception->getMessage(),
                    500,
                    ['Content-Type' => 'application/json; charset=utf-8']
                );
            } finally {
                $connection->close();
            }
        });
    } catch (SocketException|CoroutineException $exception) {
        if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
            sleep(1);
        } else {
            break;
        }
    }
}


