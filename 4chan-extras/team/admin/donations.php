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
      'search'
    ),
    
    $_cf_ready = false
  ;
  
  const TPL_ROOT = '../views/';
  
  const TABLE = 'donations';
  
  const
    WEBROOT = '/admin/donations.php',
    DATE_FORMAT ='m/d/y H:i:s',
    PAGE_SIZE = 100
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
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
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
    
    $tbl = self::TABLE;
    
    $query =<<<SQL
SELECT name, message, amount_cents, created_on FROM $tbl
ORDER BY id DESC
LIMIT $offset,$lim
SQL;
    
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
    
    $this->renderHTML('donations');
  }
  
  /**
   * Search
   */
  public function search() {
    if (!isset($_GET['q'])) {
      $this->error('Bad request');
    }
    
    $q = mysql_real_escape_string($_GET['q']);
    
    if (!$q) {
      $this->error('Query cannot be empty');
    }
    
    $like_email = str_replace(array('%', '_'), array("\%", "\_"), $q);
    
    $tbl = self::TABLE;
    
    $query = <<<SQL
SELECT *
FROM `$tbl`
WHERE (ref_id = '$q'
OR transaction_id = '$q'
OR customer_id = '$q'
OR email LIKE '%$like_email%'
OR ip = '$q')
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    if (mysql_num_rows($res) == 0) {
      $this->error('Nothing found.');
    }
    
    $this->donations = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->donations[] = $row;
    }
    
    $this->renderHTML('donations');
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
