<?php
/**
 * MTS Simple Booking Articles スケジュール管理モジュール
 *
 * @Filename	mtssb-schedule-admin.php
 * @Date		2012-04-27
 * @Author		S.Hayashi
 *
 * Updated to 1.4.0 on 2018-01-29
 * Updated to 1.2.0 on 2012-12-22
 */

class MTSSB_Schedule_Admin { //extends MTSSB_Schedule {
	const VERSION = '1.4.0';
	const PAGE_NAME = 'simple-booking-schedule';

	private static $iSchedule = null;

	private $domain;

	// WPオプションス ケジュールデータ
	private $schedule = null;

	// 読み込んだ予約品目データ
	private $article_id;
	private $theyear;
	private $articles = null;

	// 操作対象データ
	private $themonth = 0;		// 当該カレンダーのunix time

	private $action = '';
	private $message = '';
	private $errflg = false;

	/**
	 * インスタンス化
	 *
	 */
	static function get_instance() {
		if (!isset(self::$iSchedule)) {
			self::$iSchedule = new MTSSB_Schedule_Admin();
		}

		return self::$iSchedule;
	}

	public function __construct() {
		global $mts_simple_booking;

		//parent::__construct();
		$this->domain = MTS_Simple_Booking::DOMAIN;

		// CSSロード
		$mts_simple_booking->enqueue_style();

		// Javascriptロード
		wp_enqueue_script("mtssb_schedule_admin_js", plugins_url("js/mtssb-schedule-admin.js", __FILE__), array('jquery'));

	}

	/**
	 * 管理画面メニュー処理
	 *
	 */
	public function schedule_page() {

		$this->errflg = false;
		$this->message = '';

		// 予約品目の読み込み
		$this->articles = MTSSB_Article::get_all_articles();
		if (empty($this->articles)) {
			$this->message = __('The exhibited reservation item data has nothing.', $this->domain);
		}
		$this->article_id = key($this->articles);

		$this->themonth = mktime(0, 0, 0, date_i18n('n'), 1, date_i18n('Y'));

		if (isset($_REQUEST['action'])) {
			switch ($_REQUEST['action']) {
				case 'schedule' :
					$this->_schedule_parameter(intval($_GET['article_id']), intval($_GET['schedule_year']), intval($_GET['schedule_month']));
					break;
				case 'save' :
					if (wp_verify_nonce($_POST['nonce'], self::PAGE_NAME . '-save')) {
						$this->article_id = intval($_POST['article_id']);
						$this->_schedule_update();
						$this->_schedule_parameter(intval($_POST['article_id']), intval($_POST['schedule_year']), intval($_POST['schedule_month']));
						$this->message = __('Schedule has been saved.', $this->domain);
					} else {
						$this->errflg = true;
						$this->message = "Nonce error";
					}
					break;
				default:
					$this->errflg = true;
					$this->message  = "Unknown action";
					break;
			}
		}

		// 対象年月のスケジュールデータの読み込み
		$key_name = MTS_Simple_Booking::SCHEDULE_NAME . date_i18n('Ym', $this->themonth);
		$this->schedule = get_post_meta($this->article_id, $key_name, true);

?>
	<div class="wrap">
		<h2><?php _e('Schadule Management', $this->domain); ?></h2>
		<?php if (!empty($this->message)) : ?>
			<div class="<?php echo ($this->errflg) ? 'error' : 'updated' ?>"><p><strong><?php echo $this->message; ?></strong></p></div>
		<?php endif; ?>

		<?php if (!empty($this->articles)) : ?>

			<?php $this->_select_form() ?>

			<?php $this->_schedule_form() ?>

		<?php endif; ?>

	</div><!-- wrap -->
<?php
		return;

	}

