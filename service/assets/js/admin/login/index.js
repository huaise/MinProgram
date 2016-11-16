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
			username: {
				message: '用户名不合法',
				validators: {
					notEmpty: {
						message: '用户名不得为空'
					}
				}
			},
			password: {
				validators: {
					notEmpty: {
						message: '密码不得为空'
					}
				}
			}
		}
	});
});