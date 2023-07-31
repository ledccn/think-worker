<?php

namespace Ledc\ThinkWorker;

use Closure;
use Exception;
use Ledc\ThinkWorker\Http\Request;
use Ledc\ThinkWorker\Http\Response;
use think\App;
use think\exception\Handle;
use think\exception\HttpException;
use think\facade\Config;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Worker;

/**
 * Worker应用对象
 */
class Application extends App
{
    /**
     * @var Worker|null
     */
    protected static $worker = null;

    /**
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker)
    {
        static::$worker = $worker;
        Http::requestClass(Request::class);

        $this->initialize();
        $this->bind([
            'think\Cookie' => Cookie::class,
        ]);
    }

    /**
     * @param TcpConnection|mixed $connection
     * @param Request|mixed $request
     * @return null
     */
    public function onMessage($connection, $request)
    {
        try {
            $resp = new Response();
            Context::set(Request::class, $request);
            Context::set(Response::class, $resp);
            $path = $request->path();
            $key = $request->method() . $path;
            if (
                static::unsafeUri($connection, $path, $request) ||
                static::findFile($connection, $path, $key, $request)
            ) {
                return null;
            }

            $this->adapter($connection, $request);

            $this->beginTime = microtime(true);
            $this->beginMem = memory_get_usage();
            $this->db->clearQueryTimes();
            while (ob_get_level() > 1) {
                ob_end_clean();
            }

            ob_start();
            $response = $this->http->run();
            $content = ob_get_clean();

            ob_start();
            $response->send();
            $this->http->end($response);
            $content .= ob_get_clean() ?: '';

            $resp->withStatus($response->getCode())->withHeaders($response->getHeader())->withBody($content);
            self::send($connection, $resp, $request);
        } catch (HttpException|Exception|Throwable $e) {
            $this->exception($connection, $resp, $request, $e);
        } finally {
            static::destory();
        }
        return null;
    }

    /**
     * Find File.
     * @param TcpConnection $connection
     * @param string $path
     * @param string $key
     * @param Request|mixed $request
     * @return bool
     */
    protected static function findFile(TcpConnection $connection, string $path, string $key, $request): bool
    {
        if (preg_match('/%[0-9a-f]{2}/i', $path)) {
            $path = urldecode($path);
            if (static::unsafeUri($connection, $path, $request)) {
                return true;
            }
        }

        $publicDir = app()->getRootPath() . 'public';
        $file = "$publicDir/$path";
        if (!is_file($file)) {
            return false;
        }

        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            if (!static::config('default.support_php_files', false)) {
                return false;
            }
            static::send($connection, static::execPhpFile($file), $request);
            return true;
        }

