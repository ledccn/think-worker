<?php

namespace Ledc\ThinkWorker\Command;

use Ledc\ThinkWorker\Process;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
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

        $option = Config::get('gateway_worker');
        $default = $option['default'];

        $logsDir = runtime_path('logs');
        Workerman::createDir($logsDir);
        Process::init($default);

        // 开启守护进程模式
        if ($input->hasOption('daemon')) {
            Worker::$daemonize = true;
        }

        $process = $option['process'];
        foreach ($process as $name => $config) {
            Process::start($name, $config);
        }

        // Run worker
        Worker::runAll();
    }
}
