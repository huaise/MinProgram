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
            phone: {
                message: '手机号不合法',
                validators: {
                    notEmpty: {
                        message: '手机号不得为空'
                    },
                    regexp: {
                        regexp: /^1\d{10}$/,
                        message: '手机号不合法'
                    }
                }
            },
            birthday: {
                validators: {
                    date: {
                        format: 'YYYY-MM-DD',
                        message: '生日不合法'
                    }
                }
            }
        }
    });
    if (edit_mode) {
        $('#form_admin').bootstrapValidator('validate');
    }
    $('#birthday').datepicker({
        format: "yyyy-mm-dd",
        todayBtn: true,
        clearBtn: true,
        language: "zh-CN"
    });
    $('#birthday').datepicker().on('changeDate', function(e) {
        $('#form_admin').data('bootstrapValidator').updateStatus('birthday', 'NOT_VALIDATED', null).validateField('birthday');
    });

    initComplexArea('province', 'city', 'district', csa1, csa2, csa3, province, city, district);
    initImageUploaderCropWithPreiview('avatar_uploader', 'avatar', 0, 0, 100, 100);
    initMultipleImageUploaderWithPreiview('album_uploader', 'album', 0, 0, 1, 8);
});