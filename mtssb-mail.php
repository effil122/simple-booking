<?php
/**
 * MTS Simple Booking メール送信処理モジュール
 *
 * @Filename	mtssb-mail.php
 * @Date		2012-05-17
 * @Author		S.Hayashi
 *
 * Updated to 1.1.5 on 2012-12-04
 */

class MTSSB_Mail {
	const VERSION = '1.1.5';


	/**
	 * Common private valiable
	 */
	private $domain;

	private $shop;
	private $formshop;

	/**
	 * Constructor
	 *
	 */
	public function __construct() {

		$this->domain = MTS_Simple_Booking::DOMAIN;

		// 施設情報の読み込み
		$this->shop = get_option($this->domain . '_premise');

		// メールのフロム情報
		$this->fromshop = "From: {$this->shop['name']} <{$this->shop['email']}>\n";
	}

	/**
	 * 予約登録のメール
	 *
	 */
	public function booking_mail() {
		global $mts_simple_booking;

		$booking = $mts_simple_booking->oBooking_form->getBooking();

		// 予約IDの生成
		$reserve_id = date('ymd', $booking['booking_time']) . substr("00{$booking['booking_id']}", -3);

		// 予約者情報
		$adda = array($booking['client']['name'], $reserve_id);

		// メールテンプレートの読込み
		$template = get_option($this->domain . '_reserve');

		// メール文生成
		$body = $this->_booking_content($booking, $template);

		// クライアント
		$subject = $template['title'];
		$client_content = $this->_replace_variable($template['header'], $adda) . $body . $this->_replace_variable($template['footer'], $adda);
		$client_to = $booking['client']['email'];
		if (!empty($client_to)) {
			$client_ret = wp_mail($client_to, $subject, $client_content, $this->fromshop);

		//$body フォームのデータまとめ　_booking_content　の中にデータ存在
			// require_once __DIR__ . '/vendor/autoload.php';
			
			// $google_key =  __DIR__ . '/rugged-practice-303002-0661d79ae090.json';//取得したサービスキーのパスを指定
			// $google_client = new Google_Client();//Googleクライアントインスタンスを作成
			// $google_client->setScopes([//スコープを以下の内容でセット
			// 	\Google_Service_Sheets::SPREADSHEETS,
			// 	\Google_Service_Sheets::DRIVE,]);
			// $google_client->setAuthConfig($google_key);//サービスキーをセット
			
			// $sheet = new Google_Service_Sheets($google_client);//シートを操作するインスタンス
			// $sheet_id = '19x4fRf4JM62IfmtGdVaRL-QWbF0BgVJXOHwhh-qr_Ak';//対象のスプレッドシートのIDを指定
			// $range = 'シート1!A'.($booking['booking_id']+1).':G';//対象のスプレッドシートで書き込む範囲を指定（今回の場合、sheet1）
			
			// var_dump($booking );
			global $mts_simple_booking;
			$controls = $mts_simple_booking->oBooking_form->getControls();
			$article = $mts_simple_booking->oBooking_form->getArticle();
			$date = date('Y/n/j H:i', $booking['booking_time']);
			var_dump($booking);
			// var_dump($article);
			// var_dump($date);
// 			print_r($article);
			$client = &$booking['client'];
			$values = [
				[$booking['booking_id'],$date,$client['name'],$client['furigana'],$client['email'],$client['tel'],$client['adult']]
			];			
			// //書き込む内容を収めた配列をbodyに格納
			// $google_body = new Google_Service_Sheets_ValueRange(['values' => $values,]);
			// $sheet->spreadsheets_values->update($sheet_id, $range, $google_body, ["valueInputOption" => 'USER_ENTERED']);

	}

		// 自社
		$myheader = date_i18n('Y年n月j日 H:i') . "\n予約ID：{$reserve_id}\n\n";
		//$my_title = 'Webから予約を受け付けました';
		$my_content = $this->_replace_variable($myheader, $adda) . $body;
		$my_to = $this->shop['email'];
		if (!empty($my_to)) {
			$my_ret = wp_mail($my_to, $subject, $my_content, $this->fromshop);
		}

		// 携帯
		if (!empty($this->shop['mobile'])) {
			$mobile_ret = wp_mail($this->shop['mobile'], $subject, $myheader, $this->fromshop);
		}

		if (!$client_ret || !$my_ret) {
			return false;
		}

		return true;
	}

