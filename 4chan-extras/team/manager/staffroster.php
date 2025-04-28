<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/geoip2.php';

require_once 'lib/archives.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL & ~E_NOTICE);
}

require_once '../lib/csp.php';

class APP {
  protected
    $action = null,
    
    // Routes
    $actions = array(
      'index',
      'coverage',
      'activity',
      'scoreboard',
      'extra_logs',
      'manage',
      'flags',
      'update_boards',
      'update_flags',
      'update_email',
      'promote_janitor',
      'remove_janitor',
      'remove_janitor_htpasswd',
      'ips',
      'j_names',
      'check_email'
    );
  
  const TPL_ROOT = '../views/';
  
  const WEBROOT = '/manager';
  
  const
    JANITOR = 1,
    MOD = 2;
  
  const DATE_FORMAT = 'm/d/Y H:i:s';
  
  // Red if below
  const
    EVENT_LOG_DAYS = 7,
    ACTIVITY_THRES = 2592000, // number of seconds
    ROSTER_THRES = 2 // number of janitors
  ;
  
  const
    HTPASSWD_RM_CMD = '/usr/local/www/bin/htpasswd -D %s %s',
    JANITOR_HTPASSWD = '/www/global/htpasswd/janitors',
    JANITOR_HTPASSWD_NGINX = '/www/global/htpasswd/janitors_nginx';
  
  const STATS_ACTION_DENIED = 0;
  const STATS_ACTION_ACCEPTED = 1;
  
  const STATS_PERIOD_MONTHS = 6;
  const STATS_PERIOD_ACTION_MONTHS = 3;
  const STATS_PERIOD_ACTIVITY_DAYS = 7;
  
  // For confirmation emails
  const
    FROM_NAME = 'Team 4chan',
    FROM_ADDRESS = 'janitorapps@4chan.org';
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  /**
   * Returns the data as json
   */
  final protected function success($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
    // Don't die() here as it will break fastcgi_finish_request()
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
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Returns the derefered archive url for a post
   * or false if no archive is defined.
   */
  private function archive_url($board, $post_id) {
    $url = return_archive_link($board, $post_id, false, true); // lib/archives
    
    if ($url) {
      $url = derefer_url($url); // lib/admin
    }
    
    return $url;
  }
  
  /**
   * Sends confirmation emails
   */
  private function send_confirmation_email($subject, $message) {
    $headers = "From: " . self::FROM_NAME . " <" . self::FROM_ADDRESS . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $opts = '-f ' . self::FROM_ADDRESS;
    
    return mail(self::FROM_ADDRESS, $subject, $message, $headers, $opts);
  }
  
  /**
   * Get users
   */
  private function getUsers($level = null) {
    $query = 'SELECT id, username, level, allow FROM mod_users';
    
    if ($level === self::JANITOR) {
      $query .= " WHERE level = 'janitor'";
    }
    else if ($level === self::MOD) {
      $query .= " WHERE level = 'mod'";
    }
    else {
      $query .= " WHERE level != 'admin'";
    }
    
    $query .= ' ORDER BY username';
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      die('Error while getting the userlist');
    }
    
    $users = array();
    
    while($row = mysql_fetch_assoc($result)) {
      $users[] = $row;
    }
    
    return $users;
  }
  
  private function validateBoards($ary) {
    $boards = $this->get_boards();
    
    foreach ($ary as $b) {
      if (!isset($boards[$b])) {
        $this->error('Invalid board.');
      }
    }
    
    return true;
  }
  
  private function get_boards() {
    $query = 'SELECT dir FROM boardlist';
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[$board['dir']] = true;
    }
    
    if (has_flag('developer')) {
      $boards['test'] = true;
    }
    
