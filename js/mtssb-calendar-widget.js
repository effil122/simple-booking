/**
 * Widget 予約カレンダー
 *
 * @Filename	mtssb-calendar-widget.js
 * @Date		2012-11-27
 * @Author		S.Hayashi
 *
 * @License		GPL2 or MIT
 *
 */
var mtssb_calendar_widget = function($) {

	/**
	 * 月予約カレンダーの表示
	 *
	 */
	var monthly_calendar = function() {

		// 年月パラメータの確認
		var regym = /ym=(\d{4})-(\d{1,2})/g;
		var ym = regym.exec($(this).attr('href'));
		if (isNaN(ym[1]) || isNaN(ym[2])) {
			return false;
		}

		// パラメータの設定とAJAX準備
		var $winfo = $(this).closest('.mtssb-calendar-widget').next();
		var param = {
			action: 'mtssb_get_booking_calendar',
			nonce: $winfo.find(".mtssb-calendar-widget-nonce").text(),
			param: $winfo.find(".mtssb-calendar-widget-param").text(),
		};
		var posturl = $winfo.find(".mtssb-ajaxurl").text() + '?ym=' + ym[1] + '-' + ym[2];

		var $warea = $winfo.prev();
		$warea.children(".ajax-calendar-loading-img").css('display', 'block');

		$.post(posturl, param, function(data) {
			$warea.children(".monthly-calendar").replaceWith(data);
			$warea.find("a.calendar-daylink").click(day_schedule);
			$warea.find(".monthly-prev-next a").bind('click', monthly_calendar);
			$warea.children(".ajax-calendar-loading-img").css('display', 'none');
		});

		return false;
	}

	/**
	 * 予約日スケジュールの表示
	 *
	 */
	var day_schedule = function() {

		// 年月日Unix timeパラメータの確認
		var regymd = /ymd=(\d*)/g;
		var ymd = regymd.exec( $(this).attr('href') );
		if (isNaN(ymd[1])) {
			return false;
		}

		// パラメータの設定とAJAX準備
		var $winfo = $(this).closest('.mtssb-calendar-widget').next();
		var param = {
			action: 'mtssb_get_booking_calendar',
			nonce: $winfo.find(".mtssb-calendar-widget-nonce").text(),
			param: $winfo.find(".mtssb-calendar-widget-param").text(),
		};
		var posturl = $winfo.find(".mtssb-ajaxurl").text() + '?ymd=' + ymd[1];

		var $warea = $winfo.prev();
		$warea.children(".ajax-calendar-loading-img").css('display', 'block');

		$.post(posturl, param, function(data) {
			$warea.children(".monthly-calendar").replaceWith(data);
			$warea.children(".ajax-calendar-loading-img").css('display', 'none');
		});

		return false;
	}

	$(document).ready(function() {
		$(".mtssb-calendar-widget .monthly-prev-next a").click(monthly_calendar);
		$(".mtssb-calendar-widget a.calendar-daylink").click(day_schedule);
	});

}

var oMtssbCalendarWidget = new mtssb_calendar_widget(jQuery);
