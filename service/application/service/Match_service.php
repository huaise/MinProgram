<?php
/**
 * 匹配/邀请服务
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/2/17
 * Time: 11:55
 */

class Match_service extends MY_Service {

	/**
	 * 匹配允许的最低得分
	 *
	 * @var	int
	 */
	private $_min_score_allowed = 40;

	public $bi_user_list = array();			//所有参与匹配的用户，写入bi表

	public $user_list = array();			//匹配的用户列表
	public $fail_user_list = array();		//匹配失败的用户列表
	public $wake_user_list = array();		//唤醒的用户列表
	public $matched_list = array();			//已匹配的用户列表
	public $before_user_list = array();		//符合二次匹配要求的用户

	public $score_list = array();			//打分列表:保存分数，第一个key是from_uid，即打分者uid，第二个key是to_uid，即被打分者uid

	public $current_day_timestamp = 0;		// 时间基点 当天0点对应的时间戳

	public $uid_push_array = array();		//匹配成功的uid列表，用于最后的推送，其key为uid

	public $province = 0;					// 匹配的省
	public $city = 0;						// 匹配的市
	public $create_time = 0;				// 匹配的时间点

	public $user_fileds = 'uid, gender, birthday, height, weight, salary, education, auth_education,
				target_age_min, target_age_max, target_education, target_height_min, target_height_max,
				target_salary, target_look, salary_vs_look,
				look_grade_total, look_grade_times, vip_date, last_time';		//用户表查询的列

	/**
	 * 匹配项是否一票否决
	 *
	 * @var	int
	 */
	private $_strict_mode = array('age'=>FALSE, 'look'=>FALSE, 'education'=>FALSE, 'salary'=>FALSE, 'height'=>FALSE, 'weight'=>FALSE);


	public function __construct()
	{
		parent::__construct();
		$this->_log_tag = 'match';
		$this->current_day_timestamp = strtotime(date('Y-m-d'));
	}

	/**
	 * 初始化匹配参数
	 *
	 * @param int		$num_of_user	参与匹配的用户数量
	 * @return null
	 */
	private function _init_parameters($num_of_user) {
		$this->_min_score_allowed = 40;

		// 匹配项改为不按照人数来判断是否一票否决
		// 一票否定项：年龄，身高，学历，（男生配女生时，女生的颜值），（女生配男生时，男生的收入）

//		if ($num_of_user < 500) {
//
//		} else if ($num_of_user < 1000) {
//			$this->_strict_mode['age'] = TRUE;
//			$this->_strict_mode['look'] = TRUE;
//			$this->_strict_mode['education'] = TRUE;
//		} else if ($num_of_user < 2000) {
//			$this->_strict_mode['age'] = TRUE;
//			$this->_strict_mode['look'] = TRUE;
//			$this->_strict_mode['education'] = TRUE;
//			$this->_strict_mode['salary'] = TRUE;
//		} else {
//			$this->_strict_mode['age'] = TRUE;
//			$this->_strict_mode['look'] = TRUE;
//			$this->_strict_mode['education'] = TRUE;
//			$this->_strict_mode['salary'] = TRUE;
//			$this->_strict_mode['height'] = TRUE;
//			$this->_strict_mode['weight'] = TRUE;
//		}

		$this->_strict_mode['age'] = TRUE;
		$this->_strict_mode['education'] = TRUE;
		$this->_strict_mode['height'] = TRUE;

		//初始化
		$this->fail_user_list = array();
		$this->uid_push_array = array();
	}

