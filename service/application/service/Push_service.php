<?php
/**
 * 推送服务
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/2/17
 * Time: 11:55
 */

class Push_service extends MY_Service {

	protected $_fields = "`p_useredition`.uid, `p_useredition`.last_platform, `p_useredition`.last_version, `p_useredition`.gc_id, `p_useredition`.xc_id, `p_useredition`.ac_id, `p_useredition`.gc_id_ios, `p_useredition`.openid";

	public function __construct()
	{
		parent::__construct();
		$this->_log_tag = 'push';
	}


	/**
	 * 推送消息，异步推送
	 * @param array		$message		消息体
	 * @param bool		$sync_flag		是否同步推送
	 * @param bool		$all_flag		是否是向所有用户推送
	 * @return NULL
	 */
	public function push_message($message, $sync_flag=FALSE, $all_flag=FALSE) {

		// 只有现网才允许全部推送
		if (DEBUG) {
			$all_flag = FALSE;
		}

		// 如果不是全部推送
		if (!$all_flag) {
			// 首先获取需要推送的用户
			$push_user_list = $this->push_message_prepare($message);

			if (empty($push_user_list)) {
				return NULL;
			}
		}
		else {
			$push_user_list = array();
		}

		//获取全局变量
		$this->load->service("wx_service");
		$access_token = $this->wx_service->get_access_token();

		// 如果配置了PCNTL，且不要求同步推送
		if (config_item('pcntl') && !$sync_flag) {
			if (!function_exists('shutdown')) {
				function shutdown() {
					posix_kill(posix_getpid(), SIGHUP);
				}
			}

			// Do some initial processing


			// Switch over to daemon mode.

			if ($pid = pcntl_fork()) {
				pcntl_wait($status);
				return NULL;	 // Parent
			}
			else{
				ob_end_clean(); // Discard the output buffer and close

				// Close all of the standard file descriptors as we are running as a daemon.
				fclose(defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
				fclose(defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));
				fclose(defined('STDERR') ? STDERR : fopen('php://stderr', 'w'));

				register_shutdown_function('shutdown');

				if (posix_setsid() < 0)
					exit(0);

				if ($pid = pcntl_fork())
					exit(0);	 // Parent

				// Now running as a daemon. This process will even survive
				// an apachectl stop.

				$this->_push_message_request($message, $push_user_list, $all_flag, $access_token);
				exit(0);
			}
		}
		else {
			$this->_push_message_request($message, $push_user_list, $all_flag, $access_token);
		}
	}

	/**
	 * 准备推送消息，会返回接收消息的用户
	 * @param array		$message		消息体，指针形式
	 * @return array	接收消息的用户
	 */
	public function push_message_prepare(&$message) {

		$push_user_list = array();

		$type = $message['type'];

		switch ($type) {
			//如果是新闻推送
			case PUSH_NEWS:
				// 生成推送内容
				$message['content'] = $message['title'];

				$this->load->helper('url');
				$message['image'] = get_attachment_url($message['image'], 'news');

				// 获取接收消息的用户
				$push_user_list = $this->_select_push_user_for_news($message['custom_push']);
				break;
			// 如果是调用接口推送
			case PUSH_CALL_PORT:
				// 生成推送内容
				$message['content'] = "";

				// 获取接收消息的用户
				$push_user_list = $this->_select_push_user($message['uid']);
				break;
			// 如果是正在输入推送
			case PUSH_CHAT_START_INPUT:
			case PUSH_CHAT_STOP_INPUT:
				// 生成推送内容
				$message['content'] = "";

				// 获取接收消息的用户
				$push_user_list = $this->_select_push_user_for_chat($message['chatid'], $message['from_uid']);

				break;
			// 如果是匹配成功
			case PUSH_MATCH_SUCCEED_INFORM:
				// 生成推送内容
				$message['content'] = "您有新的配对了，快来看看吧";

				// 获取接收消息的用户
				$push_user_list = $this->_select_push_user($message['uid']);
				break;
			// 如果是收到邀请
			case PUSH_INVTIE_RECEIVE:
				// 生成推送内容
				$message['content'] = "您收到了一条邀请，ta会是谁呢？";

				// 获取接收消息的用户
				$push_user_list = $this->_select_push_user($message['uid']);
				break;
			// 如果是邀请被接受
			case PUSH_INVTIE_ACCEPT:
				// 生成推送内容
				$message['content'] = "有人接受了您的邀请，快去约会吧";

				// 获取接收消息的用户
				$push_user_list = $this->_select_push_user($message['uid']);
				break;
			// 如果是聊天消息
			case PUSH_CHAT_TEXT_MESSAGE:
			case PUSH_CHAT_VOICE_MESSAGE:
			case PUSH_CHAT_IMAGE_MESSAGE:
			case PUSH_CHAT_FILE_MESSAGE:
			case PUSH_CHAT_EMOJI_MESSAGE:
			case PUSH_CHAT_SYSTEM_MESSAGE:
				// 生成推送内容
				$this->load->helper('url');
				$message['attachment'] = get_attachment_url($message['attachment'], 'chat');

				// 获取接收消息的用户
				$push_user_list = $this->_select_push_user_for_chat($message['chatid'], $message['from_uid']);
				break;
			default:
				break;
		}

		return $push_user_list;
	}


