<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/archives.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}
/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}
*/
require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'search',
      'by_ip'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const DATE_FORMAT = 'r'; // RFC 2822: Thu, 21 Dec 2000 16:01:07 +0200
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  final protected function error($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  /**
   * Returns a hashmap of boards: board_dir => true
   */
  private function get_valid_boards() {
    $query = 'SELECT dir FROM boardlist';
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    $boards = array();
    
    while ($row = mysql_fetch_row($res)) {
      $boards[$row[0]] = true;
    }
    
    //$boards['test'] = true;
    
    return $boards;
  }
  
  // parameters should already be sql safe
  private function search_posts($board, $value, $type) {
    if ($type == 'no') {
      $col = 'no';
    }
    else {
      $col = 'tim';
    }
    
    $query = "SELECT time, host, no FROM `$board` WHERE archived = 0 AND `$col` = $value";
    
    $res = mysql_board_call($query);
    
    if (!$res) {
      $this->error('Database Error (p1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      return null;
    }
    
    return mysql_fetch_assoc($res);
  }
  
  // parameters should already be sql safe
  private function search_user_actions($board, $value, $type) {
    if ($type == 'no') {
      $col = 'postno';
      $val = '%s';
    }
    else if ($type == 'ip') {
      $col = 'ip';
      $val = '%d';
    }
    else {
      $col = 'uploaded';
      $val = '%s';
    }
    
    if ($board) {
      $board_clause = "board = '" . mysql_real_escape_string($board) . "' AND";
    }
    else {
      $board_clause = '';
    }
    
    $query = <<<SQL
SELECT ip as host, UNIX_TIMESTAMP(time) as time, postno as no, action, board FROM user_actions WHERE
$board_clause
(action = 'new_thread' OR action='new_reply')
AND `$col` = $val
SQL;
    
    $res = mysql_global_call($query, $value);
    
    if (!$res) {
      $this->error('Database Error (ua1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      return null;
    }
    
    if ($type !== 'ip') {
      $row = mysql_fetch_assoc($res);
      $row['host'] = long2ip($row['host']);
      return $row;
    }
    else {
      $data = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $data[] = $row;
      }
      
      return $data;
    }
  }
  
  private function get_req_sigs($ip) {
    $sql = "SELECT board, req_sig, UNIX_TIMESTAMP(created_on) as ts FROM flood_log WHERE ip = '%s'";
    
    $res = mysql_global_call($sql, $ip);
    
    if (!$res) {
      return null;
    }
    
    $sigs = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $sigs[] = $row;
    }
    
    return $sigs;
  }
  
  private function by_ip() {
    if ($_GET['ip']  === '') {
      $this->error('IP cannot be empty.');
    }
    
    $value = ip2long($_GET['ip']);
    
    if (!$value) {
      $this->error('Invalid IP.');
    }
    
    $this->type = 'ip';
    $this->value = htmlspecialchars($_GET['ip']);
    $this->results = $this->search_user_actions(null, $value, 'ip');
    
    $this->req_sigs = $this->get_req_sigs($_GET['ip']);
    
    $this->renderHTML('iplookup');
  }
  
  private function search() {
    if ($_GET['board'] === '') {
      $this->error('Board cannot be empty.');
    }
    
    $board = $_GET['board'];
    
    if (isset($_GET['no']) && $_GET['no'] !== '') {
      $value = (int)$_GET['no'];
      $type = 'no';
    }
    else if (isset($_GET['tim']) && $_GET['tim'] !== '') {
      $value = (int)$_GET['tim'];
      $type = 'tim';
    }
    else {
      $value = false;
    }
    
    $valid_boards = $this->get_valid_boards();
    
    if (!isset($valid_boards[$board])) {
      $this->error('Invalid board.');
    }
    
    if (!$value) {
      $this->error('Missing parameters.');
    }
    
    $this->results = $this->search_posts($board, $value, $type);
    
    if (!$this->result) {
      $this->results = $this->search_user_actions($board, $value, $type);
    }
    
    $this->type = $type;
    $this->value = $value;
    $this->board = $board;
    
    $this->renderHTML('iplookup');
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Index
   */
  public function index() {
    if (isset($_GET['board'])) {
      $this->search();
    }
    
    $this->renderHTML('iplookup');
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
