<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once '../lib/sec.php';
define('IN_APP', true);

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}

$mysql_suppress_err = false;
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'update',
      'delete',
      'view',
      'debug'
    ),
    
    $_cf_ready = false
  ;
  
  const TPL_ROOT = '../views/';
  
  const
    STATUS_ACTIVE = 1,
    STATUS_DISABLED = 0
  ;
  
  const
    POLLS_TABLE = 'polls',
    OPTIONS_TABLE = 'poll_options',
    VOTES_TABLE = 'poll_votes'
  ;
  
  const
    WEBROOT = '/admin/polls.php',
    DATE_FORMAT ='m/d/y',
    PAGE_SIZE = 50
  ;
  
  public function debug() {
    //header('Content-Type: text/plain');
    //echo '<pre>';
    //print_r(scandir(self::IMG_ROOT));
    //echo '</pre>';
  }
  
  public function dump($ary) {
    header('Content-Type: text/plain');
    echo '<pre>';
    print_r($ary);
    echo '</pre>';
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
  
  private function generate_link($id) {
    return 'https://www.4chan.org/polls/' . $id;
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
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function validate_boards($ary) {
    $boards = $this->get_boards();
    
    foreach ($ary as $b) {
      if (!isset($boards[$b])) {
        return false;
      }
    }
    
    return true;
  }
  
  private function disable_expired() {
    $now = (int)$_SERVER['REQUEST_TIME'];
    
    $tbl = self::POLLS_TABLE;
    
    $query =<<<SQL
UPDATE `$tbl` SET active = 0
WHERE expires_on > 0 AND expires_on <= $now
SQL;
    
    return !!mysql_global_call($query);
  }
  
  private function init_cloudflare() {
    if ($this->_cf_ready) {
      return;
    }
    
    global $constants, $INI_PATTERN, $loaded_files, $configdir, $yconfgdir;
    
    require_once 'lib/ini.php';
    
    load_ini("$configdir/cloudflare_config.ini");
    finalize_constants();
    
    define('CLOUDFLARE_EMAIL', 'cloudflare@4chan.org');
    define('CLOUDFLARE_ZONE', '4chan.org');
    define('CLOUDFLARE_ZONE_2', '4cdn.org');
    
    $this->_cf_ready = true;
  }
  
  private function str_to_epoch($str) {
    if (!$str) {
      return 0;
    }
    
    if (preg_match('/^(\d\d)\/(\d\d)\/(\d\d)$/', $str, $m)) {
      return (int)mktime(0, 0, 0, $m[1], $m[2], '20' . $m[3]);
    }
    
    return 0;
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
    
    $lim = self::PAGE_SIZE + 1;
    
    $tbl = self::POLLS_TABLE;
    
    $query = "SELECT * FROM $tbl ORDER BY id DESC LIMIT $offset,$lim";
    
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
    
    $this->renderHTML('polls');
  }
  
  private function update() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $this->poll = null;
      $this->renderHTML('polls-update');
      return;
    }
    
    //$this->dump($_POST);
    //die();
    
    // Active
    if (isset($_POST['active'])) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    // Title
    if (!isset($_POST['title']) || $_POST['title'] === '') {
      $this->error('Title cannot be empty');
    }
    
    $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
    
    // Description
    if (isset($_POST['description']) && $_POST['description'] !== '') {
      //$description = htmlspecialchars($_POST['description'], ENT_QUOTES);
      $description = $_POST['description'];
    }
    else {
      $description = '';
    }
    
    // Options
    $old_options = array();
    $new_options = array();
    
    if (isset($_POST['options']) && is_array($_POST['options'])) {
      
      foreach ($_POST['options'] as $oid => $opt) {
        $oid = (int)$oid;
        $old_options[$oid] = htmlspecialchars($opt, ENT_QUOTES);
      }
    }
    
    if (isset($_POST['new_options']) && is_array($_POST['new_options'])) {
      if (isset($_POST['new_options'])) {
        foreach ($_POST['new_options'] as $opt) {
          if ($opt !== '') {
            $new_options[] = htmlspecialchars($opt, ENT_QUOTES);
          }
        }
      }
    }
    
    if (!$old_options && !$new_options) {
      $this->error('Options cannot be empty.');
    }
    
    // Expiration
    if (isset($_POST['expires']) && $_POST['expires'] !== '') {
      $expires_on = $this->str_to_epoch($_POST['expires']);
    }
    else {
      $expires_on = 0;
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    $username = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    $polls_tbl = self::POLLS_TABLE;
    $opts_tbl = self::OPTIONS_TABLE;
    
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
      
      $query = "SELECT title, description, vote_count FROM $polls_tbl WHERE id = $id";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database error (op0)');
      }
      
      $poll = mysql_fetch_assoc($res);
      
      if (!$poll) {
        $this->error('Poll not found.');
      }
      
      if ($poll['vote_count'] > 0) {
        if ($poll['title'] !== $title) {
          $this->error('You cannot change the title because the poll already has votes.');
        }
        
        if ($poll['description'] !== '' && $poll['description'] !== $description) {
          $this->error('You cannot change the description because the poll already has votes.');
        }
      }
      
      $sql =<<<SQL
UPDATE `$polls_tbl` SET
status = %d,
title = '%s',
description = '%s',
updated_on = %d,
expires_on = %d,
updated_by = '%s'
WHERE id = $id LIMIT 1
SQL;
      
      $res = mysql_global_call($sql,
        $active, $title, $description, $now, $expires_on, $username
      );
      
      if (!$res) {
        $this->error('Database error (1)');
      }
      
      foreach ($old_options as $oid => $value) {
        if ($value === '') {
          $query = "DELETE FROM $opts_tbl WHERE id = %d LIMIT 1";
          $res = mysql_global_call($query, $oid);
          if (!$res) {
            $this->error("Database error (opt:$oid)");
          }
        }
        else {
          $query = "UPDATE $opts_tbl SET caption = '%s' WHERE id = %d LIMIT 1";
          $res = mysql_global_call($query, $value, $oid);
          if (!$res) {
            $this->error("Database error (opt:$oid)");
          }
        }
      }
      
      foreach ($new_options as $value) {
        $query = "INSERT INTO $opts_tbl (`poll_id`, `caption`) VALUES (%d, '%s')";
        $res = mysql_global_call($query, $id, $value);
        if (!$res) {
          $this->error("Database error (opt:add)");
        }
      }
      
      $poll_id = $id;
    }
    else {
      $sql =<<<SQL
INSERT INTO `$polls_tbl`(status, title, description, created_on, updated_on,
expires_on, updated_by) VALUES (%d, '%s', '%s', %d, %d, %d, '%s')
SQL;
      
      $res = mysql_global_call($sql,
        $active, $title, $description, $now, 0, $expires_on, $username
      );
      
      if (!$res) {
        $this->error('Database error (1)');
      }
      
      $poll_id = mysql_global_insert_id();
      
      if (!$poll_id) {
        $this->error('Database error (id)');
      }
      
      foreach ($new_options as $value) {
        $query = "INSERT INTO $opts_tbl (`poll_id`, `caption`) VALUES (%d, '%s')";
        $res = mysql_global_call($query, $poll_id, $value);
        if (!$res) {
          $this->error("Database error (opt:add)");
        }
      }
    }
    
    $this->success(self::WEBROOT . '?action=view&amp;id=' . $poll_id);
  }
  
  public function delete() {
    if (!isset($_POST['id'])) {
      $this->errorJSON('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    // Poll
    $tbl = self::POLLS_TABLE;
    
    $query = "DELETE FROM $tbl WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (1)');
    }
    
    // Options
    $tbl = self::OPTIONS_TABLE;
    
    $query = "DELETE FROM $tbl WHERE poll_id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (2)');
    }
    
    // Votes
    $tbl = self::VOTES_TABLE;
    
    $query = "DELETE FROM $tbl WHERE poll_id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (3)');
    }
    
    $this->success(self::WEBROOT);
  }
  
  private function view() {
    if (!isset($_GET['id'])) {
      $this->error('Bad Request.');
    }
    
    $id = (int)$_GET['id'];
    
    // Poll
    $tbl = self::POLLS_TABLE;
    
    $query = "SELECT * FROM $tbl WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1)');
    }
    
    $poll = mysql_fetch_assoc($res);
    
    if (!$poll) {
      $this->error('Poll not found.');
    }
    
    // Options
    $tbl = self::OPTIONS_TABLE;
    
    $query = "SELECT * FROM $tbl WHERE poll_id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (2)');
    }
    
    $options = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $options[] = $row;
    }
    
    // Votes
    $tbl = self::VOTES_TABLE;
    
    $query = "SELECT option_id, COUNT(*) as cnt FROM $tbl WHERE poll_id = $id GROUP BY option_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (3)');
    }
    
    $scores = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $scores[$row['option_id']] = $row['cnt'];
    }
    
    $this->poll = $poll;
    $this->options = $options;
    $this->scores = $scores;
    
    $this->renderHTML('polls-update');
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
