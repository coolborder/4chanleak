<?php

require_once 'lib/db.php';
require_once 'lib/captcha.php';

//$mysql_suppress_err = false;
//ini_set('display_errors', 1);
//error_reporting(E_ALL);

// Set to false to close applications
define('APPLICATIONS_OPEN', false);

// Boards for which the applications are NOT being accepted
define('INVALID_BOARDS', 'b s4s qa trash bant');

// Only accepts applications for the following boards, if defined
//define('ONLY_BOARDS', 'tv');

// ---

if (APPLICATIONS_OPEN) {
  require_once 'lib/db.php';
  require_once 'lib/captcha.php';
  
  mysql_global_connect();
}

define('IN_APP', true);

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

define('WEB_DOMAIN', IS_4CHANNEL ? '4channel.org' : '4chan.org');

class JanitorApplications {
  protected
    // Routes
    $actions = array(
      'index',
      'submit'
    );
  
  private $tz_names = array(
    -10 => 'HAST',
    -9 => 'AKST',
    -8 => 'PST',
    -7 => 'MST',
    -6 => 'CST',
    -5 => 'EST',
    -4 => 'AST',
    -3 => 'BRT',
    0 => 'GMT',
    1 => 'CET',
    2 => 'EET',
    8 => 'AWST',
    9 => 'JST',
    10 => 'AEST'
  );
  
  private $recaptcha_public_key = '6Ldp2bsSAAAAAAJ5uyx_lx34lJeEpTLVkP5k04qc';
  private $recaptcha_private_key = '6Ldp2bsSAAAAAN2MLRwLc15YclEWm2W4Uc2uBGaC';
  
  const TPL_ROOT = 'views/';
  
  const WEB_ROOT = '/janitorapp';
  
  const TABLE = 'janitor_apps';
  
  const STATUS_IGNORED = 9;
  
  const
    MIN_AGE = 18,
    
    MAX_FIELD_LENGTH = 255,
    
    MAX_TXT_FIELD_LENGTH = 4096,
    MIN_TOTAL_TXT_FIELD_LENGTH = 1024,
    
    MAX_NUM_VALUE = 1024,
    
    MAX_BAN_COUNT = 10,
    BAN_CHECK_INTERVAL = '6 MONTH'
  ;
  
  const HMAC_SECRET = 'ec5f20ad826535921c98f37b7da9c6535e5518c84f6d40adcb83e0bb9f9d6256';
  
  const TOKEN_MIN_TTL_SEC = 3;
  
  const
    ERR_GENERIC = 'Internal Server Error',
    ERR_APP_NOT_FOUND = 'Application not found.',
    ERR_APPS_CLOSED = 'Janitor applications are closed.',
    ERR_INVALID_FIELD = 'Invalid value for: ',
    ERR_DUP = 'There is already an application with this email.',
    ERR_BAD_CAPTCHA = 'You seem to have mistyped the CAPTCHA.',
    ERR_NOT_DESKTOP = 'Submitting applications from mobile devices is not allowed.',
    ERR_PROXY = 'Submitting applications from proxies is not allowed.'
  ;
  
  const
    STR_Q1 = "Describe your expertise in the board's subject matter.",
    STR_Q2 = "What are the main problems facing the board?",
    STR_Q3 = "What is your favorite thing about the board?",
    STR_Q4 = "What makes you a particularly good applicant for the team?"
  ;
  