	/**
	 * 予約スケジュールのフォーム表示出力
	 */
	private function _schedule_form() {
		$weeks = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');

		if (!empty($this->schedule)) {
			$schedule = $this->schedule;
		} else {
			$schedule = $this->_new_month($this->themonth);
		}

		// 過去スケジュールの場合はdisableセット
		$disabled = $this->themonth < mktime(0, 0, 0, date_i18n('n'), 1, date_i18n('Y')) ? ' disabled="disabled"' : '';

		// カレンダー生成パラメータ
		$starti = date('w', $this->themonth);
		$endd = count($schedule);
		$endi = $starti + $endd + 5 - date('w', mktime(0, 0, 0, date('n', $this->themonth), $endd, date('Y', $this->themonth)));


?>
	<form method="post" action="?page=<?php echo self::PAGE_NAME ?>">
		<div class="mtssb-schedule">
			<?php foreach ($weeks as $wname) {
				$week = strtolower($wname);
				echo "<div class=\"schedule-box column-title $week\"><label>" . __($wname)
				 . "<input id=\"schedule-check-$week\" class=\"$week\" type=\"checkbox\"$disabled /></label></div>";
			} ?>

			<?php
				for ($i = 0, $day = 1 - $starti; $i <= $endi ; $i++, $day++) {
					// フロート解除
					if ($i % 7 == 0) {
						echo "<div class=\"clear\"> </div>\n";
					}

					if (0 < $day && $day <= $endd) {
						$week = strtolower($weeks[$i % 7]);
						$day = sprintf("%02d", $day);
						echo "<div class=\"schedule-box $week\">";
						echo "<div class=\"schedule-day $week\"><label for=\"schedule-open-$day\">$day</label></div>";
						echo "<div class=\"schedule-open" . ($schedule[$day]['open'] ? ' open' : '') . "\"><input type=\"hidden\" name=\"schedule[$day][open]\" value=\"0\"$disabled />";
						echo "<input id=\"schedule-open-$day\" class=\"$week\" type=\"checkbox\" name=\"schedule[$day][open]\" value=\"1\"" . (0 < $schedule[$day]['open'] ? ' checked="checked"' : '') . " $disabled /></div>";
						echo "<div class=\"schedule-delta\"><input type=\"text\" name=\"schedule[$day][delta]\" value=\"" . (isset($schedule[$day]['delta']) ? $schedule[$day]['delta'] : 0) . "\"$disabled /></div>";
						echo "<div class=\"schedule-class\"><input type=\"text\" name=\"schedule[$day][class]\" value=\"" . $schedule[$day]['class'] . "\"$disabled /></div>";
					} else {
						echo '<div class="schedule-box no-day"> ';
					}
					echo "</div>\n";
				}
			?>
			<div class="clear"> </div>
		</div><!-- mtssb-schedule -->

		<?php if (!$disabled) : ?><div class="schedule-footer">
			<input type="hidden" name="article_id" value="<?php echo $this->article_id ?>" />
			<input type="hidden" name="schedule_year" value="<?php echo date('Y', $this->themonth) ?>" />
			<input type="hidden" name="schedule_month" value="<?php echo date('n', $this->themonth) ?>" />
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce(self::PAGE_NAME . '-save') ?>" />
			<input type="hidden" name="action" value="save" />
			<input type="submit" class="button-primary" value="<?php _e('Save Schedule', $this->domain) ?>" id="schedule-save" />
		</div><?php endif; ?>
	</form>

	<div id="schedule-description">
		<p><?php _e('Example:', $this->domain) ?></p>
		<div class="schedule-box">
			<div class="schedule-day"><?php _e('Day', $this->domain) ?></div>
			<div class="schedule-open_"><?php _e('Schedule Open', $this->domain) ?></div>
			<div class="schedule-delta"><?php _e('Delta', $this->domain) ?></div>
			<div class="schedule-class"><?php _e('Class Name', $this->domain) ?></div>
		</div>
	</div>

<?php
	}

