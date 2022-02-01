<?php
if (!class_exists('MTSSB_Booking')) {
	require_once(dirname(__FILE__) . '/mtssb-booking.php');
}
/**
 * MTS Simple Booking フロント処理モジュール
 *
 * @Filename	mtssb-front.php
 * @Date		2012-05-08
 * @Author		S.Hayashi
 *
 * Updated to 1.2.0 on 2012-12-22
 * Updated to 1.1.5 on 2012-12-02
 * Updated to 1.1.0 on 2012-11-02
 */
class MTSSB_Front extends MTSSB_Booking {
	const VERSION = '1.2.0';

	// 予約条件設定
	private $controls = array();

	// 予約カレンダー表示　日付データ
	private $this_year;				// 本日年
	private $this_month;			// 本日月
	private $this_day;				// 本日日
	private $this_time;				// 本日年月unix time
	private $today_time;			// 本日年月日 unix time
	private $start_time;			// 予約有効開始年月日 unix time(以上)
	private $start_day_time;		// 予約有効開始年月日 unit time
	private $max_time;				// 予約可能年月日unix time(未満)


	// 予約カレンダー表示　データベース
	private $articles = array();
	private $schedule = array();
	private $reserved = array();

	// 表示ページのURL
	private $this_page = '';

	private $action = '';
	private $message = '';
	private $errflg = false;


	// 表示データ
	private $weeks = array('Sun','Mon','Tue','Wed','Thu','Fri','Sat');

	/**
	 * Constructor
	 *
	 */
	public function __construct() {

		parent::__construct();

		// Controlsのロード
		$this->controls = get_option($this->domain . '_controls');

		// 表示ページのURL
		$this->this_page = get_permalink();
	}

