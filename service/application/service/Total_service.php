<?php
/**
 * 用户数据统计
 * Created by PhpStorm.
 * User: CaiZhenYu
 * Date: 2016/5/10
 * Time: 11:55
 */

class Total_service extends MY_Service {

	public $_api_code = 666;	//编码在看...

	public $user_list;			//用户信息

	public $user_total;			//用户已存在的统计信息

	public $user_total_new;		//用户新增的统计信息
	public $user_total_merge;	//用户新增的统计信息 -- 特殊处理的部分，首月统计数据合并

	public $time;				//当前时间

	public $user_num = '';		//每次初始化的用户数 默认一次性初始化

	public $type = 1;			//type:1对未统计的用户初始化统计数据，2定时更新相关统计数据

	public function __construct()
	{
		parent::__construct();
		$this->load->model('user_model');
		$this->load->model('bi_model');
		$this->load->model('match_model');
		$this->load->model('user_total_model');
		$this->load->model('invite_model');
		$this->load->model('auth_model');
		$this->load->model('user_album_model');
		$this->load->model('task_model');
		$this->load->model('task_log_model');
		$this->load->model('activity_sign_model');
		$this->time = time();
	}

	/**
	 *更新统计数据
	 */
	public function main_update(){
		//获取之前统计的时间节点
		$update_data_key = 'update_data_key';
		$last_time = $this->log->get_log_string($update_data_key);

//		$time = $this->time.'0000';
		$time = $this->time;

//		$this->user_model->set_table_name('p_userdetail');
//		$this->user_list = $this->user_model->select(array('last_time >=' => $last_time, 'last_time <' => $time));
//		$this->user_model->set_table_name('p_userbase');

		//---------改由从bi获取需要更新的用户--------------
		$this->user_model->db->where(array('time >=' => $last_time, 'time <' => $time, 'type' => 4));
		$this->user_model->db->select('p_bi.time, p_userdetail.*');
		$this->user_model->db->from('p_bi');
		$this->user_model->db->join('p_userdetail', 'p_bi.uid = p_userdetail.uid', 'RIGHT');
		$query = $this->user_model->db->get();
		$this->user_list = $this->user_model->process_sql($query);

		//去重
		$flag_user_list = array();
		foreach ($this->user_list as $key=>$val){
			$flag_user_list[$val['uid']] = $val;
		}
		$this->user_list = $flag_user_list;
		//---------获取需要更新的用户结束--------------

		foreach ($this->user_list as $val) {
			$this->user_total_new = array();
			$this->user_total_merge = array();

			$this->get_user_total($val['uid']);

			//注册相关的统计
			$this->register_total($val);

			//匹配成功相关统计
			$this->macth_suc_total($val);

			//参与匹配相关统计
			$this->match_total($val);

			//邀请相关统计 -- 这里只取月度统计数据
			$this->invite_total($val);

			//提取要更新的项
			$flag_update = array();
			$update_type = array(
				1,2,3,4,5,6,7,9,10,11,66
			);
			foreach ($this->user_total as $v) {
				if (in_array($v['type'], $update_type))
					$flag_update[$v['type']] = $v;
			}

			//月统计项
			$month_total = $this->user_total_model->get_one(array('uid' => $val['uid'], 'type' => 8));
			if (empty($month_total)){
				foreach ($this->user_total_merge as $k=>$v){
					//目前就一种特殊情况 不做区分判断
					$content = '';
					if ($v['type'] == 8){
						$content = 'match_num:' . $v['content'] . ',';
					} elseif($v['type'] == 14){
						$content = 'invite_num:' . $v['content'] . ',';
					} elseif($v['type'] == 15){
						$content = 'response_num:' . $v['content'] . ',';
					}

					if (isset($this->user_total_merge[8])){
						$this->user_total_merge[8]['content'] .= $content;
					} else {
						$this->user_total_merge[8] = array(
							'uid' => $v['uid'],
							'type' => 8,
							'content' => $content,
							'create_time' => $v['create_time'],
							'update_time' => $v['update_time'],
						);
					}
				}
				if (isset($this->user_total_merge[8]) && !empty($this->user_total_merge[8]))
					$this->user_total_model->insert($this->user_total_merge[8]);

			}

			$flag_insert = array();
			foreach ($this->user_total_new as $v) {
				//剔除非定时更新的
				if (!in_array($v['type'], $update_type)){
					continue;
				}

				if (!isset($flag_update[$v['type']])){
					$flag_insert[] = $v;
				} elseif(($v['type'] == 1 || $v['type'] == 66 || $v['type'] == 3) && $v['content'] != 0) {
					//更新
					$this->user_total_model->update(array('content' => $v['content']), array('id' => $flag_update[$v['type']]['id']));
				}
			}
			if ($flag_insert)
				$this->user_total_model->insert_batch($flag_insert);

		}

		//保存统计的时间节点
		$this->log->write_log_string($time, $update_data_key);

	}

