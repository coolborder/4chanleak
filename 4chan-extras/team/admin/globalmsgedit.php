<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}

//require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'update',
    );
  
  const TPL_ROOT = '../views/';
  
  const GLOBAL_MSG_FILE = '/www/global/yotsuba/globalmsg.txt';
  const FRONT_MSG_FILE = '/usr/www/4chan.org/web/www/data/announce.txt';
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  /**
   * Success
   */
  final protected function success($redirect = null, $no_exit = false) {
    $this->redirect = $redirect;
    $this->renderHTML('success');
    if (!$no_exit) {
      die();
    }
  }
  
  /**
   * Error
   */
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
   * Update message
   */
  public function update() {
    if (!isset($_POST['message'])) {
      $this->error('Bad request');
    }
    
    if (!isset($_POST['type'])) {
      $this->error('Bad request');
    }
    
    if ($_POST['type'] == 'global') {
      $msg_file = self::GLOBAL_MSG_FILE;
    }
    else if ($_POST['type'] == 'front') {
      $msg_file = self::FRONT_MSG_FILE;
    }
    else {
      $this->error('Bad request');
    }
    
    if (file_put_contents($msg_file, $_POST['message']) !== false) {
      $this->success();
    }
    else {
      $this->error("Couldn't write to file");
    }
    
    $this->success();
  }
  
  /**
   * Default page
   */
  public function index() {
    if (isset($_GET['type'])) {
      $this->type = $_GET['type'];
      
      if ($this->type == 'global') {
        $msg_file = self::GLOBAL_MSG_FILE;
        $this->title = 'Global Message';
      }
      else if ($this->type == 'front') {
        $msg_file = self::FRONT_MSG_FILE;
        $this->title = 'Front Page Message';
      }
      
      if (file_exists($msg_file)) {
        $this->message = file_get_contents($msg_file);
        
        if ($this->message === false) {
          $this->error("Couldn't read file");
        }
      }
      else {
        $this->message = '';
      }
    }
    else {
      $this->title = 'Site Messages';
    }
    
    $this->renderHTML('globalmsgedit');
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
