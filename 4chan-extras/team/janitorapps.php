<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/geoip2.php';

define('IN_APP', true);

auth_user();

if (!has_level()) {
  APP::denied();
}
/*
if (!has_flag('developer')) {
  die('503');
}
*/
require_once 'lib/csp.php';

class APP {
  protected
    // Routes
    $actions = array(
      'index',
      'rate',
      'stats',
      'review',
      'accept',
      'reject',
      'send_orientation',
      'create_account'/*,
      'create_table'*/
    ),
    // Table name
    $tableName = 'janitor_apps',
    // Maximum score
    $maxScore = 5,
      // Number of entries to display per page (reviews)
    $pageSize = 25,
    // Number of votes before the application is counted as "rated"
    $voteCountThreshold = 5;
  
  const TPL_ROOT = 'views/';
  
  const REVIEW_OPEN = true;
  
  const
    OPEN        = 0,
    REJECTED    = 1,
    ACCEPTED    = 2,
    SIGNED      = 4,
    ORIENTED    = 3,
    CLOSED      = 8,
    IGNORED     = 9;
  
  const
    MAIL_ACCEPT_FILE         = './data/mail_janitor_accept.txt',
    MAIL_ORIENTATION_FILE    = './data/mail_janitor_orientation.txt',
    MAIL_ACCOUNT_FILE        = './data/mail_janitor_account.txt';
    
  const
    ADMIN_SALT_PATH = '/www/keys/2014_admin.salt';
  
  const
    HTPASSWD_CMD = '/usr/local/www/bin/htpasswd -Bb -C 10 %s %s %s',
    HTPASSWD_CMD_NGINX = '/usr/local/www/bin/htpasswd -b %s %s %s',
    HTPASSWD_RM_CMD_NGINX = '/usr/local/www/bin/htpasswd -D %s %s',
    JANITOR_HTPASSWD_TMP = '/www/global/htpasswd/temp_agreement_nginx',
    JANITOR_HTPASSWD = '/www/global/htpasswd/janitors',
    JANITOR_HTPASSWD_NGINX = '/www/global/htpasswd/janitors_nginx';
  
  const
    STR_Q1 = "Describe your expertise in the board's subject matter.",
    STR_Q2 = "What are the main problems facing the board?",
    STR_Q3 = "What is your favorite thing about the board?",
    STR_Q4 = "What makes you a particularly good applicant for the team?"
  ;
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
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
  
  final protected function errorHTML($msg) {
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
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function getRandomHexBytes($length = 10) {
    $bytes = openssl_random_pseudo_bytes($length);
    return bin2hex($bytes);
  }
  
  private function sortByVoteCountDesc($a, $b) {
    $a = $a[0];
    $b = $b[0];
    
    if ($a === $b) {
      return 0;
    }
    
    return ($a > $b) ? -1 : 1;
  }
  
  private function removeTempAccount($hashed_auth_key) {
    $cmd = sprintf(self::HTPASSWD_RM_CMD_NGINX,
      self::JANITOR_HTPASSWD_TMP,
      escapeshellarg($hashed_auth_key)
    );
    
    if (system($cmd) === false) {
      $this->error('Internal Server Error (rta1).');
    }
    
    return true;
  }
  
  private function getStatusCounts() {
    $query = "SELECT closed, COUNT(*) as cnt FROM `{$this->tableName}` GROUP BY closed";
    
    $result = mysql_global_call($query);
    
    $data = [];
    
    while ($row = mysql_fetch_assoc($result)) {
      $data[$row['closed']] = (int)$row['cnt'];
    }
    
    return $data;
  }
  
  /**
   * Email
   */
  private function sendMail($email, $mail_file, $values = null) {
    if (!file_exists($mail_file)) {
      $this->error('Cannot find e-mail file.');
    }
    
    $lines = file($mail_file);
    
    $subject = trim(array_shift($lines));
    $message = implode('', $lines);
    
    if ($values) {
      $message = str_replace(array_keys($values), array_values($values), $message);
    }
    
    $headers = "From: Team 4chan <janitorapps@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $opts = '-f janitorapps@4chan.org';
    
    return mail($email, $subject, $message, $headers, $opts);
  }
  
  /**
   * Changes application status (accepted/rejected)
   */
  private function changeStatus($id, $status) {
    $id = (int)$id;
    $status = (int)$status;
    
    $query = "SELECT closed FROM `{$this->tableName}` WHERE id = $id";
    
    $result = mysql_global_call($query);
    
    $app = mysql_fetch_assoc($result);
    
    if (!$app) {
      $this->error('Bad ID.');
    }
    
    $query = "UPDATE `{$this->tableName}` SET closed = $status WHERE id = $id LIMIT 1";
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error("Couldn't change the status.");
    }
  }
  