	/**
	 *初始化统计数据
	 */
	public function main(){
		$this->get_user_list();

		foreach ($this->user_list as $val) {
			$this->user_total_new = array();
			$this->user_total_merge = array();

			$this->get_user_total($val['uid']);

			//注册相关的统计
			$this->register_total($val);

			//匹配成功相关统计
			$this->macth_suc_total($val);

			//参与匹配相关统计
			$this->match_total($val);

			//邀请相关统计
			$this->invite_total($val);

			//认证相关统计
			$this->auth_total($val);

			//头像相关统计
			$this->avatar_total($val);

			//相册相关统计
			$this->album_total($val);

			//资料完整度相关统计
			$this->complete_total($val);

			//申请推荐相关统计
			$this->recommend_total($val);

			//活动相关统计
			$this->activity_total($val);

			$this->save($val);

		}

	}

	//保存
	public function save($user_info){

		if (!empty($this->user_total)){
			//全量更新
			$this->user_total_model->delete(array('uid' => $user_info['uid']));
		}
		
		$this->user_total_model->insert_batch($this->user_total_new);

		foreach ($this->user_total_merge as $key=>$val){
			//目前就一种特殊情况 不做区分判断
			$content = '';
			if ($val['type'] == 8){
				$content = 'match_num:' . $val['content'] . ',';
			} elseif($val['type'] == 14){
				$content = 'invite_num:' . $val['content'] . ',';
			} elseif($val['type'] == 15){
				$content = 'response_num:' . $val['content'] . ',';
			}

			if (isset($this->user_total_merge[8])){
				$this->user_total_merge[8]['content'] .= $content;
			} else {
				$this->user_total_merge[8] = array(
					'uid' => $val['uid'],
					'type' => 8,
					'content' => $content,
					'create_time' => $val['create_time'],
					'update_time' => $val['update_time'],
				);
			}
		}
		if (isset($this->user_total_merge[8]) && !empty($this->user_total_merge[8]))
			$this->user_total_model->insert($this->user_total_merge[8]);



		//保存统计的用户节点
		$reset_data_key = 'reset_data_key';
		$this->log->write_log_string($user_info['uid'], $reset_data_key);

	}

	//活动相关统计
	public function activity_total($user_info){
		$activity_sign = $this->activity_sign_model->select(array('uid' => $user_info['uid'], 'pay_state' => 2));

		foreach ($activity_sign as $key=>$val){
			$this->add_user_total($user_info['uid'], 34, $val['aid'], $val['create_time'], $val['create_time']);
		}

	}

	//申请推荐相关统计
	public function recommend_total($user_info){
		$this->user_model->set_table_name('p_recommend_apply');
		$recommend_first = $this->user_model->get_one(array('uid' => $user_info['uid'], 'type' => 0), FALSE, '*', 'create_time ASC');
		$this->user_model->set_table_name('p_userbase');

		if (!empty($recommend_first))
			$this->add_user_total($user_info['uid'], 33, '', $recommend_first['create_time'], $recommend_first['create_time']);

	}

