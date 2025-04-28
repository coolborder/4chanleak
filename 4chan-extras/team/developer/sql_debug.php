<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/geoip2.php';

require_once 'lib/archives.php';

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}

set_time_limit(60);

class App {
  protected
    // Routes
    $actions = array(
      'index'
    )
  ;
  
  /**
   * Index
   */
  public function index() {
    global $con;
    header('Content-Type: text/plain');
    
    $sql = 'SELECT board, post_num FROM banned_users WHERE template_id IN (6, 226) ORDER BY no DESC LIMIT 1000';
    $res = mysql_global_call($sql);
    if (!$res) {
      die('Database Error');
    }
    
    $urls = [];
    
    while ($row = mysql_fetch_row($res)) {
      $url = return_archive_link($row[0], $row[1], false, true);
      if ($url) {
        $urls[] = $url;
      }
    }
    
    sort($urls);
    
    echo implode("\n", $urls);
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
