<?php
require_once 'lib/db.php';
require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

mysql_global_connect();

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'vote'/*,
      'debug'*/
    );
  
  private $_salt = null;
  
  const
    DATE_FORMAT_SHORT ='m/d/y',
    
    SALT_PATH = '/www/keys/2014_admin.salt'
  ;
  
  const
    POLLS_TABLE = 'polls',
    OPTIONS_TABLE = 'poll_options',
    VOTES_TABLE = 'poll_votes',
    MODS_TABLE = 'mod_users'
  ;
  
  const
    S_ALREADY_VOTED = 'You have already voted in this poll.',
    S_BAD_POLL = 'Poll not found.'
  ;
  
  const
    STATUS_DISABLED = 0,
    STATUS_ACTIVE = 1
  ;
  
  const WEBROOT = '/polls';
  
  const CSRF_TKN = '_ptkn';
  
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
    setcookie(self::CSRF_TKN, $tkn, 0, '/polls', 'www.4chan.org', true);
  }
  
  private function set_cache($flag) {
    if (!$flag) {
      header('Cache-Control: no-cache');
    }
  }
  
  /**
   * Verify captcha. Dies on failure.
   */
  private function verifyCaptcha() {
    global $recaptcha_public_key, $recaptcha_private_key;
    
    $response = $_POST["g-recaptcha-response"];
    
    if (!$response) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    $response = urlencode($response);
    
    $rlen = strlen($response);
    
    if ($rlen > 2048) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    $api_url = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_private_key}&response=$response";
    
    $recaptcha_ch = rpc_start_request($api_url, null, null, false);
    
    if (!$recaptcha_ch) {
    	$this->error(self::S_BAD_CAPTCHA); // not really
    }
    
    $ret = rpc_finish_request($recaptcha_ch, $error, $httperror);
    
    // BAD
    // 413 Request Too Large is bad; it was caused intentionally by the user.
    if ($httperror == 413) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    // BAD
    if ($ret == null) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    $resp = json_decode($ret, true);
    
    // BAD
    // Malformed JSON response from Google
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    // GOOD
    if ($resp['success']) {
      return true;
    }
    
    // BAD
    $this->error(self::S_BAD_CAPTCHA);
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
      
      if (!isset($_COOKIE[self::CSRF_TKN]) || !isset($_POST[self::CSRF_TKN])
        || $_COOKIE[self::CSRF_TKN] == '' || $_POST[self::CSRF_TKN] == ''
        || $_COOKIE[self::CSRF_TKN] !== $_POST[self::CSRF_TKN]) {
        return false;
      }
    }
    
    return true;
  }
  
  private function init_salt() {
    if (!$this->_salt) {
      $this->_salt = file_get_contents(self::SALT_PATH);
    }
    
    if (!$this->_salt) {
      $this->error('Internal Server Error (is0)');
    }
  }
  
  private function get_voter_id() {
    if (!$this->_salt) {
      $this->init_salt();
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    if (isset($_COOKIE['pass_id']) && $_COOKIE['pass_id'] !== '') {
      $pass_parts = explode('.', $_COOKIE['pass_id']);
      
      $pass_user = $pass_parts[0];
      $pass_session = $pass_parts[1];
      
      if (!$pass_user || !$pass_session) {
        return false;
      }
      
      $query = <<<SQL
SELECT user_hash, session_id, status,
UNIX_TIMESTAMP(expiration_date) as expiration_date
FROM pass_users WHERE pin != '' AND user_hash = '%s'
SQL;
      
      $res = mysql_global_call($query, $pass_user);
      
      if (!$res) {
        $this->error('Database Error (gvi1)');
      }
      
      $pass = mysql_fetch_assoc($res);
      
      if (!$pass || !$pass['session_id']) {
        return false;
      }
      
      $hashed_pass_session = substr(hash('sha256', $pass['session_id'] . $this->_salt), 0, 32);
      
      if ($hashed_pass_session !== $pass_session) {
        return false;
      }
      
      if ((int)$pass['expiration_date'] <= $now) {
        return false;
      }
      
      if ($pass['status'] != 0) {
        return false;
      }
      
      return hash('sha256', $pass['user_hash'] . $this->_salt);
    }
    else if (isset($_COOKIE['4chan_auser']) && $_COOKIE['4chan_auser'] !== '') {
      $username = $_COOKIE['4chan_auser'];
      $password = $_COOKIE['apass'];
      
      if (!$username || !$password) {
        return false;
      }
      
      $query = "SELECT * FROM `" . self::MODS_TABLE . "` WHERE `username` = '%s' LIMIT 1";
      
      $res = mysql_global_call($query, $username);
      
      if (!$res) {
        $this->error('Database Error (gvi2)');
      }
      
      $user = mysql_fetch_assoc($res);
      
      if (!$user) {
        return false;
      }
      
      $hashed_admin_password = hash('sha256', $user['username'] . $user['password'] . $this->_salt);
      
      if ($hashed_admin_password !== $password) {
        return false;
      }
      
      if ($user['password_expired'] == 1) {
        return false;
      }
      
      return hash('sha256', $user['id'] . $this->_salt);
    }
    
    return false;
  }
  
  private function disable_expired() {
    $now = (int)$_SERVER['REQUEST_TIME'];
    
    $tbl = self::POLLS_TABLE;
    $status_active = self::STATUS_ACTIVE;
    $status_expired = self::STATUS_DISABLED;
    
    $query =<<<SQL
UPDATE `$tbl` SET status = $status_expired
WHERE  status = $status_active AND expires_on > 0 AND expires_on <= $now
SQL;
    
    return !!mysql_global_call($query);
  }
  
  /**
   * Default page
   */
  public function index() {
    //$this->set_cache(true);
    
    $this->disable_expired();
    
    if (isset($_GET['id']) && $_GET['id'] !== '') {
      $this->view_poll();
      return;
    }
    
    if (isset($_GET['results']) && $_GET['results'] !== '') {
      $this->view_results();
      return;
    }
    
    $tbl = self::POLLS_TABLE;
    $status_active = self::STATUS_ACTIVE;
    
    $query =<<<SQL
SELECT id, title
FROM `$tbl` WHERE status = $status_active ORDER BY id DESC
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error', 500);
    }
    
    $this->items = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    $this->renderHTML('polls');
  }
  
  private function view_poll() {
    $polls_tbl = self::POLLS_TABLE;
    $options_tbl = self::OPTIONS_TABLE;
    $status_active = self::STATUS_ACTIVE;
    
    $id = (int)$_GET['id'];
    
    $this->poll_id = $id;
    
    $query = "SELECT title, description FROM $polls_tbl WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1)', 500);
    }
    
    $this->poll = mysql_fetch_assoc($res);
    
    if (!$this->poll) {
      $this->error('Poll not found.', 404);
    }
    
    $query = "SELECT id, caption FROM $options_tbl WHERE poll_id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (2)', 500);
    }
    
    $this->options = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->options[] = $row;
    }
    
    $this->_tkn = $this->get_csrf_token();
    
    $this->renderHTML('polls-view');
  }
  
  private function view_results() {
    $polls_tbl = self::POLLS_TABLE;
    $options_tbl = self::OPTIONS_TABLE;
    $votes_tbl = self::VOTES_TABLE;
    
    $id = (int)$_GET['results'];
    
    $this->poll_id = $id;
    
    // Poll
    $query = "SELECT title, description, vote_count FROM $polls_tbl WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1)', 500);
    }
    
    $this->poll = mysql_fetch_assoc($res);
    
    if (!$this->poll) {
      $this->error('Poll not found.', 404);
    }
    
    // Options
    $query = "SELECT id, caption FROM $options_tbl WHERE poll_id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (2)', 500);
    }
    
    $this->options = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->options[] = $row;
    }
    
    // Votes
    $query = "SELECT option_id, COUNT(*) as cnt FROM $votes_tbl WHERE poll_id = $id GROUP BY option_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (3)');
    }
    
    $this->scores = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->scores[$row['option_id']] = $row['cnt'];
    }
    
    $this->renderHTML('polls-view');
  }
  
  /**
   * Vote
   */
  public function vote() {
    $this->set_cache(false);
    
    if (!$this->validate_csrf()) {
      $this->error('Bad request.');
    }
    
    if (!isset($_POST['id']) || $_POST['id'] === '') {
      $this->error('Bad request.');
    }
    
    $voter_id = $this->get_voter_id();
    
    if (!$voter_id) {
      $this->error('Only 4chan Pass users can vote.');
    }
    
    $polls_tbl = self::POLLS_TABLE;
    $options_tbl = self::OPTIONS_TABLE;
    $votes_tbl = self::VOTES_TABLE;
    
    // Option
    $option_id = (int)$_POST['id'];
    
    $query = "SELECT * FROM `$options_tbl` WHERE id = %d";
    
    $res = mysql_global_call($query, $option_id);
    
    if (!$res) {
      $this->error('Database Error (1)');
    }
    
    $opt = mysql_fetch_assoc($res);
    
    if (!$opt) {
      $this->error(self::S_BAD_POLL);
    }
    
    // Poll
    $poll_id = (int)$opt['poll_id'];
    
    $query = "SELECT status, expires_on FROM `$polls_tbl` WHERE id = %d";
    
    $res = mysql_global_call($query, $poll_id);
    
    if (!$res) {
      $this->error('Database Error (1-1)');
    }
    
    $poll = mysql_fetch_assoc($res);
    
    if (!$poll) {
      $this->error(self::S_BAD_POLL);
    }
    
    if ((int)$poll['status'] !== self::STATUS_ACTIVE) {
      $this->error(self::S_BAD_POLL);
    }
    
    if ($poll['expires_on'] && (int)$poll['expires_on'] < $_SERVER['REQUEST_TIME']) {
      $this->error(self::S_BAD_POLL);
    }
    
    // Already voted
    $query = "SELECT id FROM `$votes_tbl` WHERE voter_id = '%s' AND poll_id = %d";
    
    $res = mysql_global_call($query, $voter_id, $poll_id);
    
    if (!$res) {
      $this->error('Database Error (2-2)');
    }
    
    if (mysql_num_rows($res) > 0) {
      $this->error(self::S_ALREADY_VOTED);
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    //mysql_global_call('START TRANSACTION');
    
    $query = <<<SQL
INSERT INTO `$votes_tbl` (voter_id, poll_id, option_id)
VALUES ('%s', %d, %d)
SQL;
    
    $res = mysql_global_call($query, $voter_id, $poll_id, $option_id);
    
    if (!$res) {
      $this->error('Database Error (3)');
    }
    
    $query = "UPDATE `$polls_tbl` SET vote_count = vote_count + 1 WHERE id = %d LIMIT 1";
    
    $res = mysql_global_call($query, $poll_id);
    
    if (!$res) {
      //mysql_global_call('ROLLBACK');
      $this->error('Database Error (4)');
    }
    
    //mysql_global_call('COMMIT');
    
    $this->success(null, self::WEBROOT);
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
