<?php
/**
 * 拓展的表单辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * 表单验证中，用于重新填充表单中的Select控件
 *
 * 指定一个字段名，如果已经启用了表单验证，则使用表单中该字段名对应的值
 * 否则如果post中有该字段名对应的值，则使用post中的值，否则使用候补值
 *
 * @param	string		$field			字段名
 * @param	string		$value			select的option的值
 * @param	string		$alternative	候补值，如果没有启用表单验证，且post中没有值，则使用该值
 * @param	bool		$default		默认是否选中
 * @return	string
 */
function set_select($field, $value = '', $alternative = NULL, $default = FALSE)
{
	$CI =& get_instance();

	// 如果设置了表单验证，则不需要考虑候补值，直接使用表单验证类的中的数据
	if (isset($CI->form_validation) && is_object($CI->form_validation) && $CI->form_validation->has_rule($field))
	{
		return $CI->form_validation->set_select($field, $value, $default);
	}
	// 如果没有设置表单验证，且post值为空
	else if (($input = $CI->input->post($field, FALSE)) === NULL)
	{
		// 如果没有候补值，则直接走默认值逻辑
		if ($alternative === NULL) {
			return ($default === TRUE) ? ' selected="selected"' : '';
		}
		// 如果有候补值，则使用候补值
		else {
			$input = (string) $alternative;
		}
	}

	$value = (string) $value;

	if (is_array($input))
	{
		// Note: in_array('', array(0)) returns TRUE, do not use it
		foreach ($input as &$v)
		{
			if ($value === $v)
			{
				return ' selected="selected"';
			}
		}

		return '';
	}

	return ($input === $value) ? ' selected="selected"' : '';
}


/**
 * 表单验证中，用于重新填充表单中的Checkbox控件
 *
 * 指定一个字段名，如果已经启用了表单验证，则使用表单中该字段名对应的值
 * 否则如果post中有该字段名对应的值，则使用post中的值，否则使用候补值
 *
 * @param	string		$field			字段名
 * @param	string		$value			checkbox的value值
 * @param	array		$alternative	候补值，如果没有启用表单验证，且post中没有值，则使用该值，注意该值需要为数组
 * @param	bool		$default		默认是否选中
 * @return	string
 */
function set_checkbox($field, $value = '', $alternative = NULL, $default = FALSE)
{
	$CI =& get_instance();

	// 如果设置了表单验证，则不需要考虑候补值，直接使用表单验证类的中的数据
	if (isset($CI->form_validation) && is_object($CI->form_validation) && $CI->form_validation->has_rule($field))
	{
		return $CI->form_validation->set_checkbox($field, $value, $default);
	}

	// Form inputs are always strings ...
	$value = (string) $value;
	if (is_array($alternative))
	{
		foreach ($alternative as &$v)
		{
			$v = (string)$v;
		}
		unset($v);
	}

	// 如果是post
	if ($CI->input->method() === 'post')
	{
		$input = $CI->input->post($field, FALSE);
	}
	// 如果不是post
	else {
		// 如果有候补值，则使用候补值
		if ($alternative !== NULL) {
			$input = $alternative;
		}
		// 如果没有候补值，则只能为NULL
		else {
			$input = NULL;
		}
	}

	if (is_array($input))
	{
		// Note: in_array('', array(0)) returns TRUE, do not use it
		foreach ($input as &$v)
		{
			if ($value === $v)
			{
				return ' checked="checked"';
			}
		}

		return '';
	}

	// Unchecked checkbox and radio inputs are not even submitted by browsers ...
	if ($CI->input->method() === 'post')
	{
		return ($input === $value) ? ' checked="checked"' : '';
	}

	return ($default === TRUE) ? ' checked="checked"' : '';
}


/**
 * 表单验证中，用于重新填充表单中的Radio控件
 *
 * 指定一个字段名，如果已经启用了表单验证，则使用表单中该字段名对应的值
 * 否则如果post中有该字段名对应的值，则使用post中的值，否则使用候补值
 *
 * @param	string		$field			字段名
 * @param	string		$value			radio的value
 * @param	string		$alternative	候补值，如果没有启用表单验证，且post中没有值，则使用该值
 * @param	bool		$default		默认是否选中
 * @return	string
 */
function set_radio($field, $value = '', $alternative = NULL, $default = FALSE)
{
	$CI =& get_instance();

	// 如果设置了表单验证，则不需要考虑候补值，直接使用表单验证类的中的数据
	if (isset($CI->form_validation) && is_object($CI->form_validation) && $CI->form_validation->has_rule($field))
	{
		return $CI->form_validation->set_radio($field, $value, $default);
	}

	// Form inputs are always strings ...
	$value = (string) $value;
	$alternative = (string) $alternative;

	// 如果是post
	if ($CI->input->method() === 'post')
	{
		$input = $CI->input->post($field, FALSE);
	}
	// 如果不是post
	else {
		// 如果有候补值，则使用候补值
		if ($alternative !== NULL) {
			$input = $alternative;
		}
		// 如果没有候补值，则只能为NULL
		else {
			$input = NULL;
		}
	}

	if (is_array($input))
	{
		// Note: in_array('', array(0)) returns TRUE, do not use it
		foreach ($input as &$v)
		{
			if ($value === $v)
			{
				return ' checked="checked"';
			}
		}

		return '';
	}

	// Unchecked checkbox and radio inputs are not even submitted by browsers ...
	if ($CI->input->method() === 'post')
	{
		return ($input === $value) ? ' checked="checked"' : '';
	}
	// 如果有候补值，则使用候补值
	else if ($alternative !== NULL) {
		return ($alternative === $value) ? ' checked="checked"' : '';
	}

	return ($default === TRUE) ? ' checked="checked"' : '';
}