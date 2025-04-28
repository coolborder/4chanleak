<?php
require_once 'lib/db.php';
require_once 'lib/admin.php';
require_once 'lib/auth.php';
require_once 'lib/captcha.php';
require_once 'lib/userpwd.php';

define('IN_APP', true);

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

mysql_global_connect();

class SuggestionBox {
  protected
    // Routes
    $actions = array(
      'index',
      'submit',
      'reply',
      'search',
      'read',
      'review',
      'show',
      'dismiss',
      'approve'/*,
      'dismiss_old'*/
    ),
    
    // Number of entries per page
    $page_size = 25,
    
    // Categories for suggestions (displayed in brackets)
    $categories = array(
      0 => 'Feedback',
      1 => 'Bug',
      2 => 'Feature',
      3 => 'Spam Filter',
      4 => 'Range Ban',
      5 => 'New Board'
    ),
    
    // Categories for suggestions (displayed in dropdown menu)
    $categoriesLabels = array(
      0 => 'Moderation Feedback',
      1 => 'Bug Report',
      2 => 'Feature Suggestion',
      3 => 'Spam Filter Issue',
      4 => 'IP Range Ban Issue',
      5 => 'Board Suggestion'
    ),
    
    $current_cat = null,
    
    // Reply status icons
    $reply_icons = array(
      'mod' => 'modicon.gif',
      'admin' => 'adminicon.gif',
      'developer' => 'developericon.gif'/*,
      'manager' => 'managericon.gif'*/
    ),
    
    // Reply status labels
    $reply_labels = array(
      'mod' => 'Anonymous ## Mod',
      'admin' => 'Anonymous ## Admin',
      'developer' => 'Anonymous ## Developer'/*,
      'manager' => 'Anonymous ## Manager'*/
    ),
    
    // Field lengths
    $max_len_email = 150,
    $max_len_subject = 150,
    $max_len_message = 5000,
    $max_len_message_reply = 20000,
    
    // Maximum number of submissions per day per IP
    $max_daily_submissions = 3,
    
    // reCaptcha
    $recaptcha_public_key = '6Ldp2bsSAAAAAAJ5uyx_lx34lJeEpTLVkP5k04qc',
    $recaptcha_private_key = '6Ldp2bsSAAAAAN2MLRwLc15YclEWm2W4Uc2uBGaC',
    
    // Error messages
    $errstr = array(
      'bad_req' => 'Bad request.',
      'no_captcha' => 'You forgot to solve the CAPTCHA.',
      'captcha_server_err' => "Couldn't verify the CAPTCHA.",
      'captcha_expired' => 'This CAPTCHA is no longer valid because it has expired.',
      'wrong_captcha' => 'You seem to have mistyped the CAPTCHA.',
      'permabanned' => "You can't submit feedback when permanently banned.",
      'db_err' => 'Database error.',
      'flood_daily' => 'You can only submit %s per day.',
      'flood_blacklisted' => "Submitting feedback from this IP has been blocked due to abuse.",
      'bad_category' => 'Invalid category.',
      'bad_email' => 'Invalid E-Mail address',
      'long_email' => 'E-Mail address is too long.',
      'empty_subject' => 'Subject cannot be empty.',
      'long_subject' => 'Subject is too long.',
      'no_msg' => 'Message cannot be empty.',
      'long_msg' => 'Message is too long.',
      'no_query' => 'Query cannot be empty.',
      'no_results' => 'Nothing found.',
      'bad_id' => 'Invalid ID.',
      'bad_capcode' => 'You cannot post with that capcode.'
    ),
    
    // Success messages
    $succstr = array(
      'question_done' => 'Success! Returning to index...',
      'answer_done' => 'Success! Returning to index...'
    ),
    
    // Allowed server for admin interface
    $admin_server = array('team.4chan.org', 'team_cf.4chan.org'),
    
    // Blacklisted IPs
    $cidr_blacklist = array(
      '73.70.240.228/32' // !RHAJATxdtU flooder
    )
    ;
  
  /**
   * Checks the server name for admin tasks. Dies on failure.
   */
  private function checkServer() {
    if (!in_array($_SERVER['SERVER_NAME'], $this->admin_server)) {
      die();
    }
  }
  
