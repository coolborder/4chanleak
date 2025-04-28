<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('mod')) {
  APP::denied();
}

require_once 'lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'nav',
      'dashboard',
      'scoreboard',
      'staff_overview'
    )
  ;
  
  const TPL_ROOT = 'views/';
  
  const STAFF_STATS_DAYS = 7;
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Returns the data as json
   */
  final protected function successJSON($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
    die();
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function errorJSON($message = null) {
    $payload = array('status' => 'error', 'message' => $message);
    $this->renderJSON($payload);
    die();
  }
  
  /**
   * Returns a JSON response
   */
  private function renderJSON($data) {
    header('Content-type: application/json');
    echo json_encode($data);
  }
  
  private function get_self_clr_stats() {
    $days = (int)self::STAFF_STATS_DAYS;
    
    $sql = <<<SQL
SELECT arg_str, COUNT(*) as cnt FROM event_log
WHERE type = 'staff_self_clear'
AND created_on >= DATE_SUB(NOW(), INTERVAL $days DAY)
GROUP BY arg_str
ORDER BY cnt DESC
LIMIT 10
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->errorJSON('Database Error');
    }
    
    $data = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $data[$row['arg_str']] = $row['cnt'];
    }
    
    return $data;
  }
  
  private function get_self_del_stats() {
    $days = (int)self::STAFF_STATS_DAYS;
    
    $sql = <<<SQL
SELECT arg_str, COUNT(*) as cnt FROM event_log
WHERE type = 'staff_self_del'
AND created_on >= DATE_SUB(NOW(), INTERVAL $days DAY)
GROUP BY arg_str
ORDER BY cnt DESC
LIMIT 10
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->errorJSON('Database Error');
    }
    
    $data = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $data[$row['arg_str']] = $row['cnt'];
    }
    
    return $data;
  }
  
  private function get_fence_skip_stats() {
    $days = (int)self::STAFF_STATS_DAYS;
    
    // Get janitors
    $sql = "SELECT username, allow FROM mod_users WHERE level = 'janitor' AND allow != 'all'";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->errorJSON('Database Error');
    }
    
    $janitor_list = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $janitor_list[$row['username']] = explode(',', $row['allow']);
    }
    
    // Get deletions
    $sql = <<<SQL
SELECT admin, board FROM `del_log`
WHERE tool = '' AND ts >= DATE_SUB(NOW(), INTERVAL $days DAY)
GROUP BY admin, board
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->errorJSON('Database Error');
    }
    
    $data = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $username = $row['admin'];
      
      if (!isset($janitor_list[$username])) {
        continue;
      }
      
      if (!isset($data[$username])) {
        $data[$username] = 0;
      }
      
      if (!in_array($row['board'], $janitor_list[$username])) {
        $data[$username] += 1;
      }
    }
    
    $data = array_filter($data, function($v) { return $v > 0; });
    
    if (!empty($data)) {
      arsort($data, SORT_NUMERIC);
      $data = array_slice($data, 0, 10);
    }
    
    return $data;
  }
  
  public function staff_overview() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->errorJSON('Bad Request');
    }
    
    if (!isset($_GET['mode'])) {
      $this->errorJSON('Bad Request');
    }
    
    $mode = $_GET['mode'];
    
    if ($mode === 'clr') {
      $data = $this->get_self_clr_stats();
    }
    else if ($mode === 'del') {
      $data = $this->get_self_del_stats();
    }
    else if ($mode === 'fence_skip') {
      $data = $this->get_fence_skip_stats();
    }
    else {
      $this->errorJSON('Bad Request');
    }
    
    $this->successJSON($data);
  }
  
  private function scoreboard() {
    // Fetch all mods
    $sql = "SELECT id, username FROM mod_users WHERE level IN ('mod', 'manager')";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Internal Server Error (gss0');
    }
    
    $users = array();
    
    while($row = mysql_fetch_assoc($res)) {
      $users[$row['id']] = $row['username'];
    }
    
    // Count processed ban requests
    $sql =<<<SQL
SELECT created_by_id as user_id, COUNT(*) as cnt FROM janitor_stats
WHERE created_on > DATE_SUB(NOW(), INTERVAL 1 MONTH)
GROUP BY created_by_id
ORDER BY cnt DESC
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Internal Server Error (gss11');
    }
    
    $brs = array();
    
    while($row = mysql_fetch_assoc($res)) {
      $brs[$row['user_id']] = $row['cnt'];
    }
    
    // Count bans
    $sql =<<<SQL
SELECT admin as username, COUNT(*) as cnt FROM banned_users
WHERE `now` > DATE_SUB(NOW(), INTERVAL 1 MONTH)
GROUP BY admin
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Internal Server Error (gss12');
    }
    
    $bans = array();
    
    while($row = mysql_fetch_assoc($res)) {
      $bans[$row['username']] = $row['cnt'];
    }
    
    $this->users = $users;
    $this->brs = $brs;
    $this->bans = $bans;
    
    $this->renderHTML('index-scoreboard');
  }
  
  /**
   * Index
   */
  public function index() {
    $this->renderHTML('index');
  }
  
  public function nav() {
    $this->is_manager = has_level('manager');
    $this->is_admin = has_level('admin');
    $this->is_dev = has_flag('developer');
    
    $this->renderHTML('index-nav');
  }
  
  public function dashboard() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->errorJSON('Bad Request');
    }
    
    $this->renderHTML('index-dashboard');
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