	//资料完整度相关统计
	public function complete_total($user_info){
		//获取任务相关的id
		$task_list = $this->task_model->select(array('keyword' => 'task_user_complete'));
		if (empty($task_list))
			return FALSE;

		$task_id_list = array();
		foreach ($task_list as $val) {
			$task_id_list[] = $val['t_id'];
		}
		//获取首次完成记录
		$task_log = $this->task_log_model->get_one(array('uid' => $user_info['uid'], 't_id' => $task_id_list, 'state' => 0), FALSE, '*', 'create_time ASC');

		if (!empty($task_log))
			$this->add_user_total($user_info['uid'], 32, '', $task_log['create_time'], $task_log['create_time']);

		return TRUE;
	}

	//相册相关统计
	public function album_total($user_info){
		$auth_list = $this->user_album_model->select(array('uid' => $user_info['uid']));

		foreach ($auth_list as $val){
			$this->add_user_total($user_info['uid'], 31, $val['image'], $val['create_time'], $val['create_time']);
		}

	}

	//头像相关统计
	public function avatar_total($user_info){
		if ($user_info['avatar']){
			$auth_list = $this->bi_model->get_one(array('uid' => $user_info['uid'], 'type' => 5), FALSE, '*', 'time DESC');

			if (!empty($auth_list)){
				$this->add_user_total($user_info['uid'], 30, $user_info['avatar'], $auth_list['time'], $auth_list['time']);
			} else {
				$this->add_user_total($user_info['uid'], 30, $user_info['avatar'], $this->time, $this->time);
			}
		}

	}

	//认证相关统计
	public function auth_total($user_info){
		$auth_list = $this->auth_model->select(array('uid' => $user_info['uid'], 'done_flag' => 1), FALSE, '*', '', 'review_time DESC', '', 'type');

		if (isset($auth_list[1])){
			$review_time = $auth_list[1]['review_time'] == 0 ? $auth_list[1]['create_time'] : $auth_list[1]['review_time'];
			$this->add_user_total($user_info['uid'], 26, $auth_list[1]['auth_id'], $review_time, $review_time);
		}
		if (isset($auth_list[2])){
			$review_time = $auth_list[2]['review_time'] == 0 ? $auth_list[2]['create_time'] : $auth_list[2]['review_time'];
			$this->add_user_total($user_info['uid'], 27, $auth_list[2]['auth_id'], $review_time, $review_time);
		}
		if (isset($auth_list[3])){
			$review_time = $auth_list[3]['review_time'] == 0 ? $auth_list[3]['create_time'] : $auth_list[3]['review_time'];
			$this->add_user_total($user_info['uid'], 28, $auth_list[3]['auth_id'], $review_time, $review_time);
		}
		if (isset($auth_list[4])){
			$review_time = $auth_list[4]['review_time'] == 0 ? $auth_list[4]['create_time'] : $auth_list[4]['review_time'];
			$this->add_user_total($user_info['uid'], 29, $auth_list[4]['auth_id'], $review_time, $review_time);
		}

	}

