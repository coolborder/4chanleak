<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once '../lib/sec.php';
define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

/*
$mysql_suppress_err = false;
ini_set('display_errors', 1);
error_reporting(E_ALL);
*/

require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'enable',
      'disable',
      'events',
      'update_event',
      'delete_event'/*,
      'debug',
      'set_live',
      'unset_live'*/
    ),
    
    $_cf_ready = false,
    
    $_event_types = array(
      1 => 'Submit',
      2 => 'Vote'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const
    STATUS_PENDING = 0,
    STATUS_ACTIVE = 1,
    STATUS_DISABLED = 2,
    STATUS_LOST = 3, // Special status for vtuber images which didn't go to round 2
    STATUS_LIVE = 9 // dummy
  ;
  
  const
    //BANNERS_TABLE = 'contest_banners',
    //EVENTS_TABLE = 'contest_banner_events',
    BANNERS_TABLE = 'contest_imgs',
    EVENTS_TABLE = 'contest_img_events',
    NAME_KEY = '846e2fd927ee70a5',
    IMG_ROOT = '/www/global/static/image/contest_banners'
  ;
  
  const ALL_BOARDS_TAG = 'all';
  
  const
    WEBROOT = '/manager/contest_banners.php',
    DATE_FORMAT ='m/d/y H:i',
    DATE_FORMAT_SHORT ='m/d/y',
    PAGE_SIZE = 50
  ;
  
  const CMD_RESYNC = 'nohup /usr/local/bin/suid_run_global resync_global';
  
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
  
  public function get_image_url($banner, $filter_pending = false, $thumbnail = false) {
    $root = 'https://s.4cdn.org/image/contest_banners/';
    
    if ($filter_pending) {
      $root .= self::NAME_KEY . '_';
    }
    
    if ($thumbnail) {
      $img_url = $root . $banner['file_id'] . '_th.jpg';
    }
    else {
      $img_url = $root . $banner['file_id'] . '.' . $banner['file_ext'];
    }
    
    return $img_url;
  }
  
  private function get_boards() {
    $query = 'SELECT dir FROM boardlist';
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[$board['dir']] = true;
    }
    
    $boards['test'] = true;
    $boards['_'] = true;
    
    return $boards;
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
    
    if (preg_match('/(\d{4})\-(\d\d)\-(\d\d)/', $str, $m)) {
      return (int)mktime(0, 0, 0, $m[2], $m[3], $m[1]);
    }
    else if (preg_match('/(\d\d)\/(\d\d)\/(\d\d)/', $str, $m)) {
      return (int)mktime(0, 0, 0, $m[1], $m[2], '20' . $m[3]);
    }
    
    return 0;
  }
  
  private function set_active($id, $active) {
    $id = (int)$id;
    
    if (!$id) {
      $this->errorJSON('Invalid ID.');
    }
    
    $tbl = self::BANNERS_TABLE;
    
    if ($active) {
      $status = self::STATUS_ACTIVE;
    }
    else {
      $status = self::STATUS_DISABLED;
    }
    
    $query = "SELECT * FROM $tbl WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (sa1)');
    }
    
    $banner = mysql_fetch_assoc($res);
    
    if (!$banner) {
      $this->errorJSON('Banner not found');
    }
    
    if ($banner['status'] == $status) {
      $this->errorJSON('Nothing to do');
    }
    else if ($active && $banner['status'] != self::STATUS_PENDING) {
      $this->errorJSON('Only pending entries can be set to active');
    }
    
    // Deleting file
    $file_id = $banner['file_id'];
    $file_ext = $banner['file_ext'];
    
    if (!preg_match('/^[a-f0-9]+$/', $file_id) || !preg_match('/^[a-z]+$/', $file_ext)) {
      $this->errorJSON('Internal Server Error (sa1)');
    }
    
    $file_name = $file_id . '.' . $file_ext;
    $path_pending = self::IMG_ROOT . '/' . self::NAME_KEY . '_' . $file_name;
    $path_active = self::IMG_ROOT . '/' . $file_name;
    
    if ($banner['th_width'] > 0) {
      $th_file_name = $file_id . '_th.jpg';
      $th_path_pending = self::IMG_ROOT . '/' . self::NAME_KEY . '_' . $th_file_name;
      $th_path_active = self::IMG_ROOT . '/' . $th_file_name;
    }
    
    if ($active) {
      if (file_exists($path_pending) && !rename($path_pending, $path_active)) {
        $this->errorJSON('Internal Server Error (sa21)');
      }
      
      if ($banner['th_width'] > 0) {
        if (file_exists($th_path_pending) && !rename($th_path_pending, $th_path_active)) {
          $this->errorJSON('Internal Server Error (sa21t)');
        }
      }
    }
    else {
      if ($banner['status'] == self::STATUS_PENDING) {
        $file_name = self::NAME_KEY . '_' . $file_name;
        $path = $path_pending;
      }
      else {
        $path = $path_active;
      }
      
      if (file_exists($path) && !unlink($path)) {
        $this->errorJSON('Internal Server Error (sd22)');
      }
      
      // Purging cache
      $this->init_cloudflare();
      
      $url = 'https://s.4cdn.org/image/contest_banners/' . $file_name;
      
      //exec(self::CMD_RESYNC);
      
      cloudflare_purge_url($url, true);

      if ($banner['th_width'] > 0) {
        if ($banner['status'] == self::STATUS_PENDING) {
          $th_file_name = self::NAME_KEY . '_' . $file_name;
          $th_path = $th_path_pending;
        }
        else {
          $th_path = $th_path_active;
        }
        
        if (file_exists($th_path) && !unlink($th_path)) {
          $this->errorJSON('Internal Server Error (sd22t)');
        }
        
        $url = 'https://s.4cdn.org/image/contest_banners/' . $th_file_name;
        cloudflare_purge_url($url, true);
      }
    }
    
    // Deleting DB entry
    $query = "UPDATE $tbl SET status = $status, is_live = 0 WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (sa2)');
    }
    
    return true;
  }
  
  private function get_banner_counts() {
    $counts = array();
    
    $tbl = self::BANNERS_TABLE;
    
    $query = "SELECT status, COUNT(*) as cnt FROM $tbl GROUP BY status";
    
    $res = mysql_global_call($query);
    
    while ($row = mysql_fetch_assoc($res)) {
      $counts[$row['status']] = $row['cnt'];
    }
    
    $query = "SELECT COUNT(*) as cnt FROM $tbl WHERE is_live = 1";
    
    $res = mysql_global_call($query);
    
    $counts[self::STATUS_LIVE] = mysql_fetch_assoc($res)['cnt'];
    
    return $counts;
  }
  
  /**
   * Default page
   */
  public function index() {
    $qs = array();
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $lim = self::PAGE_SIZE + 1;
    
    $tbl = self::BANNERS_TABLE;
    $status = 'status = ' . self::STATUS_PENDING;
    $this->filter = 'pending';
    
    if (isset($_GET['filter'])) {
      if ($_GET['filter'] == 'active') {
        //$status = 'status = ' . self::STATUS_ACTIVE;
        $status = 'status IN(' . self::STATUS_ACTIVE . ',' . self::STATUS_LOST . ')'; // FIXME
        $this->filter = 'active';
        $qs[] = 'filter=active';
      }
      elseif ($_GET['filter'] == 'disabled') {
        $status = 'status = ' . self::STATUS_DISABLED;
        $this->filter = 'disabled';
        $qs[] = 'filter=disabled';
      }
      elseif ($_GET['filter'] == 'live') {
        $status = 'is_live = 1';
        $this->filter = 'live';
        $qs[] = 'filter=live';
      }
    }
    
    $this->boards = $this->get_boards();
    
    if (isset($_GET['board'])) {
      if (!isset($this->boards[$_GET['board']])) {
        $this->error('Invalid board.');
      }
      
      $this->filter_board = $_GET['board'];
      $qs[] = 'board=' . $_GET['board'];
      
      $board_clause = " AND board = '" . mysql_real_escape_string($_GET['board']) . "'";
      $order = 'score DESC';
    }
    else {
      $this->filter_board = null;
      $board_clause = '';
      
      if ($this->filter === 'live') {
        $order = 'board ASC';
      }
      else if ($this->filter === 'active') {
        $order = 'score2 DESC, score DESC'; // FIXME
      }
      else {
        $order = 'id DESC';
      }
    }
    
    // Counts
    $this->counts = $this->get_banner_counts();
    
    // Banners
    $query = "SELECT * FROM $tbl WHERE $status$board_clause ORDER BY $order LIMIT $offset,$lim";
    
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
    
    if ($qs) {
      $this->search_qs = implode('&amp;', $qs);
    }
    else {
      $this->search_qs = '';
    }
    
    $this->renderHTML('contest-banners');
  }
  
  public function disable() {
    if (!isset($_POST['id'])) {
      $this->error('Invalid ID.');
    }
    
    $this->set_active($_POST['id'], false);
    
    $this->successJSON();
  }
  
  public function enable() {
    if (!isset($_POST['id'])) {
      $this->error('Invalid ID.');
    }
    
    $this->set_active($_POST['id'], true);
    
    $this->successJSON();
  }
  
  private function toggle_live($id, $flag) {
    $tbl = self::BANNERS_TABLE;
    $active_status = self::STATUS_ACTIVE;
    
    $id = (int)$id;
    $flag = (int)$flag;
    
    $query = "UPDATE $tbl SET is_live = $flag WHERE id = $id AND status = $active_status LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error (tl)');
    }
  }
  
  public function set_live() {
    if (!isset($_POST['id'])) {
      $this->error('Invalid ID.');
    }
    
    $this->toggle_live($_POST['id'], 1);
    
    $this->successJSON();
  }
  
  public function unset_live() {
    if (!isset($_POST['id'])) {
      $this->error('Invalid ID.');
    }
    
    $this->toggle_live($_POST['id'], 0);
    
    $this->successJSON();
  }
  
  public function events() {
    $tbl = self::EVENTS_TABLE;
    
    $query = "SELECT * FROM `$tbl` ORDER BY starts_on ASC";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1)');
    }
    
    $this->items = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    $this->counts = $this->get_banner_counts();
    
    $this->renderHTML('contest-banners-events');
  }
  
  public function update_event() {
    $tbl = self::EVENTS_TABLE;
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      if (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        if (!$id) {
          $this->errorJSON('Invalid ID.');
        }
      }
      else {
        $id = null;
      }
      
      // boards
      if (isset($_POST['boards'])) {
        if ($_POST['boards'] === '') {
          $boards = self::ALL_BOARDS_TAG;
        }
        else {
          $boards = preg_split('/[^a-z0-9]+/i', $_POST['boards']);
          
          if (!$this->validate_boards($boards)) {
            $this->errorJSON('Invalid board list.');
          }
          
          $boards = implode(',', $boards);
        }
      }
      else {
        $boards = self::ALL_BOARDS_TAG;
      }
      
      // event type
      if (!isset($_POST['event_type'])) {
        $this->errorJSON('Invalid event type.');
      }
      
      $event_type = (int)$_POST['event_type'];
      
      if (!isset($this->_event_types[$event_type])) {
        $this->errorJSON('Invalid event type.');
      }
      
      // starts on
      if (isset($_POST['starts_on'])) {
        $starts_on = $this->str_to_epoch($_POST['starts_on']);
      }
      else {
        $starts_on = 0;
      }
      
      // ends on
      if (isset($_POST['ends_on'])) {
        $ends_on = $this->str_to_epoch($_POST['ends_on']);
      }
      else {
        $ends_on = 0;
      }
      
      if ($id) {
        $query = <<<SQL
UPDATE `$tbl` SET event_type = %d, boards = '%s', starts_on = %d, ends_on = %d
WHERE id = %d
SQL;
        $res = mysql_global_call($query, $event_type, $boards, $starts_on, $ends_on, $id);
      }
      else {
        $query = <<<SQL
INSERT INTO `$tbl` (event_type, boards, starts_on, ends_on)
VALUES(%d, '%s', %d, %d)
SQL;
        $res = mysql_global_call($query, $event_type, $boards, $starts_on, $ends_on);
      }
      
      if (!$res) {
        $this->errorJSON('Database Error (1)');
      }
      
      $this->successJSON();
    }
    else if (isset($_GET['id'])) {
      // id
      $id = (int)$_GET['id'];
      
      $query = "SELECT id, event_type, boards, starts_on, ends_on FROM `$tbl` WHERE id = $id";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->errorJSON('Database Error (2)');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->errorJSON('Event not found.');
      }
      
      $event = mysql_fetch_assoc($res);
      
      if ($event['starts_on']) {
        $event['starts_on'] = date(self::DATE_FORMAT_SHORT, $event['starts_on']);
      }
      else {
        $event['starts_on'] = '';
      }
      
      if ($event['ends_on']) {
        $event['ends_on'] = date(self::DATE_FORMAT_SHORT, $event['ends_on']);
      }
      else {
        $event['ends_on'] = '';
      }
      
      if ($event['boards'] === self::ALL_BOARDS_TAG) {
        $event['boards'] = '';
      }
      
      $this->successJSON($event);
    }
    else {
      $this->errorJSON('Bad Request.');
    }
  }
  
  public function delete_event() {
    if (!isset($_POST['id'])) {
      $this->errorJSON('Bad Request.');
    }
    
    $id = (int)$_POST['id'];
    
    $tbl = self::EVENTS_TABLE;
    
    $query = "DELETE FROM $tbl WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database Error.');
    }
    
    $this->successJSON();
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