  private function cidr_test($longip, $CIDR) {
    list ($net, $mask) = explode("/", $CIDR);
    
    $mask = (int)$mask;
    
    if (!$mask) return false;
    
    $ip_net = ip2long($net);
    
    if (!$ip_net) return false;
    
    $ip_mask = ~((1 << (32 - $mask)) - 1);
    
    $ip_ip = $longip;
    
    $ip_ip_net = $ip_ip & $ip_mask;
    
    return ($ip_ip_net == $ip_net);
  }
  
  private function is_ip_blacklisted($ip) {
    $longip = ip2long($ip);
    
    if (!$longip) {
      return false;
    }
    
    foreach ($this->cidr_blacklist as $cidr) {
      if ($this->cidr_test($longip, $cidr)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Returns the data as json
   */
  final protected function success($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function error($message, $code = null, $data = null) {
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
  
  /**
   * Error and success messages when in admin mode
   */
  private function successAdmin($message, $redirect_to = null, $redirect_time = 3) {
    $this->message = $message;
    $this->mode = 'success';
    
    if ($redirect_to !== null) {
      $this->redirect_to = $redirect_to;
      $this->redirect_time = $redirect_time;
    }
    
    $this->renderHTML('feedback-review');
  }
  
  private function errorAdmin($message) {
    $this->message = $message;
    $this->mode = 'error';
    $this->renderHTML('feedback-review');
    die();
  }
  
  /**
   * Renders error HTML template and dies
   */
  private function errorHTML($message) {
    $this->message = $message;
    $this->mode = 'error';
    $this->renderHTML('feedback');
    die();
  }
  
  /**
   * Renders success HTML template
   */
  private function successHTML($message, $redirect_to = null, $redirect_time = 3) {
    $this->message = $message;
    $this->mode = 'success';
    
    if ($redirect_to !== null) {
      $this->redirect_to = $redirect_to;
      $this->redirect_time = $redirect_time;
    }
    
    $this->renderHTML('feedback');
  }
  
  /**
   * Returns a JSON response
   */
  private function renderJSON($data) {
    header('Content-type: application/json');
    echo json_encode($data);
  }
  
  /**
   * Trims whitespace and removes zerowidth characters
   */
  private function cleanString($str) {
    return trim(preg_replace('/(\x{000B}|\x{00A0}|\x{FEFF}|\x{00AD}|[\x{2000}-\x{200F}]|[\x{2028}-\x{202F}]|[\x{2060}-\x{206F}])/u', '', $str));
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    include('views/' . $view . '.tpl.php');
  }
  
  /**
   * Verify captcha. Dies on failure.
   */
  private function verifyCaptcha() {
    $response = $_POST["g-recaptcha-response"];
    
    if (!$response) {
      $this->errorHTML($this->errstr['no_captcha']);
    }
    
    $response = urlencode($response);
    
    $rlen = strlen($response);
    
    if ($rlen > 4096) {
      $this->errorHTML($this->errstr['wrong_captcha']);
    }
    
    $api_url = "https://www.google.com/recaptcha/api/siteverify?secret={$this->recaptcha_private_key}&response=$response";
    
    $recaptcha_ch = rpc_start_request($api_url, null, null, false);
    
    if (!$recaptcha_ch) {
    	$this->errorHTML($this->errstr['wrong_captcha']); // not really
    }
    
    $ret = rpc_finish_request($recaptcha_ch, $error, $httperror);
    
    // BAD
    // 413 Request Too Large is bad; it was caused intentionally by the user.
    if ($httperror == 413) {
      $this->errorHTML($this->errstr['wrong_captcha']);
    }
    
    // BAD
    if ($ret == null) {
      $this->errorHTML($this->errstr['wrong_captcha']);
    }
    
    $resp = json_decode($ret, true);
    
    // BAD
    // Malformed JSON response from Google
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->errorHTML($this->errstr['wrong_captcha']);
    }
    
    // GOOD
    if ($resp['success']) {
      return true;
    }
    
    // BAD
    $this->errorHTML($this->errstr['wrong_captcha']);
  }
  
  /**
   * Sets answered status
   */
  private function setStatus($id, $status) {
    $id = (int)$id;
    $status = (int)$status;
    
    $query = <<<SQL
UPDATE suggestion_box
SET answered = $status
WHERE id = $id
LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error($this->errstr['db_err']);
    }
  }
  
  /**
   * Count drafts
   */
  private function countDrafts() {
    $query = 'SELECT COUNT(*) as cnt FROM suggestion_box WHERE answered = 3';
    $res = mysql_global_call($query);
    $row = mysql_fetch_assoc($res);
    
    return $row['cnt'];
  }
  
  /**
   * Count unanswered
   */
  private function countUnanswered() {
    $counts = array();
    
    $query = <<<SQL
SELECT category, COUNT(*) as cnt
FROM suggestion_box
WHERE answered = 0 GROUP BY category
SQL;
    
    $res = mysql_global_call($query);
    
    foreach ($this->categories as $cid => $value) {
      $counts[$cid] = 0;
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $counts[$row['category']] = (int)$row['cnt'];
    }
    
    return $counts;
  }
  /**
   * Dismiss
   */
  public function dismiss() {
    $this->checkServer();
    
    auth_user();
    
    if (!has_level('manager') && !has_flag('developer')) {
      $this->renderHTML('denied');
      die();
    }
    
    if (!isset($_POST['id'])) {
      $this->error($this->errstr['bad_id']);
    }
    
    $this->setStatus($_POST['id'], 2);
    
    $this->success();
  }
  
  /**
   * Dismiss entries older than 30 days
   */
  public function dismiss_old($value='') {
    $this->checkServer();
    
    auth_user();
    
    if (!has_level() || !has_flag('developer')) {
      $this->renderHTML('denied');
      die();
    }
    
    $query = <<<SQL
UPDATE suggestion_box
SET answered = 2
WHERE created_on < DATE_SUB(NOW(), INTERVAL 30 DAY)
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error($this->errstr['db_err']);
    }
    
    $this->success();
  }
  
  /**
   * Approve draft
   */
  public function approve() {
    $this->checkServer();
    
    auth_user();
    
    if (!has_level('admin')) {
      $this->renderHTML('denied');
      die();
    }
    
    if (!isset($_POST['id'])) {
      $this->error($this->errstr['bad_id']);
    }
    
    $id = (int)$_POST['id'];
    
    $query = "SELECT answered FROM suggestion_box WHERE id = $id";
    $res = mysql_global_call($query);
    $row = mysql_fetch_assoc($res);
    
    if ($row['answered'] !== '3') {
      $this->error($this->errstr['bad_id']);
    }
    
    $this->setStatus($_POST['id'], 1);
    
    $this->success();
  }
  
  /**
   * Default page
   */
  public function index($is_review = false) {
    // Offset
    if (isset($_GET['offset'])) {
      $offset = 'AND id < ' . (int)$_GET['offset'];
    }
    else {
      $offset = '';
    }
    
    $this->is_review = $is_review;
    $this->is_draft_mode = false;
    
    $order = 'updated_on DESC';
    $answered = 'AND answered = 1';
    
    $this->get_params = null;
    
    // Suggestions
    $limit = $this->page_size + 1;
    
    $query = <<<SQL
SELECT id, subject, category,
DATE_FORMAT(updated_on, '%m/%d/%y %H:%i') as updated_date,
updated_capcode, message
FROM suggestion_box
WHERE parent_id = 0
$answered
$offset
ORDER BY $order
LIMIT $limit
SQL;
    
    $this->suggestions = array();
    
    $res = mysql_global_call($query);
    
    // Replies
    $count = mysql_num_rows($res);
    
    if ($count > 0) {
      $in_clause = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->suggestions[] = $row;
        $in_clause[] = $row['id'];
      }
      
      if ($this->has_next_page = $count > $this->page_size) {
        array_pop($this->suggestions);
        array_pop($in_clause);
      }
      
      $in_clause = implode(',', $in_clause);
      
      $query = <<<SQL
SELECT parent_id, message
FROM suggestion_box
WHERE parent_id IN($in_clause)
SQL;
      
      $this->replies = array();
      
      $res = mysql_global_call($query);
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->replies[$row['parent_id']] = $row;
      }
    }
    else {
      $this->has_next_page = false;
    }
    
    $this->mode = 'index';
    
    $this->renderHTML('feedback');
  }
  
