<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Ledc\ThinkWorker;

use Ledc\ThinkWorker\Command\GatewayWorker;
use Ledc\ThinkWorker\Command\Workerman;
use think\Service as BaseService;

/**
 * ThinkPHP服务
 */
class Service extends BaseService
{
    /**
     * 注册
     * @return void
     */
    public function register()
    {
        $this->commands([
            'workerman' => Workerman::class,
            'worker:gateway' => GatewayWorker::class,
        ]);
    }
}