  /**
   * Creates temporary credentials to access /agreement
   */
  private function createTemporaryCreds($id) {
    $id = (int)$id;
    
    $auth_key = $this->getRandomHexBytes(32);
    
    if (!$auth_key) {
      $this->error('Internal Server Error (ctc1)');
    }
    
    $http_passwd = $this->getRandomHexBytes(32);
    
    if (!$http_passwd) {
      $this->error('Internal Server Error (ctc1)');
    }
    
    // ---
    
    $admin_salt = file_get_contents(self::ADMIN_SALT_PATH);
    
    if (!$admin_salt) {
      $this->error('Internal Server Error (ctc2)');
    }
    
    $hashed_auth_key = hash('sha256', $auth_key . $admin_salt);
    
    $query = "SELECT id FROM `{$this->tableName}` WHERE agreement_key = '%s'";
    $result = mysql_global_call($query, $hashed_auth_key);
    
    if (!$result) {
      $this->error("Database error (ctc0).");
    }
    
    if (mysql_num_rows($result) !== 0) {
      $this->error('Internal Server Error (ctc3)');
    }
    
    // Delete existing account
    $query = "SELECT agreement_key FROM `{$this->tableName}` WHERE id = $id";
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error("Database error (ctc1).");
    }
    
    if (mysql_num_rows($result) > 0) {
      $hashed_old_key = mysql_fetch_row($result)[0];
      $this->removeTempAccount($hashed_old_key);
    }
    
    // Update the janitor application with the new temp auth key
    $query = "UPDATE `{$this->tableName}` SET agreement_key = '%s', key_created_on = %d WHERE id = $id LIMIT 1";
    $result = mysql_global_call($query, $hashed_auth_key, time());
    
    if (!$result) {
      $this->error("Database error (ctc2).");
    }
    
    if (mysql_affected_rows() !== 1) {
      $this->error("Database error (ctc3).");
    }
    
    // Update the htpasswd file
    $cmd = sprintf(self::HTPASSWD_CMD_NGINX,
      self::JANITOR_HTPASSWD_TMP,
      escapeshellarg($hashed_auth_key),
      escapeshellarg($http_passwd)
    );
    
    if (system($cmd) === false) {
      $this->error('Could not update htpasswd file (ctc1).');
    }
    