  /**
   * Admin view
   */
  public function review() {
    $this->checkServer();
    
    auth_user();
    
    if (!has_level()) {
      $this->renderHTML('denied');
      die();
    }
    
    $this->can_dismiss = has_level('manager') || has_flag('developer');
    
    header('Content-Security-Policy: default-src *.4chan.org *.4cdn.org');
    header('X-Content-Security-Policy: default-src *.4chan.org *.4cdn.org');
    
    // Offset
    if (isset($_GET['offset'])) {
      $offset = 'AND id < ' . (int)$_GET['offset'];
    }
    else {
      $offset = '';
    }
    
    $this->is_draft_mode = false;
    
    $order = 'created_on DESC';
    $answered = 'AND answered = 0';
    
    $this->get_params = 'action=review';
    
    if (isset($_GET['answered'])) {
      // answered -> 1
      // dismissed -> 2
      // drafts -> 3
      $answered_param = (int)$_GET['answered'];
      $answered = "AND answered = $answered_param";
      $this->get_params .= "&amp;answered=$answered_param";
      
      $this->is_draft_mode = $answered_param === 3;
    }
    
    // Category
    $cat_param = '';
    
    if (isset($_GET['cat'])) {
      $cat_id = (int)$_GET['cat'];
      
      if ($cat_id >= 0) {
        $this->get_params .= "&amp;cat=$cat_id";
        $cat_param = "AND category = $cat_id";
        $this->current_cat = $cat_id;
      }
    }
    
    // Count drafts
    $this->draft_count = $this->countDrafts();
    
    // Count unanswered
    $this->cat_count = $this->countUnanswered();
    
    // Suggestions
    $limit = $this->page_size + 1;
    
    $query = <<<SQL
SELECT id, subject, category,
DATE_FORMAT(updated_on, '%m/%d/%y %H:%i') as updated_date,
updated_capcode, message
FROM suggestion_box
WHERE parent_id = 0
$answered
$cat_param
$offset
ORDER BY $order
LIMIT $limit
SQL;
    
    $this->suggestions = array();
    
    $res = mysql_global_call($query);
    
    // Replies
    $count = mysql_num_rows($res);
    
    if ($count > 0) {
      $in_clause = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->suggestions[] = $row;
        $in_clause[] = $row['id'];
      }
      
      if ($this->has_next_page = $count > $this->page_size) {
        array_pop($this->suggestions);
        array_pop($in_clause);
      }
      
      $in_clause = implode(',', $in_clause);
      
      $query = <<<SQL
SELECT parent_id, message
FROM suggestion_box
WHERE parent_id IN($in_clause)
SQL;
      
      $this->replies = array();
      
      $res = mysql_global_call($query);
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->replies[$row['parent_id']] = $row;
      }
    }
    else {
      $this->has_next_page = false;
    }
    
    $this->mode = 'index';
    
    $this->renderHTML('feedback-review');
  }
  
  /**
   * Submit
   */
  public function submit() {
    if (!isset($_POST['category']) || !isset($_POST['subject']) || !isset($_POST['message'])) {
      $this->errorHTML($this->errstr['bad_req']);
    }
    
    $answered = 0;
    
    if ($this->is_ip_blacklisted($_SERVER['REMOTE_ADDR'])) {
      $this->errorHTML($this->errstr['flood_blacklisted']);
    }
    
    // Captcha
    require_once 'lib/rpc.php';
    
    $this->verifyCaptcha();
    
    // IP
    $ip = mysql_real_escape_string($_SERVER['REMOTE_ADDR']);
    
    // Disallow permabanned IPs
    /*
    $query =<<<SQL
SELECT no FROM banned_users
WHERE host = '$ip'
AND length = '0000-00-00 00:00:00'
AND global = 1
AND active = 1
LIMIT 1
SQL;
    
    $result = mysql_global_call($query);
    
    if (mysql_num_rows($result) > 0) {
      $this->errorHTML($this->errstr['permabanned']);
    }
    */
    $query = <<<SQL
SELECT id FROM suggestion_box
WHERE created_on >= DATE_SUB(NOW(), INTERVAL 1 DAY)
AND ip = '$ip'
AND parent_id = 0
LIMIT {$this->max_daily_submissions}
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->errorHTML($this->errstr['db_err']);
    }
    
    if (mysql_num_rows($result) === $this->max_daily_submissions) {
      $plural = $this->max_daily_submissions > 1 ? 's' : '';
      $msg = sprintf($this->errstr['flood_daily'], "$this->max_daily_submissions question$plural");
      $this->errorHTML($msg);
    }
    
    // Category
    $category = (int)$_POST['category'];
    
    if (!isset($this->categories[$category])) {
      $this->errorHTML($this->errstr['bad_category']);
    }
    
    // Subject
    $subject = str_replace("\r\n", ' ', $_POST['subject']);
    
    $subject = $this->cleanString($subject);
    
    if ($subject === '') {
      $this->errorHTML($this->errstr['empty_subject']);
    }
    
    $subject = htmlspecialchars($subject, ENT_QUOTES);
    
    if (mb_strlen($subject) > $this->max_len_subject) {
      $this->errorHTML($this->errstr['long_subject']);
    }
    
    $subject = mysql_real_escape_string($subject);
    
    // Message
    $message = $this->cleanString($_POST['message']);
    
    if ($message === '') {
      $this->errorHTML($this->errstr['no_msg']);
    }
    
    $message = htmlspecialchars($message, ENT_QUOTES);
    $message = str_replace("\r\n", '<br>', $message);
    
    if (mb_strlen($message) > $this->max_len_message) {
      $this->errorHTML($this->errstr['long_msg']);
    }
    
    $message = mysql_real_escape_string($message);
    
    $extra = array();
    
    $extra['http_ua'] = $_SERVER['HTTP_USER_AGENT'];
    $extra['http_lang'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    
    // Extra data
    if (isset($_COOKIE['4chan_pass']) && $_COOKIE['4chan_pass']) {
      $pwd = UserPwd::decodePwd($_COOKIE['4chan_pass']);
      
      if ($pwd) {
        $extra['4chan_pass'] = $pwd;
      }
    }
    
    $extra = json_encode($extra);
    $extra = mysql_real_escape_string($extra);
    
    $query = <<<SQL
INSERT INTO suggestion_box
(parent_id, created_on, updated_on, updated_username, updated_capcode, category, subject, message, ip, answered, extra)
VALUES(0, NOW(), NOW(), '', 'none', $category, '$subject', '$message',
'$ip', $answered, '$extra')
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->errorHTML($this->errstr['db_err']);
    }
    
    $this->successHTML($this->succstr['question_done'], '');
  }
  
  /**
   * Reply
   */
  public function reply() {
    header('Content-Security-Policy: default-src *.4chan.org');
    header('X-Content-Security-Policy: default-src *.4chan.org');
    
    $this->checkServer();
    
    auth_user();
    
    if (!has_level()) {
      $this->renderHTML('denied');
      die();
    }
    
    // Dismiss
    if (isset($_POST['dismiss']) && isset($_POST['id'])) {
      $this->setStatus($_POST['id'], 2);
      $this->successAdmin($this->succstr['answer_done'], '?action=review');
      return;
    }
    
    if (!isset($_POST['id']) || !isset($_POST['message']) || !isset($_POST['capcode'])) {
      $this->errorAdmin($this->errstr['bad_req']);
    }
    
    $id = (int)$_POST['id'];
    
    if ($id < 1) {
      $this->errorHTML($this->errstr['bad_id']);
    }
    
    // Check if the entry exists
    $query = "SELECT id, answered FROM suggestion_box WHERE id = $id";
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->errorAdmin($this->errstr['db_err']);
    }
    
    if (!mysql_num_rows($result)) {
      $this->errorAdmin($this->errstr['bad_id']);
    }
    
    // Check if it already has an answer
    $entry = mysql_fetch_assoc($result);
    
    $has_answer = $entry['answered'] === '1' || $entry['answered'] === '3';
    
    // Message
    $message = $this->cleanString($_POST['message']);
    
    if ($message === '') {
      $this->errorAdmin($this->errstr['no_msg']);
    }
    
    $message = htmlspecialchars($message, ENT_QUOTES);
    $message = str_replace("\r\n", '<br>', $message);
    
    if (mb_strlen($message) > $this->max_len_message_reply) {
      $this->errorAdmin($this->errstr['long_msg']);
    }
    
    $message = mysql_real_escape_string($message);
    
    // Username, capcode
    $username = mysql_real_escape_string($_COOKIE['4chan_auser']);
    
    $capcode = $_POST['capcode'];
    
    $is_admin = has_level('admin');
    
    if (!isset($this->reply_labels[$capcode])) {
      $this->errorAdmin($this->errstr['bad_capcode']);
    }
    
    if ($capcode === 'admin' && !$is_admin) {
      $this->errorAdmin($this->errstr['bad_capcode']);
    }/*
    else if ($capcode === 'manager' && !has_level('manager')) {
      $this->errorHTML($this->errstr['bad_capcode']);
    }*/
    else if ($capcode === 'developer' && !has_flag('developer')) {
      $this->errorAdmin($this->errstr['bad_capcode']);
    }
    
    // Admin can reply directly, other capcodes can only submit drafts
    if ($is_admin) {
      $answer_type = 1;
    }
    else {
      $answer_type = 3;
    }
    
    if (isset($_POST['update']) && $has_answer) {
      $query = <<<SQL
UPDATE suggestion_box
SET message = '$message',
updated_on = NOW(),
updated_username = '$username',
updated_capcode = '$capcode'
WHERE parent_id = $id
LIMIT 1
SQL;
    }
    else if (!$has_answer) {
      $query = <<<SQL
INSERT INTO suggestion_box (parent_id, created_on, updated_on, updated_username,
updated_capcode, category, email, subject, message, ip)
VALUES($id, NOW(), NOW(), '$username', '$capcode', 0, '', '', '$message', '')
SQL;
    }
    else {
      $this->errorAdmin($this->errstr['bad_req']);
    }
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->errorAdmin($this->errstr['db_err']);
    }
    
    $query = <<<SQL
