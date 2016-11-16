<?php
/**
 * 微信的相关授权等
 * Created by PhpStorm.
 * User: hvcheng
 * Date: 2016/4/22
 * Time: 11:50
 */

Class Wx_service extends MY_Service
{
	private $weixin_id; //微信公众号名
	private $weixin_token; //微信Token
	private $weixin_appid; //微信APPID
	private $weixin_appsecret; //微信APPSECRET
	private $weixin_encoding_key; //微信消息加密密钥
	private $weixin_access_token; //微信access_token
	private $refresh_access_token_times; //重复获取access_token的次数
	private $debug;					// 是否是调试模式，输出日志
	private $crypt;					// 是否启用加密传输

	public function __construct($debug = FALSE, $crypt = TRUE)
	{
		$this->load->model('user_model');
		$this->load->model('config_model');
		$this->load->helper('network');
		$this->weixin_id = config_item('weixin_id');
		$this->weixin_token = config_item('weixin_token');
		$this->weixin_appid = config_item('weixin_appid');
		$this->weixin_appsecret = config_item('weixin_appsecret');
		$this->weixin_encoding_key = config_item('weixin_encoding_key');
//		$this->weixin_access_token = $this->get_access_token();
		$this->refresh_access_token_times = 0;
		$this->debug = $debug;
		$this->crypt = $crypt;
	}

	/**
	 * 获取 echostr
	 * @return mixed  string/null
	 */
	public function valid()
	{
		$echoStr = $this->input->get('echostr');

		//valid signature , option
		if ($this->checkSignature()) {
			echo $echoStr;
		} else {
			exit();
		}
	}


	/**
	 * 用户鉴权
	 * @param bool		$guest_enable_flag		是否允许游客身份，如果不允许且无Cookie，则跳到授权页面，否则返回userweixin
	 * @param bool		$error_flag				若为1则表明虽然有cookie但是验证失败，此时不再检验cookie
	 * @return array 	用户在p_userweixin表中的基本信息
	 */
	public function authority($guest_enable_flag, $error_flag = FALSE)
	{
		$userweixin = array();
		$state = "";

		//从微信回调回来，获取到code参数
		if ($this->input->get('code') != "") {
			$code = $this->input->get('code');
			$state = $this->input->get('state');
			if ($this->debug) {
				$this->log->write_log_json(array("log"=>"Got Code " . $code), 'weixin');
			}
			$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $this->weixin_appid .
				"&secret=" . $this->weixin_appsecret . "&code=" . $code . "&grant_type=authorization_code";
			$response = file_get_contents($url);
			$result = json_decode($response, TRUE);
			$openid = $result['openid'];

			if ($this->debug) {
				$this->log->write_log_json(array("log"=>"由code获取open_id成功", "result"=>$result), 'weixin');
			}

			//判断该openid对应的账号是否已经在数据库中
			$get_info_flag = 0; //这个flag表示是否需要进一步获取信息
			$insert_flag = 0; //这个flag表示是否需要插入数据库

			$ori_userweixin = $this->user_model->get_row_by_openid($openid);

			//如果能找到
			if (!empty($ori_userweixin)) {
				//判断是否需要刷新个人信息
				if ($ori_userweixin['update_time'] + config_item('weixin_info_refresh_time') < time()) {
					$get_info_flag = 1;
				}
			} //如果找不到
			else {
				$insert_flag = 1;
				$get_info_flag = 1;
			}
			//如果需要刷新个人信息
			if ($get_info_flag) {
				$userweixin = $ori_userweixin;
				$userweixin['openid'] = $result['openid'];
				$userweixin['access_token'] = $result['access_token'];
				$userweixin['access_token_time'] = time() + $result['expires_in'] - 60;
				$userweixin['refresh_token'] = $result['refresh_token'];


				if ($this->debug) {
					$this->log->write_log_json(array("log"=> "从微信回调回来，带有code，刷新个人信息"), 'weixin');
				}


				$this->get_weixin_info($userweixin);
				//如果数据库中还不存在该openid对应的记录，则添加
				if ($insert_flag) {
					if ($this->debug) {
						$this->log->write_log_json(array("log"=> "插入数据库"), 'weixin');
					}
					$userweixin['create_time'] = time();
					$userweixin['follow_flag'] = 0;
					//数据库添加一个微信用户

					$this->user_model->insert_userweixin($userweixin);
				}
				//如果存在则更新
				else {
					if ($this->debug) {
						$this->log->write_log_json(array("log"=> "更新数据库"), 'weixin');
					}
					if ($ori_userweixin['openid'] == $userweixin['openid']) {
						$this->user_model->update($userweixin, array("openid"=>$ori_userweixin['openid']));
					}
				}
			} else {
				$userweixin = $ori_userweixin;
			}

			$openid = $userweixin['openid'];
			$openkey = $this->get_openkey($openid);
			setcookie("openid", $openid, time() + 7776000); //有效期90天
			setcookie("openkey", $openkey, time() + 7776000); //有效期90天

			unset($result);
		}
		//检测到openid
		else if (isset($_COOKIE["openid"]) && !$error_flag) {
			$openid = $_COOKIE["openid"];

			//去数据库中找用户信息
			$userweixin = $this->user_model->get_row_by_openid($openid);

			if ($this->debug) {
				$this->log->write_log_json(array("log"=>"去数据库中找用户信息"), 'weixin');
			}


			//加密鉴权
			if ($this->verify_openkey($userweixin['openid'], $_COOKIE['openkey'])) {
				if ($this->debug) {
					$this->log->write_log_json(array("log"=>"Welcome " . $_COOKIE["openid"] . "!<br />"), 'weixin');
				}

			} else {
				if ($this->debug) {
					$this->log->write_log_json(array("log"=>"权限错误"), 'weixin');
					$this->log->write_log_json(array("log"=>"权限错误", "cookie"=>$_COOKIE, "userweixin"=>$userweixin), "weixin_error");
				}
				$userweixin = $this->authority($guest_enable_flag, TRUE);
				$userweixin['state'] = $state;
				return $userweixin;
			}

			//判断是否需要刷新个人信息
			if ($userweixin['update_time'] + config_item('weixin_info_refresh_time') < time()) {
				if ($this->debug) {
					$this->log->write_log_json(array("log"=>"检测到openid之后，刷新个人信息"), 'weixin');
				}
				$ori_userweixin = $userweixin;
				$this->get_weixin_info($userweixin);
				if ($ori_userweixin['openid'] == $userweixin['openid']) {
					$this->user_model->update_userweixin($userweixin, array('openid'=>$ori_userweixin['openid']));
				}
			}
		}
		//如果允许游客访问，则返回uid为-1的用户基本信息
		else if ($guest_enable_flag) {
			setcookie("openid", '', 0);
			setcookie("openkey", '', 0);
			$userweixin['uid'] = '-1';
		} //检测不到openid，并且不允许游客访问，则跳转到授权页面
		else {
			$this->jump_to_authority();
		}
		$userweixin['state'] = $state;
		return $userweixin;
	}


	/**
	 * 跳转到授权页面
	 * @return NULL
	 */
	private function jump_to_authority()
	{
		setcookie("openid", '', 0);
		setcookie("openkey", '', 0);

		$this->load->helper('url');
		$redirect_uri = current_url();

		$state = urlencode($this->input->server('QUERY_STRING'));

		$redirect_uri = urlencode($redirect_uri);

		$url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $this->weixin_appid .
			"&redirect_uri=" . $redirect_uri . "&response_type=code&scope=snsapi_userinfo&state=$state#wechat_redirect";
		if ($this->debug) {
			$this->log->write_log_json(array("log"=> "授权url", "url"=>$url), 'weixin');
		}
		//@todo 需要封装所有跳转操作 以兼容异步请求
		if(isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"])=="xmlhttprequest"){
			echo json_encode(array('state' => true, 'redirect_url' => $url));
		} else {
			header("Location:" . $url);
		}
		exit();
	}

	/**
	 * 重新获取用户的access_token
	 * @param string		$refresh_token
	 * @return array		调用微信重新获取用户的access_token接口的调用结果
	 */
	private function refresh_token($refresh_token)
	{
		$url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=" . $this->weixin_appid . "&grant_type=refresh_token&refresh_token=" . $refresh_token;

		if ($this->debug) {
			$this->log->write_log_json(array("log"=> "重新获取用户的access_token", "request_url"=>$url), 'weixin');
		}

		$response = file_get_contents($url);
		$result = json_decode($response, TRUE);
		return $result;
	}


	/**
	 * 获取一个用户的个人信息
	 * @param array		$userweixin		用户的基本信息，指针形式
	 * @return null
	 */
	private function get_weixin_info(&$userweixin)
	{
		$finish_flag = 0;
		$result_userinfo = array();
		$result_userinfo['errcode'] = 'error';
		//如果是已经关注的状态
		if ($userweixin['follow_flag'] == 1) {
			if ($this->debug) {
				$this->log->write_log_json(array("log"=> "是已经关注的状态"), 'weixin');
			}

			//获取用户基本信息
			$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $this->get_access_token() . "&openid=" . $userweixin['openid'] . "&lang=zh_CN";
			$response = file_get_contents($url);
			$result_userinfo = json_decode($response, TRUE);

			if ($this->debug) {
				$this->log->write_log_json($result_userinfo, 'weixin');
			}

			//如果成功
			if ($result_userinfo['subscribe'] == '1') {
				if ($this->debug) {
					$this->log->write_log_json(array("log"=> "获取成功"), 'weixin');
				}

				$finish_flag = 1;
			} //如果是access_token过期
			elseif ($result_userinfo['errcode'] == '40001' || $result_userinfo['errcode'] == '40014' ||$result_userinfo['errcode'] == '42001') {
				//强制更新access_token
				$this->get_access_token(TRUE);
				//重复调用
				$this->get_weixin_info($userweixin);
				return;
			}
		}

		//如果不能通过已关注状态获取用户基本信息，则通过网页授权方式获取
		if (!$finish_flag) {
			if ($this->debug) {
				$this->log->write_log_json(array("log"=> "不能通过已关注状态获取用户基本信息"), 'weixin');
			}
			//检查用户的access_token是否过期


			//如果过期了，或者还没有access_token，则直接重新获取access_token
			if (time() > $userweixin['access_token_time'] || $userweixin['access_token']=='') {
				if ($this->debug) {
					$this->log->write_log_json(array("log"=> "重新获取access_token"), 'weixin');
				}


				if ($userweixin['refresh_token'] != '') {
					$result = $this->refresh_token($userweixin['refresh_token']);
				}
				else {
					$result['errcode'] = 'ERROR';
				}

				//如果没有重新获取成功，则有可能是用户的refresh_token过期了，需要重新授权
				if ($result['errcode'] != '') {
					if ($this->debug) {
						$this->log->write_log_json(array("log"=> "重新获取access_token失败"), 'weixin');
					}
					$this->jump_to_authority();
					//return;
				} //如果获取成功了，则更新相应的token
				else {
					if ($this->debug) {
						$this->log->write_log_json(array("log"=>"重新获取access_token成功", "result"=>$result), 'weixin');
					}

					$userweixin['access_token'] = $result['access_token'];
					$userweixin['access_token_time'] = time() + $result['expires_in'] - 60;
					$userweixin['refresh_token'] = $result['refresh_token'];
				}
			}

			//获取用户基本信息
			$url = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $userweixin['access_token'] . "&openid=" . $userweixin['openid'] . "&lang=zh_CN";
			$response = file_get_contents($url);
			$result_userinfo = json_decode($response, TRUE);

			//如果没有获取成功，则有可能是用户的access_token过期了，需要重新获取
			if ($result_userinfo['errcode'] != '') {
				//只需要将有效时间设为过期，然后重复调用即可重复获取到
				$userweixin['access_token_time'] = 0;
				$this->get_weixin_info($userweixin);
				return;
			}
		}

		//处理返回值
		if ($result_userinfo['errcode'] == '') {
			if ($this->debug) {
				$this->log->write_log_json(array("log"=> "get_weixin_info成功，开始处理返回值"), 'weixin');
			}

			//微信昵称
			$userweixin['name'] = $result_userinfo['nickname'];
			$userweixin['name'] = trim($userweixin['name']);

			//性别
			$userweixin['gender'] = (is_id($result_userinfo['sex']) ? $result_userinfo['sex'] : 0);

			//国家
			$userweixin['country'] = $result_userinfo['country'];
			$userweixin['province'] = $result_userinfo['province'];
			$userweixin['city'] = $result_userinfo['city'];
			//头像
			$str_array = explode("/", $result_userinfo['headimgurl']);
			$last_index = count($str_array) - 1;
			if (is_numeric($str_array[$last_index])) {
				array_splice($str_array, $last_index, 1); //删除第二个元素
			}
			$userweixin['avatar'] = implode("/", $str_array);
			//更新时间
			$userweixin['update_time'] = time();
		}
	}

	/**
	 * 获取微信access_token
	 * @param bool		$error_flag		为TRUE说明有错误，强制执行更新
	 * @return string 	微信access_token
	 */
	public function get_access_token($error_flag = FALSE){
		// 如果已经获取了，且不是强制更新，那么直接返回即可
		if ($this->weixin_access_token!='' && !$error_flag) {
			return $this->weixin_access_token;
		}

		$config = $this->config_model->get_rows_by_name_array(array(
			"access_token",
			"token_expire_time",
			"wx_lock_flag"
		));

		// 如果强制更新
		if ($error_flag && $this->weixin_access_token==$config['access_token']) {
			$config['token_expire_time'] = 0;
		}

		$expire_time = $config['token_expire_time'];
		$lock_state = $config['wx_lock_flag'];

		// 如果未过期
		if (time() < $config['token_expire_time']) {
			$this->weixin_access_token = $config['access_token'];
			return $this->weixin_access_token;
		}
		// 如果是已经过期，且没有锁住，或者是过期1个小时以上，并且不管是否锁住
		else if (($expire_time < time() && $lock_state == '0') || $expire_time < time() - 3600) {
			$this->refresh_access_token_times++;
			//如果重试次数在允许范围内
			if ($this->refresh_access_token_times < 5) {

				// 如果是没有锁住的状态，开始加锁
				if ($lock_state == '0') {
					// 检测到过期，开始获取，此时将lock_flag置1
					$this->config_model->update(array('values'=>'1'), array('name'=>'wx_lock_flag', 'values'=>0));
					// 如果没有锁住，并且不足1个小时
					if ($this->db->affected_rows() <=0 && $expire_time > time() - 3600) {
						$this->weixin_access_token = $config['access_token'];
						return $config['access_token'];
					}
				}

				//获取新的access_token
				$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $this->weixin_appid . "&secret=" . $this->weixin_appsecret;
				$response = file_get_contents($url);
				$result = json_decode($response, TRUE);

				//保存到数据库中
				$access_token = $result['access_token'];
				$expire_time = time() + $result['expires_in'] - 200;

				if ($access_token != "") {
					//更新数据库
					$this->config_model->update_config(array('access_token'=>$access_token, 'token_expire_time'=>$expire_time));
				}

				// 无论是否拿到，都要解锁
				$this->config_model->update_config(array('wx_lock_flag'=>'0'));

				$this->weixin_access_token = $access_token;
				return $access_token;
			} //如果重试次数过多
			else {
				exit();
			}
		}

		return $this->weixin_access_token;
	}


	/**
	 * 向某个用户推送消息（走微信客服系统，要求24小时内用户与公众号有过交互）
	 * @param array		$data		消息体，需要按照微信指定格式，数组形式
	 * @return mixed 	调用微信推送消息接口的返回结果
	 */
	public function push_message($data)
	{
		$url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $this->get_access_token();
		$result = post_request_https($url, $data);
		$result = json_decode($result, TRUE);
		return $result;
	}

	/**
	 * 设置自定义菜单
	 * @param array		$data		菜单，需要按照微信指定格式，json形式
	 * @return mixed 	调用微信设置自定义菜单接口的返回结果
	 */
	public function set_menu($data)
	{
		$url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=" . $this->get_access_token();
		$result = post_request_https($url, $data);
		$result = json_decode($result, TRUE);
		return $result;
	}


	/**
	 * 验证签名
	 * @return bool 	TRUE(表示签名正确)/FALSE(表示签名错误)
	 */
	private function checkSignature()
	{


		$signature = $this->input->get('signature');
		$timestamp = $this->input->get('timestamp');
		$nonce = $this->input->get('nonce');

		$token = $this->weixin_token;
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);

		if ($tmpStr == $signature) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * 根据openid获取openkey
	 * @param  string	$openid		用户的微信id
	 * @return string 	生成的openkey
	 */
	private function get_openkey($openid)
	{
		return password_hash("V1cgzgpdFYStqln9".$openid."uf207TMbRXvwYCd4lyat8FpA3Sx70KfS", PASSWORD_DEFAULT);
	}

	/**
	 * 验证openkey
	 * @param  string	$openid			用户的微信id
	 * @param  string	$openkey		用户的openkey
	 * @return bool 	TRUE(openkey正确)/FALSE(openkey错误)
	 */
	private function verify_openkey($openid, $openkey)
	{
		return password_verify("V1cgzgpdFYStqln9".$openid."uf207TMbRXvwYCd4lyat8FpA3Sx70KfS", $openkey);
	}


	/**
	 * 回复用户信息
	 * @param  mixed	$postStr		用户发送过来的消息
	 * @return string 	调用微信回复接口的结果
	 */
	public function responseMsg($postStr)
	{
		if (!empty($postStr)) {
			if ($this->crypt) {
				$pc = new WXBizMsgCrypt($this->weixin_token, $this->weixin_encoding_key, $this->weixin_appid);
				$msg = '';
				$errCode = $pc->decryptMsg($this->input->get('msg_signature'), $this->input->get('timestamp'), $this->input->get('nonce'), $postStr, $msg);

				if ($this->debug) {
					$this->log->write_log_json(array('custom_event'=>'收到一条信息，开始解密','postStr'=>$postStr), 'weixin_response');
					$this->log->write_log_json($_GET, 'weixin_response');
					$this->log->write_log_json(array('custom_event'=>'收到一条信息，解密完成','msg'=>$msg, 'errCode'=>$errCode), 'weixin_response');
				}

				// 如果解密错误
				if ($errCode != 0) {
					echo "";
					exit;
				}

				$postObj = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
			}
			else {
				$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			}
			$openid = $postObj->FromUserName;
			$toUsername = $postObj->ToUserName;
			$msgType = $postObj->MsgType;
//			$createTime = $postObj->CreateTime;
//			$content = $postObj->Content;
//			$msgId = $postObj->MsgId;

			if ($this->debug) {
				$this->log->write_log_json(array('custom_event'=>'收到一条信息，判断msgType', 'msgType'=>$msgType), 'weixin_response');
			}

			$time = time();
			//文本回复
			$textTpl = "<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[%s]]></MsgType>
						<Content><![CDATA[%s]]></Content>
						</xml>";
			//图片回复
			$imageTpl = "<xml>
						 <ToUserName><![CDATA[%s]]></ToUserName>
						 <FromUserName><![CDATA[%s]]></FromUserName>
						 <CreateTime>%s</CreateTime>
						 <MsgType><![CDATA[%s]]></MsgType>
						 <Image>
						 <MediaId><![CDATA[%s]]></MediaId>
						 </Image>
						 </xml>";
			//图文消息回复
			$newsTpl = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[news]]></MsgType>
							<ArticleCount>1</ArticleCount>
							<Articles>
								<item>
									<Title><![CDATA[%s]]></Title>
									<Description><![CDATA[%s]]></Description>
									<PicUrl><![CDATA[%s]]></PicUrl>
									<Url><![CDATA[%s]]></Url>
								</item>
							</Articles>
							<FuncFlag>1</FuncFlag>
						</xml>";
			$newsFlag = FALSE;
			$title = "示例消息";
			$description = "点进去可以看到一些东西";
			$picUrl = "http://www.chahaoyou.com/images/avatar_0.png";
			$url = "http://www.chahaoyou.com";

			// 最后的返回内容
			$contentStr = '';
			$resultStr = '';
			$media_id = '';

			if ($msgType == 'event') {
				$event = $postObj->Event;
				if ($this->debug) {
					$this->log->write_log_json(array('custom_event'=>'收到一条信息，判断event', 'event'=>$event), 'weixin_response');
				}

				//关注事件
				if ($event == 'subscribe') {
					$ori_userweixin = $this->user_model->get_row_by_openid($openid);
					$get_info_flag = FALSE;
					$insert_flag = FALSE;
					//如果能找到
					if (!empty($ori_userweixin)) {
						$userweixin = $ori_userweixin;
						//判断是否需要刷新个人信息
						if ($ori_userweixin['update_time'] + config_item('weixin_info_refresh_time') < time()) {
							$get_info_flag = TRUE;
						}
					} //如果找不到
					else {
						$insert_flag = TRUE;
						$get_info_flag = TRUE;
					}
					//如果需要刷新个人信息
					if ($get_info_flag) {
						$userweixin['openid'] = $openid;
						$userweixin['follow_flag'] = 1;
						$this->get_weixin_info($userweixin);

						$id = $postObj->EventKey;
						$id  = substr($id, 8);	//进行字符截取
						//如果数据库中还不存在该openid对应的记录，则添加
						if ($insert_flag) {
							//数据库添加一个微信用户
							$userweixin['channel'] = $id;
							$userweixin['create_time'] = time();
							$this->user_model->insert_userweixin($userweixin, FALSE);

							//如果该用户是新关注的用户，判断该用户渠道号是否需要给司机发送红包
							$this->load->model('weixin_channel_model');
							$weixin_channel = $this->weixin_channel_model->get_row_by_id($id, 'driver_uid');
							//如果存在司机的uid
							if ($weixin_channel['driver_uid'] != 0) {
								$uid = $weixin_channel['driver_uid'];
								//每天奖励上限10个红包
								$start_time = strtotime(date('Y-m-d'));
								$end_time = $start_time+86400;
								$this->load->model('pingpp_order_model');
								$red_count = $this->pingpp_order_model->count(array("uid"=>$uid, "type"=>ORDER_TYPE_WEIXIN_CASH, "create_time >="=>$start_time, "create_time<="=>$end_time));

								if ($red_count < 10) {
									//获取累计获取红包总数
									$red_total = $this->pingpp_order_model->count(array("uid"=>$uid, "type"=>ORDER_TYPE_WEIXIN_CASH));
									//获取该司机的微信opneid
									$userweixin_red = $this->user_model->get_row_by_id($uid, 'p_userweixin', 'openid');
									//生成订单,发放红包
									$this->load->service('pay_service');
									$amount = 200;
									$subject = "简爱关注奖励";
									$body = "恭喜您成功获得一个关注奖励，今日已获得".$red_count."个，累计共获得".$red_total."个，再接再厉哦";
									$pingpp_result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_WEIXIN_CASH, 0, 0, $amount, 12, '', array("openid"=>$userweixin_red['openid'], "subject"=>$subject, "body"=>$body));
									// 如果是测试网，并且使用的是测试key，那么不会有回调，为了测试，需要直接写入账单
									if (DEBUG) {
										if (strpos(config_item("pingpp_secretkey"), "sk_test_") !== FALSE) {
											$this->pay_service->finish_pingpp_order($pingpp_result['pingpp_order']['order_no'], $pingpp_result['pingpp_order']['pingpp_id']);
										}
									}
								}

							}
						} //如果存在则更新
						else {
							if ($ori_userweixin['channel']==0) {
								$userweixin['channel'] = $id;
							}
							if ($ori_userweixin['openid'] == $userweixin['openid']) {
								$this->user_model->update_userweixin($userweixin, array("openid"=>$ori_userweixin['openid']));
							}
						}
					} //如果不需要刷新个人信息
					else {
						if ($ori_userweixin['channel']==0) {
							$id = $postObj->EventKey;
							$id  = substr($id, 8);	//进行字符截取
						} else {
							$id = 0;
						}
						//只更新一下数据库中的是否关注状态
						$this->user_model->update_userweixin(array("follow_flag"=>1, "channel"=>$id), array("openid"=>$openid));
					}

					//个人名片关注的用户的逻辑
					$user = $this->user_model->get_row_by_openid($openid);

					//获取自动回复内容
					$this->load->model('weixin_channel_model');
					$weixin_channel = $this->weixin_channel_model->get_row_by_id($user['channel']);
					$contentStr = isset($weixin_channel['content'])?$weixin_channel['content']:"";

					//判断该渠道是否需要发送图文消息
					if ($weixin_channel['news_flag']) {
						$this->load->helper('url');
						$weixin_channel['news_picurl'] = get_attachment_url($weixin_channel['news_picurl'], 'weixin');
						//发送图文消息给用户
						$this->wx_service->send_customer_message($openid, 'news',
							array(array("title"=>$weixin_channel['news_title'],
								"description"=>$weixin_channel['news_desc'],
								"url"=>$weixin_channel['news_url'],
								"picurl"=>$weixin_channel['news_picurl'])));
					}

					if ($user['last_uid'] != 0) {
						$watch_user = $this->user_model->get_row_by_id($user['last_uid'], 'p_userdetail');
						//获取图片地址
						$this->load->helper('url');
						$picUrl = get_attachment_url($watch_user['avatar'], 'avatar');
						//将内容发送给用户
						$url = config_item("base_url").WXDIR."index";
						//发送图文消息给用户
						$this->wx_service->send_customer_message($openid, 'news',
							array(array("title"=>$watch_user['username']."的个人主页",
								"description"=>"快来联系我吧~",
								"url"=>$url,
								"picurl"=>$picUrl)));

					}

				}
				//取消关注事件
				else if ($event == 'unsubscribe') {
					$this->user_model->update_userweixin(array("follow_flag"=>0), array("openid"=>$openid));
				}
				//扫描二维码事件
				else if ($event == 'SCAN'){
					$id = (int) ($postObj->EventKey);
					//获取自动回复内容
					$this->load->model('weixin_channel_model');
					$weixin_channel = $this->weixin_channel_model->get_row_by_id($id);
					$contentStr = isset($weixin_channel['content'])?$weixin_channel['content']:"";
				}
			} elseif ($msgType == 'text') {
				$keyword = trim($postObj->Content);
				mb_internal_encoding("UTF-8");

				// 自动回复
				$this->load->model('autoreply_model');
				$reply = $this->autoreply_model->get_reply_by_needle($keyword);

				//根据类型发送不同的信息
				if ($reply['type']==1) {
					$contentStr = $reply['text'];
				} else if ($reply['type']==2) {
					$newsFlag = TRUE;
					$title = $reply['news_title'];
					$description = $reply['news_desc'];
					$picUrl = $reply['news_picurl'];
					$url = $reply['news_url'];
				}


			} elseif ($msgType == 'image') {
//				$contentStr = "";
			} elseif ($msgType == 'voice') {
//				$contentStr = "";
			}

			//如果不是图文消息
			if (!$newsFlag) {
				if ($contentStr != '') {
					$resultStr = sprintf($textTpl, $openid, $toUsername, $time, 'text', $contentStr);
					if ($this->debug) {
						$this->log->write_log_json(array('custom_event'=>'发送非图文消息', 'resultStr'=>$resultStr), 'weixin_response');
					}
				}
				elseif($media_id != ''){
					$this->log->write_log_json(array("resultStr"=> $resultStr), 'weixin_response');
					$resultStr = sprintf($imageTpl, $openid, $toUsername, $time, 'image', $media_id);
				}
			} //如果是图文消息
			else {
				$resultStr = sprintf($newsTpl, $openid, $toUsername, $time, $title, $description, $picUrl, $url);
			}

			if ($this->crypt && isset($pc)) {
				$encryptMsg = '';
				$errCode = $pc->encryptMsg($resultStr, $this->input->get('timestamp'), $this->input->get('nonce'), $encryptMsg);

				if ($this->debug) {
					$this->log->write_log_json(array('custom_event'=>'加密完成','encryptMsg'=>$encryptMsg, 'errCode'=>$errCode), 'weixin_response');
				}

				// 如果解密错误
				if ($errCode != 0) {
					echo "";
					exit;
				}
				echo $encryptMsg;
			}
			else {
				echo $resultStr;
			}
		} else {
			echo "";
			exit;
		}
	}


	/**
	 * 使用模板消息回复用户信息
	 * @param  mixed	$data		数据 需要按照微信指定格式
	 * @return mixed 	微信接口调用的结果
	 */
	function send_template_message($data)
	{

		$url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$this->get_access_token();

		$result = post_request_https($url,$data);
		return $result;

	}

	/**
	 * 进行json编码，并且不转义中文，在设置菜单栏时使用
	 * @param  array	$array		数据 要编码的数组
	 * @return mixed 	json编码后的结果
	 */

	public function json_encode_ch($array)
	{
		return urldecode(json_encode($this->_url_encode_array($array)));
	}

	/**
	 * 对数组进行url编码
	 * @param  array	$array		数据 要编码的数组
	 * @return mixed 	url编码后的结果
	 */
	private function _url_encode_array($array)
	{
		if (is_array($array)) {
			foreach ($array as $key => $value) {
				$array[urlencode($key)] = $this->_url_encode_array($value);
			}
		} else {
			$array = urlencode($array);
		}

		return $array;
	}

	/**
	 * 获取微信分享的一系列信息
	 * @return mixed 	微信分享的一系列信息，包括appId等凭证信息，以及title等默认分享内容
	 */
	public function getSignPackage()
	{
		$jsapiTicket = $this->_getJsApiTicket();
		$protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		$timestamp = time();
		$nonceStr = $this->_createNonceStr();

		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

		$signature = sha1($string);

		$signPackage = array(
			"appId" => $this->weixin_appid,
			"nonceStr" => $nonceStr,
			"timestamp" => $timestamp,
			"url" => $url,
			"signature" => $signature,
			"rawString" => $string
		);
		//下面设置一些默认的分享内容
		$signPackage['title'] = "简爱";
		$signPackage['image_url'] = "http://i.jianailove.com/static/share.png";
		$signPackage['desc'] = "简爱：一座城，等你一个人。";
		$signPackage['link'] = config_item('base_url').HOMEDIR."index";
		$signPackage['tag'] = "官网";

		return $signPackage;
	}
	/**
	 * 随机创建一个NonceStr
	 * @param  int		$length		生成的字符长度
	 * @return string 	$NonceStr	生成的字符
	 */
	private function _createNonceStr($length = 16)
	{
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i ++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}


	/**
	 * 获取jsapi凭证
	 * @param  bool		$need_expire_time	生成的字符长度
	 * @return mixed 	jsapi凭证
	 */
	private function _getJsApiTicket($need_expire_time = FALSE)
	{
		// jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
		$para_array = $this->config_model->get_rows_by_name_array(array(
			"jsapi_ticket",
			"ticket_expire_time",
			"jsapi_lock_flag"
		));
		$expire_time = $para_array['ticket_expire_time'];
		$lock_state = $para_array['jsapi_lock_flag'];
		// 如果是已经过期，且没有锁住，或者是过期1个小时以上，并且不管是否锁住
		if (($expire_time < time() && $lock_state == '0') || $expire_time < time() - 3600) {

			// 如果是没有锁住的状态，开始加锁
			if ($lock_state == '0') {
				// 检测到过期，开始获取，此时将lock_flag置1
				$this->config_model->update(array('values'=>'1'), array('name'=>'jsapi_lock_flag', 'values'=>0));
				// 如果没有锁住，并且不足1个小时
				if ($this->db->affected_rows() <= 0 && $expire_time > time() - 3600) {
					return ($need_expire_time ? $para_array : $para_array['jsapi_ticket']);
				}
			}

			$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$this->get_access_token();
			$res = json_decode($this->_httpGet($url), TRUE);
			$ticket = $res['ticket'];
			// 获取成功，修改数据库
			if ($ticket) {
				$expire_time = time() + $res['expires_in'] - 200;
				$this->config_model->update_config(array('ticket_expire_time'=>$expire_time, 'jsapi_ticket'=>$ticket, 'jsapi_lock_flag'=>'0'));

				$para_array['jsapi_ticket'] = $ticket;
				$para_array['ticket_expire_time'] = $expire_time;
			}
			// 获取失败，则解锁
			else {
				$this->config_model->update_config(array('jsapi_lock_flag'=>'0'));
			}
		}
		return ($need_expire_time ? $para_array : $para_array['jsapi_ticket']);
	}

	/**
	 * 辅助函数，curl获取通过get获取http
	 * @param  string		$url	生成的字符长度
	 * @return mixed 		内容
	 */
	private function _httpGet($url)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_URL, $url);

		$res = curl_exec($curl);
		curl_close($curl);

		return $res;
	}


	/**
	 * 微信上传接口 - 从微信获取图片并上传到七牛
	 * @param  string|array		$serverId	微信图片id，多个以逗号分隔或者直接传数组
	 * @param string		$tag		上传文件存放目录（默认avatar）
	 * @param array			$crop_para	裁剪的参数，只对头像上传有用
	 * @return array 		文件数组
	 */
	public function get_image_by_weixin($serverId, $tag = 'avatar', $crop_para=array() ){
		$this->load->library('upload');
		$this->load->helper('string');

		if (!is_array($serverId)){
			$serverId = explode(',', $serverId);
		}

		$access_token = $this->get_access_token();
		//返回的文件数组
		$output = array();

		$upload_path = './uploads/' . $tag;

		foreach ($serverId as $id){
			if (!$id) continue;

			//调用微信的接口得到照片并保存在本地
			$url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=$access_token&media_id=$id";
			$img = @file_get_contents($url);

			//异常的情况添加log
			if (count($http_response_header) != 7) {
				//微信成功返回的头信息有7个， 失败则只有5个， 异常的情况
				$this->log->write_log_json(
					array(
						'http_response_header' => $http_response_header,
						'result' => $img,
						'media_id' => $id
					),
					'upload_weixin');
				//直接返回空数组
				return array();
			}

			//更改头像命名规则
			$avatar = time().strtolower(random_string('alnum',10)) . '.jpg';
			$filename = $upload_path . '/' . $avatar;
			file_put_contents($filename, $img);

			//如果是头像，则需要进行裁剪
			if ($tag == 'avatar') {
				//对原图进行裁剪生成缩略图
				//首先获取x,y,w,h参数
				$x = $crop_para['image_x'];
				$y = $crop_para['image_y'];
				$w = $crop_para['image_w'];
				$h = $crop_para['image_h'];
				$xrb = $crop_para['image_xbr'];
				$yrb = $crop_para['image_ybr'];
				//获取原图的尺寸
				$origin = getimagesize($filename);

				$origin_width = $origin[0];	//获取图像的宽
				$origin_height =$origin[1];	//获取图像的高
				//目标图片的宽长
				$target_width = 750;
				$target_height = 750;
				//获取图片裁剪的一些参数
				$this->load->library('image_lib');
				$image_size = $this->image_lib->getResizeThumbSize($x, $y, $w, $h, $xrb, $yrb, $origin_width, $origin_height);

				$thumb_image_url = $filename;//目标缩略图路径
				$image_url = $filename;//大图图片路径
				//裁剪生成缩略图
				$this->image_lib->resizeThumbnailImage($thumb_image_url, $image_url, $image_size['crop_width'], $image_size['crop_height'], $image_size['start_width'], $image_size['start_height'], $target_width, $target_height);

			}

			// 上传七牛
			$this->upload->upload_to_qiniu(array(array('path'=>$filename,'key'=>$tag . '/'. $avatar)));

			$output[] = $avatar;
		}

		return $output;
	}


	/**
	 * 生成推广二维码
	 * @param int 	$id			生成的二维码id
	 * @return string $image 	生成的二维码图片地址
	 * */
	public function generate_qr($id) {
		$this->load->model('config_model');
		$access_token = $this->config_model->get_rows_by_name_array('access_token');

		//永久二维码post参数
		$qrcode = '{ "action_name": "QR_LIMIT_SCENE", "action_info": { "scene": { "scene_id": '.$id.' } } }';

		$url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$access_token['access_token'];
		$this->load->helper('url');
		$result = post_request_https($url, $qrcode);
		$jsoninfo = json_decode($result, true);
		if (isset($jsoninfo['ticket'])) {
			$ticket = $jsoninfo['ticket'];
			$img=  "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$ticket;
			return $img;
		} else {
			return null;
		}

	}

	/**
	 * 给用户发送客服信息
	 * @param string	$openid		微信的openid
	 * @param string	$type		发送的信息类型
	 * @param array		$content	发送的信息体
	 * @return mixed	微信的返回结果
	 * */
	public function send_customer_message($openid, $type, $content = array()) {
		$this->load->model('config_model');
		$access_token = $this->config_model->get_rows_by_name_array('access_token');

		//生成需要post的参数
		if ($type=="text") {
			$msg = '{"content":"'.$content['content'].'"}';
		} else if ($type=="image") {
			$msg = '{"media_id":"'.$content['media_id'].'"}';
		} else if ($type=="voice") {
			$msg = '{"media_id":"'.$content['media_id'].'"}';
		} else if ($type=="video") {
			$msg = '{"media_id":"'.$content['media_id'].'",
			"thumb_media_id":"'.$content['thumb_media_id'].'",
			"title":"'.$content['title'].'",
			"description":"'.$content['description'].'" }';
		} else if ($type=="music") {
			$msg = '{"title":"'.$content['title'].'",
			"description":"'.$content['description'].'",
			"musicurl":"'.$content['musicurl'].'",
			"hqmusicurl":"'.$content['hqmusicurl'].'",
			"thumb_media_id":"'.$content['thumb_media_id'].'" }';
		} else if ($type=="news") {
			$msg = '{"articles":[';
			foreach ($content as $row) {
				$msg .= '{"title":"'.$row['title'].'",
             "description":"'.$row['description'].'",
             "url":"'.$row['url'].'",
             "picurl":"'.$row['picurl'].'"
				}';
				if ($row!=end($content)) {
					$msg .= ",";
				}
			}
			$msg .= "]}";
		} else if ($type=="mpnews") {
			$msg = '{"media_id":"'.$content['media_id'].'"}';
		} else if ($type=="wxcard") {
			$msg = '{  "card_id":"'.$content['card_id'].'",
           "card_ext": "'.$content['card_ext'].'"}';
		}

		if (isset($msg)) {
			$data ='{"touser":"'.$openid.'","msgtype":"'.$type.'","'.$type.'":'.$msg.'}';
			$url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$access_token['access_token'];

			$this->load->helper('network');
			$result = post_request_https($url, $data);
			return $result;
		}

		return NULL;
	}


	/**
	 * 上传微信临时素材
	 * @param string	$type		上传的信息类型
	 * @param string	$filepath	上传的文件
	 * @return mixed	微信的返回结果
	 * */
	public function upload_temp_media($type, $filepath) {
		$this->load->model('config_model');
		$access_token = $this->config_model->get_rows_by_name_array('access_token');

		$url = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=".$access_token['access_token']."&type=".$type;
		$filedata = array("media"  => curl_file_create($filepath));

		$this->load->helper('network');
		$result_media = post_request_https($url, $filedata);

		$result = json_decode($result_media, TRUE);
		return $result;

	}


}


