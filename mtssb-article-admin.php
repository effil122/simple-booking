<?php
if (!class_exists('MTSSB_Article')) {
	require_once('mtssb-article.php');
}
/**
 * カスタムポストタイプ予約品目 管理画面処理
 *
 * @Filename	mtssb-article-admin.php
 * @Date		2012-04-19
 * @Author		S.Hayashi
 *
 * Updated to 1.0.2 on 2012-10-09
 */
class MTSSB_Article_Admin extends MTSSB_Article {
	const VERSION = '1.0.2';

	/**
	 * Private Variables
	 */
	private $module_name;		// mtssb-article-admin
	private $nonce_name;		// mtssb-article-admin_nonce
	private $nonce_timetable;	// mtsbb-article-admin_nonce_timetable

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct();

		// Set nonce variables
		$this->module_name = basename(__FILE__, '.php');
		$this->nonce_name = $this->module_name . '_nonce';
		$this->nonce_timetable = $this->nonce_name . '_timetable';

		// Register fook procedure to save custom fields
		add_action('save_post', array($this, 'save_custom_fields'));

		// Register fook procedure to display MTSBB Room list view
		add_filter("manage_edit-" . self::POST_TYPE . "_columns", array($this, 'get_column_titles'));

		// カスタム投稿タイプのedit.phpに表示するカスタムカラム処理のフック
		add_action('manage_posts_custom_column', array($this, 'out_custom_column'));

		// Load JavaScript at post.php
		add_action("admin_print_scripts-post.php", array($this, 'post_enqueue_script'));
		add_action("admin_print_scripts-post-new.php", array($this, 'post_enqueue_script'));