    return $boards;
  }
  
  /**
   * Get users by board
   */
  private function getUsersByBoard($level = null) {
    // Boards
    $query = 'SELECT dir FROM boardlist ORDER BY dir';
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      die('Error while getting the boardlist');
    }
    
    $boards = array();
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[$board['dir']] = array();
    }
    
    // Users
    $raw_users = $this->getUsers($level);
    
    $users = array();
    $boards['all'] = array();
    
    foreach($raw_users as $row) {
      $users[$row['id']] = $row;
      
      $allow = explode(',', $row['allow']);
      
      foreach ($allow as $board) {
        if (!isset($boards[$board])) {
          $boards[$board] = array();
        }
        
        $boards[$board][] = $row['id'];
      }
    }
    
    $boards['global'] = $boards['all'];
    unset($boards['all']);
    
    return array('users' => $users, 'boards' => $boards);
  }
  
  private function get_user_by_name($username) {
    $sql = "SELECT username, allow, flags FROM mod_users WHERE username = '%s' LIMIT 1";
    
    $res = mysql_global_call($sql, $username);
    
    if (!$res) {
      return null;
    }
    
    return mysql_fetch_assoc($res);
  }
  
  private function getIPLoc($ip) {
    $ipinfo = GeoIP2::get_country($ip);
    
    if ($ipinfo) {
      $loc = array();
      
      if (isset($ipinfo['city_name'])) {
        $loc[] = $ipinfo['city_name'];
      }
      
      if (isset($ipinfo['state_code'])) {
        $loc[] = $ipinfo['state_code'];
      }
      
      $loc[] = $ipinfo['country_name'];
      
      $loc = implode(', ', $loc);
    }
    else {
      $loc = '';
    }
    
    return $loc;
	}
  
  /**
   * "Days/Hours/Minutes ago"
   */
  private function getPreciseDuration($delta) {
    if ($delta < 1) {
      return 'moments';
    }
    
    if ($delta < 60) {
      return $delta . ' seconds';
    }
    
    if ($delta < 3600) {
      $count = floor($delta / 60);
      
      if ($count > 1) {
        return $count . ' minutes';
      }
      else {
        return 'one minute';
      }
    }
    
    if ($delta < 86400) {
      $count = floor($delta / 3600);
      
      if ($count > 1) {
        $head = $count . ' hours';
      }
      else {
        $head = 'one hour';
      }
      
      $tail = floor($delta / 60 - $count * 60);
      
      if ($tail > 1) {
        $head .= ' and ' . $tail . ' minutes';
      }
      
      return $head;
    }
    
    $count = floor($delta / 86400);
    
    if ($count > 1) {
      $head = $count . ' days';
    }
    else {
      $head = 'one day';
    }
    
    $tail = floor($delta / 3600 - $count * 24);
    
    if ($tail > 1) {
      $head .= ' and ' . $tail . ' hours';
    }
    
    return $head;
  }
  
  /**
   * Shows user activity
   */
  public function activity() {
    $users = $this->getUsers();
    
    $user_id_map = array();
    
    $this->levels = array();
    $this->user_boards = array();
    $this->last_actions = array();
    $this->action_count = array();
    
    foreach ($users as $user) {
      $query = <<<SQL
SELECT UNIX_TIMESTAMP(ts) as time
FROM del_log
WHERE admin = '%s'
ORDER BY id DESC
LIMIT 1
SQL;
      $res = mysql_global_call($query, $user['username']);
      $row = mysql_fetch_assoc($res);
      
      $this->levels[$user['username']] = $user['level'];
      $this->last_actions[$user['username']] = $_SERVER['REQUEST_TIME'] - $row['time'];
      
      $this->user_boards[$user['username']] = $user['allow'];
      
      $user_id_map[$user['id']] = $user['username'];
    }
    
    unset($this->last_actions['janitortest']);
    unset($this->last_actions['Auto-ban']);
    
    
    $period = (int)self::STATS_PERIOD_ACTIVITY_DAYS;
    
    $query = <<<SQL
SELECT admin, COUNT(*) as cnt
FROM del_log
WHERE ts > DATE_SUB(NOW(), INTERVAL $period DAY)
GROUP BY admin
SQL;
    
    $res = mysql_global_call($query);
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->action_count[$row['admin']] = $row['cnt'];
    }
    
    // ban requests
    $query = <<<SQL