	/**
	 * 月間予約カレンダー出力
	 *
	 */
	public function monthly_calendar($atts) {

		// 予約受付終了状態
		if (empty($this->controls['available'])) {
			return $this->controls['closed_page'];
		}

		// 日時の初期設定
		$this->_ready_time();

		// ショートコードパラメータの初期化
		$params = shortcode_atts(array(
			'id' => '-1',
			'type' => 'table',		// or div
			'class' => 'monthly-calendar',
			'year' => $this->this_year,
			'month' => $this->this_month,
			'pagination' => '1',
			'caption' => '1',
			'link' => '1',
			'weeks' => '',
			'skiptime' => '0',		// 当該日の時間割表示をスキップする
			'href' => '',
			'calendar_id' => '',
		), $atts);

		// 予約品目の取得
		$this->articles = MTSSB_Article::get_all_articles($params['id']);
		if (empty($this->articles)) {
			return __('Not found any articles of reservation.', $this->domain);
		}

		// 日付が指定されたら当該日の予約表示をする
		if (isset($_GET['ymd']) && (!isset($_GET['cid']) || $_GET['cid'] == $params['calendar_id'])) {
			$daytime = intval($_GET['ymd']);
			$daytime = $daytime - $daytime % 86400;
			if ($this->today_time <= $daytime && $daytime < $this->max_time) {
				return $this->_daily_schedule($daytime, $params);
			}
		}

		// 対象年月
		$theyear = intval($params['year']);
		$themonth = intval($params['month']);

		// ページ切り替え
		if (isset($_GET['ym']) && (!isset($_GET['cid']) || $_GET['cid'] == $params['calendar_id'])) {
			$ym = explode('-', $_GET['ym']);
			if (count($ym) == 2) {
				$theyear = $ym[0] > $ym[1] ? intval($ym[0]) : intval($ym[1]);
				$themonth = $ym[0] > $ym[1] ? intval($ym[1]) : intval($ym[0]);
			}
		}

		// 対象年月チェック
		$thetime = mktime(0, 0, 0, $themonth, 1, $theyear);
		if ($thetime < $this->this_time ||  $this->max_time <= $thetime) {
			$thetime = $this->this_time;
			$theyear = $this->this_year;
			$themonth = $this->this_month;
		}

		// 対象年月のスケジュールを読込む
		$key_name = MTS_Simple_Booking::SCHEDULE_NAME . date_i18n('Ym', $thetime);
		foreach ($this->articles as $article_id => $article) {
			$this->schedule[$article_id] = get_post_meta($article_id, $key_name, true);
		}

		// 対象年月の予約カウントデータを読込む
		$this->reserved = $this->get_reserved_count($theyear, $themonth);

		// 曜日データのローカライズ
		foreach ($this->weeks as $week) {
			$weeknames[] = __($week);
		}
		if ($params['weeks']) {
			$weeks = explode(',', $params['weeks']);
			if (count($weeks) == 7) {
				$weeknames = $weeks;
			}
		}

		$days = (mktime(0, 0, 0, $themonth + 1, 1, $theyear) - $thetime) / 86400;

		$starti = date('w', $thetime);
		$endi = $starti + $days + 5 - date('w', mktime(0, 0, 0, $themonth, $days, $theyear));

		ob_start();
?>

	<div class="<?php echo $params['class'] ?>">
	<table>
		<?php if ($params['caption'] == 1) { $this->_caption($theyear, $themonth, $thetime); } ?>
		<tr>
			<?php for ($i = 0; $i < 7; $i++) {
				$week = strtolower($this->weeks[$i]);
				echo "<th class=\"week-title $week\">" . $weeknames[$i] . "</th>";
			} ?>
		</tr>

		<?php
			for ($i = 0, $day = 1 - $starti; $i <= $endi ; $i++, $day++) {
				// 行終了
				if ($i % 7 == 0) {
					echo (0 < $i ? "</tr>\n" : '') . "<tr>\n";
				}

				if (0 < $day && $day <= $days) {
					$ymdtime = mktime(0, 0, 0, $themonth, $day, $theyear);
					$this->_reservation_of_the_day($ymdtime, $params);
				} else {
					echo '<td class="day-box no-day">&nbsp;</td>' . "\n";
				}
			}
		?>
	</table>
	<?php $this->_prev_next_link($theyear, $themonth, $params) ?>

	</div><!-- reservation-table -->
	<?php echo apply_filters('mtssb_monthly_message_after', '', $params['type']) ?>

<?php
		return ob_get_clean();
	}

