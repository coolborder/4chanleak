<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

class APP {
  protected
    // Routes
    $actions = array(
      'index',
      'submit',
      'delete',
      'preview'
      //'truncate_and_import'
    );
  
  const TPL_ROOT = '../views/';
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  /**
   * Returns the data as json
   */
  final protected function success($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function error($message, $code = null, $data = null) {
    $payload = array('status' => 'error', 'message' => $message);
    
    if ($code) {
      $payload['code'] = $code;
    }
    
    if ($data) {
      $payload['data'] = $data;
    }
    
    $this->renderJSON($payload, 'error');
    
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
  
  /**
   * Removes line breaks, escapes html and parses markdown
   */
  private function formatMessage($value) {
    $value = preg_replace('/\r|\n/', '', $value);
    
    return $value;
  }
  
  /**
   * Get users by board
   */
  public function truncate_and_import() {
    mysql_global_call('TRUNCATE TABLE blotter_messages');
    
    $path = '/www/global/blotter.txt';
    
    $entries = array_reverse(file($path));
    
    foreach ($entries as $entry) {
      if (strpos($entry, '<font color="red">') === 0) {
        $is_red = true;
        $entry = str_replace('<font color="red">', '', $entry);
        $entry = str_replace('</font>', '', $entry);
      }
      else {
        $is_red = false;
      }
      
      list($date, $content) = explode(' - ', $entry, 2);
      
      $date = explode('/', $date);
      $date = mktime(0, 0, 0, (int)$date[0], (int)$date[1], (int)$date[2]);
      
      if ($is_red) {
        $content = '<span class="redtxt">' . $content . '</span>';
      }
      
      $content = mysql_real_escape_string($content);
      
      $query = <<<SQL
INSERT INTO blotter_messages(`date`, content, author)
VALUES($date, '$content', 'moot')
SQL;
      
      mysql_global_call($query);
    }
  }
  
  /**
   * Add message
   */
  public function submit() {
    if (!isset($_POST['content'])) {
      $this->error('Bad request');
    }
    
    $content = $this->formatMessage($_POST['content']);
    
    if ($content == '') {
      $this->error('Message cannot be empty');
    }
    
    $date = (int)$_SERVER['REQUEST_TIME'];
    $content = mysql_real_escape_string($content);
    $author = mysql_real_escape_string($_COOKIE['4chan_auser']);
    
    $query = <<<SQL
INSERT INTO blotter_messages(`date`, content, author)
VALUES($date, '$content', '$author')
SQL;
    
    mysql_global_call($query);
    
    if (mysql_affected_rows() < 1) {
      $this->error('Something went wrong');
    }
    
    $this->success();
  }
  
  /**
   * Preview message
   */
  public function preview() {
    if (!isset($_POST['content'])) {
      $this->error('Bad request');
    }
    
    $content = $this->formatMessage($_POST['content']);
    
    $this->success(array('message' => $content));
  }
  
  /**
   * Delete message
   */
  public function delete() {
    if (!isset($_POST['id'])) {
      $this->error('Bad request');
    }
    
    $id = (int)$_POST['id'];
    
    $query = "DELETE FROM blotter_messages WHERE id = $id";
    
    mysql_global_call($query);
    
    if (mysql_affected_rows() < 1) {
      $this->error('Something went wrong');
    }
    
    $this->success();
  }
  
  /**
   * Default page
   */
  public function index() {
    $query = "SELECT * FROM blotter_messages ORDER BY id DESC LIMIT 50";
    
    $result = mysql_global_call($query);
    
    $this->messages = array();
    
    while ($row = mysql_fetch_assoc($result)) {
      $this->messages[] = $row;
    }
    
    $this->renderHTML('blotter');
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