  private function error($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function generate_token() {
    $bytes = openssl_random_pseudo_bytes(8);
    
    if (!$bytes) {
      $this->error(self::ERR_GENERIC . ' (gt0)');
    }
    
    $bytes = bin2hex($bytes);
    
    $time = $_SERVER['REQUEST_TIME'];
    
    $ip_parts = explode('.', $_SERVER['REMOTE_ADDR'], 3);
    $ip_mask  = "{$ip_parts[0]}.{$ip_parts[1]}";
    
    $data = [];
    $data[] = $time;
    $data[] = $bytes;
    $data[] = hash_hmac('sha1', "$time $ip_mask $bytes", self::HMAC_SECRET);
    
    return implode('.', $data);
  }
  
  private function is_token_valid($token) {
    list($time, $bytes, $token_sig) = explode('.', $token);
    
    $ip_parts = explode('.', $_SERVER['REMOTE_ADDR'], 3);
    $ip_mask  = "{$ip_parts[0]}.{$ip_parts[1]}";
    
    $this_sig = hash_hmac('sha1', "$time $ip_mask $bytes", self::HMAC_SECRET);
    
    if ($this_sig !== $token_sig) {
      return false;
    }
    
    if ($_SERVER['REQUEST_TIME'] - $time < self::TOKEN_MIN_TTL_SEC) {
      return false;
    }
    
    return true;
  }
  
  private function is_desktop_browser() {
    return preg_match('/\b(Linux|X11|Macintosh|Windows)\b/', $_SERVER['HTTP_USER_AGENT']) &&
      !preg_match('/\b(Android|iPhone)\b/', $_SERVER['HTTP_USER_AGENT']);
  }
  
  private function is_ip_rangebanned() {
    $long_ip = ip2long($_SERVER['REMOTE_ADDR']);
    
    if (!$long_ip) {
      return true;
    }
    
    $query = <<<SQL
SELECT COUNT(*) as cnt
FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip
AND active = 1 AND boards = '' AND expires_on = 0
AND ops_only = 0 AND img_only = 0 AND lenient = 0 AND ua_ids = ''
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return true;
    }
    
    if ((int)mysql_fetch_row($res)[0]) {
      return true;
    }
    
    return false;
  }
  
  public function is_bot_spam() {
    if (!preg_match('/^[a-z]+[A-Z][a-z]+[0-9]?$/', $_POST['handle'])) {
      return false;
    }
    
    if (!preg_match('/^[0-9]+$/', $_POST['times'])) {
      return false;
    }
    
    if (strpos($_POST['email'], '@gmail.com') === false) {
      return false;
    }
    /*
    if ($_SERVER['HTTP_USER_AGENT'] !== 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:80.0) Gecko/20100101 Firefox/80.0') {
      return false;
    }
    */
    if ($_SERVER['HTTP_ACCEPT_LANGUAGE'] !== 'en-US,en;q=0.5') {
      return false;
    }
    
    if (preg_match('/\/[a-z0-9]{1,4}\//', "{$_POST['q1']}{$_POST['q2']}{$_POST['q3']}{$_POST['q4']}")) {
      return false;
    }
    
    return true;
  }
  
  public function is_ip_known() {
    $long_ip = ip2long($_SERVER['REMOTE_ADDR']);
    
    if (!$long_ip) {
      return true;
    }
    
    // Not before
    $minutes_min = 720;
    
    // Not after (5 days)
    $minutes_max = 7200;
    
    // At least X replies
    $posts_min = 1;
    
    // check posting history
    $query = <<<SQL
SELECT COUNT(*) FROM user_actions
WHERE ip = $long_ip AND action = 'new_reply'
AND (time BETWEEN DATE_SUB(NOW(), INTERVAL $minutes_max MINUTE) AND DATE_SUB(NOW(), INTERVAL $minutes_min MINUTE))
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    $count = (int)mysql_fetch_row($res)[0];
    
    if ($count < $posts_min) {
      return false;
    }
    
    return true;
  }
  
