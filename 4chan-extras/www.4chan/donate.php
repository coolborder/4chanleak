<?php
// Closed
die();

header('Cache-Control: private, no-cache, no-store');
header('Expires: -1');
header('Vary: *');
header('Strict-Transport-Security: max-age=15768000');

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
  header('HTTP/1.1 301 Moved Permanently');
  header('Location: https://www.4chan.org/donate');
  die();
}

require_once 'lib/db.php';
/*
require_once 'lib/admin.php';
require_once 'lib/auth.php';
auth_user();
if (!has_level('manager') && !has_flag('developer')) {
  header("HTTP/1.0 404 Not Found");
  die();
}

$mysql_suppress_err = false;
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
*/
define('IN_APP', true);

require_once 'lib/ini.php';
load_ini_file('payments_config.ini');

require_once 'payments/lib/Stripe.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'donate'
    );
  
  private $_salt = null;
  
  const TABLE = 'donations';
  
  const DESCRIPTION = '[DONATION]';
  
  const
    FIELD_MAX_LEN = 150,
    COOLDOWN = 30, // seconds
    MIN_AMOUNT = 500 // Amounts are in cents
  ;
  
  const
    S_TOO_LONG = '%s is too long.',
    S_NO_TOKEN = 'Invalid token.',
    S_BAD_EMAIL = 'Invalid e-mail',
    S_EMAIL_MISMATCH = 'Your e-mail addresses do not match.',
    S_BAD_AMOUNT = 'Invalid amount',
    S_DB_ERROR = 'Database Error.',
    S_STRIPE_ERROR = 'There has been a problem with your payment method.',
    S_CARD_SUB_ERROR = 'You may have entered your information incorrectly. Your card has not been charged.',
    S_BTC_SUB_ERROR = 'Your Bitcoin address has been debited, however there was an error with our system. Please contact <a href="mailto:4chand@4chan.org?subject=4chan%20Donations%20-%20Bitcoin%20Error">4chand@4chan.org</a> if your bitcoins aren\'t refunded after 1 hour.',
    S_DB_SUB_ERROR = 'Please contact <a href="mailto:4chand@4chan.org">4chand@4chan.org</a> for assistance.',
    S_TOO_FAST = 'You have to wait a while before making another donation.',
    ERR_ORDER_BAD_COUNTRY = 'Donations from your country have been blocked due to US sanctions.'
  ;
  
  const WEBROOT = '/donate';
  
  public function debug() {
    
  }
  
  final protected function success($msg = null, $redirect = null) {
    $this->redirect = $redirect;
    $this->message = $msg;
    $this->renderHTML('success');
    die();
  }
  
  final protected function error($msg, $code = null) {
    if ($code) {
      http_response_code($code);
    }
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  private function validate_cooldown($token) {
    $tbl = self::TABLE;
    
    $query = "SELECT created_on FROM `$tbl` WHERE ip = '%s' ORDER BY id DESC LIMIT 1";
    
    $res = mysql_global_call($query, $_SERVER['REMOTE_ADDR']);
    
    if (!$res) {
      quick_log_to('/www/perhost/stripe_donations.log', "ERROR (DB Cooldown): $token");
      $this->error(self::S_DB_ERROR . '<br>' . self::S_DB_SUB_ERROR);
    }
    
    if (mysql_num_rows($res) < 1) {
      return true;
    }
    
    $ts = (int)mysql_fetch_row($res)[0];
    $dt = $_SERVER['REQUEST_TIME'] - self::COOLDOWN;
    
    if ($ts > $dt) {
      quick_log_to('/www/perhost/stripe_donations.log', "ERROR (Too fast): $token");
      $this->error(self::S_TOO_FAST . '<br>' . self::S_DB_SUB_ERROR);
    }
  }
  
  private function validate_country() {
    $blocked = array('CI', 'CU', 'IR', 'KP', 'MM', 'SY');
    
    $country = geoip_country_code_by_addr($_SERVER['REMOTE_ADDR']);
    
    if ($country && in_array($country, $blocked)) {
      $this->error(self::ERR_ORDER_BAD_COUNTRY);
    }
  }
  
  private function stripeError($is_bitcoin = false) {
    $msg = self::S_STRIPE_ERROR;
    
    if ($is_bitcoin) {
      $msg .= '<br>' . self::S_BTC_SUB_ERROR;
    }
    else {
      $msg .= '<br>' . self::S_CARD_SUB_ERROR;
    }
    
    $this->error($msg);
  }
  
  private function generate_reference_id() {
    $bytes = openssl_random_pseudo_bytes(16);
    return rtrim(base64_encode($bytes), '=');
  }
  
  /**
   * Returns a JSON response
   */
  private function renderJSON($data) {
    header('Content-type: application/json');
    echo json_encode($data);
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    include('views/' . $view . '.tpl.php');
  }
  
  /**
   * Default page
   */
  public function index() {
    if (!mysql_global_connect()) {
      $this->error(self::S_DB_ERROR . '<br>' . self::S_DB_SUB_ERROR);
    }
    
    $this->validate_country();
    
    $this->renderHTML('donate');
  }
  
  private function send_email($email,$donation) {
    $subject = 'Your 4chan donation';
    
    $amount_usd = round($donation['amount'] / 100);
    
    $summary = array();
    
    $summary[] = "Donation ID: {$donation['id']}";
    
    $summary[] = "Amount: $$amount_usd USD";
    
    if ($donation['name']) {
      $summary[] = "Name: {$donation['name']}";
    }
    
    if ($donation['message']) {
      $summary[] = "Message:\n{$donation['message']}";
    }
    
    $summary[] = "Display name and message publicly: " . ($donation['is_public'] ? 'Yes' : 'No');
    
    $summary = implode("\n\n", $summary);
    
    $message = <<<TXT
Thank you for supporting 4chan!

You have been billed for $$amount_usd USD.

Donation Details:
================
$summary
================

If you have any questions, please e-mail 4chand@4chan.org

Thanks again for your support!
TXT;

    // From:
    $headers = "From: 4chan <4chand@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    return mail($email, $subject, $message, $headers, '-f 4chand@4chan.org');
  }
  
  public function donate() {
    if (!mysql_global_connect()) {
      $this->error(self::S_DB_ERROR);
    }
    
    $this->validate_country();
    
    set_time_limit(120);
    
    // Token
    if (!isset($_POST['stripeToken'])) {
      $this->error(self::S_NO_TOKEN);
    }
    
    $token = $_POST['stripeToken'];
    
    // Cooldown
    $this->validate_cooldown($token);
    
    // Amount
    if (!isset($_POST['amount'])) {
      $this->error(self::S_BAD_AMOUNT);
    }
    
    $amount = (int)$_POST['amount'];
    
    $amount = $amount * 100; // in cents
    
    if ($amount < self::MIN_AMOUNT) {
      $this->error(self::S_BAD_AMOUNT);
    }
    
    // Email
    if (!isset($_POST['email']) || !isset($_POST['email2'])) {
      $this->error(self::S_BAD_EMAIL);
    }
    
    if ($_POST['email'] !== $_POST['email2']) {
      $this->error(self::S_EMAIL_MISMATCH);
    }
    
    $email = $_POST['email'];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->error(self::S_BAD_EMAIL);
    }
    
    $email = htmlspecialchars(strtolower($email), ENT_QUOTES);
    
    if (strlen($email) > self::FIELD_MAX_LEN) {
      $this->error(sprintf(self::S_TOO_LONG, 'E-mail'));
    }
    
    // Name
    if (isset($_POST['name']) && $_POST['name'] !== '') {
      $name = htmlspecialchars($_POST['name'], ENT_QUOTES);
    }
    else {
      $name = '';
    }
    
    if (strlen($name) > self::FIELD_MAX_LEN) {
      $this->error(sprintf(self::S_TOO_LONG, 'Name'));
    }
    
    // Message
    if (isset($_POST['message']) && $_POST['message'] !== '') {
      $message = htmlspecialchars($_POST['message'], ENT_QUOTES);
    }
    else {
      $message = '';
    }
    
    if (strlen($message) > self::FIELD_MAX_LEN) {
      $this->error(sprintf(self::S_TOO_LONG, 'Message'));
    }
    
    // Public
    if (isset($_POST['public']) && $_POST['public'] === '1') {
      $is_public = 1;
    }
    else {
      $is_public = 0;
    }
    
    // IP
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Date
    $now = $_SERVER['REQUEST_TIME'];
    
    // ---
    
    $tbl = self::TABLE;
    
    // Payment
    Stripe::setApiKey(STRIPE_API_KEY_PRIVATE);
    
    $is_bitcoin = $_POST['stripeTokenType'] === 'source_bitcoin';
    
    try {
      $query =<<<SQL
SELECT customer_id FROM `$tbl`
WHERE email = '%s' AND transaction_id LIKE 'ch_%%'
ORDER BY created_on DESC LIMIT 1
SQL;
      
      $res = mysql_global_call($query, $email);
      
      if ($res) {
        $cust_id = mysql_fetch_row($res)[0];
      }
      else {
        $cust_id = false;
      }
      
      $customer = null;
      
      if (!$is_bitcoin && $cust_id) {
        try {
          $customer = Stripe_Customer::retrieve($cust_id);
          $customer->card = $token;
          $customer->description = self::DESCRIPTION;
          $customer->save();
        }
        catch (Exception $e) {
          // This shouldn't happen in Live mode.
        }
      }
      
      if (!$customer) {
        $cus_array = array(
          'email'           => $email,
          'description'     => self::DESCRIPTION,
          'account_balance' => 0
        );
        
        if ($is_bitcoin) {
          $cus_array['source'] = $token;
        }
        else {
          $cus_array['card'] = $token;
        }
        
        $customer = Stripe_Customer::create($cus_array);
      }
    }
    catch (Exception $e) {
      quick_log_to('/www/perhost/stripe_donations.log', "ERROR (1): $token\n" . print_r($e, true));
      $this->stripeError($is_bitcoin);
    }
    
    if (!$is_bitcoin) {
      $default_card = $customer->default_card;
      
      $card = null;
      
      foreach ($customer->cards->data as $_card) {
        if ($_card->id === $default_card) {
          $card = $_card;
        }
      }
      
      $checks = array(
        $card->cvc_check,
        $card->address_line1_check,
        $card->address_zip_check
      );
      
      foreach ($checks as $val) {
        if ($val === 'fail') {
          quick_log_to('/www/perhost/stripe_donations.log', "ERROR (Card check failed): $token");
          $this->stripeError();
        }
      }
    }
    
    try {
      $charge = Stripe_Charge::create(array(
        'amount' => $amount,
        'currency' => 'usd',
        'customer' => $customer->id,
        'description' => self::DESCRIPTION
      ));
      
      $transaction_id = $charge->id;
    }
    catch (Exception $e) {
      quick_log_to('/www/perhost/stripe_donations.log', "ERROR (Charge failed): $token\n" . print_r($e, true));
      $this->stripeError();
    }
    
    // Finalizing
    $ref_id = $this->generate_reference_id();
    
    $this->summary = array(
      'id' => $ref_id,
      'name' => $name,
      'email' => $email,
      'message' => $message,
      'amount' => $amount,
      'is_public' => $is_public
    );
    
    $query =<<<SQL
INSERT INTO `$tbl` (ref_id, name, email, customer_id, transaction_id, message, amount_cents, is_public, created_on, ip)
VALUES ('%s', '%s', '%s', '%s', '%s', '%s', %d, %d, %d, '%s')
SQL;
    
    $res = mysql_global_call($query, $ref_id, $name, $email, $customer->id, $transaction_id, $message, $amount, $is_public, $now, $ip);
    
    if (!$res) {
      quick_log_to('/www/perhost/stripe_donations.log', "ERROR (DB insert failed): $token\n" . print_r($this->summary, true));
      $this->error(self::S_DB_ERROR . '<br>' . self::S_DB_SUB_ERROR);
    }
    
    $this->send_email($email, $this->summary);
    
    $this->renderHTML('donate-ok');
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
      die();
    }
  }
}

$ctrl = new App();
$ctrl->run();