	/**
	 * 对指定的省份城市开始一次匹配
	 *
	 * @param int		$province		省份id
	 * @param int		$city			城市id
	 * @param int		$create_time	本次匹配对应的创建时间，在这个时间到之前，客户端无法看到本次的匹配结果
	 * @return null
	 */
	public function start_match($province, $city, $create_time)
	{
		set_time_limit(3600);
		ignore_user_abort(TRUE);

		// 如果省份不合法，则退出
		if (!is_id($province)) {
			$this->_log('省份不合法，终止！', TRUE);
			return;
		}

		// 如果城市不合法，则退出
		if (!is_id($city)) {
			$this->_log('城市不合法，终止！', TRUE);
			return;
		}

		// 如果没有设置开始显示时间，则设置为当前时间
		if (!is_id($create_time)) {
			$create_time = time();
		}

		//匹配参数设置
		$this->province = $province;
		$this->city = $city;
		$this->create_time = $create_time;

		// 清空日志字符串
		$this->_log_string = '';

		$this->benchmark->mark('function_start');

		$this->load->model('user_model');
		$this->load->model('area_model');
		$this->load->model('match_model');
		$this->load->helper('date');

		$this->_log('开始为省份id为'.$this->province.'，城市id为'.$this->city.'的城市进行一次匹配', TRUE);

		//匹配用户初始化
		$this->get_user_list();

		//唤醒用户初始化
		$this->get_wake_user_list();

		$uid_array = array_keys($this->user_list);
		$this->bi_user_list = $uid_array;

		if (empty($this->user_list)) {
			$this->_log('没有找到符合要求的用户，终止！', TRUE);
			return;
		}

		foreach ($this->user_list as &$row) {
			//记录唤醒的用户 用于针对唤醒用户的逻辑处理
			$last_time_enabled = ($this->current_day_timestamp - 86400 * 5) * 10000;			// 允许匹配的用户是近五天有登录的用户
			if ($row['last_time'] < $last_time_enabled && !isset($this->wake_user_list[$row['uid']])){
				$this->wake_user_list[$row['uid']] = $row;
			}
		}

		// 根据人数，进行参数控制
		$this->_init_parameters(count($this->user_list));

		// 获取已匹配的记录
		$this->get_matched_list($uid_array);

//		$uid_from = 0;						// 外层循环的uid，即打分者uid
//		$uid_to = 0;						// 内层循环的uid，即被打分者uid
		$candidate_uid_vip_array = array();			// 符合匹配条件的uid的数组，会员
		$candidate_uid_not_vip_array = array();		// 符合匹配条件的uid的数据，非会员

		//获取用户的打分列表
		$this->get_score_list();

		// 下面开始做会员的筛选
		$temp_uid_array = array_keys($this->score_list);
		foreach ($temp_uid_array as $uid) {
			// 根据是否是会员，加入到不同的数组
			if ($this->user_list[$uid]['is_vip']) {
				$candidate_uid_vip_array[] = $uid;
			}
			else {
				$candidate_uid_not_vip_array[] = $uid;
			}
		}
		unset($temp_uid_array);

		// 打乱顺序
		shuffle($candidate_uid_vip_array);
		shuffle($candidate_uid_not_vip_array);
		$candidate_uid_array = array_merge($candidate_uid_vip_array, $candidate_uid_not_vip_array);		// 待匹配的记录，key是顺序，value是uid，会员优先匹配
		$this->_log('获取待匹配的人的uid列表完成，总共'.count($candidate_uid_array).'个待匹配的人', TRUE);

		//做一次双向匹配
		$this->bi_match($candidate_uid_array);

		//单向匹配
		$this->single_match($candidate_uid_array);

		//获取给匹配失败用户匹配的用户
		$this->get_before_user_list();

		$uid_array = array_merge(array_keys($this->fail_user_list), array_keys($this->before_user_list));
		$this->bi_user_list = array_merge($uid_array, $this->bi_user_list);
		// 获取已匹配的记录
		$this->get_matched_list($uid_array);

		//二次匹配
		$this->second_match();

		//将匹配失败的唤醒用户保存下来
		$this->set_wake_user_list();

		// 测试网立即推送，现网会有另外一个脚本执行推送
		if (DEBUG) {
			if (!empty($this->uid_push_array)) {
				$this->benchmark->mark('push_start');
				$this->_log('开始推送', TRUE);
				$this->load->service('push_service');
				$message['type'] = PUSH_MATCH_SUCCEED_INFORM;
				$message['uid'] = array_keys($this->uid_push_array);
				$this->push_service->push_message($message, TRUE);

				$this->benchmark->mark('push_end');
				$this->_log('推送完成，总共花费时间：'.$this->benchmark->elapsed_time('push_start', 'push_end'), TRUE);
			}
			else {
				$this->_log('开始推送', TRUE);
				$this->_log('没有用户需要推送', TRUE);
				$this->benchmark->mark('push_end');
				$this->_log('推送完成，总共花费时间：'.$this->benchmark->elapsed_time('push_start', 'push_end'), TRUE);
			}
		}

		// 添加BI
		$this->benchmark->mark('bi_start');
		$this->_log('开始插入BI', TRUE);

		$bi_list = array();
		foreach ($this->bi_user_list as $uid) {
			//去重
			$bi_list[$uid] = array('uid'=>$uid, 'type'=>4);
		}
		$this->load->model('bi_model');
		$this->bi_model->insert_bi_batch($bi_list);

		$this->benchmark->mark('bi_end');
		$this->_log('插入BI完成，花费时间：'.$this->benchmark->elapsed_time('bi_start', 'bi_end'), TRUE);

		$this->benchmark->mark('function_end');
		$this->_log('本次匹配全部执行完成，总共花费时间：'.$this->benchmark->elapsed_time('function_start', 'function_end'), TRUE);

	}

