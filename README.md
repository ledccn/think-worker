ThinkPHP 6.0 Workerman 扩展
===============

## 安装

```
composer require ledc/think-worker
```

## 使用方法



### GatewayWorker

配置文件在`config/gateway_worker.php`

在命令行启动GatewayWorker
~~~
php think worker:gateway
~~~



### 自定义进程或HttpServer

配置文件在`config/workerman.php`

在命令行启动服务端
~~~
php think workerman
~~~



然后就可以通过浏览器直接访问当前应用

~~~
http://localhost:2346
~~~


测试websocket
~~~
// 假设服务端ip为127.0.0.1
ws = new WebSocket("ws://127.0.0.1:2345");
ws.onopen = function() {
    alert("连接成功");
    ws.send('tom');
    alert("给服务端发送一个字符串：tom");
};
ws.onmessage = function(e) {
    alert("收到服务端的消息：" + e.data);
};
~~~



linux下面可以支持下面指令
~~~
php think workerman [start|stop|reload|restart|status]
~~~

workerman的参数可以在应用配置目录下的workerman.php里面配置。
