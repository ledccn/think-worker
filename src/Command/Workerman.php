<?php

namespace Ledc\ThinkWorker\Command;

use Ledc\ThinkWorker\HttpServer;
use Ledc\ThinkWorker\Process;
use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\App;
use think\facade\Config;
use Workerman\Worker;

/**
 * Workerman 命令行类
 */
class Workerman extends Command
{
    /**
     * 支持的动作列表
     */
    const ACTION_LIST = ['start', 'stop', 'reload', 'restart', 'status', 'connections'];

    /**
     * 创建目录
     * @param string $directory
     * @return void
     */
    public static function createDir(string $directory): void
    {
        if (!is_dir($directory)) {
            if (false === @mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create the "%s" directory', $directory));
            }
        }
        if (!is_writable($directory)) {
            throw new RuntimeException(sprintf('Unable to write in the "%s" directory', $directory));
        }
    }

    /**
     * @return void
     */
    public function configure()
    {
        $this->setName('workerman')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status|connections", 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the workerman server in daemon mode.')
            ->setDescription('Workerman Server for ThinkPHP');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     */
    public function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        if (DIRECTORY_SEPARATOR === "\\") {
            $output->writeln("<error>Not Support Windows.</error>");
            exit(1);
        }

        if (!in_array($action, static::ACTION_LIST)) {
            $output->writeln("<error>Invalid argument action:{$action}, Expected " . implode('|', static::ACTION_LIST) . " .</error>");
            exit(1);
        }

        global $argv;
        array_shift($argv);
        array_shift($argv);
        array_unshift($argv, 'think', $action);
        if ('start' === $action) {
            $output->writeln('Starting Workerman server...');
        }

        //全部配置
        $workerman = Config::get('workerman');
        $default = $workerman['default'];

        static::createDir(runtime_path('logs'));
        Process::init($default);

        //HTTP服务
        $worker = new HttpServer($default);
        $worker->setRootPath(App::getRootPath());

        // 开启守护进程模式
        if ($input->hasOption('daemon')) {
            Worker::$daemonize = true;
        }

        $process = $workerman['process'];
        foreach ($process as $name => $config) {
            Process::start($name, $config);
        }

        // Run worker
        Worker::runAll();
    }
}
