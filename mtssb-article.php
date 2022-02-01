<?php
/**
 * MTS Simple Booking Articles module 予約品目モジュール
 *
 * @Filename	mtssb-article.php
 * @Date		2012-04-19
 * @Author		S.Hayashi
 *
 * Updated to 1.4.0 on 2018-01-29
 */

class MTSSB_Article {
	const VERSION = '1.4.0';

	const POST_TYPE = 'mtssb_article';
	const PST_VERSION = '1.0';

	/**
	 * Protected valiable
	 */
	protected $domain;			// mts_simple_booking

	// Custom Post
	//public $custom_post;		// array('name'=>'room', 'type'=>'mtsbb_room')


	/**
	 * Constructor
	 *
	 */
	public function __construct() {

		$this->domain = MTS_Simple_Booking::DOMAIN;

		// Register Custom Post Type
		if (!post_type_exists(self::POST_TYPE)) {
			$this->_register_post_type();
		}

		// AJAX登録
		//add_action('wp_ajax_mtssb_get_timetable', array($this, 'ajax_get_the_timetable'));
	}

	/**
	 * Register Custom Post Type
	 *
	 */
	protected function _register_post_type() {
		$labels = array(
			'name' => __('Booking Articles', $this->domain),
			'singular_name' => __('Booking Article', $this->domain),
			'add_new' => __('New Booking Article', $this->domain),
			'add_new_item' => __('Add New Booking Article', $this->domain),
			'edit_item' => __('Edit Booking Article', $this->domain),
		);

		$args = array(
			'label' => 'Articles',
			'labels' => $labels,
			'public' => true,
			//'publicly_queryable' => false,
			'rewrite' => array('slug' => 'article'),
			'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
			'register_meta_box_cb' => array($this, 'register_meta_box'),
		);
		register_post_type(self::POST_TYPE, $args);
		flush_rewrite_rules(false);
	}

	/**
	 * Meta Box for admin edit page
	 *	Override function for admin class
	 * 
	 */
	public function register_meta_box() {
	}

	/**
	 * 予約品目初期データ
	 */
	public function get_new_article($data=false) {
		$article = array(
			//'article_id' => 0,
			//'name' => '',
			'timetable' => '',			// 予約時間割
			'restriction' => 'capacity',// 予約制限(capacity or quantity)
			'capacity' => 0,			// 収容定員数
			'quantity' => 0,			// 予約最大件数
			'minimum' => 0,				// 予約受付最小人数
			'maximum' => 0,				// 予約受付最大人数
			'count' => array(			// 人数カウントレート(子供は半分0.5など可能)
				'adult' => 1,
				'child' => 0,
				'baby' => 0,
			),
			'addition' => 0,		// 予約追加オプション選択
		);

		if ($data) {
			$article = array_merge($article, $data);
		}

		return $article;
	}