	//邀请相关统计
	public function invite_total($user_info){
		$invite_list = $this->get_invite_list($user_info['uid'], $user_info['gender']);

		$invite_month = 0;	//月邀请次数
		$invited_month = 0;	//月被邀请次数

		$invite = 0;		//邀请次数
		$invite_suc = 0;	//邀请成功次数
		$invited = 0;		//被邀请次数
		$invited_suc = 0;	//接受邀请次数

		$invite_first = false;	//是否已记录首次邀请
		$invited_first = false;	//是否已记录首次被邀请

		$invite_10 = false;		//是否已记录第10次邀请
		$invite_20 = false;		//是否已记录第20次邀请
		$invite_100 = false;	//是否已记录第100次邀请
		$invited_10 = false;	//是否已记录第10次被邀请
		$invited_20 = false;	//是否已记录第10次被邀请
		$invited_100 = false;	//是否已记录第10次被邀请

		$month_time = strtotime(date('Y-m-d', $user_info['register_time']) . ' +1 month ');		//月时间点

		foreach ($invite_list as $key=>$val){
			if ($user_info['gender'] == 1){
				switch ($val['state']) {
					case 1:
						$invite++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invite_month++;
						if (!$invite_first){
							$this->add_user_total($user_info['uid'], 35, $val['uid_2'], $val['create_time'], $val['create_time']);
							$invite_first = true;
						}
						break;
					case 2:
						//邀请成功
						$this->add_user_total($user_info['uid'], 13, $val['uid_2'], $val['create_time'], $val['create_time']);
						$invite_suc++;
						$invite++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invite_month++;
						break;
					case 3:
						$invited++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invited_month++;
						if (!$invited_first){
							$this->add_user_total($user_info['uid'], 36, $val['uid_2'], $val['create_time'], $val['create_time']);
							$invited_first = true;
						}
						break;
					case 4:
						//接受邀请
						$this->add_user_total($user_info['uid'], 12, $val['uid_2'], $val['create_time'], $val['create_time']);
						$invited_suc++;
						$invited++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invited_month++;
						break;
					default:
						break;
				}
			}
			if ($user_info['gender'] == 2){
				switch ($val['state']) {
					case 3:
						$invite++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invite_month++;
						if (!$invite_first){
							$this->add_user_total($user_info['uid'], 35, $val['uid_1'], $val['create_time'], $val['create_time']);
							$invite_first = true;
						}
						break;
					case 4:
						//邀请成功
						$this->add_user_total($user_info['uid'], 13, $val['uid_1'], $val['create_time'], $val['create_time']);
						$invite_suc++;
						$invite++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invite_month++;
						break;
					case 1:
						$invited++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invited_month++;
						if (!$invited_first){
							$this->add_user_total($user_info['uid'], 36, $val['uid_1'], $val['create_time'], $val['create_time']);
							$invited_first = true;
						}
						break;
					case 2:
						//接受邀请
						$this->add_user_total($user_info['uid'], 12, $val['uid_1'], $val['create_time'], $val['create_time']);
						$invited_suc++;
						$invited++;
						if ($val['create_time'] <= $month_time && $month_time <= $this->time)
							$invited_month++;
						break;
					default:
						break;
				}
			}

			if ($invite == 10 && !$invite_10){
				$this->add_user_total($user_info['uid'], 16, $val['iid'], $val['create_time'], $val['create_time']);
				$invite_10 = true;
			}
			if ($invite == 20 && !$invite_20){
				$this->add_user_total($user_info['uid'], 17, $val['iid'], $val['create_time'], $val['create_time']);
				$invite_20 = true;
			}
			if ($invite == 100 && !$invite_100){
				$this->add_user_total($user_info['uid'], 18, $val['iid'], $val['create_time'], $val['create_time']);
				$invite_100 = true;
			}
			if ($invited == 10 && !$invited_10){
				$this->add_user_total($user_info['uid'], 19, $val['iid'], $val['create_time'], $val['create_time']);
				$invited_10 = true;
			}
			if ($invited == 20 && !$invited_20){
				$this->add_user_total($user_info['uid'], 20, $val['iid'], $val['create_time'], $val['create_time']);
				$invited_20 = true;
			}
			if ($invited == 100 && !$invited_100){
				$this->add_user_total($user_info['uid'], 21, $val['iid'], $val['create_time'], $val['create_time']);
				$invited_100 = true;
			}

		}

		$flag_last = end($invite_list);
		if (count($invite_list) == 0){
			$create_time = $this->time;
		} else {
			$create_time = $flag_last['create_time'];
		}
		$this->add_user_total($user_info['uid'], 22, $invite, $create_time, $create_time);
		$this->add_user_total($user_info['uid'], 23, $invited, $create_time, $create_time);
		$this->add_user_total($user_info['uid'], 24, $invite_suc, $create_time, $create_time);
		$this->add_user_total($user_info['uid'], 25, $invited_suc, $create_time, $create_time);


		if ($month_time <= $this->time){
			$this->add_user_total_merge($user_info['uid'], 14, $invite_month, $month_time, $month_time);
			$this->add_user_total_merge($user_info['uid'], 15, $invited_month, $month_time, $month_time);
		}

	}