        if (!static::config('default.support_static', false)) {
            return false;
        }
        $callback = function ($request) use ($file) {
            clearstatcache(true, $file);
            return (new Response())->file($file);
        };
        static::send($connection, $callback($request), $request);
        return true;
    }

    /**
     * UnsafeUri.
     * @param TcpConnection $connection
     * @param string $path
     * @param Request|mixed $request
     * @return bool
     */
    protected static function unsafeUri(TcpConnection $connection, string $path, Request $request): bool
    {
        if (
            !$path ||
            strpos($path, '..') !== false ||
            strpos($path, "\\") !== false ||
            strpos($path, "\0") !== false
        ) {
            $callback = static::getFallback();
            static::send($connection, $callback($request), $request);
            return true;
        }
        return false;
    }

    /**
     * Config.
     * @param string $key
     * @param mixed $default
     * @return array|mixed|null
     */
    protected static function config(string $key, $default = null)
    {
        return Config::get('workerman.' . $key, $default);
    }

    /**
     * GetFallback.
     * @return Closure
     */
    protected static function getFallback(): Closure
    {
        return function () {
            try {
                $notFoundContent = file_get_contents(public_path() . '404.html');
            } catch (Throwable $e) {
                $notFoundContent = '404 Not Found';
            }
            return new Response(404, [], $notFoundContent);
        };
    }

    /**
     * 适配器
     * @param TcpConnection|mixed $connection
     * @param Request|mixed $request
     * @return void
     */
    protected function adapter(TcpConnection $connection, Request $request): void
    {
        // Init.
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = [];
        // $_SERVER
        $_SERVER = [
            'REQUEST_METHOD' => $request->method(),
            'REQUEST_URI' => $request->uri(),
            'SERVER_PROTOCOL' => $request->protocolVersion(),
            'SERVER_ADDR' => $connection->getLocalIp(),
            'SERVER_PORT' => $connection->getLocalPort(),
            'REMOTE_ADDR' => $connection->getRemoteIp(),
            'REMOTE_PORT' => $connection->getRemotePort(),
            'SERVER_SOFTWARE' => 'workerman',
            'SERVER_NAME' => $request->host(),
            'HTTP_HOST' => $request->host(),
            'HTTP_USER_AGENT' => $request->header('USER-AGENT', ''),
            'HTTP_ACCEPT' => $request->header('ACCEPT', ''),
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE' => $request->cookie(),
            'HTTP_CONNECTION' => '',
            'CONTENT_TYPE' => $request->header('CONTENT-TYPE', ''),
            'QUERY_STRING' => $request->queryString(),
            'CONTENT_LENGTH' => $request->header('CONTENT-LENGTH', ''),
        ];

        $_GET = $request->get();
        $_POST = $request->post();
        $_REQUEST = $_POST + $_GET;
        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $request->rawBody();
    }

    /**
     * @param TcpConnection|mixed $connection
     * @param Response|mixed $resp
     * @param Request|mixed $request
     * @param Throwable $e
     * @return void
     */
    protected function exception(TcpConnection $connection, Response $resp, Request $request, Throwable $e)
    {
        try {
            if ($e instanceof Exception) {
                $handler = $this->make(Handle::class);
                $handler->report($e);

                $response = $handler->render($this->request, $e);
                $resp->withStatus($response->getCode())->withHeaders($response->getHeader())->withBody($response->getContent());
            } else {
                $resp = new Response(500, [], $e->getMessage());
            }

            static::send($connection, $resp, $request);
        } catch (Throwable $throwable) {
            static::destory();
        }
    }

    /**
     * 清理环境
     * @return void
     */
    protected static function destory(): void
    {
        Context::destroy();
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = [];
        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = '';
    }

    /**
     * @return Request
     */
    public static function request(): Request
    {
        return Context::get(Request::class);
    }

    /**
     * @return Response
     */
    public static function response(): Response
    {
        return Context::get(Response::class);
    }

    /**
     * @return Worker|null
     */
    public static function worker(): ?Worker
    {
        return static::$worker;
    }

    /**
     * Send.
     * @param TcpConnection|mixed $connection
     * @param mixed $response
     * @param Request|mixed $request
     * @return void
     */
    protected static function send($connection, $response, $request)
    {
        $keepAlive = $request->header('connection');
        if (($keepAlive === null && $request->protocolVersion() === '1.1')
            || $keepAlive === 'keep-alive' || $keepAlive === 'Keep-Alive'
        ) {
            $connection->send($response);
            return;
        }
        $connection->close($response);
    }

    /**
     * ExecPhpFile.
     * @param string $file
     * @return false|string
     */
    public static function execPhpFile(string $file)
    {
        ob_start();
        // Try to include php file.
        try {
            include $file;
        } catch (Exception $e) {
            echo $e;
        }
        return ob_get_clean();
    }

    /**
     * @param mixed $data
     * @return string
     */
    protected static function stringify($data): string
    {
        $type = gettype($data);
        switch ($type) {
            case 'boolean':
                return $data ? 'true' : 'false';
            case 'NULL':
                return 'NULL';
            case 'array':
                return 'Array';
            case 'object':
                if (!method_exists($data, '__toString')) {
                    return 'Object';
                }
            //no break
            default:
                return (string)$data;
        }
    }
}
