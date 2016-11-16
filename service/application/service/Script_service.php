<?php
/**
 * 脚本相关
 * Created by PhpStorm.
 * User: CaiZhenYu
 * Date: 2016/5/10
 * Time: 11:55
 */

class Script_service extends MY_Service {

	public function __construct()
	{
		parent::__construct();
	}

	//发放奖励
	public function task(){
		$this->load->model('user_model');
		$this->load->model('task_model');
		$this->load->model('task_log_model');
		$this->load->service('user_service');

		//设置表
		$this->user_model->set_table_name('p_userdetail');

		//需要处理的任务
		$task_list_config = array(
			'task_user_complete',
			'task_user_album',
			'task_user_auth',
		);

		$time = time();	//相关时间用同一个点

		if ($this->input->get('task_all') == 1){
			//暂不使用全体遍历
			$this->log->write_log_json(array('message'=>'暂不使用全体遍历'), 'script_task');
			exit();
			$task_list = $this->task_model->select(array('keyword' => $task_list_config), FALSE, '*', '', '', '', 'keyword');
			//首次需要遍历所有用户
			$this->log->write_log_json(array('message'=>'-------------' . date('Y-m-d H:i:s') . '遍历全体用户，为已完成用户发放奖励-------------'), 'script_task');

			$user_list = $this->user_model->select();

			foreach ($user_list as $key=>$val){
				$this->user_service->_user = $val;

				//首次直接发 不考虑任务状态
				if ($this->user_service->get_album(3)){
					//添加记录
					$insert_data = array(
						't_id' => $task_list['task_user_album']['t_id'],
						'uid' => $val['uid'],
						'create_time' => $time,
						'update_time' => $time,
					);
					$this->task_log_model->insert($insert_data);
					//发放奖励
					$this->user_model->update_vip_date(2, array("uid"=>$val['uid']));
					$this->log->write_log_json(array('message'=>$val['uid'] . '完成task_user_album（上传3张个人照片到相册）任务'), 'script_task');
				}

				if ($this->user_service->get_info_complete() == 100){
					//添加记录
					$insert_data = array(
						't_id' => $task_list['task_user_complete']['t_id'],
						'uid' => $val['uid'],
						'create_time' => $time,
						'update_time' => $time,
					);
					$this->task_log_model->insert($insert_data);
					//发放奖励
					$this->user_model->update_vip_date(1, array("uid"=>$val['uid']));
					$this->log->write_log_json(array('message'=>$val['uid'] . '完成task_user_complete（资料完整度100%）任务'), 'script_task');
				}

				$auth_num = 0;
				if ($val['auth_identity'] == 1){
					$auth_num++;
				}
				if ($val['auth_education'] > 0){
					$auth_num++;
				}
				if ($val['auth_house'] > 0){
					$auth_num++;
				}
				if ($val['auth_car'] > 0){
					$auth_num++;
				}
				if ($auth_num >= 2){
					//添加记录
					$insert_data = array(
						't_id' => $task_list['task_user_complete']['t_id'],
						'uid' => $val['uid'],
						'create_time' => $time,
						'update_time' => $time,
					);
					$this->task_log_model->insert($insert_data);
					//发放奖励
					$this->user_model->update_vip_date(4, array("uid"=>$val['uid']));
					$this->log->write_log_json(array('message'=>$val['uid'] . '完成task_user_auth（完成任意2项认证）任务'), 'script_task');
				}
			}

			//首次需要遍历所有用户结束
			$this->log->write_log_json(array('message'=>'-------------' . date('Y-m-d H:i:s') . '遍历全体用户(' . count($user_list) . ')，为已完成用户发放奖励结束-------------'), 'script_task');

			//写入时间 $time;
			$this->log->write_log_string($time, 'script_time');
		} else {
			//获取之前的执行时间
			$start_time = $this->log->get_log_string('script_time');
			//获取有数据变更的用户
			$where = array(
				'p_userdetail.update_time >= ' => $start_time,
				'p_userdetail.update_time < ' => $time,
				'p_useredition.last_version >= ' => 105000
			);

			//记录开始
			$this->log->write_log_json(array('message'=>'-------------' . date('Y-m-d H:i:s') . '遍历变更用户，为已完成用户发放奖励-------------'), 'script_task');

			$task_list = $this->task_model->select(array('state' => 0, 'keyword' => $task_list_config), FALSE, '*', '', '', '', 't_id');
			//需要获取1.5以上的用户给予奖励
			$this->user_model->db->from('p_userdetail');
			$this->user_model->db->where($where);
			$this->user_model->db->join('p_useredition', 'p_useredition.uid = p_userdetail.uid');
			$user_list = $this->user_model->process_sql($this->db->get());

			foreach ($user_list as $val){
				$this->user_service->_user = $val;

				$task_list_flag = $task_list;

				//每个用户独立查询已完成的任务
				$user_task_list = $this->task_log_model->get_list($val['uid']);
				//去除已奖励任务
				foreach ($user_task_list as $v){
					unset($task_list_flag[$v['t_id']]);
				}

				foreach ($task_list_flag as $v){

					switch ($v['keyword']) {
						case 'task_user_auth':
							$auth_num = 0;
							if ($val['auth_identity'] == 1){
								$auth_num++;
							}
							if ($val['auth_education'] > 0){
								$auth_num++;
							}
							if ($val['auth_house'] > 0){
								$auth_num++;
							}
							if ($val['auth_car'] > 0){
								$auth_num++;
							}
							if ($auth_num >= 2){
								//添加记录
								$insert_data = array(
									't_id' => $v['t_id'],
									'uid' => $val['uid'],
									'create_time' => $time,
									'update_time' => $time,
								);
								$this->task_log_model->insert($insert_data);
								//发放奖励
								$this->user_model->update_vip_date(4, array("uid"=>$val['uid']));
								$this->log->write_log_json(array('message'=>$val['uid'] . '完成task_user_auth（完成任意2项认证）任务'), 'script_task');
							}
							break;
						case 'task_user_album':
							if ($this->user_service->get_album(3)){
								//添加记录
								$insert_data = array(
									't_id' => $v['t_id'],
									'uid' => $val['uid'],
									'create_time' => $time,
									'update_time' => $time,
								);
								$this->task_log_model->insert($insert_data);
								//发放奖励
								$this->user_model->update_vip_date(2, array("uid"=>$val['uid']));
								$this->log->write_log_json(array('message'=>$val['uid'] . '完成task_user_album（上传3张个人照片到相册）任务'), 'script_task');
							}
							break;
						case 'task_user_complete':
							if ($this->user_service->get_info_complete() == 100){
								//添加记录
								$insert_data = array(
									't_id' => $v['t_id'],
									'uid' => $val['uid'],
									'create_time' => $time,
									'update_time' => $time,
								);
								$this->task_log_model->insert($insert_data);
								//发放奖励
								$this->user_model->update_vip_date(1, array("uid"=>$val['uid']));
								$this->log->write_log_json(array('message'=>$val['uid'] . '完成task_user_complete（资料完整度100%）任务'), 'script_task');

								//添加统计数据
								$this->load->model('user_total_model');
								$user_total = array(
									'uid' => $val['uid'],
									'type' => 32,
									'content' => '',
									'create_time' => $time,
									'update_time' => $time,
								);
								$this->user_total_model->insert($user_total);

							}
							break;
						default:
							break;
					}

				}
			}

			//记录结束
			$this->log->write_log_json(array('message'=>'-------------' . date('Y-m-d H:i:s') . '遍历变更用户(' . count($user_list) . ')，为已完成用户发放奖励结束-------------'), 'script_task');

			//写入时间 $time;
			$this->log->write_log_string($time, 'script_time');
		}

	}

}