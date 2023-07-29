<?php

namespace Ledc\ThinkWorker\Command;

use Ledc\ThinkWorker\Process;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

/**
 * GatewayWorker 命令行类
 */
class GatewayWorker extends Command
{
    /**
     * @return void
     */
    public function configure()
    {
        $this->setName('worker:gateway')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status|connections", 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the workerman server in daemon mode.')
            ->setDescription('GatewayWorker Server for ThinkPHP');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     */
    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        if (DIRECTORY_SEPARATOR === '\\') {
            $output->writeln("GatewayWorker Not Support On Windows.");
            exit(1);
        }

        if (!in_array($action, Workerman::ACTION_LIST)) {
            $output->writeln("Invalid argument action:{$action}, Expected " . implode('|', Workerman::ACTION_LIST) . " .");
            exit(1);
        }

        global $argv;
        array_shift($argv);
        array_shift($argv);
        array_unshift($argv, 'think', $action);
        if ('start' === $action) {
            $output->writeln('Starting GatewayWorker server...');
        }

        $logsDir = runtime_path('logs');
        Workerman::createDir($logsDir);
        Worker::$pidFile = runtime_path() . 'gateway_worker.pid';
        Worker::$stdoutFile = runtime_path() . 'logs/gateway_worker_stdout.log';
        Worker::$logFile = runtime_path() . 'logs/gateway_worker.log';
        TcpConnection::$defaultMaxPackageSize = 10 * 1024 * 1024;
        if (property_exists(Worker::class, 'statusFile')) {
            Worker::$statusFile = runtime_path() . 'gateway_worker.status';
        }
        if (property_exists(Worker::class, 'stopTimeout')) {
            Worker::$stopTimeout = 2;
        }

        // 开启守护进程模式
        if ($input->hasOption('daemon')) {
            Worker::$daemonize = true;
        }

        $option = Config::get('gateway_worker');
        foreach ($option as $name => $config) {
            Process::start($name, $config);
        }

        // Run worker
        Worker::runAll();
    }
}
