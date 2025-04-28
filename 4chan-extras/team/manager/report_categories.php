<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

require_once '../lib/sec.php';

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

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
      'update',
      'delete',
      'settings',
      'settings_update',
      'settings_delete'
    ),
    
    $date_format = 'm/d/y H:i'
    ;
  
  const TBL_NAME = 'report_categories';
  const SETTINGS_TBL = 'report_settings';
  
  // Multiplier for boards
  const MAX_COEF = 100.00;
  const MIN_COEF = 0.01;
  
  const MAX_WEIGHT = 9999.99;
  
  const WS_BOARD_TAG = '_ws_';
  const NWS_BOARD_TAG = '_nws_';
  
  const TPL_ROOT = '../views/';
  
  const WEBROOT = '/manager/report_categories.php';
  
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
  
  private function pluralize($count, $one = '', $not_one = 's') {
    return $count == 1 ? $one : $not_one;
  }
  
  private function validateBoards($ary, $allow_ws_nws = false) {
    $boards = $this->get_boards($allow_ws_nws);
    
    foreach ($ary as $b) {
      if (!isset($boards[$b])) {
        $this->error('Invalid board.');
      }
    }
    
    return true;
  }
  
  private function get_boards($allow_ws_nws = false) {
    $query = 'SELECT dir FROM boardlist';
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[$board['dir']] = true;
    }
    
    if ($allow_ws_nws) {
      $boards['_nws_'] = true;
      $boards['_ws_'] = true;
    }
    
    $boards['test'] = true;
    
    return $boards;
  }
  
  private function updateCommit() {
    $id = (int)$_POST['id'];
    
    $tbl = self::TBL_NAME;
    
    $query = "SELECT * FROM `$tbl` WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    $cat = mysql_fetch_assoc($res);
    
    if (!$cat) {
      $this->error('Category not found.');
    }
    
    $clauses = array();
    
    // Board
    if (isset($_POST['board']) && $_POST['board'] !== '') {
      $board = $_POST['board'];
      
      $this->validateBoards(array($_POST['board']), true);
      
      if ($cat['board'] !== $board) {
        $board = mysql_real_escape_string($board);
        
        if ($board === false) {
          $this->error('Database error (1).');
        }
        
        $clauses[] = "board = '" . $board . "'";
      }
    }
    
    // Title
    if (!isset($_POST['title']) || $_POST['title'] === '') {
      $this->error('Title cannot be empty.');
    }
    
    $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
    
    if ($cat['title'] !== $title) {
      $title = mysql_real_escape_string($title);
      
      if ($title === false) {
        $this->error('Database error (2).');
      }
      
      $clauses[] = "title = '" . $title . "'";
    }
    
    // Weight
    if (!isset($_POST['weight'])) {
      $this->error('Weight cannot be empty.');
    }
    
    $weight = (float)$_POST['weight'];
    
    if ($weight > self::MAX_WEIGHT) {
      $this->error('Weight cannot be greater than ' . self::MAX_WEIGHT);
    }
    
    if ($weight !== (float)$cat['weight']) {
      $clauses[] = "weight = $weight";
    }
    
    // Exclude boards
    if (isset($_POST['exclude_boards'])) {
      if ($_POST['exclude_boards'] === '') {
        $exclude_boards = '';
      }
      else {
        $exclude_boards = preg_split('/[^a-z0-9]+/i', $_POST['exclude_boards']);
        $this->validateBoards($exclude_boards);
        $exclude_boards = implode(',', $exclude_boards);
      }
    }
    else {
      $exclude_boards = '';
    }
    
    if ($cat['exclude_boards'] !== $exclude_boards) {
      $exclude_boards = mysql_real_escape_string($exclude_boards);
      $clauses[] = "exclude_boards = '$exclude_boards'";
    }
    
    // Filtered threshold
    if (!isset($_POST['filtered'])) {
      $this->error('Filter threshold cannot be empty.');
    }
    
    $filtered = (int)$_POST['filtered'];
    
    if ($filtered !== (int)$cat['filtered']) {
      $clauses[] = "filtered = $filtered";
    }
    
    // Options
    $opts = array('op_only', 'reply_only', 'image_only');
    
    foreach ($opts as $opt) {
      if (isset($_POST[$opt]) && $_POST[$opt]) {
        $val = '1';
      }
      else {
        $val = '0';
      }
      
      if ($cat[$opt] !== $val) {
        $clauses[] = "$opt = $val";
      }
    }
    
    if (empty($clauses)) {
      $this->error('Nothing to do.');
    }
    
    $clauses = implode(',', $clauses);
    
    $sql = "UPDATE `$tbl` SET $clauses WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  private function createCommit() {
    $tbl = self::TBL_NAME;
    
    // Board
    if (isset($_POST['board']) && $_POST['board'] !== '') {
      $board = $_POST['board'];
      $this->validateBoards(array($_POST['board']), true);
    }
    
    // Title
    if (!isset($_POST['title']) || $_POST['title'] === '') {
      $this->error('Title cannot be empty.');
    }
    
    $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
    
    // Weight
    if (!isset($_POST['weight'])) {
      $this->error('Weight cannot be empty.');
    }
    
    $weight = (int)$_POST['weight'];
    
    // Exclude boards
    if (isset($_POST['exclude_boards'])) {
      if ($_POST['exclude_boards'] === '') {
        $exclude_boards = '';
      }
      else {
        $exclude_boards = preg_split('/[^a-z0-9]+/i', $_POST['exclude_boards']);
        $this->validateBoards($exclude_boards);
        $exclude_boards = implode(',', $exclude_boards);
      }
    }
    else {
      $exclude_boards = '';
    }
    
    // Filtered threshold
    if (!isset($_POST['filtered'])) {
      $this->error('Filter threshold cannot be empty.');
    }
    
    $filtered = (int)$_POST['filtered'];
    
    // Options
    $opts = array('op_only' => 0, 'reply_only' => 0, 'image_only' => 0);
    
    foreach ($opts as $opt => $val) {
      if (isset($_POST[$opt]) && $_POST[$opt]) {
        $opts[$opt] = 1;
      }
    }
    
    $sql =<<<SQL
INSERT INTO `$tbl` (board, title, weight, exclude_boards, filtered, op_only, reply_only, image_only)
VALUES('%s', '%s', %d, '%s', %d, %d, %d, %d)
SQL;
    
    $res = mysql_global_call($sql, $board, $title, $weight, $exclude_boards,
      $filtered,
      $opts['op_only'], $opts['reply_only'], $opts['image_only']
    );
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  public function delete() {
    if (!isset($_POST['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    $tbl = self::TBL_NAME;
    
    $query = "SELECT * FROM `$tbl` WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    $cat = mysql_fetch_assoc($res);
    
    if (!$cat) {
      $this->error('Category not found.');
    }
    
    $query = "DELETE FROM `$tbl` WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Settings page
   */
  public function settings() {
    $tbl = self::SETTINGS_TBL;
    
    $sql = "SELECT * FROM `$tbl`";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->entries = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->entries[] = $row;
    }
    
    $this->renderHTML('report_categories-settings');
  }
  
  
  private function updateCommit_settings() {
    $id = (int)$_POST['id'];
    
    $tbl = self::SETTINGS_TBL;
    
    $query = "SELECT * FROM `$tbl` WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    $cat = mysql_fetch_assoc($res);
    
    if (!$cat) {
      $this->error('Setting not found.');
    }
    
    $clauses = array();
    
    // Coefficient
    if (!isset($_POST['coef'])) {
      $this->error('Coefficient cannot be empty.');
    }
    
    $coef = round((float)$_POST['coef'], 2);
    
    if ($coef > self::MAX_COEF || $coef < self::MIN_COEF) {
      $this->error('Coefficient should be between ' . self::MIN_COEF . ' and ' . self::MAX_COEF);
    }
    
    if ((string)$coef !== $cat['coef']) {
      $clauses[] = "coef = '$coef'";
    }
    
    // Exclude boards
    if (!isset($_POST['boards']) || $_POST['boards'] === '') {
      $this->error('Board list cannot be empty.');
    }
    
    $boards = preg_split('/[^a-z0-9]+/i', $_POST['boards']);
    $this->validateBoards($boards);
    $boards = implode(',', $boards);
    
    if ($cat['boards'] !== $boards) {
      $boards = mysql_real_escape_string($boards);
      $clauses[] = "boards = '$boards'";
    }
    
    if (empty($clauses)) {
      $this->error('Nothing to do.');
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    $clauses[] = "updated_on = $now";
    
    $user = mysql_real_escape_string($_COOKIE['4chan_auser']);
    $clauses[] = "updated_by = '$user'";
    
    $clauses = implode(',', $clauses);
    
    $sql = "UPDATE `$tbl` SET $clauses WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT . '?action=settings');
  }
  
  private function createCommit_settings() {
    $tbl = self::SETTINGS_TBL;
    
    // Coefficient
    if (!isset($_POST['coef'])) {
      $this->error('Coefficient cannot be empty.');
    }
    
    $coef = round((float)$_POST['coef'], 2);
    
    if ($coef > self::MAX_COEF || $coef < self::MIN_COEF) {
      $this->error('Coefficient should be between ' . self::MIN_COEF . ' and ' . self::MAX_COEF);
    }
    
    // Exclude boards
    if (!isset($_POST['boards']) || $_POST['boards'] === '') {
      $this->error('Board list cannot be empty.');
    }
    
    $boards = preg_split('/[^a-z0-9]+/i', $_POST['boards']);
    $this->validateBoards($boards);
    $boards = implode(',', $boards);
    
    $now = $_SERVER['REQUEST_TIME'];
    $user = $_COOKIE['4chan_auser'];
    
    $sql =<<<SQL
INSERT INTO `$tbl` (boards, coef, updated_on, updated_by)
VALUES('%s', '%s', %d, '%s')
SQL;
    
    $res = mysql_global_call($sql, $boards, $coef, $now, $user);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT . '?action=settings');
  }
  
  public function settings_delete() {
    if (!isset($_POST['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    $tbl = self::SETTINGS_TBL;
    
    $query = "SELECT * FROM `$tbl` WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    $cat = mysql_fetch_assoc($res);
    
    if (!$cat) {
      $this->error('Setting not found.');
    }
    
    $query = "DELETE FROM `$tbl` WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Databese Error.');
    }
    
    $this->success(self::WEBROOT . '?action=settings_update');
  }
  
  public function settings_update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['id'])) {
        $this->updateCommit_settings();
      }
      else {
        $this->createCommit_settings();
      }
      
      return;
    }
    else if (isset($_GET['id'])) {
      $id = (int)$_GET['id'];
      $tbl = self::SETTINGS_TBL;
      $query = "SELECT * FROM `$tbl` WHERE id = $id LIMIT 1";
      $res = mysql_global_call($query);
      $this->entry = mysql_fetch_assoc($res);
      if (!$this->entry) {
        $this->error('Setting not found.');
      }
    }
    else {
      $this->entry = array();
    }
    
    $this->renderHTML('report_categories-settings-update');
  }
  
  /**
   * Default page
   */
  public function index() {
    $tbl = self::TBL_NAME;
    
    $sql = "SELECT * FROM `$tbl` ORDER BY board ASC";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->categories = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->categories[] = $row;
    }
    
    $this->renderHTML('report_categories');
  }
  
  public function update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (isset($_POST['id'])) {
        $this->updateCommit();
      }
      else {
        $this->createCommit();
      }
      
      return;
    }
    else if (isset($_GET['id'])) {
      $id = (int)$_GET['id'];
      $tbl = self::TBL_NAME;
      $query = "SELECT * FROM `$tbl` WHERE id = $id LIMIT 1";
      $res = mysql_global_call($query);
      $this->cat = mysql_fetch_assoc($res);
      if (!$this->cat) {
        $this->error('Category not found.');
      }
    }
    else {
      $this->cat = array();
    }
    
    $this->board_list = $this->get_boards(true);
    
    $this->renderHTML('report_categories-update');
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