SELECT created_by_id as id, COUNT(*) as cnt
FROM janitor_stats
WHERE created_on > DATE_SUB(NOW(), INTERVAL $period DAY)
GROUP BY created_by_id
SQL;
    
    $res = mysql_global_call($query);
    
    while ($row = mysql_fetch_assoc($res)) {
      if (isset($user_id_map[$row['id']])) {
        $name = $user_id_map[$row['id']];
        
        if (isset($this->action_count[$name])) {
          $this->action_count[$name] += (int)$row['cnt'];
        }
        else {
          $this->action_count[$name] = (int)$row['cnt'];
        }
      }
    }
    
    // appeals
    $query = <<<SQL
SELECT username, COUNT(*) as cnt
FROM appeal_stats
WHERE created_on > DATE_SUB(NOW(), INTERVAL $period DAY)
GROUP BY username
SQL;
    
    $res = mysql_global_call($query);
    
    while ($row = mysql_fetch_assoc($res)) {
      $name = $row['username'];
      
      if (isset($this->action_count[$name])) {
        $this->action_count[$name] += (int)$row['cnt'];
      }
      else {
        $this->action_count[$name] = (int)$row['cnt'];
      }
    }
    
    $this->renderHTML('staffroster-activity');
  }
  
  public function promote_janitor() {
    if (!isset($_POST['id']) || $_POST['id'] == '') {
      $this->error('User ID cannot be empty.');
    }
    
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid or expired OTP.');
    }
    
    $user_id = (int)$_POST['id'];
    
    $query = "SELECT username FROM mod_users WHERE id = $user_id AND level = 'janitor' LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (1)');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error("This user doesn't exist");
    }
    
    $user = mysql_fetch_assoc($res);
    
    // Upgrade account
    $query =<<<SQL
