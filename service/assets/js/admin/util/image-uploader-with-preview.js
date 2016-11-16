var jcrop_api = new Array();
// 初始化一个带有预览功能的图片上传控件
// 参数含义：
// container：容器元素的id，如avatar_uploader
// input：input元素的id，如avatar，表单中该项的name应于id相同
// width和height，限制预览区域的大小，如果不限制请设为0，会自适应调整（推荐不限制）
function initImageUploaderWithPreiview(container, input, width, height) {
    var div_id = 'iuwp_div_' + input;
    var preview_id = 'iuwp_pre_' + input;
    var clear_id = 'iuwp_clear_' + input;

    $('#'+container).append('<input type="file" id="'+input+'" name="'+input+'" size="20" /><div id="'+div_id+'"><img class="img-responsive" id="'+preview_id+'" style="diplay:none" /></div><input type="button" class="btn-danger" id="'+clear_id+'" value="清空图片">');

    $('#'+input).change(function(){
        setImagePreview(input, preview_id, div_id, width, height);
    });
    $('#'+clear_id).click(function(){
        clearAttachments(input, preview_id, div_id, width, height);
    });
}

// 初始化一个带有预览功能的图片上传剪切控件
// 参数含义：
// container：容器元素的id，如avatar_uploader
// input：input元素的id，如avatar，表单中该项的name应于id相同
// width和height，限制预览区域的大小，如果不限制请设为0，会自适应调整（推荐不限制）
function initImageUploaderCropWithPreiview(container, input, width, height, target_width, target_height) {
    var div_id = 'iuwp_div_' + input;
    var preview_id = 'iuwp_pre_' + input;
    var clear_id = 'iuwp_clear_' + input;
    var aspectRatio = target_width/target_height;

    $('#'+container).append('<input type="file" id="'+input+'" name="'+input+'" size="20" /><div id="'+div_id+'"><img class="img-responsive" id="'+preview_id+'" style="diplay:none" /></div><input type="button" class="btn-danger" id="'+clear_id+'" value="清空图片">');
    $('#'+container).append('<input type="hidden"  id="'+input+'_x" name="'+input+'_x" />');
    $('#'+container).append('<input type="hidden"  id="'+input+'_y" name="'+input+'_y" />');
    $('#'+container).append('<input type="hidden"  id="'+input+'_w" name="'+input+'_w" />');
    $('#'+container).append('<input type="hidden"  id="'+input+'_h" name="'+input+'_h" />');
    $('#'+container).append('<input type="hidden"  id="'+input+'_xbr" name="'+input+'_xbr" />');
    $('#'+container).append('<input type="hidden"  id="'+input+'_ybr" name="'+input+'_ybr" />');

    $('#'+input).change(function(){
        if (jcrop_api[input] != null) {
            jcrop_api[input].destroy();
        }
        setImagePreview(input, preview_id, div_id, width, height);
        $('#'+preview_id).Jcrop({
            bgOpacity:0.2,
            aspectRatio:aspectRatio,
            onSelect: function (c)
            {
                $('#'+input+'_x').val(c.x);
                $('#'+input+'_y').val(c.y);
                $('#'+input+'_w').val(c.w);
                $('#'+input+'_h').val(c.h);
            }
        },function(){
            jcrop_api[input] = this;
            var bounds = this.getBounds();
            $('#'+input+'_xbr').val(bounds[0]);
            $('#'+input+'_ybr').val(bounds[1]);

            var ori_area_height;
            var ori_area_width;
            if(bounds[0] / bounds[1] >= aspectRatio){
                ori_area_height = Math.max(20, bounds[1]-20);
                ori_area_width = ori_area_height * aspectRatio;
            }
            else{
                ori_area_width = Math.max(20, bounds[0]-20);
                ori_area_height = ori_area_width / aspectRatio;
            }
            this.setSelect([0, 0, ori_area_width, ori_area_height]);
        });
    });
    //初始化截取的函数

    $('#'+clear_id).click(function(){
        clearCropAttachments(input, preview_id, div_id, width, height, aspectRatio);
    });
}

