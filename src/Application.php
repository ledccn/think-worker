<?php

namespace Ledc\ThinkWorker;

use Exception;
use Ledc\ThinkWorker\Http\Request;
use Ledc\ThinkWorker\Http\Response;
use think\App;
use think\exception\Handle;
use think\exception\HttpException;
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
            Context::set(Request::class, $request);
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

            $resp = new Response($response->getCode(), $response->getHeader(), $content);
            self::send($connection, $resp, $request);
        } catch (HttpException|Exception|Throwable $e) {
            $this->exception($connection, $request, $e);
        } finally {
            Context::destroy();
        }
        return null;
    }

    /**
     * 适配器
     * @param TcpConnection $connection
     * @param Request $request
     * @return void
     */
    protected function adapter($connection, Request $request): void
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
            'HTTP_USER_AGENT' => $request->header(strtolower('HTTP_USER_AGENT'), ''),
            'HTTP_ACCEPT' => $request->header(strtolower('HTTP_ACCEPT'), ''),
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE' => $request->cookie(),
            'HTTP_CONNECTION' => '',
            'CONTENT_TYPE' => $request->header(strtolower('CONTENT_TYPE'), ''),
            'QUERY_STRING' => $request->queryString(),
            //'CONTENT_LENGTH' => $request->header(strtolower('CONTENT_LENGTH'), ''),
        ];

        $_GET = $request->get();
        $_POST = $request->post();
        $_REQUEST = $_POST + $_GET;
        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $request->rawBody();
    }

    /**
     * @param $connection
     * @param $request
     * @param $e
     * @return void
     */
    protected function exception($connection, $request, $e)
    {
        if ($e instanceof Exception) {
            $handler = $this->make(Handle::class);
            $handler->report($e);

            $response = $handler->render($this->request, $e);
            $resp = new Response($response->getCode(), [], $response->getContent());
        } else {
            $resp = new Response(500, [], $e->getMessage());
        }

        static::send($connection, $resp, $request);
    }

    /**
     * 清理环境
     * @return void
     */
    protected static function destory(): void
    {
        Context::destroy();
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = [];
    }

    /**
     * @return Request
     */
    public static function request(): Request
    {
        return Context::get(Request::class);
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
        static::destory();
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
