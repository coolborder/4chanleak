<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'reset'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const EMAIL_FILE = '../data/mail_reset_4chan_pass.txt';
  
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
  
  private function sendEmail($email, $values = null) {
    $mail_file = self::EMAIL_FILE;
    
    if (!file_exists($mail_file)) {
      $this->error('Cannot find e-mail file.');
    }
    
    $lines = file($mail_file);
    
    $subject = trim(array_shift($lines));
    $message = implode('', $lines);
    
    if ($values) {
      $message = str_replace(array_keys($values), array_values($values), $message);
    }
    
    $headers = "From: 4chan Pass <4chanpass@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $opts = '-f 4chanpass@4chan.org';
    
    return mail($email, $subject, $message, $headers, $opts);
  }
  
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
    $this->renderHTML('reset4chanpass');
  }
  
  /**
   * Create user
   */
  public function reset() {
    if (!isset($_POST['uid'])) {
      $this->error('Token or Transaction ID cannot be empty.');
    }
    
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid or expired OTP.');
    }
    
    $uid = trim($_POST['uid']);
    
    if ($uid === '') {
      $this->error('Token or Transaction ID cannot be empty.');
    }
    
    $query =<<<SQL
SELECT *, UNIX_TIMESTAMP(expiration_date) as expiration_date FROM pass_users
WHERE (transaction_id = '%s' OR user_hash = '%s')
AND (status = 0 OR status = 6)
AND pin != ''
LIMIT 1
SQL;
    
    $res = mysql_global_call($query, $uid, $uid);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('4chan Pass Not Found.');
    }
    
    $pass = mysql_fetch_assoc($res);
    
    // email
    if ($pass['gift_email'] !== '') {
      $email = $pass['gift_email'];
    }
    else {
      $email = $pass['email'];
    }
    
    $user_hash = $pass['user_hash'];
    
    $plain_pin = mt_rand(100000, 999999);
    
    $query =<<<SQL
UPDATE pass_users SET pin = %d, status = 6
WHERE user_hash = '%s'
AND (status = 0 OR status = 6)
AND pin != ''
LIMIT 1
SQL;
    
    $res = mysql_global_call($query, $plain_pin, $user_hash);
    
    if (!$res) {
      $this->error('Database Error (2).');
    }
    
    $this->user_hash = $user_hash;
    $this->plain_pin = $plain_pin;
    
    // Don't wait for sendEmail()
    $this->renderHTML('reset4chanpass');
    
    fastcgi_finish_request();
    
    // Send instructions email
    $values = array(
      '{{USER_HASH}}' => $user_hash,
      '{{PIN}}' => $plain_pin
    );
    
    $this->sendEmail($email, $values);
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
