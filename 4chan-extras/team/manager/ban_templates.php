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
      'stats'
      /*, 'create_table'*/
    ),
    
    $date_format = 'm/d/y H:i',
    
    $ban_types = array(
      'local' => 'Local',
      'global' => 'Global',
      'zonly' => 'Unappealable'
    ),
    
    $postban_types = array(
      '' => '',
      'delpost' => 'Delete Post',
      'delfile' => 'Delete File',
      'delall' => 'Delete All By IP',
      'move' => 'Move Thread',
    ),
    
    $blacklist_types = array(
      '' => '',
      'image' => 'Auto-ban',
      'rejectimage' => 'Reject',
    ),
    
    $action_types = array(
      '' => '',
      'quarantine' => 'Quarantine File',
      'revokepass_spam' => 'Revoke Pass (Spam)',
      'revokepass_illegal' => 'Revoke Pass (Illegal Content)',
    ),
    
    $save_types = array(
      '' => '',
      'everything' => 'Post and File',
      'json_only' => 'Post Only',
    ),
    
    $access_types = array(
      'janitor' => 'Janitor',
      'mod' => 'Moderator',
      'manager' => 'Manager',
      'admin' => 'Administrator',
    )
    
    ;
  
  const DEFAULT_PRIVATE_REASON = 'via ban template';
  
  const BANLEN_PERMA = 'indefinite';
  
  const TPL_ROOT = '../views/';
  
  const WEBROOT = '/manager/ban_templates.php';
  
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
  
  private function updateCommit() {
    // Rule ID
    if (isset($_POST['rule']) && preg_match('/^[a-z0-9]+$/', $_POST['rule'])) {
      $rule = $_POST['rule'];
    }
    else {
      $this->error('Invalid Rule ID');
    }
    
    // Name
    if (isset($_POST['name']) && $_POST['name'] !== '') {
      $name = htmlspecialchars($_POST['name'], ENT_QUOTES);
    }
    else {
      $this->error('Invalid Name');
    }
    
    // Ban type
    if (isset($_POST['ban_type']) && $_POST['ban_type'] !== '' && isset($this->ban_types[$_POST['ban_type']])) {
      $bantype = $_POST['ban_type'];
    }
    else {
      $this->error('Invalid Ban Type');
    }
    
    // Ban days
    if (isset($_POST['ban_days']) && $_POST['ban_days'] !== '') {
      $days = (int)$_POST['ban_days'];
      
      if ($days === 0) {
        $bantype = 'global';
      }
      else if ($days === -1) {
        $banlen = self::BANLEN_PERMA;
      }
    }
    else {
      $this->error('Invalid Ban Length');
    }
    
    // Can Warn
    if (isset($_POST['can_warn']) && $_POST['can_warn'] === '1') {
      $can_warn = 1;
    }
    else {
      $can_warn = 0;
    }
    
    // Public ban
    if (isset($_POST['publicban']) && $_POST['publicban'] === '1') {
      $publicban = 1;
    }
    else {
      $publicban = 0;
    }
    
    // Display publicly
    if (isset($_POST['is_public']) && $_POST['is_public'] === '1') {
      $is_public = 1;
    }
    else {
      $is_public = 0;
    }
    
    // Public reasons
    if (isset($_POST['public_reason']) && $_POST['public_reason'] !== '') {
      $publicreason = $_POST['public_reason'];
    }
    else {
      $this->error('Invalid Public Reason');
    }
    
    // Private reason
    if (isset($_POST['private_reason']) && $_POST['private_reason'] !== '') {
      $privatereason = $_POST['private_reason'];
    }
    else {
      $privatereason = self::DEFAULT_PRIVATE_REASON;
    }
    
    // Post-ban action
    if (isset($_POST['postban']) && $_POST['postban'] !== '' && isset($this->postban_types[$_POST['postban']])) {
      $postban = $_POST['postban'];
    }
    else {
      $postban = '';
    }
    
    // Post-ban action param
    $postban_arg = '';
    
    if ($postban === 'move') {
      if (!isset($_POST['postban_arg']) || $_POST['postban_arg'] === '') {
        $this->error('Post-ban Action Param cannot be empty');
      }
      $postban_arg = $_POST['postban_arg'];
    }
    
    // Blacklist
    if (isset($_POST['blacklist']) && $_POST['blacklist'] !== '' && isset($this->blacklist_types[$_POST['blacklist']])) {
      $blacklist = $_POST['blacklist'];
    }
    else {
      $blacklist = '';
    }
    
    // Special action
    if (isset($_POST['special_action']) && $_POST['special_action'] !== '' && isset($this->action_types[$_POST['special_action']])) {
      $special_action = $_POST['special_action'];
    }
    else {
      $special_action = '';
    }
    
    // Save post
    if (isset($_POST['save_post']) && $_POST['save_post'] !== '' && isset($this->save_types[$_POST['save_post']])) {
      $save_post = $_POST['save_post'];
    }
    else {
      $save_post = '';
    }
    
    // Required level
    if (isset($_POST['level']) && $_POST['level'] !== '' && isset($this->access_types[$_POST['level']])) {
      $level = $_POST['level'];
    }
    else {
      $this->error('Invalid Required Level');
    }
    

    // Updating existing entry
    if (isset($_POST['id']) && $_POST['id']) {
      $id = (int)$_POST['id'];
      
      $query = "SELECT no FROM ban_templates WHERE no = $id LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if (!mysql_num_rows($res)) {
        $this->error('Template not found.');
      }
      
      $sql =<<<SQL
UPDATE `ban_templates` SET
rule = '%s',
name = '%s',
bantype = '%s',
banlen = '%s',
publicreason = '%s',
privatereason = '%s',
days = %d,
publicban = %d,
postban = '%s',
postban_arg = '%s',
blacklist = '%s',
special_action = '%s',
save_post = '%s',
is_public = %d,
can_warn = %d,
level = '%s'
WHERE no = $id LIMIT 1
SQL;
      
      $res = mysql_global_call($sql,
      //$res = sprintf($sql,
        $rule, $name, $bantype, $banlen, $publicreason, $privatereason,
        $days, $publicban,
        $postban, $postban_arg, $blacklist, $special_action, $save_post,
        $is_public, $can_warn, $level
      );
      
      //die($res);
      
      if (!$res) {
        $this->error('Database error.');
      }
    }
    // New entry
    else {
      $sql =<<<SQL
INSERT INTO `ban_templates` (
rule, name, bantype, banlen,
publicreason, privatereason, days, publicban,
postban, postban_arg, blacklist, special_action, save_post,
is_public, can_warn, level)
VALUES (
'%s', '%s', '%s', '%s',
'%s', '%s', %d, %d,
'%s', '%s', '%s', '%s', '%s',
%d, %d, '%s'
)
SQL;

      $res = mysql_global_call($sql,
      //$res = sprintf($sql,
        $rule, $name, $bantype, $banlen, $publicreason, $privatereason,
        $days, $publicban,
        $postban, $postban_arg, $blacklist, $special_action, $save_post,
        $is_public, $can_warn, $level
      );
      
      //die($res);
      
      if (!$res) {
        $this->error('Database error.');
      }
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Ban Request stats
   */
  public function stats() {
    // get boards
    $query = "SELECT dir FROM boardlist WHERE dir != 'j' AND dir != 'test'";
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->all_boards = array();
    
    while ($row = mysql_fetch_row($res)) {
      $this->all_boards[] = $row[0];
    }
    
    if (isset($_GET['board']) && $_GET['board']) {
      if (!in_array($_GET['board'], $this->all_boards)) {
        $this->error('Invalid Board');
      }
      
      $this->board = $_GET['board'];
      
      $this->boards = array($this->board);
    }
    else {
      $this->board = 'global';
      
      $this->boards = $this->all_boards;
    }
    
    // get templates
    $sql = "SELECT no, name, rule FROM ban_templates WHERE level = 'janitor' ORDER BY LENGTH(rule), rule ASC";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->ban_templates = array();
    
    $global_templates = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->ban_templates[$row['no']] = $row['name'];
      
      if (preg_match("/^{$this->board}[0-9]+$/", $row['rule'])) {
        $global_templates[] = (int)$row['no'];
      }
    }
    
    // get usage stats
    
    $tbl = 'janitor_stats';
    
    $action_accepted = 1;
    
    // global usage
    $query = <<<SQL
SELECT requested_tpl, COUNT(*) as cnt FROM $tbl
GROUP BY requested_tpl ORDER BY cnt DESC
SQL;
    
    $res = mysql_global_call($query);
    
    $global_usage = array();
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        $global_usage[$row['requested_tpl']] = (int)$row['cnt'];
      }
    }
    
    // accepted template usage
    $query = <<<SQL
