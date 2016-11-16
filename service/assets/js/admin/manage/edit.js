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
                    },
                    stringLength: {
                        min: 8,
                        message: '密码长度不得少于8位'
                    },
                    identical: {
                        field: 'password_confirm',
                        message: '两次输入密码不一致'
                    },
                    different: {
                        field: 'username',
                        message: '用户名与密码不得相同'
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
                    },
                    different: {
                        field: 'username',
                        message: '用户名与密码不得相同'
                    }
                }
            },
            authority: {
                validators: {
                    notEmpty: {
                        message: '请选择权限'
                    }
                }
            }
        }
    });
    if (edit_mode) {
        $('#form_admin').bootstrapValidator('validate');
    }
});