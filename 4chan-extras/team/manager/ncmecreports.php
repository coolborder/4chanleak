<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once '../lib/sec.php';
require_once '../lib/otp_session.php';

define('IN_APP', true);

auth_user();

if (!has_level('admin') && (!has_level('manager') || !has_flag('ncmec')) && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

OTPSession::validate();
/*
$mysql_suppress_err = false;
ini_set('display_errors', 1);
error_reporting(E_ALL);
*/

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'view',
      'search'
    )
    ;
  
  const TPL_ROOT = '../views/';
  
  const
    REP_TABLE = 'ncmec_reports',
    OLD_REP_TABLE = 'ncmec_reports_old',
    WEBROOT = '/manager/ncmecreports',
    DATE_FORMAT_SQL ='%m/%d/%y %H:%i:%s',
    PAGE_SIZE = 100
  ;
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  final protected function success($redirect = null) {
    $this->redirect = $redirect;
    $this->renderHTML('success');
    die();
  }
  
  final protected function error($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * View report contents
   */
  public function view() {
    if (isset($_GET['id'])) {
      $mode_ncmec = false;
      $id = (int)$_GET['id'];
    }
    else if (isset($_GET['ncmec_id'])) {
      $mode_ncmec = true;
      $id = (int)$_GET['ncmec_id'];
    }
    else {
      $this->error('Bad Request');
    }
    
    if (isset($_GET['old'])) {
      $this->old_mode = true;
      $tbl = self::OLD_REP_TABLE;
    }
    else {
      $this->old_mode = false;
      $tbl = self::REP_TABLE;
    }
    
    // Searching by ncmec_id.
    // Check the old table if nothing is found in the new one.
    if ($mode_ncmec) {
      $col = 'report_ncmec_id';
      $query = "SELECT * FROM `$tbl` WHERE report_ncmec_id = $id";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database error (1).');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Nothing found.');
      }
      
      $this->report = mysql_fetch_assoc($res);
    }
    // Search by id only in the new table.
    else {
      $col = 'id';
    }
    
    $query = "SELECT * FROM `$tbl` WHERE $col = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Nothing found.');
    }
    
    $this->report = mysql_fetch_assoc($res);
    
    $this->renderHTML('ncmecreports');
  }
  
  /**
   * Search
   */
  public function search() {
    $clauses = array();
    
    if (isset($_GET['board']) && $_GET['board'] !== '') {
      $board = $_GET['board'];
      
      if (!preg_match('/^[a-z0-9]+$/', $board)) {
        $this->error('Invalid board.');
      }
      
      $clauses[] = "`board` = '" . mysql_real_escape_string($board) . "'";
    }
    
    if (isset($_GET['pid']) && $_GET['pid'] !== '') {
      $pid = (int)$_GET['pid'];
      
      if (!$pid) {
        $this->error('Invalid post number.');
      }
      
      $clauses[] = "`post_num` = $pid";
    }
    
    if (isset($_GET['props']) && is_array($_GET['props']) && !empty($_GET['props'])) {
      if (!isset($_GET['vals']) || !is_array($_GET['vals']) || empty($_GET['vals'])) {
        $this->error('Missing values.');
      }
      
      $props = array();
      
      foreach ($_GET['props'] as $prop) {
        if ($prop === '') {
          continue;
        }
        
        if (!preg_match('/^[_a-z0-9]+$/', $prop)) {
          $this->error('Invalid property: ' . htmlspecialchars($prop));
        }
        
        $props[] = $prop;
      }
      
      $vals = array();
      
      foreach ($_GET['vals'] as $val) {
        if ($val !== '') {
          $vals[] = $val;
        }
      }
      
      if (count($props) !== count($vals)) {
        $this->error('Prop/Value mismatch.');
      }
      
      $pairs = array_combine($props, $vals);
      
      foreach ($pairs as $prop => $val) {
        $prop = mysql_real_escape_string($prop);
        
        $recast = (string)(int)$val;
        
        if ($val === $recast) {
          $val = (int)$val;
        }
        else {
          $val = mysql_real_escape_string($val);
        }
        
        $clauses[] = "`post_json` LIKE '%\"$prop\":%$val%'";
      }
    }
    else {
      $pairs = null;
    }
    
    if (empty($clauses)) {
      $this->error('Empty query.');
    }
    
    if ($pairs) {
      $post_col = ' post_json,';
    }
    else {
      $post_col = '';
    }
    
    $tables = array(self::REP_TABLE => false, self::OLD_REP_TABLE => true);
    
    $date_fmt = self::DATE_FORMAT_SQL;
    
    $where = implode(' AND ', $clauses);
    
    $reports = array();
    
    foreach ($tables as $tbl => $old_mode) {
      $query = <<<SQL
SELECT id, board, post_num,$post_col
DATE_FORMAT(report_sent_timestamp, '$date_fmt') as sent_on,
report_ncmec_id as ncmec_id, report_sent
FROM `$tbl`
WHERE $where
ORDER BY id DESC
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database error.');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        if ($pairs) {
          $props = array();
          
          $json = json_decode($row['post_json']);
          
          foreach ($json as $key => $value) {
            if (isset($pairs[$key])) {
              $props[$key] = $value;
            }
          }
          
          $row['props'] = $props;
        }
        
        $row['old'] = $old_mode;
        
        $reports[] = $row;
      }
    }
    
    $this->has_props = !!$pairs;
    
    $this->items = $reports;
    
    $this->renderHTML('ncmecreports-search');
  }
  
  /**
   * Default page
   */
  public function index() {
    if (isset($_GET['search'])) {
      $this->renderHTML('ncmecreports-search');
      return;
    }
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $this->search_qs = array();
    
    if (isset($_GET['unsent'])) {
      $this->unsent_mode = true;
      $this->search_qs[] = 'unsent';
      $where = 'WHERE report_sent = 0';
    }
    else {
      $this->unsent_mode = false;
      $where = $this->search_qs = '';
    }
    
    if (isset($_GET['old'])) {
      $this->old_mode = true;
      $this->search_qs[0] = 'old';
      $tbl = self::OLD_REP_TABLE;
    }
    else {
      $this->old_mode = false;
      $tbl = self::REP_TABLE;
    }
    
    // Count unsent reports
    $query = "SELECT COUNT(*) FROM `$tbl` WHERE report_sent = 0";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->unsent_count = (int)mysql_fetch_row($res)[0];
    
    // ---
    
    $lim = self::PAGE_SIZE + 1;
    
    $date_fmt = self::DATE_FORMAT_SQL;
    
    $query = <<<SQL
SELECT id, board, post_num,
DATE_FORMAT(report_sent_timestamp, '$date_fmt') as sent_on,
report_sent,
report_ncmec_id as ncmec_id
FROM `$tbl`
$where
ORDER BY id DESC
LIMIT $offset,$lim
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    // ---
    
    $this->offset = $offset; 
    
    $this->previous_offset = $offset - self::PAGE_SIZE;
    
    if ($this->previous_offset < 0) {
      $this->previous_offset = 0;
    }
    
    if (mysql_num_rows($res) === $lim) {
      $this->next_offset = $offset + self::PAGE_SIZE;
    }
    else {
      $this->next_offset = 0;
    }
    
    $this->items = array();
    
    while($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    if ($this->next_offset) {
      array_pop($this->items);
    }
    
    if (!empty($this->search_qs)) {
      $this->search_qs = implode('&amp;', $this->search_qs) . '&amp;';
    }
    else {
      $this->search_qs = '';
    }
    
    $this->renderHTML('ncmecreports');
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