  /**
   * Verify captcha. Dies on failure.
   */
  private function verify_captcha() {
    $response = $_POST["g-recaptcha-response"];
    
    if (!$response) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    $response = urlencode($response);
    
    $rlen = strlen($response);
    
    if ($rlen > 4096) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    $api_url = "https://www.google.com/recaptcha/api/siteverify?secret={$this->recaptcha_private_key}&response=$response";
    
    $recaptcha_ch = rpc_start_request($api_url, null, null, false);
    
    if (!$recaptcha_ch) {
      $this->error(self::ERR_BAD_CAPTCHA); // not really
    }
    
    $ret = rpc_finish_request($recaptcha_ch, $error, $httperror);
    
    // BAD
    // 413 Request Too Large is bad; it was caused intentionally by the user.
    if ($httperror == 413) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    // BAD
    if ($ret == null) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    $resp = json_decode($ret, true);
    
    // BAD
    // Malformed JSON response from Google
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    // GOOD
    if ($resp['success']) {
      return true;
    }
    
    // BAD
    $this->error(self::ERR_BAD_CAPTCHA);
  }
  
  private function generate_unique_id() {
    $bytes = openssl_random_pseudo_bytes(32);
    
    $str = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='); 
    
    if (!$str) {
      $this->error(self::ERR_GENERIC . ' (gui1)');
    }
    
    return $str;
  }
  
  private function get_valid_boards() {
    $query = 'SELECT dir FROM boardlist ORDER BY dir ASC';
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    if (!$result) {
      return $boards;
    }
    
    $invalid_boards = explode(' ', INVALID_BOARDS);
    
    if (defined('ONLY_BOARDS') && ONLY_BOARDS) {
      $only_boards = explode(' ', ONLY_BOARDS);
    }
    else {
      $only_boards = false;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      if ($only_boards && !in_array($board['dir'], $only_boards)) {
        continue;
      }
      
      if (in_array($board['dir'], $invalid_boards)) {
        continue;
      }
      
      $boards[$board['dir']] = true;
    }
    
    return $boards;
  }
  
  private function get_app_by_uid_and_email($uid, $email) {
    $tbl = self::TABLE;
    
    $email = strtolower($email);
    
    $query = "SELECT * FROM `$tbl` WHERE unique_id = '%s' AND email = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $uid, $email);
    
    if (!$res) {
      $this->error(self::ERR_GENERIC . ' (gabu1)');
    }
    
    return mysql_fetch_assoc($res);
  }
  
  private function app_exists_by_email($email) {
    $email = strtolower($email);
    
    $tbl = self::TABLE;
    
    $query = "SELECT id FROM `$tbl` WHERE email = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $email);
    
    if (!$res) {
      $this->error(self::ERR_GENERIC . ' (cabe1)');
    }
    
    return mysql_num_rows($res) !== 0;
  }
  
  private function flatten_field($value) {
    return trim(preg_replace('/[\n\r]+/', ' ', $value));
  }
  
  private function is_post_field_valid($field, $max_len) {
    return isset($_POST[$field]) && $_POST[$field] !== '' && mb_strlen($_POST[$field]) <= $max_len;
  }
  
  private function validate_ip_ban_history($ip) {
    $interval = self::BAN_CHECK_INTERVAL;
    
    $query =<<<SQL
SELECT COUNT(no) as cnt FROM banned_users
WHERE host = '%s'
AND global = 1
AND now >= DATE_SUB(NOW(), INTERVAL $interval)
LIMIT 1
SQL;
    
    $result = mysql_global_call($query, $ip);
    
    if (!$result) {
      $this->error(self::ERR_GENERIC . ' (cbfi1)');
    }
    
    $result = mysql_fetch_assoc($result);
    
    return (int)$result['cnt'] < self::MAX_BAN_COUNT;
  }
  