SELECT requested_tpl, COUNT(*) as cnt FROM $tbl
WHERE action_type = $action_accepted AND requested_tpl = accepted_tpl
GROUP BY requested_tpl
SQL;
    
    $res = mysql_global_call($query);
    
    $this->acceptance_rates = array();
    
    $this->global_sample_sizes = array();
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        if (!isset($global_usage[$row['requested_tpl']])) {
          continue;
        }
        
        $this->acceptance_rates[$row['requested_tpl']] = round(((int)$row['cnt'] / (float)$global_usage[$row['requested_tpl']]) * 100);
        $this->global_sample_sizes[$row['requested_tpl']] = $global_usage[$row['requested_tpl']];
      }
    }
    
    // per board global templates stats
    $clause = implode(',', $global_templates);
    
    $query = <<<SQL
SELECT requested_tpl, board, COUNT(*) as cnt FROM $tbl
WHERE requested_tpl IN ($clause)
GROUP BY board, requested_tpl
SQL;
    
    $res = mysql_global_call($query);
    
    $board_usage = array();
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        if (!isset($board_usage[$row['requested_tpl']])) {
          $board_usage[$row['requested_tpl']] = array();
        }
        
        $board_usage[$row['requested_tpl']][$row['board']] = (int)$row['cnt'];
      }
    }
    
    $query = <<<SQL