	//双向匹配
	public function bi_match($candidate_uid_array){
		$this->benchmark->mark('match_start');
		$this->_log('下面开始双向匹配', TRUE);

		$matched_id_arary = array();		// 本次匹配中匹配成功的uid列表
		$current_min_score = 90;

		while(TRUE) {
			while (TRUE) {
				$count = 0;

				foreach ($candidate_uid_array as $from_id) {
					if (in_array($from_id, $matched_id_arary)) {
						continue;
					}

					$max_score = 0;
					$max_to_id = 0;

					foreach ($this->score_list[$from_id] as $to_uid => $score) {
						if (in_array($to_uid, $matched_id_arary)) {
							continue;
						}

						//过滤只是单方面打分合格的
						if (!isset($this->score_list[$from_id][$to_uid]) || !isset($this->score_list[$to_uid][$from_id])){
							continue;
						}

						// 双方的得分都要大于当前要求的最低分
						if ($this->score_list[$from_id][$to_uid]<$current_min_score || $this->score_list[$to_uid][$from_id]<$current_min_score) {
							continue;
						}

						// 计算总分
						$temp_score = $this->score_list[$from_id][$to_uid] + $this->score_list[$to_uid][$from_id];//取得总分
						// 如果是最大的，那么替换掉
						if ($temp_score > $max_score) {
							$max_score = $temp_score;
							$max_to_id = $to_uid;
						}
					}

					// 如果能找到
					if (is_id($max_to_id)) {
						// 从打分中取出彼此打分，避免下一次再匹配到
						unset($this->score_list[$from_id][$max_to_id]);
						unset($this->score_list[$max_to_id][$from_id]);

						// 男性在前
						if ($this->user_list[$from_id]['gender'] == 1) {
							$matched_id_arary[] = $from_id;
							$matched_id_arary[] = $max_to_id;
						}
						else {
							$matched_id_arary[] = $max_to_id;
							$matched_id_arary[] = $from_id;
						}

						$this->uid_push_array[$max_to_id] = TRUE;
						$this->uid_push_array[$from_id] = TRUE;

						$count++;
					}
				}

				$this->_log('当前最低分'.$current_min_score."，匹配到".$count."对", TRUE);

				if ($count == 0) {
					break;
				}
			}

			$current_min_score = $current_min_score - 10;
			if ($current_min_score <= $this->_min_score_allowed) {
				break;
			}
		}

		$this->benchmark->mark('match_end');
		$this->_log('匹配结束,总共有'.count($matched_id_arary).'人匹配成功，花费时间：'.$this->benchmark->elapsed_time('match_start', 'insert_end'), TRUE);

		if (!empty($matched_id_arary)) {
			$this->benchmark->mark('insert_start');
			$this->_log('开始将匹配结果插入数据库', TRUE);
			$matched_data_arary = array();
			foreach ($matched_id_arary as $index=>$uid) {
				if ($index % 2 == 1) {
					continue;
				}

				$matched_data_arary[] = array('uid_1'=>$uid, 'uid_2'=>$matched_id_arary[$index+1], 'create_time'=>$this->create_time);
			}
			$this->match_model->insert_batch($matched_data_arary);
			$this->set_last_match_time($matched_data_arary);

			$this->benchmark->mark('insert_end');
			$this->_log('将匹配结果插入数据库完成，花费时间：'.$this->benchmark->elapsed_time('insert_start', 'insert_end'), TRUE);

			//去除已匹配的唤醒用户
			foreach ($this->wake_user_list as $key=>$val){
				if (in_array($key, $matched_id_arary)){
					unset($this->wake_user_list[$key]);
				}
			}

			//提取匹配失败的用户
			foreach ($this->user_list as $key=>$val){
				if (!in_array($key, $matched_id_arary)){
					$this->fail_user_list[$key] = $val;
				}
			}

		} else {
			//没人成功匹配， 全失败
			$this->fail_user_list = $this->user_list;
		}

	}

	//单向匹配
	public function single_match($candidate_uid_array){
		$this->benchmark->mark('match_start');
		$this->_log('下面开始单向匹配', TRUE);

		$matched_id_arary = array();		// 本次匹配中匹配成功的uid列表
		$current_min_score = 90;

		while(TRUE) {
			while (TRUE) {
				$count = 0;

				foreach ($candidate_uid_array as $from_id) {
					if (in_array($from_id, $matched_id_arary)) {
						continue;
					}

					$max_score = 0;
					$max_to_id = 0;

					foreach ($this->score_list[$from_id] as $to_uid => $score) {
						if (in_array($to_uid, $matched_id_arary)) {
							continue;
						}

						// 得分都要大于当前要求的最低分
						if ($this->score_list[$from_id][$to_uid]<$current_min_score) {
							continue;
						}

						// 计算总分
						$temp_score = $this->score_list[$from_id][$to_uid];//取得总分
						// 如果是最大的，那么替换掉
						if ($temp_score > $max_score) {
							$max_score = $temp_score;
							$max_to_id = $to_uid;
						}
					}

					// 如果能找到
					if (is_id($max_to_id)) {
						// 从打分中取出彼此打分，避免下一次再匹配到
						unset($this->score_list[$from_id][$max_to_id]);
						unset($this->score_list[$max_to_id][$from_id]);

						//匹配者在前
						$matched_id_arary[] = $from_id;
						$matched_id_arary[] = $max_to_id;

						$this->uid_push_array[$from_id] = TRUE;

						$count++;
					}
				}

				$this->_log('当前最低分'.$current_min_score."，匹配到".$count."对", TRUE);

				if ($count == 0) {
					break;
				}
			}

			$current_min_score = $current_min_score - 10;
			if ($current_min_score <= $this->_min_score_allowed) {
				break;
			}
		}

		$this->benchmark->mark('match_end');
		$this->_log('匹配结束,总共有'.(count($matched_id_arary)/2).'人匹配成功，花费时间：'.$this->benchmark->elapsed_time('match_start', 'match_end'), TRUE);

		if (!empty($matched_id_arary)) {
			$this->benchmark->mark('insert_start');
			$this->_log('开始将匹配结果插入数据库', TRUE);
			$matched_data_arary = array();
			foreach ($matched_id_arary as $index=>$uid) {
				if ($index % 2 == 1) {
					continue;
				}

				$matched_data_arary[] = array(
					'uid_1'=>($this->user_list[$uid]['gender']==1 ? $uid : $matched_id_arary[$index+1]),
					'uid_2'=>($this->user_list[$uid]['gender']==1 ? $matched_id_arary[$index+1] : $uid),
					'hidden_1'=>($this->user_list[$uid]['gender']==1 ? 0 : 1),
					'hidden_2'=>($this->user_list[$uid]['gender']==1 ? 1 : 0),
					'create_time'=>$this->create_time
				);
			}
			$this->match_model->insert_batch($matched_data_arary);
			$this->set_last_match_time($matched_data_arary);

			$this->benchmark->mark('insert_end');
			$this->_log('将匹配结果插入数据库完成，花费时间：'.$this->benchmark->elapsed_time('insert_start', 'insert_end'), TRUE);

		}

	}