	//注册相关的统计
	public function register_total($user_info){
		//注册时间
		$this->add_user_total($user_info['uid'], 9, $user_info['register_time'], $user_info['register_time'], $user_info['register_time']);

		//第100天
		$register_100 = strtotime(date('Y-m-d', $user_info['register_time']) . ' +100 day ');
		//第365天
		$register_365 = strtotime(date('Y-m-d', $user_info['register_time']) . ' +365 day ');

		if ($register_100 <= $this->time)
			$this->add_user_total($user_info['uid'], 10, $register_100, $register_100, $register_100);
		if ($register_365 <= $this->time)
			$this->add_user_total($user_info['uid'], 11, $register_365, $register_365, $register_365);

	}

	//匹配成功相关统计
	public function macth_suc_total($user_info){
		//获取匹配成功次数
		$match_suc_list = $this->get_match_suc_list($user_info['uid'], $user_info['gender']);

		//匹配到的异性总人数
		$match_suc_total = count($match_suc_list);
		$match_suc_end = end($match_suc_list);

		if ($match_suc_total != 0){
			$this->add_user_total($user_info['uid'], 3, $match_suc_total, $match_suc_end['create_time'], $match_suc_end['create_time']);
		} else {
			$this->add_user_total($user_info['uid'], 3, $match_suc_total, $this->time, $this->time);
		}

		if ($match_suc_total >= 1000){
			//匹配第1000人
			$match_suc_end = $match_suc_list[999];
			$this->add_user_total($user_info['uid'], 4, $match_suc_end['mid'], $match_suc_end['create_time'], $match_suc_end['create_time']);
		}
		if ($match_suc_total >= 100){
			//匹配第100人
			$match_suc_end = $match_suc_list[99];
			$this->add_user_total($user_info['uid'], 5, $match_suc_end['mid'], $match_suc_end['create_time'], $match_suc_end['create_time']);
		}
		if ($match_suc_total >= 10){
			//匹配第10人
			$match_suc_end = $match_suc_list[9];
			$this->add_user_total($user_info['uid'], 6, $match_suc_end['mid'], $match_suc_end['create_time'], $match_suc_end['create_time']);
		}

		$match_suc = 0;			//匹配成功次数
		$match_suc_flag = 0;	//标记匹配时间点，用于统计成功次数
		$match_suc_week = 0;	//周匹配人数
		$match_suc_month = 0;	//月匹配人数
		$week_time = strtotime(date('Y-m-d', $user_info['register_time']) . ' +1 week ');		//周时间点
		$month_time = strtotime(date('Y-m-d', $user_info['register_time']) . ' +1 month ');		//月时间点
		foreach ($match_suc_list as $key=>$val){
			if ($match_suc_flag != $val['create_time']){
				$match_suc++;
				$match_suc_flag = $val['create_time'];
			}

			if ($val['create_time'] <= $week_time && $week_time <= $this->time)
				$match_suc_week++;
			if ($val['create_time'] <= $month_time && $month_time <= $this->time)
				$match_suc_month++;
		}
		$this->add_user_total($user_info['uid'], 66, $match_suc, $this->time, $this->time);

		if ($week_time <= $this->time)
			$this->add_user_total($user_info['uid'], 7, $match_suc_week, $week_time, $week_time);
		if ($month_time <= $this->time)
			$this->add_user_total_merge($user_info['uid'], 8, $match_suc_month, $month_time, $month_time);

	}

