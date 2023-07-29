ThinkPHP 6.0 Workerman 扩展
===============

## 安装

```
composer require ledc/think-worker
```

## 使用方法



### GatewayWorker

在命令行启动GatewayWorker
~~~
php think worker:gateway
~~~



### 自定义进程或HttpServer

在命令行启动服务端
~~~
php think workerman
~~~

然后就可以通过浏览器直接访问当前应用

~~~
http://localhost:2346
~~~

linux下面可以支持下面指令
~~~
php think workerman [start|stop|reload|restart|status]
~~~

workerman的参数可以在应用配置目录下的workerman.php里面配置。