// 初始化一个带有预览功能的多图上传控件
// 参数含义：
// container：容器元素的id，如avatar_uploader
// input：input元素的id，如avatar，表单中该项的name应于id相同
// width和height，限制预览区域的大小，如果不限制请设为0，会自适应调整（推荐不限制）
// min_num和max_num，图片数量的最小值和最大值
function initMultipleImageUploaderWithPreiview(container, input, width, height, min_num, max_num) {
    min_num = (min_num<1 ? 1 : min_num);
    max_num = (max_num<min_num ? min_num : max_num);

    var control_div_id = 'iuwp_control_' + input;
    var add_button_id = 'iuwp_add_' + input;
    var delete_button_id = 'iuwp_delete_' + input;

    $('#'+container).append('<div id="'+control_div_id+'" data-num="0" style="margin: 10px"><button id="'+add_button_id+'" class="btn btn-primary btn-sm">添加一张图片</button><button id="'+delete_button_id+'" style="display:none; margin-left:10px" class="btn btn-danger btn-sm">删除一张图片</button><div>');

    $('#'+add_button_id).click(function(){
        addMultipleImageUploader(input, width, height, min_num, max_num);
    });
    $('#'+delete_button_id).click(function(){
        deleteMultipleImageUploader(input, min_num, max_num);
    });

    for (var i=0; i<min_num; i++) {
        addMultipleImageUploader(input, width, height, min_num, max_num);
    }
}

function addMultipleImageUploader(input, width, height, min_num, max_num) {
    var control_div_id = 'iuwp_control_' + input;
    var current = $('#'+control_div_id).data('num');
    var add_button_id = 'iuwp_add_' + input;
    var delete_button_id = 'iuwp_delete_' + input;

    if (current >= max_num) {
        $('#'+add_button_id).hide();
        return;
    }

    var input_id = input + '_' + current;
    var div_id = 'iuwp_div_' + input_id;
    var preview_id = 'iuwp_pre_' + input_id;
    var clear_id = 'iuwp_clear_' + input_id;

    $('#'+control_div_id).before('<input type="file" id="'+input_id+'" name="'+input+'[]" size="20" /><div id="'+div_id+'"><img class="img-responsive" id="'+preview_id+'" style="diplay:none" /></div><input type="button" class="btn-danger" style="margin-bottom: 10px" id="'+clear_id+'" value="清空图片">');

    $('#'+input_id).change(function(){
        setImagePreview(input_id, preview_id, div_id, width, height);
    });
    $('#'+clear_id).click(function(){
        clearAttachments(input_id, preview_id, div_id, width, height);
    });

    current++;
    $('#'+control_div_id).data('num', current);
    if (current >= max_num) {
        $('#'+add_button_id).hide();
    }
    if (current > min_num) {
        $('#'+delete_button_id).show();
    }
}

function deleteMultipleImageUploader(input, min_num, max_num) {
    var control_div_id = 'iuwp_control_' + input;
    var current = $('#'+control_div_id).data('num');
    var add_button_id = 'iuwp_add_' + input;
    var delete_button_id = 'iuwp_delete_' + input;

    if (current <= min_num) {
        $('#'+delete_button_id).hide();
        return;
    }

    current--;

    var input_id = input + '_' + current;
    var div_id = 'iuwp_div_' + input_id;
    var preview_id = 'iuwp_pre_' + input_id;
    var clear_id = 'iuwp_clear_' + input_id;
    $('#'+input_id).remove();
    $('#'+div_id).remove();
    $('#'+preview_id).remove();
    $('#'+clear_id).remove();

    $('#'+control_div_id).data('num', current);
    if (current < max_num) {
        $('#'+add_button_id).show();
    }
    if (current <= min_num) {
        $('#'+delete_button_id).hide();
    }
}

