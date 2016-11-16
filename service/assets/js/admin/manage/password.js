/**
 * Created by LvPeng on 2016/1/16.
 */

$(document).ready(function() {
	$('#form_admin').bootstrapValidator({
		message: '该项不合法',
		feedbackIcons: {
			valid: 'glyphicon glyphicon-ok',
			invalid: 'glyphicon glyphicon-remove',
			validating: 'glyphicon glyphicon-refresh'
		},
		fields: {
			password: {
				validators: {
					notEmpty: {
						message: '密码不得为空'
					},
					stringLength: {
						min: 8,
						message: '密码长度不得少于8位'
					},
					identical: {
						field: 'password_confirm',
						message: '两次输入密码不一致'
					}
				}
			},
			password_confirm: {
				validators: {
					notEmpty: {
						message: '再次输入密码不得为空'
					},
					stringLength: {
						min: 8,
						message: '密码长度不得少于8位'
					},
					identical: {
						field: 'password',
						message: '两次输入密码不一致'
					}
				}
			}
		}
	});
});