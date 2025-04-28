<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once '../lib/sec.php';
define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}

//require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'ping',
      'predict'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const
    WEBROOT = '/manager/tensorchan.php',
    MAX_ITEMS = 100,
    
    THRES_NSFW = 0.95,
    
    MODEL_INPUT_DIMS = 300
  ;
  
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
  
  private function format_labels($item) {
    $v = $item['nsfw'];
    
    if ($v >= self::THRES_NSFW) {
      $cls = ' class="l-r"';
    }
    else {
      $cls = '';
    }
    
    return "<li$cls>nsfw: $v</li>";
  }
  
  private function tensorchan_request($action, $data = null, &$took = null) {
    $curl = curl_init();
    
    $url = "http://danbo.int:8501/$action";
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_TIMEOUT, 4);
    
    if ($data) {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST , "POST");
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      
      $headers = array(
        'Content-Type: application/octet-stream'
      );
      
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    
    curl_setopt($curl, CURLOPT_USERAGENT, '4chan.org');
    
    if ($took !== null) {
      curl_setopt($curl, CURLOPT_HEADERFUNCTION,
        function($c, $header) use (&$took) {
          $len = strlen($header);
          
          $header = explode(':', $header, 2);
          
          if (count($header) < 2) {
            return $len;
          }
          
          if (trim($header[0]) == 'X-Took') {
            $took = (float)trim($header[1]);
          }
          
          return $len;
        }
      );
    }
    
    $resp = curl_exec($curl);
    
    if ($resp === false) {
      if ($errno = curl_errno($curl)) {
        $_err = 'Error (' . $errno . '): ' . curl_strerror($errno);
      }
      else {
        $_err = '';
      }
      
      return ["error" => $_err];
    }
    
    $resp_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if ($resp_status >= 300) {
      return ["error" => $resp];
    }
    
    if ($resp[0] == '{') {
      $resp = json_decode($resp, true);
    }
    
    curl_close($curl);
    
    return $resp;
  }
  
  /**
   * Default page
   */
  public function index() {
    $this->items = [];
    
    $clause = [];
    
    $this->html_args = [];
    
    $this->html_args['board'] = '';
    
    $board_clause = null;
    
    if (!isset($_GET['board'])) {
      $this->renderHTML('tensorchan');
      return;
    }
    
    if ($_GET['board']) {
      $board = trim($_GET['board']);
      if (preg_match('/^[0-9a-z]+$/', $board)) {
        $clause[] = "board = '$board'";
        $this->html_args['board'] = htmlspecialchars($board);
      }
    }
    
    if (isset($_GET['nsfw']) && $_GET['nsfw']) {
      $v = (float)$_GET['nsfw'];
      if (is_nan($v)) {
        $this->error('Invalid query');
      }
      $clause[] = "nsfw >= " . $v;
      $this->html_args['nsfw'] = $v;
    }
    
    if (isset($_GET['nsfw_less']) && $_GET['nsfw_less']) {
      $v = (float)$_GET['nsfw_less'];
      if (is_nan($v)) {
        $this->error('Invalid query');
      }
      $clause[] = "nsfw < " . $v;
      $this->html_args['nsfw_less'] = $v;
    }
    
    if (!empty($clause)) {
      $clause = "WHERE (" . implode(' AND ', $clause) . ")";
    }
    else {
      $clause = '';
    }
    
    $sql = "SELECT * FROM tensor_log $clause ORDER BY id DESC LIMIT " . self::MAX_ITEMS;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    $this->renderHTML('tensorchan');
  }
  
  public function ping() {
    $ping = $this->tensorchan_request('ping');
    
    if ($ping == 'pong') {
      $this->successJSON('pong');
    }
    else {
      $this->errorJSON($ping['error']);
    }
  }
  
  public function predict() {
    if (!isset($_FILES['img']['tmp_name'])) {
      $this->errorJSON('Missing image');
    }
    
    $img_data = file_get_contents($_FILES['img']['tmp_name']);
    
    $took = 0.0;
    
    $resp = $this->tensorchan_request('predict', $img_data, $took);
    
    $resp['_took'] = $took;
    
    $this->successJSON($resp);
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