	/**
	 * メール文内変数を変換して戻す
	 *
	 */
	private function _replace_variable($str, $adda=array()) {
		$search = array(
			'%CLIENT_NAME%',
			'%RESERVE_ID%',
			'%NAME%',
			'%POSTCODE%',
			'%ADDRESS%',
			'%TEL%',
			'%FAX%',
			'%EMAIL%',
			'%WEB%',
		);

		$replace = array_merge($adda, array(
			$this->shop['name'],
			$this->shop['postcode'],
			$this->shop['address1'] . ($this->shop['address2'] ? "\n{$this->shop['address2']}" : ''),
			$this->shop['tel'],
			$this->shop['fax'],
			$this->shop['email'],
			$this->shop['web'],
		));

		return str_replace($search, $replace, $str);
	}


	/**
	 * 予約メールの本文生成
	 *
	 */
	private function _booking_content($booking, $template) {
		global $mts_simple_booking;

		$controls = $mts_simple_booking->oBooking_form->getControls();
		$article = $mts_simple_booking->oBooking_form->getArticle();
		$client = &$booking['client'];

		$body = apply_filters('booking_form_number_title', '[ご予約]', 'mail') . "\n"
		 . "{$article['name']}\n"
		 . apply_filters('booking_form_date_title', '日時：') . apply_filters('booking_form_date', date('Y年n月j日 H:i', $booking['booking_time']), $booking['booking_time']) ."\n"
		 . apply_filters('booking_form_date_number', '人数：');

		foreach ($controls['count'] as $key => $val) {
			if (0 < $client[$key]) {
				$body .= apply_filters('booking_form_count_label', __(ucwords($key), $this->domain)) . " $client[$key]" . ($key == 'car' ? '台' : '名') . ', ';
			}
		}
		if (substr($body, -2) == ', ') {
			$body = substr($body, 0, -2);
		}
		$body .= "\n";

		// 連絡先
		$column = $template['column'];
		$body .= "\n" . apply_filters('booking_form_client_title', '[連絡先]', 'mail') . "\n";
		if (0 < $column['company']) {
			$body .= apply_filters('booking_form_company', '会社名') . "：{$client['company']}\n";
		}
		if (0 < $column['name']) {
			$body .= apply_filters('booking_form_name', '名前') . "：{$client['name']}\n";
		}
		if (0 < $column['furigana']) {
			$body .= apply_filters('booking_form_furigana', 'フリガナ') . "：{$client['furigana']}\n";
		}
		if (0 < $column['email']) {
			$body .= apply_filters('booking_form_email', 'E-Mail') . "：{$client['email']}\n";
		}
		if (0 < $column['postcode']) {
			$body .= apply_filters('booking_form_postcode', '郵便番号') . "：{$client['postcode']}\n";
		}
		if (0 < $column['address']) {
			$body .= apply_filters('booking_form_address', '住所') . "：{$client['address1']}";
			if (!empty($client['address2'])) {
				$body .= " {$client['address2']}";
			}
			$body .= "\n";
		}
		if (0 < $column['tel']) {
			$body .= apply_filters('booking_form_tel', '電話番号') . "：{$client['tel']}\n";
		}

		// 連絡事項
		if (!empty($booking['note'])) {
			$body .= "\n" . apply_filters('booking_form_message_title', '[連絡事項]', 'mail') . "\n";
			$body .= $this->_form_message($booking['note']);
		}

		$body .= "\n\n";

		return $body;
	}

	/**
	 * 入力メッセージの幅を整形する
	 *
	 */
	private function _form_message($message) {

		// 改行文字を\nに統一する
		$message = preg_replace("/(\r\n|\r)/", "\n", $message);

		// 行を切り出す
		$strs = mb_split("\n", $message);

		$formed = '';
		// 各行を72桁幅にする
		foreach ($strs as $str) {
			while (72 < mb_strwidth($str)) {
				$strw = mb_strimwidth($str, 0, 73, "\n");
				$formed .= $strw;
				$str = mb_substr($str, mb_strlen($strw) - 1);
			}
			$formed .= $str . "\n";
		}

		return $formed;
	}
}