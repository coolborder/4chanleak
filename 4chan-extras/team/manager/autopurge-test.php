<?php
die();
require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

if (php_sapi_name() !== 'cli') {
  require_once '../lib/sec.php';
  
  auth_user();
  
  if (!has_level('manager') && !has_flag('developer')) {
    APP::denied();
  }
  
  require_once '../lib/csp.php';
  
  if (has_flag('developer')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE);
    $mysql_suppress_err = false;
  }
}
else {
  exit(0);
}

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'update',
      'delete',
      'exec',
      'preview'/*,
      'import'*/
    ),
    
    $default_reason = 'Spam.',
    $default_ban_days = 90,
    
    $field_map = array(
      'comment'   => array('label' => 'Comment', 'type' => 'text', 'col' => 'com'),
      'name'      => array('label' => 'Name', 'type' => 'text', 'col' => 'name'),
      'subject'   => array('label' => 'Subject', 'type' => 'text', 'col' => 'sub'),
      'thread_id' => array('label' => 'Thread ID', 'type' => 'int', 'col' => 'resto', 'desc' => 'OPs have an ID of 0'),
      'filename'  => array('label' => 'File Name', 'type' => 'text', 'col' => 'filename', 'desc' => 'No extension'),
      'ip_ranges' => array('label' => 'IP Ranges', 'type' => 'net', 'col' => 'host'),
      'filesize'  => array('label' => 'File Size', 'type' => 'int', 'col' => 'fsize', 'desc' => 'Bytes.'),
      'img_w'     => array('label' => 'Image Width', 'type' => 'int', 'col' => 'w'),
      'img_h'     => array('label' => 'Image Height', 'type' => 'int', 'col' => 'h'),
      'country'   => array('label' => 'Country', 'type' => 'string', 'col' => 'country'),
      'md5'       => array('label' => 'File MD5', 'type' => 'string', 'col' => 'md5'),
      'phash'     => array('label' => 'File similarity', 'type' => 'dhash', 'col' => 'tmd5'),
      'pwd'       => array('label' => 'Password', 'type' => 'string', 'col' => 'pwd'),
    ),
    
    $field_type_desc = array(
      'text' => 'Regex. Must both start and end with "/" or "@". Case-insensitive by default. Use the "b" flag for case-sensitive (binary) searches',
      'int' => 'Integer. Supports comparison operators > < >= <=.',
      'net' => 'Comma-separated list of CIDRs.',
      'string' => 'Comma-separated list of strings. Case-insensitive.',
      'dhash' => 'Perceptual hash of the thumbnail. Prefix the hash with up to 3 > or < signs for broader or stricter matches. If this rule is active, the entire ruleset will only match against new users.'
    ),
    
    $valid_ops = array('>' => true, '<' => true, '>=' => true, '<=' => true),
    
    $date_format = 'm/d/y H:i'
    ;
  
  const TABLE = 'autopurge';
  
  const TPL_ROOT = '../views/';
  
  const WEBROOT = '/manager/autopurge';
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  /**
   * Returns the data as json
   */
  final protected function successJSON($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function errorJSON($message, $code = null, $data = null) {
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
  
  final protected function print_err($msg) {
    fwrite(STDERR, $msg);
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
  
  private function validateBoards($ary) {
    $boards = $this->get_boards();
    
    foreach ($ary as $b) {
      if (!isset($boards[$b])) {
        $this->error('Invalid board.');
      }
    }
    
    return true;
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
    
    return $boards;
  }
  
  private function get_user_status_new_query() {
    $params = [];
    
    // Browser ID
    $params[] = null;
    
    // Req Sig
    $params[] = null;
    
    // Known status
    $params[] = '1';
    
    $lim = count($params) - 1;
    
    $data = [];
    
    $flag = false;
    
    for ($i = $lim; $i >= 0; $i--) {
      if ($params[$i] !== null) {
        $data[] = $params[$i];
        $flag = true;
      }
      else if ($flag) {
        $data[] = '[^:]*';
      }
    }
    
    if (empty($data)) {
      return false;
    }
    
    $data = array_reverse($data);
    
    return '^' . implode(':', $data) . '[^:]*';
  }
  
  private function ban_ip($ip, $board, $post, $rule) {
    $reverse = gethostbyaddr($ip);
    
    $ban_days = (int)$rule['ban_days'];
    
    if ($ban_days < 0) {
      $length = '0000-00-00 00:00:00';
    }
    else {
      $length = date('Y-m-d H:i:s', time() + ($ban_days * (24 * 60 * 60)));
    }
    
    $post_json = json_encode($post);
    
    $reason = $rule['public_reason'] . '<>autopurge rule #' . $rule['id'];
    
    $sql =<<<SQL
INSERT INTO `banned_users` (
board, global, zonly, name, host, reverse, xff, reason, length, admin,
md5, post_num, rule, post_time, template_id, post_json, admin_ip, password
) VALUES (
'%s',  %d,      0,     '%s', '%s', '%s',    '',  '%s',   '%s',   'autopurge',
'%s', %d,      '',   '%s',      0,           '%s',      '127.0.0.1', '%s'
)
SQL;
    
    $res = mysql_global_call($sql,
      $board, 1, $post['name'], $ip, $reverse, $reason, $length,
      $post['md5'], $post['no'], $post['time'], $post_json, $post['pwd']
    );
  }
  
  private function delete_posts($board, $post_id_map) {
    $username = 'autopurge';
    
    $url = "https://sys.int/$board/imgboard.php";
    
    $post = array();
    $post['mode'] = 'usrdel';
    $post['onlyimgdel'] = '';
    $post['tool'] = 'autopurge';
    
    foreach ($post_id_map as $no => $val) {
      $post[$no] = 'delete';
    }
    
    rpc_start_request($url, $post, array('4chan_auser' => $username), true);
    
    return true;
  }
  
  private function updateCommit() {
    $tbl = self::TABLE;
    
    // Patterns
    $patterns = array();
    
    $ops_regex = '/^(' . implode('|', array_keys($this->valid_ops)) . ')?[0-9]+$/';
    
    foreach ($this->field_map as $field => $meta) {
      if (!isset($_POST[$field]) || $_POST[$field] === '') {
        continue;
      }
      
      $value = $_POST[$field];
      
      if ($meta['type'] == 'int') {
        if (!preg_match($ops_regex, $value)) {
          $this->error('Invalid value for ' . $meta['label']);
        }
        
        $patterns[$field] = $value;
      }
      else if ($meta['type'] == 'text') {
        if (preg_match('/[\/@]b$/', $value)) {
          $test_value = preg_replace('/b$/', '', $value);
        }
        else {
          $test_value = $value;
        }
        
        if (!preg_match('/^[\/@].+[\/@]$/', $test_value)) {
          $this->error('Invalid value for ' . $meta['label']);
        }
        
        if (preg_match($test_value, '') === false) {
          $this->error('Invalid value for ' . $meta['label']);
        }
        
        $patterns[$field] = $value;
      }
      else if ($meta['type'] == 'string') {
        $patterns[$field] = explode(',', $value);
      }
      else if ($meta['type'] == 'net') {
        $ip_ranges = explode(',', $value);
        
        $ip_num_ranges = array();
        
        foreach ($ip_ranges as $cidr) {
          $ip_range = $this->range_from_cidr($cidr);
          
          if ($ip_range === false) {
            $this->error('Invalid CIDR.');
          }
          
          $ip_num_ranges[trim($cidr)] = $ip_range;
        }
        
        $patterns[$field] = $ip_num_ranges;
      }
      else if ($meta['type'] == 'dhash') {
        $_hash = str_replace(['<', '>'], '', $value);
        
        if (!preg_match('/^[0-9a-f]{16}$/', $_hash)) {
          $this->error('Invalid value for phash.');
        }
        
        $patterns[$field] = $value;
      }
      else {
        $this->error('Internal Server Error (uft1)');
      }
    }
    
    if (empty($patterns)) {
      $this->error('Nothing to match.');
    }
    
    $json_patterns = json_encode($patterns);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->error('Internal Server Error (jle1)');
    }
    
    if (strlen($json_patterns) > 65535) {
      $this->error('Internal Server Error (len1)');
    }
    
    // boards
    if (isset($_POST['boards'])) {
      if ($_POST['boards'] === '') {
        $boards = '';
      }
      else {
        $boards = preg_split('/[^a-z0-9]+/i', $_POST['boards']);
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
    
    // Public reason
    if (isset($_POST['public_reason'])) {
      $public_reason = htmlspecialchars($_POST['public_reason'], ENT_QUOTES);
    }
    else {
      $public_reason = $this->default_reason;
    }
    
    // Ban days
    if (isset($_POST['ban_days'])) {
      $ban_days = (int)$_POST['ban_days'];
    }
    else {
      $ban_days = $this->default_ban_days;
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    $username = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      $sql =<<<SQL
UPDATE `$tbl` SET
active = %d,
patterns = '%s',
boards = '%s',
public_reason = '%s',
ban_days = %d,
description = '%s',
updated_on = %d,
updated_by = '%s'
WHERE id = $id LIMIT 1
SQL;
    }
    else {
      
      $sql =<<<SQL
INSERT INTO `$tbl` (
active, patterns, boards, public_reason, ban_days,
description, updated_on, updated_by)
VALUES (%d, '%s', '%s', '%s', %d, '%s', %d, '%s')
SQL;
    }
    
    $res = mysql_global_call($sql,
      $active, $json_patterns, $boards,
      $public_reason, $ban_days, $description, $now, $username
    );
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Default page
   */
  public function index() {
    $tbl = self::TABLE;
    
    $sql = "SELECT * FROM `$tbl` ORDER BY id DESC";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    $this->rules = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      if (!$row['patterns']) {
        $row['patterns'] = array();
      }
      else {
        $patterns = json_decode($row['patterns'], true);
        
        foreach ($patterns as $field => &$value) {
          if ($this->field_map[$field]['type'] == 'net') {
            $value = implode(',', array_keys($value));
          }
          else if ($this->field_map[$field]['type'] == 'string') {
            $value = implode(',', $value);
          }
        }
        
        $row['patterns'] = $patterns;
      }
      
      $this->rules[] = $row;
    }
    
    $this->renderHTML('autopurge');
  }
  
  public function update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->updateCommit();
    }
    else if (isset($_GET['id'])) {
      $id = (int)$_GET['id'];
      $tbl = self::TABLE;
      $query = "SELECT * FROM `$tbl` WHERE id = $id LIMIT 1";
      $res = mysql_global_call($query);
      $this->rule = mysql_fetch_assoc($res);
      
      if (!$this->rule['patterns']) {
        $this->patterns = array();
      }
      else {
        $this->patterns = json_decode($this->rule['patterns'], true);
        
        foreach ($this->patterns as $field => &$value) {
          if ($this->field_map[$field]['type'] == 'net') {
            $value = implode(',', array_keys($value));
          }
          else if ($this->field_map[$field]['type'] == 'string') {
            $value = implode(',', $value);
          }
        }
      }
      
    }
    else {
      $this->rule = null;
    }
    
    $this->renderHTML('autopurge-update');
  }
  
  public function delete() {
    if (!isset($_POST['id'])) {
      $this->error('Bad request.');
    }
    
    $tbl = self::TABLE;
    
    $id = (int)$_POST['id'];
    
    $query = "DELETE FROM `$tbl` WHERE id = $id LIMIT 1";
    $res = mysql_global_call($query);
    
    if ($res === false) {
      $this->error('Database error.');
    }
    
    $this->success(self::WEBROOT);
  }
  
  public function preview() {
    if (!isset($_GET['id'])) {
      $this->error('Bad request.');
    }
    
    $tbl = self::TABLE;
    
    $id = (int)$_GET['id'];
    
    $query = "SELECT * FROM `$tbl` WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    $board_map = $this->get_boards();
    $all_boards = array_keys($board_map);
    
    $rule = mysql_fetch_assoc($res);
    
    if (!$rule) {
      $this->error('Rule not found.');
    }
    
    if ($rule['boards'] !== '') {
      $boards = explode(',', $rule['boards']);
    }
    else {
      $boards = $all_boards;
    }
    
    $ip_map = array();
    $post_id_map = array();
    
    foreach ($boards as $board) {
      if (!isset($board_map[$board])) {
        $this->error('Invalid board.');
      }
      
      $this->match_board_posts($board, $rule, $ip_map, $post_id_map);
    }
    
    $this->posts = $post_id_map;
    
    $this->renderHTML('autopurge-preview-test');
  }
  
  public function exec() {
    if (php_sapi_name() !== 'cli') {
      $this->error('Bad request');
    }
    
    sleep(rand(0, 180));
    
    $tbl = self::TABLE;
    
    $query = "SELECT * FROM `$tbl` WHERE active = 1";
    
    $res = mysql_global_call($query);
    
    if ($res === false) {
      $this->print_err('Error while querying rules: ' . mysql_error());
      exit(1);
    }
    
    $board_map = $this->get_boards();
    $all_boards = array_keys($board_map);
    
    $ip_map = array();
    $post_id_map = array();
    
    while ($rule = mysql_fetch_assoc($res)) {
      if ($rule === false) {
        $this->print_err('Error while fetching rule: ' . mysql_error());
        exit(1);
      }
      
      if ($rule['boards'] !== '') {
        $boards = explode(',', $rule['boards']);
      }
      else {
        $boards = $all_boards;
      }
      
      foreach ($boards as $board) {
        if (!isset($board_map[$board])) {
          $this->print_err("Skipping invalid board: $board");
          continue;
        }
        
        $this->match_board_posts($board, $rule, $ip_map, $post_id_map, true);
      }
    }
    
    foreach ($ip_map as $ip => $ban_info) {
      $this->ban_ip($ip, $ban_info['board'], $ban_info['post'], $ban_info['rule']);
    }
    
    foreach ($post_id_map as $board => $post_ids) {
      $this->delete_posts($board, $post_ids);
    }
    
    //header('Content-Type: text/plain');
    //print_r($ip_map);
    //print_r($post_id_map);
    
    exit(0);
  }
  
  private function match_board_posts($board, $rule, &$ip_map, &$post_id_map, $cli = false) {
    $sql_clause = array();
    
    $sql_clause[] = 'archived = 0';
    $sql_clause[] = 'sticky = 0';
    $sql_clause[] = "capcode = 'none'";
    
    $patterns = json_decode($rule['patterns'], true);
    
    foreach ($patterns as $field => $value) {
      if (!isset($this->field_map[$field])) {
        continue;
      }
      
      $meta = $this->field_map[$field];
      
      $type = $meta['type'];
      $col = $meta['col'];
      
      if ($type === 'net') {
        $or_clause = array();
        
        foreach ($value as $cidr => $ip_range) {
          $range_start = $ip_range[0];
          $range_end = $ip_range[1];
          
          if ($range_start < 1 || $range_end < 1) {
            continue;
          }
          
          $or_clause[] = "INET_ATON($col) >= {$range_start} "
            . "AND INET_ATON($col) <= {$range_end} AND $col != ''";
        }
        
        if (empty($or_clause)) {
          if ($cli) {
            $this->print_err('Net clause is empty for rule #' . $rule['id']);
            exit(1);
          }
          else {
            $this->error('Net clause is empty.');
          }
        }
        
        $sql_clause[] = '(' . implode(' OR ', $or_clause) . ')';
      }
      else if ($type === 'string') {
        if (empty($value)) {
          if ($cli) {
            $this->print_err('String clause is empty for rule #' . $rule['id']);
            exit(1);
          }
          else {
            $this->error('String clause is empty.');
          }
        }
        
        $or_clause = array();
        
        foreach ($value as $or_val) {
          $esc = mysql_real_escape_string(trim($or_val));
          
          if ($esc === false) {
            if ($cli) {
              $this->print_err('DB Error for rule #' . $rule['id']);
              exit(1);
            }
            else {
              $this->error('Database Error (esc).');
            }
          }
          
          $or_clause[] = $col . "='$esc'";
        }
        
        $sql_clause[] = '(' . implode(' OR ', $or_clause) . ')';
      }
      else if ($type === 'text') {
        $bin_flag = preg_match('/[\/@]b$/', $value) ? 'BINARY ' : '';
        
        $value = preg_replace('/^[\/@]|[\/@]b?$/', '', $value);
        
        $esc = mysql_real_escape_string($value);
        
        if ($esc === false) {
          if ($cli) {
            $this->print_err('DB Error for rule #' . $rule['id']);
            exit(1);
          }
          else {
            $this->error('Database Error (esc).');
          }
        }
        
        $sql_clause[] = $col . " REGEXP $bin_flag'$esc'";
      }
      else if ($type === 'int') {
        $operator = '=';
        
        if (preg_match('/([>=<]{1,2})\s*([0-9]+)$/', $value, $matches)) {
          if (isset($this->valid_ops[$matches[1]])) {
            $operator = $matches[1];
          }
          $value = $matches[2];
        }
        
        $value = (int)$value;
        
        $sql_clause[] = "$col $operator $value";
      }
      else if ($type === 'dhash') {
        $_hash = str_replace(['<', '>'], '', $value);
        
        if (!preg_match('/^[0-9a-f]{16}$/', $_hash)) {
          if ($cli) {
            $this->print_err('Invalid phash value for rule #' . $rule['id']);
            exit(1);
          }
          else {
            $this->error('Invalid phash value.');
          }
        }
        
        $_thres = 4;
        
        if ($value[0] === '>') {
          $_thres += substr_count($value, '>') * 2;
        }
        else if ($value[0] === '<') {
          $_thres -= substr_count($value, '<');
        }
        
        $user_info_sql = $this->get_user_status_new_query();
        
        if (!$user_info_sql) {
          if ($cli) {
            $this->print_err('Could not get user info query for rule #' . $rule['id']);
            exit(1);
          }
          else {
            $this->error('Could not get user info query.');
          }
        }
        
        // FIXME: remove the length check once the old md5s are purged out
        $sql_clause[] = "fsize > 0 AND email RLIKE '$user_info_sql' AND LENGTH(tmd5) = 16 AND BIT_COUNT(CAST(CONV('"
          . mysql_real_escape_string($_hash)
          . "', 16, 10) AS UNSIGNED) ^ CAST(CONV(tmd5, 16, 10) AS UNSIGNED)) <= $_thres";
      }
    }
    
    if (empty($sql_clause)) {
      if ($cli) {
        $this->print_err('Clause is empty for rule #' . $rule['id']);
        exit(1);
      }
      else {
        $this->error('Clause is empty.');
      }
    }
    
    $sql_clause = implode(' AND ', $sql_clause);
    
    $query = "SELECT * FROM `%s` WHERE $sql_clause";
    
    $res = mysql_board_call($query, $board);
    
    if (!$res) {
      if ($cli) {
        $this->print_err('Database error (sel)');
        exit(1);
      }
      else {
        $this->error('Database error (sel)');
      }
    }
    
    while ($post = mysql_fetch_assoc($res)) {
      if ($post['host'] && !isset($ip_map[$post['host']])) {
        $ip_map[$post['host']] = array(
          'board' => $board,
          'post' => $post,
          'rule' => $rule
        );
      }
      
      if (!isset($post_id_map[$board])) {
        $post_id_map[$board] = array();
      }
      
      $post_id_map[$board][$post['no']] = $post;
    }
    
    mysql_free_result($res);
  }
  
  private function range_from_cidr($cidr) {
    $cidr = trim($cidr);
    
    $parts = explode('/', $cidr);
    
    $str_start = $parts[0];
    $num_start = ip2long($str_start);
    
    $mask = (int)$parts[1];
    
    if (!$num_start) {
      return false;
    }
    
    if ($mask < 1 || $mask > 32) {
      return false;
    }
    
    $ip_count = 1 << (32 - $mask);
    
    $bitmask = ~((1 << (32 - $mask)) - 1);
    
    $num_start = $num_start & $bitmask;
    $str_start = long2ip($num_start);
    
    $num_end = $num_start + $ip_count;
    $str_end = long2ip($num_end);
    
    return array($num_start, $num_end);
  }
  
  /**
   * Main
   */
  public function run() {
    $method = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (php_sapi_name() === 'cli') {
      $action = 'exec';
    }
    else if (isset($method['action'])) {
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
