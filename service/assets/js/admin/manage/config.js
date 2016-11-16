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
			project_name: {
				validators: {
					notEmpty: {
						message: '项目名称不得为空'
					}
				}
			}
		}
	});
});