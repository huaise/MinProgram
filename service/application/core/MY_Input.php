<?php
/**
 * 拓展的核心输入类
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/6
 * Time: 20:56
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Input extends CI_Input {

	/**
	 * Fetch an item from the SESSION array
	 *
	 * @param	mixed	$index		Index for item to be fetched from $_SESSION
	 * @param	bool	$xss_clean	Whether to apply XSS filtering
	 * @return	mixed
	 */
	public function session($index, $xss_clean = NULL)
	{
		return $this->_fetch_from_array($_SESSION, $index, $xss_clean);
	}

}