	/**
	 * 推送消息，私有函数
	 * @param array		$message			消息体，指针形式
	 * @param array		$push_user_list		接收消息的用户列表
	 * @param bool		$all_flag			是否是向所有用户推送
	 * @param string	$access_token		微信的access_token
	 * @return NULL
	 */
	private function _push_message_request($message, $push_user_list, $all_flag=FALSE, $access_token) {
		// 只有现网才允许全部推送
		if (DEBUG) {
			$all_flag = FALSE;
		}

		// 需要合法的接收推送的用户
		if (!$all_flag && count($push_user_list)==0) {
			return NULL;
		}

		// 单推且是聊天消息，才走apns的补推逻辑
		if (!$all_flag && count($push_user_list)==1 && in_array($message['type'], array(PUSH_CHAT_TEXT_MESSAGE, PUSH_CHAT_VOICE_MESSAGE, PUSH_CHAT_IMAGE_MESSAGE, PUSH_CHAT_FILE_MESSAGE, PUSH_CHAT_EMOJI_MESSAGE, PUSH_CHAT_SYSTEM_MESSAGE))) {
			$apns_repush_flag = TRUE;
		}
		else {
			$apns_repush_flag = FALSE;
		}

		// 唯一标识
		$result = microtime(true);
		$str_temp = explode(".", $result);
		$message['r'] = ($str_temp[0]%10000) * 10000 + $str_temp[1];

		// 初始化数组
		$push_user_list_1 = array();				//安卓个推
		$push_user_list_2_getui = array();			//IOS个推(企业版)
		$push_user_list_3_getui = array();			//IOS个推(AppStore)
		$push_user_list_2 = array();				//IOS(企业版)
		$push_user_list_3 = array();				//IOS(AppStore)
		$push_user_list_4 = array();				//微信

		//平台分为安卓、IOS以及个推IOS三种
		foreach($push_user_list as $index => $row) {
			//首先判断用户的平台，分为安卓、IOS以及个推IOS三种，与通知系统有区别

			//安卓（个推）
			if ($row['gc_id']!="" && $row['last_platform']==PLATFORM_ANDROID) {
				$push_user_list_1[] = $row;
				continue;
			}

			// iOS(企业版)
			if ($row['last_platform']==PLATFORM_IOS_INHOUSE) {
				// 如果有个推的id，则走个推
				if ($row['gc_id_ios'] != "") {
					$push_user_list_2_getui[] = $row;
					// 如果满足apn补推的逻辑，则再走一遍apn
					if ($apns_repush_flag && $row['ac_id'] != "") {
						$push_user_list_2[] = $row;
					}
				}
				// 如果没有个推的id，但是有apn的id，则走apn
				else if ($row['ac_id'] != "") {
					$push_user_list_2[] = $row;
				}
				continue;
			}

			// iOS(AppStore)
			if ($row['last_platform']==PLATFORM_IOS_APPSTORE) {
				// 如果有个推的id，则走个推
				if ($row['gc_id_ios'] != "") {
					$push_user_list_3_getui[] = $row;
					// 如果满足apn补推的逻辑，则再走一遍apn
					if ($apns_repush_flag && $row['ac_id'] != "") {
						$push_user_list_3[] = $row;
					}
				}
				// 如果没有个推的id，但是有apn的id，则走apn
				else if ($row['ac_id'] != "") {
					$push_user_list_3[] = $row;
				}
				continue;
			}

			//微信
			if ($row['last_platform']==PLATFORM_WEIXIN && $row['openid'] != "") {
				$push_user_list_4[] = $row;
				continue;
			}

		}

		// 引入个推文件
		require_once(dirname(__FILE__) . '/../third_party/igetui/IGt.Push.php');

		//发送安卓消息
		if (!empty($push_user_list_1) || $all_flag) {
			$this->_push_message_request_a($message, $push_user_list_1, $all_flag);
		}

		//发送IOS个推消息(企业版)
		if (!empty($push_user_list_2_getui) || $all_flag) {
			$this->_push_message_request_i_getui($message, $push_user_list_2_getui, PLATFORM_IOS_INHOUSE, $apns_repush_flag, $all_flag);
		}
		//发送IOS个推消息(AppStore)
		if (!empty($push_user_list_3_getui) || $all_flag) {
			$this->_push_message_request_i_getui($message, $push_user_list_3_getui, PLATFORM_IOS_APPSTORE, $apns_repush_flag, $all_flag);
		}

		//发送IOS消息(企业版)
		if (!empty($push_user_list_2)) {
			$this->_push_message_request_i($message, $push_user_list_2, PLATFORM_IOS_INHOUSE);
		}
		//发送IOS消息(AppStore)
		if (!empty($push_user_list_3)) {
			$this->_push_message_request_i($message, $push_user_list_3, PLATFORM_IOS_APPSTORE);
		}

		//发送微信信息
		if (!empty($push_user_list_4)) {
			$this->_push_message_request_w($message, $push_user_list_4, PLATFORM_WEIXIN, $access_token);
		}
	}

