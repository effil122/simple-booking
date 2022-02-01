<?php
if (!class_exists('MTSSB_Booking')) {
	require_once(dirname(__FILE__) . '/mtssb-booking.php');
}
/**
 * MTS Simple Booking Booking 予約登録・編集モジュール
 *
 * @Filename	mtssb-booking-admin.php
 * @Date		2012-04-30
 * @Author		S.Hayashi
 *
 * Updated to 1.4.0 on 2018-01-29
 * Updated to 1.1.0 on 2012-10-11
 * Updated to 1.0.2 on 2012-10-23
 */

class MTSSB_List_Admin extends MTSSB_Booking {
	const VERSION = '1.4.0';
	const PAGE_NAME = 'simple-booking-list';

	private static $iList = null;

	// リストテーブルオブジェクト
	private $blist = null;

	// 読み込んだ予約品目データ
	private $article_id;
	private $theyear;
	private $articles = null;		// 予約品目

	// 操作対象データ
	private $themonth = 0;		// 当該カレンダーのunix time
	private $action = '';		// none or montly

	private $message = '';
	private $errflg = false;

	/**
	 * インスタンス化
	 *
	 */
	static function get_instance() {
		if (!isset(self::$iList)) {
			self::$iList = new MTSSB_List_Admin();
		}

		return self::$iList;
	}

	public function __construct() {
		global $mts_simple_booking;

		parent::__construct();

		// CSSロード
		$mts_simple_booking->enqueue_style();

		// Javascriptロード
		//wp_enqueue_script("mtssb_add_admin_js", plugins_url("js/mtssb-add-admin.js", __FILE__), array('jquery'));

	}

	/**
	 * 管理画面メニュー処理
	 *
	 */
	public function list_page() {

		$this->errflg = false;
		$this->message = '';

		$this->action = 'none';
		$this->themonth = mktime(0, 0, 0, date_i18n('n'), 1, date_i18n('Y'));

		if (isset($_GET['action'])) {
			$this->action = $_GET['action'];
		}

		switch ($this->action) {
			case 'monthly' :
				if (isset($_GET['year']) && isset($_GET['month'])) {
					$this->themonth = mktime(0, 0, 0, intval($_GET['month']), 1, intval($_GET['year']));
				}
				break;
			case 'delete' :
				// NONCEチェックOKなら削除する
				if (wp_verify_nonce($_GET['nonce'], self::PAGE_NAME . "_{$this->action}")) {
					if ($this->del_booking($_GET['booking_id'])) {
						$this->message = sprintf(__('Booking ID:%d was deleted.', $this->domain), $_GET['booking_id']);
					} else {
						$this->message = __('Deleting the booking data was failed.', $this->domain);
						$this->errflg = true;
					}
				} else {
					$this->message = 'Nonce check error.';
					$this->errflg = true;
				}
				// ページネーションのリンクにdeleteが残るのでURLをクリアする
				$_SERVER['REQUEST_URI'] = remove_query_arg(array('booking_id', 'action', 'nonce'));
				break;
			default:
				break;
		}


		// リスト表示
		if (!class_exists('WP_List_Table')) {
			require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
		}

		$this->blist = new MTSSB_Booking_List();
		$this->blist->prepare_items($this);
		//$action = $this->blist->current_action();


?>
	<div class="wrap">

		<div id="icon-edit" class="icon32"><br /></div>
		<h2><?php _e('Booking List', $this->domain) ?></h2>

		<?php if (!empty($this->message)) : ?><div class="<?php echo $this->errflg ? 'error' : 'updated' ?>">
			<p><?php echo $this->message ?></p>
		</div><?php endif; ?>

		<?php $this->_select_form() ?>

		<div id="booking-list">
			<form id="movies-filter" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
				<?php $this->blist->display() ?>
			</form>
		</div>
	</div>
    <?php
	}

