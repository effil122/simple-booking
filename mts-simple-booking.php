<?php
/*
Plugin Name: Simple Booking
Plugin URI:http://mtssb.mt-systems.jp/
Description: 簡易予約処理システムです。オンラインで予約を受付けメール転送及びスプレッドシートに書き込みを行います。（※メールとスプレッドシートの設定が必須）WordPress Ver.3.5以降で動作させて下さい。
Version: 1.4.1
Author: Suzuyama Hidehisa
Author URI: https://effil.net/
*/

$mts_simple_booking = new MTS_Simple_Booking();

class MTS_Simple_Booking {

	const VERSION = '1.4.1';
	const DOMAIN = 'mts_simple_booking';

	const ADMIN_MENU = 'simple-booking';
	const PAGE_LIST = 'simple-booking-list';
	const PAGE_BOOKING = 'simple-booking-booking';
	const PAGE_SETTINGS = 'simple-booking-settings';
	const PAGE_SCHEDULE = 'simple-booking-schedule';

	// フロントページ
	const PAGE_BOOKING_FORM = 'booking-form';
	const PAGE_BOOKING_THANKS = 'booking-thanks';
	const PAGE_CONFIRMATION = 'confirmation';

	const ADMIN_CSS_FILE = 'css/mtssb-admin.css';
	const FRONT_CSS_FILE = 'css/mtssb-front.css';

	// ユーザー登録のロール
	const USER_ROLE = 'customer';

	// スケジュール名プレフィックス
	const SCHEDULE_NAME = 'schedule_';

	// モジュールオブジェクト
	public $oArticle = null;
	public $oBooking_form = null;
	public $oFront = null;
	public $oCalendar_widget = null;
	private $oMail = null;

	// MTS Customerプラグインの組込み
	public $mtscu_activation = false;

	// ランゲージファイルロード
	protected $lang = false;


	public $plugin_url;
	public $settings;

	public function __construct() {

		// Set Plug in URL
		$this->plugin_url = plugin_dir_url(__FILE__);	// WP_PLUGIN_URL . '/mts-simple-booking/'

		// ランゲージファイルロード
		if (!$this->lang) {
			if (load_textdomain(self::DOMAIN, dirname(__FILE__) . '/languages/' . get_locale() . '.mo')) {
				$this->lang = true;
			}
		}

		add_action('init', array(&$this, 'init_mtssb'));

		// 予約カレンダーウィジェットモジュールのロード
		require_once('mtssb-calendar-widget.php');
		MTSSB_Calendar_Widget::set_ajax_hook();
        add_action('widgets_init', function() {
            register_widget(MTSSB_Calendar_Widget::BASE_ID);
        });

	}