UPDATE mod_users SET level = 'mod', allow = 'all', flags = ''
WHERE id = $user_id AND level = 'janitor' LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (2)');
    }
    
    $this->success([ 'username' => $user['username'] ]);
  }
  
  public function remove_janitor_htpasswd() {
    if (!has_flag('developer')) {
      die("You don't have access to this tool");
    }
    
    if (!isset($_GET['username'])) {
      $this->error('Bad Request.');
    }
    
    $username = $_GET['username'];
    
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_GET['otp'])) {
      $this->error('Invalid OTP.');
    }
    
    $cmd = sprintf(self::HTPASSWD_RM_CMD,
      self::JANITOR_HTPASSWD,
      escapeshellarg($username)
    );
    
    if (system($cmd) === false) {
      $this->error('Internal Server Error (1).');
    }
    
    $cmd = sprintf(self::HTPASSWD_RM_CMD,
      self::JANITOR_HTPASSWD_NGINX,
      escapeshellarg($username)
    );
    
    if (system($cmd) === false) {
      $this->error('Internal Server Error (2).');
    }
    
    echo 'Done';
  }
  
  private function getUserActionStats($user) {
    $user_id = (int)$user['id'];
    
    $username = $user['username'];
    
    $period = (int)self::STATS_PERIOD_ACTION_MONTHS;
    
    $stats = array();
    
    // count deletions
    $query = <<<SQL
SELECT board, resto, COUNT(*) as cnt FROM del_log
WHERE admin = '%s'
AND cleared = 0
AND imgonly = 0
AND ts > DATE_SUB(NOW(), INTERVAL $period MONTH)
GROUP BY board, resto
SQL;
    
    $res = mysql_global_call($query, $username);
    
    if (!$res) {
      $this->errorHTML('Database Error (guas1)');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      if (!isset($stats[$row['board']])) {
        $stats[$row['board']] = array();
      }
      
      $stats[$row['board']]['del_total'] += (int)$row['cnt'];
      
      if ($row['resto'] === '0') {
        $stats[$row['board']]['del_threads'] = (int)$row['cnt'];
      }
    }
    /*
    $query = <<<SQL
SELECT COUNT(*) FROM del_log
WHERE admin = '%s'
AND cleared = 0
AND imgonly = 1
AND ts > DATE_SUB(NOW(), INTERVAL $period MONTH)
SQL;
    
    $res = mysql_global_call($query, $username);
    
    if (!$res) {
      $this->errorHTML('Database Error (guas2)');
    }
    
    $row = mysql_fetch_row($res);
    
    $stats['del_images'] = (int)$row[0];
    */
    // count clears
    $query = <<<SQL
SELECT board, resto, COUNT(*) as cnt FROM del_log
WHERE admin = '%s'
AND cleared = 1
AND ts > DATE_SUB(NOW(), INTERVAL $period MONTH)
GROUP BY board, resto
SQL;
    
    $res = mysql_global_call($query, $username);
    
    if (!$res) {
      $this->errorHTML('Database Error (guas3)');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      if (!isset($stats[$row['board']])) {
        $stats[$row['board']] = array();
      }
      
      $stats[$row['board']]['clear_total'] += (int)$row['cnt'];
      
      if ($row['resto'] === '0') {
        $stats[$row['board']]['clear_threads'] = (int)$row['cnt'];
      }
    }
    
    // count BRs
    $query = <<<SQL
SELECT board, COUNT(*) as cnt FROM del_log
WHERE admin = '%s'
AND template_id > 0
AND ts > DATE_SUB(NOW(), INTERVAL $period MONTH)
GROUP BY board
SQL;
    
    $res = mysql_global_call($query, $username);
    
    if (!$res) {
      $this->errorHTML('Database Error (guas4)');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $stats[$row['board']]['ban_requests'] = (int)$row['cnt'];
    }
    
    
    return $stats;
  }
  
  private function renderTemplateUsageStats($user_id) {
    $user_id = (int)$user_id;
    
    if (!$user_id) {
      $this->errorHTML('Bad Request.');
    }
    
    // get username
    $query = "SELECT username, allow FROM mod_users WHERE id = $user_id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorHTML('Database Error (1)');
    }
    
    $this->user = mysql_fetch_assoc($res);
    
    if (!$this->user) {
      $this->errorHTML('User Not Found.');
    }
    
    // get template list
    $query = 'SELECT no, name FROM ban_templates';
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorHTML('Database Error (2)');
    }
    
    $this->ban_templates = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->ban_templates[$row['no']] = $row['name'];
    }
    
    // fetch template usage data
    
    $tbl = 'janitor_stats';
    
    $action_denied = self::STATS_ACTION_DENIED;
    $action_accepted = self::STATS_ACTION_ACCEPTED;
    
    // global template usage
    $query = <<<SQL
SELECT requested_tpl, COUNT(*) as cnt FROM $tbl
WHERE user_id = $user_id GROUP BY requested_tpl
ORDER BY cnt DESC
SQL;
    
    $res = mysql_global_call($query);
    
    if (mysql_num_rows($res) < 1) {
      $this->errorHTML('No data available for this user.');
    }
    
    $this->global_usage = array();
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        $this->global_usage[$row['requested_tpl']] = (int)$row['cnt'];
      }
    }
    
    // denied template usage
    $query = <<<SQL
SELECT requested_tpl, COUNT(*) as cnt FROM $tbl
WHERE user_id = $user_id AND action_type = $action_denied GROUP BY requested_tpl
ORDER BY cnt DESC
SQL;
    
    $res = mysql_global_call($query);
    
    $this->denied_usage = array();
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        $this->denied_usage[$row['requested_tpl']] = (int)$row['cnt'];
      }
    }
    
    // amended usage
    $query = <<<SQL