    return array('login' => $hashed_auth_key, 'pwd' => $http_passwd, 'key' => $auth_key);
  }
  
  /**
   * Get boards
   */
  private function getBoards() {
    $query = 'SELECT dir FROM boardlist';
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      die('Error while getting the boardlist');
    }
    
    $boards = array();
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[] = $board['dir'];
    }
    
    return $boards;
  }
  
  /**
   * Create table
   */
  public function create_table() {
    $tableName = $this->tableName;
    
    $sql = "DROP TABLE `$tableName`";
    mysql_global_call($sql);
    
    $sql =<<<SQL
CREATE TABLE IF NOT EXISTS `$tableName` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `firstname` varchar(255) CHARACTER SET utf8 NOT NULL,
  `handle` varchar(255) CHARACTER SET utf8 NOT NULL,
  `email` varchar(255) CHARACTER SET utf8 NOT NULL,
  `age` tinyint(2) unsigned NOT NULL,
  `tz` tinyint(3) NOT NULL,
  `hours` tinyint(2) unsigned NOT NULL,
  `times` varchar(50) NOT NULL,
  `board1` char(6) NOT NULL,
  `board2` char(6) NOT NULL,
  `experience` text CHARACTER SET utf8 NOT NULL,
  `why` text CHARACTER SET utf8 NOT NULL,
  `what` text CHARACTER SET utf8 NOT NULL,
  `ip` char(15) NOT NULL,
  `updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed` tinyint(1) unsigned NOT NULL,
  `total` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `votes` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `avg` float(4,2) unsigned NOT NULL DEFAULT '0.00',
  `voters` varchar(255) DEFAULT '',
  `agreement_key` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `key_created_on` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE (`email`),
  KEY `board1_idx` (`board1`),
  KEY `board2_idx` (`board2`),
  KEY `votes_idx` (`votes`),
  KEY `avg_idx` (`avg`),
  KEY `voters_idx` (`voters`),
  KEY `closed_idx` (`closed`),
  KEY `agreement_key` (`agreement_key`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
SQL;

/*    mysql_global_call($sql);
    
    $sql =<<<SQL
INSERT INTO `$tableName` (firstname, handle, email, age, tz, hours, times,
board1, board2, experience, why, what, ip, closed)
VALUES('John', 'janny', 'moot@4chan.org',
12, 1, 19, '7am-2am', 'po', '', 'None', 'N/A',
'N/A', '127.0.0.1', 0)
SQL;

    mysql_global_call($sql);
    
    $sql =<<<SQL
INSERT INTO `$tableName` (firstname, handle, email, age, tz, hours, times,
board1, board2, experience, why, what, ip, closed)
VALUES('John', 'janny', 'desuwa@4chan.org',
12, 1, 19, '7am-2am', 'po', '', 'None', 'N/A',
'N/A', '127.0.0.1', 0)
SQL; */

    mysql_global_call($sql);
  }
  
  private function validate_otp($otp) {
    $query = "SELECT auth_secret FROM mod_users WHERE username = '%s'";
    
    $res = mysql_global_call($query, $_COOKIE['4chan_auser']);
    
    if (!$res) {
      $this->error('Database error (vO)');
    }
    
    $user = mysql_fetch_assoc($res);
    
    if (!$user || !$user['auth_secret']) {
      $this->error('Invalid OTP.');
    }
    
    require_once 'lib/GoogleAuthenticator.php';
    
    $ga = new PHPGangsta_GoogleAuthenticator();
    
    $dec_secret = auth_decrypt($user['auth_secret']);
    
    if ($dec_secret === false) {
      $this->error('Internal Server Error (vO).');
    }
    
    if (!$ga->verifyCode($dec_secret, $otp, 2)) {
      $this->error('Invalid OTP.');
    }
  }
  
  /**
   * Stats and counters
   */
  public function stats() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->error('Bad request');
    }
    
    // ------
    // Applications
    // ------
    $totals = array();
    
    // Total submitted
    $query = "SELECT COUNT(id) FROM `{$this->tableName}`";
    $res = mysql_global_call($query);
    $totals['total'] = mysql_fetch_row($res)[0];
    
    // Unrated
    $query = "SELECT COUNT(id) FROM `{$this->tableName}` WHERE closed = "
      . self::OPEN . ' AND votes < ' . $this->voteCountThreshold;
    $res = mysql_global_call($query);
    $totals['open'] = mysql_fetch_row($res)[0];
    
    // Auto-Ignored
    $query = "SELECT COUNT(id) FROM `{$this->tableName}` WHERE closed = " . self::IGNORED;
    $res = mysql_global_call($query);
    $totals['ignored'] = mysql_fetch_row($res)[0];
    
    // Accepted
    $query = "SELECT COUNT(id) FROM `{$this->tableName}` WHERE closed = " . self::ACCEPTED;
    $res = mysql_global_call($query);
    $totals['accepted'] = mysql_fetch_row($res)[0];
    
    // Oriented
    $query = "SELECT COUNT(id) FROM `{$this->tableName}` WHERE closed = " . self::ORIENTED;
    $res = mysql_global_call($query);
    $totals['oriented'] = mysql_fetch_row($res)[0];
    
    // Completed
    $query = "SELECT COUNT(id) FROM `{$this->tableName}` WHERE closed = " . self::CLOSED;
    $res = mysql_global_call($query);
    $totals['completed'] = mysql_fetch_row($res)[0];
    
    // ------
    // Boards
    // ------
    $boards = array();
    
    // As first choice
    $query = "SELECT board1, COUNT(id) as cnt FROM `{$this->tableName}` WHERE closed != "
      . self::IGNORED . ' GROUP BY board1';
    $res = mysql_global_call($query);
    while ($row = mysql_fetch_assoc($res)) {
      if ($row['board1']) {
        $boards[$row['board1']] = array((int)$row['cnt'], 0);
      }
    }
    
    // As second choice
    $query = "SELECT board2, COUNT(*) as cnt FROM `{$this->tableName}` WHERE closed != "
      . self::IGNORED . ' GROUP BY board2';
    $res = mysql_global_call($query);
    while ($row = mysql_fetch_assoc($res)) {
      if ($row['board2']) {
        $boards[$row['board2']][1] = (int)$row['cnt'];
      }
    }
    
    // ------
    // Mod activity
    // ------
    $users = array();
    
    $query = "SELECT username FROM mod_users WHERE level != 'janitor'";
    $res = mysql_global_call($query);
    while ($row = mysql_fetch_row($res)) {
      $users[$row[0]] = array(0, 0);
    }
    
    $query = "SELECT voters FROM `{$this->tableName}`";
    $res = mysql_global_call($query);
    while ($row = mysql_fetch_row($res)) {
      $voters = $row[0];
      
      if ($voters === '') {
        continue;
      }
      
      preg_match_all('/([^<]+)<([0-9])>/', $voters, $matches, PREG_SET_ORDER);
      
      foreach ($matches as $m) {
        $username = $m[1];
        $score = (int)$m[2];
        
        ++$users[$username][0];
        $users[$username][1] += $score;
      }
    }
    
    uasort($users, array($this, 'sortByVoteCountDesc'));
    
    // -----
    
    $this->totals = $totals;
    $this->boards = $boards;
    $this->users = $users;
    
    $this->renderHTML('janitorapps-stats');
  }
  
  /**
   * Default page
   */
  public function index() {
    if (!self::REVIEW_OPEN) {
      $this->errorHTML("Applications aren't closed yet");
    }
    
    $username = mysql_real_escape_string($_COOKIE['4chan_auser']);
    
    $this->is_supadmin = $_COOKIE['4chan_auser'] === 'hiro';
    
    $status = self::OPEN;
    
    $this->boards = $this->getBoards();
    
    if (isset($_GET['board']) && in_array($_GET['board'], $this->boards)) {
      $board = mysql_real_escape_string($_GET['board']);
      
      $this->filterBoard = htmlspecialchars($board, ENT_QUOTES);
      
      $board = "AND board1 = '$board'";
    }
    else if (isset($_GET['board2']) && in_array($_GET['board2'], $this->boards)) {
      $board = mysql_real_escape_string($_GET['board2']);
      
      $this->filterBoard2 = htmlspecialchars($board, ENT_QUOTES);
      
      $board = "AND (board1 = '$board' OR board2 = '$board')";
    }
    else {
      $board = '';
      
      $this->filterBoard = null;
    }
    
    $tableName = $this->tableName;
    
    // Number of rated applications / Total number of applications
    $query =<<<SQL
SELECT COUNT(id) FROM `$tableName`
WHERE voters LIKE '%$username<%'
SQL;
    $res = mysql_global_call($query);
    $this->ratedCount = (int)mysql_fetch_row($res)[0];
    
    // FIXME: voters column default value should be '>'
    
    // Fetch unrated apps
    $query =<<<SQL
SELECT id FROM `$tableName`
WHERE closed = $status
AND (votes < 3 OR avg > 2.0)
AND voters NOT LIKE '%$username<%'
$board
SQL;
    
    $res = mysql_global_call($query);
    
    $this->unratedCount = mysql_num_rows($res);
    
    $ids = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $ids[] = $row['id'];
    }
    
    if (!empty($ids)) {
      $key = array_rand($ids);
      
      $random_id = $ids[$key];
      
      $query = "SELECT * FROM `{$this->tableName}` WHERE id = $random_id";
      
      $res = mysql_global_call($query);
      
      $this->app = array();
      
      $row = mysql_fetch_assoc($res);
      
      // Escape HTML
      foreach ($row as $key => $value) {
        $this->app[$key] = htmlspecialchars($value, ENT_QUOTES);
      }
      
      if ($this->is_supadmin) {
        $q = "SELECT COUNT(no) FROM banned_users WHERE host = '{$row['ip']}'";
        $r = mysql_global_call($q);
        
        if (!$r) {
          $this->app['ban_count'] = -1;
        }
        else {
          $this->app['ban_count'] = (int)mysql_fetch_row($r)[0];
        }
      }
    }
    else {
      $this->app = null;
    }
    
    $this->canReview = has_level('manager') || has_flag('developer');
    
    $this->renderHTML('janitorapps');
  }
  
  /**
   * Rate application
   */
  public function rate() {
    if (!self::REVIEW_OPEN) {
      $this->error("Applications aren't closed yet");
    }
    
    if (!isset($_GET['id']) || !isset($_GET['score'])) {
      $this->error('Missing arguments');
    }
    
    $id = (int)$_GET['id'];
    $score = (int)$_GET['score'];
    
    if ($score > $this->maxScore) {
      $this->error('Invalid score');
    }
    
    $query = "SELECT closed, voters FROM `{$this->tableName}` WHERE id = $id";
    $result = mysql_global_call($query);
    $app = mysql_fetch_assoc($result);
    
    if (!$app) {
      $this->error('Bad id');
    }
    
    $username = mysql_real_escape_string($_COOKIE['4chan_auser']);
    
    if (strpos($app['voters'], "$username<") !== false) {
      $this->error('You have already rated this application');
    }
    
    if ((int)$app['closed'] != self::OPEN) {
      $this->error('This application is not open');
    }
    
    $tableName = $this->tableName;
    
    $query =<<<SQL
UPDATE `$tableName`
SET total = total + $score,
votes = votes + 1,
avg = total / votes,
voters = CONCAT(voters, '$username<$score>')
WHERE id = $id
LIMIT 1
SQL;
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error('Database error');
    }
    
    $this->success();
  }
  
  public function review() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->error("Can't let you do that.");
    }
    
    $this->timezones = array(
      0 => 'Americas (-12 -2)',
      1 => 'Europe (-1 +4)',
      2 => 'Asia (+5 +14)'
    );
    
    $timezone_offsets = array(array(-12, -2), array(-1, 4), array(5, 14));
    
    // Filtering
    $clauses = array();
    $url_clauses = array();
    
    $this->filterStatus = null;
    $this->filterBoard = null;
    $this->filterTz = null;
    $this->filterSearch = null;
    
    // Type
    if (isset($_GET['status'])) {
      if ($_GET['status'] == 'rejected') {
        $clauses[] = 'closed = ' . self::REJECTED;
      }
      else if ($_GET['status'] == 'accepted') {
        $clauses[] = 'closed = ' . self::ACCEPTED;
      }
      else if ($_GET['status'] == 'signed') {
        $clauses[] = 'closed = ' . self::SIGNED;
      }
      else if ($_GET['status'] == 'oriented') {
        $clauses[] = 'closed = ' . self::ORIENTED;
      }
      else if ($_GET['status'] == 'closed') {
        $clauses[] = 'closed = ' . self::CLOSED;
      }
      else if ($_GET['status'] == 'ignored') {
        $clauses[] = 'closed = ' . self::IGNORED;
      }
      
      $this->filterStatus = $_GET['status'];
      $url_clauses[] = 'status=' . htmlspecialchars($_GET['status'], ENT_QUOTES);
    }
    else {
      $clauses[] = 'closed = ' . self::OPEN;
    }
    
    // Board
    if (isset($_GET['board'])) {
      $board_escaped = mysql_real_escape_string($_GET['board']);
      $clauses[] = "(board1 = '$board_escaped' OR board2 = '$board_escaped')";
      $this->filterBoard = $_GET['board'];
      $url_clauses[] = 'board=' . htmlspecialchars($_GET['board'], ENT_QUOTES);
    }
    
    // Timezone
    if (isset($_GET['tz']) && $timezone_offsets[(int)$_GET['tz']]) {
      $int_tz = (int)$_GET['tz'];
      $tz_range = $timezone_offsets[$int_tz];
      $clauses[] = "(tz >= {$tz_range[0]} AND tz <= {$tz_range[1]})";
      $this->filterTz = $int_tz;
      $url_clauses[] = 'tz=' . $int_tz;
    }
    
    // Search (email or ID)
    if (isset($_GET['search'])) {
      $search_terms = preg_split("/[\s,]+/", $_GET['search']);
      
      $search_clauses = array();
      $email_clauses = array();
      $id_clauses = array();
      
      foreach ($search_terms as $term) {
        if (stripos($term, '@') !== false) {
          $email_clauses[] = mysql_real_escape_string($term);
        }
        else {
          $id_clauses[] = (int)$term;
        }
      }
      
      if (!empty($email_clauses)) {
        $search_clauses[] = "email IN('" . implode("','", $email_clauses) . "')";
      }
      
      if (!empty($id_clauses)) {
        $search_clauses[] = "id IN(" . implode(",", $id_clauses) . ")";
      }
      
      if (!empty($search_clauses)) {
        $clauses[] = '(' . implode(' OR ', $search_clauses) . ')';
      }
      
      $this->filterSearch = htmlspecialchars($_GET['search'], ENT_QUOTES);
      
      // Don't paginate search results
      $needPagination = false;
    }
    else {
      $needPagination = true;
    }
    
    if (!empty($clauses)) {
      $where = 'WHERE ' . implode(' AND ', $clauses);
    }
    else {
      $where = '';
    }
    
    // Offset
    if ($needPagination) {
      if (isset($_GET['offset'])) {
        $offset = (int)$_GET['offset'];
      }
      else {
        $offset = 0;
      }
      $limit = $this->pageSize + 1;
      $limit = "LIMIT $offset, $limit";
    }
    else {
      $limit = '';
    }
    
    $tableName = $this->tableName;
    
    $query =<<<SQL
