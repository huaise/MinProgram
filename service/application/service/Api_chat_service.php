<?php
/**
 * 聊天相关接口服务，如获取聊天列表、发送消息、获取消息等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/3/9
 * Time: 11:14
 */

class Api_chat_service extends Api_Service {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('chat_model');
	}

	/**
	 *
	 * 接口名称：get_chat_list
	 * 接口功能：用户获取聊天列表（的更新）
	 * 接口编号：0801
	 * 接口加密名称：CoEYC3tlBiMLk0Nx
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$update_time		上次获取时间，首次获取时，可以设为0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['update_time']: 调用成功时返回，表示本次调用时间，客户端应使用该时间覆盖本地保存的上次调用该接口时间
	 * $output['chat_list']: 调用成功时返回，每一个元素均为一个聊天，包含chatid等基本信息，如quit为1则表示是退出了该聊天
	 * $output['chatuser_list']: 调用成功时返回，每一个元素均为一个聊天中的一个用户，包含cuid、chatid、uid等基本信息，如quit为1则表示是退出了该聊天
	 *
	 */
	public function get_chat_list($uid, $token, $update_time)
	{
//		$api_code = '0801';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

//		$user = $result['user'];

		$post_time = time();
		$update_time = (int)$update_time;

		$output['chat_list'] = array();
		$output['chatuser_list'] = array();

		// 如果不是首次获取，则获取该用户所退出的所有聊天
		if ($update_time > 0) {
			$this->chat_model->set_table_name('p_chatuser');
			$where = array('uid'=>$uid, 'active_flag'=>0, 'update_time >='=>$update_time);

			$result = $this->chat_model->select($where, FALSE, 'chatid');
			if (!empty($result)) {
				foreach ($result as $row) {
					$output['chat_list'][] = array('chatid'=>$row['chatid'], 'quit'=>1);
				}
			}
		}

		// 然后获取用户所在的所有聊天
		// 如果加入了群聊，这里要修改，需要取出用户的join_time，如果join_time大于update_time，则必须要取出chat和chatuser中的所有信息
		$chatid_array = $this->chat_model->get_active_chatid_by_uid($uid);

		// 如果没有聊天，这里就可以返回了
		if (empty($chatid_array)) {
			$output['state'] = TRUE;
			return $output;
		}

		// 根据用户所在的聊天，获取chat中的所有更新
		$this->chat_model->set_table_name('p_chat');
		$where = array('chatid'=>$chatid_array);
		if ($update_time > 0) {
			$where['update_time >='] = $update_time;
		}
		$result = $this->chat_model->select($where, FALSE, FIELD_CHAT_CLIENT);
		if (!empty($result)) {
			foreach ($result as $row) {
				$output['chat_list'][] = $this->chat_model->send_chat($row);
			}
		}

		// 根据用户所在的聊天，获取chatuser中的所有更新
		$this->chat_model->set_table_name('p_chatuser');
		$where = array('chatid'=>$chatid_array);
		// 不是首次获取，则获取增量更新
		if ($update_time > 0) {
			$where['update_time >='] = $update_time;
		}
		// 首次获取则只获取有效的用户
		else {
			$where['active_flag'] = 1;
		}
		$result = $this->chat_model->select($where, FALSE, FIELD_CHATUSER_CLIENT);
		if (!empty($result)) {
			foreach ($result as $row) {
				$output['chatuser_list'][] = $this->chat_model->send_chatuser($row);
			}
		}

		$output['state'] = TRUE;
		$output['update_time'] = $post_time;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_chat_detail
	 * 接口功能：用户获取指定聊天的详细信息
	 * 接口编号：0802
	 * 接口加密名称：PSv5cyBFXuoStgH6
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$chatid				聊天id
	 * @param int		$update_time		上次获取时间，首次获取时，可以设为0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['update_time']: 调用成功时返回，表示本次调用时间，客户端应使用该时间覆盖本地保存的上次调用该接口时间
	 * $output['chat']: 调用成功时返回，为该聊天的基本信息，包含chatid等基本信息，如quit为1则表示是退出了该聊天
	 * $output['chatuser_list']: 调用成功时返回，每一个元素均为一个聊天中的一个用户，包含cuid、chatid、uid等基本信息，如quit为1则表示是退出了该聊天
	 *
	 */
	public function get_chat_detail($uid, $token, $chatid, $update_time)
	{
		$api_code = '0802';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

//		$user = $result['user'];

		// 如果chatid不合法
		if (!is_id($chatid)) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "聊天id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 首先判断该用户是否在该聊天内
		$result = $this->chat_model->check_user_in_chat($uid, $chatid, '1');
		if ($result['state'] == FALSE) {
			return $result;
		}

		$post_time = time();

		// 获取该聊天
		$chat = $this->chat_model->get_chat_by_id($chatid, FIELD_CHAT_CLIENT);

		// 如果找不到聊天
		if (empty($chat)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您不在该聊天中";
			$output['ecode'] = "0000004";
			return $output;
		}

		$output['chat'] = $this->chat_model->send_chat($chat);

		// 获取该聊天中的所有（更新）的用户
		$update_time = (int)$update_time;
		$output['chatuser_list'] = array();

		$this->chat_model->set_table_name('p_chatuser');
		$where = array('chatid'=>$chatid);
		if ($update_time > 0) {
			$where['update_time >='] = $update_time;
		}
		$result = $this->chat_model->select($where, FALSE, FIELD_CHATUSER_CLIENT);
		if (!empty($result)) {
			foreach ($result as $row) {
				$output['chatuser_list'][] = $this->chat_model->send_chatuser($row);
			}
		}

		$output['state'] = TRUE;
		$output['update_time'] = $post_time;
		return $output;
	}


	/**
	 *
	 * 接口名称：chat_send
	 * 接口功能：用户发送一条信息
	 * 接口编号：0803
	 * 接口加密名称：DJWkJBKQ1ZIx4zSg
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$chatid				聊天id
	 * @param int		$type				信息类型，1表示普通文本信息，2表示语音，3表示图片，4表示文件，5表示表情
	 * @param string	$content			文本信息直接上传字符串，语音类型为长度，单位为秒，可以包含小数点，服务端以文本格式保存。其他类型可以为空
	 * @param int		$time				客户端发送时间，消息重发时应保证该时间相同
	 * @param array		$weixin_param		客户端无需上传,微信发送
	 * @param string	$attachment			附件名称，客户端无需上传，如果附件名称不为空，且类型为语音或者图片，则content字段应为语音长度或者图片小图的尺寸
	 *
	 * 注：上传的文件的key请设置为upfile
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['chatcontent']: 调用成功时返回，为发送的信息的最终保存结果，请按照最终保存结果更新本地数据库
	 *
	 */
	public function chat_send($uid, $token, $chatid, $type, $content, $time, $weixin_param=array(), $attachment='')
	{
		$api_code = '0803';

		// 涉及到客服聊天的接口，需要将chatid强行转为字符串，不然当chatid为负数时，数据库查询的时候会转义chatid为字段名，加上`，导致错误
		$chatid = (string)$chatid;

		// 如果uid为0，则表示是后台直接发送消息
		if ($uid === 0) {
			if ($token !== SERVICE_TOKEN) {
				$output['state'] = FALSE;
				$output['error'] = "token错误";
				$output['ecode'] = "0000001";
				return $output;
			}

			$chatuser = array('name'=>'恋爱小助手');
		}
		else {
			// 用户鉴权
			$result = $this->user_model->check_authority($uid, $token);
			if ($result['state'] == FALSE) {
				return $result;
			}

			//		$user = $result['user'];

			// 首先判断该用户是否在该聊天内
			$result = $this->chat_model->check_user_in_chat($uid, $chatid, 'name');
			if ($result['state'] == FALSE) {
				return $result;
			}
			$chatuser = $result['chatuser'];
		}


		// 检测信息类型是否合法
		$type = (int)$type;
		if ($type < CHATCONTENT_TYPE_TEXT || $type > CHATCONTENT_TYPE_SYSTEM) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "信息类型错误";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 构建信息内容
		$content = trim($content);
		$chatcontent['from_uid'] = $uid;
		$chatcontent['chatid'] = $chatid;
		$chatcontent['type'] = $type;
		$chatcontent['client_time'] = (int)$time;
		$chatcontent['content'] = '';
		$chatcontent['attachment'] = '';

		// 根据信息类型不同，进行不同操作
		// 如果是普通文本消息
		if ($type == CHATCONTENT_TYPE_TEXT || $type == CHATCONTENT_TYPE_SYSTEM) {
			// 检测内容是否为空
			if ($content == '') {
				//输出错误
				$output['state'] = FALSE;
				$output['error'] = "信息内容不能为空";
				$output['ecode'] = $api_code."002";
				return $output;
			}

			// 构建信息内容
			$chatcontent['content'] = $content;
			$chatcontent['attachment'] = "";
		}
		// 如果是语音
		elseif ($type == CHATCONTENT_TYPE_VOICE) {
			// 检测内容是否为空
			if ($content == '') {
				//输出错误
				$output['state'] = FALSE;
				$output['error'] = "语音长度不能为空";
				$output['ecode'] = $api_code."003";
				return $output;
			}

			// 如果附件没有上传
			if ($attachment == '') {

				// 语音上传
				$this->load->library('upload');
				$this->load->helper('string');

				if (!isset($_FILES['upfile']['name']) || $_FILES['upfile']['name']=='') {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "找不到上传的文件";
					$output['ecode'] = $api_code."004";
					return $output;
				}


				$config = array();
				$config['upload_path'] = './uploads/chat';
				$config['allowed_types'] = '*';							// CI自带的类型过滤功能，不支持amr，有需要可以自己写
				$config['file_name'] = time().strtolower(random_string('alnum',10));
				$config['overwrite'] = FALSE;

				$this->upload->initialize($config);

				// 如果上传失败
				if ( !$this->upload->do_upload('upfile')) {
					//输出错误
					$output['state'] = FALSE;
					$output['error'] = "文件上传失败：".$this->upload->display_errors('','');
					$output['ecode'] = $api_code."005";
					return $output;
				}

				$upload_result = $this->upload->data();

				// 上传七牛
				$this->upload->upload_to_qiniu(array(array('path'=>$config['upload_path'].'/'.$upload_result['file_name'],'key'=>'chat/'.$upload_result['file_name'])));

				// 构建信息内容
				$chatcontent['content'] = $content;
				$chatcontent['attachment'] = $upload_result['file_name'];
			}
			//如果附件已经上传了
			else{
				$chatcontent['content'] = $content;
				$chatcontent['attachment'] = $attachment;
			}
		}
		//如果是图片
		elseif ($type == CHATCONTENT_TYPE_IMAGE) {
			//如果附件没有上传
			if ($attachment == '') {

				// 图片上传
				$this->load->library('upload');
				$this->load->helper('string');

				// 单图上传
				if (!isset($_FILES['upfile']['name']) || $_FILES['upfile']['name']=='') {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "找不到上传的文件";
					$output['ecode'] = $api_code."004";
					return $output;
				}


				$config = array();
				$config['upload_path'] = './uploads/chat';
				$config['allowed_types'] = 'jpg|jpeg|png';
				$config['file_name'] = time().strtolower(random_string('alnum',10));
				$config['overwrite'] = FALSE;

				$this->upload->initialize($config);

				// 如果上传失败
				if ( !$this->upload->do_upload('upfile')) {
					//输出错误
					$output['state'] = FALSE;
					$output['error'] = "文件上传失败：".$this->upload->display_errors('','');
					$output['ecode'] = $api_code."005";
					return $output;
				}

				$upload_result = $this->upload->data();

				// 上传七牛
				$this->upload->upload_to_qiniu(array(array('path'=>$config['upload_path'].'/'.$upload_result['file_name'],'key'=>'chat/'.$upload_result['file_name'])));

				// 构建信息内容
				$chatcontent['content'] = $upload_result['image_width'].",".$upload_result['image_height'];
				$chatcontent['attachment'] = $upload_result['file_name'];
			}
			//如果附件已经上传了
			else{
				$chatcontent['content'] = $content;
				$chatcontent['attachment'] = $attachment;
			}

		}
		// 如果是文件
		elseif ($type == CHATCONTENT_TYPE_FILE) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "信息类型错误";
			$output['ecode'] = $api_code."001";
			return $output;
		}
		// 如果是表情
		elseif ($type == CHATCONTENT_TYPE_EMOJI) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "信息类型错误";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 写入数据库
		$result = $this->chat_model->insert_chatcontent($chatcontent);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		$chatcontent = $result['chatcontent'];

		// 如果不存在，且不是用户发送给客服，才推送
		if ($result['exist_flag']==FALSE && ($chatid>0 || $uid==0)) {
			// 推送消息
			$this->load->service('push_service');
			$message['type'] = $chatcontent['type'] + 90;
			$message['from_name'] = $chatuser['name'];

			$message['ccid'] = $chatcontent['ccid'];
			$message['chatid'] = $chatcontent['chatid'];
			$message['from_uid'] = $chatcontent['from_uid'];
			$message['content'] = $chatcontent['content'];
			$message['attachment'] = $chatcontent['attachment'];
			$message['send_time'] = $chatcontent['send_time'];
			$message['weixin_param'] = $weixin_param;

			$this->push_service->push_message($message);
		}

		$output['state'] = TRUE;
		$output['chatcontent'] = $this->chat_model->send_chatcontent($chatcontent);
		return $output;
	}


	/**
	 *
	 * 接口名称：get_chatcontent_update
	 * 接口功能：用户获取所有新的聊天信息
	 * 接口编号：0804
	 * 接口加密名称：Xz7vefIPIG3hXzvN
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$update_time		上次获取时间，长整形，单位为0.0001秒，首次获取时，可以设为0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['update_time']: 调用成功时返回，表示本次调用时间，长整形，单位为0.0001秒，客户端应使用该时间覆盖本地保存的上次调用该接口时间
	 * $output['chatcontent_list']: 调用成功时返回，每一个元素均为一条聊天信息，包含chatid等基本信息
	 *
	 */
	public function get_chatcontent_update($uid, $token, $update_time)
	{
//		$api_code = '0804';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
		$user = $result['user'];

		if ($update_time == 0) {
			$update_time = $user['last_chat_time'] * 10000;
		}

		//封装微秒时间
		$this->load->helper('date');
		$post_time = time();
		$post_time_micro = get_microtime();
		$output['chatcontent_list'] = array();

		// 获取用户所在的所有聊天
		$chatid_array = $this->chat_model->get_active_chatid_by_uid($uid);

		// 如果没有聊天，这里就可以返回了
		if (empty($chatid_array)) {
			$output['state'] = TRUE;
			return $output;
		}

		// 根据用户所在的聊天，获取chatcontent中的所有更新
		$this->chat_model->set_table_name('p_chatcontent');
		$where = array('chatid'=>$chatid_array);
		if ($update_time > 0) {
			$where['send_time >='] = $update_time;
		}
		$result = $this->chat_model->select($where, FALSE, FIELD_CHATCONTENT_CLIENT);
		if (!empty($result)) {
			foreach ($result as $row) {
				$output['chatcontent_list'][] = $this->chat_model->send_chatcontent($row);
			}
		}

		// 更新数据库中的last_chat_time
		if ($post_time - $user['last_chat_time'] >= 3600) {
			$this->user_model->update_userbase(array('last_chat_time'=>$post_time), array('uid'=>$uid));
		}

		$output['state'] = TRUE;
		$output['update_time'] = $post_time_micro;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_chatcontent_history
	 * 接口功能：用户获取某个指定聊天的历史信息
	 * 接口编号：0805
	 * 接口加密名称：rItTOcQwN0pgqQtA
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$chatid				聊天id
	 * @param int		$start_time			开始时间，长整形，单位为0.0001秒，首次获取时，可以设为0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['chatcontent_list']: 调用成功时返回，每一个元素均为一条聊天信息，包含chatid等基本信息
	 * $output['news_list']: 调用成功且为客服聊天时返回，每一个元素均为显示时间在本次获取到的聊天消息范围内的一条新闻，包含nid, title, images, url等基本信息
	 *
	 */
	public function get_chatcontent_history($uid, $token, $chatid, $start_time)
	{
//		$api_code = '0805';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		// 涉及到客服聊天的接口，需要将chatid强行转为字符串，不然当chatid为负数时，数据库查询的时候会转义chatid为字段名，加上`，导致错误
		$chatid = (string)$chatid;

		$output['chatcontent_list'] = array();

		// 首先判断该用户是否在该聊天内
		$result = $this->chat_model->check_user_in_chat($uid, $chatid, '1');
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$chatuser = $result['chatuser'];

		// 获取用户一定条数的聊天的历史信息
		$this->chat_model->set_table_name('p_chatcontent');
		$where = array('chatid'=>$chatid);
		if ($start_time > 0) {
			$where['send_time <='] = $start_time;
		}
		$result = $this->chat_model->select($where, FALSE, FIELD_CHATCONTENT_CLIENT, MAX_CHATCONTENT_PER_GET, 'send_time DESC');
		if (!empty($result)) {
			foreach ($result as $row) {
				$output['chatcontent_list'][] = $this->chat_model->send_chatcontent($row);
			}
		}

		// 如果是客服聊天，需要返回新闻
		if ($chatid <= 0) {
			$output['news_list'] = array();
			$this->load->model('news_model');

			// 如果本次没有获取到任何聊天内容，说明客服聊天已经获取光了，此时就去获取显示时间更老的新闻
			if (empty($output['chatcontent_list'])) {
				if ($start_time > 0) {
					// 时间是向下取整，因为此时已经没有聊天消息了
					$news_start_time = floor($start_time / 10000);
					$news_list = $this->news_model->select(array('display_time <='=>$news_start_time, 'active_flag'=>1), FALSE, FIELD_NEWS_CLIENT, 10, 'display_time DESC');
				}
				else {
					$news_list = $this->news_model->select(array('active_flag'=>1), FALSE, FIELD_NEWS_CLIENT, 10, 'display_time DESC');
				}
			}
			// 如果只获取到了一条，且是首次获取，说明这是唯一一条了，需要返回该条聊天消息之后的所有新闻
			else if (count($output['chatcontent_list']) == 1 && $start_time == 0) {
				$news_start_time = ceil($output['chatcontent_list'][0]['send_time'] / 10000);
				$news_list = $this->news_model->select(array('display_time >='=>$news_start_time, 'active_flag'=>1), FALSE, FIELD_NEWS_CLIENT);
			}
			// 如果只获取到了一条，且不是首次获取，说明这是最后一条了
			else if (count($output['chatcontent_list']) == 1) {
				// 时间是向上取整，因为最后一条消息之前已经获取到了，比该消息更新的新闻也肯定获取到了，因此下面用的是小于号
				$news_start_time = ceil($output['chatcontent_list'][0]['send_time'] / 10000);
				$news_list = $this->news_model->select(array('display_time <'=>$news_start_time, 'active_flag'=>1), FALSE, FIELD_NEWS_CLIENT, 10, 'display_time DESC');
			}
			// 如果获取到多于一条，且是首次获取，需要返回本次获取的最老一条的聊天消息之后的所有新闻
			else if ($start_time == 0) {
				// 时间全部是向上取整，因为无法确定本次获取的最老一条的聊天消息是否是所在秒中的第一条，即有可能有一条没有获取的消息，和本次获取的最老一条的聊天消息在同一秒内
				$news_start_time = ceil($output['chatcontent_list'][count($output['chatcontent_list'])-1]['send_time'] / 10000);
				$news_list = $this->news_model->select(array('display_time >='=>$news_start_time, 'active_flag'=>1), FALSE, FIELD_NEWS_CLIENT);
			}
			// 如果获取到多于一条，且不是首次获取，需要返回本次获取到的聊天消息所在时间范围内的新闻
			else {
				// 时间全部是向上取整，因为无法确定本次获取的最老一条的聊天消息是否是所在秒中的第一条，即有可能有一条没有获取的消息，和本次获取的最老一条的聊天消息在同一秒内
				$news_start_time = ceil($output['chatcontent_list'][count($output['chatcontent_list'])-1]['send_time'] / 10000);	// 最后一条的时间，最老的一条，时间戳数值最小
				$news_end_time = ceil($output['chatcontent_list'][0]['send_time'] / 10000);		// 第一条的时间，最新的一条，时间戳数值最大
				$news_list = $this->news_model->select(array('display_time >='=>$news_start_time, 'display_time <'=>$news_end_time, 'active_flag'=>1), FALSE, FIELD_NEWS_CLIENT);
			}


			if (!empty($news_list)) {
				foreach ($news_list as $row) {
					$output['news_list'][] = $this->news_model->send($row);
				}
			}

		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：report_input
	 * 接口功能：用户汇报正在输入/取消输入
	 * 接口编号：0806
	 * 接口加密名称：Y2NuaeLVK2M0d8J6
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$chatid				聊天id
	 * @param int		$is_stop			是开始还是停止输入，如果是开始则为0，如果是停止则为1（目前的逻辑中不需要传输此参数）
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function report_input($uid, $token, $chatid, $is_stop)
	{
		$api_code = '0806';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		// 如果chatid不合法
		if (!is_id($chatid)) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "聊天id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 首先判断该用户是否在该聊天内
		$result = $this->chat_model->check_user_in_chat($uid, $chatid, '1');
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 判断推送类型
		if ($is_stop) {
			$type = PUSH_CHAT_STOP_INPUT;
		}
		else {
			$type = PUSH_CHAT_START_INPUT;
		}

		// 推送消息
		$this->load->service('push_service');
		$message['type'] = $type;
		$message['chatid'] = $chatid;
		$message['from_uid'] = $uid;
		$this->push_service->push_message($message);

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：chat_delete
	 * 接口功能：用户删除/退出一个聊天
	 * 接口编号：0807
	 * 接口加密名称：qhUlc5EAhWWFRPFD
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$chatid				聊天id
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function chat_delete($uid, $token, $chatid)
	{
		$api_code = '0807';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		// 如果chatid不合法
		if (!is_id($chatid)) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "聊天id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 首先判断该用户是否在该聊天内
		$result = $this->chat_model->check_user_in_chat($uid, $chatid, '1');
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$chatuser = $result['chatuser'];

		// 获取该聊天
		$chat = $this->chat_model->get_chat_by_id($chatid, 'type');

		// 如果找不到聊天
		if (empty($chat)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您不在该聊天中";
			$output['ecode'] = "0000004";
			return $output;
		}

		// 根据聊天的类型，进行不同的判断

		// 目前仅支持删除普通点对点聊天
		if ($chat['type'] != 1) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "聊天类型不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 删除数据库中的聊天
		if (!$this->chat_model->delete_by_chatid($chatid, $uid, 1, 2)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_random_truth
	 * 接口功能：用户获取一个随机的真心话
	 * 接口编号：0808
	 * 接口加密名称：nMLk3pzCOLTa5dLv
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['truth']: 为一条真心话，包含id和content
	 *
	 */
	public function get_random_truth($uid, $token)
	{
		$api_code = '0808';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		$this->load->model('truth_model');
		$truth = $this->truth_model->get_random_one();

		// 如果找不到
		if (empty($truth)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到真心话";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		$output['truth'] = $truth;
		$output['state'] = TRUE;
		return $output;
	}

	/**
	 * 接口名称：get_user_is_app
	 * 接口功能：获取用户类型， 是否为app用户
	 * 接口编号：0809
	 * 接口加密名称：Q30i7XHl2tiLKvh
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$chatid				聊天id
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['is_app']: 1表示是0表示不是
	 */
	public function get_user_is_app($uid, $token, $chatid){
		$api_code = '0809';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 如果chatid不合法
		if (!is_id($chatid)) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "聊天id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 首先判断该用户是否在该聊天内
		$result = $this->chat_model->check_user_in_chat($uid, $chatid, '1');
		if ($result['state'] == FALSE) {
			return $result;
		}

		//获取该聊天内的用户
		$user_list = $this->chat_model->get_user_list_by_chatid($chatid);
		foreach ($user_list as $key=>$val){
			if ($uid != $val['uid']){
				//收信人 判断是否为已下载app
				$user_info = $this->user_model->get_row_by_id($val['uid'], 'p_useredition');
				if (!in_array($user_info['last_platform'], array(1,2,3))) {
					$output['state'] = TRUE;
					$output['is_app'] = 0;
					return $output;
				}
			}
		}

		$output['state'] = TRUE;
		$output['is_app'] = 1;
		return $output;

	}

}