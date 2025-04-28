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
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL & ~E_NOTICE);
}
*/
require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'update',
      'delete',
      'toggle_active',
      'tools',
      'test_ips',
      'search',
      'auto'
      /*, 'import'
      , 'create_table'*/
    )
    ;
  
  const TPL_ROOT = '../views/';
  
  const
    WEBROOT = '/manager/iprangebans',
    DATE_FORMAT ='m/d/y H:i',
    PAGE_SIZE_AUTO = 50,
    PAGE_SIZE = 250,
    SEARCH_PAGE_SIZE = 500,
    
    AUTO_TTL_SEC = 5400 // 90 minutes
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
  
  public function create_table() {
    //$sql = 'DROP TABLE `iprangebans`';
    //mysql_global_call($sql);
    /*
    $sql =<<<SQL
CREATE TABLE IF NOT EXISTS `iprangebans` (
  `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `active` tinyint(1) NOT NULL,
  `ops_only` tinyint(1) NOT NULL,
  `img_only` tinyint(1) NOT NULL,
  `lenient` tinyint(1) NOT NULL,
  `boards` varchar(255) NOT NULL,
  `range_start` int(10) unsigned NOT NULL,
  `range_end` int(10) unsigned NOT NULL,
  `str_start` varchar(255) NOT NULL,
  `str_end` varchar(255) NOT NULL,
  `cidr` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `action` tinyint(1) unsigned NOT NULL,
  `created_on` int(10) unsigned NOT NULL,
  `updated_on` int(10) unsigned NOT NULL,
  `expires_on` int(10) unsigned NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `updated_by` varchar(255) NOT NULL,
  KEY `full_idx` (`range_start`, `range_end`, `active`, `expires_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
SQL;
    
    mysql_global_call($sql);
    */
  }
  
  private function ip_to_range16($ip) {
    $bits = explode('.', $ip);
    return "{$bits[0]}.{$bits[1]}.0.0/16";
  }
  
  private function get_global_where() {
    return "active = 1 AND boards = '' AND ops_only = 0 AND img_only = 0 AND lenient = 0 AND report_only = 0 AND expires_on = 0";
  }
  
  private function validateBoards($ary) {
    $boards = $this->get_boards();
    
    foreach ($ary as $b) {
      if (!isset($boards[$b])) {
        $this->error('Invalid board.');
      }
    }
    
    return true;
  }
  
  private function getDuration($delta) {
    if ($delta < 86400) {
      $count = ceil($delta / 3600);
      
      if ($count > 1) {
        $head = $count . ' hours';
      }
      else {
        $head = 'one hour';
      }
    }
    
    $count = ceil($delta / 86400);
    
    if ($count > 1) {
      $head = $count . ' days';
    }
    else {
      $head = 'one day';
    }
    
    return $head;
  }
  
  private function get_optimal_range($start, $end) {
    $range = [0, 0, 0];
    
    for ($mask = 32; $mask >= 0; $mask--) {
      $ip_count = (1 << (32 - $mask)) - 1;
      
      $bitmask = ~$ip_count;
      
      $ip_start = $start & $bitmask;
      $ip_end = $ip_start + $ip_count;
      
      if ($ip_start === $start && $ip_end <= $end) {
        $range[0] = $ip_start;
        $range[1] = $ip_end;
        $range[2] = $mask;
      }
      else {
        break;
      }
    }
    
    return $range;
  }
  
  private function get_biggest_range($start, $end) {
    $range = [0, 0, 0];
    
    for ($mask = 0; $mask <= 32; $mask++) {
      $ip_count = (1 << (32 - $mask)) - 1;
      
      $bitmask = ~$ip_count;
      
      $ip_start = $start & $bitmask;
      $ip_end = $ip_start + $ip_count;
      
      if ($ip_start <= $start && $ip_end >= $end) {
        $range[0] = $ip_start;
        $range[1] = $ip_end;
        $range[2] = $mask;
      }
      else {
        break;
      }
    }
    
    return $range;
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
    
    $boards['_report_'] = true;
    
    $boards['_ws_'] = true;
    
    return $boards;
  }
  
  private function validateRanges($rangelist) {
    $rangelist = trim($rangelist);
    
    $ranges = explode("\n", $rangelist);
    
    $valid = array();
    $invalid = array();
    
    foreach ($ranges as $cidr) {
      $cidr = trim($cidr);
      
      if (!$cidr) {
        continue;
      }
      
      $parts = explode('/', $cidr);
      
      $str_start = $parts[0];
      $num_start = ip2long($str_start);
      
      $mask = (int)$parts[1];
      
      if ($num_start === false || !$parts[1]) {
        $invalid[] = $cidr;
        continue;
      }
      
      if ($mask < 0 || $mask > 32) {
        $invalid[] = $cidr;
        continue;
      }
      
      $ip_count = 1 << (32 - $mask);
      
      if ($ip_count < 1) {
        $invalid[] = $cidr;
        continue;
      }
      
      $bitmask = ~($ip_count - 1);
      
      $num_start = $num_start & $bitmask;
      $str_start = long2ip($num_start);
      
      $num_end = $num_start + $ip_count - 1;
      $str_end = long2ip($num_end);
      
      $valid[] = array(
        'range_start'   => $num_start,
        'range_end'     => $num_end,
        'str_start'     => $str_start,
        'str_end'       => $str_end,
        'cidr'          => $cidr
      );
    }
    
    if (!empty($invalid)) {
      $this->invalid = $invalid;
      $this->renderHTML('iprangebans-error');
      die();
    }
    
    if (empty($valid)) {
      return null;
    }
    
    return $valid;
  }
  
  private function disable_expired() {
    $now = (int)$_SERVER['REQUEST_TIME'];
    
    $query =<<<SQL
UPDATE `iprangebans` SET active = 0
WHERE active = 1 AND expires_on > 0 AND expires_on <= $now
SQL;
    
    return !!mysql_global_call($query);
  }
  
  private function hours_to_epoch($now, $hours) {
    if ($hours <= 0) {
      return 0;
    }
    
    return $now + $hours * 3600;
  }
  
  private function str_to_epoch($expires) {
    if (!$expires) {
      return 0;
    }
    
    if (!preg_match('/(\d\d)\/(\d\d)\/(\d\d) (\d\d):(\d\d)/', $expires, $m)) {
      return 0;
    }
    
    return (int)mktime($m[4], $m[5], 0, $m[1], $m[2], '20' . $m[3]);
  }
  
  private function updateCommit() {
    // boards
    if (isset($_POST['boards'])) {
      if ($_POST['boards'] === '') {
        $boards = '';
      }
      else {
        $boards = preg_split('/[^_a-z0-9]+/i', $_POST['boards']);
        $this->validateBoards($boards);
        $boards = implode(',', $boards);
      }
    }
    else {
      $boards = '';
    }
    
    // Description
    if (isset($_POST['description']) && $_POST['description'] !== '') {
      $description = htmlspecialchars($_POST['description'], ENT_QUOTES);
    }
    else {
      $description = '';
    }
    
    // Active
    if (isset($_POST['active'])) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    // OPs only
    if (isset($_POST['ops_only'])) {
      $ops_only = 1;
    }
    else {
      $ops_only = 0;
    }
    
    // Image only
    if (isset($_POST['img_only'])) {
      $img_only = 1;
    }
    else {
      $img_only = 0;
    }
    
    // Lenient
    if (isset($_POST['lenient'])) {
      $lenient = 1;
    }
    else {
      $lenient = 0;
    }
    
    // Identified only
    if (isset($_POST['ua_ids']) && $_POST['ua_ids']) {
      if (!preg_match('/^[,a-z0-9\s]+$/', $_POST['ua_ids'])) {
        $this->error('Invalid browser IDs.');
      }
      $ua_ids = preg_split('/[^_a-z0-9]+/i', trim($_POST['ua_ids']));
      $ua_ids = implode(',', $ua_ids);
    }
    else {
      $ua_ids = '';
    }
    
    // Report only
    if (isset($_POST['report_only'])) {
      $report_only = 1;
    }
    else {
      $report_only = 0;
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    $username = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    // Ranges
    if (!isset($_POST['ranges']) || $_POST['ranges'] == '') {
      $ranges = null;
    }
    else {
      $ranges = $this->validateRanges($_POST['ranges']);
    }
    
    // -----
    // Updating a single entry. Range modification possible.
    // -----
    if (isset($_POST['id'])) {
      if (!$ranges || count($ranges) > 1) {
        $this->error('Invalid IP range.');
      }
      
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
      
      // Expiration
      if (isset($_POST['expires'])) {
        $expires_on = $this->str_to_epoch($_POST['expires']);
      }
      else {
        $expires_on = 0;
      }
      
      $range = $ranges[0];
      
      $sql =<<<SQL
UPDATE `iprangebans` SET
active = %d,
ops_only = %d,
img_only = %d,
lenient = %d,
ua_ids = '%s',
report_only = %d,
boards = '%s',
range_start = %d,
range_end = %d,
str_start = '%s',
str_end = '%s',
cidr = '%s',
description = '%s',
expires_on = %d,
updated_on = %d,
updated_by = '%s'
WHERE id = $id LIMIT 1
SQL;
      
      $res = mysql_global_call($sql,
        $active, $ops_only, $img_only, $lenient, $ua_ids, $report_only,
        $boards,
        $range['range_start'], $range['range_end'],
        $range['str_start'], $range['str_end'], $range['cidr'], $description,
        $expires_on, $now, $username
      );
      
      if (!$res) {
        $this->error('Database error.');
      }
    }
    // -----
    // Updating multiple entries. Range modification not possible.
    // -----
    else if (isset($_POST['ids'])) {
      // Expiration
      if (isset($_POST['expires'])) {
        $expires_on = $this->str_to_epoch($_POST['expires']);
      }
      else {
        $expires_on = 0;
      }
      
      $ids = explode(',', $_POST['ids']);
      
      $in_clause = array();
      
      foreach ($ids as $id) {
        $id = (int)$id;
        
        if (!$id) {
          $this->error('Invalid ID.');
        }
        
        $in_clause[] = $id;
      }
      
      if (empty($in_clause)) {
        $this->error('Missing ID list.');
      }
      
      if (isset($_POST['update_desc']) && $_POST['update_desc'] == '1') {
        $description_col = "description = '"
          . mysql_real_escape_string($description)
          . "',";
      }
      else {
        $description_col = ''; 
      }
      
      $in_clause = implode(',', $in_clause);
      
      $sql =<<<SQL
UPDATE `iprangebans` SET
active = %d,
ops_only = %d,
img_only = %d,
lenient = %d,
ua_ids = '%s',
report_only = %d,
boards = '%s',
$description_col
expires_on = %d,
updated_on = %d,
updated_by = '%s'
WHERE id IN ($in_clause)
SQL;
      
      $res = mysql_global_call($sql,
        $active, $ops_only, $img_only, $lenient, $ua_ids, $report_only,
        $boards, $expires_on, $now, $username
      );
      
      if (!$res) {
        $this->error('Database error.');
      }
    }
    // -----
    // Creating a new entry with one or more ranges.
    // -----
    else {
      if (!$ranges) {
        $this->error('Invalid IP range.');
      }
      
      // Expiration
      if (isset($_POST['expires'])) {
        $expires_on = $this->hours_to_epoch($now, (int)$_POST['expires']);
      }
      else {
        $expires_on = 0;
      }
      
      foreach ($ranges as $range) {
        $sql =<<<SQL
INSERT INTO `iprangebans` (active,
ops_only, img_only, lenient, ua_ids, report_only,
boards, range_start, range_end,
str_start, str_end, cidr, description, action, created_on, updated_on,
expires_on, created_by, updated_by)
VALUES (%d,
%d, %d, %d, '%s', %d,
'%s', %d, %d,
'%s', '%s', '%s', '%s', 2, %d, 0,
%d, '%s', '')
SQL;
        $res = mysql_global_call($sql,
          $active,
          $ops_only, $img_only, $lenient, $ua_ids, $report_only,
          $boards, $range['range_start'], $range['range_end'],
          $range['str_start'], $range['str_end'], $range['cidr'], $description,
          $now, $expires_on, $username
        );
        
        if (!$res) {
          $this->error('Database error.');
        }
      }
    }
    
    $this->success(self::WEBROOT);
  }
  
  private function dedup($ips_txt) {
    $ips = explode("\n", trim($ips_txt));
    
    $this->results = array();
    
    $clause = $this->get_global_where();
    
    foreach ($ips as $val) {
      $val = trim($val);
      
      if (!preg_match('/^[\.0-9]+\/[0-9]+$/', $val)) {
        continue;
      }
      
      $query = "SELECT id FROM `iprangebans` WHERE cidr = '$val' AND $clause LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database error.');
      }
      
      if (mysql_num_rows($res) === 0) {
        $this->results[] = $val;
      }
    }
    
    $this->mode = 'dedup';
    
    $this->renderHTML('iprangebans-dedup');
  }
  
  private  function calculate($ips_txt) {
    $lines = explode("\n", trim($ips_txt));
    
    $ips = [];
    
    foreach ($lines as $line) {
      $line = trim($line);
      
      if (!preg_match('/^[\.0-9]+$/', $line)) {
        continue;
      }
      
      $ip_num = ip2long($line);
      
      if ($ip_num) {
        $ips[] = $ip_num;
      }
    }
    
    sort($ips);
    
    if (count($ips) < 2) {
      $this->error('You need to prove more than 1 valid IP');
    }
    
    $ip_min = $ips[0];
    $ip_max = $ips[count($ips) - 1];
    
    if ($ip_min < 1) {
      $this->error('Invalid data supplied');
    }
    
    $ranges = [];
    
    $current_ip = $ip_min;
    
    while ($current_ip <= $ip_max) {
      $r = $this->get_optimal_range($current_ip, $ip_max);
      
      if (!$r[0]) {
        $this->error("Invalid range: $current_ip, " . long2ip($current_ip));
      }
      
      $ranges[] = long2ip($r[0]) . "/{$r[2]}";
      
      $current_ip = $r[1] + 1;
    }
    
    $this->results = $ranges;
    
    $broad_r = $this->get_biggest_range($ip_min, $ip_max);
    
    $this->result_broad = long2ip($broad_r[0]) . "/{$broad_r[2]}";
    
    $this->ip_min_broad = long2ip($broad_r[0]);
    $this->ip_max_broad = long2ip($broad_r[1]);
    
    $this->ip_min = long2ip($ip_min);
    $this->ip_max = long2ip($ip_max);
    
    $this->mode = 'calculate';
    
    $this->renderHTML('iprangebans-dedup');
  }
  
  private function aggregate($ips_txt) {
    $lines = explode("\n", trim($ips_txt));
    
    $ips = [];
    
    foreach ($lines as $line) {
      $line = trim($line);
      
      if (!preg_match('/^[\.0-9]+\/[0-9]+$/', $line)) {
        continue;
      }
      
      $ips[] = $line;
    }
    
    $ips = implode("\n", $ips);
    
    require_once '../lib/Aggregator.php';
    
    $aggr = new CIDRAM\Aggregator\Aggregator();
    
    $this->results = explode("\n", $aggr->aggregate($ips));
    
    $this->mode = 'aggregate';
    
    $this->renderHTML('iprangebans-dedup');
  }
  
  public function tools() {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
      $this->renderHTML('iprangebans-tools');
    }
    else if (isset($_POST['mode'])) {
      if ($_POST['mode'] == 'dedup') {
        $this->dedup($_POST['data']);
      }
      else if ($_POST['mode'] == 'aggregate') {
        $this->aggregate($_POST['data']);
      }
      else if ($_POST['mode'] == 'calculate') {
        $this->calculate($_POST['data']);
      }
      else {
        $this->error('Bad request.');
      }
    }
    else {
      $this->error('Bad request.');
    }
  }
  
  public function test_ips() {
    if (isset($_POST['ips'])) {
      $ips = explode("\n", trim($_POST['ips']));
      
      $this->trim_result = array();
      
      foreach ($ips as $val) {
        $val = trim($val);
        
        if (!preg_match('/^[.0-9\/]+$/', $val)) {
          continue;
        }
        
        $long_ip = ip2long($val);
        
        if (!$long_ip) {
          $this->error('Invalid IP.');
        }
      
        $query =<<<SQL
SELECT id FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip AND active = 1
LIMIT 1
SQL;
        
        $res = mysql_global_call($query);
        
        if (!$res) {
          $this->error('Database error.');
        }
        
        if (mysql_num_rows($res) === 0) {
          $this->trim_result[] = $val;
        }
      }
    }
    
    $this->renderHTML('iprangebans-test_ips');
  }
  
  /**
   * Auto-rangebans
   */
  public function auto() {
    require_once 'lib/archives.php';
    
    $this->now = $_SERVER['REQUEST_TIME'];
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $lim = self::PAGE_SIZE_AUTO + 1;
    
    $query =<<<SQL
SELECT ip, board, thread_id, post_id, arg_num, arg_str, ua_sig, name as tpl_name,
UNIX_TIMESTAMP(created_on) as created_on
FROM event_log
LEFT JOIN ban_templates ON no = arg_num
WHERE type = 'rangeban'
ORDER BY id DESC LIMIT $offset, $lim
SQL;
    
    $res = mysql_global_call($query);
    
    $this->offset = $offset; 
    
    $this->previous_offset = $offset - self::PAGE_SIZE_AUTO;
    
    if ($this->previous_offset < 0) {
      $this->previous_offset = 0;
    }
    
    if (mysql_num_rows($res) === $lim) {
      $this->next_offset = $offset + self::PAGE_SIZE_AUTO;
    }
    else {
      $this->next_offset = 0;
    }
    
    $this->ranges = array();
    
    while($row = mysql_fetch_assoc($res)) {
      $this->ranges[] = $row;
    }
    
    if ($this->next_offset) {
      array_pop($this->ranges);
    }
    
    $this->renderHTML('iprangebans-auto');
  }
  
  /**
   * Default page
   */
  public function index() {
    $this->disable_expired();
    
    $this->search_mode = false;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $lim = self::PAGE_SIZE + 1;
    
    $query = 'SELECT * FROM iprangebans ORDER BY id DESC LIMIT '
      . $offset . ', ' . $lim;
    
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
    
    $this->ranges = array();
    
    while($row = mysql_fetch_assoc($res)) {
      $this->ranges[] = $row;
    }
    
    if ($this->next_offset) {
      array_pop($this->ranges);
    }
    
    $this->search_qs = '';
    
    $this->renderHTML('iprangebans');
  }
  
  public function search() {
    if (!isset($_GET['mode'])) {
      $this->error('Bad request.');
    }
    
    $this->disable_expired();
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $lim = self::SEARCH_PAGE_SIZE + 1;
    
    $mode = $_GET['mode'];
    $q = $_GET['q'];
    
    $url_params = array();
    
    $url_params['mode'] = $mode;
    $url_params['q'] = $q;
    
    $this->search_mode = htmlspecialchars($mode);
    $this->search_query = htmlspecialchars($q, ENT_QUOTES);
    
    if ($mode == 'ip') {
      $q = trim($q);
      
      if ($q === '') {
        $this->error('Empty query.');
      }
      
      $long_ip = ip2long($q);
      
      if (!$long_ip) {
        $this->error('Invalid IP.');
      }
      
      $query =<<<SQL
SELECT * FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip
ORDER BY id DESC
LIMIT $offset, $lim
SQL;
    }
    else if ($mode == 'desc') {
      $q = htmlspecialchars(mysql_real_escape_string($q), ENT_QUOTES);
      
      $order_col = 'id';
      
      $clauses = array();
      
      if ($q !== '') {
        $clauses[] = "description LIKE '%$q%'";
      }
      
      if (isset($_GET['board']) && $_GET['board'] !== '') {
        if (!preg_match('/^[_a-z0-9]+$/', $_GET['board'])) {
          $this->error('Invalid board.');
        }
        
        $this->board_query = htmlspecialchars(mysql_real_escape_string($_GET['board']), ENT_QUOTES);
        $url_params['board'] = $_GET['board'];
        $clauses[] = "boards REGEXP '(^|,)" . $this->board_query . "(,|$)'";
      }
      
      if (isset($_GET['active_only'])) {
        $url_params['active_only'] = '1';
        $clauses[] = 'active = 1';
        $this->search_active = true;
      }
      
      if (isset($_GET['ops_only'])) {
        $url_params['ops_only'] = '1';
        $clauses[] = 'ops_only = 1';
        $this->search_ops = true;
      }
      
      if (isset($_GET['img_only'])) {
        $url_params['img_only'] = '1';
        $clauses[] = 'img_only = 1';
        $this->search_img = true;
      }
      
      if (isset($_GET['lenient'])) {
        $url_params['lenient'] = '1';
        $clauses[] = 'lenient = 1';
        $this->search_lenient = true;
      }
      
      if (isset($_GET['report_only'])) {
        $url_params['report_only'] = '1';
        $clauses[] = 'report_only = 1';
        $this->search_report = true;
      }
      
      if (isset($_GET['temp'])) {
        $url_params['temp'] = '1';
        $clauses[] = 'expires_on > 0';
        $this->search_temp = true;
      }
      
      if (isset($_GET['recent'])) {
        $url_params['recent'] = '1';
        $this->search_recent = true;
        $order_col = 'updated_on';
      }
      
      if (empty($clauses)) {
        if (!isset($this->search_recent)) {
          $this->error('Empty query.');
        }
        else {
          $clauses = '';
        }
      }
      else {
        $clauses = 'WHERE ' . implode(' AND ', $clauses);
      }
      
      $query =<<<SQL
SELECT * FROM iprangebans
$clauses
ORDER BY $order_col DESC
LIMIT $offset, $lim
SQL;
    }
    else {
      $this->error('Bad request.');
    }
    
    $res = mysql_global_call($query);
    
    $this->offset = $offset; 
    
    $this->previous_offset = $offset - self::SEARCH_PAGE_SIZE;
    
    if ($this->previous_offset < 0) {
      $this->previous_offset = 0;
    }
    
    if (mysql_num_rows($res) === $lim) {
      $this->next_offset = $offset + self::SEARCH_PAGE_SIZE;
    }
    else {
      $this->next_offset = 0;
    }
    
    $this->ranges = array();
    
    while($row = mysql_fetch_assoc($res)) {
      $this->ranges[] = $row;
    }
    
    if ($this->next_offset) {
      array_pop($this->ranges);
    }
    
    $tmp_params = array();
    
    foreach ($url_params as $param => $value) {
      $tmp_params[] = $param . '=' . urlencode($value);
    }
    
    $this->search_qs = 'action=search&amp;' . htmlentities(implode('&', $tmp_params)) . '&amp;';
    
    $this->renderHTML('iprangebans');
  }
  
  public function update() {
    $this->ids = null;
    $this->range = null;
    $this->from_ranges = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (!isset($_POST['from_ranges'])) {
        $this->updateCommit();
      }
      else {
        $this->from_ranges = htmlspecialchars($_POST['from_ranges'], ENT_QUOTES);
      }
    }
    else if (isset($_GET['ids'])) {
      $this->disable_expired();
      
      $ids = explode(',', $_GET['ids']);
      
      $in_clause = array();
      
      foreach ($ids as $id) {
        $id = (int)$id;
        
        if (!$id) {
          $this->error('Invalid ID.');
        }
        
        $in_clause[] = $id;
      }
      
      $in_clause = implode(',', $in_clause);
      
      $id = $in_clause[0];
      $query = "SELECT * FROM iprangebans WHERE id IN($in_clause)";
      $res = mysql_global_call($query);
      
      $this->ranges = array();
      $this->ids = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->ranges[] = $row;
        $this->ids[] = $row['id'];
      }
      
      if (empty($this->ranges)) {
        $this->error('Nothing found.');
      }
      
      $this->range = $this->ranges[0];
      $this->ids = implode(',', $this->ids);
    }
    else if (isset($_GET['id'])) {
      $this->ids = null;
      $id = (int)$_GET['id'];
      $query = "SELECT * FROM iprangebans WHERE id = $id LIMIT 1";
      $res = mysql_global_call($query);
      $this->range = mysql_fetch_assoc($res);
    }
    
    $this->renderHTML('iprangebans-update');
  }
  
  public function toggle_active() {
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
      
      $count = 1;
      
      $clause = '= ' . $id;
    }
    else if (isset($_POST['ids'])) {
      $ids = explode(',', $_POST['ids']);
      
      $clause = array();
      
      foreach ($ids as $id) {
        $id = (int)$id;
        
        if (!$id) {
          if (isset($_POST['xhr'])) {
            $this->errorJSON('Invalid ID.');
          }
          else {
            $this->error('Invalid ID.');
          }
        }
        
        $clause[] = $id;
      }
      
      $count = count($clause);
      
      $clause = 'IN(' . implode(',', $clause) . ')';
    }
    else {
      $this->errorJSON('Bad request.');
    }
    
    if (!isset($_POST['active'])) {
      $this->errorJSON('Bad request.');
    }
    
    $active = (int)$_POST['active'];
    
    if ($active) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    $username = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    $now = $_SERVER['REQUEST_TIME'];
    
    $query = <<<SQL
