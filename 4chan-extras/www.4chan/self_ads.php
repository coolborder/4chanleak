<?php
header('Cache-Control: private, no-cache, no-store');
header('Expires: -1');
header('Vary: *');
//header('Strict-Transport-Security: max-age=15768000');

if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
  //header('HTTP/1.1 301 Moved Permanently');
  //header('Location: https://www.4chan.org/donate');
  die();
}

die();

require_once 'lib/db.php';
require_once 'lib/admin.php';
require_once 'lib/auth.php';
require_once 'lib/captcha.php';

if ($_SERVER['REMOTE_ADDR'] !== '62.210.138.29') {
  die('404');
}

$mysql_suppress_err = false;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

auth_user();

if (!has_flag('developer')) {
  die('403');
}

//require_once 'lib/ini.php';
//load_ini_file('payments_config.ini');

require_once 'payments/lib/Stripe.php';

define('IN_APP', true);

mysql_global_connect();

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'submit',
      'debug'
    );
  
  private $_salt = null;
  
  private $_placements = array(
    'desktop_leaderboard' => array(
      'label' => 'Leaderboard (Desktop)',
      'width' => 728,
      'height' => 90
    ),
    'mobile_rectangle' => array(
      'label' => 'Medium Rectangle (Mobile)',
      'width' => 300,
      'height' => 250
    ),
  );
  
  // Above USD => CPM
  private $_pricing_table = array(
    0 => 0.25,
    100 => 0.24,
    500 => 0.23,
    1000 => 0.22,
    2500 => 0.21,
    5000 => 0.20
  );
  
  const
    ADZERK_GEO_PATH = 'data/adzerk_countries.json',
    ADZERK_BASE_URL = 'https://api.adzerk.net/v1/',
    ADZERK_API_KEY = '313348F0A21CEA424EA838EA7B066817E289',
    ADZERK_ADVERTISER = 149638
  ;
  
  const CUSTOMER_DESCRIPTION = '[SELF-SERVE ADS]';
  
  const
    MIN_USD_AMOUNT = 20,
    IMG_MAX_FILESIZE = 153600, // 150KB
    FIELD_MAX_LEN = 150, // author and email fields limits
    COOLDOWN = 30,
    DATE_FORMAT_SHORT ='m/d/y',
    
    SALT_PATH = '/www/keys/2014_admin.salt'
  ;
  
  const
    CUSTOMERS_TABLE = 'ads_customers',
    CAMPAIGNS_TABLE = 'ads_campaigns',
    IMG_ROOT = '/www/global/static/image/self_serve'
  ;
  
  const
    S_NO_TOKEN = 'Invalid token.',
    S_BAD_EMAIL = 'Invalid e-mail address.',
    S_BAD_DATE = 'Invalid %s date',
    S_EMAIL_MISMATCH = 'Your e-mail addresses do not match.',
    S_NO_FILE = 'You forgot to upload your banner.',
    S_MAX_FILESIZE = 'The uploaded file is too big.',
    S_FILE_FORMAT = 'Please make sure your banner is a valid JPG, PNG or GIF image.',
    S_FILE_DIMS = 'Please make sure your banner has the right dimensions (%d×%d)',
    S_LONG_FIELD = '%s is too long.'
  ;
  
  const
    S_STRIPE_ERROR = 'There has been a problem with your payment method.',
    S_CARD_SUB_ERROR = 'You may have entered your information incorrectly. Your card has not been charged.',
    S_BTC_SUB_ERROR = 'Your Bitcoin address has been debited, however there was an error with our system. Please contact <a href="mailto:advertise@4chan.org?subject=Bitcoin%20Error">advertise@4chan.org</a> for assistance.',
    S_DB_SUB_ERROR = 'Please contact <a href="mailto:advertise@4chan.org">advertise@4chan.org</a> for assistance.'
  ;
  
  const
    STATUS_PENDING = 0,
    STATUS_ACTIVE = 1,
    STATUS_DISABLED = 2
  ;
  
  public function debug() {
    //$data = $this->adzerk_req('countries');
    //$this->renderJSON($data);
    echo mkdir('/www/global/static/image/self_serve');
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
  
  /**
   * Returns the data as json
   */
  final protected function successJSON($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function errorJSON($message, $code = null, $data = null) {
    $payload = array('status' => 'error', 'message' => $message);
    
    if ($code) {
      $payload['code'] = $code;
    }
    
    if ($data) {
      $payload['data'] = $data;
    }
    
    $this->renderJSON($payload, 'error');
    
    die();
  }
  
  private function stripeError($is_bitcoin = false, $ret = false) {
    $msg = self::S_STRIPE_ERROR;
    
    if ($is_bitcoin) {
      $msg .= '<br>' . self::S_BTC_SUB_ERROR;
    }
    else {
      $msg .= '<br>' . self::S_CARD_SUB_ERROR;
    }
    
    if ($ret) {
      return $msg;
    }
    else {
      $this->error($msg);
    }
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
  
  private function get_csrf_token() {
    return bin2hex(openssl_random_pseudo_bytes(16));
  }
  
  private function set_csrf_token($tkn) {
    setcookie('_bctkn', $tkn, 0, '/banner-contest', 'www.4chan.org', true);
  }
  
  private function set_cache($flag) {
    if (!$flag) {
      header('Cache-Control: no-cache');
    }
  }
  
  private function validate_csrf($ref_only = false) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != ''
        && !preg_match('/^https?:\/\/([_a-z0-9]+)\.4chan\.org/', $_SERVER['HTTP_REFERER'])) {
        return false;
      }
      
      if ($ref_only) {
        return true;
      }
      
      if (!isset($_COOKIE['_bctkn']) || !isset($_POST['_bctkn'])
        || $_COOKIE['_bctkn'] == '' || $_POST['_bctkn'] == ''
        || $_COOKIE['_bctkn'] !== $_POST['_bctkn']) {
        return false;
      }
    }
    
    return true;
  }
  
  // Target keywords
  private function get_valid_keywords() {
    $query = "SELECT dir, name FROM boardlist ORDER BY dir ASC";
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    $boards['ws'] = 'All Worksafe Boards';
    $boards['nws'] = 'All Non-Worksafe Boards';
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[$board['dir']] = "/{$board['dir']}/ - {$board['name']}";
    }
    
    // fixme: wtf
    if (isset($boards['vp'])) {
      $boards['vp'] = mb_convert_encoding($boards['vp'], 'UTF-8', 'ASCII');
    }
    
    return $boards;
  }
  
  // Geotargets
  private function get_valid_countries() {
    $data = file_get_contents(self::ADZERK_GEO_PATH);
    
    if (!$data) {
      // fixme: error log
      return array();
    }
    
    $data = json_decode($data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      // fixme: error log
      return array();
    }
    
    return $data;
  }
  
  private function str_to_epoch($str) {
    if (!$str) {
      return 0;
    }
    
    if (!preg_match('/(\d\d)\/(\d\d)\/(\d\d\d\d) (\d\d):(\d\d)/', $str, $m)) {
      return 0;
    }
    
    return (int)mktime($m[4], $m[5], 0, $m[1], $m[2], $m[3]);
  }
  
  private function get_user_hash() {
    $ip = explode('.', $_SERVER['REMOTE_ADDR']);
    $ip = "{$ip[0]}.{$ip[1]}";
    
    $ua = $_SERVER['HTTP_USER_AGENT'];
    
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    
    return sha1("$ip-$ua-$lang-. . $this->_salt");
  }
  
  private function format_keywords($data) {
    $valid_keywords = $this->get_valid_keywords();
    
    $data = explode("\n", $data);
    
    $keywords = array();
    
    foreach ($data as $kw) {
      $kw = trim($kw);
      
      if (!isset($valid_keywords[$kw])) {
        $this->error(S_BAD_KEYWORD);
      }
      
      $keywords[] = $kw;
    }
    
    return implode(',', $keywords);
  }
  
  private function get_impression_count($amount_usd) {
    $cpm = 0;
    
    foreach ($this->_pricing_table as $above_usd => $this_cpm) {
      if ($amount_usd >= $above_usd) {
        $cpm = $this_cpm;
      }
      else {
        break;
      }
    }
    
    if (!$cpm) {
      $this->error('Internal Server Error (gic)');
    }
    
    return ceil($amount_usd / $cpm * 1000);
  }
  
  private function parse_geo_targets($data) {
    $ary = $this->get_valid_countries();
    
    $valid_countries = array();
    
    foreach ($ary as $c) {
      $valid_countries[$c['Code']] = true;
    }
    
    $data = explode("\n", $data);
    
    $geo = array();
    
    foreach ($data as $c) {
      $c = trim($c);
      
      if (!isset($valid_countries[$c])) {
        $this->error(S_BAD_GEO);
      }
      
      $geo[] = array('Country' => $c);
    }
    
    return $geo;
  }
  
  private function init_salt() {
    if (!$this->_salt) {
      $this->_salt = file_get_contents(self::SALT_PATH);
    }
    
    if (!$this->_salt) {
      $this->error('Internal Server Error (is0)');
    }
  }
  
  private function adzerk_req($path, $params = null, $is_put = false) {
    $url = self::ADZERK_BASE_URL . $path;
    
    $ch = curl_init($url);
    
    if (!$ch) {
      return false;
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Adzerk-ApiKey: ' . self::ADZERK_API_KEY));
    
    if ($params) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_SLASHES));
      
      if ($is_put) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
      }
    }
    
    $res = curl_exec($ch);
    
    curl_close($ch);
    
    if ($res === false) {
      return false;
    }
    
    return json_decode($res);
  }
  
  private function generate_session_id() {
    $bytes = openssl_random_pseudo_bytes(128);
    $bytes = rtrim(base64_encode($bytes), '=');
    
    if (!$bytes) {
      $this->error('Internal Server Error (gsi)');
    }
    
    return $bytes;
  }
  
  private function generate_password() {
    $bytes = bin2hex(openssl_random_pseudo_bytes(16));
    
    if (!$bytes) {
      $this->error('Internal Server Error (gp)');
    }
    
    return $bytes;
  }
  
  private function process_payment($new_customer, $param) {
    // Token
    if (!isset($_POST['stripeToken'])) {
      return array(false, self::S_NO_TOKEN);
    }
    
    $token = $_POST['stripeToken'];
    
    //Stripe::setApiKey(STRIPE_API_KEY_PRIVATE);
    Stripe::setApiKey('sk_test_vl5hXPr819cxnwSeN6ybfO66');
    
    $is_bitcoin = $_POST['stripeTokenType'] === 'source_bitcoin';
    
    try {
      if ($new_customer) {
        $email = $param;
        
        $cus_array = array(
          'email'           => $email,
          'description'     => self::CUSTOMER_DESCRIPTION,
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
      else {
        $customer_id = $param;
        
        $customer = Stripe_Customer::retrieve($customer_id);
        
        if ($is_bitcoin) {
          $customer->source = $token;
        }
        else {
          $customer->card = $token;
        }
        
        $customer->save();
      }
      
      return array(true, $customer->id);
    }
    catch (Exception $e) {
      quick_log_to('/www/perhost/stripe_self_ads.log', "ERROR (1): $token\n" . print_r($e, true));
      return array(false, $this->stripeError($is_bitcoin, true));
    }
  }
  
  /**
   * Submit
   */
  public function submit() {
    if (!$this->validate_csrf(true)) {
      $this->error('Bad request.');
    }
    
    $this->init_salt();
    
    $now = $_SERVER['REQUEST_TIME'];
    
    /**
     * Campaign settings
     */
    // File
    /*
    if (!isset($_FILES['file'])) {
      $this->error(self::S_NO_FILE);
    }
    
    $up_meta = $_FILES['file'];  
    
    if ($up_meta['error'] !== UPLOAD_ERR_OK) {
      if ($up_meta['error'] === UPLOAD_ERR_INI_SIZE) {
        $this->error(self::S_MAX_FILESIZE);
      }
      else if ($up_meta['error'] === UPLOAD_ERR_NO_FILE) {
        $this->error(self::S_NO_FILE);
      }
      else {
        $this->error('Internal Server Error (1)');
      }
    }
    
    if (!is_uploaded_file($up_meta['tmp_name'])) {
      $this->error('Internal Server Error (2)');
    }
    
    $file_size = filesize($up_meta['tmp_name']);
    
    if ($file_size > self::IMG_MAX_FILESIZE) {
      $this->error(self::S_MAX_FILESIZE);
    }
    
    $filemeta = getimagesize($up_meta['tmp_name']);
    
    if (!is_array($filemeta)) {
      $this->error(self::S_FILE_FORMAT);
    }
    
    switch ($filemeta[2])
    {
      case IMAGETYPE_GIF:
        $file_ext = 'gif';
        break;
      case IMAGETYPE_JPEG:
        $file_ext = 'jpg';
        break;
      case IMAGETYPE_PNG:
        $file_ext = 'png';
        break;
      default:
        $this->error(self::S_FILE_FORMAT);
        break;
    }
    
    if ($filemeta[0] !== self::IMG_WIDTH || $filemeta[1] !== self::IMG_HEIGHT) {
      $this->error(sprintf(self::S_FILE_DIMS, self::IMG_WIDTH, self::IMG_HEIGHT));
    }
    */
    // Placement
    if (!isset($_POST['placement']) || $_POST['placement'] === '') {
      $this->error(self::S_BAD_PLACEMENT);
    }
    
    if (!isset($this->_placements[$_POST['placement']])) {
      $this->error(self::S_BAD_PLACEMENT);
    }
    
    $placement = $_POST['placement'];
    
    // Target keywords
    if (isset($_POST['keywords']) && $_POST['keywords'] !== '') {
      $keywords = $this->format_keywords($_POST['keywords']);
    }
    else {
      $keywords = null;
    }
    
    // Target countries
    if (isset($_POST['geo']) && $_POST['geo'] !== '') {
      $geo = $this->parse_geo_targets($_POST['geo']);
    }
    else {
      $geo = null;
    }
    
    // Price
    if (!isset($_POST['amount_usd']) || $_POST['amount_usd'] === '') {
      $this->error(self::S_BAD_PRICE);
    }
    
    $amount_usd = (int)$_POST['amount_usd'];
    
    if (!$amount_usd || $amount_usd < self::MIN_USD_AMOUNT) {
      $this->error(self::S_BAD_PRICE);
    }
    
    $amount_cents = $amount_usd * 100;
    
    // Impressions
    $impressions = $this->get_impression_count($amount_usd);
    
    // Dates
    if (isset($_POST['starts_on']) && $_POST['starts_on'] !== '') {
      $starts_on = trim($_POST['starts_on']);
      $starts_on = $this->str_to_epoch($starts_on);
    }
    else {
      $starts_on = 0;
    }
    
    if (!$starts_on) {
      $this->error(sprintf(self::S_BAD_DATE, 'start'));
    }
    
    if (isset($_POST['ends_on']) && $_POST['ends_on'] !== '') {
      $ends_on = trim($_POST['ends_on']);
      $ends_on = $this->str_to_epoch($ends_on);
      
      if (!$ends_on) {
        $this->error(sprintf(self::S_BAD_DATE, 'end'));
      }
    }
    else {
      $ends_on = 0;
    }
    
    // URL
    if (!isset($_POST['click_url']) || $_POST['click_url'] === '') {
      $this->error(self::S_BAD_URL);
    }
    
    $click_url = trim($_POST['click_url']);
    
    if (mb_strlen($click_url) > 255) {
      $this->error(sprintf(self::S_LONG_FIELD, 'URL'));
    }
    
    // Frequency cap
    if (isset($_POST['freq_cap']) && $_POST['freq_cap'] !== '') {
      $freq_cap = (int)trim($_POST['freq_cap']);
    }
    else {
      $freq_cap = null;
    }
    
    // Compile settings json
    $settings = array();
    
    if ($freq_cap) {
      $settings['freq_cap'] = $freq_cap;
    }
    
    if ($keywords) {
      $settings['keywords'] = $keywords;
    }
    
    if ($geo) {
      $settings['geo'] = $geo;
    }
    
    if (!empty($settings)) {
      $settings = json_encode($settings, JSON_UNESCAPED_SLASHES);
    }
    else {
      $settings = '';
    }
    
    // IP
    $ip = $_SERVER['REMOTE_ADDR'];
    /*
    // File name
    $file_name = sha1_file($up_meta['tmp_name']);
    $file_name .= substr(hash('sha256', $file_name . $this->_salt), 0, 16);
    $file_name .=  '.' . $file_ext;
    
    $file_path = self::IMG_ROOT . '/' . $file_name;
    
    if (move_uploaded_file($up_meta['tmp_name'], $file_path) === false) {
      $this->error('Internal Server Error (mf)');
    }
    */
    /**
     * Customer
     */
    // E-mail
    if (!isset($_POST['email']) || !isset($_POST['email2'])) {
      $this->error(self::S_BAD_EMAIL);
    }
    
    if ($_POST['email'] !== $_POST['email2']) {
      $this->error(self::S_EMAIL_MISMATCH);
    }
    
    $email = strtolower(trim($_POST['email']));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $this->error(self::S_BAD_EMAIL);
    }
    
    // Name
    if (isset($_POST['name']) && $_POST['name'] !== '') {
      $name = trim($_POST['name']);
      
      if (mb_strlen($name) > self::FIELD_MAX_LEN) {
        $this->error(sprintf(self::S_LONG_FIELD, 'Name'));
      }
    }
    else {
      $name = '';
    }
    
    // Auth key
    $auth_key = $this->generate_password();
    $auth_key_hashed = password_hash($auth_key, PASSWORD_DEFAULT);
    
    // Session
    $session_id = $this->generate_session_id();
    
    // User hash
    $user_hash = $this->get_user_hash();
    
    /**
     * Payment
     */
    list($_status, $_msg) = $this->process_payment(true, $email);
    
    if ($_status === true) {
      $customer_id = $_msg;
    }
    else {
      $this->error($_msg);
    }
    
    /**
     * Inserting
     */
    //if (!mysql_global_call('START TRANSACTION')) {
    //  $this->error('Database Error (i0)');
    //}
    
    $tbl = self::CUSTOMERS_TABLE;
    
    $query =<<<SQL
INSERT INTO `$tbl`(
  `name`, `email`, `auth_key`, `customer_id`, `created_on`, `session_id`,
  `session_updated`, `ip`, `user_hash`
) VALUES ('%s', '%s', '%s', '%s', %d, '%s', %d, '%s', '%s')
SQL;
    
    $res_customer = mysql_global_call($query,
    //$res_customer = sprintf($query,
      $name,
      $email,
      $auth_key_hashed,
      $customer_id,
      $now,
      $session_id,
      $now,
      $ip,
      $user_hash
    );
    
    if (!$res_customer) {
      //mysql_global_call('ROLLBACK')
      $this->error('Database Error (i1)');
    }
    
    $customer_internal_id = mysql_global_insert_id();
    
    if (!$customer_internal_id) {
      //mysql_global_call('ROLLBACK')
      $this->error('Database Error (i2)');
    }
    
    //Campaign
    $tbl = self::CAMPAIGNS_TABLE;
    
    $query =<<<SQL
INSERT INTO `$tbl`(
  `customer_id`, `campaign_id`, `transaction_id`, `ip`, `created_on`, `starts_on`,
  `ends_on`, `impressions`, `price_cents`, `settings`, `status`, `reject_reason`,
  `url`, `file_name`, `file_size`, `placement`
) VALUES (%d, '%s', '%s', '%s', %d, %d,
%d, %d, %d, '%s', %d, '',
'%s', '%s', %d, '%s')
SQL;
    
    $res_campaign = mysql_global_call($query,
    //$res_campaign = sprintf($query,
      $customer_internal_id,
      '',
      '',
      $ip,
      $now,
      $starts_on,
      $ends_on,
      $impressions,
      $amount_cents,
      $settings,
      self::STATUS_PENDING,
      $click_url,
      '', // file name
      0, // file size
      $placement
    );
    
    if (!$res_campaign) {
      $this->error('Database Error (i3)');
    }
    
    //if (!mysql_global_call('COMMIT')) {
    //  $this->error('Database Error (i4)');
    //}
    
    //$this->renderHTML('banner-contest-sent');
    
    echo 'ok';
  }
  
  /**
   * Default page
   */
  public function index() {
    $this->keywords = $this->get_valid_keywords();
    
    $this->countries = $this->get_valid_countries();
    
    $this->renderHTML('self-ads');
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
