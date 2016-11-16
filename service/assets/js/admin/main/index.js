/**
 * Created by LvPeng on 2016/1/16.
 */
var is_all_open = false;

$(document).ready(function() {
	$("#click_all").click(function() {
		if (is_all_open) {
			$(".panel-collapse").collapse('hide');
			$(this).html('展开全部');
			is_all_open = false;
		}
		else {
			$(".panel-collapse").collapse('show');
			$(this).html('收起全部');
			is_all_open = true;
		}
	});
});