	/**
	 * 推送消息（安卓个推），私有函数
	 * @param array		$message			消息体，指针形式
	 * @param array		$push_user_list		接收消息的用户列表
	 * @param bool		$all_flag			是否是向所有用户推送
	 * @return NULL
	 */
	private function _push_message_request_a($message, $push_user_list, $all_flag=FALSE) {
		// 只有现网才允许全部推送
		if (DEBUG) {
			$all_flag = FALSE;
		}

		if (!$all_flag && count($push_user_list)==0) {
			return NULL;
		}

		$appkey = GT_APPKEY_1;
		$appid =  GT_APPID_1;
		$master_secret = constant("GT_MASTERSECRET_1");
		$igt = new IGeTui(GT_HOST, $appkey, $master_secret, FALSE);

		// 创建透传消息
		$template =  new IGtTransmissionTemplate();

		$template ->set_appId($appid);			// 应用appid
		$template ->set_appkey($appkey);		// 应用appkey
		$template ->set_transmissionType(2);	// 收到消息是否立即启动应用，1为立即启动，2则广播等待客户端自启动

		// 消息是否走离线
		$is_offline = TRUE;

		// 生成消息体
		switch($message['type']) {
			// 新闻推送
			case PUSH_NEWS:
				$transmissionContent = array(
					'nid' => $message['nid'],
					'type' => $message['type'],
					"title" => $message['title'],
					"url" => $message['url'],
					"image" => $message['image'],
					"display_time" => $message['display_time']
				);
				break;

			// 调用接口推送
			case PUSH_CALL_PORT:
				$transmissionContent = array(
					"type" => $message['type'],
					"value" => $message['value']
				);
				break;

			// 正在输入消息
			case PUSH_CHAT_START_INPUT:
			case PUSH_CHAT_STOP_INPUT:
				$transmissionContent = array(
					'chatid' => $message['chatid'],
					'type' => $message['type']
				);
				$is_offline = FALSE;
				break;

			//聊天消息
			case PUSH_CHAT_TEXT_MESSAGE:
			case PUSH_CHAT_VOICE_MESSAGE:
			case PUSH_CHAT_IMAGE_MESSAGE:
			case PUSH_CHAT_FILE_MESSAGE:
			case PUSH_CHAT_EMOJI_MESSAGE:
			case PUSH_CHAT_SYSTEM_MESSAGE:
				$transmissionContent = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'from_name' => $message['from_name'],
					'content' => $message['content'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;

			// 匹配成功
			case PUSH_MATCH_SUCCEED_INFORM:
				$transmissionContent = array(
					"type" => $message['type'],
					"content" => $message['content'],
				);
				break;
			// 收到邀请
			case PUSH_INVTIE_RECEIVE:
				// 邀请被接受
			case PUSH_INVTIE_ACCEPT:
				$transmissionContent = array(
					"type" => $message['type'],
//					"iid" => $message['iid'],
					"content" => $message['content'],
				);
				break;
			default:
				return false;
		}

		if (isset($message['r'])) {
			$transmissionContent['r'] = $message['r'];
		}

		$transmissionContent = json_encode($transmissionContent);
		$template -> set_transmissionContent($transmissionContent);

		// 个推信息体
		if ($all_flag) {
			$gt_message = new IGtAppMessage();
			$gt_message -> set_appIdList(array($appid));

			// 只对安卓推送
			$phoneTypeList=array('ANDROID');
			$cdt = new AppConditions();
			$cdt->addCondition2(AppConditions::PHONE_TYPE, $phoneTypeList);
			$gt_message->set_conditions($cdt->getCondition());
		}
		else if (count($push_user_list) > 1) {
			$gt_message = new IGtListMessage();
		}
		else {
			$gt_message = new IGtSingleMessage();
		}

		$gt_message->set_isOffline($is_offline);//是否离线
		if ($is_offline) {
			$gt_message->set_offlineExpireTime(43200000);//离线时间，毫秒，1000*3600*12=43200000
		}
		else {
			$gt_message->set_offlineExpireTime(1);//离线时间
		}
		$gt_message->set_data($template);//设置推送消息类型

		// 如果是推送给全部，调用推送给应用的接口
		if ($all_flag) {
			$result = $igt->pushMessageToApp($gt_message);

			// 保存日志
			$para_array = array();
			$para_array['message'] = $message;
			$para_array['platform'] = '1';
			$para_array['method'] = 'all';
			$para_array['result'] = $result;
			$this->log->write_log_json($para_array, $this->_log_tag);
		}
		// 如果有多人，则调用群推
		else if (count($push_user_list) > 1) {
			$contentId = $igt->getContentId($gt_message);

			//接收方
			$count = 0;
			$target_list = array();
			foreach($push_user_list as $row) {
				$target = new IGtTarget();
				$target->set_appId($appid);
				$target->set_clientId($row['gc_id']);
				$target_list[] = $target;
				$count++;
				//如果多于50人，则推送
				if ($count >= 50) {
					//推送消息
					$result = $igt->pushMessageToList($contentId, $target_list);

					// 保存日志
					$para_array = array();
					$para_array['message'] = $message;
					$para_array['count'] = count($target_list);
					$para_array['platform'] = '1';
					$para_array['method'] = 'list';
					$para_array['result'] = $result;
					$this->log->write_log_json($para_array, $this->_log_tag);

					//重置数据
					$count = 0;
					$target_list = array();
				}
			}

			if (!empty($target_list)) {
				//推送消息
				$result = $igt->pushMessageToList($contentId, $target_list);

				// 保存日志
				$para_array = array();
				$para_array['message'] = $message;
				$para_array['count'] = count($target_list);
				$para_array['platform'] = '1';
				$para_array['method'] = 'list';
				$para_array['result'] = $result;
				$this->log->write_log_json($para_array, $this->_log_tag);
			}
		}
		// 如果只有一个人
		else{
			//接收方
			$target = new IGtTarget();
			$target->set_appId($appid);
			$target->set_clientId($push_user_list[0]['gc_id']);

			try {
				$result = $igt->pushMessageToSingle($gt_message, $target);
			}catch(RequestException $e) {
				$requstId = $e.getRequestId();
				$result = $igt->pushMessageToSingle($gt_message, $target, $requstId);
			}

			// 保存日志
			$para_array = array();
			$para_array['message'] = $message;
			$para_array['cid'] = $push_user_list[0]['gc_id'];
			$para_array['uid'] = $push_user_list[0]['uid'];
			$para_array['platform'] = '1';
			$para_array['method'] = 'single';
			$para_array['result'] = $result;
			$this->log->write_log_json($para_array, $this->_log_tag);
			unset($target);
		}
		unset($template);
		unset($gt_message);
		unset($target_list);
		unset($result);
		unset($igt);

		return NULL;
	}

	/**
	 * 推送消息（iOS个推），私有函数
	 * @param array		$message			消息体，指针形式
	 * @param array		$push_user_list		接收消息的用户列表
	 * @param int		$platform			对应的平台
	 * @param bool		$apns_repush_flag	该消息是否启用apns的补推逻辑
	 * @param bool		$all_flag			是否是向所有用户推送
	 * @return NULL
	 */
	private function _push_message_request_i_getui($message, $push_user_list, $platform, $apns_repush_flag, $all_flag=FALSE) {
		// 只有现网才允许全部推送
		if (DEBUG) {
			$all_flag = FALSE;
		}

		if (!$all_flag && count($push_user_list)==0) {
			return null;
		}

		// 是否走离线
		$is_offline = TRUE;

		// 生成内容
		// $content是用来显示在通知栏的内容，$transmissionContent_apn是用在apn透传的内容
		switch($message['type']) {
			// 新闻推送
			case PUSH_NEWS:
				$content = $message['content'];
				$transmissionContent_apn = array(
					'nid' => $message['nid'],
					'type' => $message['type'],
					"title" => $message['title'],
					"url" => $message['url'],
					"display_time" => $message['display_time'],
					"image" => $message['image']
				);
				break;

			// 调用接口推送
			case PUSH_CALL_PORT:
				$content = '';
				$transmissionContent_apn = array(
					"type" => $message['type'],
					"value" => $message['value']
				);
				break;

			// 正在输入消息
			case PUSH_CHAT_START_INPUT:
			case PUSH_CHAT_STOP_INPUT:
				$content = '';
				$transmissionContent_apn = array(
					'chatid' => $message['chatid'],
					'type' => $message['type']
				);
				$is_offline = FALSE;
				break;

			// 文本聊天消息
			case PUSH_CHAT_TEXT_MESSAGE:
				$content = $message['content'];
				if ($message['from_name'] != "") {
					$content = $message['from_name'].":".$content;
				}
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 语音聊天消息
			case PUSH_CHAT_VOICE_MESSAGE:
				$content = $message['from_name']."发来一段语音";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 图片聊天消息
			case PUSH_CHAT_IMAGE_MESSAGE:
				$content = $message['from_name']."发来一张照片";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 文件聊天消息
			case PUSH_CHAT_FILE_MESSAGE:
				$content = $message['from_name']."发来一个文件";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 表情聊天消息
			case PUSH_CHAT_EMOJI_MESSAGE:
				$content = $message['from_name']."发来一个表情";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 系统提醒消息
			case PUSH_CHAT_SYSTEM_MESSAGE:
				$content = $message['content'];
				if ($message['from_name'] != "") {
					$content = $message['from_name'].":".$content;
				}
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;

			// 匹配成功
			case PUSH_MATCH_SUCCEED_INFORM:
				$content = $message['content'];
				$transmissionContent_apn = array(
					"type" => $message['type'],
					"content" => str_repeat($content, 1),
				);
				break;
			// 收到邀请
			case PUSH_INVTIE_RECEIVE:
				// 邀请被接受
			case PUSH_INVTIE_ACCEPT:
				$content = $message['content'];
				$transmissionContent_apn = array(
					"type" => $message['type'],
//					"iid" => $message['iid'],
					"content" => str_repeat($content, 1),
				);
				break;

			default:
				return false;
		}

		// 用在个推透传的内容，当有通知栏内容的时候，会比APN多一个内容字段，经过测试，这里不需要限制长度
		$transmissionContent_getui = $transmissionContent_apn;
		if ($content != '') {
			$transmissionContent_getui['content'] = (isset($message['content']) ? $message['content'] : $content);
		}

		// 根据不同的平台以及是否是现网，选择不同的推送id
		// appstore版
		if ($platform == PLATFORM_IOS_APPSTORE) {
			$appkey = constant("GT_APPKEY_3");
			$appid =  constant("GT_APPID_3");
			$master_secret = constant("GT_MASTERSECRET_3");
		}
		// 企业版
		else {
			if (DEBUG) {
				$appkey = constant("GT_APPKEY_1");
				$appid =  constant("GT_APPID_1");
				$master_secret = constant("GT_MASTERSECRET_1");
			}
			else{
				$appkey = constant("GT_APPKEY_2");
				$appid =  constant("GT_APPID_2");
				$master_secret = constant("GT_MASTERSECRET_2");
			}
		}

		$igt = new IGeTui(GT_HOST, $appkey, $master_secret);

		//创建透传消息
		$template =  new IGtTransmissionTemplate();

		$template -> set_appId($appid);			// 应用appid
		$template -> set_appkey($appkey);		// 应用appkey
		$template -> set_transmissionType(2);	// 收到消息是否立即启动应用，1为立即启动，2则广播等待客户端自启动

		// 如果通知栏有内容，或者是启用apns补推逻辑，那么客户端就有可能收到两条，此时要加入唯一标识
		if ($content!='' || $apns_repush_flag) {
			if (isset($message['r'])) {
				$transmissionContent_getui['r'] = $message['r'];
			}
		}

		// 转为apns的条件为：
		// 通知栏有内容 且 该消息不启用apns补推逻辑
		if ($content!='' && !$apns_repush_flag) {
			// 加入唯一标识
			if (isset($message['r'])) {
				$transmissionContent_apn['r'] = $message['r'];
			}

			$apn = new IGtAPNPayload();
			$apn->badge = 1;
			$apn->sound = "";
			$apn->add_customMsg("custom", json_encode($transmissionContent_apn));
			$apn->contentAvailable = 1;

			// 计算剩余长度，最大长度为2048字节，当加上alert之后，会增加10字节左右的冗余，因此最大长度设置为2028
			// 一个汉字是3个字节，json之后是6字节，一个英文或标点是1个字节，json之后是1至2字节，所以json之后的长度约为json之前的两倍
			mb_internal_encoding("UTF-8");
			$remain_length = floor((2028 - strlen($apn->get_payload())) / 2);

			// 如果超出了限制，则需要截取
			if (strlen($content) > $remain_length) {
				$content = mb_strcut($content, 0, $remain_length);
			}

			$alertmsg=new SimpleAlertMsg();		//$alertmsg是通知栏的显示内容
			$alertmsg->alertMsg=$content;
			$apn->alertMsg=$alertmsg;
			$template->set_apnInfo($apn);		// 如果$transmissionContent_json和$alertmsg的长度之和大于2048则这里会报错
		}

		$template -> set_transmissionContent(json_encode($transmissionContent_getui));

		//个推信息体
		if ($all_flag) {
			$gt_message = new IGtAppMessage();
			$gt_message -> set_appIdList(array($appid));

			// 只对IOS推送
			$phoneTypeList=array('IOS');
			$cdt = new AppConditions();
			$cdt->addCondition2(AppConditions::PHONE_TYPE, $phoneTypeList);
			$gt_message->set_conditions($cdt->getCondition());
		}
		else if (count($push_user_list) > 1) {
			$gt_message = new IGtListMessage();
		}
		else {
			$gt_message = new IGtSingleMessage();
		}

		$gt_message->set_isOffline($is_offline);//是否离线
		if ($is_offline) {
			$gt_message->set_offlineExpireTime(43200000);//离线时间，毫秒，1000*3600*12=43200000
		}
		else {
			$gt_message->set_offlineExpireTime(1);//离线时间
		}
		$gt_message->set_data($template);//设置推送消息类型

		//如果是推送给全部，调用推送给应用的接口
		if ($all_flag) {
			$result = $igt->pushMessageToApp($gt_message);

			// 保存日志
			$para_array = array();
			$para_array['message'] = $message;
			$para_array['platform'] = $platform.'_g';
			$para_array['method'] = 'all';
			$para_array['result'] = $result;
			$this->log->write_log_json($para_array, $this->_log_tag);
		}
		// 如果有多人，则调用群推
		else if (count($push_user_list) > 1) {
			$contentId = $igt->getContentId($gt_message);

			//接收方
			$count = 0;
			$target_list = array();
			foreach($push_user_list as $chatuser) {
				$target = new IGtTarget();
				$target->set_appId($appid);
				$target->set_clientId($chatuser['gc_id_ios']);
				$target_list[] = $target;
				unset($target);
				$count++;
				//如果多于50人，则推送
				if ($count >= 50) {
					//推送消息
					$result = $igt->pushMessageToList($contentId, $target_list);

					// 保存日志
					$para_array = array();
					$para_array['message'] = $message;
					$para_array['count'] = count($target_list);
					$para_array['platform'] = $platform.'_g';
					$para_array['method'] = 'list';
					$para_array['result'] = $result;
					$this->log->write_log_json($para_array, $this->_log_tag);

					//重置数据
					$count = 0;
					$target_list = array();
				}
			}

			if (!empty($target_list)) {
				//推送消息
				$result = $igt->pushMessageToList($contentId, $target_list);

				// 保存日志
				$para_array = array();
				$para_array['message'] = $message;
				$para_array['count'] = count($target_list);
				$para_array['platform'] = $platform.'_g';
				$para_array['method'] = 'list';
				$para_array['result'] = $result;
				$this->log->write_log_json($para_array, $this->_log_tag);
			}
		}
		//如果只有一个人
		else{

			//接收方
			$target = new IGtTarget();
			$target->set_appId($appid);
			$target->set_clientId($push_user_list[0]['gc_id_ios']);

			try {
				$result = $igt->pushMessageToSingle($gt_message, $target);
			}catch(RequestException $e) {
				$requstId = $e.getRequestId();
				$result = $igt->pushMessageToSingle($gt_message, $target, $requstId);
			}

			// 保存日志
			$para_array = array();
			$para_array['message'] = $message;
			$para_array['cid'] = $push_user_list[0]['gc_id_ios'];
			$para_array['uid'] = $push_user_list[0]['uid'];
			$para_array['platform'] = $platform.'_g';
			$para_array['method'] = 'single';
			$para_array['result'] = $result;
			$this->log->write_log_json($para_array, $this->_log_tag);
			unset($target);
		}
		unset($template);
		unset($gt_message);
		unset($target_list);
		unset($result);
		unset($igt);

		return null;
	}

	/**
	 * 推送消息（iOS的apn），私有函数
	 * 该函数不支持向所有用户推送，因为向所有用户推送时，必定没有apn的补推，会通过个推转apn的形式
	 * @param array		$message			消息体，指针形式
	 * @param array		$push_user_list		接收消息的用户列表
	 * @param int		$platform			对应的平台
	 * @return NULL
	 */
	private function _push_message_request_i($message, $push_user_list, $platform) {
		// 生成内容
		// $content是用来显示在通知栏的内容，$transmissionContent_apn是用在apn透传的内容
		switch($message['type']) {
			// 新闻推送
			case PUSH_NEWS:
				$content = $message['content'];
				$transmissionContent_apn = array(
					'nid' => $message['nid'],
					"title" => $message['title'],
					"url" => $message['url'],
					'type' => $message['type'],
					"display_time" => $message['display_time'],
					"image" => $message['image']
				);
				break;

			// 调用接口推送
			case PUSH_CALL_PORT:
				return FALSE;
				break;

			// 正在输入消息
			case PUSH_CHAT_START_INPUT:
			case PUSH_CHAT_STOP_INPUT:
				return FALSE;
				break;

			// 文本聊天消息
			case PUSH_CHAT_TEXT_MESSAGE:
				$content = $message['content'];
				if ($message['from_name'] != "") {
					$content = $message['from_name'].":".$content;
				}
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 语音聊天消息
			case PUSH_CHAT_VOICE_MESSAGE:
				$content = $message['from_name']."发来一段语音";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 图片聊天消息
			case PUSH_CHAT_IMAGE_MESSAGE:
				$content = $message['from_name']."发来一张照片";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 文件聊天消息
			case PUSH_CHAT_FILE_MESSAGE:
				$content = $message['from_name']."发来一个文件";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 表情聊天消息
			case PUSH_CHAT_EMOJI_MESSAGE:
				$content = $message['from_name']."发来一个表情";
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;
			// 系统提醒消息
			case PUSH_CHAT_SYSTEM_MESSAGE:
				$content = $message['content'];
				if ($message['from_name'] != "") {
					$content = $message['from_name'].":".$content;
				}
				$transmissionContent_apn = array(
					'ccid' => $message['ccid'],
					'chatid' => $message['chatid'],
					'from_uid' => $message['from_uid'],
					'attachment' => $message['attachment'],
					'type' => $message['type'],
					'send_time' => $message['send_time'],
				);
				break;

			// 匹配成功
			case PUSH_MATCH_SUCCEED_INFORM:
				$content = $message['content'];
				$transmissionContent_apn = array(
					"type" => $message['type'],
					"content" => str_repeat($content, 1),
				);
				break;
			// 收到邀请
			case PUSH_INVTIE_RECEIVE:
				// 邀请被接受
			case PUSH_INVTIE_ACCEPT:
				$content = $message['content'];
				$transmissionContent_apn = array(
					"type" => $message['type'],
//					"iid" => $message['iid'],
					"content" => str_repeat($content, 1),
				);
				break;

			default:
				return false;
		}

		// 加入时间戳
		if (isset($message['r'])) {
			$transmissionContent_apn['r'] = $message['r'];
		}

		// 根据不同的平台以及是否是现网，选择不同的推送id
		// appstore版
		if ($platform == PLATFORM_IOS_APPSTORE) {
			$appkey = constant("GT_APPKEY_3");
			$appid =  constant("GT_APPID_3");
			$master_secret = constant("GT_MASTERSECRET_3");
		}
		// 企业版
		else {
			if (DEBUG) {
				$appkey = constant("GT_APPKEY_1");
				$appid =  constant("GT_APPID_1");
				$master_secret = constant("GT_MASTERSECRET_1");
			}
			else{
				$appkey = constant("GT_APPKEY_2");
				$appid =  constant("GT_APPID_2");
				$master_secret = constant("GT_MASTERSECRET_2");
			}
		}

		$igt = new IGeTui(GT_HOST, $appkey, $master_secret);

		//创建APN消息
		$template =  new IGtAPNTemplate();

		$template -> set_appId($appid);			// 应用appid
		$template -> set_appkey($appkey);		// 应用appkey

		$apn = new IGtAPNPayload();
		$apn->badge = 1;
		$apn->sound = "";
		$apn->add_customMsg("custom", json_encode($transmissionContent_apn));
		$apn->contentAvailable = 1;

		// 计算剩余长度，最大长度为2048字节，当加上alert之后，会增加10字节左右的冗余，因此最大长度设置为2028
		// 一个汉字是3个字节，json之后是6字节，一个英文或标点是1个字节，json之后是1至2字节，所以json之后的长度约为json之前的两倍
		mb_internal_encoding("UTF-8");
		$remain_length = floor((2028 - strlen($apn->get_payload())) / 2);

		// 如果超出了限制，则需要截取
		if (strlen($content) > $remain_length) {
			$content = mb_strcut($content, 0, $remain_length);
		}

		$alertmsg=new SimpleAlertMsg();		//$alertmsg是通知栏的显示内容
		$alertmsg->alertMsg=$content;
		$apn->alertMsg=$alertmsg;
		$template->set_apnInfo($apn);		// 如果$transmissionContent_json和$alertmsg的长度之和大于2048则这里会报错

		//个推信息体
		if (count($push_user_list) > 1) {
			$gt_message = new IGtListMessage();
		}
		else {
			$gt_message = new IGtSingleMessage();
		}

		$gt_message->set_data($template);//设置推送消息类型

		// 如果有多人，则调用群推
		if (count($push_user_list) > 1) {
			$contentId = $igt->getAPNContentId($appid, $gt_message);

			//接收方
			$count = 0;
			$target_list = array();
			foreach($push_user_list as $chatuser) {
				$target_list[] = $chatuser['ac_id'];
				$count++;
				//如果多于50人，则推送
				if ($count >= 50) {
					//推送消息
					$result = $igt->pushAPNMessageToList($appid, $contentId, $target_list);

					// 保存日志
					$para_array = array();
					$para_array['message'] = $message;
					$para_array['count'] = count($target_list);
					$para_array['platform'] = $platform.'_a';
					$para_array['method'] = 'list';
					$para_array['result'] = $result;
					$this->log->write_log_json($para_array, $this->_log_tag);

					//重置数据
					$count = 0;
					$target_list = array();
				}
			}

			if (!empty($target_list)) {
				//推送消息
				$result = $igt->pushAPNMessageToList($appid, $contentId, $target_list);

				// 保存日志
				$para_array = array();
				$para_array['message'] = $message;
				$para_array['count'] = count($target_list);
				$para_array['platform'] = $platform.'_a';
				$para_array['method'] = 'list';
				$para_array['result'] = $result;
				$this->log->write_log_json($para_array, $this->_log_tag);
			}
		}
		//如果只有一个人
		else{

			$result = $igt->pushAPNMessageToSingle($appid, $push_user_list[0]['ac_id'], $gt_message);

			// 保存日志
			$para_array = array();
			$para_array['message'] = $message;
			$para_array['cid'] = $push_user_list[0]['ac_id'];
			$para_array['uid'] = $push_user_list[0]['uid'];
			$para_array['platform'] = $platform.'_a';
			$para_array['method'] = 'single';
			$para_array['result'] = $result;
			$this->log->write_log_json($para_array, $this->_log_tag);
		}
		unset($template);
		unset($gt_message);
		unset($target_list);
		unset($result);
		unset($igt);

		return null;
	}

	/**
	 * 推送消息（微信），私有函数
	 * @param array		$message			消息体，指针形式
	 * @param array		$push_user_list		接收消息的用户列表
	 * @param bool		$all_flag			是否是向所有用户推送
	 * @param bool		$access_token		微信的access_token
	 * @return NULL
	 */
	private function _push_message_request_w($message, $push_user_list, $all_flag=FALSE, $access_token) {
		// 只有现网才允许全部推送
		if (DEBUG) {
			$all_flag = FALSE;
		}

		if (!$all_flag && count($push_user_list)==0) {
			return NULL;
		}

		$this->log->write_log_json($message, $this->_log_tag);

		// 数据初始化
		$first = array('value'=>'');
		$keyword1 = array('value'=>'');
		$keyword2 = array('value'=>'');
		$remark = array('value'=>'');
		$name = array('value'=>'');
		$transmissionContent = array();

		// 生成消息体
		switch($message['type']) {
			// 新闻推送
			case PUSH_NEWS:
			// 调用接口推送
			case PUSH_CALL_PORT:
			// 正在输入消息
			case PUSH_CHAT_START_INPUT:
			case PUSH_CHAT_STOP_INPUT:
			// 非文本聊天消息
			case PUSH_CHAT_VOICE_MESSAGE:
			case PUSH_CHAT_IMAGE_MESSAGE:
			case PUSH_CHAT_FILE_MESSAGE:
			case PUSH_CHAT_EMOJI_MESSAGE:
			case PUSH_CHAT_SYSTEM_MESSAGE:
				return NULL;

			//聊天文本消息
			case PUSH_CHAT_TEXT_MESSAGE:
				if ($message['chatid'] < 0 && $message['from_uid'] == 0) {
					$template_id = "";
					$type = $message['weixin_param']['type'];

					if ($type==WEIXIN_PUSH_IDENTITY_FAIL) {
						$first['value'] = "您好，您的申请已认证失败";
						$keyword1['value'] = "身份认证";
						$remark['value'] = "请重新上传资料";
						$template_id = config_item("weixin_template_auth_fail");
					}
					else if ($type==WEIXIN_PUSH_IDENTITY_SUCCESS) {
						$first['value'] = "您好，您的申请已认证成功";
						$keyword1['value'] = "身份认证";
						$remark['value'] = "恭喜您的身份已经通过审核";
						$template_id = config_item("weixin_template_auth_success");
					}
					else if ($type==WEIXIN_PUSH_EDUCATION_FAIL) {
						$first['value'] = "您好，您的申请已认证失败";
						$keyword1['value'] = "学历认证";
						$remark['value'] = "请重新上传资料";
						$template_id = config_item("weixin_template_auth_fail");

					}
					else if ($type==WEIXIN_PUSH_EDUCATION_SUCCESS) {
						$first['value'] = "您好，您的申请已认证成功";
						$keyword1['value'] = "学历认证";
						$remark['value'] = "恭喜您的学历已经通过审核";
						$template_id = config_item("weixin_template_auth_success");
					}
					else if ($type==WEIXIN_PUSH_CAR_FAIL) {
						$first['value'] = "您好，您的申请已认证失败";
						$keyword1['value'] = "车产认证";
						$remark['value'] = "请重新上传资料";
						$template_id = config_item("weixin_template_auth_fail");

					}
					else if ($type==WEIXIN_PUSH_CAR_SUCCESS) {
						$first['value'] = "您好，您的申请已认证成功";
						$keyword1['value'] = "车产认证";
						$remark['value'] = "恭喜您的车产已经通过审核";
						$template_id = config_item("weixin_template_auth_success");
					}
					else if ($type==WEIXIN_PUSH_HOUSE_FAIL) {
						$first['value'] = "您好，您的申请已认证失败";
						$keyword1['value'] = "房产认证";
						$remark['value'] = "请重新上传资料";
						$template_id = config_item("weixin_template_auth_fail");

					}
					else if ($type==WEIXIN_PUSH_HOUSE_SUCCESS) {
						$first['value'] = "您好，您的申请已认证成功";
						$keyword1['value'] = "房产认证";
						$remark['value'] = "恭喜您的房产已经通过审核";
						$template_id = config_item("weixin_template_auth_success");
					}
					else if ($type==WEIXIN_PUSH_AVATAR_FAIL) {
						$first['value'] = "您好，您的头像审核失败";
						$keyword1['value'] = "头像审核";
						$remark['value'] = "请重新上传头像";
						$template_id = config_item("weixin_template_auth_fail");
					}
					else if ($type==WEIXIN_PUSH_AVATAR_SUCCESS) {
						$first['value'] = "您好，您的头像审核成功";
						$keyword1['value'] = "头像审核";
						$remark['value'] = "恭喜您的头像已经通过审核";
						$template_id = config_item("weixin_template_auth_success");
					}
					else if ($type==WEIXIN_PUSH_RECOMMEND_FAIL) {
						$first['value'] = "您好，您的推荐审核失败";
						$keyword1['value'] = "推荐审核";
						$remark['value'] = "请重新申请审核";
						$template_id = config_item("weixin_template_auth_fail");
					}
					else if ($type==WEIXIN_PUSH_RECOMMEND_SUCCESS) {
						$first['value'] = "您好，您的推荐审核成功";
						$keyword1['value'] = "推荐审核";
						$remark['value'] = "恭喜您的推荐已经通过审核";
						$template_id = config_item("weixin_template_auth_success");
					}
					else if ($type==WEIXIN_PUSH_ACTIVITY) {
						//获取活动信息
						$activity = $message['weixin_param']['activity'];
						$first['value'] = "您已报名“".$activity['name']."”活动";
						$keyword1['value'] = $activity['start_time'];
						$keyword2['value'] = $activity['address'];
						$remark['value'] = "别忘记按时参加哦";
						$template_id = config_item("weixin_template_activity");
						$link_url = config_item("base_url").HOMEDIR."activity?aid=".$activity['aid'];
					}
					else if ($type==WEIXIN_PUSH_SERVICE_ENTRUST) {
						//获取服务名称
						$keyword1['value'] = "恋爱委托";
						$keyword2['value'] = "已下单";
						$remark['value'] = "24小时内将有客服与您联系";
						$template_id = config_item("weixin_template_service");
						$link_url = config_item("base_url").HOMEDIR."love_entrust";
					}
					else if ($type==WEIXIN_PUSH_SERVICE_QINGFENG) {
						//获取服务名称
						$name['value'] = "形象提示服务";
						$remark['value'] = "有效期：永久
12-48小时内将会有客服与您沟通确认";
						$template_id = config_item("weixin_template_service_success");
						$link_url = config_item("base_url").HOMEDIR."qingfeng";
					}
					else if ($type==WEIXIN_PUSH_SERVICE_DATING) {
						//获取服务名称
						$name['value'] = "模拟约会服务";
						$remark['value'] = "有效期：永久
12-48小时内将会有客服与您沟通确认";
						$template_id = config_item("weixin_template_service_success");
						$link_url = config_item("base_url").HOMEDIR."dating_simulation";
					}
					else if ($type==WEIXIN_PUSH_SERVICE_TOP) {
						//获取服务名称
						$keyword1['value'] = "高端推荐服务";
						$keyword2['value'] = "预约成功";
						$remark['value'] = "24小时内将有客服与您联系";
						$template_id = config_item("weixin_template_service");
						$link_url = config_item("base_url").HOMEDIR."top_service";
					}
					else if ($type==WEIXIN_PUSH_VIP) {
						//获取服务名称
						$vip = $message['weixin_param']['vip'];
						$first['value'] = "恭喜您已成为会员";
						$keyword1['value'] = $vip['vip_lv']==1?"高级会员":"普通会员";
						$keyword2['value'] = $vip['vip_date'];
						$remark['value'] = "如有疑问，请联系客服";
						$template_id = config_item("weixin_template_vip");
						$link_url = config_item("base_url").WXDIR."personal";
					}

					$this->load->helper("date");
					if ($template_id==config_item("weixin_template_auth_fail")) {
						$transmissionContent = array(
							'template_id' => $template_id,
							'data'=>array(
								'first' =>$first,
								'keyword1' => $keyword1,
								'keyword2' => array("value"=>"失败"),
								'keyword3' => array("value"=>get_str_from_time(time())),
								'keyword4' => array("value"=>$message['content']),
								'remark' => $remark,
							),
						);
					}
					else if ($template_id==config_item("weixin_template_auth_success")) {
						$transmissionContent = array(
							'template_id' => $template_id,
							'data'=>array(
								'first' =>$first,
								'keyword1' => $keyword1,
								'keyword2' => array("value"=>"成功"),
								'keyword3' => array("value"=>get_str_from_time(time())),
								'keyword4' => array("value"=>$message['content']),
								'remark' => $remark,
							),
						);
					}
					else if ($template_id==config_item("weixin_template_activity")) {
						$transmissionContent = array(
							'template_id' => $template_id,
							'data'=>array(
								'first' =>array("value"=>""),
								'keyword1' => $keyword1,
								'keyword2' => $keyword2,
								'remark' => $remark,
							),
						);
					}
					else if ($template_id==config_item("weixin_template_service")) {
						$transmissionContent = array(
							'template_id' => $template_id,
							'data'=>array(
								'first' =>array("value"=>""),
								'keyword1' => $keyword1,
								'keyword2' => $keyword2,
								'remark' => $remark,
							),
						);
					}
					else if ($template_id==config_item("weixin_template_vip")) {
						$transmissionContent = array(
							'template_id' => $template_id,
							'data'=>array(
								'first' =>$first,
								'keyword1' => $keyword1,
								'keyword2' => $keyword2,
								'remark' => $remark,
							),
						);
					}
					else if ($template_id==config_item("weixin_template_service_success")) {
						$transmissionContent = array(
							'template_id' => $template_id,
							'data'=>array(
								'name' =>$name,
								'remark' => $remark,
							),
						);
					}

				}
				break;

			// 匹配成功
			case PUSH_MATCH_SUCCEED_INFORM:
			// 收到邀请
			case PUSH_INVTIE_RECEIVE:
			// 邀请被接受
			case PUSH_INVTIE_ACCEPT:
				$transmissionContent = array(
					'template_id' => config_item("weixin_template_match"),
					'data'=>array(
						"first" => array("value"=>$message['content']),
						"name" => array("value"=>"点击查看详情"),
						"sex" => array("value"=>"点击查看详情"),
						"tel" => array("value"=>"点击查看详情"),
						"remark" => array("value"=>"点击查看详情"),
					),
				);
				break;
			default:
				return false;
		}

		$push_url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$access_token";

		//下面是微信推送的逻辑
		foreach ($push_user_list as $push_user) {
			$transmissionContent_temp = $transmissionContent;

			$transmissionContent_temp['touser'] = $push_user['openid'];
			$transmissionContent_temp['url'] = isset($link_url)?$link_url:config_item("base_url").WXDIR."index";
			$transmissionContent_temp = json_encode($transmissionContent_temp);
			$this->load->helper("network");
			$result =post_request($push_url, $transmissionContent_temp, 1);

			// 保存日志
			$para_array = array();
			$para_array['openid'] = $push_user['openid'];
			$para_array['uid'] = $push_user['uid'];
			$para_array['transmissionContent'] = $transmissionContent_temp;
			$para_array['result'] = $result;
			$this->log->write_log_json($para_array, $this->_log_tag);
			unset($para_array);
		}
		return NULL;
	}

	/**
	 * 根据uid，查询出用户的信息，包括推送id、版本、平台等
	 * @param int|array		$uid		uid，可以int也可以为数组
	 * @return array	接收消息的用户的信息
	 */
	private function _select_push_user($uid) {
		$this->load->model('user_model');
		$this->user_model->set_table_name('p_useredition');
		return $this->user_model->select(array('uid'=>$uid), FALSE, $this->_fields);
	}

	/**
	 * 根据chatid和from_uid，查询出用户的信息，包括推送id、版本、平台等
	 * @param int		$chatid		聊天id
	 * @param int		$from_uid		消息发送者id
	 * @return array	接收消息的用户的信息
	 */
	public function _select_push_user_for_chat($chatid, $from_uid) {
		// 如果是客服聊天
		if ($chatid < 0) {
			// 如果是客服发出的
			if ($from_uid == 0) {
				return $this->_select_push_user(substr($chatid, 1));
			}

			// 如果不是客服发出的，直接返回空即可
			return NULL;
		}

		$this->load->model('user_model');

		//找到用户信息
		$sql = "select ".$this->_fields." from `p_useredition`,`p_chatuser`
			where `p_useredition`.uid=`p_chatuser`.uid
			and `p_chatuser`.chatid=".$this->user_model->db->escape($chatid)."
			and `p_chatuser`.active_flag=1 and `p_chatuser`.uid!=".$this->user_model->db->escape($from_uid);

		$result = $this->user_model->db->query($sql);
		return $this->user_model->process_sql($result);
	}

	/**
	 * 根据uid或者pid或者cid查询出用户的信息，包括推送id、版本、平台等
	 * @param array		$custom_push		推送的数据，包含uid，pid，cid
	 * @return array	接收消息的用户的信息
	 */
	private function _select_push_user_for_news($custom_push) {
		$this->load->model('user_model');

		//如果为群推
		if (isset($custom_push['all_flag']) && $custom_push['all_flag']) {
			//查询所有的用户
			$this->user_model->set_table_name('p_useredition');
			return $this->user_model->select(array(), FALSE, $this->_fields);
		}
		//如果存在uid
		elseif (isset($custom_push['uid']) && is_id($custom_push['uid'])) {
			$uid = $custom_push['uid'];

			//根据uid查询出用户信息
			$this->user_model->set_table_name('p_useredition');
			return $this->user_model->select(array('uid'=>$uid), FALSE, $this->_fields);

		}
		//如果存在城市id，那么一定是城市推送,那么也会存在省份id
		elseif (isset($custom_push['cid']) && isset($custom_push['pid']) && is_id($custom_push['cid']) && is_id($custom_push['pid'])) {
			$pid = $custom_push['pid'];
			$cid = $custom_push['cid'];

			//查询该城市用户，找到用户信息
			$sql = "select ".$this->_fields." from `p_useredition`,`p_userdetail`
			where `p_useredition`.uid=`p_userdetail`.uid
			and `p_userdetail`.province=".$this->user_model->db->escape($pid)."
			and `p_userdetail`.city=".$this->user_model->db->escape($cid);

			$result = $this->user_model->db->query($sql);
			return $this->user_model->process_sql($result);
		}
		//如果是省份推送
		elseif (isset($custom_push['pid']) && is_id($custom_push['pid'])) {
			$pid = $custom_push['pid'];

			//查询该省份用户，找到用户信息
			$sql = "select ".$this->_fields." from `p_useredition`,`p_userdetail`
			where `p_useredition`.uid=`p_userdetail`.uid
			and `p_userdetail`.province=".$this->user_model->db->escape($pid);
			$result = $this->user_model->db->query($sql);
			return $this->user_model->process_sql($result);
		}
		return null;
	}



}