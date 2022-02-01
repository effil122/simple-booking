<?php
/**
 * MTS Simple Booking 予約カレンダーウィジェットモジュール
 *
 * @Filename	mtssb-calendar-widget.php
 * @Date		2012-11-27
 * @Author		S.Hayashi
 *
 * Updated to 1.4.6 on 2013-04-26
 */
class MTSSB_Calendar_Widget extends WP_Widget {
	const VERSION = '1.4.6';

	const BASE_ID = 'mtssb_calendar_widget';
	const GET_BOOKING = 'mtssb_get_booking_calendar';	// Function name called by ajax

	const JS_PATH = 'js/mtssb-calendar-widget.js';		// JavaScript file path

	private	$domain = '';

	/**
	 * Constructor
	 *	Register widget with WordPress.
	 */
	public function __construct() {
		$this->domain = MTS_Simple_Booking::DOMAIN;

		parent::__construct(
			self::BASE_ID,					// Base ID
			__('MTS Simple Booking Calendar', $this->domain),	// Name
			array('description' => __('The booking calendar is displayed in the side bar.', $this->domain))
		);
	}

	/**
	 * Front End AJAX エントリーの登録
	 *
	 */
	static public function set_ajax_hook() {
		add_action('wp_ajax_' . self::GET_BOOKING, array('MTSSB_Calendar_Widget', 'get_booking_calendar'));
		add_action('wp_ajax_nopriv_' . self::GET_BOOKING, array('MTSSB_Calendar_Widget', 'get_booking_calendar'));
	}

	/**
	 * Sanitize widget form values as they are saved
	 *
	 */
	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		$instance['post_id'] = intval($new_instance['post_id']);
		$instance['caption'] = intval($new_instance['caption']);
		$instance['link'] = intval($new_instance['link']);
		$instance['pagination'] = intval($new_instance['pagination']);
		$instance['skiptime'] = intval($new_instance['skiptime']);
		$year = intval($new_instance['year']);
		if ($year < date_i18n('Y')) {
			$year = $month = '';
		} else {
			$month = intval($new_instance['month']);
			if ($month < 1 || 12 < $month || ($year == date_i18n('Y') && $month < date_i18n('n'))) {
				$year = $month = '';
			}
		}
		$instance['year'] = $year;
		$instance['month'] = $month;
		$class = trim(strip_tags(stripslashes($new_instance['class'])));
		$instance['class'] = empty($class) ? 'monthly-calendar' : $class;


