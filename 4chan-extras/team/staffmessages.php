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

if (has_flag('developer')) {
  ini_set('display_errors', '1');
  error_reporting(E_ALL & ~E_NOTICE);
  $mysql_suppress_err = false;
}

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'create',
      'delete'
    ),
    
    $date_format = 'm/d/y H:i'
    ;
  
  const TPL_ROOT = 'views/';
  
  const DATE_FORMAT ='m/d/y H:i';
  
  const WEBROOT = '/staffmessages.php';
  
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
  
  private function format_staff_message($msg) {
    return preg_replace('@https://i.4cdn.org/j/[a-z0-9]+/[0-9]+\.(?:jpg|png|gif)@', '<img alt="" src="\\0">', $msg);
  }
  
  private function create() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->error('Bad Request.');
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    $created_by = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    // boards
    if (isset($_POST['boards'])) {
      if ($_POST['boards'] === '') {
        $boards = '';
      }
      else {
        $boards = preg_split('/[^_a-z0-9]+/i', $_POST['boards']);
        $this->validateBoards($boards);
        $boards = implode(',', $boards);
      }
    }
    else {
      $boards = '';
    }
    
    // Content
    if (!isset($_POST['content']) || $_POST['content'] === '') {
      $this->error('The message cannot be empty.');
    }
    
    $content = htmlspecialchars($_POST['content'], ENT_QUOTES);
    
    $content = nl2br($content);
    
    $sql =<<<SQL
INSERT INTO staff_messages (created_on, created_by, boards, content)
VALUES (%d, '%s', '%s', '%s')
SQL;
    
    $res = mysql_global_call($sql, $now, $created_by, $boards, $content);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  private function delete() {
    if (!isset($_GET['id'])) {
      $this->error('Message not found.');
    }
    
   $id = (int)$_GET['id'];
   
   $sql = "DELETE FROM staff_messages WHERE id = $id LIMIT 1";
   
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Default page
   */
  public function index() {
    $sql = 'SELECT * FROM staff_messages ORDER BY id DESC';
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->messages = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->messages[] = $row;
    }
    
    $this->renderHTML('staffmessages');
  }
  
  /**
   * Main
   */
  public function run() {
    $method = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (php_sapi_name() === 'cli') {
      $action = 'exec';
    }
    else if (isset($method['action'])) {
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