	/**
	 * 指定日の予約情報を出力
	 *
	 * @thetime		ymd unixtime
	 */
	private function _reservation_of_the_day($thetime, $params) {
		global $mts_simple_booking;

		$link = $params['link'];
		$skip = $params['skiptime'];
		$cid = $params['calendar_id'];

		$idxday = date('d', $thetime);
		$week = strtolower($this->weeks[date_i18n('w', $thetime)]);

		// 予約率を求めるパラメータの計算と予約スケジュールチェック
		$schedule = false;
		$class = '';
		$capacity = $quantity = $rsvd_number = $rsvd_count = 0;
		foreach ($this->articles as $article_id => $article) {
			if (!empty($this->schedule[$article_id])) {
				if ($this->schedule[$article_id][$idxday]['open']) {
					$capacity += ($article['capacity'] + intval($this->schedule[$article_id][$idxday]['delta'])) * count($article['timetable']);
					$quantity += ($article['quantity'] + intval($this->schedule[$article_id][$idxday]['delta'])) * count($article['timetable']);
					if (isset($this->reserved[$thetime][$article_id])) {
						// 残り席数が予約最少人数を下回った場合は満席とする
						//if (($article['capacity'] - $article['minimum'] * 0.5) * count($article['timetable']) < $this->reserved[$thetime][$article_id]['number']) {
						//	$rsvd_numver += $article['capacity'] * count($article['timetable']);
						//} else {
						//	$rsvd_number += $this->reserved[$thetime][$article_id]['number'];
						//}
						$rsvd_number += $this->reserved[$thetime][$article_id]['number'];
						$rsvd_count += $this->reserved[$thetime][$article_id]['count'];
					}
					$schedule = true;
				}
	
				// スケジュールで指定したクラス
				$class= $this->schedule[$article_id][$idxday]['class'];
			}

			// 予約制限をセット
			$restriction = $article['restriction'];
		}

		// 空予約率
		if ($schedule) {
			if ($restriction == 'capacity') {
				$capacity_rate = ($capacity - $rsvd_number) * 100 / ($capacity ? $capacity : 1);
				//echo sprintf("%d/%d人 残%d％\n", $rsvd_number, $capacity, intval($capacity_rate));
			} else {
				$quantity_rate = ($quantity - $rsvd_count) * 100 / ($quantity ? $quantity : 1);
				//echo sprintf("%d/%d件 残%d％\n", $rsvd_count, $quantity, intval($quantity_rate));
			}
		}

		// 表示マーク
		if ((empty($this->controls['output_margin']) && $thetime < $this->start_day_time) || $thetime < $this->today_time || $this->max_time <= $thetime || !$schedule) {
			$mark = 'disable';
		} else {
			if ($restriction == 'capacity') {
				if ($this->controls['vacant_rate'] < $capacity_rate) {
					$mark = 'vacant';
				} else if ($capacity_rate <= 0) {
					$mark = 'full';
				} else {
					$mark = 'low';
				}
				// 席数(人数)残数
				$disp_number = $capacity - $rsvd_number;
			} else {
				if ($this->controls['vacant_rate'] < $quantity_rate) {
					$mark = 'vacant';
				} else if ($quantity_rate <= 0) {
					$mark = 'full';
				} else {
					$mark = 'low';
				}
				// 予約件数残数
				$disp_number = $quantity - $rsvd_count;
			}
		}

		// 予約カレンダーからそのまま予約フォームへリンクする指定がある場合
		$linkurl = '';
		if ($link) {
			if ($skip && count($this->articles) == 1) {
				if ($this->start_day_time <= $thetime) {
					$form_link = $mts_simple_booking->get_permalink_by_slug(MTS_Simple_Booking::PAGE_BOOKING_FORM);
					$article = reset($this->articles);
					$linkurl = esc_url(add_query_arg(array('aid' => $article['article_id'],
					 'utm' => $thetime + $article['timetable'][0]), $form_link));
				}
			} else {
				$arg = array('ymd' => $thetime) + (empty($cid) ? array() : array('cid' => $cid));
				$linkurl = esc_url(add_query_arg($arg, $this->this_page));
			}
		}

		// TD Box
		echo "<td class=\"day-box $week $mark"
		 . ($thetime == $this->today_time ? ' today' : '')
		 . (empty($class) ? '' : " $class") . '">';
		// 日付
		echo "<div class=\"day-number\">" . esc_html(apply_filters('mtssb_day', intval($idxday))) . '</div>';

		// マーク・リンク表示(記号または残数)
		echo '<div class="calendar-mark">';

		if ($mark == 'vacant' || $mark == 'low') {
			echo $linkurl ? ('<a class="calendar-daylink" href="' . $linkurl . '">') : '';
			echo (empty($this->controls[$mark . '_mark']) ? $disp_number : $this->controls[$mark . '_mark']);
			echo $linkurl ? '</a>' : '';
		} else if ($mark == 'full') {
			echo $this->controls['full_mark'];
		} else {
			echo $this->controls['disable'];
		}

		echo "</div></td>\n";

	}

	/**
	 * 予約カレンダーのキャプション表示
	 *
	 */
	private function _caption($year, $month, $thetime) {

		$title = esc_html(apply_filters('mtssb_caption', date(__('F, Y'), $thetime), $thetime));

?>
		<caption><?php echo $title ?></caption>
<?php
	}

