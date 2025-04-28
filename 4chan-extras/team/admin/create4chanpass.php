<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}
/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}
*/
require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'create',
      'batches',
      'create_batch',
      'export_batch'/*,
      'create_table'*/
    )
  ;
  
  const MAX_BATCH_SIZE = 5000;
  
  const DATE_FORMAT ='m/d/y H:i';
  
  const EXEC_LIMIT = 300;
  
  const NORMAL_STATUS = 6;
  const PRE_STATUS = 7;
  
  const TPL_ROOT = '../views/';
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  final protected function success($redirect = null, $no_exit = false) {
    $this->redirect = $redirect;
    $this->renderHTML('success');
    if (!$no_exit) {
      die();
    }
  }
  
  final protected function error($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  /*
  public function create_table() {
    $query = <<<SQL
CREATE TABLE IF NOT EXISTS `pass_batches` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `batch_id` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `size` int(10) unsigned NOT NULL,
 `created_on` int(10) unsigned NOT NULL,
 `created_by` varchar(255) NOT NULL,
 `description` varchar(512) NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
SQL;
    mysql_global_call($query);
    
    echo 'Done';
  }
  */
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function getRandomHexBytes($length = 10) {
    $bytes = openssl_random_pseudo_bytes($length);
    return bin2hex($bytes);
  }
  
  /**
   * Index
   */
  public function index() {
    $this->renderHTML('create4chanpass');
  }
  
  /**
   * Create 4chan Pass
   */
  public function create() {
    $fields = array('email' => 'E-mail', 
      'transaction' => 'Transaction ID', 'otp' => '2FA OTP');
    
    foreach ($fields as $field => $label) {
      if (!isset($_POST[$field]) || $_POST[$field] == '') {
        $this->error($label . ' cannot be empty.');
      }
    }
    
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid or expired OTP.');
    }
    
    // E-mails
    $email = $_POST['email'];
    
    if (!preg_match('/^[^ ]+@[^ ]+/', $email)) {
      $this->error('Invalid E-mail');
    }
    
    if (isset($_POST['gift_email']) && $_POST['gift_email'] != '') {
      $gift_email = $_POST['gift_email'];
      
      if (!preg_match('/^[^ ]+@[^ ]+/', $gift_email)) {
        $this->error('Invalid Gift E-mail');
      }
      
      $is_gift = true;
    }
    else {
      $gift_email = '';
      $is_gift = false;
    }
    
    // Transaction
    if (!isset($_POST['transaction']) || $_POST['transaction'] === '') {
      $this->error('Transaction cannot be empty');
    }
    
    $transaction = $_POST['transaction'];
    
    // Price paid in cents
    if (isset($_POST['price_paid']) && $_POST['price_paid'] !== '') {
      if (!preg_match('/^[0-9]+$/', $_POST['price_paid'])) {
        $this->error('Invalid price paid');
      }
      
      $price_paid = (int)$_POST['price_paid'];
    }
    else {
      $price_paid = 0;
    }
    
    $price_paid_dollars = round($price_paid / 100.0, 2);
    
    if (isset($_POST['preloaded']) && $_POST['preloaded']) {
      $status = self::PRE_STATUS;
    }
    else {
      $status = self::NORMAL_STATUS;
    }
    
    // ---
    
    $pending_id = $this->getRandomHexBytes(16);
    
    $user_hash = $this->generateUserHash();
    
    $plain_pin = $this->generatePin();
    $pin = crypt($plain_pin, substr($user_hash, 4, 9));
    
    $query = <<<SQL
INSERT INTO pass_users(user_hash, transaction_id, pin, purchase_date, status,
last_ip, last_used, email, pending_id, expiration_date, email_sent, gift_email,
price_paid)
VALUES ('%s', '%s', '%s', NOW(), '$status', '0.0.0.0', NOW(), '%s', '%s',
NOW() + INTERVAL 1 YEAR, 1, '%s', %d)
SQL;
    
    $res = mysql_global_call($query,
      $user_hash, $transaction, $plain_pin, $email, $pending_id, $gift_email,
      $price_paid
    );
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $expiry_str = date('m/d/y', strtotime('+1 year'));
    
    require_once('/www/global/yotsuba/payments/stripe_web_request.php');
    
    define('STRIPE_EMAIL_AMOUNT_IN_DOLLARS', $price_paid_dollars);
    
    send_welcome_email($user_hash, $plain_pin, $expiry_str, $email, $gift_email, $is_gift, false);
    
    $this->user_hash = $user_hash;
    $this->plain_pin = $plain_pin;
    $this->price_paid_dollars = $price_paid_dollars;
    
    $this->renderHTML('create4chanpass');
  }
  
  private function generateUserHash() {
    return substr(strtoupper(str_shuffle( 'abcdefghijklmnopqrstuvqxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567901234567890')), 0, 10);
  }
  
  private function generatePin() {
    return mt_rand(100000, 999999);
  }
  
  public function batches() {
    $query = "SELECT * FROM pass_batches ORDER BY id DESC";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error');
    }
    
    $this->batches = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->batches[] = $row;
    }
    
    $this->renderHTML('create4chanpass-batch');
  }
  
  public function create_batch() {
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid or expired OTP.');
    }
    
    $batch_size = (int)$_POST['batch_size'];
    
    if ($batch_size < 1 || $batch_size > self::MAX_BATCH_SIZE) {
      $this->error('Invalid Batch Size');
    }
    
    if (isset($_POST['description'])) {
      $description = htmlspecialchars($_POST['description'], ENT_QUOTES);
    }
    else {
      $description = '';
    }
    
    $created_on = time();
    
    $created_by = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    $batch_id = 'batch_' . $this->getRandomHexBytes(8);
    
    $sql =<<<SQL