	/**
	 * プラグイン初期化処理
	 *
	 */
	public function init_mtssb()
	{

		// その他設定の読み込み
		$miscellaneous = get_option(self::DOMAIN . '_miscellaneous');

		// MTS Customerプラグインの組み込み確認
		if (!function_exists('is_plugin_active')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		if (is_plugin_active('mts-customer/mts-customer.php')) {
			$this->mtscu_activation = true;
		}

		if (is_admin()) {

			// ユーザーロールがcustomerなら管理画面を表示させない
			if (!$this->mtscu_activation) {
				$currentUser = wp_get_current_user();
				if (in_array(self::USER_ROLE, $currentUser->roles)) {
					wp_redirect(home_url());
					exit();
				}
			}

			// 管理画面処理メニュー登録
			add_action('admin_menu', array($this, 'add_admin_menu'));

			// その他管理モジュールのロードとインスタンス化
			add_action('admin_init', array($this, 'admin_init'));

			// 予約品目処理オブジェクトのロード
			require_once('mtssb-article-admin.php');
			$oArticle = new MTSSB_Article_Admin();

		} else {
			// 予約品目処理オブジェクトのロード
			require_once('mtssb-article.php');
			$oArticle = new MTSSB_Article();

			// 予約の登録、メール送信
			add_action('wp', array($this, 'internal_dispatcher'));

			// ショートコードの登録
			add_shortcode('monthly_calendar', array($this, 'monthly_calendar'));

			// フォームのフロント処理ディスパッチャー
			add_filter('the_content', array($this, 'form_dispatcher'));

			// フロント admin bar を非表示にする
			if ($miscellaneous['adminbar']) {
				add_filter('show_admin_bar', '__return_false');
			}

			// フロント表示のための設定
			add_action('wp_enqueue_scripts', array($this, 'front_enqueue_style'));
		}

	}

	/**
	 * 管理画面メニュー登録
	 *
	 */
	public function add_admin_menu() {
		add_menu_page(__('MTS Simple Booking', self::DOMAIN), __('Simple Booking', self::DOMAIN), 'administrator', self::ADMIN_MENU, array($this, 'menu_calendar'));
		add_submenu_page(self::ADMIN_MENU, __('Calendar', self::DOMAIN), __('Calendar', self::DOMAIN), 'administrator', self::ADMIN_MENU, array($this, 'menu_calendar'));
		add_submenu_page(self::ADMIN_MENU, __('List Booking', self::DOMAIN), __('List Booking', self::DOMAIN), 'administrator', self::PAGE_LIST, array($this, 'menu_list'));
		add_submenu_page(self::ADMIN_MENU, __('Add & Edit', self::DOMAIN), __('Add & Edit', self::DOMAIN), 'administrator', self::PAGE_BOOKING, array($this, 'menu_booking'));
		add_submenu_page(self::ADMIN_MENU, __('Schedule', self::DOMAIN), __('Schedule', self::DOMAIN), 'administrator', self::PAGE_SCHEDULE, array($this, 'menu_schedule'));
		add_submenu_page(self::ADMIN_MENU, __('Settings', self::DOMAIN), __('Settings', self::DOMAIN), 'administrator', self::PAGE_SETTINGS, array($this, 'menu_settings'));
	}

	/**
	 * 管理画面メニュー処理　予約カレンダー
	 *
	 */
	public function menu_calendar() {
		$this->calendar->calendar_page();
	}

	/**
	 * 管理画面メニュー処理　予約の一覧
	 *
	 */
	public function menu_list() {
		$this->blist->list_page();
	}

	/**
	 * 管理画面メニュー処理　予約の新規追加
	 *
	 */
	public function menu_booking() {
		$this->booking->booking_page();
	}

	/**
	 * 管理画面メニュー処理　スケジュール
	 *
	 */
	public function menu_schedule() {
		$this->schedule->schedule_page();
	}

	/**
	 * 管理画面メニュー処理　各種設定
	 *
	 */
	public function menu_settings() {
		$this->settings->settings_page();
	}

	/**
	 * admin_init アクションで処理しなければならないモジュール処理
	 *
	 */

	public function admin_init() {

		if (isset($_REQUEST['page'])) {
			$page = $_REQUEST['page'];
		// WP options.phpでオプションデータ保存のためのホワイトリスト登録
		} else if (isset($_POST['option_page']) && isset($_POST['mts_page_tag'])) {
			$page = $_POST['mts_page_tag'];
		} else {
			return;
		}

		switch ($page) {
			case self::ADMIN_MENU :
			//case self::PAGE_CALENDAR :
				if (!class_exists('MTSSB_Calendar_Admin')) {
					require_once('mtssb-calendar-admin.php');
				}
				$this->calendar = MTSSB_Calendar_Admin::get_instance();
				break;
			case self::PAGE_LIST :
				if (!class_exists('MTSSB_List_Admin')) {
					require_once('mtssb-list-admin.php');
				}
				$this->blist = MTSSB_List_Admin::get_instance();
				break;
			case self::PAGE_BOOKING :
				if (!class_exists('MTSSB_Booking_Admin')) {
					require_once('mtssb-booking-admin.php');
				}
				$this->booking = MTSSB_Booking_Admin::get_instance();
				break;
			case self::PAGE_SETTINGS :
				if (!class_exists('MTSSB_Settings_Admin')) {
					require_once('mtssb-settings-admin.php');
				}
				$this->settings = MTSSB_Settings_Admin::get_instance();
				break;
			case self::PAGE_SCHEDULE :
				if (!class_exists('MTSSB_Schedule_Admin')) {
					require_once('mtssb-schedule-admin.php');
				}
				$this->schedule = MTSSB_Schedule_Admin::get_instance();
				break;
			default :
				break;
		}

	}

	/**
	 * 管理画面 CSS ファイルロード登録
	 *
	 */
	public function enqueue_style() {
		$handle = self::DOMAIN . '_admin_css';
		wp_enqueue_style($handle, $this->plugin_url . self::ADMIN_CSS_FILE);
	}

	/**
	 * ショートコード 月間予約カレンダーのロード・実行
	 *
	 */
	public function monthly_calendar($atts) {
		if (!class_exists('MTSSB_Front')) {
			require_once(dirname(__FILE__) . '/mtssb-front.php');
		}

		$this->oFront = new MTSSB_Front();
		return $this->oFront->monthly_calendar($atts);
	}

	/**
	 * フロント CSS ファイルロード登録
	 *
	 */
	public function front_enqueue_style() {
		$handle = self::DOMAIN . '_front_css';
		wp_enqueue_style($handle, $this->plugin_url . self::FRONT_CSS_FILE);
	}

	/**
	 * 予約登録・メール送信処理内部ディスパッチャー
	 *
	 */
	public function internal_dispatcher() {

		$action = isset($_POST['action']) ? $_POST['action'] : '';

		if (is_page(self::PAGE_BOOKING_FORM)) {
			if ($action == 'confirm') {
				$booking_form = $this->_load_module('MTSSB_Booking_Form');
				if ($booking_form->front_booking()) {
					$mail = $this->_load_module('MTSSB_Mail');
					// 予約メールをお客・自社・モバイルへ送信、リダイレクトページがあれば実行
					if ($mail->booking_mail()) {
						$next_url = self::get_permalink_by_slug(self::PAGE_BOOKING_THANKS);
						if ($next_url) {
							wp_redirect($next_url);
							exit();
						}
					} else {
						// メールの送信エラーセット
						$booking_form->error_send_mail();
					}
				}
			}
			// jQueryを読込む
			//wp_enqueue_script('jquery');

		}
	}

	/**
	 * 予約処理、お問い合わせ処理フォームディスパッチャー
	 *
	 */
	public function form_dispatcher($content) {
		
		if (is_page(self::PAGE_BOOKING_FORM)) {
			$booking_form = $this->_load_module('MTSSB_Booking_Form');
			$content = $booking_form->booking_form($content);
		}

		return $content;
	}

	/**
	 * フロントページ処理モジュールのロード
	 *
	 * @class_name
	 * @return		Module Object
	 */
	private function _load_module($class_name) {

		if (!class_exists($class_name)) {
			$filename = strtolower(str_replace('_', '-', $class_name)) . '.php';
			require(dirname(__FILE__) . "/$filename");
		}

		switch ($class_name) {
			case 'MTSSB_Booking_Form':
				if (empty($this->oBooking_form)) {
					$this->oBooking_form = new MTSSB_Booking_Form();
				}
				return $this->oBooking_form;
			case 'MTSSB_Mail':
				if (empty($this->oMail)) {
					$this->oMail = new MTSSB_Mail();
				}
				return $this->oMail;
			default:
				break;
		}

		return null;
	}

	/**
	 * スラッグ名から投稿のリンクURLを取得する
	 *
	 * @slug	スラッグ名
	 * @type	post_type(='page')
	 */
	static public function get_permalink_by_slug($name) {
		global $wpdb;

		$post_id = $wpdb->get_col($wpdb->prepare("
			SELECT ID FROM {$wpdb->posts}
			WHERE post_status='publish' AND post_name=%s
			ORDER BY ID", $name));

		if (empty($post_id)) {
			return false;
		}

		return get_permalink($post_id[0]);
	}

	/**
	 * Uninstall
	 *
	 */
	public function uninstall() {
	}

}

