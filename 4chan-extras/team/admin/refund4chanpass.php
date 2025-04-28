<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

if (php_sapi_name() !== 'cli') {
  define('IN_APP', true);
  
  auth_user();
  
  if (!has_level('admin')) {
    APP::denied();
  }
  
  require_once '../lib/csp.php';
  
  if (has_flag('developer')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE);
    $mysql_suppress_err = false;
  }
}

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'refund',
      //'mass_refund',
      'preview',
      'search',
      //'check_refunds',
      //'create_table',
      'check_stripe',
    ),
    
    $default_reason = 'Spam.',
    $default_ban_days = 90,
    
    $field_map = array(
      'com' => 'Comment',
      'sub' => 'Subject',
      'name' => 'Name',
      'filename' => 'File name'
    ),
    
    $date_format = 'm/d/y H:i'
    ;
  
  const TPL_ROOT = '../views/';
  
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
  /*
  public function create_table() {
    $query = <<<SQL
CREATE TABLE IF NOT EXISTS `pass_refunds` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `transaction_id` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 `email` varchar(255) NOT NULL,
 `amount` int(10) unsigned NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
SQL;
    mysql_global_call($query);
    
    echo 'Done';
  }
  */
  private function sendRefundEmail($email, $prorate_ts, $amount, $token) {
    $mail_file = '../data/mail_refund_4chan_pass.txt';
    
    if (!file_exists($mail_file)) {
      $this->error('Cannot find e-mail file (the Pass was refunded.)');
    }
    
    $lines = file($mail_file);
    
    $subject = trim(array_shift($lines));
    $message = implode('', $lines);
    
    $values = array(
      '{{DATE}}' => date('F jS Y', $prorate_ts),
      '{{AMOUNT}}' => '$' . ($amount / 100.0),
      '{{TOKEN}}' => $token
    );
    
    $message = str_replace(array_keys($values), array_values($values), $message);
    
    // From:
    $headers = "From: 4chan Pass <4chanpass@4chan.org>\r\n";
    $headers .= "Bcc: 4chan Pass <4chanpass@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Envelope
    $opts = '-f 4chanpass@4chan.org';
    
    set_time_limit(0);
    
    mail($email, $subject, $message, $headers, $opts);
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  public function index() {
    $this->renderHTML('pass-refund');
  }
  
  public function refund() {
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_GET['otp'])) {
      $this->error('Invalid OTP.');
    }
    
    require_once 'payments/lib/Stripe.php';
    
    $cfg = file_get_contents('/www/global/yotsuba/config/payments_config.ini');
    preg_match('/STRIPE_API_KEY_PRIVATE.?=.?([^\r\n]+)/', $cfg, $m);
    
    Stripe::setApiKey($m[1]);
    
    if (!isset($_GET['transaction_id']) || !isset($_GET['prorate'])) {
      $this->error('Bad request');
    }
    
    $prorate_ts = (int)$_GET['prorate'];
    $tid = $_GET['transaction_id'];
    
    if (!$prorate_ts || !$tid) {
      $this->error('Bad request');
    }
    
    // Fetch the pass
    $query = <<<SQL
