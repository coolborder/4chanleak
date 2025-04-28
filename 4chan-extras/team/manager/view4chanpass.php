<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}

require_once '../lib/csp.php';

require_once 'lib/ini.php';

load_ini("$configdir/payments_config.ini");
finalize_constants();

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'search',
      'update_email',
      'send_renewal_email',
      'coinbase_charges',
      'paypal_sales',
      'coinbase_confirm',
      'coinbase_view',
      'revoke'
    ),
    
    $status_str = array(
      '0' => 'Active',
      '1' => 'Expired',
      '2' => 'Refunded',
      '3' => 'Disputed',
      '4' => 'Banned (Spam)',
      '5' => 'Banned (Illegal)',
      '6' => 'Pending Creation',
      '7' => 'Pending (Batch)'
    )
  ;
  
  const RESULT_LIMIT = 1000;
  
  const TPL_ROOT = '../views/';
  
  const CHARGE_STATUS_CONFIRMED = 'CONFIRMED';
  const CHARGE_STATUS_UNRESOLVED = 'UNRESOLVED';
  
  const COINBASE_FORCE_CONFIRM_SECRET = 'b1a9b46d5e2d8320baa4c8b1e38dcdf1';
  
  const MAX_ADJUST_MONTHS = 50; // maximum pass duration adjustment in months for over/underpayments
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  final protected function success($redirect = null) {
    $this->redirect = $redirect;
    $this->renderHTML('success');
    die();
  }

  final protected function error($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  /**
   * Returns the data as json
   */
  final protected function successJSON($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
    die();
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function errorJSON($message = null) {
    $payload = array('status' => 'error', 'message' => $message);
    $this->renderJSON($payload);
    die();
  }
  
  /**
   * Returns a JSON response
   */
  private function renderJSON($data) {
    header('Content-type: application/json');
    echo json_encode($data);
  }
  
  private function format_time($time) {
    $time = strtotime($time);
    return date('Y-m-d H:i:s', $time);
  }
  
  /**
   * Send reminder emails
   */
  public function send_email($email, $token, $pending_id) {
    $subject = "Your 4chan Pass has expired!";
    $message =<<<MSG
Your 4chan Pass (Token: $token) has expired.

In order to continue posting without typing a CAPTCHA, you must renew your Pass. Renewing your 
Pass will add 12 additional months from the date of your renewal payment.

You can renew your Pass by visiting the following link: https://www.4channel.org/pass?renew=$pending_id

If you have any questions or problems renewing, please e-mail 4chanpass@4chan.org

Thanks for your support!
MSG;
    
    // From:
    $headers = "From: 4chan Pass <4chanpass@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Envelope
    $opts = '-f 4chanpass@4chan.org';
    
    return mail($email, $subject, $message, $headers, $opts);
  }

  /**
   * Coinbase API request
   */
  private function coinbase_api_request($action, $post_data = null, $method = null) {
    $curl = curl_init();
    
    $url = "https://api.commerce.coinbase.com/$action";
    
    $headers = array(
      'Content-Type: application/json',
      'X-CC-Api-Key: ' . COINBASE_API_KEY,
      'X-CC-Version: 2018-03-22'
    );
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
    if ($post_data) {
      $post_data = json_encode($post_data);
      curl_setopt($curl, CURLOPT_POST , 1);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    
    if ($method !== null) {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_USERAGENT, '4chan.org');
    
    $resp = curl_exec($curl);
    
    if ($resp === false) {
      $this->error('API Error: curl returned false');
    }
    
    $resp_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if ($resp_status >= 300) {
      $this->error('API Error: ' . $resp);
    }
    
    $resp_json = json_decode($resp, true);
    /*
    if (isset($resp_json['warnings'])) {
      $this->error('API Warnings: ' . json_encode($resp_json['warnings']));
    }
    */
    return $resp_json;
  }
  
  private function get_coinbase_charge($charge_code) {
    $charge = $this->coinbase_api_request("charges/$charge_code");
    
    if (!$charge) {
      return false;
    }
    
    if (!isset($charge['data'])) {
      return false;
    }
    
    return $charge['data'];
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function get_days_from_payment($payed, $to_pay) {
    $delta = (float)$payed - (float)$to_pay;

    $day_price = (float)$to_pay / 12.0;

    $days = (int)round($delta / $day_price);

    return $days;
  }
  
  /**
   * Index
   */
  public function index() {
    $sql = <<<SQL
SELECT user_hash, email, gift_email, purchase_date
FROM pass_users ORDER BY purchase_date DESC LIMIT 20
SQL;
    
    $res = mysql_global_call($sql);
    
    $this->recent_passes = array();
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        $this->recent_passes[] = $row;
      }
    }
    
    $this->renderHTML('view4chanpass');
  }
  
  /**
   * Confirms a coinbase charge and creates a Pass (remotely)
   */
  public function coinbase_confirm() {
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->errorJSON('Invalid or expired OTP.');
    }
    
    if (!isset($_POST['charge_id'])) {
      $this->errorJSON('Bad request');
    }
    
    $charge_id = $_POST['charge_id'];
    
    if (!$charge_id) {
      $this->errorJSON('Charge ID cannot be empty.');
    }
    
    if (isset($_POST['adjust_months'])) {
      $adjust_months = (int)$_POST['adjust_months'];
      
      if (abs($adjust_months) > self::MAX_ADJUST_MONTHS) {
        $this->errorJSON('Adjusted duration is too big.');
      }
    }
    else {
      $adjust_months = null;
    }
    
    set_time_limit(60);
    
    $curl = curl_init();
    
    $url = "https://www.4chan.org/payments/coinbase.php?action=force_confirm";
    
    $headers = array(
      'X-Callback-Secret: ' . self::COINBASE_FORCE_CONFIRM_SECRET
    );
    
    $post_fields = "charge_id=$charge_id";
    
    if ($adjust_months) {
      $post_fields .= "&adjust_months=$adjust_months";
    }
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_USERAGENT, '4chan.org');
    
    $resp = curl_exec($curl);
    
    if ($resp === false) {
      $this->errorJSON('API Error: curl returned false');
    }
    
    if ($resp === '1') {
      $this->successJSON();
    }
    else {
      $this->errorJSON($resp);
    }
  }
  
  /**
   * Update email
   */
  public function update_email() {
    if (!isset($_POST['tid']) || !isset($_POST['token']) || !isset($_POST['otp'])) {
      $this->errorJSON('Bad Request.');
    }
    
    if (!$_POST['tid'] || !$_POST['token']) {
      $this->errorJSON('Bad Request.');
    }
    
    if (!isset($_POST['new_email']) || !$_POST['new_email']) {
      $this->errorJSON('New E-mail cannot be empty.');
    }
    
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->errorJSON('Invalid OTP.');
    }
    
    $transaction_id = $_POST['tid'];
    $user_hash = $_POST['token'];
    
    $new_email = strtolower(trim($_POST['new_email']));
    
    $query = "SELECT * FROM pass_users WHERE transaction_id = '%s' AND user_hash = '%s'";
    
    $res = mysql_global_call($query, $transaction_id, $user_hash);
    
    if (!$res) {
      $this->errorJSON('Database Error (1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->errorJSON('Pass Not Found.');
    }
    
    if (mysql_num_rows($res) > 1) {
      $this->errorJSON('Internal Server Error (ue1).');
    }
    
    $query = "UPDATE pass_users SET email = '%s' WHERE transaction_id = '%s' AND user_hash = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $new_email, $transaction_id, $user_hash);
    
    if (!$res) {
      $this->errorJSON('Database Error (2).');
    }
    
    if (mysql_affected_rows() < 1) {
      $this->errorJSON('Nothing changed.');
    }
    
    $this->successJSON(array('new_email' => $new_email));
  }
  
  /**
   * Search
   */
  public function search() {
    if (!isset($_GET['q'])) {
      $this->error('Bad request');
    }
    
    $q = mysql_real_escape_string($_GET['q']);
    
    if (!$q) {
      $this->error('Query cannot be empty');
    }
    
    $like_email = str_replace(array('%', '_'), array("\%", "\_"), $q);
    
    if (preg_match('/^[0-9]{1,3}\.([0-9]{1,3}\.)?([0-9]{1,3}\.)?([0-9]{1,3})?\*$/', $q)) {
      $ip_val = str_replace('*', '%', $q);
      $ip_clauses = " LIKE '$ip_val%'";
    }
    else {
      $ip_clauses = " = '$q'";
    }
    
    $lim = self::RESULT_LIMIT + 1;
    
    $query = <<<SQL
SELECT *
FROM pass_users
WHERE (user_hash = '$q'
OR transaction_id = '$q'
OR customer_id = '$q'
OR email LIKE '%$like_email%'
OR gift_email LIKE '%$like_email%'
OR last_ip $ip_clauses
OR registration_ip $ip_clauses)
ORDER BY purchase_date DESC
LIMIT $lim
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    $count = mysql_num_rows($res);
    
    if (!$count) {
      $this->error('Nothing found.');
    }
    
    $this->too_many_results = $count > self::RESULT_LIMIT;
    
    $this->passes = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->passes[] = $row;
    }
    
    $this->renderHTML('view4chanpass');
  }
  
  /**
   * Search coinbase charges
   */
  public function coinbase_charges() {
    if (!isset($_GET['q'])) {
      $this->error('Bad request');
    }
    
    $q = preg_replace('/^coinbase_/', '', $_GET['q']);
    
    $q = mysql_real_escape_string($q);
    
    if (!$q) {
      $this->error('Query cannot be empty');
    }
    
    $like_email = str_replace(array('%', '_'), array("\%", "\_"), $q);
    
    $query = <<<SQL
SELECT id, charge_code, status, email, gift_email, ip, usd_price, country,
renewal_id, created_on
FROM pass_coinbase_charges
WHERE charge_code = '$q' OR email LIKE '%$like_email%'
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    if (mysql_num_rows($res) == 0) {
      $this->error('Nothing found.');
    }
    
    $this->passes = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->passes[] = $row;
    }
    
    $this->renderHTML('view4chanpass-coinbase');
  }
  
  /**
   * Search PayPal sales
   */
  public function paypal_sales() {
    if (!isset($_GET['q'])) {
      $this->error('Bad request');
    }
    
    $q = mysql_real_escape_string($_GET['q']);
    
    if (!$q) {
      $this->error('Query cannot be empty');
    }
    
    $like_email = str_replace(array('%', '_'), array("\%", "\_"), $q);
    
    $query = <<<SQL
SELECT id, order_id, payer_id, payment_id, status, email, gift_email, ip, usd_price, country,
renewal_id, created_on
FROM pass_paypal_sales
WHERE order_id = '$q' OR payer_id = '$q' OR payment_id = '$q' OR email LIKE '%$like_email%'
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    if (mysql_num_rows($res) == 0) {
      $this->error('Nothing found.');
    }
    
    $this->passes = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->passes[] = $row;
    }
    
    $this->renderHTML('view4chanpass-paypal');
  }
  
  /**
   * View coinbase charge
   */
  public function coinbase_view() {
    if (!isset($_GET['q'])) {
      $this->error('Bad request');
    }
    
    $q = $_GET['q'];
    
    if (!$q) {
      $this->error('Query cannot be empty');
    }
    
    $query = <<<SQL
SELECT *
FROM pass_coinbase_charges
WHERE charge_code = '%s'
SQL;
    
    $res = mysql_global_call($query, $q);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    if (mysql_num_rows($res) == 0) {
      $this->error('No entry for this charge ID (4chan)');
    }
    
    $this->our_charge = mysql_fetch_assoc($res);
    
    $this->charge = $this->get_coinbase_charge($q);
    
    if (!$this->charge) {
      $this->error('No entry for this charge ID (Coinbase)');
    }
    
    $this->renderHTML('view4chanpass-coinbase');
  }
  
  public function revoke() {
    if (!isset($_POST['tid']) || !isset($_POST['token'])) {
      $this->errorJSON('Bad request');
    }
    
    if ($_POST['tid'] === '' || $_POST['token'] === '') {
      $this->errorJSON('Bad request');
    }
    
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->errorJSON('Invalid or expired OTP.');
    }
    
    if (isset($_POST['illegal']) && $_POST['illegal']) {
      $status = 5; // Violating US law
    }
    else {
      $status = 4; // Spam
    }
    
    $query = <<<SQL