SELECT *, UNIX_TIMESTAMP(updated) as updated_on FROM `$tableName`
$where
ORDER BY avg DESC, votes DESC
$limit
SQL;
    
    $result = mysql_global_call($query);
    
    $this->apps = array();
    
    while ($row = mysql_fetch_assoc($result)) {
      $app = array();
      
      // Escape HTML
      foreach ($row as $key => $value) {
        $app[$key] = htmlspecialchars($value, ENT_QUOTES);
      }
      
      $q = "SELECT COUNT(no) FROM banned_users WHERE host = '{$app['ip']}'";
      $r = mysql_global_call($q);
      
      if (!$r) {
        $app['ban_count'] = -1;
      }
      else {
        $app['ban_count'] = (int)mysql_fetch_row($r)[0];
      }
      
      $geoinfo = GeoIP2::get_country($app['ip']);
      
      if ($geoinfo && isset($geoinfo['country_code'])) {
        $country = $geoinfo['country_code'];
        
        $geo_loc = array();
        
        if (isset($geoinfo['city_name'])) {
          $geo_loc[] = $geoinfo['city_name'];
        }
        
        if (isset($geoinfo['state_code'])) {
          $geo_loc[] = $geoinfo['state_code'];
        }
        
        $geo_loc[] = $geoinfo['country_name'];
        
        $app['geo_loc'] = implode(', ', $geo_loc);
      }
      
      $this->apps[] = $app;
    }
    
    $url_search = implode('&amp;', $url_clauses);
    
    if ($url_search !== '') {
      $url_search = '&amp;' . $url_search;
    }
    
    $this->previousOffset = false;
    $this->nextOffset = false;
    
    if ($needPagination) {
      if ($offset > 0) {
        $this->filterOffset = $offset;
        
        $this->previousOffset = $offset - $this->pageSize;
        
        if ($this->previousOffset < 0) {
          $this->previousOffset = 0;
        }
        
        $this->previousOffset = $url_search . '&amp;offset=' . $this->previousOffset;
      }
      
      if (count($this->apps) > $this->pageSize) {
        $this->nextOffset = $url_search . '&amp;offset=' . ($offset + $this->pageSize);
        array_pop($this->apps);
      }
    }
    
    $this->boards = $this->getBoards();
    
    $this->counts = $this->getStatusCounts();
    
    $this->renderHTML('janitorapps-review');
  }
  
  /**
   * Accept
   */
  public function accept() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->error("Can't let you do that.");
    }
    
    if (!isset($_POST['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    // Get application
    $query = "SELECT email, closed FROM `{$this->tableName}` WHERE id = $id";
    $result = mysql_global_call($query);
    $app = mysql_fetch_assoc($result);
    
    if (!$app) {
      $this->error('Bad ID.');
    }
    
    if ($app['closed'] == self::CLOSED) {
      $this->error("This application is already fully accepted.");
    }
    
    $creds = $this->createTemporaryCreds($id);
    $this->changeStatus($id, self::ACCEPTED);
    
    // Email
    $values = array(
      '{{LOGIN}}' => $creds['login'],
      '{{PWD}}' => $creds['pwd'],
      '{{KEY}}' => $creds['key']
    );
    
    if (!$this->sendMail($app['email'],  self::MAIL_ACCEPT_FILE, $values)) {
      $this->error('Email not accepted for delivery.');
    }
    
    $this->success();
  }
  
  /**
   * Reject
   */
  public function reject() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->error("Can't let you do that.");
    }
    
    if (!isset($_POST['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    // Get application
    $query = "SELECT closed, agreement_key FROM `{$this->tableName}` WHERE id = $id";
    $result = mysql_global_call($query);
    $app = mysql_fetch_assoc($result);
    
    if (!$app) {
      $this->error('Bad ID.');
    }
    
    if ($app['closed'] == self::CLOSED) {
      $this->error("This application is already fully accepted.");
    }
    
    if ($app['closed'] == self::ACCEPTED) {
      $this->removeTempAccount($app['agreement_key']);
    }
    
    $this->changeStatus($_POST['id'], self::REJECTED);
    
    $this->success();
  }
  
  /**
   * Send orientation email
   */
  public function send_orientation() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->error("Can't let you do that.");
    }
    
    if (!isset($_POST['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    // Get application
    $query = "SELECT email, closed FROM `{$this->tableName}` WHERE id = $id";
    $result = mysql_global_call($query);
    $app = mysql_fetch_assoc($result);
    
    if (!$app) {
      $this->error('Bad ID.');
    }
    
    if ($app['closed'] != self::SIGNED) {
      $this->error("The Volunteer Moderator Agreement needs to be signed first.");
    }
    
    if ($app['closed'] == self::CLOSED) {
      $this->error("This application is already fully accepted.");
    }
    
    $this->changeStatus($id, self::ORIENTED);
    
    // Email
    if (!$this->sendMail($app['email'], self::MAIL_ORIENTATION_FILE)) {
      $this->error('Email not accepted for delivery.');
    }
    
    $this->success();
  }
  
  /**
   * Create janitor account
   */
  public function create_account() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->error("Can't let you do that.");
    }
    
    if (!isset($_POST['otp']) || !$_POST['otp']) {
      $this->error('Bad Request.');
    }
    
    $this->validate_otp($_POST['otp']);
    
    if (!isset($_POST['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    // Get application
    $query = "SELECT * FROM `{$this->tableName}` WHERE id = $id";
    $result = mysql_global_call($query);
    $app = mysql_fetch_assoc($result);
    
    if (!$app) {
      $this->error('Bad ID.');
    }
    
    if ($app['closed'] != self::ORIENTED) {
      $this->error("This user hasn't received the orientation mail yet.");
    }
    
    if ($app['closed'] == self::CLOSED) {
      $this->error("This application is already fully accepted.");
    }
    
    // Board
    if (isset($_POST['board'])) {
      $board = preg_split('/[\s,]+/', $_POST['board']);
      
      if (count($board) > 2) {
        $this->error('You can only assign janitors to a maximum of two boards.');
      }
      
      $board = implode(',', $board);
    }
    else {
      $board = $app['board1'];
    }
    
    if (!preg_match('/^[,a-z0-9]+$/', $board)) {
      $this->error('Invalid format for board.');
    }
    
    $board = mysql_real_escape_string($board);
    
    // Username
    if (isset($_POST['username'])) {
      $username = $_POST['username'];
    }
    else {
      $username = $app['handle'];
    }
    
    if (!preg_match('/^[-_a-zA-Z0-9]+$/', $username)) {
      $this->error('Invalid user name. Allowed characters are -_a-zA-Z0-9');
    }
    
    $username = mysql_real_escape_string($username);
    
    $query = "SELECT username FROM mod_users WHERE LOWER(username) = '" . strtolower($username) . "'";
    $result = mysql_global_call($query);
    
    if (mysql_num_rows($result) > 0) {
      $this->error('Username already exists.');
    }
    
    // Password
    $plain_password = $this->getRandomHexBytes();
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    // Email
    $values = array(
      '{{USERNAME}}' => $username,
      '{{PASSWORD}}' => $plain_password,
      '{{BOARD}}' => $board
    );
    
    if (!$this->sendMail($app['email'], self::MAIL_ACCOUNT_FILE, $values)) {
      $this->error('Email not accepted for delivery.');
    }
    
    $email = mysql_real_escape_string($app['email']);
    
    // Query
    $query =<<<SQL
INSERT INTO mod_users (
username,
password,
password_expired,
level,
allow,
email,
signed_agreement,
janitorapp_id
) VALUES (
'$username',
'$hashed_password',
1,
'janitor',
'$board',
'$email',
1,
$id
)
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res || mysql_affected_rows() !== 1) {
      $this->error('Database error.');
    }
    
    $cmd = sprintf(self::HTPASSWD_CMD,
      self::JANITOR_HTPASSWD,
      escapeshellarg($username),
      escapeshellarg($plain_password)
    );
    
    if (system($cmd) === false) {
      $this->error('Could not update htpasswd file (1).');
    }
    
    $cmd = sprintf(self::HTPASSWD_CMD_NGINX,
      self::JANITOR_HTPASSWD_NGINX,
      escapeshellarg($username),
      escapeshellarg($plain_password)
    );
    
    if (system($cmd) === false) {
      $this->error('Could not update htpasswd file (2).');
    }
    
    $this->changeStatus($id, self::CLOSED);
    
    $this->success();
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

$ctrl = new APP();
$ctrl->run();