		// AJAX登録
		add_action('wp_ajax_mtssb_get_timetable', array($this, 'ajax_get_the_timetable'));
	}

	/**
	 * Enqueue scripts to load JavaScript at post.php
	 *
	 */
	public function post_enqueue_script() {
		global $post;

		if ($post->post_type == self::POST_TYPE) {
			wp_enqueue_script($this->module_name . '-js', plugin_dir_url(__FILE__) . "js/mtssb-article-admin.js", array('jquery'));
		}
	}

	/**
	 * Add Meta Box of Room's Custom fields
	 *
	 */
	public function register_meta_box() {
		add_meta_box($this->module_name . '_timetable', __('Booking Timetable', $this->domain),
			array($this, 'meta_box_timetable'), self::POST_TYPE, 'normal', 'low');
		add_meta_box($this->module_name . '_provision', __('Booking Provisions', $this->domain), 
			array($this, 'meta_box_provision'), self::POST_TYPE, 'normal', 'low');
	}

	/**
	 * 予約の時間割編集画面
	 *
	 */
	public function meta_box_timetable($post) {

		$timetable = self::get_the_timetable($post->ID);

		if (empty($timetable)) {
			$timetable = array();
		}

		ob_start();
?>
	<table class="form-table">
		<tr>
			<th valign="top" scope="row"><label><?php _e('Start Time', $this->domain) ?></label></th>
			<td>
				<select id="timetable-hour" name="start[hour]">
					<?php echo $this->_out_option_hour() ?>
				</select><?php _e('Hour', $this->domain) ?>
				<select id="timetable-minute" name="start[minute]">
					<?php echo $this->_out_option_minute(0, 10) ?>
				</select><?php _e('Minute', $this->domain) ?> <a id="add-timetable" class="add-timetable button" href="javascript:void(0)" onclick="timeop.add(this)"><?php _e('Add time', $this->domain) ?></a>
				<p class="article-description"><?php _e('The reservation time of this reservation item.', $this->domain) ?></p>
				<div id="article-timetable">
					<input type="hidden" name="article[timetable]" value="" />
					<ul id="article-list">
						<?php 
						if (empty($timetable)) {
								echo '<li><input type="hidden" name="article[timetable][36000]" value="36000" />10:00 <a href="javascript:void(0)" onclick="timeop.delete(this)"> ' . __('Delete') . "</a></li>\n";
							} else {
								foreach ($timetable as $time) {
									echo "<li><input type=\"hidden\" name=\"article[timetable][$time]\" value=\"$time\" />" . date('H:i ', $time)
									 . ' <a href="javascript:void(0)" onclick="timeop.delete(this)"> ' . __('Delete') . "</a></li>\n";
								}
							} ?>
					</ul>
					<div id="delete-title" style="display:none"><?php _e('Delete') ?></div>
				</div>
			</td>
		</tr>
	</table>

<?php
		ob_end_flush();
	}

	/**
	 * 予約品目の予約条件編集画面
	 *
	 */
	public function meta_box_provision($post) {

		$article = self::get_the_article($post->ID);
		if (empty($article) || $article['post_status'] == 'auto-draft') {
			$article = $this->get_new_article();
		}

		ob_start();
?>
	<input type="hidden" name="<?php echo $this->nonce_name ?>" value="<?php echo wp_create_nonce($this->module_name) ?>" />

	<table class="form-table">
		<tr>
			<th valign="top" scope="row"><label for="article-restriction"><?php _e('Restriction', $this->domain) ?></label></th>
			<td>
				<select id="article-restriction" name="article[restriction]">
					<option value="capacity"<?php echo $article['restriction'] == 'capacity' ? ' selected="selected"' : '' ?>><?php _e('Capacity', $this->domain) ?></option>
					<option value="quantity"<?php echo $article['restriction'] == 'quantity' ? ' selected="selected"' : '' ?>><?php _e('Quantity', $this->domain) ?></option>
				</select>
				<p class="article-description"><?php _e('Choose the conditions which restrict reservation.', $this->domain) ?></p>
			</td>
		</tr>
		<tr>
			<th valign="top" scope="row"><label for="article-capacity"><?php _e('Fixed Number', $this->domain) ?></label></th>
			<td>
				<input type="text" id="article-capacity" class="small-text" name="article[capacity]" value="<?php echo $article['capacity'] ?>" /> <?php _e('Persons', $this->domain) ?>
				<p class="article-description"><?php _e('The maximum number which accepts this reservation item.', $this->domain) ?></p>
			</td>
		</tr>
		<tr>
			<th valign="top" scope="row"><label for="article-quantity"><?php _e('Booking Limit', $this->domain) ?></label></th>
			<td>
				<input type="text" id="article-quantity" class="small-text" name="article[quantity]" value="<?php echo $article['quantity'] ?>" /> <?php _e('Items', $this->domain) ?>
				<p class="article-description"><?php _e('The maximum reservation number which accepts reservation.', $this->domain) ?></p>
			</td>
		</tr>
		<tr>
			<th valign="top" scope="row"><label for="article-minimum"><?php _e('Minimum Entry', $this->domain) ?></label></th>
			<td>
				<input type="text" id="article-minimum" class="small-text" name="article[minimum]" value="<?php echo $article['minimum'] ?>" /> <?php _e('Persons', $this->domain) ?>
				<p class="article-description"><?php _e('The minimum acceptance number required for one reservation.', $this->domain) ?></p>
			</td>
		</tr>
		<tr>
			<th valign="top" scope="row"><label for="article-maximum"><?php _e('Maximum Entry', $this->domain) ?></label></th>
			<td>
				<input type="text" id="article-maximum" class="small-text" name="article[maximum]" value="<?php echo $article['maximum'] ?>" /> <?php _e('Persons', $this->domain) ?>
				<p class="article-description"><?php _e('The number in which the maximum acceptance of one reservation is possible.', $this->domain) ?></p>
			</td>
		</tr>
		<tr>
			<th valign="top" scope="row"><label for="article-count-adult"><?php _e('Count Rate', $this->domain) ?></label></th>
			<td>
				<?php _e('Adult: ', $this->domain) ?> <input type="text" id="article-count-adult" class="small-text" name="article[count][adult]" value="<?php echo sprintf('%.1f', $article['count']['adult']) ?>" /><br />
				<?php _e('Child: ', $this->domain) ?> <input type="text" id="article-count-child" class="small-text" name="article[count][child]" value="<?php echo sprintf('%.1f', $article['count']['child']) ?>" /><br />
				<?php _e('Baby: ', $this->domain) ?> <input type="text" id="article-count-baby" class="small-text" name="article[count][baby]" value="<?php echo sprintf('%.1f', $article['count']['baby']) ?>" />
				<p class="article-description"><?php _e('Calculation rates when calculating the number.', $this->domain) ?></p>
				<input type="hidden" name="article[addition]" value="0" />
			</td>
		</tr>
	</table>

<?php
		ob_end_flush();
	}

	/**
	 * 時間選択のoptionタグ列出力
	 *
	 * $the_time	unix time
	 */
	private function _out_option_hour($the_time='') {
		$the_hour = $the_time == '' ? '10' : date('H', intval($the_time));

		$out = '';
		for ($hour = 0; $hour <= 23; $hour++) {
			$out .= "<option value=\"" . sprintf("%02d", $hour) . "\""
				. ($hour==$the_hour ? " selected=\"selected\"" : '') . ">"
				. sprintf("%02d", $hour) . "</option>\n";
		}

		return $out;
	}

	/**
	 * 分選択のoptionタグ列出力
	 *
	 * $the_time	unix time
	 */
	private function _out_option_minute($the_time=0, $min_step=10) {
		$the_minute = date('i', intval($the_time));

		$out = '';
		for ($minute = 0; $minute <= 59; $minute += $min_step) {
			$out .= "<option value=\"" . sprintf("%02d", $minute) . "\""
				. ($minute==$the_minute ? " selected=\"selected\"" : '') . ">"
				. sprintf("%02d", $minute) . "</option>\n";
		}

		return $out;
	}

	/**
	 * Save custom fields
	 *
	 */
	public function save_custom_fields($post_id) {

		// Check capability and post type
		if (!current_user_can('edit_page', $post_id)) {
			return;
		} else if (!isset($_POST['post_type']) || self::POST_TYPE != $_POST['post_type']) {
			return;
		}

		// Check nonce
		if (!isset($_POST[$this->nonce_name]) || !wp_verify_nonce($_POST[$this->nonce_name], $this->module_name)) {
			return;
		}

		// Check auto save
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// 予約品目フィールド名として利用
		$fields = $this->get_new_article();

		// Save Plan data
		$article = isset($_POST['article']) ? $_POST['article'] : array();

		foreach ($fields as $column => $val) {
			$old = get_post_meta($post_id, $column, true);
			$new = $val;

			switch ($column) {
				case 'timetable':
					if (is_array($article[$column])) {
						$new = array();
						foreach ($article[$column] as $key => $time) {
							$new[] = intval($time);
						}
					} else {
						$new = '';
					}
					break;
				case 'restriction':
					$new = $article[$column] == 'capacity' ? $article[$column] : 'quantity';
					break;
				case 'capacity':
				case 'quantity':
				case 'minimum':
				case 'maximum':
					$new = intval($article[$column]);
					break;
				case 'count':
					$new = array(
						'adult' => (preg_match('/^[0-9](.[0-9]|)$/', $article[$column]['adult']) ? $article[$column]['adult'] : '0.0'),
						'child' => (preg_match('/^[0-9](.[0-9]|)$/', $article[$column]['child']) ? $article[$column]['child'] : '0.0'),
						'baby' => (preg_match('/^[0-9](.[0-9]|)$/', $article[$column]['baby']) ? $article[$column]['baby'] : '0.0'),
					);
					break;
				case 'addition':
					$new = $article[$column] == 1 ? 1 : 0;
					break;
				default:
					break;
			}

			if ($old != $new || $new == '') {
				update_post_meta($post_id, $column, $new);
			}
		}
	}

	/**
	 * Column title for list view
	 *
	 */
	public function get_column_titles() {
		return array(
			'cb' => '<input type="checkbox" />',
			'title' => __('Article Name', $this->domain),
			'timetable' => __('Timetable', $this->domain),
			'restriction' => __('Restriction', $this->domain),
			'capacity' => __('Fixed Number', $this->domain),
			'quantity' => __('Booking Limit', $this->domain),
			'minimum' => __('Minimum Entry', $this->domain),
			'maximum' => __('Maximum Entry', $this->domain),
		);
	}

	/**
	 * Output the custom column value
	 *
	 */
	public function out_custom_column($column) {
		global $post;

		if ($post->post_type != self::POST_TYPE) {
			return false;
		} else {
			$val = get_post_meta($post->ID, $column, true);
			if ($column == 'timetable' && is_array($val)) {
				foreach ($val as $key => &$time) {
					$time = date('H:i', $time);
				}
				$val = implode(',', $val);
			} else if ($column == 'restriction') {
				$val = __(ucwords($val), $this->domain);
			}
			echo $val;
		}
	}

	/**
	 * 時間割AJAX取得
	 *
	 */
	public function ajax_get_the_timetable() {

		// Check nonce
		check_ajax_referer($this->domain . '_ajax', 'nonce');

		$article_id = intval($_POST['article_id']);

		$timetable = self::get_the_timetable($article_id);

		if (!empty($timetable)) {
			$options = '';
			foreach ($timetable as $time) {
				$options .= "<option value=\"$time\">" . date('H:i', $time) . '</option>';
			}
		} else {
			$options = '<option value="">' . __('Nothing', $this->domain) . '</option>';
		}

		echo $options;

		exit();
	}

}