UPDATE suggestion_box
SET updated_on = NOW(), answered = $answer_type, updated_capcode = '$capcode'
WHERE id = $id
LIMIT 1
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->errorAdmin($this->errstr['db_err']);
    }
    
    $this->successAdmin($this->succstr['answer_done'], '?action=review', 0);
  }
  
  /**
   * Show single suggestion
   */
  public function read() {
    $this->checkServer();
    
    auth_user();
    
    if (!has_level()) {
      $this->renderHTML('denied');
      die();
    }
    
    require_once 'lib/geoip2.php';
    
    if (!isset($_GET['id'])) {
      $this->errorAdmin($this->errstr['bad_req']);
    }
    
    $id = (int)$_GET['id'];
    
    if ($id < 1) {
      $this->errorAdmin($this->errstr['no_results']);
    }
    
    // Count drafts
    $this->draft_count = $this->countDrafts();
      
    // Count unanswered
    $this->cat_count = $this->countUnanswered();
    
    // Suggestion
    $query = <<<SQL
SELECT id, subject, message, category, updated_capcode, ip, email, extra,
DATE_FORMAT(created_on, '%m/%d/%y %H:%i') as created_on
FROM suggestion_box
WHERE id = $id AND parent_id = 0
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->errorAdmin($this->errstr['db_err']);
    }
    
    if (mysql_num_rows($result) === 0) {
      $this->errorAdmin($this->errstr['no_results']);
    }
    
    $this->suggestion = mysql_fetch_assoc($result);
    
    if ($this->suggestion['extra'] !== '') {
      $this->suggestion['extra'] = json_decode($this->suggestion['extra'], true);
    }
    
    $geoinfo = GeoIP2::get_country($this->suggestion['ip']);
    
    if ($geoinfo && isset($geoinfo['country_code'])) {
      $geo_loc = array();
      
      if (isset($geoinfo['city_name'])) {
        $geo_loc[] = $geoinfo['city_name'];
      }
      
      if (isset($geoinfo['state_code'])) {
        $geo_loc[] = $geoinfo['state_code'];
      }
      
      $geo_loc[] = $geoinfo['country_name'];
      
      $this->suggestion['geo'] = $geo_loc;
    }
    else {
      $this->suggestion['geo'] = array();
    }
    
    // Reply
    $query = <<<SQL