SELECT requested_tpl, COUNT(*) as cnt FROM $tbl
WHERE user_id = $user_id AND action_type = $action_accepted AND requested_tpl != accepted_tpl
GROUP BY requested_tpl ORDER BY cnt DESC
SQL;
    
    $res = mysql_global_call($query);
    
    $this->amended_usage = array();
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        $this->amended_usage[$row['requested_tpl']] = (int)$row['cnt'];
      }
    }
    
    // action stats
    $this->action_stats = $this->getUserActionStats($this->user);
    
    $this->renderHTML('staffroster-scoreboard-templates');
  }
  
  /**
   * Shows potentially suspicious activity
   */
  
  public function extra_logs() {
    if (!isset($_GET['user']) || !$_GET['user']) {
      $this->error('Bad Request');
    }
    
    $username = $_GET['user'];
    
    $janitor = $this->get_user_by_name($username);
    
    if (!$janitor) {
      $this->errorHTML('Nothing Found');
    }
    
    $days = (int)self::EVENT_LOG_DAYS;
    
    // Deletions and Clears
    
    $sql = <<<SQL
SELECT type, created_on, ip, meta, board, post_id, meta
FROM event_log
WHERE arg_str = '%s' AND created_on >= DATE_SUB(NOW(), INTERVAL $days DAY)
AND (type = 'staff_self_del' OR type = 'staff_self_clear')
ORDER BY id DESC
SQL;
    
    $res = mysql_global_call($sql, $username);
    
    if (!$res) {
      $this->errorHTML('Database Error');
    }
    
    $this->data_clr = [];
    $this->data_del = [];
    $this->data_super_del = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $row['meta'] = json_decode($row['meta'], true);
      
      $row['arc'] = $this->archive_url($row['board'], $row['post_id']);
      
      if ($row['type'] === 'staff_self_del') {
        $this->data_del[] = $row;
      }
      else if ($row['type'] === 'staff_self_clear') {
        $this->data_clr[] = $row;
      }
    }
    
    // Unlocked Deletions
    if ($janitor['allow'] != 'all') {
      $valid_boards = explode(',', $janitor['allow']);
      
      $sql = <<<SQL
SELECT imgonly, postno as post_id, board, name, sub, com, filename, ts as created_on
FROM `del_log`
WHERE admin = '%s' AND tool = '' AND ts >= DATE_SUB(NOW(), INTERVAL $days DAY)
SQL;
      
      $res = mysql_global_call($sql, $username);
      
      if (!$res) {
        $this->errorHTML('Database Error');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        if (!in_array($row['board'], $valid_boards)) {
          $row['arc'] = $this->archive_url($row['board'], $row['post_id']);
          $this->data_super_del[] = $row;
        }
      }
    }
    
    $this->renderHTML('staffroster-extra-logs');
  }
  
  /**
   * Show accepted/rejected ban requests stats
   */
  public function scoreboard() {
    if (isset($_GET['id'])) {
      return $this->renderTemplateUsageStats($_GET['id']);
    }
    
    $users = $this->getUsers();
    
    $this->current_users = array();
    
    foreach ($users as $user) {
      $this->current_users[$user['username']] = $user;
      $user_id_map[$user['id']] = $user['username'];
    }
    
    $this->user_stats = array();
    
    $query = <<<SQL
SELECT *,
(CASE WHEN denied = 0 THEN 1.0000 WHEN approved = 0 THEN 0.0000
ELSE (approved / (denied + approved))
END) as ratio
FROM ban_request_stats ORDER BY janitor ASC
SQL;
    
    $res = mysql_global_call($query);
    
    if ($res && mysql_num_rows($res) > 0) {
      while ($row = mysql_fetch_assoc($res)) {
        if (!isset($this->current_users[$row['janitor']])) {
          continue;
        }
        
        $this->user_stats[$row['janitor']] = $row;
      }
    }
    
    // recent
    $this->user_stats_recent = array();
    
    $actions = array(
      'accepted' => self::STATS_ACTION_ACCEPTED,
      'denied' => self::STATS_ACTION_DENIED
    );
    
    foreach ($actions as $action => $action_type) {
      $query = "SELECT user_id, COUNT(*) as cnt FROM janitor_stats WHERE action_type = $action_type GROUP BY user_id";
      
      $res = mysql_global_call($query);
      
      while ($row = mysql_fetch_assoc($res)) {
        if (!isset($user_id_map[$row['user_id']])) {
          continue;
        }
        
        $username = $user_id_map[$row['user_id']];
        
        if (!isset($this->user_stats_recent[$username])) {
          $this->user_stats_recent[$username] = array(
            'accepted' => 0,
            'denied' => 0,
            'amended' => 0,
          );
        }
        
        $this->user_stats_recent[$username][$action] = (int)$row['cnt'];
      }
    }
    
    $action_type = self::STATS_ACTION_ACCEPTED;
    
    $query = <<<SQL
SELECT user_id, COUNT(*) as cnt FROM janitor_stats
WHERE action_type = $action_type AND requested_tpl != accepted_tpl GROUP BY user_id
SQL;
    
    $res = mysql_global_call($query);
    
    while ($row = mysql_fetch_assoc($res)) {
      if (!isset($user_id_map[$row['user_id']])) {
        continue;
      }
      
      $username = $user_id_map[$row['user_id']];
      
      if (!isset($this->user_stats_recent[$username])) {
        $this->user_stats_recent[$username] = array(
          'accepted' => 0,
          'denied' => 0,
          'amended' => 0,
        );
      }
      
      $this->user_stats_recent[$username]['amended'] = (int)$row['cnt'];
    }
    
    // calc ratios
    foreach ($this->user_stats_recent as $username => &$stats) {
      $stats['total'] = $stats['accepted'] + $stats['denied'];
      
      if ($stats['total']) {
        $stats['accepted_ratio'] = (int)((((float)$stats['accepted']) / $stats['total']) * 100);
      }
      else {
        $stats['accepted_ratio'] = 0;
      }
      
      if ($stats['accepted']) {
        $stats['amended_ratio'] = (int)((((float)$stats['amended']) / $stats['accepted']) * 100);
      }
      else {
        $stats['amended_ratio'] = 0;
      }
    }

    $this->renderHTML('staffroster-scoreboard');
  }
  
  public function flags() {
    $query = "SELECT id, username, flags FROM mod_users WHERE level = 'mod' ORDER BY username";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $this->users = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->users[] = $row;
    }
    
    $this->renderHTML('staffroster-flags');
  }
  
  public function remove_janitor() {
    if (!has_level('manager') && !has_flag('developer')) {
      die("You don't have access to this tool");
    }
    
    if (!isset($_POST['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid OTP.');
    }
    
    $query = "SELECT username FROM mod_users WHERE id = $id LIMIT 1";
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('User not found.');
    }
    
    $username = mysql_fetch_row($res)[0];
    
    if (!$username) {
      $this->error('Database Error (2).');
    }
    
    $cmd = sprintf(self::HTPASSWD_RM_CMD,
      self::JANITOR_HTPASSWD,
      escapeshellarg($username)
    );
    
    if (system($cmd) === false) {
      $this->error('Internal Server Error (1).');
    }
    
    $cmd = sprintf(self::HTPASSWD_RM_CMD,
      self::JANITOR_HTPASSWD_NGINX,
      escapeshellarg($username)
    );
    
    if (system($cmd) === false) {
      $this->error('Internal Server Error (2).');
    }
    
    $query =<<<SQL
INSERT INTO mod_users_removed
SELECT id, level, flags, allow, deny, username, email, janitorapp_id,
last_login, ips, signed_agreement, auth_secret FROM mod_users
WHERE username = '%s' LIMIT 1
SQL;
    
    $res = mysql_global_call($query, $username);
    
    $query = "DELETE FROM mod_users WHERE id = $id LIMIT 1";
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (3).');
    }
    
    $this->success();
  }
  
  private function check_email() {
    if (isset($_POST['email'])) {
      if (!isset($_POST['otp'])) {
        $this->errorHTML('Bad Request.');
      }
      
      if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
        $this->errorHTML('Invalid OTP.');
      }
      
      $email = $_POST['email'];
      
      $query = "SELECT username FROM `mod_users` WHERE email = '%s' AND level = 'janitor'";
      
      $res = mysql_global_call($query, $email);
      
      $user = mysql_fetch_assoc($res);
      
      if (!$user) {
        $this->errorHTML('No janitor with this email address.');
      }
      
      $this->email = $email;
      $this->username = $user['username'];
    }
    else {
      $this->email = null;
    }
    
    $this->renderHTML('staffroster-check-email');
  }
  
  private function hash_mod_name($name, $salt) {
    $hashed_bits = hash_hmac('sha256', $name, $salt, true);
    return base64_encode($hashed_bits);
  }
  
  private function match_hashed_mod_name($test_hash) {
    $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
    
    if (!$admin_salt) {
      $this->error('Internal Server Error (ghmn0)');
    }
    
    $tables = array('mod_users', 'mod_users_removed');
    
    foreach ($tables as $table) {
      $query = "SELECT username FROM `$table`";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Internal Server Error (ghmn1)');
      }
      
      while ($row = mysql_fetch_row($res)) {
        $name = $row[0];
        
        $hashed_name = $this->hash_mod_name($name, $admin_salt);
        
        if ($test_hash === $hashed_name) {
          return $name;
        }
      }
    }
    
    return false;
  }
  
  /**
   * Get username from /j/ post number
   */
  public function j_names() {
    if (isset($_GET['post_id'])) {
      $post_id = (int)$_GET['post_id'];
      
      $query = "SELECT name FROM `j` WHERE no = $post_id LIMIT 1";
      
      $res = mysql_board_call($query);
      
      $hashed_name = mysql_fetch_row($res)[0];
      
      if (!$hashed_name) {
        $this->error('Post not found.');
      }
      
      $name = $this->match_hashed_mod_name($hashed_name);
      
      if ($name === false) {
        $this->error('No matching usernames.');
      }
      
      $this->name = $name;
      $this->post_id = $post_id;
    }
    else if (isset($_GET['name'])) {
      $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
      
      if (!$admin_salt) {
        $this->error('Internal Server Error (ghmn0)');
      }
      
      echo $this->hash_mod_name($_GET['name'], $admin_salt);
      
      return;
    }
    else {
      $this->name = null;
    }
    
    $this->renderHTML('staffroster-j-names');
  }
  
  /**
   * Show login ips and dates for a user
   */
  public function ips() {
    if (!has_level('manager') && !has_flag('developer')) {
      die("You don't have access to this tool");
    }
    
    if (isset($_GET['username']) && $_GET['username']) {
      $username = mysql_real_escape_string($_GET['username']);
      $user_sql = "username = '" . $username . "' AND";
    }
    else {
      $user_sql = '';
    }
    
    $query = "SELECT username, ips, last_ua FROM mod_users WHERE $user_sql level != 'admin'";
    
    $res = mysql_global_call($query);
    
    $this->ips = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->ips[] = $row;
    }
    
    $this->action = 'ips';
    
    if (isset($_GET['days'])) {
      $this->recent = ((int)$_GET['days']) * 24 * 60 * 60;
    }
    else {
      $this->recent = null;
    }
    
    $this->need_geo = $this->recent || $user_sql;
    
    $this->renderHTML('staffroster');
  }
  
  /**
   * Update the list of allowed boards for janitors
   */
  public function update_boards() {
    if (!isset($_POST['id']) || !isset($_POST['boards'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    if ($_POST['boards'] == 'all') {
      $boards = 'all';
    }
    else if ($_POST['boards'] === '') {
      $boards = '';
    }
    else {
      $boards = preg_split('/[^a-z0-9]+/i', $_POST['boards']);
      $this->validateBoards($boards);
      $boards = implode(',', $boards);
    }
    
    $query = "SELECT id FROM mod_users WHERE id = $id AND level = 'janitor'";
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Janitor Not Found.');
    }
    
    $query = "UPDATE mod_users SET allow = '%s' WHERE id = $id LIMIT 1";
    $res = mysql_global_call($query, $boards);
    
    if (!$res) {
      $this->error('Database Error (2).');
    }
    
    if (mysql_affected_rows() < 1) {
      $this->error('Nothing changed.');
    }
    
    $this->success(array('boards' => $boards));
  }
  
  /**
   * Update the list of flags for moderators
   */
  public function update_flags() {
    if (!isset($_POST['id']) || !isset($_POST['flags'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    if ($_POST['flags'] === '') {
      $flags = '';
    }
    else {
      if (!preg_match('/^[,a-z0-9]+$/', $_POST['flags'])) {
        $this->error('Invalid flags.');
      }
      $flags = preg_split('/[^a-z0-9]+/i', $_POST['flags']);
      $flags = array_unique($flags);
      $flags = implode(',', $flags);
    }
    
    $query = "SELECT id FROM mod_users WHERE id = $id AND level = 'mod'";
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Moderator Not Found.');
    }
    
    $query = "UPDATE mod_users SET flags = '%s' WHERE id = $id LIMIT 1";
    $res = mysql_global_call($query, $flags);
    
    if (!$res) {
      $this->error('Database Error (2).');
    }
    
    if (mysql_affected_rows() < 1) {
      $this->error('Nothing changed.');
    }
    
    $this->success(array('flags' => $flags));
  }
  
  /**
   * Update janitor emails
   */
  public function update_email() {
    if (!isset($_POST['id']) || !isset($_POST['otp'])) {
      $this->error('Bad Request.');
    }
    
    if (!isset($_POST['old_email']) || $_POST['old_email'] === '') {
      $this->error('Old E-mail cannot be empty.');
    }
    
    if (!isset($_POST['new_email']) || $_POST['new_email'] === '') {
      $this->error('New E-mail cannot be empty.');
    }
    
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid OTP.');
    }
    
    $id = (int)$_POST['id'];
    
    $old_email = trim($_POST['old_email']);
    $new_email = trim($_POST['new_email']);
    
    if (!$old_email) {
      $this->error('Old E-mail cannot be empty.');
    }
    
    if (!$new_email) {
      $this->error('New E-mail cannot be empty.');
    }
    
    $query = "SELECT username, email FROM mod_users WHERE id = $id AND level = 'janitor'";
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Janitor Not Found.');
    }
    
    $user = mysql_fetch_assoc($res);
    
    if (strtolower($old_email) !== strtolower($user['email'])) {
      $this->error("Old E-mail doesn't match.");
    }
    
    $query = "UPDATE mod_users SET email = '%s' WHERE id = $id LIMIT 1";
    $res = mysql_global_call($query, $new_email);
    
    if (!$res) {
      $this->error('Database Error (2).');
    }
    
    if (mysql_affected_rows() < 1) {
      $this->error('Nothing changed.');
    }
    
    $subject = "E-mail address changed for " . $user['username'];
    
    $message = <<<TXT
The E-mail address for {$user['username']} has been changed to {$new_email} by {$_COOKIE['4chan_auser']}
TXT;
    
    $this->success();
    
    fastcgi_finish_request();
    
    $this->send_confirmation_email($subject, $message);
  }
  
  /**
   * Access control management for janitors
   */
  public function manage() {
    $query = "SELECT id, username, allow FROM mod_users wHERE level = 'janitor' ORDER BY username ASC";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $this->janitors = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->janitors[] = $row;
    }
    
    $this->renderHTML('staffroster-manage');
  }
  
  /**
   * Shows daily moderation activity by hour
   */
  public function coverage() {
    if (!isset($_GET['board']) || $_GET['board'] === '') {
      $this->error('Bad Request.');
    }
    
    
  }
  
  /**
   * Default page
   */
  public function index() {
    $data = $this->getUsersByBoard(self::JANITOR);
    
    $this->users = $data['users'];
    $this->boards = $data['boards'];
    
    $this->renderHTML('staffroster');
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