  public function submit() {
    if (APPLICATIONS_OPEN !== true) {
      $this->error(self::ERR_APPS_CLOSED);
    }
    
    $this->verify_captcha();
    
    if (!$this->is_desktop_browser()) {
      $this->error(self::ERR_NOT_DESKTOP);
    }
    
    if ($this->is_ip_rangebanned()) {
      $this->error(self::ERR_PROXY);
    }
    
    $ip_known = $this->is_ip_known() ? 1 : 0;
    
    $valid_boards = $this->get_valid_boards();
    
    $tbl = self::TABLE;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $now = $_SERVER['REQUEST_TIME'];
    
    // Name
    if (!$this->is_post_field_valid('firstname', self::MAX_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . 'First Name');
    }
    
    $first_name = $this->flatten_field($_POST['firstname']);
    
    // Nickname
    if (!$this->is_post_field_valid('handle', self::MAX_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . 'Online Nickname / Handle');
    }
    
    $nickname = $this->flatten_field($_POST['handle']);
    
    // Email
    if (!$this->is_post_field_valid('email', self::MAX_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . 'Email');
    }
    
    $email = strtolower($this->flatten_field($_POST['email']));
    
    if (!preg_match('/.+@.+/', $email)) {
      $this->error(self::ERR_INVALID_FIELD . 'Email');
    }
    
    // Age
    if (!$this->is_post_field_valid('age', self::MAX_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . 'Age');
    }
    
    $age = (int)$_POST['age'];
    
    if ($age < 1 || $age > self::MAX_NUM_VALUE) {
      $this->error(self::ERR_INVALID_FIELD . 'Age');
    }
    
    // Timezone
    if (!$this->is_post_field_valid('tz', self::MAX_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . 'Timezone');
    }
    
    $tz = (int)$_POST['tz'];
    
    if ($tz < -12 || $tz > 13) {
      $this->error(self::ERR_INVALID_FIELD . 'Timezone');
    }
    
    // Hours
    if (!$this->is_post_field_valid('hours', self::MAX_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . 'hours');
    }
    
    $hours = (int)$_POST['hours'];
    
    if ($hours < 1 || $hours > self::MAX_NUM_VALUE) {
      $this->error(self::ERR_INVALID_FIELD . 'Hours');
    }
    
    // Nickname
    if (!$this->is_post_field_valid('times', self::MAX_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . 'Hours available for janitoring');
    }
    
    $time_frames = $this->flatten_field($_POST['times']);
    
    // Board 1
    if (!isset($_POST['board1']) || !isset($valid_boards[$_POST['board1']])) {
      $this->error(self::ERR_INVALID_FIELD . 'Board');
    }
    
    $board1 = $_POST['board1'];
    
    // Board 2
    if (isset($_POST['board2']) && $_POST['board2'] !== '') {
      if (!isset($valid_boards[$_POST['board2']])) {
        $this->error(self::ERR_INVALID_FIELD . 'Board');
      }
      $board2 = $_POST['board2'];
    }
    else {
      $board2 = '';
    }
    
    // q1
    if (!$this->is_post_field_valid('q1', self::MAX_TXT_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . self::STR_Q1);
    }
    
    $q1 = trim($_POST['q1']);
    
    // q2
    if (!$this->is_post_field_valid('q2', self::MAX_TXT_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . self::STR_Q2);
    }
    
    $q2 = trim($_POST['q2']);
    
    // q3
    if (!$this->is_post_field_valid('q3', self::MAX_TXT_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . self::STR_Q3);
    }
    
    $q3 = trim($_POST['q3']);
    
    // q4
    if (!$this->is_post_field_valid('q4', self::MAX_TXT_FIELD_LENGTH)) {
      $this->error(self::ERR_INVALID_FIELD . self::STR_Q4);
    }
    
    $q4 = trim($_POST['q4']);
    
    $status = 0;
    
    // Check if the applications needs to be auto-ignored
    if (mb_strlen($q1 . $q2 . $q3 . $q4) < self::MIN_TOTAL_TXT_FIELD_LENGTH) {
      $status = self::STATUS_IGNORED;
    }
    
    if ($status === 0 && $age < self::MIN_AGE) {
      $status = self::STATUS_IGNORED;
    }
    
    if ($status === 0 && !$this->validate_ip_ban_history($ip)) {
      $status = self::STATUS_IGNORED;
    }
    
    // Special checks for spam
    if (!$ip_known && $this->is_bot_spam()) {
      $status = self::STATUS_IGNORED;
    }
    
    // -----
    // Updating
    // -----
    if (isset($_POST['id'])) {
      $application = $this->get_app_by_uid_and_email($_POST['id'], $_POST['auth_email']);
      
      if (!$application) {
        $this->error(self::ERR_APP_NOT_FOUND);
      }
      
      if ($application['email'] !== $email && $this->app_exists_by_email($email)) {
        $this->error(self::ERR_DUP);
      }
      
      $sql =<<<SQL
UPDATE `$tbl` SET
firstname = '%s',
handle = '%s',
email = '%s',
age = %d,
tz = %d,
hours = %d,
`times` = '%s',
http_ua = '%s',
http_lang = '%s',
ip_known = $ip_known,
board1 = '%s',
board2 = '%s',
q1 = '%s',
q2 = '%s',
q3 = '%s',
q4 = '%s',
ip = '%s',
closed = %d
WHERE unique_id = '%s' LIMIT 1
SQL;
      
      $res = mysql_global_call($sql,
        $first_name, $nickname, $email, $age, $tz, $hours, $time_frames,
        $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT_LANGUAGE'],
        $board1, $board2,
        $q1, $q2, $q3, $q4, $ip, $status,
        $application['unique_id']
      );
      
      if (!$res) {
        $this->error(self::ERR_GENERIC);
      }
      
      $this->unique_id = $application['unique_id'];
    }
    // -----
    // Creating
    // -----
    else {
      if ($this->app_exists_by_email($email)) {
        $this->error(self::ERR_DUP);
      }
      
      if (!isset($_POST['_cf_fuid']) || $_POST['_cf_fuid'] === '') {
        $status = self::STATUS_IGNORED;
      }
      
      if (!$this->is_token_valid($_POST['_cf_fuid'])) {
        $status = self::STATUS_IGNORED;
      }
        
      $unique_id = $this->generate_unique_id();
      
      $sql =<<<SQL
INSERT INTO `$tbl` SET
unique_id = '%s',
firstname = '%s',
handle = '%s',
email = '%s',
age = %d,
tz = %d,
hours = %d,
`times` = '%s',
http_ua = '%s',
http_lang = '%s',
ip_known = $ip_known,
board1 = '%s',
board2 = '%s',
q1 = '%s',
q2 = '%s',
q3 = '%s',
q4 = '%s',
ip = '%s',
closed = %d
SQL;
      
      $res = mysql_global_call($sql,
        $unique_id,
        $first_name, $nickname, $email, $age, $tz, $hours, $time_frames,
        $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT_LANGUAGE'],
        $board1, $board2,
        $q1, $q2, $q3, $q4, $ip, $status
      );
      
      if (!$res) {
        $this->error(self::ERR_GENERIC);
      }
      
      $this->unique_id = $unique_id;
    }
    
    $this->renderHTML('janitorapp-success');
  }
  
  public function index() {
    $this->application = null;
    $this->app_uid = null;
    $this->auth_email = null;
    $this->need_auth_email = false;
    
    if (APPLICATIONS_OPEN === true) {
      if (isset($_GET['id']) && $_GET['id']) {
        if (isset($_POST['auth_email']) && $_POST['auth_email']) {
          $this->verify_captcha();
          
          $this->application = $this->get_app_by_uid_and_email($_GET['id'], $_POST['auth_email']);
          
          if ($this->application) {
            $this->app_uid = $this->application['unique_id'];
            $this->auth_email = strtolower($_POST['auth_email']);
          }
          else {
            $this->error(self::ERR_APP_NOT_FOUND);
          }
        }
        else {
          $this->need_auth_email = true;
          $this->auth_uid = $_GET['id'];
        }
      }
      
      $this->valid_boards = $this->get_valid_boards();
    }
    
    $this->renderHTML('janitorapp');
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

$ctrl = new JanitorApplications();
$ctrl->run();
