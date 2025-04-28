<?php
die();

require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';
require_once 'lib/archives.php';

define('IN_APP', true);

auth_user();

if (!has_level()) {
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
    ),
    // Mask values above this are individual action ids (ex. subject edition)
    $maskShift = 128
  ;
  
  protected
    $actionTypes = array(
      1 => '+Spoiler',
      2 => '-Spoiler',
      3 => '+Archive',
      4 => 'HTML',
      5 => 'Capcode',
      6 => 'Move'
    ),
    
    $actionLabels = array(
      'delete' => 'Delete',
      'clear' => 'Clear',
      'options' => 'Options',
      'html' => 'HTML',
      'capcode' => 'Capcode',
      'move' => 'Move'
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
    include(self::TPL_ROOT . $view . '-test.tpl.php');
  }
  
  /**
   * Get users
   */
  private function getUsers() {
    $query = 'SELECT username FROM mod_users ORDER BY username';
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      die('Error while getting the userlist');
    }
    
    $users = array();
    
    while($row = mysql_fetch_assoc($result)) {
      $users[] = $row['username'];
    }
    
    return $users;
  }
  
  private function formatAction($entry) {
    if (isset($entry['action'])) {
      return implode('<br>', $entry['action']);
    }
    else if ($entry['cleared']) {
      return 'Cleared';
    }
    else if ($entry['imgonly']) {
      return 'Delete File';
    }
    else {
      return 'Delete';
    }
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
   * Resolve changes in thread options
   */
  private function resolveAction($oldmask, $newmask) {
    $actions = array();
    
    $oldmask = (int)$oldmask;
    $newmask =  (int)$newmask;
    
    // Individual actions
    if ($newmask > $this->maskShift) {
      $newmask -= $this->maskShift;
      
      $actions[] = $this->actionTypes[$newmask];
      
      return $actions;
    }
    
    // Thread options
    $changedmask = $oldmask ^ $newmask;
    
    if ($changedmask & 1) {
      if ($oldmask & 1) {
        $actions[] = '-Sticky';
      }
      else {
        $actions[] = '+Sticky';
      }
    }
    
    if ($changedmask & 2) {
      if ($oldmask & 2) {
        $actions[] = '-Perma-sage';
      }
      else {
        $actions[] = '+Perma-sage';
      }
    }
    
    if ($changedmask & 4) {
      if ($oldmask & 4) {
        $actions[] = '-Closed';
      }
      else {
        $actions[] = '+Closed';
      }
    }
  
    if ($changedmask & 8) {
      if ($oldmask & 8) {
        $actions[] = '-Perma-age';
      }
      else {
        $actions[] = '+Perma-age';
      }
    }
    
    if ($changedmask & 16) {
      if ($oldmask & 16) {
        $actions[] = '-Undead';
      }
      else {
        $actions[] = '+Undead';
      }
    }
    
    return $actions;
  }
  
  /**
   * Returns an array of template names matching provided ids
   */
  private function get_templates($ids) {
    if (empty($ids)) {
      return array();
    }
    
    $templates = array();
    
    $clause = 'IN(' . implode(',', $ids) . ')';
    
    $result = mysql_global_call("SELECT no, name FROM ban_templates WHERE no $clause");
    
    if (!mysql_num_rows($result)) {
      $this->error("Couldn't get ban templates");
    }
    
    while ($tpl = mysql_fetch_assoc($result)) {
      $templates[$tpl['no']] = $tpl['name'];
    }
    
    return $templates;
  }
  
  /**
   * Returns log entries as json
   */
  private function get_log() {
    $lim = self::PAGE_SIZE + 1;
    
    $entries = array();
    
    $url_params = array();
    $clauses = array();
    
    // Fetching from actions_log?
    $is_options = false;
    $is_html = false;
    $is_capcode = false;
    
    $use_actions_log = false;
    
    // Type
    if (isset($_GET['type'])) {
      if ($_GET['type'] == 'clear') {
        $url_params['type'] = 'clear';
        $clauses[] = 'cleared = 1';
      }
      else {
        $use_actions_log = true;
        
        if ($_GET['type'] == 'options') {
          $url_params['type'] = 'options';
          $is_options = true;
        }
        else if ($_GET['type'] == 'html') {
          $url_params['type'] = 'html';
          $is_html = true;
        }
        else if ($_GET['type'] == 'capcode') {
          $url_params['type'] = 'capcode';
          $is_capcode = true;
        }
        else if ($_GET['type'] == 'move') {
          $url_params['type'] = 'move';
          $is_move = true;
        }
      }
    }
    else {
      $clauses[] = 'cleared = 0';
      
      // Manual only (skip automatic and cascade deletions)
      if (isset($_GET['manual'])) {
        $url_params['manual'] = 1;
        $clauses[] = "tool NOT IN ('search', 'del-all-by-ip')";
        $clauses[] = "admin != 'autopurge'";
      }
    }
    
    // Board
    if (isset($_GET['board'])) {
      if (!$this->validateBoard($_GET['board'])) {
        $this->error('Invalid board');
      }
      
      $url_params['board'] = $_GET['board'];
      
      $clauses[] = "board = '" . mysql_real_escape_string($_GET['board']) . "'";
    }
    
    // User
    if (isset($_GET['user'])) {
      $url_params['user'] = $_GET['user'];
      $clauses[] = "admin = '" . mysql_real_escape_string($_GET['user']) . "'";
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
      
      $clauses[] = "ts <= '" . $datetime . "'";
      $clauses[] = "ts >= DATE_SUB('" . $datetime . "', INTERVAL 1 DAY)";
    }
    
    // Post
    if (isset($_GET['post'])) {
      $url_params['post'] = (int)$_GET['post'];
      $clauses[] = 'postno = ' . (int)$_GET['post'];
    }
    
    if (isset($_GET['ops']) && $_GET['ops']) {
      $url_params['ops'] = 1;
      $clauses[] = 'resto = 0';
    }
    
    // Offset
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
      $clauses[] = 'id < ' . $offset;
    }
    else {
      $offset = 0;
    }
    // HTML action
    if ($is_html) {
      $clauses[] = 'newmask = 132';
    }
    // Capcode action
    else if ($is_capcode) {
      $clauses[] = 'newmask = 133';
    }
    // Move action
    else if ($is_move) {
      $clauses[] = 'newmask = 134';
    }
    else if ($is_options) {
      $clauses[] = 'newmask < 132';
    }
    
    if (!empty($clauses)) {
      $where = 'WHERE ' . implode(' AND ', $clauses);
    }
    else {
      $where = '';
    }
    
    if ($use_actions_log) {
      $table = 'actions_log';
      $columns = 'oldmask, newmask';
    }
    else {
      $table = 'del_log';
      $columns = 'imgonly, cleared, template_id, resto, tool';
    }
    
    $query = <<<SQL
SELECT id, postno, board, name, sub, com, filename, admin, $columns,
DATE_FORMAT(ts, '%m/%d/%y %H:%i:%s') as date
FROM $table
$where
ORDER BY id DESC
LIMIT $lim
SQL;
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error('Database Error');
    }
    
    $template_ids = array();
    
    while ($row = mysql_fetch_assoc($result)) {
      $row['name'] = str_replace('&#039;', "'", $row['name']);
      
      if (strpos($row['name'], '#') !== false) {
        $names = explode('#', $row['name']);
      }
      else {
        $names = explode('</span> <span class="postertrip">', $row['name']);
      }
      
      if ($names[1]) {
        $row['name'] = $names[0];
        $row['tripcode'] = $names[1];
      }
      
      if (strpos($row['sub'], 'SPOILER<>') === 0) {
        $row['sub'] = substr($row['sub'], 9);
      }
      
      $link = return_archive_link($row['board'], $row['postno'], false, true);
      
      if ($link !== false) {
        $row['link'] = rawurlencode($link);
      }
      
      if ($use_actions_log) {
        $row['action'] = $this->resolveAction($row['oldmask'], $row['newmask']);
      }
      
      if ($row['template_id']) {
        $template_ids[] = $row['template_id'];
      }
      
      $entries[] = $row;
    }
    
    if (mysql_num_rows($result) === $lim) {
      if ($this->next_offset) {
        array_pop($entries);
      }
      
      $this->next_offset = (int)$entries[count($entries) - 1]['id'];
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
    
    $this->templates = $this->get_templates($template_ids);
    
    $this->entries = $entries;
  }
  
  /**
   * Default page
   */
  public function index() {
    $this->users = $this->getUsers();
    $this->boards = $this->getBoards();
    
    $this->get_log();
    
    $this->renderHTML('stafflog');
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
