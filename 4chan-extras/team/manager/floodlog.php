<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once '../lib/sec.php';
define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}

require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const
    STATUS_ACTIVE = 1,
    STATUS_DISABLED = 0
  ;
  
  const
    FLOOD_TABLE = 'flood_log',
    SPAM_TABLE = 'event_log',
    
    BLOCK_ACTION = 'block_flood_check'
  ;
  
  const
    WEBROOT = '/manager/floodlog.php',
    PAGE_SIZE = 150
  ;
  
  public function debug() {
  }
  
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
  
  private function get_boards() {
    $query = 'SELECT dir FROM boardlist ORDER BY dir ASC';
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_row($result)) {
      $boards[] = $board[0];
    }
    
    $boards[] = 'test';
    
    return $boards;
  }
  
  /**
   * Default page
   */
  public function index() {
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $this->boards = $this->get_boards();
    
    $this->current_board = null;
    
    $clause = "type = '" . self::BLOCK_ACTION . "'";
        
    if (isset($_GET['board'])) {
      $board = $_GET['board'];
      
      if (!in_array($board, $this->boards)) {
        $this->error('Invalid board.');
      }
      
      $this->current_board = $board;
      
      $clause .= " AND board = '" . mysql_real_escape_string($board) . "'";
    }
    
    $lim = self::PAGE_SIZE + 1;
    
    $tbl = self::SPAM_TABLE;
    
    $query = "SELECT * FROM $tbl WHERE $clause ORDER BY id DESC LIMIT $offset,$lim";
    
    $res = mysql_global_call($query);
    
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
    
    $this->renderHTML('floodlog');
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