UPDATE iprangebans SET active = $active, updated_by = '%s', updated_on = %d
WHERE id $clause LIMIT $count
SQL;
  
    $res = mysql_global_call($query, $username, $now);
    
    if (!$res) {
      $this->errorJSON('Database error.');
    }
    
    $this->successJSON();
  }
  
  public function delete() {
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
      
      $count = 1;
      
      $clause = '= ' . $id;
    }
    else if (isset($_POST['ids'])) {
      $ids = explode(',', $_POST['ids']);
      
      $clause = array();
      
      foreach ($ids as $id) {
        $id = (int)$id;
        
        if (!$id) {
          if (isset($_POST['xhr'])) {
            $this->errorJSON('Invalid ID.');
          }
          else {
            $this->error('Invalid ID.');
          }
        }
        
        $clause[] = $id;
      }
      
      $count = count($clause);
      
      $clause = 'IN(' . implode(',', $clause) . ')';
    }
    else {
      if (isset($_POST['xhr'])) {
        $this->errorJSON('Bad request.');
      }
      else {
        $this->error('Bad request.');
      }
    }
    
    $query = "DELETE FROM iprangebans WHERE id $clause LIMIT $count";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      if (isset($_POST['xhr'])) {
        $this->errorJSON('Database error.');
      }
      else {
        $this->error('Database error.');
      }
    }
    
    if (isset($_POST['xhr'])) {
      $this->successJSON();
    }
    else {
      $this->success(self::WEBROOT);
    }
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
