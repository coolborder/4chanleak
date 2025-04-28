<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once '../lib/sec.php';
define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
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
      'reset',
      'create'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const
    STATUS_INACTIVE = 0,
    STATUS_ACTIVE = 1
  ;
  
  const
    TABLE_NAME = 'vip_capcodes',
    WEBROOT = '/manager/capcodes.php',
    DATE_FORMAT ='m/d/y H:i',
    PAGE_SIZE = 50,
    KEY_BYTE_LEN = 16,
    ID_BYTE_LEN = 6
  ;
  
  public function debug() {
    //header('Content-Type: text/plain');
    //echo '<pre>';
    //print_r(scandir(self::IMG_ROOT));
    //echo '</pre>';
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
  
  private function generate_user_key() {
    $user_key = openssl_random_pseudo_bytes(self::KEY_BYTE_LEN);
    
    $user_key = rtrim(strtr(base64_encode($user_key), '+/', '-_'), '=');
    
    $hashed_user_key = password_hash($user_key, PASSWORD_DEFAULT);
    
    if (!$user_key || !$hashed_user_key) {
      $this->error('Internal Server Error (guk)');
    }
    
    return array($user_key, $hashed_user_key);
  }
  
  private function generate_user_id() {
    $user_id = openssl_random_pseudo_bytes(self::ID_BYTE_LEN);
    
    $user_id = rtrim(strtr(base64_encode($user_id), '+/', '-_'), '=');
    
    if (!$user_id) {
      $this->error('Internal Server Error (gui)');
    }
    
    return $user_id;
  }
  
  private function updateCommit() {
    // Updating existing entry
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
    }
    else {
      $id = null;
    }
    
    $tbl = self::TABLE_NAME;
    
    // Active
    if (isset($_POST['active'])) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    if ($id) {
      $query = "SELECT id FROM $tbl WHERE id = $id LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database error (0).');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Entry not found.');
      }
    }
    else {
      // Generate key for new entries
      list($user_key, $hashed_user_key) = $this->generate_user_key();
      $user_id = $this->generate_user_id();
    }
    
    // Name
    if (!isset($_POST['name']) || $_POST['name'] === '') {
      $this->error('The name cannot be empty.');
    }
    
    $name = htmlspecialchars($_POST['name'], ENT_QUOTES);
    
    // E-Mail
    if (!isset($_POST['email']) || $_POST['email'] === '') {
      $this->error('The E-Mail cannot be empty.');
    }
    
    $email = htmlspecialchars($_POST['email'], ENT_QUOTES);
    
    // Description
    if (isset($_POST['description']) && $_POST['description'] !== '') {
      $description = htmlspecialchars($_POST['description'], ENT_QUOTES);
    }
    else {
      $description = '';
    }
    
    // ---
    $now = time();
    
    $username = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    // -----
    // Updating
    // -----
    if ($id) {
      $query =<<<SQL
UPDATE `$tbl` SET
active = $active,
name = '%s',
email = '%s',
description = '%s',
updated_on = $now,
updated_by = '%s'
WHERE id = $id LIMIT 1
SQL;
      
      $res = mysql_global_call($query, $name, $email, $description, $username);
      
      if (!$res) {
        $this->error('Database error (1).');
      }
      
      $this->success(self::WEBROOT);
    }
    // -----
    // Creating a new entry
    // -----
    else {
      $query =<<<SQL
INSERT INTO `$tbl`
(active, name, email, user_id, user_key, description, created_on, created_by)
VALUES ($active, '%s', '%s', '%s', '%s', '%s', $now, '%s')
SQL;
      $res = mysql_global_call($query, $name, $email, $user_id, $hashed_user_key,
        $description, $username
      );
      
      if (!$res) {
        $this->error('Database error (2).');
      }
      
      $this->item = null;
      
      $this->plain_user_key = $user_key;
      $this->user_id = $user_id;
      
      $this->renderHTML('capcodes-update');
    }
  }
  
  /**
   * Delete entries
   */
  public function delete() {
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
      
      $count = 1;
      
      $clause = '= ' . $id;
    }
    else {
      $this->error('Bad request.');
    }
    
    $tbl = self::TABLE_NAME;
    
    $query = "DELETE FROM `$tbl` WHERE id $clause LIMIT $count";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Reset capcode
   */
  public function reset() {
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
      
      $clause = '= ' . $id;
    }
    else {
      $this->error('Bad request.');
    }
    
    $tbl = self::TABLE_NAME;
    
    $query = "SELECT id FROM `$tbl` WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (0)');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Entry not found.');
    }
    
    list($user_key, $hashed_user_key) = $this->generate_user_key();
    $user_id = $this->generate_user_id();
    
    $query = "UPDATE `$tbl` SET user_key = '%s', user_id = '%s' WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query, $hashed_user_key, $user_id);
    
    if (!$res) {
      $this->error('Database error (1)');
    }
    
    $this->plain_user_key = $user_key;
    $this->user_id = $user_id;
    
    $this->renderHTML('capcodes-update');
  }
  
  /**
   * Update entry
   */
  public function update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->updateCommit();
      return;
    }
    else if (isset($_GET['id'])) {
      $tbl = self::TABLE_NAME;
      
      $id = (int)$_GET['id'];
      
      $query = "SELECT * FROM $tbl WHERE id = $id LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error.');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Entry not found.');
      }
      
      $this->item = mysql_fetch_assoc($res);
    }
    else {
      $this->item = null;
    }
    
    $this->renderHTML('capcodes-update');
  }
  
  /**
   * Default page
   */
  public function index() {
    $tbl = self::TABLE_NAME;
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $query = "SELECT * FROM $tbl ORDER BY id DESC LIMIT $offset,$lim";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error');
    }
    
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
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    if ($this->next_offset) {
      array_pop($this->items);
    }
    
    $this->renderHTML('capcodes');
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
