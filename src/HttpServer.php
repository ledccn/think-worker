<?php

namespace Ledc\ThinkWorker;

use Workerman\Worker;

/**
 * Http服务
 */
class HttpServer
{
    /**
     * 应用根目录
     * @var string
     */
    protected $rootPath = '';
    /**
     * 网站目录
     * @var string
     */
    protected $publicPath = '';

    /**
     * 架构函数
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!empty($config['listen'])) {
            $worker = new Worker($config['listen'], $config['context'] ?? []);
            $propertyMap = [
                'name',
                'count',
                'user',
                'group',
                'reusePort',
                'transport',
                'protocol'
            ];
            foreach ($propertyMap as $property) {
                if (isset($config[$property])) {
                    $worker->$property = $config[$property];
                }
            }

            $worker->onWorkerStart = function ($worker) {
                $app = new Application($this->rootPath);
                $worker->onMessage = [$app, 'onMessage'];
                call_user_func([$app, 'onWorkerStart'], $worker);
            };
        }
    }

    /**
     * 设置应用根目录
     * @param string $path
     * @return void
     */
    public function setRootPath(string $path)
    {
        $this->rootPath = $path;
    }

    /**
     * 设置网站目录
     * @param string $publicPath
     * @return void
     */
    public function setPublicPath(string $publicPath)
    {
        $this->publicPath = $publicPath;
    }
}