SELECT UNIX_TIMESTAMP(expiration_date) as expiration_ts, user_hash,
UNIX_TIMESTAMP(purchase_date) as purchase_ts, transaction_id, price_paid,
email, gift_email, user_hash
FROM pass_users
WHERE status IN (0, 6) AND pin != '' AND transaction_id = '%s'
SQL;
    $res = mysql_global_call($query, $tid);
    
    if (!$res) {
      $this->error('Database error. No changes were made. (1)');
    }
    
    $pass = mysql_fetch_assoc($res);
    
    if (!$pass) {
      $this->error('Pass not found or is not refundable.');
    }
    
    // Calculate the amount
    if ((int)$pass['expiration_ts'] <= $prorate_ts) {
      $this->error('This Pass expired before or on the pro-rate date.');
    }
    
    $days_to_sec = 24 * 60 * 60;
    
    $day_price = (int)$pass['price_paid'] / (((int)$pass['expiration_ts'] - (int)$pass['purchase_ts']) / $days_to_sec);
    /*
    if ((int)$row['purchase_ts'] >= $prorate_ts) {
      $this->error('This Pass was purchased after the pro-rate date.');
    }
    */
    $days_to_refund = round(((int)$pass['expiration_ts'] - $prorate_ts) / $days_to_sec);
    $amount = round($days_to_refund * $day_price);
    
    if ($amount > (int)$pass['price_paid'] || $amount < 1) {
      $this->error('The amount to refund is greater than the price paid or lower than $0.01 USD.');
    }
    
    try {
      $charge = Stripe_Charge::retrieve($tid);
      
      if ($charge->amount_refunded > 0) {
        $this->error("This charge is already refunded ({$charge->amount_refunded} cents)");
      }
      
      $refund = $charge->refunds->create(array('amount' => $amount));
      
      if ($refund) {
        $query = "UPDATE pass_users SET status = 2, refund_token = '' WHERE transaction_id = '%s' LIMIT 1";
        $res = mysql_global_call($query, $tid);
        
        if (!$res) {
          $this->error("Database error (charge was refunded, but the pass status couldn't be updated");
        }
      }
    }
    catch (Stripe_Error $e) {
      $body = $e->getJsonBody();
      $err = $body['error'];
      $this->error($err['message']);
    }
    catch (Exception $e) {
      $this->error($e->getMessage());
    }
    
    set_time_limit(0);
    
    $this->sendRefundEmail($pass['email'], $prorate_ts, $amount, $pass['user_hash']);
    
    $this->success();
  }
  
  private function log($type, $msg) {
    echo date('Y-m-d H:i:s') . " - [$type] $msg\n";
  }
  
  public function mass_refund() {
    //if (php_sapi_name() !== 'cli') {
      die('Forbidden');
    //}
    
    require_once 'payments/lib/Stripe.php';
    
    $cfg = file_get_contents('/www/global/yotsuba/config/payments_config.ini');
    preg_match('/STRIPE_API_KEY_PRIVATE.?=.?([^\r\n]+)/', $cfg, $m);
    
    Stripe::setApiKey($m[1]);
    
    $this->prorate_ts = mktime(0, 0, 0, 12, 7, 2014);
    
    if (!$this->prorate_ts) {
      die('Invalid date');
    }
    
    $now = time();
    
    $days_to_sec = 24 * 60 * 60;
    
    $query = <<<SQL
SELECT UNIX_TIMESTAMP(expiration_date) as expiration_ts, user_hash,
UNIX_TIMESTAMP(purchase_date) as purchase_ts, transaction_id, price_paid,
email, gift_email, user_hash
FROM pass_users
WHERE status = 0 AND pin != '' AND transaction_id LIKE 'ch_%'
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      die('Database error');
    }
    
    $total_count = 0;
    $total_cents = 0;
    
    while ($row = mysql_fetch_assoc($result)) {
      if ((int)$row['expiration_ts'] <= $this->prorate_ts) {
        $this->log('SKIP', "Already expired or expired before prorate date ({$row['user_hash']} {$row['transaction_id']})");
        continue;
      }
      
      $day_price = (int)$row['price_paid'] / (((int)$row['expiration_ts'] - (int)$row['purchase_ts']) / $days_to_sec);
      
      if ((int)$row['purchase_ts'] >= $this->prorate_ts) {
        $days_to_refund = round(((int)$row['expiration_ts'] - (int)$row['purchase_ts']) / $days_to_sec);
        $cents_to_refund = (int)$row['price_paid'];
      }
      else {
        $days_to_refund = round(((int)$row['expiration_ts'] - $this->prorate_ts) / $days_to_sec);
        $cents_to_refund = round($days_to_refund * $day_price);
      }
      
      $cents_to_refund = round($days_to_refund * $day_price);
      
      if ($cents_to_refund > (int)$row['price_paid'] || $cents_to_refund < 1) {
        $this->log('SKIP', "Refund price $cents_to_refund vs Paid price {$row['price_paid']} ({$row['user_hash']} {$row['transaction_id']})");
        continue;
      }
      
      try {
        $charge = Stripe_Charge::retrieve($row['transaction_id']);
        
        if ($charge->amount_refunded > 0) {
          $this->log('SKIP', "Charge already refunded ({$row['user_hash']} {$row['transaction_id']})");
        }
        
        //$refund = $charge->refunds->create(array('amount' => $cents_to_refund));
        /*
        if (!$refund) {
          $this->log('FATAL', "Stripe didn't return a refund object ({$row['user_hash']} {$row['transaction_id']})");
          die();
        }
        */
        $this->log('OK', "Refunded $cents_to_refund cents for $days_to_refund days ({$row['user_hash']} {$row['transaction_id']})");
        
        $total_cents += $cents_to_refund;
        $total_count++;
        /*
        $query = "UPDATE pass_users SET status = 2 WHERE transaction_id = '%s' LIMIT 1";
        $res = mysql_global_call($query, $row['transaction_id']);
        
        if (!$res) {
          $this->log('FATAL', "Database error. Pass status could not be updated ({$row['user_hash']} {$row['transaction_id']})");
          die();
        }
        */
        $usd_price = $cents_to_refund / 100.0;
        
        $query = <<<SQL
INSERT INTO refund_emails (email, type, usd, token, transaction_id)
VALUES ('%s', '%s', '$usd_price', '%s', '%s')
SQL;
        
        if ($row['gift_email']) {
          $res = mysql_global_call($query, $row['gift_email'], 'gift_recipient', $row['user_hash'], $row['transaction_id']);
          
          if (!$res) {
            $this->log('FATAL', "Database error. Gift recipient email could not be stored ({$row['user_hash']} {$row['transaction_id']})");
            die();
          }
          
          $res = mysql_global_call($query, $row['email'], 'gift_giver', $row['user_hash'], $row['transaction_id']);
          
          if (!$res) {
            $this->log('FATAL', "Database error. Gift giver email could not be stored ({$row['user_hash']} {$row['transaction_id']})");
            die();
          }
        }
        else {
          $res = mysql_global_call($query, $row['email'], 'owner', $row['user_hash'], $row['transaction_id']);
        }
        
        
      }
      catch (Stripe_Error $e) {
        $this->log('FATAL', $e->message . " ({$row['user_hash']} {$row['transaction_id']})");
        die();
      }
      catch (Exception $e) {
        $this->log('FATAL', $e->getMessage() . " ({$row['user_hash']} {$row['transaction_id']})");
        die();
      }
    }
    
    $this->log('OK', "Done, refunded $total_count passes for $" . ($total_cents / 100.0));
  }
  
  public function search() {
    if (!isset($_GET['q'])) {
      $this->error('Bad request');
    }
    
    $q = mysql_real_escape_string($_GET['q']);
    
    if (!$q) {
      $this->error('Query cannot be empty');
    }
    
    if (!isset($_GET['to_date']) || !$_GET['to_date']) {
      $this->prorate_ts = time();
    }
    else {
      $to_date = explode('/', $_GET['to_date']);
      
      if (count($to_date) < 3) {
        $this->error('Date should be in the MM/DD/YYYY format');
      }
      
      $this->prorate_ts = mktime(0, 0, 0,
        (int)ltrim($to_date[0], '0'),
        (int)ltrim($to_date[1], '0'),
        (int)$to_date[2]);
      
      if (!$this->prorate_ts) {
        $this->error('Invalid date');
      }
    }
    
    $now = time();
    
    $days_to_sec = 24 * 60 * 60;
    
    $query = <<<SQL
SELECT UNIX_TIMESTAMP(expiration_date) as expiration_ts,
UNIX_TIMESTAMP(purchase_date) as purchase_ts, purchase_date,
expiration_date, customer_id, transaction_id, price_paid, email, user_hash
FROM pass_users
WHERE (user_hash = '$q'
OR transaction_id = '$q'
OR customer_id = '$q'
OR email = '$q') AND status IN (0, 6) AND pin != ''
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    $this->passes = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      if ((int)$row['expiration_ts'] < $this->prorate_ts) {
        $not_refundable = true;
      }
      
      $day_price = (int)$row['price_paid'] / (((int)$row['expiration_ts'] - (int)$row['purchase_ts']) / $days_to_sec);
      
      if ((int)$row['purchase_ts'] >= $this->prorate_ts) {
        $days_to_refund = round(((int)$row['expiration_ts'] - (int)$row['purchase_ts']) / $days_to_sec);
        $cents_to_refund = (int)$row['price_paid'];
      }
      else {
        $days_to_refund = round(((int)$row['expiration_ts'] - $this->prorate_ts) / $days_to_sec);
        $cents_to_refund = round($days_to_refund * $day_price);
      }
      
      if ($cents_to_refund > (int)$row['price_paid'] || $cents_to_refund < 1) {
        $not_refundable = true;
      }
      
      if (!preg_match('/^ch_/', $row['transaction_id'])) {
        $not_refundable = true;
      }
      
      if ($not_refundable) {
        $row['not_refundable'] = true;
      }
      
      $row['refund_cents'] = $cents_to_refund;
      $row['refund_days'] = $days_to_refund;
      
      $this->passes[] = $row;
    }
    
    $this->renderHTML('pass-refund');
  }
  
  public function preview() {
    $this->prorate_ts = mktime(0, 0, 0, 12, 7, 2014);
    
    $now = time();
    
    $days_to_sec = 24 * 60 * 60;
    
    $query = <<<SQL
SELECT UNIX_TIMESTAMP(expiration_date) as expiration_ts,
UNIX_TIMESTAMP(purchase_date) as purchase_ts, purchase_date,
expiration_date, customer_id, transaction_id, price_paid, email, user_hash
FROM pass_users
WHERE status IN (0, 6) AND pin != '' AND transaction_id LIKE 'ch_%'
ORDER BY purchase_date ASC
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    $this->total_count = 0;
    $this->total_cents = 0;
    
    $this->passes = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      if ((int)$row['expiration_ts'] <= $this->prorate_ts) {
        continue;
      }
      
      $day_price = (int)$row['price_paid'] / (((int)$row['expiration_ts'] - (int)$row['purchase_ts']) / $days_to_sec);
      
      if ((int)$row['purchase_ts'] >= $this->prorate_ts) {
        $days_to_refund = round(((int)$row['expiration_ts'] - (int)$row['purchase_ts']) / $days_to_sec);
        $cents_to_refund = (int)$row['price_paid'];
      }
      else {
        $days_to_refund = round(((int)$row['expiration_ts'] - $this->prorate_ts) / $days_to_sec);
        $cents_to_refund = round($days_to_refund * $day_price);
      }
      
      
      if ($cents_to_refund > (int)$row['price_paid'] || $cents_to_refund < 1) {
        continue;
      }
      
      $this->total_cents += $cents_to_refund;
      $this->total_count++;
      
      $row['refund_cents'] = $cents_to_refund;
      $row['refund_days'] = $days_to_refund;
      
      $this->passes[] = $row;
    }
    
    $this->renderHTML('pass-refund');
  }
  /*
  public function check_refunds() {
    $query = <<<SQL
SELECT pass_users.*, pass_refunds.amount, pass_refunds.ip FROM pass_refunds LEFT JOIN pass_users ON
pass_refunds.transaction_id = pass_users.transaction_id
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    $this->total_refunded = 0;
    $this->refunds = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->refunds[] = $row;
      $this->total_refunded += (int)$row['amount'];
    }
    
    $this->renderHTML('pass-refund');
  }
  */
  public function check_stripe() {
    die();
    
    if (!isset($_GET['tid']) || !$_GET['tid']) {
      $this->error('Bad request');
    }
    
    require_once 'payments/lib/Stripe.php';
    
    $cfg = file_get_contents('/www/global/yotsuba/config/payments_config.ini');
    preg_match('/STRIPE_API_KEY_PRIVATE.?=.?([^\r\n]+)/', $cfg, $m);
    
    Stripe::setApiKey($m[1]);
    
    try {
      $charge = Stripe_Charge::retrieve($_GET['tid']);
      
      echo '<pre>';
      print_r($charge);
      echo '</pre>';
    }
    catch (Stripe_Error $e) {
      $body = $e->getJsonBody();
      $err = $body['error'];
      $this->error($err['message']);
    }
    catch (Exception $e) {
      $this->error($e->getMessage());
    } 
  }
  
  /**
   * Main
   */
  public function run() {
    $method = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (php_sapi_name() === 'cli') {
      $action = 'mass_refund';
    }
    else if (isset($method['action'])) {
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
