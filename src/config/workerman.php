<?php
// +----------------------------------------------------------------------
// | Workerman设置 仅对 php think workerman 指令有效
// +----------------------------------------------------------------------
use Ledc\ThinkWorker\Monitor;

return [
    /**
     * 默认配置
     */
    'default' => [
        /**
         * 服务监听配置
         */
        // 协议 支持 tcp udp unix http websocket text
        'listen' => 'http://0.0.0.0:2346',
        'transport' => 'tcp',
        'context' => [],
        'name' => 'workerman',
        'count' => 4,
        'user' => '',
        'group' => '',
        'reusePort' => false,
        //PHP配置
        'error_reporting' => E_ALL,
        'default_timezone' => 'Asia/Shanghai',
        /**
         * 主进程配置
         */
        'event_loop' => '',
        'stop_timeout' => 2,
        'pid_file' => runtime_path() . 'workerman.pid',
        'status_file' => runtime_path() . 'workerman.status',
        'stdout_file' => runtime_path() . 'logs/stdout.log',
        'log_file' => runtime_path() . 'logs/workerman.log',
        'max_package_size' => 10 * 1024 * 1024,
    ],
    /**
     * 进程配置
     */
    'process' => [
        // File update detection and automatic reload
        'monitor' => [
            //使能
            'enable' => true,
            //业务进程：handler类
            'handler' => Monitor::class,
            //worker支持的属性
            'properties' => [
                'reloadable' => false,
            ],
            //业务进程：handler类的构造函数参数
            'constructor' => [
                // Monitor these directories
                'monitor_dir' => [
                    app_path(),
                    config_path(),
                    base_path() . '/.env',
                ],
                // Files with these suffixes will be monitored
                'monitor_extensions' => [
                    'php', 'html', 'htm', 'env'
                ]
            ]
        ],
        'websocket' => [
            //使能
            'enable' => true,
            //监听
            'listen' => 'websocket://0.0.0.0:2345',
            //上下文
            'context' => [],
            //worker支持的属性
            'properties' => [
                // 支持事件回调
                // onWorkerStart
                'onWorkerStart'  => function ($worker) {

                },
                // onWorkerReload
                'onWorkerReload' => function ($worker) {

                },
                // onConnect
                'onConnect'      => function ($connection) {

                },
                // onMessage
                'onMessage'      => function ($connection, $data) {
                    $connection->send('receive success');
                },
                // onClose
                'onClose'        => function ($connection) {

                },
                // onError
                'onError'        => function ($connection, $code, $msg) {
                    echo "error [ $code ] $msg\n";
                },
            ],
            //业务进程：handler类
            'handler' => '',
            //业务进程：handler类的构造函数参数
            'constructor' => [],
        ],
    ],
];