	/**
	 * スケジュール月間指定フォームの出力
	 */
	private function _select_form() {

		$this_year = date_i18n('Y');
		$this_month = date_i18n('n');
		$this_time = mktime(0, 0, 0, $this_month, 1, $this_year);

		$theyear = date('Y', $this->themonth);
		$themonth = date('n', $this->themonth);

		// リンク
		$prev_month = mktime(0, 0, 0, $themonth - 1, 1, $theyear);
		$prev_str = date('Y-m', $prev_month);
		$next_month = mktime(0, 0, 0, $themonth + 1, 1, $theyear);
		$next_str = date('Y-m', $next_month);

?>
	<div id="schedule-select-article">
		<h3><?php _e('Change date of list', $this->domain) ?></h3>
		<form method="get" action="">
			<input type="hidden" name="page" value="<?php echo self::PAGE_NAME ?>" />

			<?php _e('Year: ', $this->domain); ?>
			<select class="select-year" name="year">
				<?php for ($y = $this_year - 1; $y <= $this_year; $y++) {
					echo "<option value=\"$y\"";
					if ($y == $this_year) {
						echo ' selected="selected"';
					}
					echo ">$y</option>\n";
				} ?>
			</select>

			<?php _e('Month:',$this->domain); ?>
			<select class="select-month" name="month">
				<?php for ($m = 1; $m <= 12; $m++) {
					echo "<option value=\"$m\"";
					if ($m == $this_month) {
						echo ' selected="selected"';
					}
					echo ">$m</option>\n";
				} ?>
			</select>

			<input class="button-secondary" type="submit" value="<?php _e('Change monthly', $this->domain) ?>" />
			<input type="hidden" name="action" value="monthly" />
		</form>

		<?php if ($this->action == 'monthly') : ?><h3><?php echo sprintf('%4d-%02d', $theyear, $themonth) ?></h3>
		<ul class="subsubsub">
			<li><?php echo '<a href="?page=' . self::PAGE_NAME . "&year=" . date('Y', $prev_month)
					 . "&month=" . date('n', $prev_month) . "&action=monthly\">$prev_str</a>"; ?> | </li>
			<li><?php echo '<a href="?page=' . self::PAGE_NAME . "&year=" . date('Y', $next_month)
						 . "&month=" . date('n', $next_month) . "&action=monthly\">$next_str</a>"; ?></li>
		</ul>
		<div class="clear"> </div><?php endif; ?>
	</div>

<?php
	}

	/**
	 * リストオブジェクトからのレコード件数取得コール関数
	 *
	 */
	public function list_count() {

		if ($this->action == 'monthly') {
			return $this->get_booking_count_monthly(date('Y', $this->themonth), date('n', $this->themonth));
		} else {
			return $this->get_booking_count();
		}
	}

	/**
	 * リストオブジェクトからのデータ取得コール関数
	 *
	 */
	public function read_data($offset, $limit, $order) {

		if ($this->action == 'monthly') {
			$conditions = 'booking_time>=' . mktime(0, 0, 0, date('n', $this->themonth), 1, date('Y', $this->themonth))
				. ' AND booking_time<' . mktime(0, 0, 0, date('n', $this->themonth) + 1, 1, date('Y', $this->themonth));
			return $this->get_booking_list($offset, $limit, $order, $conditions);

		} else {
			return $this->get_booking_list($offset, $limit, $order);
		}

	}

}

/**
 * 予約一覧
 *
 */
class MTSSB_Booking_List extends WP_List_Table {
	const PAGE_NAME = 'simple-booking-list';

	private $domain = '';
	private $per_page = 20;

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		global $status, $page;

		parent::__construct(array(
			'singular' => 'booking',
			'plural' => 'bookings',
			'ajax' => false
		));