	//二次匹配
	public function second_match(){
		$this->benchmark->mark('insert_wake_start');
		$matched_data_arary = array();	//匹配成功列表
		//进行匹配
		foreach ($this->fail_user_list as $key=>$val){
			$flag_user_score = array();		//用户对其他用户的打分情况列表

			foreach ($this->before_user_list as $k=>$v){

				// 如果匹配过，则不再处理
				if (isset($this->matched_list[$key]) && in_array($k, $this->matched_list[$key])) {
					continue;
				}
				if (isset($this->matched_list[$k]) && in_array($key, $this->matched_list[$k])) {
					continue;
				}

				//双方打分
				$score_to_from = $this->_calculate_score($val, $v);
				$score_from_to = $this->_calculate_score($v, $val);
				//分太低 舍弃
				if ($score_from_to <= $this->_min_score_allowed || $score_to_from <= $this->_min_score_allowed){
					continue;
				}

				//取一个
				if (!empty($flag_user_score)){
					if ($score_to_from+$score_from_to > $flag_user_score[0]['score']){
						$flag_user_score[0] = array(
							'uid' => $k,
							'score' => $score_to_from+$score_from_to
						);
					}
				} else {
					$flag_user_score[] = array(
						'uid' => $k,
						'score' => $score_to_from+$score_from_to
					);
				}
			}
			if (empty($flag_user_score))
				continue;

			//去掉第二次匹配成功的唤醒用户
			unset($this->wake_user_list[$key]);

			foreach ($flag_user_score as $k=>$v){
				if ($val['gender'] == 1){
					$matched_data_arary[] = array('uid_1'=>$key, 'uid_2'=>$v['uid'], 'create_time'=>$this->create_time);
				} else {
					$matched_data_arary[] = array('uid_2'=>$key, 'uid_1'=>$v['uid'], 'create_time'=>$this->create_time);
				}

				$this->uid_push_array[$key] = TRUE;
				$this->uid_push_array[$v['uid']] = TRUE;

				//标记匹配次数 匹配到两次就移除
				if (isset($this->before_user_list[$v['uid']]['match_num'])){
					unset($this->before_user_list[$v['uid']]);
				} else {
					$this->before_user_list[$v['uid']]['match_num'] = 1;
				}
			}

		}

		if (!empty($matched_data_arary)){
			$this->set_last_match_time($matched_data_arary);
			$this->match_model->insert_batch($matched_data_arary);
		}
		$this->benchmark->mark('insert_wake_end');
		$this->_log('首次匹配失败人数:'.count($this->fail_user_list).',总共有'.count($matched_data_arary).'对匹配成功,将匹配失败用户二次匹配结果插入数据库完成，花费时间：'.$this->benchmark->elapsed_time('insert_wake_start', 'insert_wake_end'), TRUE);

	}

	/**
	 * 获取初始匹配用户
	 */
	public function get_user_list(){

		$this->benchmark->mark('get_start');
		$this->_log('开始查找所有符合要求的用户', TRUE);

		$this->user_model->set_table_name('p_userdetail');
		$this->user_model->db->reset_query();
		$this->user_model->db->where('complete_flag', 1);
		$this->user_model->db->where('match_flag', 1);
		$this->user_model->db->where('service_flag', 1);
		$this->user_model->db->where('forbidden_time <', $this->create_time);
		$this->user_model->db->where('province', $this->province);
		$this->user_model->db->where('city', $this->city);

		$last_time_enabled = ($this->current_day_timestamp - 86400 * 5) * 10000;			// 允许匹配的用户是近五天有登录的用户

		// 唤醒点1：连续15天没有登录，第16天唤醒
		$last_time_wake_1_end = ($this->current_day_timestamp - 86400 * 15) * 10000;
		$last_time_wake_1_start = ($last_time_wake_1_end + 86400) * 10000;

		// 唤醒点2：连续45天没有登录，第46天唤醒
		$last_time_wake_2_end = ($this->current_day_timestamp - 86400 * 45) * 10000;
		$last_time_wake_2_start = ($last_time_wake_1_end + 86400) * 10000;

		// 唤醒点3：连续105天没有登录，第106天唤醒
		$last_time_wake_3_end = ($this->current_day_timestamp - 86400 * 105) * 10000;
		$last_time_wake_3_start = ($last_time_wake_1_end + 86400) * 10000;

		$this->user_model->db->group_start()
			->where('last_time>=', $last_time_enabled)
			->or_group_start()
			->where('last_time>=', $last_time_wake_1_start)
			->where('last_time<=', $last_time_wake_1_end)
			->group_end()
			->or_group_start()
			->where('last_time>=', $last_time_wake_2_start)
			->where('last_time<=', $last_time_wake_2_end)
			->group_end()
			->or_group_start()
			->where('last_time>=', $last_time_wake_3_start)
			->where('last_time<=', $last_time_wake_3_end)
			->group_end()
			->group_end();

		$this->user_list = $this->user_model->select(NULL, FALSE, $this->user_fileds, '', 'uid ASC', '', 'uid');
		$this->user_list = $this->fill_user_info($this->user_list);

		$this->benchmark->mark('get_end');
		$this->_log('完成查找所有符合要求的用户，总共找到'.count($this->user_list).'个用户，花费时间：'.$this->benchmark->elapsed_time('get_start', 'get_end'), TRUE);

	}