	/**
	 * 予約カレンダーのページリンク表示
	 *
	 */
	private function _prev_next_link($year, $month, $params) {

		if ($params['pagination'] != 1) {
			return;
		}

		// リンク
		$prevtime = mktime(0, 0, 0, $month - 1, 1, $year);
		$prev_title = esc_html(apply_filters('mtssb_prev_title', date(__('F, Y'), $prevtime), $prevtime));
		$prev_arg = array('ym' => date('Y-n', $prevtime)) + (empty($params['calendar_id']) ? array() : array('cid' => $params['calendar_id']));
		$nexttime = mktime(0, 0, 0, $month + 1, 1, $year);
		$next_title = esc_html(apply_filters('mtssb_next_title', date(__('F, Y'), $nexttime), $nexttime));
		$next_arg = array('ym' => date('Y-n', $nexttime)) + (empty($params['calendar_id']) ? array() : array('cid' => $params['calendar_id']));
?>
	<div class="monthly-prev-next">
		<div class="monthly-prev"><?php if ($this->this_time <= $prevtime) {
			echo '<a href="' . esc_url(add_query_arg($prev_arg, $this->this_page)) . '">' . $prev_title . '</a>';
		} else {
			echo "<span class=\"no-link\">$prev_title</span>";
		} ?></div>
		<div class="monthly-next"><?php if ($nexttime < $this->max_time) {
			echo '<a href="' . esc_url(add_query_arg($next_arg, $this->this_page)) . '">' . $next_title . '</a>';
		} else {
			echo "<span class=\"no-link\">$next_title</span>";
		} ?></div>
		<br style="clear:both" />
	</div>

<?php
	}

	/**
	 * 本日日付パラメータ計算
	 *
	 */
	private function _ready_time() {
		// 本日の情報
		$this->this_year = date_i18n('Y');
		$this->this_month = date_i18n('n');
		$this->this_day = date_i18n('j');
		$this->this_time = mktime(0, 0, 0, $this->this_month, 1, $this->this_year);
		$this->today_time = mktime(0, 0, 0, $this->this_month, $this->this_day, $this->this_year);
		$this->start_time = current_time('timestamp') + intval($this->controls['start_accepting']) * 60;
		$this->start_day_time = $this->today_time + intval($this->controls['start_accepting'] / 1440) * 86400;
		$this->max_time = mktime(0, 0, 0, $this->this_month + $this->controls['period'], $this->this_day, $this->this_year);
	}

	/**
	 * 予約指定日スケジュール表示
	 *
	 * $daytime		unix time
	 * $params		ショートコードパラメータ
	 */
	private function _daily_schedule($daytime, $params) {

		// スケジュールキー名
		$key_name = MTS_Simple_Booking::SCHEDULE_NAME . date_i18n('Ym', $daytime);

		// 対象日付の予約カウントデータを読込む
		$this->reserved = $this->get_reserved_day_count($daytime);

		ob_start();
?>

	<?php foreach ($this->articles as $article_id => &$article) :
		$this->schedule[$article_id] = get_post_meta($article_id, $key_name, true);
		// 予約クローズなら何も表示しない
		if (!$this->schedule[$article_id][date('d', $daytime)]['open']) continue;
	?><div class="day-calendar">

	<?php echo apply_filters('mtssb_day_title', "<h3>{$article['name']}</h3>", $article['name']) ?>
	<?php echo apply_filters('mtssb_day_caption', '<p>' . date(__('F j, Y', $this->domain), $daytime) . ' (' . __(date('D', $daytime)) . ')' . '</p>', $daytime) ?>
	<table>
		<tr>
			<th class="day-left"><?php echo apply_filters('mtssb_daily_time_title', __('Time', $this->domain)) ?></th>
			<th class="day-right"><?php echo apply_filters('mtssb_daily_booking_title', __('Booking', $this->domain)) ?></th>
		</tr>

		<?php foreach ($article['timetable'] as $time) : ?>
		<tr>
			<th class="day-left"><?php echo apply_filters('mtssb_time_header', date('H:i', $time), $time) ?>〜<?php echo apply_filters('mtssb_time_header', date('H:i', $time+7200), $time) ?></th>
			<td class="day-right"><?php $this->_reservation_of_the_article($article_id, $article, $daytime + $time, $this->schedule[$article_id][date_i18n('d', $daytime)]) ?></td>
		</tr><?php endforeach; ?>

	</table>

	</div><?php endforeach; ?>
	<?php echo apply_filters('mtssb_daily_message_after', '', $params['type']) ?>

<?php
		return ob_get_clean();
	}