		$this->domain = MTS_Simple_Booking::DOMAIN;
	}

	/**
	 * リストカラム情報
	 *
	 */
	public function get_columns() {
		return array(
			'booking_id' => __('ID', $this->domain),
			'booking_time' => __('Booking Date', $this->domain),
			'name' => __('Name'),
			'number' => __('Number', $this->domain),
			'article_id' => __('Article Name', $this->domain),
			'confirmed' => __('Confirmed', $this->domain),
			'created' => __('Date'),
		);
	}

	/**
	 * ソートカラム情報
	 *
	 */
	public function get_sortable_columns() {
		return array(
			'booking_id' => array('id', false),
			'booking_time' => array('booking_time', true),
		);
	}

	/**
	 * カラムデータのデフォルト表示
	 *
	 */
	public function column_default($item, $column_name) {

		switch ($column_name) {
			case 'booking_id' :
				return $item[$column_name];
			case 'confirmed' :
				$url = plugins_url('image/' . ($item[$column_name] ? 'system-tick.png' : 'system-stop.png'), __FILE__);
				return '<img src="' . $url . '" />';
				//return $item[$column_name];
			case 'article_id' :
				return $item['article_name'];
			case 'number' :
				return $item[$column_name];
			case 'created' :
				return substr($item[$column_name], 0, 10) . '<br />' . substr($item[$column_name], -8);
			case 'name' :
				return (empty($item['client']['company']) ? '' : sprintf('%s<br />', esc_html($item['client']['company']))) . esc_html($item['client']['name']);
			//case 'booking_time' :
			//	return date('Y-m-d H:i', $item['booking_time']);
			default :
				return print_r($item, true);
		}
	}

	/**
	 * カラムデータ booking_time とアクションリンク表示
	 *
	 */
	public function column_booking_time($item) {

		// アクション
		$actions = array(
			'view' => sprintf('<a href="?page=simple-booking&amp;bid=%d">%s</a>', $item['booking_id'], __('View')),
			'edit' => sprintf('<a href="?page=simple-booking-booking&amp;booking_id=%d&action=edit">%s</a>', $item['booking_id'], __('Edit')),
			'delete' => sprintf("<a href=\"?page=simple-booking-list&amp;booking_id=%d&action=delete&nonce=%s\" onclick=\"return confirm('%s')\">%s</a>", $item['booking_id'], wp_create_nonce(self::PAGE_NAME . '_delete'), __('Do you really want to delete this booking?', $this->domain), __('Delete')),
		);

		//return esc_html($item['client']['name']) . $this->row_actions($actions);
		return date('Y-m-d H:i', $item['booking_time']) . $this->row_actions($actions);
	}



	/**
	 * リスト表示準備
	 *
	 * @dba		parent object
	 */
	public function prepare_items($dba=null) {

		// カラムヘッダープロパティの設定
		$this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

		// カレントページの取得
		$current_page = $this->get_pagenum() - 1;

		// 予約データ全数の取得
		$total_items = $dba->list_count();

		// 予約データの取得
		$this->items = $dba->read_data($current_page * $this->per_page, $this->per_page, $this->_set_order_key());

		//$data = MTSSB_Booking::get_booking_list($current_page * $this->per_page, $this->per_page, $this->_set_order_key());
		//$this->items = $data;

		// ページネーション設定
		$this->set_pagination_args(array(
			'total_items' => $total_items,
			'per_page' => $this->per_page,
			'total_pages' => ceil($total_items / $this->per_page),
		));

	}

	/**
	 * カラムソート指定をキー配列にする
	 *
	 */
	private function _set_order_key() {
		$order = array('key' => 'booking_id', 'direction' => 'desc');

		if (isset($_REQUEST['orderby'])) {
			if ($_REQUEST['orderby'] == 'booking_id' || $_REQUEST['orderby'] == 'booking_time') {
				$order['key'] = $_REQUEST['orderby'];
			}
		}

		if (isset($_REQUEST['order'])) {
			$order['direction'] = $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';
		}

		return $order;
	}
}