		return $instance;
	}

	/**
	 * Back-end widget form
	 *
	 */
	public function form($instance) {
		$title = isset($instance['title']) ? $instance['title'] : __('Calendar', $this->domain);
		$post_id = empty($instance['post_id']) ? '' : $instance['post_id'];
		$caption = isset($instance['caption']) ? $instance['caption'] : '1';
		$link = isset($instance['link']) ? $instance['link'] : '1';
		$pagination = isset($instance['pagination']) ? $instance['pagination'] : '1';
		$skiptime = empty($instance['skiptime']) ? '0' : $instance['skiptime'];
		$year = empty($instance['year']) ? '' : $instance['year'];
		$month = empty($instance['month']) ? '' : $instance['month'];
		$class = empty($instance['class']) ? 'monthly-calendar' : $instance['class'];

?>
		<p>
			<label for="<?php echo $this->get_field_id('title') ?>"><?php _e('Title:') ?></label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id('title') ?>" name="<?php echo $this->get_field_name('title') ?>" value="<?php echo $title ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('post_id') ?>"><?php _e('ID:') ?></label><br />
			<input type="text" id="<?php echo $this->get_field_id('post_id') ?>" name="<?php echo $this->get_field_name('post_id') ?>" value="<?php echo $post_id ?>" size="5" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('caption') ?>"><?php _e('Display Caption', $this->domain) ?></label><br />
			<input type="hidden" id="<?php echo $this->get_field_id('caption')  . '_' ?>" name="<?php echo $this->get_field_name('caption') ?>" value="0" />
			<input type="checkbox" id="<?php echo $this->get_field_id('caption') ?>" name="<?php echo $this->get_field_name('caption') ?>" value="1"<?php echo $caption ? ' checked' : ''; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('link') ?>"><?php _e('Booking Link', $this->domain) ?></label><br />
			<input type="hidden" id="<?php echo $this->get_field_id('link')  . '_' ?>" name="<?php echo $this->get_field_name('link') ?>" value="0" />
			<input type="checkbox" id="<?php echo $this->get_field_id('link') ?>" name="<?php echo $this->get_field_name('link') ?>" value="1"<?php echo $link ? ' checked' : ''; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('pagination') ?>"><?php _e('Month Link', $this->domain) ?></label><br />
			<input type="hidden" id="<?php echo $this->get_field_id('pagination')  . '_' ?>" name="<?php echo $this->get_field_name('pagination') ?>" value="0" />
			<input type="checkbox" id="<?php echo $this->get_field_id('pagination') ?>" name="<?php echo $this->get_field_name('pagination') ?>" value="1"<?php echo $pagination ? ' checked' : ''; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('skiptime') ?>"><?php _e('Skip Timetable', $this->domain) ?></label><br />
			<input type="hidden" id="<?php echo $this->get_field_id('skiptime')  . '_' ?>" name="<?php echo $this->get_field_name('skiptime') ?>" value="0" />
			<input type="checkbox" id="<?php echo $this->get_field_id('skiptime') ?>" name="<?php echo $this->get_field_name('skiptime') ?>" value="1"<?php echo $skiptime ? ' checked' : ''; ?> />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('year') ?>"><?php _e('Special Date', $this->domain) ?></label><br />
			<input type="text" id="<?php echo $this->get_field_id('year') ?>" name="<?php echo $this->get_field_name('year') ?>" value="<?php echo $year ?>" size="4" /><?php _e('Year', $this->domain) ?>&nbsp;
			<input type="text" id="<?php echo $this->get_field_id('month') ?>" name="<?php echo $this->get_field_name('month') ?>" value="<?php echo $month ?>" size="2" /><?php _e('Month', $this->domain) ?>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('class') ?>"><?php _e('Class Name', $this->domain) ?></label><br />
			<input type="text" id="<?php echo $this->get_field_id('class') ?>" name="<?php echo $this->get_field_name('class') ?>" value="<?php echo $class ?>" />
		</p>

<?php
	}

	/**
	 * Front-end display of widget
	 *
	 */
	public function widget($args, $instance) {
		global $mts_simple_booking;

		$this->_set_script();

		extract($args);

		$title = apply_filters('widget_title',
			empty($instance['title']) ? __('Booking Calendar', $this->domain) : $instance['title'], $instance, $this->id_base);

		// 客室タイプまたはプランの指定確認
		$param = array(
			'id' => (isset($instance['post_id']) ? $instance['post_id'] : 0),
			'year' => $instance['year'],
			'month' => $instance['month'],
			'caption' => $instance['caption'],
			'link' => $instance['link'],
			'pagination' => $instance['pagination'],
			'skiptime' => $instance['skiptime'],
			'class' => (empty($instance['class']) ? 'booking-calendar' : $instance['class']),
			'href' => '',
		);

		echo $before_widget;
		if ($title) {
			echo $before_title . $title . $after_title;
		}

?>
	<div class="mtssb-calendar-widget" style="position: relative">
		<?php echo $mts_simple_booking->monthly_calendar($param) ?>

		<div class="ajax-calendar-loading-img" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%">
			<img src="<?php echo $mts_simple_booking->plugin_url . "image/ajax-loaderf.gif" ?>" style="height:24px; width:24px; position:absolute; top:50%; left:50%; margin-top:-12px; margin-left:-12px;" />
		</div>
	</div>

	<div class="mtsbb-widget-calendar-params" style="display:none">
		<div class="mtssb-calendar-widget-nonce"><?php echo wp_create_nonce(self::GET_BOOKING) ?></div>
		<div class="mtssb-ajaxurl"><?php echo admin_url('admin-ajax.php') ?></div>
		<div class="mtssb-calendar-widget-pid"><?php echo esc_html($param['id']) ?></div>
		<div class="mtssb-calendar-widget-param"><?php echo urlencode(serialize($param)) ?></div>
	</div>
<?php

		echo $after_widget;
	}

	/**
	 * When this widget is activated, set including javascript
	 *
	 */
	private function _set_script() {
		global $mts_simple_booking;

		if (is_active_widget(false, false, self::BASE_ID)) {
			wp_enqueue_script("mtssb_calendar_widget_js", $mts_simple_booking->plugin_url . self::JS_PATH, array('jquery'));
		}
	}

	/**
	 * Ajax
	 *
	 */
	static public function get_booking_calendar() {
		global $mts_simple_booking;

		// Check nonce
		if (!wp_verify_nonce($_POST['nonce'], self::GET_BOOKING)) {
			exit();
		}

		$params = unserialize(urldecode($_POST['param']));

		// 予約カレンダー生成モジュールのインスタンス化
		if (!class_exists('MTSSB_Front')) {
			require_once(dirname(__FILE__) . '/mtssb-front.php');
		}
		$oFront = new MTSSB_Front;

		echo $oFront->monthly_calendar($params);

		exit();
	}

}
