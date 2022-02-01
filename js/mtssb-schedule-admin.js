/**
 * MTS Simple Booking 管理画面スケジュール編集操作
 *
 * @Filename	mtssb-schedule-admin.js
 * @Date		2012-06-xx
 * @Author		S.Hayashi
 * @Version		1.0.0
 */
jQuery(document).ready(function($) {

	$(".schedule-box.column-title input").change(function() {
		var week = $(this).attr('class');

		if ($(this).get(0).checked) {
			$(".schedule-open ." + week).attr('checked', 'checked').parent().addClass('open');
			//$(this).parent().addClass('open');
		} else {
			$(".schedule-open ." + week).removeAttr('checked').parent().removeClass('open');
			//$(this).parent().removeClass('open');
		}
	});

	$(".schedule-open input").change(function() {
		if ($(this).get(0).checked) {
			$(this).parent().addClass('open');
		} else {
			$(this).parent().removeClass('open');
		}
	});

});
