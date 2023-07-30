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
            $uri = $request->uri();
            $body = $request->rawBody();

            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();
            $this->db->clearQueryTimes();

            $pathinfo = ltrim(strpos($uri, '?') ? strstr($uri, '?', true) : $uri, '/');

            $this->request
                ->setPathinfo($pathinfo)
                ->withInput($body);

            while (ob_get_level() > 1) {
                ob_end_clean();
            }

            ob_start();
            $response = $this->http->run();
            $content  = ob_get_clean();

            ob_start();
            $response->send();
            $this->http->end($response);
            $content .= ob_get_clean() ?: '';

            $_response = new Response($response->getCode(), $response->getHeader(), $content);
            self::send($connection, $_response, $request);
        } catch (HttpException|Exception|Throwable $e) {
            $this->exception($connection, $request, $e);
        }
        return null;
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

            $resp    = $handler->render($this->request, $e);
            $response = new Response($resp->getCode(), [], $resp->getContent());
        } else {
            $response = new Response(500, [], $e->getMessage());
        }

        static::send($connection, $response, $request);
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
        Context::destroy();
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
                return  $data ? 'true' : 'false';
            case 'NULL':
                return 'NULL';
            case 'array':
                return 'Array';
            case 'object':
                if (!method_exists($data, '__toString')) {
                    return 'Object';
                }
            default:
                return (string)$data;
        }
    }
}