UPDATE pass_users SET status = $status
WHERE status = 0 AND transaction_id = '%s' AND user_hash = '%s'
LIMIT 1
SQL;
    
    $res = mysql_global_call($query, $_POST['tid'], $_POST['token']);
    
    if (!$res) {
      $this->errorJSON('Database error');
    }
    
    $this->successJSON();
  }
  
  /**
   * Manually resend the expiration notice
   */
  public function send_renewal_email() {
    if (!isset($_POST['token'])) {
      $this->errorJSON('Bad request');
    }
    
    if ($_POST['token'] === '') {
      $this->errorJSON('Bad request');
    }
    
    $user_hash = $_POST['token'];
    
    $query = <<<SQL
SELECT email, gift_email, pending_id, user_hash FROM pass_users
WHERE status = 1 AND pin != '' AND expiration_date < NOW()
AND email_expired_sent = 1
AND user_hash = '%s'
SQL;
    
    $res = mysql_global_call($query, $user_hash);
    
    if (!$res) {
      $this->errorJSON('Database error (1)');
    }
    
    if (mysql_num_rows($res) !== 1) {
      $this->errorJSON('Pass not found / Too many entries for token');
    }
    
    $pass = mysql_fetch_assoc($res);
    
    if (!$pass) {
      $this->errorJSON('Database error (2)');
    }
    
    if ($pass['gift_email'] !== '') {
      $owner_email = $pass['gift_email'];
    }
    else {
      $owner_email = $pass['email'];
    }
    
    set_time_limit(0);
    
    $status = $this->send_email($owner_email, $pass['user_hash'], $pass['pending_id']);
    
    if (!$status) {
      $this->errorJSON('Mail rejected');
    }
    
    $this->successJSON();
  }
  
  /**
   * Main
   */
  public function run() {
    $method = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (isset($method['action'])) {
      $action = $method['action'];
    }
    else {
      $action = 'index';
    }
    
    if (in_array($action, $this->actions)) {
      $this->$action();
    }
    else {
      $this->error('Bad request');
    }
  }
}

$ctrl = new App();
$ctrl->run();