	/**
	 * 予約品目、スケジュール年の選択フォーム出力
	 */
	private function _select_form() {
		$past = 1;
		$future = 1;

		$theyear = date('Y', $this->themonth);
		$themonth = date('n', $this->themonth);

		// リンク
		$this_year = date_i18n('Y');
		$min_month = mktime(0, 0, 0, 1, 1, $this_year - $past);
		$max_month = mktime(0, 0, 0, 12, 1, $this_year + $future);

		$prev_month = mktime(0, 0, 0, $themonth - 1, 1, $theyear);
		$prev_str = date('Y-m', $prev_month);
		$next_month = mktime(0, 0, 0, $themonth + 1, 1, $theyear);
		$next_str = date('Y-m', $next_month);

?>
	<div id="schedule-select-article">
		<h3><?php _e('Reservation item and the year', $this->domain) ?></h3>
		<form method="get" action="">
			<input type="hidden" name="page" value="<?php echo self::PAGE_NAME ?>" />
			<select class="select-article" name="article_id">
				<?php foreach ($this->articles as $article_id => $article) {
					echo "<option value=\"$article_id\"";
					if ($article_id == $this->article_id) {
						echo ' selected="selected"';
					}
					echo ">{$this->articles[$article_id]['name']}</option>\n";
				} ?>
			</select> 

			<?php _e('Year: ', $this->domain); ?>
			<select class="select-year" name="schedule_year">
				<?php for ($y = $this_year - $past; $y <= $this_year + $future; $y++) {
					echo "<option value=\"$y\"";
					if ($y == $theyear) {
						echo ' selected="selected"';
					}
					echo ">$y</option>\n";
				} ?>
			</select>

			<?php _e('Month:',$this->domain); ?>
			<select class="select-month" name="schedule_month">
				<?php for ($m = 1; $m <= 12; $m++) {
					echo "<option value=\"$m\"";
					if ($m == $themonth) {
						echo ' selected="selected"';
					}
					echo ">$m</option>\n";
				} ?>
			</select>

			<input class="button-secondary" type="submit" value="<?php _e('Change') ?>" />
			<input type="hidden" name="action" value="schedule" />
		</form>

		<h3><?php echo date('Y-m ', $this->themonth) . $this->articles[$this->article_id]['name'] ?></h3>
		<ul class="subsubsub">
			<li><?php
				if ($min_month <= $prev_month) {
					echo '<a href="?page=' . self::PAGE_NAME . "&article_id={$this->article_id}&schedule_year="
					 . date('Y', $prev_month) . "&schedule_month=" . date('n', $prev_month) . "&action=schedule\">$prev_str</a>";
				} else {
					echo $prev_str;
				} ?> | </li>
			<li><?php
				if ($next_month <= $max_month) {
					echo '<a href="?page=' . self::PAGE_NAME . "&article_id={$this->article_id}&schedule_year="
					 . date('Y', $next_month) . "&schedule_month=" . date('n', $next_month) . "&action=schedule\">$next_str</a>";
				} else {
					echo $next_str;
				} ?></li>
		</ul>
		<div class="clear"> </div>
	</div>

<?php
	}

	/**
	 * スケジュールデータの保存
	 *
	 */
	private function _schedule_update() {
		$article_id = intval($_POST['article_id']);
		if (!isset($this->articles[$article_id])) {
			return;
		}

		$key_name = MTS_Simple_Booking::SCHEDULE_NAME . sprintf("%04d%02d", intval($_POST['schedule_year']), intval($_POST['schedule_month']));
		update_post_meta($article_id, $key_name, $_POST['schedule']);
	}

	/**
	 * スケジュール管理に必要なパラメータの設定
	 *
	 */
	private function _schedule_parameter($article_id, $year, $month) {
		$past = 1;
		$future = 1;

		// 予約品目のIDチェック
		if (!isset($this->articles[$article_id])) {
			return;
		}

		// スケジュールの期間チェック
		$themonth = mktime(0, 0, 0, $month, 1, $year);

		$this_year = date_i18n('Y');
		if ($themonth < mktime(0, 0, 0, 1, 1, $this_year - $past)
		 || mktime(0, 0, 0, 12, 1, $this_year + $future) < $themonth) {
			return;
		};

		// パラメータの設定
		$this->article_id = $article_id;
		$this->themonth = $themonth;
	}

	/**
	 * スケジュール最小データ
	 *
	 * @daytime		xx年xx月xx日のunix time
	 */
	protected function _new_day($daytime=0) {

		return array(date('d', $daytime) => array(
			'open' => 0,		// 0:閉店 1:開店
			'delta' => 0,		// 予約数量の増減
			'class' => '',		// class 表示データ
		));
	}

	/**
	 * 1ヶ月の空スケジュール取得
	 *
	 * @datetime	xx年xx月1日のunix time
	 */
	protected function _new_month($monthtime) {

		// 当月日データ構築
		$next_month = mktime(0, 0, 0, date('n', $monthtime) + 1, 1, date('Y', $monthtime));

		$month = array();
		for ($daytime = $monthtime; $daytime < $next_month; $daytime += 86400) {
			$month += $this->_new_day($daytime);
		}

		return $month;
	}

}