	/**
	 * 予約品目の時間割取得
	 *
	 */
	static public function get_the_timetable($article_id=0) {
		global $wpdb;

		$data = $wpdb->get_row($wpdb->prepare("
			SELECT ID AS article_id,m1.meta_value AS timetable
			FROM $wpdb->posts
			LEFT JOIN $wpdb->postmeta AS m1 ON m1.post_id=ID AND m1.meta_key='timetable'
			WHERE ID=%d", intval($article_id)), ARRAY_A);

		if (empty($data['timetable'])) {
			return array();
		}

		return unserialize($data['timetable']);
	}

	/**
	 * 予約品目の取得
	 *
	 */
	static public function get_the_article($article_id=0) {
		global $wpdb;

		$sql = $wpdb->prepare("
			SELECT ID AS article_id,post_title AS name,post_type,post_status,
				m1.meta_value AS timetable,m2.meta_value AS restriction,m3.meta_value AS capacity,m4.meta_value AS quantity,
				m5.meta_value AS minimum,m6.meta_value AS maximum,m7.meta_value AS count,m8.meta_value AS addition
			FROM {$wpdb->posts}
			LEFT JOIN {$wpdb->postmeta} AS m1 ON m1.post_id=ID AND m1.meta_key='timetable'
			LEFT JOIN {$wpdb->postmeta} AS m2 ON m2.post_id=ID AND m2.meta_key='restriction'
			LEFT JOIN {$wpdb->postmeta} AS m3 ON m3.post_id=ID AND m3.meta_key='capacity'
			LEFT JOIN {$wpdb->postmeta} AS m4 ON m4.post_id=ID AND m4.meta_key='quantity'
			LEFT JOIN {$wpdb->postmeta} AS m5 ON m5.post_id=ID AND m5.meta_key='minimum'
			LEFT JOIN {$wpdb->postmeta} AS m6 ON m6.post_id=ID AND m6.meta_key='maximum'
			LEFT JOIN {$wpdb->postmeta} AS m7 ON m7.post_id=ID AND m7.meta_key='count'
			LEFT JOIN {$wpdb->postmeta} AS m8 ON m8.post_id=ID AND m8.meta_key='addition'
			WHERE ID=%d AND post_type=%s", intval($article_id), self::POST_TYPE);

		$article = $wpdb->get_row($sql, ARRAY_A);

		if (!isset($article['timetable']) || empty($article['timetable'])) {
			$article['timetable'] = array();
		} else {
			$article['timetable'] = unserialize($article['timetable']);
		}

		if (isset($article['count'])) {
			$article['count'] = unserialize($article['count']);
		}

		return $article;
	}

	/**
	 * 全予約品目の取得
	 *
	 * @key		0:number 1:ID
	 */
	static public function get_all_articles($ids='0') {
		global $wpdb;

		// 予約品目IDの指定
		if ($ids == '0') {
			$aids = '1=1';
		} else {
			$aids = "ID in ($ids)";
		}

		$sql = $wpdb->prepare("
			SELECT ID AS article_id,post_title AS name,post_name AS slug,post_type,post_status,
				m1.meta_value AS timetable,m2.meta_value AS restriction,m3.meta_value AS capacity,m4.meta_value AS quantity,
				m5.meta_value AS minimum,m6.meta_value AS maximum,m7.meta_value AS count,m8.meta_value AS addition
			FROM {$wpdb->posts}
			LEFT JOIN {$wpdb->postmeta} AS m1 ON m1.post_id=ID AND m1.meta_key='timetable'
			LEFT JOIN {$wpdb->postmeta} AS m2 ON m2.post_id=ID AND m2.meta_key='restriction'
			LEFT JOIN {$wpdb->postmeta} AS m3 ON m3.post_id=ID AND m3.meta_key='capacity'
			LEFT JOIN {$wpdb->postmeta} AS m4 ON m4.post_id=ID AND m4.meta_key='quantity'
			LEFT JOIN {$wpdb->postmeta} AS m5 ON m5.post_id=ID AND m5.meta_key='minimum'
			LEFT JOIN {$wpdb->postmeta} AS m6 ON m6.post_id=ID AND m6.meta_key='maximum'
			LEFT JOIN {$wpdb->postmeta} AS m7 ON m7.post_id=ID AND m7.meta_key='count'
			LEFT JOIN {$wpdb->postmeta} AS m8 ON m8.post_id=ID AND m8.meta_key='addition'
			WHERE post_type=%s AND post_status=%s AND $aids
			ORDER BY menu_order ASC, ID ASC", self::POST_TYPE, 'publish');

		$articles = $wpdb->get_results($sql, ARRAY_A);

		// 時間割・カウントフラグデータのアンシリアライズとIDのインデックス化
		$articleids = array();
		$acount = count($articles);
		for ($i = 0; $i < $acount; $i++) {
			// タイムテーブルをアンシリアライズする
			if (isset($articles[$i]['timetable']) && !empty($articles[$i]['timetable'])) {
				$articles[$i]['timetable'] = unserialize($articles[$i]['timetable']);
			} else {
				$articles[$i]['timetable'] = array();
			}

			// １日の合計受入枠を計算する
			$number = count($articles[$i]['timetable']);
			$articles[$i]['total_capacity'] = $articles[$i]['capacity'] * $number;
			$articles[$i]['total_quantity'] = $articles[$i]['quantity'] * $number;

			// カウントフラグデータ(人数計算レート)をアンシリアライズする
			if (isset($articles[$i]['count'])) {
				$articles[$i]['count'] = unserialize($articles[$i]['count']);
			}

			$articleids[$articles[$i]['article_id']] = $articles[$i];
		}

		return $articleids;
	}

}
