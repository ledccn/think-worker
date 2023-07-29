<?php
// +----------------------------------------------------------------------
// | Workerman设置 仅对 php think workerman 指令有效
// +----------------------------------------------------------------------
return [
    /**
     * 默认配置
     */
    'default' => [
        /**
         * 服务监听配置
         */
        // 协议 支持 tcp udp unix http websocket text
        'listen' => 'http://0.0.0.0:8787',
        'transport' => 'tcp',
        'context' => [],
        'name' => 'workerman',
        'count' => 4,
        'user' => '',
        'group' => '',
        'reusePort' => false,
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
        'websocket' => [
            //使能
            'enable' => true,
            //监听
            'listen' => 'http://0.0.0.0:8787',
            //上下文
            'context' => [],
            //worker支持的属性
            'properties' => [

            ],
            //子进程：handler类
            'handler' => '',
            //子进程：handler类的构造函数参数
            'constructor' => [],
        ],
    ],
];
