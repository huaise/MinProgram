/**
 * Created by LvPeng on 2016/1/16.
 */
	
$(document).ready(function() {
	$(".del").click(function(){
		var inform = $(this).data("inform");
		var uid = $(this).data("uid");
		if(confirm("数据删除后将无法恢复，确定要删除\""+inform+"\"这个管理员？")){
			$.ajax({
				url: delete_url,
				data: 'action=delete&uid=' + uid,
				type: 'POST',
				cache: false,
				dataType: 'html',
				success: function (data,st) {
					data= data.replace(/\s+/g, "");
					alert(data);
					window.location.href=window.location.href;
				}
			});
		}
		return false;
	});
	$("#search_form").submit(function(){
		var url = encodeURI(search_url + "?action=search&username=" + $("#username").val() + "&authority=" + $("#authority").val());
		window.location.href = url;
		return false;
	});
});