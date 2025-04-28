<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}
/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
}
*/
require_once '../lib/csp.php';

require_once 'lib/ini.php';

load_ini("$configdir/cloudflare_config.ini");
finalize_constants();

define('CLOUDFLARE_EMAIL', 'cloudflare@4chan.org');
define('CLOUDFLARE_ZONE', '4chan.org');
define('CLOUDFLARE_ZONE_2', '4cdn.org');

// FIXME
function cloudflare_purge_url_channel($files) {
  // 4cdn = ca66ca34d08802412ae32ee20b7e98af (zone2)
  // 4chan = 363d1b9b6be563ffd5143c8cfcc29d52
  
  $url = 'https://api.cloudflare.com/client/v4/zones/199c270859e695ff96ae44705d3a5414/purge_cache';
  
  $opts = array(
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => array(
      'X-Auth-Email: ' . CLOUDFLARE_EMAIL,
      'X-Auth-Key: ' . CLOUDFLARE_API_TOKEN,
      'Content-Type: application/json'
    )
  );
  
  // Multiple files
  if (is_array($files)) {
    // Batching
    if (count($files) > 30) {
      $files = array_chunk($files, 30);
      
      foreach ($files as $batch) {
        $opts[CURLOPT_POSTFIELDS] = '{"files":' . json_encode($batch, JSON_UNESCAPED_SLASHES) . '}';
        //print_r($opts[CURLOPT_POSTFIELDS]);
        rpc_start_request_with_options($url, $opts);
      }
    }
    else {
      $opts[CURLOPT_POSTFIELDS] = '{"files":' . json_encode($files, JSON_UNESCAPED_SLASHES) . '}';
      //print_r($opts[CURLOPT_POSTFIELDS]);
      rpc_start_request_with_options($url, $opts);
    }
  }
  // Single file
  else {
    $opts[CURLOPT_POSTFIELDS] = '{"files":["' . $files . '"]}';
    //print_r($opts[CURLOPT_POSTFIELDS]);
    rpc_start_request_with_options($url, $opts);
  }
}

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'purge_cache',
      'rebuild_all',
      'restart_daemons',
      'get_ws_boards'
    ),
    
    $isAdmin = false,
    $isManager = false,
    $isDev = false
  ;
  
  const TPL_ROOT = '../views/';
  
  const JS_ROOT = '../js/';
  
  const
    REBUILD_ALL_CMD = 'nohup /www/global/bin/rebuildall >> /www/perhost/rebuildall.log 2>&1 &',
    RESTART_DAEMONS_CMD = 'nohup /usr/local/bin/suid_run_global bin/remote-restart-rebuildd >> /www/perhost/rebuildall.log  2>&1 &'
  ;
  
  public function __construct() {
    if (has_level('admin')) {
      $this->isAdmin = $this->isManager = true;
    }
    else if (has_level('manager')) {
      $this->isManager = true;
    }
    if (has_flag('developer')) {
      $this->isDev = true;
    }
  }
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  final protected function success($redirect = null, $no_exit = false) {
    $this->redirect = $redirect;
    $this->renderHTML('success');
    if (!$no_exit) {
      die();
    }
  }
  
  final protected function error($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Index
   */
  public function index() {
    $this->renderHTML('maintenance');
  }
  
  private function purge_cache_internal($board, $file, $no) {
    $url = "http://g0ch4.brazil.jp:24502";
    
    $post = array();
    $post['rmpath'] = "/$board/$file";
    $post['key'] = '6a310437e13935b64beefcf10da8dba3';
    $post = http_build_query($post);
    
    rpc_start_request($url, $post, null, false);
  }
  
  /**
   * Cache purge tool
   */
  public function purge_cache() {
    if (!isset($_POST['purge_list']) || $_POST['purge_list'] == '') {
      $this->error('The URL list cannot be empty.');
    }
    
    $urls = trim($_POST['purge_list']);
    
    $urls = preg_split('/\r\n|\n/', $urls);
    
    $status = array();
    
    $cdn_urls_batch = array();
    
    foreach ($urls as $url) {
      preg_match('/\/([a-z0-9]+)\/([0-9]+)[sm]?\.(....?)/', $url, $m);
      
      $board = $m[1]; $tim = $m[2]; $ext = $m[3];
      
      $basename = "${tim}.$ext";
      
      if ($tim) {
        if (preg_match('/^(https?:\/\/)?is[23]?\.(?:4chan|4channel)\.org\//', $url)) {
          $this->purge_cache_internal($board, $basename);
          $status[] = 'Purged ' . $basename . ' on /' . $board . '/ (Internal)';
        }
        else {
          $cdn_urls_batch[] = $url;
          //cloudflare_purge_by_basename($board, $basename);
          $status[] = 'Purged ' . $basename . ' on /' . $board . '/';
        }
      }
      else if (preg_match('/^(https?:\/\/)?[a-z0-9]+\.4chan\.org\//', $url)) {
        cloudflare_purge_url($url);
        $status[] = 'Purged ' . htmlspecialchars($url);
      }
      else if (preg_match('/^(https?:\/\/)?[a-z0-9]+\.4channel\.org\//', $url)) {
        cloudflare_purge_url_channel($url);
        $status[] = 'Purged ' . htmlspecialchars($url);
      }
      else if (preg_match('/^(https?:\/\/)?[a-z0-9]+\.4cdn\.org\//', $url)) {
        cloudflare_purge_url($url, true);
        $status[] = 'Purged ' . htmlspecialchars($url);
      }
      else {
        $status[] = 'Invalid URL: ' . htmlspecialchars($url);
      }
    }
    
    if (!empty($cdn_urls_batch)) {
      cloudflare_purge_url($cdn_urls_batch, true);
    }
    
    $this->success_msg = implode('<br>', $status);
    
    $this->renderHTML('success');
  }
  
  /**
   * Prints JSON arrays of safe for work and nsfw boards
   */
  public function get_ws_boards() {
    $json = file_get_contents('/www/4chan.org/web/boards/boards.json.gz');
    
    if (!$json) {
      $this->error("Couldn't open boards.json file (1)");
    }
    
    $json = gzdecode($json);
    
    if ($json === false) {
      $this->error("Couldn't open boards.json file (2)");
    }
    
    $boards = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->error('JSON decoding error.');
    }
    
    $sfw = array();
    $nsfw = array();
    
    $sfw_map = array();
    $nsfw_map = array();
    
    foreach ($boards['boards'] as $board) {
      if ($board['ws_board'] == 1) {
        $sfw[] = $board['board'];
        $sfw_map[$board['board']] = 1;
      }
      else {
        $nsfw[] = $board['board'];
        $nsfw_map[$board['board']] = 1;
      }
    }
    
    $this->sfw = json_encode($sfw);
    $this->sfw_map = json_encode($sfw_map);
    $this->sfw_regex = implode('|', $sfw);
    
    $this->sfw_php = array();
    foreach ($sfw as $board) { $this->sfw_php[] = "'$board'=>true"; }
    $this->sfw_php = "array(" . implode(',', $this->sfw_php) . ")";
    
    $this->nsfw = json_encode($nsfw);
    $this->nsfw_map = json_encode($nsfw_map);
    $this->nsfw_regex = implode('|', $nsfw);
    
    $this->nsfw_php = array();
    foreach ($nsfw as $board) { $this->nsfw_php[] = "'$board'=>true"; }
    $this->nsfw_php = "array(" . implode(',', $this->nsfw_php) . ")";
    
    // Get board JSON for 404 pages
    $res = mysql_global_call('SELECT dir as board, name as title FROM boardlist');
    
    $all_boards_json = [];
    
    if ($res) {
      while ($row = mysql_fetch_assoc($res)) {
        $row['title'] = htmlspecialchars(htmlspecialchars($row['title']));
        $all_boards_json[] = $row;
      }
    }
    
    $this->all_boards_json = json_encode($all_boards_json);
    
    $this->renderHTML('maintenance-board_linker');
  }
  
  /**
   * Rebuild all boards
   */
  public function rebuild_all() {
    if (!$this->isAdmin && !$this->isDev) {
      $this->error("Can't let you do that.");
    }
    exec(self::REBUILD_ALL_CMD);
    $this->success();
  }
  
  /**
   * Restart rebuildd daemons
   */
  public function restart_daemons() {
    exec(self::RESTART_DAEMONS_CMD);
    $this->success();
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
