<?php

require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('mod')) {
  APP::denied();
}
/*
if (has_flag('developer')) {
  //$mysql_suppress_err = false;
  //ini_set('display_errors', 1);
  //ini_set('display_startup_errors', 1);
  //error_reporting(E_ALL);
}
else {
  die('403');
}
*/
require_once 'lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'ban',
      'search',
      'from_pid',
      'get_templates'
    ),
    
    $is_manager = false,
    
    $safe_countries = 'US,GB,CA,DE,AU,FR,NL,FI,PL,SE,ES,IT,NZ,NO,DK,IE,AT,PT,BE,CZ,BG,HR'
  ;
  
  const TPL_ROOT = 'views/';
  
  const
    REV_TIME_LIMIT = 60, // at least 60 seconds to get hostnames
    INS_TIME_LIMIT = 10, // at least 10 seconds to insert bans into the db
    MAX_BOARD_RESULTS = 750, // per board max results
    MAX_RESULTS = 1500, // total max results
    MAX_IP_BANS = 100,
    MAX_TIDS_BY_SUB = 10, // max number of thread ids when searching tids by sub
    DATE_FORMAT = 'm/d/Y H:i:s',
    WEBROOT = '/search',
    BAN_TABLE = 'banned_users'
  ;
  
  const PWD_REGEX = '/^[a-f0-9]{32}$/'; // regex to validate password for multi bans
  
  public function __construct() {
    $this->is_manager = has_level('manager') || has_flag('developer'); // fixme
  }
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  final protected function errorHTML($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  /**
   * Returns the data as json
   */
  final protected function success($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
    die();
  }
  
  final protected function success_empty($board) {
    $data = ['board' => $board, 'posts' => []];
    $this->success($data);
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function error($message = null, $fatal = false) {
    $payload = array('status' => 'error', 'message' => $message);
    
    if ($fatal === true) {
      $payload['fatal'] = true;
    }
    
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
   * Returns a hashmap of valid boards
   */
  private function get_boards() {
    $query = 'SELECT dir FROM boardlist ORDER BY dir ASC';
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[$board['dir']] = true;
    }
    
    if (has_flag('developer')) {
      $boards['test'] = true;
    }
    
    return $boards;
  }
  
  /**
   * Checks if the argument is a valid board
   */
  private function is_board_valid($board) {
    if ($board === 'test' && has_flag('developer')) {
      return true;
    }
    
    $query = "SELECT dir FROM boardlist WHERE dir = '%s'";
    
    $res = mysql_global_call($query, $board);
    
    return $res && mysql_num_rows($res) > 0;
  }
  
  /**
   * Returns a json-encoded array of valid boards
   */
  private function get_boards_json() {
    return json_encode(array_map('strval', array_keys($this->get_boards())), JSON_HEX_TAG);
  }
  
  /**
   * Formats the email field for querying from GET params
   */
  private function get_user_info_query() {
    $params = [];
    
    // Browser ID
    if (isset($_GET['browser_id']) && $_GET['browser_id']) {
      $params[] = mysql_real_escape_string($_GET['browser_id']);
    }
    else {
      $params[] = null;
    }
    
    // Req Sig
    if (isset($_GET['req_sig']) && $_GET['req_sig']) {
      $params[] = mysql_real_escape_string($_GET['req_sig']);
    }
    else {
      $params[] = null;
    }
    
    // Known status
    $known_status = null;
    
    if (isset($_GET['usrs']) && $_GET['usrs']) {
      if ($_GET['usrs'] === 'n') {
        $known_status = '1';
      }
      else if ($_GET['usrs'] === 'u') {
        $known_status = '[12]';
      }
    }
    
    $params[] = $known_status;
    
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
  
  /**
   * Splits the name and returns array($name, $tripcode or null)
   */
  private function format_name($name) {
    $name = str_replace('&#039;', "'", $name);
    
    if (strpos($name, '#')) {
      return explode('#', $name);
    }
    
    if (strpos($name, '<span ')) {
      $parts = explode('</span> <span class="postertrip">', $name);
      
      return $parts;
    }
    
    return array($name, null);
  }
  
  /**
   * Formats the subject field (spoiler)
   * returns array($clean_subject, (bool)$is_spoiler);
   */
  private function format_subject($sub, $board = null) {
    if (strpos($sub, 'SPOILER<>') === 0) {
      $sub = substr($sub, 9);
      if ($sub === false) {
        $sub = '';
      }
      $spoiler = true;
    }
    else {
      $spoiler = false;
    }
    
    // TODO: nasty
    if ($board === 'f') {
      $sub = preg_replace('/^[0-9]+\|/', '', $sub);
    }
    
    return array($sub, $spoiler);
  }
  
  /**
   * Formats nametrip for bans
   */
  private function format_ban_name($name) {
    $tripcode = '';
    
    if (strpos($name, '</span> <span class="postertrip">!') !== false) {
      $name_bits = explode('</span> <span class="postertrip">!', $name);
      if ($name_bits[1]) {
        $tripcode = $name_bits[1];
      }
      $name = str_replace( '</span> <span class="postertrip">!', ' #', $name );
    }
    
    return array($name, $tripcode);
  }
  
  /**
   * Formats a where clause for "net" fields.
   */
  private function build_net_clause($col, $value) {
    if (strpos($value, '/') !== false) {
      $ip_range = $this->range_from_cidr($value);
      
      if ($ip_range === false) {
        $this->error('Invalid CIDR.');
      }
      
      return "INET_ATON($col) >= {$ip_range[0]} "
       . "AND INET_ATON($col) <= {$ip_range[1]} AND $col != ''";
    }
    else if (preg_match('/\*$/', $value)) {
      $value = str_replace(array('%', '_'), array("\%", "\_"), $value);
      
      $value = preg_replace('/\*+$/', '%', $value);
      
      if (strlen($value) < 2) {
        $this->error('Invalid IP.');
      }
      
      return $col . " LIKE '" . mysql_real_escape_string($value) . "'";
    }
    else {
      return $col . "='" . mysql_real_escape_string($value) . "'";
    }
  }
  
  /**
   * Returns the numeric IP range of a CIDR as array(start, end)
   */
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
   * Get the Pass ID from a ban id or post id or ban request id
   */
  private function get_pass_id_from_ref($ref, $valid_boards) {
    $ref = trim($ref);
    
    // /board/post_id
    if (preg_match('/^\/([a-z0-9]+)\/([0-9]+)$/', $ref, $m)) {
      $board = $m[1];
      $val = (int)$m[2];
      
      if (!isset($valid_boards[$board])) {
        $this->error('Invalid board for Pass Ref.', true);
      }
      
      $sql = "SELECT 4pass_id FROM `%s` WHERE no = $val LIMIT 1";
      $res = mysql_board_call($sql, $board);
    }
    // ban_id
    else if (preg_match('/^[0-9]+$/', $ref)) {
      $val = (int)$ref;
      $sql = "SELECT 4pass_id FROM `banned_users` WHERE no = $val LIMIT 1";
      $res = mysql_global_call($sql);
    }
    // !ban_request_id
    else if (preg_match('/^![0-9]+$/', $ref)) {
      $val = (int)ltrim($ref, '!');
      
      return $this->get_pass_id_from_ban_request($val);
    }
    else {
      $this->error('Invalid Pass Reference.', true);
    }
    
    if (!$res) {
      $this->error('Database Error (gpifr)', true);
    }
    
    $pass_id = mysql_fetch_row($res)[0];
    
    if (!$pass_id) {
      $this->error("The referenced post or ban doesn't have a Pass associated with it.", true);
    }
    
    return $pass_id;
  }
  
  private function get_pass_id_from_ban_request($id) {
    $sql = "SELECT spost FROM `ban_requests` WHERE id = $id LIMIT 1";
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database Error (gpifbr)', true);
    }
    
    $post = mysql_fetch_row($res)[0];
    
    if (!$post) {
      $this->error('Invalid Pass Reference.', true);
    }
    
    $post = unserialize($post);
    
    $pass_id = $post['4pass_id'];
    
    if (!$pass_id) {
      $this->error("The referenced post or ban doesn't have a Pass associated with it.", true);
    }
    
    return $pass_id;
  }
  
  /**
   * Fetch all ban templates
   */
  public function get_templates() {
    $query = 'SELECT rule, name, bantype, publicreason as reason, days, level FROM ban_templates ORDER BY length(rule), rule ASC';
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error("Couldn't fetch templates");
    }
    
    $levels = [
      'janitor' => 1,
      'mod' => 2,
      'manager' => 3,
      'admin' => 4,
    ];
    
    if (has_level('mod')) {
      $this_role = 'mod';
    }
    else if (has_level('manager')) {
      $this_role = 'manager';
    }
    else if (has_level('admin')) {
      $this_role = 'admin';
    }
    else {
      $this_role = 'janitor';
    }
    
    $this_level = $levels[$this_role];
    
    $templates = [];
    
    while ($tpl = mysql_fetch_assoc($res)) {
      if (!isset($levels[$tpl['level']]) || $this_level < $levels[$tpl['level']]) {
        continue;
      }
      
      if (strpos($tpl['rule'], 'global') === 0) {
        $board = 'global';
      }
      else {
        $board = preg_replace('/^([a-z3]+)[0-9]+$/', '$1', $tpl['rule']);
      }
      
      if ($tpl['bantype'] === 'global') {
        $tpl['global'] = 1;
      }
      
      unset($tpl['level']);
      unset($tpl['rule']);
      unset($tpl['bantype']);
      
      if (!isset($templates[$board])) {
        $templates[$board] = [];
      }
      
      $templates[$board][] = $tpl;
    }
    
    $this->success($templates);
  }
  
  /**
   * Find IP by post number and redirect to search()
   */
  public function from_pid() {
    if (!isset($_GET['board']) || !isset($_GET['pid'])) {
      $this->error('Bad Request.');
    }
    
    $pid = (int)$_GET['pid'];
    
    if ($pid < 1) {
      $this->error('Invalid post ID.');
    }
    
    $valid_boards = $this->get_boards();
    
    if (!$this->is_board_valid($_GET['board'])) {
      $this->errorHTML('Invalid board.');
    }
    
    $query = "SELECT host FROM `%s` WHERE no = $pid";
    
    $res = mysql_board_call($query, $_GET['board']);
    
    if (!$res) {
      $this->errorHTML('Database Error.');
    }
    
    if (!mysql_num_rows($res)) {
      $this->errorHTML('Post not found.');
    }
    
    $ip = mysql_fetch_row($res)[0];
    
    if ($ip === '') {
      $this->errorHTML('This post is archived.');
    }
    
    header('Location: ' . self::WEBROOT . '#{"ip":"' . $ip . '"}');
  }
  
  /**
   * Only keep results matching the geolocation (can be any subdivision below the country)
   */
  public function filter_location($items, $loc) {
    if (!$loc) {
      return $items;
    }
    
    require_once 'lib/geoip2.php';
    
    $data = [];
    
    $_dup = [];
    
    foreach ($items as $item) {
      if (!$item['host']) {
        continue;
      }
      
      if (isset($_dup[$item['host']])) {
        if ($_dup[$item['host']] === true) {
          $data[] = $item;
        }
        continue;
      }
      
      $ip_info = GeoIP2::get_country($item['host']);
      
      if (!$ip_info) {
        continue;
      }
      
      if (isset($ip_info['city_name']) && strcasecmp($loc, $ip_info['city_name']) === 0) {
        $data[] = $item;
        $_dup[$item['host']] = true;
      }
      else if (isset($ip_info['state_name']) && strcasecmp($loc, $ip_info['state_name']) === 0) {
        $data[] = $item;
        $_dup[$item['host']] = true;
      }
      else {
        $_dup[$item['host']] = false;
      }
    }
    
    return $data;
  }
  
  /**
   * Only keep results made by fresh IPs with no known posintg history
   */
  public function filter_unknown_results($items) {
    $data = [];
    
    $sql =<<<SQL
SELECT ip FROM `flood_log`
WHERE created_on > DATE_SUB(NOW(), INTERVAL 6 HOUR)
GROUP BY ip
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      return $data;
    }
    
    $ips = [];
    
    while ($row = mysql_fetch_row($res)) {
      $ips[$row[0]] = true;
    }
    
    if (empty($ips)) {
      return $data;
    }
    
    foreach ($items as $item) {
      if ($item['host'] && isset($ips[$item['host']])) {
        $data[] = $item;
      }
    }
    
    return $data;
  }
  
  private function process_country_field($field) {
    return str_ireplace('_safe_', $this->safe_countries, $field);
  }
  
  private function process_clause_text($col, $value) {
    $value = str_replace("'", '&#039;', $value);
    
    // Strict match
    if (preg_match('/^".+"$/', $value)) {
      $value = preg_replace('/^"|"$/', '', $value);
      
      $clause = $col . "='" . mysql_real_escape_string($value) . "'";
    }
    // Regex
    else if (preg_match('/^\/.+\/b?$/', $value)) {
      $bin_flag = preg_match('/\/b$/', $value) ? 'BINARY ' : '';
      
      $value = preg_replace('/^\/|\/b?$/', '', $value);
      
      $clause = $col . " REGEXP $bin_flag'" . mysql_real_escape_string($value) . "'";
    }
    // LIKE '%val%'
    else {
      $value = preg_replace('/^\\\\\//', '/', $value);
      $value = str_replace(array('%', '_'), array("\%", "\_"), $value);
      $clause = $col . " LIKE '%" . mysql_real_escape_string($value) . "%'";
    }
    
    return $clause;
  }
  
  private function process_clause_int($col, $value) {
    $valid_ops = array('>' => true, '<' => true, '>=' => true, '<=' => true);
    
    $operator = '=';
    
    if (preg_match('/([>=<]{1,2})\s*([0-9]+)$/', $value, $matches)) {
      if (isset($valid_ops[$matches[1]])) {
        $operator = $matches[1];
      }
      
      $value = (int)$matches[2];
      
      $clause = "$col $operator $value";
    }
    else if (strpos($value, '-') !== false) {
      $bounds = explode('-', $value);
      
      $left = (int)$bounds[0];
      $right = (int)$bounds[1];
      
      $clause = "$col BETWEEN $left AND $right";
    }
    else {
      $value = (int)$value;
      $clause = "$col = $value";
    }
    
    return $clause;
  }
  
  private function get_thread_ids_by_subject($tbl, $value, $archive_mode) {
    $tids = [];
    
    $clause = $this->process_clause_text('sub', $value);
    
    $arc = (int)$archive_mode;
    
    $lim = self::MAX_TIDS_BY_SUB + 1;
    
    $sql = "SELECT no FROM `$tbl` WHERE archived = $arc AND resto = 0 AND $clause LIMIT " . $lim;
    
    $res = mysql_board_call($sql);
    
    if (!$res) {
      return false;
    }
    
    while ($row = mysql_fetch_row($res)) {
      $tids[] = (int)$row[0];
    }
    
    return $tids;
  }
  
  /**
   * Search
   */
  public function search() {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
      if (preg_match('/^https:\/\/(boards\.(4chan|4channel)\.org$/', $_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header("Access-Control-Allow-Credentials: true");
      }
    }
    
    if (!isset($_GET['board'])) {
      $this->error('Bad Request.');
    }
    
    $valid_boards = $this->get_boards();
    
    if (!isset($valid_boards[$_GET['board']])) {
      $this->error('Invalid board.');
    }
    
    $is_partial = false;
    
    $board = htmlspecialchars($_GET['board']);
    
    $tbl = mysql_real_escape_string($board);
    
    $lim = self::MAX_BOARD_RESULTS + 1;
    
    $items = array();
    
    /**
     * Field types control how input fields are interpreted
     * string: strict match only
     * text: LIKE match with leading and trailing wildcards,
     *    strict search if the value is enclosed in double quotes ("),
     *    regex search if the value is enclosed in forward slashes (/)
     * int: integer, accepts leading operators (>, >=, <, <=) and range queries (a,b)
     */
    $valid_fields = array(
      'ip'        => array('type' => 'net', 'col' => 'host'),
      'nametrip'  => array('type' => 'text', 'col' => 'name'),
      'subject'   => array('type' => 'text', 'col' => 'sub'),
      'comment'   => array('type' => 'text', 'col' => 'com'),
      'thread_id' => array('type' => 'tid', 'col' => 'resto'),
      'filename'  => array('type' => 'text', 'col' => 'filename'),
      'fileuid'   => array('type' => 'string', 'col' => 'tim'),
      'ext'       => array('type' => 'string', 'col' => 'ext'),
      'filesize'  => array('type' => 'int', 'col' => 'fsize'),
      'th_w'      => array('type' => 'int', 'col' => 'tn_w'),
      'th_h'      => array('type' => 'int', 'col' => 'tn_h'),
      'img_w'     => array('type' => 'int', 'col' => 'w'),
      'img_h'     => array('type' => 'int', 'col' => 'h'),
      'password'  => array('type' => 'string', 'col' => 'pwd'),
      'country'   => array('type' => 'string', 'col' => 'country', 'op_not' => true),
      'md5'       => array('type' => 'string', 'col' => 'md5'),
      'phash'     => array('type' => 'dhash', 'col' => 'tmd5'),
      'user_id'   => array('type' => 'string', 'col' => 'id'),
      'ago'       => array('type' => 'ago', 'col' => 'time'),
    );
    
    if ($this->is_manager) {
      $valid_fields['pass_id'] = array('type' => 'string', 'col' => '4pass_id');
      $valid_fields['_meta'] = array('type' => 'int', 'col' => 'since4pass');
      // for the SQL query below
      $pass_id_col = ', 4pass_id, since4pass';
    }
    else {
      $pass_id_col = '';
    }
    
    // -------
    
    if (isset($_GET['pass_ref'])) {
      $_pass_id = $this->get_pass_id_from_ref($_GET['pass_ref'], $valid_boards);
      $sql_clause['pass_ref'] = "4pass_id = '$_pass_id'";
    }
    
    if (!isset($_GET['archived'])) {
      $sql_clause['archived'] = 'archived = 0';
      $archive_mode = false;
    }
    else {
      $sql_clause['archived'] = 'archived = 1';
      $archive_mode = true;
    }
    
    if (!$archive_mode && isset($_GET['loc']) && $_GET['loc'] && isset($_GET['thread_id'])) {
      $location_mode = true;
    }
    else {
      $location_mode = false;
    }
    
    if (isset($_GET['has_opts'])) {
      if ($archive_mode) {
        unset($sql_clause['archived']);
      }
      
      $sql_clause['has_opts'] = <<<SQL
(resto = 0
AND (sticky = 1 OR closed = 1 OR permasage = 1 OR permaage = 1 OR undead = 1))
SQL;
    }
    
    if (isset($_GET['has_capcode'])) {
      $sql_clause['has_capcode'] = "capcode != 'none'";
    }
    
    foreach ($valid_fields as $field => $meta) {
      if (!isset($_GET[$field])) {
        continue;
      }
      
      $value = $_GET[$field];
      
      if ($value === '') {
        continue;
      }
      
      $type = $meta['type'];
      $col = $meta['col'];
      $has_op_not = isset($meta['op_not']) && $meta['op_not'];
      
      if ($type === 'net') {
        $value = trim($value);
        
        if (preg_match('/[,\s]/', $value)) {
          $or_clause = array();
          
          $or_values = preg_split('/[,\s]+/', $value);
          
          foreach ($or_values as $or_val) {
            $or_clause[] = $this->build_net_clause($col, $or_val);
          }
          
          if (!empty($or_clause)) {
            $sql_clause[$field] = '(' . implode(' OR ', $or_clause) . ')';
          }
        }
        else {
          $sql_clause[$field] = $this->build_net_clause($col, $value);
        }
      }
      else if ($type === 'string') {
        if ($field === 'country') {
          $value = $this->process_country_field($value);
        }
        
        $value = str_replace("'", '&#039;', $value);
        
        if ($has_op_not && strpos($value, '!') !== false) {
          $value = str_replace('!', '', $value);
          $use_op_not = true;
        }
        else {
          $use_op_not = false;
        }
        
        if (strpos($value, ',') !== false) {
          $or_clause = array();
          
          $or_values = explode(',', $value);
          
          $use_and = false;
          
          foreach ($or_values as $or_val) {
            $or_val = trim($or_val);
            
            $or_clause[] = $col . "='" . mysql_real_escape_string($or_val) . "'";
          }
          
          if (!empty($or_clause)) {
            $sql_clause[$field] = '(' . implode(' OR ', $or_clause) . ')';
          }
        }
        else {
          if ($col === '4pass_id' && $value === '*') {
            $sql_clause[$field] = $col . "!=''";
          }
          else {
            $sql_clause[$field] = $col . "='" . mysql_real_escape_string($value) . "'";
          }
        }
        
        if ($use_op_not) {
          $sql_clause[$field] = '(NOT ' . $sql_clause[$field] . ')';
        }
      }
      else if ($type === 'text') {
        $value = $this->process_clause_text($col, $value);
        $sql_clause[$field] = $value;
      }
      else if ($type === 'int') {
        $value = $this->process_clause_int($col, $value);
        $sql_clause[$field] = $value;
      }
      else if ($type === 'tid') {
        if (preg_match('/[^>0-9 ]/', $value)) {
          $value = $this->get_thread_ids_by_subject($tbl, $value, $archive_mode);
          
          if (!$value) {
            $this->success_empty($board);
          }
          
          if (count($value) > self::MAX_TIDS_BY_SUB) {
            array_pop($value);
            $is_partial = true;
          }
          
          $sql_clause[$field] = $col . ' IN(' . implode(',', $value) . ')';
        }
        else {
          $value = $this->process_clause_int($col, $value);
          $sql_clause[$field] = $value;
        }
      }
      else if ($type === 'ago') {
        $value = (float)$value;
        
        if ($value < 0) {
          $value = 0;
        }
        
        $value = $_SERVER['REQUEST_TIME'] - (int)($value * 3600);
        
        $sql_clause[$field] = "$col >= $value";
      }
      else if ($type === 'dhash') {
        if ($archive_mode) {
          $this->error('phash search only works on live posts.');
        }
        
        $_hash = str_replace(['<', '>'], '', $value);
        
        if (!preg_match('/^[0-9a-f]{16}$/', $_hash)) {
          $this->error('Invalid value for phash.');
        }
        
        $_thres = 4;
        
        if ($value[0] === '>') {
          $_thres += substr_count($value, '>') * 2;
        }
        else if ($value[0] === '<') {
          $_thres -= substr_count($value, '<');
        }
        
        // FIXME: remove the length check once the old md5s are purged out
        $sql_clause[$field] = "fsize > 0 AND LENGTH(tmd5) = 16 AND BIT_COUNT(CAST(CONV('"
          . mysql_real_escape_string($_hash)
          . "', 16, 10) AS UNSIGNED) ^ CAST(CONV(tmd5, 16, 10) AS UNSIGNED)) <= $_thres";
      }
      else if ($type === 'pass_id_ref') {
        if (strpos($value, '-') !== false) {
          $clear_pass_id = $this->get_pass_id_from_post_uid($col, $value);
        }
        else {
          $clear_pass_id = $this->get_pass_id_from_ban_id($col, (int)$value);
        }
        
        $sql_clause[$field] = $col . "='" . mysql_real_escape_string($clear_pass_id) . "'";
      }
    }
    
    // User info field (stored as email)
    $user_info_sql = $this->get_user_info_query();
    
    if ($user_info_sql) {
      $sql_clause['user_info'] = "email RLIKE '$user_info_sql'";
    }
    
    $sql_clause = array_values($sql_clause);
    
    if (empty($sql_clause)) {
      $this->error('Empty Query.');
    }
    
    $sql_clause = implode(' AND ', $sql_clause);
    
    $query = <<<SQL
SELECT SQL_NO_CACHE no, resto, host, country, filename, filedeleted, fsize, ext, w, h,
permasage, permaage, sticky, undead, closed, capcode, name, sub, com, tim, time,
pwd$pass_id_col
FROM `$tbl` WHERE $sql_clause ORDER BY no ASC LIMIT $lim
SQL;
    
    $res = mysql_board_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $clean_name = $this->format_name($row['name']);
      $clean_sub = $this->format_subject($row['sub'], $board);
      
      $row['board'] = $board;
      
      if ($row['capcode'] === 'none') {
        unset($row['capcode']);
      }
      
      $has_opts = 5;
      
      if ($archive_mode || $row['closed'] === '0') {
        unset($row['closed']);
        $has_opts--;
      }
      
      if ($row['sticky'] === '0') {
        unset($row['sticky']);
        $has_opts--;
      }
      
      if ($row['permasage'] === '0' || $row['resto'] !== '0') {
        unset($row['permasage']);
        $has_opts--;
      }
      
      if ($row['permaage'] === '0') {
        unset($row['permaage']);
        $has_opts--;
      }
      
      if ($row['undead'] === '0') {
        unset($row['undead']);
        $has_opts--;
      }
      
      if ($has_opts > 0) {
        $row['has_opts'] = 1;
      }
      
      if (!$row['ext']) {
        unset($row['ext']);
        unset($row['filename']);
        unset($row['filedeleted']);
        unset($row['fsize']);
        unset($row['w']);
        unset($row['h']);
      }
      else if ($row['filedeleted'] === '0') {
        unset($row['filedeleted']);
      }
      
      if ($clean_name[1]) {
        $row['name'] = $clean_name[0];
        $row['tripcode'] = $clean_name[1];
      }
      
      if ($clean_sub[1]) {
        $row['sub'] = $clean_sub[0];
        $row['spoiler'] = $clean_sub[1];
      }
      else if ($board === 'f') {
        $row['sub'] = $clean_sub[0];
      }
      
      if (isset($row['filename']) && !preg_match('//u', $row['filename'])) {
        $row['filename'] = mb_convert_encoding($row['filename'], 'UTF-8', 'UTF-8');
      }
      
      $items[] = $row;
    }
    
    if (mysql_num_rows($res) > self::MAX_BOARD_RESULTS) {
      array_pop($items);
      $data['partial'] = true;
    }
    
    // Can be set when searching by thread sub returns too many thread ids
    if ($is_partial) {
      $data['partial'] = true;
    }
    
    if (!empty($items)) {
      if ($location_mode) {
        $items = $this->filter_location($items, trim($_GET['loc']));
      }
    }
    
    $data['board'] = $board;
    $data['posts'] = $items;
    /*
    if (has_flag('developer')) {
      $data['query'] = $query;
    }
    */
    $this->success($data);
  }
  
  /**
   * Multi ban
   */
  public function ban() {
    if (!isset($_POST['ips']) || $_POST['ips'] === '') {
      $this->error('Nothing to do.');
    }
    
    $ips = json_decode($_POST['ips'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->error('Internal Server Error (jle)');
    }
    
    if (empty($ips)) {
      $this->error('Nothing to do.');
    }
    
    $global = isset($_POST['global']) && $_POST['global'];
    
    $no_reverse = isset($_POST['no_reverse']) && $_POST['no_reverse'];
    
    if (count($ips) > self::MAX_IP_BANS && !$no_reverse) {
      $this->error('Too many IPs to ban. Maximum is ' . self::MAX_IP_BANS);
    }
    
    if (!isset($_POST['public_reason']) || $_POST['public_reason'] === '') {
      $this->error('Public reason cannot be empty.');
    }
    else {
      $public_reason = nl2br(htmlspecialchars($_POST['public_reason'], ENT_QUOTES), false);
    }
    
    if (!isset($_POST['private_reason']) || $_POST['private_reason'] === '') {
      $private_reason = '';
      $has_private_reason = false;
    }
    else {
      $private_reason = ', ' . htmlspecialchars($_POST['private_reason'], ENT_QUOTES);
      $has_private_reason = true;
    }
    
    // Include some search params in the private reason
    if ($has_private_reason === false && isset($_POST['params']) && $_POST['params']) {
      $valid_fields = array(
        'boards',
        'nametrip',
        'comment',
        'thread_id',
        'filename',
        'ext',
        'filesize',
        'img_w',
        'img_h',
        'country',
        'req_sig'
      );
      
      $params = json_decode($_POST['params'], true);
      
      $clean_params = array();
      
      foreach ($valid_fields as $valid_field) {
        if (!isset($params[$valid_field]) || $params[$valid_field] === '') {
          continue;
        }
        
        $valid_param = $params[$valid_field];
        
        if (mb_strlen($valid_param) > 25) {
          $valid_param = mb_substr($valid_param, 0, 24) . '…';
        }
        
        $clean_params[$valid_field] = $valid_param;
      }
      
      if (!empty($clean_params)) {
        $clean_params = json_encode($clean_params, JSON_UNESCAPED_UNICODE);
        $clean_params = htmlspecialchars($clean_params, ENT_QUOTES);
        
        $private_reason = ', ' . $clean_params;
      }
    }
    
    if (!isset($_POST['days'])) {
      $this->error('Ban length cannot be empty.');
    }
    
    $days = (int)$_POST['days'];
    
    if ($days == -1) {
      $length = '0';
    }
    else {
      $length = date('Y-m-d H:i:s', time() + $days * ( 24 * 60 * 60 ));
    }
    
    // Validate ips, boards and passwords and get the reverse
    $valid_boards = $this->get_boards();
    
    set_time_limit(self::REV_TIME_LIMIT);
    
    $ip_revs = array();
    
    foreach ($ips as $entry) {
      $ip = $entry['ip'];
      
      if (ip2long($ip) === false) {
        $this->error('One of the IPs is invalid.');
      }
      
      if (!isset($valid_boards[$entry['board']])) {
        $this->error('One of the boards is invalid.');
      }
      
      if ($entry['pwd'] && !preg_match(self::PWD_REGEX, $entry['pwd'])) {
        $this->error('Invalid data supplied.');
      }
      
      if (!$no_reverse) {
        $ip_revs[$ip] = gethostbyaddr($ip);
      }
      else {
        $ip_revs[$ip] = $ip;
      }
    }
    
    // Inserting bans
    set_time_limit(self::INS_TIME_LIMIT);
    
    $tbl = self::BAN_TABLE;
    
    $has_errors = false;
    
    foreach ($ips as $entry) {
      $ip = $entry['ip'];
      $board = $entry['board'];
      $pid = (int)$entry['pid'];
      $pwd = $entry['pwd'];
      $reverse = $ip_revs[$ip];
      
      $formatted_reason = "$public_reason<>via search, pid: $pid$private_reason";
      
      $query = <<<SQL
INSERT INTO `$tbl`(board, global, zonly, name, host, reverse, xff, reason,
length, admin, md5, 4pass_id, post_num, rule, post_time, post_json, template_id,
admin_ip, tripcode, password)
VALUES('%s', %d, 0, '', '%s', '%s', '', '%s', '%s', '%s', '', '', 0, '', 0,
'', 0, '%s', '', '%s')
SQL;
      /*
      printf($query,
        $board, $global, $ip, $reverse, $formatted_reason, $length,
        $_COOKIE['4chan_auser'],
        $_SERVER['REMOTE_ADDR'],
        $pwd
      );
      */
      $res = mysql_global_call($query,
        $board, $global, $ip, $reverse, $formatted_reason, $length,
        $_COOKIE['4chan_auser'],
        $_SERVER['REMOTE_ADDR'],
        $pwd
      );
      
      if (!$res) {
        $has_errors = true;
      }
    }
    
    if ($has_errors) {
      $this->error('Errors occurred. Not all IPs could be banned.');
    }
    else {
      $this->success();
    }
  }
  
  /**
   * Index
   */
  public function index() {
    $this->renderHTML('search-test');
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