function setImagePreview(input, preview, div, width, height) {
    $("#"+preview).removeAttr("style"); // 清除样式，避免两次上传不同大小图片显示问题
    var docObj=document.getElementById(input);
    var imgObjPreview=document.getElementById(preview);
    if(docObj.files && docObj.files[0]){
        //火狐下，直接设img属性
        imgObjPreview.style.display = 'block';
        if (width > 0) {
            imgObjPreview.style.width = width+'px';
        }
        if (height > 0) {
            imgObjPreview.style.height = height+'px';
        }

        //火狐7以上版本不能用上面的getAsDataURL()方式获取，需要一下方式
        imgObjPreview.src = window.URL.createObjectURL(docObj.files[0]);
    }else{
        //IE下，使用滤镜
        docObj.select();
        var imgSrc = document.selection.createRange().text;
        var localImagId = document.getElementById(div);
        //必须设置初始大小
        if (width > 0) {
            localImagId.style.width = width+'px';
        }
        if (height > 0) {
            localImagId.style.height = height+'px';
        }

        //图片异常的捕捉，防止用户修改后缀来伪造图片
        try{
            localImagId.style.filter="progid:DXImageTransform.Microsoft.AlphaImageLoader(sizingMethod=scale)";
            localImagId.filters.item("DXImageTransform.Microsoft.AlphaImageLoader").src = imgSrc;
        }catch(e){
            alert("您上传的图片格式不正确，请重新选择!");
            return false;
        }
        imgObjPreview.style.display = 'none';
        document.selection.empty();
    }
    return true;
}
//没有裁剪功能的图片上传清理函数
function clearAttachments(input, preview, div, width, height){
    var name = $('#'+input).attr("name");
    $('#'+input).replaceWith('<input name="'+name+'" type="file" id="'+input+'"  />');
    $('#'+input).change(function(){
        setImagePreview(input, preview, div, width, height);
    });
    var imgObjPreview=document.getElementById(preview);
    imgObjPreview.style.display = 'none';
    return true;
}
//裁剪功能的图片上传清理函数
function clearCropAttachments(input, preview, div, width, height, aspectRatio){
    var name = $('#'+input).attr("name");
    var preview_id = 'iuwp_pre_' + input;
    //判断当前容器里面是否有图片,防止无图片点击清空图片产生bug
    if($('#'+preview_id).attr("src")==null || $('#'+preview_id).attr("src")=='undefined' ){
        return true;
    }

    jcrop_api[input].destroy();

    $('#'+input).replaceWith('<input name="'+name+'" type="file" id="'+input+'"  />');
    $('#'+input).change(function(){
        setImagePreview(input, preview, div, width, height);
        $('#'+preview_id).Jcrop({
            bgOpacity:0.2,
            aspectRatio:aspectRatio,
            onSelect: function (c)
            {
                $('#'+input+'_x').val(c.x);
                $('#'+input+'_y').val(c.y);
                $('#'+input+'_w').val(c.w);
                $('#'+input+'_h').val(c.h);
            }
        },function(){
            jcrop_api[input] = this;
            var bounds = this.getBounds();
            $('#'+input+'_xbr').val(bounds[0]);
            $('#'+input+'_ybr').val(bounds[1]);

            var ori_area_height;
            var ori_area_width;
            if(bounds[0] / bounds[1] >= aspectRatio){
                ori_area_height = Math.max(20, bounds[1]-20);
                ori_area_width = ori_area_height * aspectRatio;
            }
            else{
                ori_area_width = Math.max(20, bounds[0]-20);
                ori_area_height = ori_area_width / aspectRatio;
            }
            this.setSelect([0, 0, ori_area_width, ori_area_height]);
        });
    });

    var imgObjPreview=document.getElementById(preview);
    imgObjPreview.style.display = 'none';
    return true;
}