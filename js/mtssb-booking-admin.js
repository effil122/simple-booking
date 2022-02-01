/**
 * MTS Simple Booking 管理画面予約登録・編集操作
 *
 * @Filename	mtssb-booking-admin.js
 * @Date		2012-05-09
 * @Author		S.Hayashi
 * @Version		1.0.0
 */
var timetable_operation = function($) {

	// タイムテーブルAJAX取得
	$(document).ready(function() {
		var params = {
			action : 'mtssb_get_timetable',
			article_id : 0,
			nonce : $("#ajax-nonce").val(),
		};

		$("#booking-article").change(function() {
			$("#booking-time").css('display', 'none');
			$("#loader-img").css('display', 'inline');
			params.article_id = $("#booking-article option:selected").val();
			$.post(ajaxurl, params, function(data) {
				$("#loader-img").css('display', 'none');
 				$("#booking-time").css('display', 'inline').html(data);

			});
			return false;
		});
	});
};

var timeop = new timetable_operation(jQuery);
