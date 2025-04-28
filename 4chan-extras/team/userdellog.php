<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';
require_once 'lib/geoip2.php';
require_once 'lib/archives.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

require_once 'lib/csp.php';
/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL & ~E_NOTICE);
}
*/
class APP {
  protected
    // Routes
    $actions = array(
      'index'
    )
  ;
  
  const TPL_ROOT = 'views/';
  
  const PAGE_SIZE = 100;
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  final protected function success($redirect = null) {
    $this->redirect = $redirect;
    $this->renderHTML('success');
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
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Get boards
   */
  private function getBoards() {
    $query = "SELECT dir FROM boardlist";
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      die('Error while getting the boardlist');
    }
    
    $boards = array();
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[] = $board['dir'];
    }
    
    if (has_level('manager') || has_flag('developer')) {
      $boards[] = 'test';
      $boards[] = 'j';
    }
    
    return $boards;
  }
  
  private function validateBoard($board) {
    $boards = $this->getBoards();
    
    return in_array($board, $boards);
  }
  
  /**
   * Returns log entries as json
   */
  private function get_log() {
    $lim = self::PAGE_SIZE + 1;
    
    $entries = array();
    
    $url_params = array();
    $clauses = array();
    
    $clauses[] = "action = 'delete'";
    
    // Board
    if (isset($_GET['board'])) {
      if (!$this->validateBoard($_GET['board'])) {
        $this->error('Invalid board');
      }
      
      $url_params['board'] = $_GET['board'];
      
      $clauses[] = "board = '" . mysql_real_escape_string($_GET['board']) . "'";
    }
    
    // Date
    if (isset($_GET['date'])) {
      if (!preg_match('/[0-9]{2}\/[0-9]{2}(?:\/[0-9]{2})?/', $_GET['date'])) {
        $this->error('Bad date format');
      }
      
      $url_params['date'] = $_GET['date'];
      
      $datetime = explode('/', $_GET['date']);
      
      if (!isset($datetime[2])) {
        $dateyear = date('Y');
      }
      else {
        $dateyear = '20' . $datetime[2];
      }
      
      $datetime = $dateyear . '-' . $datetime[0] . '-' . $datetime[1] . ' 23:59:59';
      $datetime = mysql_real_escape_string($datetime);
      
      $clauses[] = "time <= '" . $datetime . "'";
      $clauses[] = "time >= DATE_SUB('" . $datetime . "', INTERVAL 1 DAY)";
    }
    
    // Post
    if (isset($_GET['post'])) {
      $url_params['post'] = (int)$_GET['post'];
      $clauses[] = 'postno = ' . (int)$_GET['post'];
    }
    
    // Offset
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
      $clauses[] = "time < FROM_UNIXTIME('" . $offset . "')";
    }
    else {
      $offset = 0;
    }
    
    if (!empty($clauses)) {
      $where = 'WHERE ' . implode(' AND ', $clauses);
    }
    else {
      $where = '';
    }
    
    $query = <<<SQL
SELECT ip, board, postno, UNIX_TIMESTAMP(time) as ts, DATE_FORMAT(time, '%m/%d/%y %H:%i:%s') as date
FROM user_actions
$where
ORDER BY time DESC
LIMIT $lim
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error('Database Error');
    }
    
    $template_ids = array();
    
    $country_cache = [];
    
    while ($row = mysql_fetch_assoc($result)) {
      $link = return_archive_link($row['board'], $row['postno'], false, true);
      
      if ($link !== false) {
        $row['link'] = rawurlencode($link);
      }
      
      $row['ip'] = long2ip($row['ip']);
      
      if (isset($country_cache[$row['ip']])) {
        $row['country'] = $country_cache[$row['ip']];
      }
      else {
        $geoinfo = GeoIP2::get_country($row['ip']);
        
        if ($geoinfo && isset($geoinfo['country_code'])) {
          $row['country'] = $geoinfo['country_code'];
        }
        else {
          $row['country'] = 'XX';
        }
        
        $country_cache[$row['ip']] = $row['country'];
      }
      
      $entries[] = $row;
    }
    
    if (mysql_num_rows($result) === $lim) {
      if ($this->next_offset) {
        array_pop($entries);
      }
      
      $this->next_offset = (int)$entries[count($entries) - 1]['ts'];
    }
    else {
      $this->next_offset = 0;
    }
    
    $this->url_params = $url_params;
    
    $tmp_params = array();
    
    foreach ($url_params as $param => $value) {
      $tmp_params[] = $param . '=' . rawurlencode($value);
    }
    
    if (!empty($tmp_params)) {
      $this->search_qs = implode('&amp;', $tmp_params) . '&amp;';
    }
    else {
      $this->search_qs = null;
    }
    
    $this->entries = $entries;
  }
  
  /**
   * Default page
   */
  public function index() {
    $this->boards = $this->getBoards();
    
    $this->get_log();
    
    $this->renderHTML('userdellog');
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