	/**
	 * 获取所有符合二次匹配要求的用户
	 * 最后一次【匹配成功】的时间为6天前，11天前，21天前，31天前，41天前，51天前，61天前，71天前，81天前，91天前的用户
	 */
	public function get_before_user_list(){

		$this->benchmark->mark('get_start');
		$this->_log('开始查找所有符合二次匹配要求的用户', TRUE);

		$this->user_model->set_table_name('p_userdetail');
		$this->user_model->db->reset_query();
		$this->user_model->db->where('complete_flag', 1);
		$this->user_model->db->where('match_flag', 1);
		$this->user_model->db->where('service_flag', 1);
		$this->user_model->db->where('forbidden_time <', $this->create_time);
		$this->user_model->db->where('province', $this->province);
		$this->user_model->db->where('city', $this->city);

		//配置...
		$day_array = array(
			6, 11, 21, 31, 41, 51, 61, 71, 81, 91
		);

		foreach ($day_array as $key=>$val){
			if ($key == 0){
				$this->user_model->db->group_start()
					->where('last_match_time>=', $this->current_day_timestamp - 86400*$val)
					->where('last_match_time<=', $this->current_day_timestamp - 86400*($val-1));
			} else {
				$this->user_model->db->or_group_start()
					->where('last_match_time>=', $this->current_day_timestamp - 86400*$val)
					->where('last_match_time<=', $this->current_day_timestamp - 86400*($val-1));
				$this->user_model->db->group_end();
			}
		}
		$this->user_model->db->group_end();

		$this->before_user_list = $this->user_model->select(NULL, FALSE, $this->user_fileds, '', 'uid ASC', '', 'uid');
		$this->before_user_list = $this->fill_user_info($this->before_user_list);

		foreach ($this->before_user_list as $key=>$val){
			if (isset($this->user_list[$key]))
				unset($this->before_user_list[$key]);
		}

		$this->benchmark->mark('get_end');
		$this->_log('完成查找所有符合二次匹配要求的用户，总共找到'.count($this->before_user_list).'个用户，花费时间：'.$this->benchmark->elapsed_time('get_start', 'get_end'), TRUE);


	}

	//获取用户的打分列表
	public function get_score_list(){
		$this->benchmark->mark('get_start');

		// 开始计算得分
		$this->_log('开始计算得分', TRUE);
		$this->score_list = array();

		foreach ($this->user_list as $uid_from => $user_from) {		// 外层循环的uid，即打分者uid
			foreach ($this->user_list as $uid_to => $user_to) {		// 内层循环的uid，即被打分者uid
				// 只计算比自己的uid还要大的用户的
				if ($uid_to <= $uid_from) {
					continue;
				}

				// 如果匹配过，则不再处理
				if (isset($this->matched_list[$uid_from]) && in_array($uid_to, $this->matched_list[$uid_from])) {
					continue;
				}

				// from给to打分
				$score_from_to = $this->_calculate_score($user_from, $user_to);

				// to给from打分
				$score_to_from = $this->_calculate_score($user_to, $user_from);

				// 如果分数合格的，单方合格和双方合格都保存
				if ($score_to_from > $this->_min_score_allowed)
					$this->score_list[$uid_to][$uid_from] = $score_to_from;
				if ($score_from_to > $this->_min_score_allowed)
					$this->score_list[$uid_from][$uid_to] = $score_from_to;
			}
		}

		$this->benchmark->mark('get_end');
		$this->_log('计算得分完成,总共' . count($this->score_list) . '个用户，花费时间：'.$this->benchmark->elapsed_time('get_start', 'get_end'), TRUE);
	}

	//获取已匹配的记录
	public function get_matched_list($uid_array){

		$this->benchmark->mark('get_start');
		$this->_log('开始获取已匹配的记录', TRUE);

		$this->matched_list = array();
		$matched_list = $this->match_model->select(array('uid_1'=>$uid_array, 'uid_2'=>$uid_array), TRUE, 'uid_1,uid_2');
		foreach ($matched_list as $row) {
			// 为了节省内存，可以让value中的uid都大于key
			if ($row['uid_1'] > $row['uid_2']) {
				$this->matched_list[$row['uid_2']][] = $row['uid_1'];
			} else {
				$this->matched_list[$row['uid_1']][] = $row['uid_2'];
			}
		}

		$this->benchmark->mark('get_end');
		$this->_log('获取已匹配的记录完成，花费时间：'.$this->benchmark->elapsed_time('get_start', 'get_end'), TRUE);

	}

	//填充用户信息
	public function fill_user_info($user_list){
		// 处理用户信息
		foreach ($user_list as &$row) {
			// 年龄
			$row['age'] = get_age($row['birthday']);

			// 外貌评分
			if ($row['look_grade_times'] < 10) {
				$row['look_grade'] = 3;
			}
			else {
				$row['look_grade'] = round($row['look_grade_total']/$row['look_grade_times']);
			}

			//男生额外加分
			if ($row['gender'] == 1)
				$row['look_grade'] += 0.4;

			// 外貌权重和收入权重
			// 收入重要
			if ($row['salary_vs_look'] == 2) {
				$row['weight_look'] = 3;
				$row['weight_income'] = 7;
			}
			// 外貌重要
			else if ($row['salary_vs_look'] == 3) {
				$row['weight_look'] = 7;
				$row['weight_income'] = 3;
			}
			// 无所谓
			else {
				$row['weight_look'] = 5;
				$row['weight_income'] = 5;
			}

			// 认证学历
			if ($row['auth_education'] > 0) {
				$row['education'] = $row['auth_education'];
			}

			// 是否是VIP
			$row['is_vip'] = $this->user_model->is_vip($row);

		}

		return $user_list;
	}