	//参与匹配相关统计
	public function match_total($user_info){
		//获取参与匹配列表
		$match_list = $this->get_match_list($user_info['uid']);

		//总匹配次数
		$match_count = count($match_list);
		$match_end = end($match_list);

		if ($match_count != 0){
			$this->add_user_total($user_info['uid'], 1, $match_count, $match_end['time'], $match_end['time']);
		} else {
			$this->add_user_total($user_info['uid'], 1, $match_count, $this->time, $this->time);
		}

		if ($match_count >= 1){
			//首次匹配
			$match_first = $this->user_total_model->get_one(array('uid' => $user_info['uid'], 'type' => 2));
			if (empty($match_first))
				$this->add_user_total($user_info['uid'], 2, '', $match_list[0]['time'], $match_list[0]['time']);
		}
	}

	public function add_user_total($uid, $type, $content, $create_time, $update_time){
		$this->user_total_new[] = array(
			'uid' => $uid,
			'type' => $type,
			'content' => $content,
			'create_time' => $create_time,
			'update_time' => $update_time,
		);
	}

	public function add_user_total_merge($uid, $type, $content, $create_time, $update_time){
		$this->user_total_merge[] = array(
			'uid' => $uid,
			'type' => $type,
			'content' => $content,
			'create_time' => $create_time,
			'update_time' => $update_time,
		);
	}

	//获取参与匹配列表
	public function get_match_list($uid){
		return $this->bi_model->select(array('uid' => $uid, 'type' => 4));
	}

	//获取邀请列表
	public function get_invite_list($uid, $gender){
		if ($gender == 1){
			$where = array('uid_1' => $uid);
		} else {
			$where = array('uid_2' => $uid);
		}

		$p_invite = $this->invite_model->select($where, FALSE, "*", '', 'create_time ASC');

		$this->invite_model->set_table_name('r_invite');
		$r_invite = $this->invite_model->select($where, FALSE, "*", '', 'create_time ASC');
		$this->invite_model->set_table_name('p_invite');

		return array_merge($r_invite, $p_invite);

	}

	//获取匹配成功列表
	public function get_match_suc_list($uid, $gender){
		if ($gender == 1){
			$where = array('uid_1' => $uid, 'hidden_1' => 0, 'hidden_2' => 0);
		} else {
			$where = array('uid_2' => $uid, 'hidden_1' => 0, 'hidden_2' => 0);
		}

		return $this->match_model->select($where, FALSE, "*", '', 'create_time ASC');

	}

	//获取用户统计信息
	public function get_user_total($uid){
		$this->user_total = $this->user_total_model->select(array('uid' => $uid));

	}

	//获取未统计的用户列表
	public function get_user_list(){
		//获取之前统计的用户节点
		$reset_data_key = 'reset_data_key';
		$last_uid = $this->log->get_log_string($reset_data_key);

		if ($last_uid) {
			$where = array(
				'uid > ' => $last_uid
			);
		} else {
			$where = array(

			);
		}

		$this->user_model->set_table_name('p_userdetail');
		$this->user_list = $this->user_model->select($where, FALSE, '*', $this->user_num, 'uid ASC');
		$this->user_model->set_table_name('p_userbase');

	}

	//接受邀请 实时统计
	public function set_match_response($uid, $to_uid){
		//接受邀请
		$user_total[] = array(
			'uid' => $uid,
			'type' => 12,
			'content' => $to_uid,
			'create_time' => $this->time,
			'update_time' => $this->time,
		);
		//邀请成功
		$user_total[] = array(
			'uid' => $to_uid,
			'type' => 13,
			'content' => $uid,
			'create_time' => $this->time,
			'update_time' => $this->time,
		);
		$this->user_total_model->insert_batch($user_total);
		//接受邀请数量
		$this->user_total_model->update(array('content' => '+=1'), array('uid' => $uid, 'type' => 25));
		//邀请成功数量
		$this->user_total_model->update(array('content' => '+=1'), array('uid' => $to_uid, 'type' => 24));
	}

