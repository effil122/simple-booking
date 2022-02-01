/**
 * MTS Simple Booking 予約品目のタイムスケジュール操作
 *
 * @Filename	mtssb-article-admin.js
 * @Date		2012-05-08
 * @Author		S.Hayashi
 * @Version		1.0.0
 */
var timetable_operation = function($) {

	var timetable = new Array();

	// タイムテーブル追加
	this.add = function(thea) {
		var minutes = Number($("#timetable-hour option:selected").text()) * 3600
					 + Number($("#timetable-minute option:selected").text()) * 60;

		timetable.push(minutes);
		timetable.sort(function(a, b) {
			return a - b;
		});

		// 時間割リストの再表示
		redisplay();
	};

	// タイムテーブル削除
	this.delete = function(thea) {
		var time = $(thea).prev().val();
		for (var i = 0; i < timetable.length; i++) {
			if (timetable[i] == time) {
				timetable.splice(i, 1);
				break;
			}
		}

		// 時間割リストの再表示
		redisplay();
	};


	$(document).ready(function() {

		// タイムテーブル初期化
		$("#article-list input").each(function(key) {
			timetable[key] = Number($(this).val());
		});

	});

	// 時間割再表示
	var redisplay = function() {
		var title = $("#delete-title").text();

		$("#article-list li").remove();
		for (i in timetable) {
			jifun = ('0' + Math.floor(timetable[i] / 3600)).substr(-2) + ':' + ('0' + Math.floor(timetable[i] % 3600 / 60)).substr(-2);

			newelm = '<li><input type="hidden" name="article[timetable][' + i + ']" value="' + timetable[i] + '" />'
			 + jifun + ' <a href="javascript:void(0)" onclick="timeop.delete(this)">' + title + '</a></li>';

			$("#article-list").append($(newelm));
		}
	}

};

var timeop = new timetable_operation(jQuery);