SELECT message,
DATE_FORMAT(created_on, '%m/%d/%y %H:%i') as created_on,
DATE_FORMAT(updated_on, '%m/%d/%y %H:%i') as updated_on,
updated_capcode
FROM suggestion_box
WHERE parent_id = $id
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->errorAdmin($this->errstr['db_err']);
    }
    
    if (mysql_num_rows($result) > 0) {
      $this->reply = mysql_fetch_assoc($result);
    }
    else {
      $this->reply = null;
    }
    
    $this->capcodes = array();
    
    if (has_level('admin')) {
      $this->capcodes[] = 'admin';
    }
    /*
    if (has_level('manager')) {
      $this->capcodes[] = 'manager';
    }
    */
    if (has_flag('developer')) {
      $this->capcodes[] = 'developer';
    }
    
    $this->capcodes[] = 'mod';
    
    $this->is_review = true;
    $this->mode = 'read';
    $this->renderHTML('feedback-review');
  }
  
  /**
   * Goto ID
   */
  public function show() {
    $this->search(true);
  }
  
  /**
   * Search
   */
  public function search($goto = false) {
    if (!isset($_GET['q'])) {
      $this->errorHTML($this->errstr['bad_req']);
    }
    
    // Goto ID
    if ($goto !== false) {
      $q = (int)$_GET['q'];
      
      if ($q < 1) {
        $this->errorHTML($this->errstr['bad_id']);
      }
      
      $this->q = $q;
      
      $where = "id = $q";
      
      $this->get_params = null;
    }
    // Search
    else {
      $q = trim($_GET['q']);
      $q = htmlspecialchars($q, ENT_QUOTES);
      
      if ($q === '') {
        $this->errorHTML($this->errstr['no_query']);
      }
      
      $this->get_params = '?q=' . $q . '&amp;action=search';
      
      $this->q = $q;
      
      $q = mysql_real_escape_string($q);
      
      $where = "MATCH(subject) AGAINST ('$q')";
    }
    
    // Offset
    if (isset($_GET['offset'])) {
      $offset = 'AND id < ' . (int)$_GET['offset'];
    }
    else {
      $offset = '';
    }
    
    // Suggestions
    $limit = $this->page_size + 1;
    
    $query = <<<SQL
SELECT id, subject, category,
DATE_FORMAT(updated_on, '%m/%d/%y %H:%i') as updated_date,
updated_capcode, message
FROM suggestion_box
WHERE parent_id = 0
AND answered = 1
AND $where
$offset
ORDER BY updated_on DESC
LIMIT $limit
SQL;
    
    $this->suggestions = array();
    
    $res = mysql_global_call($query);
    
    // Replies
    $count = mysql_num_rows($res);
    
    if ($count > 0) {
      $in_clause = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->suggestions[] = $row;
        $in_clause[] = $row['id'];
      }
      
      if ($this->has_next_page = $count > $this->page_size) {
        array_pop($this->suggestions);
        array_pop($in_clause);
      }
      
      $in_clause = implode(',', $in_clause);
      
      $query = <<<SQL
SELECT parent_id, message
FROM suggestion_box
WHERE parent_id IN($in_clause)
SQL;
      
      $this->replies = array();
      
      $res = mysql_global_call($query);
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->replies[$row['parent_id']] = $row;
      }
    }
    else {
      $this->has_next_page = false;
    }
    
    $this->is_review = false;
    $this->mode = 'index';
    $this->renderHTML('feedback');
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

$ctrl = new SuggestionBox();
$ctrl->run();