	/**
	 * 予約の詳細表示
	 *
	 */
	protected function _reservation_of_the_article($article_id, &$article, $thetime, $schedule) {
		global $mts_simple_booking;

		// 予約ページのURL
		$form_link = $mts_simple_booking->get_permalink_by_slug(MTS_Simple_Booking::PAGE_BOOKING_FORM);

		$rsvd_number = $rsvd_count = 0;

		// 予約受付開始時刻の確認
		$mark = 'disable';
		if ($this->today_time != $this->start_day_time || $this->start_time < $thetime) {
			if (isset($this->reserved[$thetime][$article_id])) {
				$rsvd_number = $this->reserved[$thetime][$article_id]['number'];
				$rsvd_count = $this->reserved[$thetime][$article_id]['count'];
			}
	
			if ($article['restriction'] == 'capacity') {
				$capacity = $article['capacity'] + intval($schedule['delta']);
				$capacity_rate = ($capacity - $rsvd_number) * 100 / ($capacity ? $capacity : 1);
				//echo sprintf("%d/%d人 残:%d％\n", $rsvd_number, $capacity, $capacity_rate);
			} else {
				$quantity = $article['quantity'] + intval($schedule['delta']);
				$quantity_rate = ($quantity - $rsvd_count) * 100 / ($quantity ? $quantity : 1);
				//echo sprintf("%d/%d件 残:%d％\n", $rsvd_count, $article['quantity'] + intval($schedule['delta']), $quantity_rate);
			}
	
			if ($article['restriction'] == 'capacity') {
				if ($this->controls['vacant_rate'] < $capacity_rate) {
					$mark = 'vacant';
				} else if ($capacity_rate <= 0) {
					$mark = 'full';
				} else {
					$mark = 'low';
				}
				// 席数(人数)残数
				$disp_number = $capacity - $rsvd_number;
			} else {
				if ($this->controls['vacant_rate'] < $quantity_rate) {
					$mark = 'vacant';
				} else if ($quantity_rate <= 0) {
					$mark = 'full';
				} else {
					$mark = 'low';
				}
				// 予約件数残数
				$disp_number = $quantity - $rsvd_count;
			}
		}

		// リンク表示(記号または残数)
		echo "<div class=\"calendar-mark $mark" . (empty($class) ? '' : " $class") . '">';

		if ($mark == 'vacant' || $mark == 'low') {
			if (((1440 <= $this->controls['start_accepting'] && $this->start_day_time <= $thetime) || $this->start_time < $thetime) && $this->controls['available']) {
				echo '<a class="booking-timelink" href="' . esc_url(add_query_arg(array('aid' => $article_id, 'utm' => $thetime), $form_link)) . '">';
				echo $this->_daily_mark($disp_number, $mark) . '</a>';
			} else {
				echo $this->_daily_mark($disp_number, $mark);
			}
		} else if ($mark == 'full') {
			echo $this->controls['full_mark'];
		} else {
			echo $this->controls['disable'];
		}

		echo '</div>';
	}

	/**
	 * 予約当日の時間割マーク表示
	 *
	 */
	protected function _daily_mark($number, $mark) {
		$output = empty($this->controls[$mark . '_mark']) ? $number : $this->controls[$mark . '_mark'];

		return apply_filters('mtssb_daily_mark', $output, $number);
	}

}