	//获取上次匹配未匹配到的唤醒用户列表
	public function get_wake_user_list(){

		$this->benchmark->mark('get_start');
		$this->_log('开始获取上次匹配未匹配到的唤醒用户列表', TRUE);

		$wake_user_key = 'wake_user_list_p' . $this->province . '_c' . $this->city;		//唤醒用户缓存的key
		$wake_user_list = $this->log->get_log_string($wake_user_key);
		if (!$wake_user_list){
			$this->wake_user_list = array();
		} else {
			$this->wake_user_list = json_decode($wake_user_list, true);
		}

		//将上次匹配未匹配到的唤醒用户合并到本次匹配列表
		foreach ($this->wake_user_list as $key=>$val){
			if (!isset($this->user_list[$key]))
				$this->user_list[$key] = $this->wake_user_list[$key];
		}

		$this->benchmark->mark('get_end');
		$this->_log('获取上次匹配未匹配到的唤醒用户列表完成，总共' . count($this->wake_user_list) . '人，花费时间：'.$this->benchmark->elapsed_time('get_start', 'get_end'), TRUE);

	}
	//保存匹配失败的唤醒用户
	public function set_wake_user_list(){
		$wake_user_key = 'wake_user_list_p' . $this->province . '_c' . $this->city;		//唤醒用户缓存的key
		$wake_user_list = json_encode($this->wake_user_list);
		$this->log->write_log_string($wake_user_list, $wake_user_key);
	}

	/**
	 * 更新用户成功匹配时间
	 * @param array	$matched_data_arary	成功匹配的用户数据
	 */
	public function set_last_match_time($matched_data_arary){
		$uid_list = array();
		foreach ($matched_data_arary as $val){
			if (!in_array($val['uid_1'], $uid_list))
				$uid_list[] = $val['uid_1'];
			if (!in_array($val['uid_2'], $uid_list))
				$uid_list[] = $val['uid_2'];
		}
		$this->user_model->update(array('last_match_time' => $this->create_time), array('uid' => $uid_list), 'p_userdetail');
	}

	/**
	 * 给指定用户推荐两个用户
	 *
	 * @param array		$user			指定用户的详情，要求包含匹配相关的字段
	 * @return null
	 */
	public function start_match_single($user){
		// 如果配置了PCNTL，则异步进行
		if (config_item('pcntl')) {
			if (!function_exists('shutdown')) {
				function shutdown() {
					posix_kill(posix_getpid(), SIGHUP);
				}
			}

			// Do some initial processing
			// 关闭数据库，避免主线程结束之后数据库自动关闭，子线程数据库也被关闭
			$this->load->model('user_model');
			$this->user_model->db->close();

			// Switch over to daemon mode.

			// Parent
			if ($pid = pcntl_fork()) {
				pcntl_wait($status);
				// 重新连接数据库
				$this->load->database();
				$this->user_model->db->reconnect();
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

				// 重新连接数据库
				$this->load->database();
				$this->user_model->db->reconnect();

				// 开始匹配
				$this->_match_single($user);

				exit(0);
			}
		}
		// 否则同步进行
		else {
			$this->_match_single($user);
		}
	}

	/**
	 * 给指定用户推荐两个用户
	 *
	 * @param array		$user			指定用户的详情，要求包含匹配相关的字段
	 * @return null
	 */
	private function _match_single($user)
	{
		set_time_limit(3600);
		ignore_user_abort(TRUE);

		// 清空日志字符串
		$this->_log_string = '';
		$this->_log_tag = 'match_single';

		$this->benchmark->mark('function_start');
		$this->_log('开始为uid为'.$user['uid'].'的用户进行一次匹配', TRUE);

		$this->load->model('user_model');
		$this->load->model('area_model');
		$this->load->model('match_model');
		$this->load->helper('date');

		// 判断省份城市是否开通（get_area_state方法内会判断省份城市是否合法）
		$result = $this->area_model->get_area_state($user['province'], $user['city']);
		if ($result['open_state'] != 2) {
			$this->_log('省份城市未开通或者省份城市不合法', TRUE);
			return;
		}

		// 处理用户自己的信息
		// 年龄
		$user['age'] = get_age($user['birthday']);

		// 外貌权重和收入权重
		// 收入重要
		if ($user['salary_vs_look'] == 2) {
			$user['weight_look'] = 3;
			$user['weight_income'] = 7;
		}
		// 外貌重要
		else if ($user['salary_vs_look'] == 3) {
			$user['weight_look'] = 7;
			$user['weight_income'] = 3;
		}
		// 无所谓
		else {
			$user['weight_look'] = 5;
			$user['weight_income'] = 5;
		}

		// 首先找到所有符合该用户条件的用户
		$where = array('complete_flag'=>1);

		// 性别
		if ($user['gender'] == 1) {
			$where['gender'] = 2;
		}
		else if ($user['gender'] == 2) {
			$where['gender'] = 1;
		}
		else {
			$this->_log('性别不合法', TRUE);
			return;
		}

		// 地区
		$where['province'] = $user['province'];
		$where['city'] = $user['city'];

//		// 年龄
//		$year = date('Y');
//		$month_day = date('m-d');
//		$where['birthday >='] = ($year - $user['target_age_max']).'-'.$month_day;
//		$where['birthday <='] = ($year - $user['target_age_min']).'-'.$month_day;
//
//		// 身高
//		$where['height >='] = $user['target_height_min'];
//		$where['height <='] = $user['target_height_max'];


		// 找到所有符合要求的用户
		$this->_log('开始查找所有符合要求的用户', TRUE);
		$this->user_model->set_table_name('p_userdetail');
		$fileds = 'uid, gender, birthday, height, weight, salary, education, auth_education,
				look_grade_total, look_grade_times';
		$user_list = $this->user_model->select($where, FALSE, $fileds);

		if (empty($user_list)) {
			$this->_log('没有找到符合要求的用户，终止！', TRUE);
			return;
		}

		// 处理用户信息
		foreach ($user_list as $index => &$row) {
			// 只取平均分大于3分的用户
			if ($row['look_grade_times'] == 0) {
				unset($user_list[$index]);
				continue;
			}

			$row['look_grade'] = round($row['look_grade_total']/$row['look_grade_times']);
			if ($row['look_grade'] < 3) {
				unset($user_list[$index]);
				continue;
			}

			// 年龄
			$row['age'] = get_age($row['birthday']);

			// 认证学历
			if ($row['auth_education'] > 0) {
				$row['education'] = $row['auth_education'];
			}
		}
		unset($row);

		$this->benchmark->mark('get_users');

		$this->_log('查找所有符合要求的用户完成，总共找到'.count($user_list).'个用户，花费时间：'.$this->benchmark->elapsed_time('function_start', 'get_users'), TRUE);

		// 开始计算得分
		$this->_log('开始计算得分', TRUE);
		$score_list = array();						// 保存分数，key是被打分者uid

		foreach ($user_list as $user_to) {		// 外层循环的uid，即打分者uid
			// from给to打分
			$score_from_to = $this->_calculate_score($user, $user_to);

			// 如果分数都合格，则可以加入到数组中了
			$score_list[$user_to['uid']][] = $score_from_to;

		}

		$this->benchmark->mark('get_score');
		$this->_log('计算得分完成，花费时间：'.$this->benchmark->elapsed_time('get_users', 'get_score'), TRUE);

		// 找到得分最高的两个用户，作为匹配结果
		arsort($score_list);

		$count = 0;
		$matched_data_arary = array();
		foreach ($score_list as $to_uid=>$score) {
			if ($count >= 2) {
				break;
			}

			$matched_data_arary[] = array('uid_1'=> ($user['gender']==1 ? $user['uid'] : $to_uid),
				'uid_2'=>($user['gender']==1 ? $to_uid : $user['uid']),
				'hidden_1'=>($user['gender']==1 ? 0 : 1),
				'hidden_2'=>($user['gender']==1 ? 1 : 0),
				'create_time'=>time());

			$count++;
		}

		if (!empty($matched_data_arary)) {
			$this->_log('开始将匹配结果插入数据库', TRUE);
			$this->load->model('match_model');
			$this->match_model->insert_batch($matched_data_arary);
		}

		$this->benchmark->mark('function_end');
		$this->_log('本次匹配全部执行完成，总共花费时间：'.$this->benchmark->elapsed_time('function_start', 'function_end'), TRUE);
	}