	//发起邀请 实时统计
	public function set_match_invite($uid, $to_uid, $iid){
		$user_total = array();	//需要插入的新统计数据
		//获取邀请者的邀请数量
		$invite_total = $this->user_total_model->get_one(array('uid' => $uid, 'type' => 22));
		if ($invite_total['content'] == 9){
			//第10个邀请
			$user_total[] = array(
				'uid' => $uid,
				'type' => 16,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		} elseif ($invite_total['content'] == 19){
			//第20个邀请
			$user_total[] = array(
				'uid' => $uid,
				'type' => 17,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		} elseif ($invite_total['content'] == 99){
			//第100个邀请
			$user_total[] = array(
				'uid' => $uid,
				'type' => 18,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		} elseif ($invite_total['content'] == 0){
			//第1个邀请
			$user_total[] = array(
				'uid' => $uid,
				'type' => 35,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		}
		//邀请数量更新
		$this->user_total_model->update(array('content' => '+=1'), array('uid' => $uid, 'type' => 22));

		//获取被邀请者的被邀请数量
		$invited_total = $this->user_total_model->get_one(array('uid' => $to_uid, 'type' => 23));
		if ($invited_total['content'] == 9){
			//收到第10个邀请
			$user_total[] = array(
				'uid' => $to_uid,
				'type' => 19,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		} elseif ($invited_total['content'] == 19){
			//收到第20个邀请
			$user_total[] = array(
				'uid' => $to_uid,
				'type' => 20,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		} elseif ($invited_total['content'] == 99){
			//收到第100个邀请
			$user_total[] = array(
				'uid' => $to_uid,
				'type' => 21,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		} elseif ($invited_total['content'] == 0){
			//收到第1个邀请
			$user_total[] = array(
				'uid' => $to_uid,
				'type' => 36,
				'content' => $iid,
				'create_time' => $this->time,
				'update_time' => $this->time,
			);
		}
		//被邀请数量更新
		$this->user_total_model->update(array('content' => '+=1'), array('uid' => $to_uid, 'type' => 23));

		if ($user_total)
			$this->user_total_model->insert_batch($user_total);
	}

	//上传相册 实时统计
	public function set_user_album($uid, $image){
		$user_total = array(
			'uid' => $uid,
			'type' => 31,
			'content' => $image,
			'create_time' => $this->time,
			'update_time' => $this->time,
		);
		$this->user_total_model->insert($user_total);
	}

	//新注册用户初始化相关数据
	public function reg_init($uid){
		$user_total[] = array(
			'uid' => $uid,'type' => 1,'content' => 0,'create_time' => $this->time,'update_time' => $this->time,
		);
		$user_total[] = array(
			'uid' => $uid,'type' => 3,'content' => 0,'create_time' => $this->time,'update_time' => $this->time,
		);
		$user_total[] = array(
			'uid' => $uid,'type' => 9,'content' => $this->time,'create_time' => $this->time,'update_time' => $this->time,
		);
		$user_total[] = array(
			'uid' => $uid,'type' => 22,'content' => 0,'create_time' => $this->time,'update_time' => $this->time,
		);
		$user_total[] = array(
			'uid' => $uid,'type' => 23,'content' => 0,'create_time' => $this->time,'update_time' => $this->time,
		);
		$user_total[] = array(
			'uid' => $uid,'type' => 24,'content' => 0,'create_time' => $this->time,'update_time' => $this->time,
		);
		$user_total[] = array(
			'uid' => $uid,'type' => 25,'content' => 0,'create_time' => $this->time,'update_time' => $this->time,
		);
		$user_total[] = array(
			'uid' => $uid,'type' => 66,'content' => 0,'create_time' => $this->time,'update_time' => $this->time,
		);

		$this->user_total_model->insert_batch($user_total);
	}

}