SELECT requested_tpl, board, COUNT(*) as cnt FROM $tbl
WHERE action_type = $action_accepted AND requested_tpl IN ($clause)
AND requested_tpl = accepted_tpl
GROUP BY board, requested_tpl
SQL;
    
    $res = mysql_global_call($query);
    
    $this->board_acceptance_rates = array();

    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        if (!isset($board_usage[$row['requested_tpl']])) {
          continue;
        }
        
        if (!isset($board_usage[$row['requested_tpl']][$row['board']])) {
          continue;
        }
        
        if (!isset($this->board_acceptance_rates[$row['requested_tpl']])) {
          $this->board_acceptance_rates[$row['requested_tpl']] = array();
        }
        
        $this->board_acceptance_rates[$row['requested_tpl']][$row['board']] =
          round(((int)$row['cnt'] / (float)$board_usage[$row['requested_tpl']][$row['board']]) * 100);
      }
    }
    
    $this->sample_sizes = $board_usage;
    
    $this->global_templates = $global_templates;
    
    $this->renderHTML('ban_templates-stats');
  }
  
  /**
   * Default page
   */
  public function index() {
    $sql = 'SELECT * FROM ban_templates ORDER BY rule ASC';
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->templates = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->templates[] = $row;
    }
    
    $this->renderHTML('ban_templates');
  }
  
  public function update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->updateCommit();
    }
    else if (isset($_GET['id'])) {
      $id = (int)$_GET['id'];
      $query = "SELECT * FROM ban_templates WHERE no = $id LIMIT 1";
      $res = mysql_global_call($query);
      $this->tpl = mysql_fetch_assoc($res);
      if (!$this->tpl) {
        $this->error('Template not found.');
      }
    }
    
    $this->renderHTML('ban_templates-update');
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