INSERT INTO `pass_batches` (batch_id, size, description, created_on, created_by)
VALUES ('%s', %d, '%s', %d, '%s')
SQL;
    
    $res = mysql_global_call($sql,
      $batch_id, $batch_size, $description, $created_on, $created_by
    );
    
    if (!$res) {
      $this->error('Database error (1)');
    }
    
    $status = self::PRE_STATUS;
    
    set_time_limit(self::EXEC_LIMIT);
    
    for ($i = 0; $i < $batch_size; ++$i) {
      $pending_id = $this->getRandomHexBytes(16);
      
      $user_hash = $this->generateUserHash();
      
      $plain_pin = $this->generatePin();
      $pin = crypt($plain_pin, substr($user_hash, 4, 9));
      
      if (!$pending_id) {
        $this->error('Internal Server Error (1)');
      }
      
      $transaction = $batch_id . '_' . $pending_id;
      
      $query = <<<SQL
INSERT INTO pass_users(user_hash, transaction_id, pin, purchase_date, status,
last_ip, last_used, email, pending_id, expiration_date, email_sent, gift_email,
price_paid, registration_country)
VALUES ('%s', '%s', '%s', NOW(), $status, '0.0.0.0', NOW(), '', '%s',
NOW() + INTERVAL 1 YEAR, 1, '', 0, 'XX')
SQL;
      
      $res = mysql_global_call($query, $user_hash, $transaction, $plain_pin, $pending_id);
      
      if (!$res) {
        $this->error('Database Error (2)');
      }
    }
    
    $this->success('?action=batches');
  }
  
  function export_batch() {
    $id = (int)$_GET['id'];
    
    if (!$id) {
      $this->error('Batch Not Found.');
    }
    
    $query = "SELECT batch_id FROM pass_batches WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1)');
    }
    
    $batch_id = mysql_fetch_row($res)[0];
    
    if (!$batch_id) {
      $this->error('Batch Not Found.');
    }
    
    $esc_batch_id = mysql_real_escape_string($batch_id);
    
    if (!$esc_batch_id) {
      $this->error('Database Error (2)');
    }
    
    $esc_batch_id = str_replace('_', "\_", $esc_batch_id . '_');
    
    $query = <<<SQL
SELECT user_hash, pin FROM pass_users
WHERE status = %d AND transaction_id LIKE '{$esc_batch_id}%%'
SQL;
    
    $res = mysql_global_call($query, self::PRE_STATUS);
    
    header('Content-disposition: attachment; filename=passes-' . $batch_id . '.txt');
    header('Content-Type: application/octet-stream');
    
    while($row = mysql_fetch_assoc($res)) {
      echo "{$row['user_hash']}\t{$row['pin']}\n";
    }
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
