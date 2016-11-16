<?php
/**
 * Created by PhpStorm.
 * User: hailang
 * Date: 2015/12/3
 * Time: 16:22
 */

use Workerman\Worker;

class Websocket extends MY_Controller
{
	public function __construct()
	{

		parent::__construct();

	}

	public function index(){

		require_once APPPATH.'third_party/Workerman/Autoloader.php';
		exit();

		// 创建一个Worker监听2346端口，使用websocket协议通讯
		$ws_worker = new Worker("websocket://192.168.199.128:5000");

		// 启动4个进程对外提供服务
		//$ws_worker->count = 4;

		// 当收到客户端发来的数据后返回hello $data给客户端
		$ws_worker->onMessage = function($connection, $data)
		{
			// 向客户端发送hello $data
			$connection->send('hello ' . $data);
		};

		// 运行worker
		Worker::runAll();
	}


}