	/**
	 * 计算被打分者对于打分者的得分
	 *
	 * @param array		$user_from		打分者
	 * @param array		$user_to		被打分者
	 * @return int 		得分
	 */
	private function _calculate_score($user_from, $user_to) {

		$score = 0;

		// 性别,同性直接否决
		if ($user_from['gender'] == $user_to['gender']) {
			return 0;
		}

		// 年龄，如果不在范围内
		if ($user_to['age']<$user_from['target_age_min'] || $user_to['age']>$user_from['target_age_max']) {
			if ($this->_strict_mode['age']) {
				return 0;
			}
		}
		else {
			// 计算两人的年龄差
			$delta_age = abs($user_to['age'] - $user_from['age']);

			// 以自己年龄为基线，对方年龄在自己年龄的左右3岁内，得满分。
			if ($delta_age <= 3) {
				$score += 20;
			}
			// 否则，每大2岁或小2岁，扣1分。
			else {
				$temp = 20 - ceil( ($delta_age - 3) / 2 );
				$temp = max(0, $temp);
				$score += $temp;
			}
		}

		// 身高，如果不在范围内
		if ($user_to['height']<$user_from['target_height_min'] || $user_to['height']>$user_from['target_height_max']) {
			if ($this->_strict_mode['height']) {
				return 0;
			}
		}
		else {
			// 计算期望身高和实际身高的差
			$delta_height = abs($user_to['height'] - (($user_from['target_height_min'] + $user_from['target_height_max']) / 2));

			// 以自己所设置的身高区间的中点为基线，对方身高每超出基线±2cm，减1分
			$temp = 10 - floor( $delta_height / 2 );
			$temp = max(0, $temp);
			$score += $temp * 2;		// 体重去掉后，身高的权重变为2倍
		}

		// 学历，如不在自己的期望区间内
		if ($user_to['education'] < $user_from['target_education']) {
			if ($this->_strict_mode['education']) {
				return 0;
			}
		}
		// 学历分为6档。第6档和第5档（博士后和博士）都得满分10分，之后依次递减2分，即硕士8分，本科6分，大专4分，高中2分，高中以下0分
		else {
			$temp = 2 * $user_to['education'];
			$temp = min(10, $temp);
			$score += $temp;
		}

		// 收入，如不在自己的期望区间内
		if ($user_to['salary'] < $user_from['target_salary']) {
			// 女生配男生时，男生的收入作为一票否决项
			if ($this->_strict_mode['salary'] || $user_from['gender']==2) {
				return 0;
			}
		}
		// 收入分为6档，第6档位最高档得满分，之后依次减5*系数。如，系数为0.3，对方收入50000（最高档）得15分，对方收入2000以下（最低档）得  (50 -（6-1）*5)*0.3=7.5
		else {
			$temp = 50 - (6 - $user_to['salary']) * 5;
			$temp = $temp * $user_from['weight_income'] / 10;
			$score += $temp;
		}

		// 外貌，如不在自己的期望区间内，该项得分为0
		if ($user_to['look_grade'] < $user_from['target_look']) {
			// 男生配女生时，女生的颜值作为一票否决项
			if ($this->_strict_mode['look'] || $user_from['gender']==1) {
				return 0;
			}
		}
		// 外貌分为5档，算法与收入相同。如系数0.7，第3档的外貌得分为，(50 -（5-3）*5) *0.7=24.5
		else {
			$temp = 50 - (5 - $user_to['look_grade']) * 5;
			$temp = $temp * $user_from['weight_look'] / 10;
			$score += $temp;
		}

		return round($score);
	}

	/**
	 * 判断两个用户之间是否可以邀请
	 * @param array			$from_user		发起邀请的用户，要求含有uid、gender、province、city、vip_time
	 * @param array			$to_user		被邀请的用户，要求含有uid、gender、province、city
	 * @return mixed
	 * $output['state']: TRUE(可以邀请)/FALSE(不可以邀请)
	 * $output['error']: 不可以邀请的原因
	 */
	public function can_invite($from_user, $to_user)
	{
		if (empty($from_user) || empty($to_user)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到该用户";
			return $output;
		}

		// 判断两人是否为异性
		$this->load->model('user_model');
		if ( !$this->user_model->is_opposite_sex($from_user['gender'], $to_user['gender']) ) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "双方性别不合法";
			return $output;
		}

		if ($from_user['gender']==1) {
			$uid_1 = $from_user['uid'];
			$uid_2 = $to_user['uid'];
		}
		else {
			$uid_1 = $to_user['uid'];
			$uid_2 = $from_user['uid'];
		}

		// 判断两人是否同城
		$this->load->model('area_model');
		if ( !$this->area_model->is_same_city($from_user['province'], $from_user['city'], $to_user['province'], $to_user['city']) ) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "双方当前不在同一城市";
			return $output;
		}


/*		// 被推荐的人随意邀请
		// 如果不是会员，则必须要匹配后才能邀请
		if ($to_user['recommend'] != 1 && !$this->user_model->is_vip($from_user)) {
			$this->load->model('match_model');
			$match = $this->match_model->get_row_by_uid($uid_1, $uid_2, '1');

			// 如果不存在匹配，则返回错误
			if (empty($match)) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "您没有权限";
				return $output;
			}
		}*/

		// 查看是否已经存在邀请了
		$this->load->model('invite_model');
		$invite = $this->invite_model->get_row_by_uid($uid_1, $uid_2, 'iid,state,expire_time');

		// 如果存在邀请
		if (!empty($invite)) {
			// 如果邀请未成功且已经过期，那么删除该邀请
			if ( $invite['state'] !=2 && $invite['state'] !=4 && $invite['expire_time']<time() ) {
				$this->invite_model->delete(array('iid'=>$invite['iid']));
			}
			// 否则，返回错误
			else {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请已经存在";
				return $output;
			}
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 * 判断一个用户是否可以接受指定邀请
	 * @param int			$uid		接受邀请的用户的uid
	 * @param int			$gender		接受邀请的用户的性别
	 * @param int			$iid		邀请id
	 * @return mixed
	 * $output['state']: TRUE(可以接受邀请)/FALSE(不可以接受邀请)
	 * $output['error']: 不可以接受邀请的原因
	 * $output['use_free_coupon']: TRUE(使用了合法的体验券)/FALSE(没有使用合法的体验券)
	 * $output['invite']: 如果可以接受邀请，则返回邀请信息
	 */
	public function can_response($uid, $gender, $iid)
	{
		$gender = (int)$gender;

		// 检测uid是否合法
		if (!is_id($uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "uid不合法";
			return $output;
		}

		// 检测性别是否合法
		if ($gender!=1 && $gender!=2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "性别不合法";
			return $output;
		}

		// 检测iid是否合法
		if (!is_id($iid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "iid不合法";
			return $output;
		}

		// 获取邀请信息
		$this->load->model('invite_model');
		$invite = $this->invite_model->get_row_by_id($iid);

		if (empty($invite)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到该邀请";
			return $output;
		}

		// 对于男性用户接受邀请
		if ($gender == 1) {
			// 检测邀请是否属于对应用户
			if ($invite['uid_1']!=$uid) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "您没有权限";
				return $output;
			}

			// 检测邀请状态
			if ($invite['state'] == 2 ||$invite['state'] == 4) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请已被接受";
				return $output;
			}

			// 检测邀请状态
			if ($invite['state'] != 3) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请状态不合法";
				return $output;
			}

			// 检测邀请过期时间
			if ($invite['expire_time'] < time()) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请已过期";
				return $output;
			}
		}
		// 如果是女性用户接受邀请
		else {
			// 检测邀请是否属于对应用户
			if ($invite['uid_2']!=$uid) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "您没有权限";
				return $output;
			}

			// 检测邀请状态
			if ($invite['state'] == 2 ||$invite['state'] == 4) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请已被接受";
				return $output;
			}

			// 检测邀请状态
			if ($invite['state'] != 1) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请状态不合法";
				return $output;
			}

			// 检测邀请是否有合法套餐和消费券
			if (!is_id($invite['menu_id'])) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "未选择套餐";
				return $output;
			}
			if (!is_id($invite['coupon_id'])) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "未购买消费券";
				return $output;
			}

			// 检测邀请过期时间
			if ($invite['expire_time'] < time()) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请已过期";
				return $output;
			}
		}


		$output['state'] = TRUE;
		$output['use_free_coupon'] = ($invite['menu_id'] == FREE_COUPON_MENU_ID && is_id($invite['coupon_id']));
		$output['invite'] = $invite;
		return $output;
	}

}