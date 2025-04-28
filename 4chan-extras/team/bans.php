<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/archives.php';

define('IN_APP', true);

auth_user();

if (!has_level('mod')) {
  APP::denied();
}

require_once 'lib/csp.php';

/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}
*/

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'update',
      'unban',
      'search',
      'ban_ips'
    ),
    
    $is_manager = false,
    
    $_salt_data = null,
    
    $_cloudflare_ready = false,
    
    $now = 0,
    
    $report_cats = array(
      1 => 'rule violation',
      2 => 'illegal content'
    )
  ;
  
  const TPL_ROOT = 'views/';
  
  const
    TABLE_NAME = 'banned_users',
    APPEALS_TABLE = 'appeals',
    TEMPLATE_TABLE = 'ban_templates',
    REP_CAT_TABLE = 'report_categories',
    PAGE_SIZE = 50,
    DATE_FORMAT = 'm/d/Y H:i:s',
    DATE_FORMAT_SHORT = 'm/d/y',
    WEBROOT = '/bans',
    WARN_THRES = 10,
    REVERSE_PARTS = 3,
    SALTFILE = '/www/keys/legacy.salt',
    THUMB_WEB_ROOT = 'https://i.4cdn.org/bans/thumb/',
    THUMB_ROOT = '/www/4chan.org/web/images/bans/thumb/',
    MAX_BAN_DAYS = 9999,
    REPORT_TEMPLATE = 190, // template used for report system abuses
    EXEC_LIMIT = 90
  ;
  
  public function __construct() {
    $this->is_developer = has_flag('developer');
    
    $this->is_manager = has_level('manager') || $this->is_developer;
    
    $this->now = time();
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
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
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
   * Plurals
   */
  private function pluralize($count, $one = '', $not_one = 's') {
    return $count == 1 ? $one : $not_one;
  }
  
  private function init_cloudflare() {
    if ($this->_cloudflare_ready) {
      return true;
    }
    
    $this->_cloudflare_ready = true;
    
    $cfg = file_get_contents('/www/global/yotsuba/config/cloudflare_config.ini');
    
    if (!$cfg) {
      return false;
    }
    
    if (preg_match('/CLOUDFLARE_API_TOKEN.?=.?([^\r\n]+)/', $cfg, $m) === false) {
      return false;
    }
    
    define('CLOUDFLARE_API_TOKEN', $m[1]);
    define('CLOUDFLARE_EMAIL', 'cloudflare@4chan.org');
    define('CLOUDFLARE_ZONE', '4chan.org');
    define('CLOUDFLARE_ZONE_2', '4cdn.org');
    
    return true;
  }
  
  /**
   * Disables blacklisted md5
   */
  private function disable_blacklist_by_md5($md5) {
    if (!$md5) {
      return true;
    }
    $sql = "UPDATE blacklist SET active = 0 WHERE field = 'md5' AND contents = '%s' LIMIT 1";
    return !!mysql_global_call($sql, $md5);
  }
  
  /**
   * Checks if a board is valid
   */
  private function is_board_valid($board) {
    if ($board === 'test') {
      if (has_flag('developer')) {
        return true;
      }
      return false;
    }
    
    if ($board === 'j') {
      return false;
    }
    
    $query = "SELECT dir FROM boardlist WHERE dir = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $board);
    
    return mysql_num_rows($res) === 1;
  }
  
  public function is_pass_valid($pass_id) {
    $sql = "SELECT user_hash FROM pass_users WHERE user_hash = '%s' LIMIT 1";
    
    $res = mysql_global_call($sql, $pass_id);
    
    if (!$res) {
      return false;
    }
    
    return mysql_num_rows($res) === 1;
  }
  
  public function is_template_valid($id) {
    $id = (int)$id;
    
    if (!$this->is_manager) {
      $clause = "AND level != 'manager'";
    }
    else {
      $clause = '';
    }
    
    $query = "SELECT no FROM ban_templates WHERE no = $id $clause LIMIT 1";
    
    $res = mysql_global_call($query);
    
    return $res && mysql_num_rows($res) === 1;
  }
  
  private function get_md5_from_blacklist_id($blid) {
    $blid = (int)$blid;
    
    $sql = "SELECT content FROM dmca_actions WHERE blacklist_id = $blid";
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      return null;
    }
    
    $content = mysql_fetch_row($res)[0];
    
    if (!$content) {
      return null;
    }
    
    $post = json_decode($content, true);
    
    if (!$post || !$post['md5']) {
      return null;
    }
    
    return $post['md5'];
  }
  
  private function get_ban_templates() {
    if (!$this->is_manager) {
      $clause = "WHERE level != 'manager'";
    }
    else {
      $clause = '';
    }
    
    $query = "SELECT rule, no, name FROM ban_templates $clause ORDER BY rule";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return array();
    }
    
    $data = array('global' => array());
    
    while ($row = mysql_fetch_assoc($res)) {
      $board = preg_replace('/(.)[0-9]+x?$/', '\1', $row['rule']);
      
      if ($board === 'global') {
        $data['global'][] = $row;
      }
      else {
        if (!isset($data[$board])) {
          $data[$board] = array();
        }
        
        $data[$board][] = $row;
      }
    }
    
    return $data;
  }
  
  /**
   * Get the Pass ID from a ban id or post id or ban request id
   */
  private function get_pass_id_from_ref($ref) {
    $ref = trim($ref);
    
    // /board/post_id
    if (preg_match('/^\/([a-z0-9]+)\/([0-9]+)$/', $ref, $m)) {
      $board = $m[1];
      $val = (int)$m[2];
      
      if (!$this->is_board_valid($board)) {
        $this->error('Invalid board for Pass Ref.');
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
      $this->error('Invalid Pass Reference.');
    }
    
    if (!$res) {
      $this->error('Database Error (gpifr)');
    }
    
    $pass_id = mysql_fetch_row($res)[0];
    
    if (!$pass_id) {
      $this->error("The referenced post or ban doesn't have a Pass associated with it.");
    }
    
    return $pass_id;
  }
  
  private function get_pass_id_from_ban_request($id) {
    $sql = "SELECT spost FROM `ban_requests` WHERE id = $id LIMIT 1";
    $res = mysql_global_call($sql);
    
    if (!$res) {
      $this->error('Database Error (gpifbr)');
    }
    
    $post = mysql_fetch_row($res)[0];
    
    if (!$post) {
      $this->error('Invalid Pass Reference.');
    }
    
    $post = unserialize($post);
    
    $pass_id = $post['4pass_id'];
    
    if (!$pass_id) {
      $this->error("The referenced post or ban doesn't have a Pass associated with it.");
    }
    
    return $pass_id;
  }
  
  /**
   * Get salt for ban url hashing. Cached.
   */
  private function get_salt() {
    if ($this->_salt_data === null) {
      $salt = file_get_contents(self::SALTFILE);
      
      if (!$salt) {
        $this->error('Internal Server Error (gbt)');
      }
      
      $this->_salt_data = $salt;
    }
    
    return $this->_salt_data;
  }
  
  /**
   * Hash 4chan Pass
   */
  private function hash_pass_id($pass_id) {
    $salt = $this->get_salt();
    
    if (!$pass_id) {
      return '';
    }
    
    return sha1($pass_id . $salt);
  }
  
  /**
   * Checks the ban template to see if the ban has a preview thumbnail.
   */
  private function has_ban_thumbnail($id) {
    $id = (int)$id;
    
    $query = 'SELECT save_post FROM ' . self::TEMPLATE_TABLE . " WHERE no = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res || !mysql_num_rows($res)) {
      return true;
    }
    
    return mysql_fetch_row($res)[0] !== 'json_only';
  }
  
  /**
   * Gets the report category for bans for report system abuse
   */
  private function get_report_category_title($cat_id) {
    $query = "SELECT title FROM " . self::REP_CAT_TABLE . " WHERE id = " . (int)$cat_id;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return 'N/A';
    }
    
    $cat = mysql_fetch_row($res);
    
    if (!$cat) {
      return 'N/A';
    }
    
    return $cat[0];
  }
  
  /**
   * Calculates and returns ban thumbnail url
   */
  private function get_ban_thumbnail($board, $post_id) {
    $salt = $this->get_salt();
    
    return self::THUMB_WEB_ROOT . $board . '/'
      . sha1($board . $post_id . $salt) . 's.jpg';
  }
  
  /**
   * Delete preserved thumbnail
   */
  private function delete_ban_thumbnail($board, $post_id) {
    $salt = $this->get_salt();
    
    if (preg_match('/^[a-z0-9]+$/', $board) === false) {
      return false;
    }
    
    $fid = $board . '/' . sha1($board . $post_id . $salt) . 's.jpg';
    
    $file = self::THUMB_ROOT . $fid;
    
    $ret = true;
    
    if (file_exists($file)) {
      $ret = unlink($file);
    }
    
    if (!$this->_cloudflare_ready) {
      $this->init_cloudflare();
    }
    
    cloudflare_purge_url(self::THUMB_WEB_ROOT . $fid, true);
    
    return $ret;
  }
  
  /**
   * Removes html from strings
   */
  private function strip_html($str) {
    $str = preg_replace('/<br ?\/?>/', "\n", $str);
    $str = preg_replace('/<[^>]+>/', '', $str);
    return htmlspecialchars(htmlspecialchars_decode($str, ENT_QUOTES), ENT_QUOTES);
  }
  
  /**
   * Returns the derefered archive url for a post
   * or false if no archive is defined.
   */
  private function archive_url($board, $post_id, $thread_id = null) {
    $url = return_archive_link($board, $post_id, false, true, $thread_id); // lib/archives
    
    if ($url) {
      $url = derefer_url($url); // lib/admin
    }
    
    return $url;
  }
  
  /**
   * Splits the name and returns array($name, $tripcode or null)
   */
  private function format_name($name, $for_report = false) {
    $name = str_replace('&#039;', "'", $name);
    
    if (strpos($name, '#')) {
      return explode('#', $name);
    }
    
    if (strpos($name, '<span ')) {
      $parts = explode('</span> <span class="postertrip">', $name);
      
      if ($parts[1]) {
        if ($for_report) {
          if ($parts[1][0] === '!') {
            $parts[1] = substr($parts[1], 1);
          }
        }
        else {
          $parts[1] = str_replace('!!', '!', $parts[1]);
        }
      }
      
      return $parts;
    }
    
    return array($name, null);
  }
  
  /**
   * Truncates the hostname, returns:
   * array((string: $truncated_hustname, bool: $is_truncated)
   */
  private function format_reverse($reverse) {
    $parts = explode('.', $reverse);
    
    $rev = implode('.<wbr>', array_slice($parts, -self::REVERSE_PARTS));
    
    if (count($parts) > self::REVERSE_PARTS) {
      $rev = array('...' . $rev, true);
    }
    else {
      $rev = array($rev, false);
    }
    
    return $rev;
  }
  
  /**
   * Splits the reason, returns array($public_reason or null, $private_reason or null) 
   */
  private function format_reason($reason) {
    $parts = explode('<>', $reason, 2);
    
    // Some old bans don't have a public reason.
    if (count($parts) < 2) {
      $parts = array(null, $parts[0]);
    }  
      
    return $parts;
  }
  
  private function process_reason_tags($reason) {
    $now = date(self::DATE_FORMAT_SHORT, $_SERVER['REQUEST_TIME']);
    
    $tags = [ '%now%', '%sign%' ];
    $replace = [ "($now)", "($now, {$_COOKIE['4chan_auser']})"];
    
    return str_replace($tags, $replace, $reason);
  }
  
  private function contains_html($str) {
    return preg_match('/<[^>]+>/', preg_replace('/<br ?\/?>/', '', $str)) === 1;
  }
  
  private function br2nl($str) {
    return preg_replace('/<br ?\/?>/', "\n", preg_replace('/[\r\n]/', '', $str));
  }
  
  /**
   * Formats the subject field (spoiler)
   * returns array($clean_subject, (bool)$is_spoiler);
   */
  private function format_subject($sub) {
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
    
    return array($sub, $spoiler);
  }
  
  /**
   * Formats name, reason, and length related fields.
   */
  private function format_entry($row) {
    $nametrip = $this->format_name($row['name']);
    $row['name'] = $nametrip[0];
    
    if (!$row['tripcode']) {
      $row['tripcode'] = $nametrip[1];
    }
    
    $reason = $this->format_reason($row['reason']);
    $row['public_reason'] = $reason[0];
    $row['private_reason'] = $reason[1];
    
    if (preg_match('/\(blacklist ID: ([0-9]+)\)/', $row['private_reason'], $blid)) {
      $blid = (int)$blid[1];
      
      $md5 = $this->get_md5_from_blacklist_id($blid);
      
      if ($md5) {
        $row['blacklisted_md5'] = $md5;
      }
    }
    
    if ($row['expires_on']) {
      $row['permanent'] = false;
      
      $length = $row['expires_on'] - $row['created_on'];
      
      if ($length <= self::WARN_THRES) { // fixme
        $row['warn'] = true;
      }
      else {
        $row['warn'] = false;
      }
      
      $row['length'] = $length;
    }
    else {
      $row['permanent'] = true;
      $row['warn'] = false;
    }
    
    if (isset($row['post_json']) && $row['post_json'] !== '') {
      $row['post_json'] = json_decode($row['post_json'], true);
    }
    
    return $row;
  }
  
  private function str_to_sql_time($str) {
    if (!$str) {
      return false;
    }
    
    if (!preg_match('/(\d\d)\/(\d\d)\/(\d\d)/', $str, $m)) {
      return false;
    }
    
    return "20{$m[3]}-{$m[1]}-{$m[2]} 00:00:00";
  }
  
  /**
   * Ban duration in days (integer)
   */
  private function days_duration($delta) {
    return (int)floor($delta / 86400);
  }
  
  /**
   * Ban duration string (X day(s))
   */
  private function pretty_duration($delta) {
    $count = (int)floor($delta / 86400);
    
    if ($count > 1) {
      $head = $count . ' days';
    }
    else {
      $head = '1 day';
    }
    
    return $head;
  }
  
  private function is_ip_rangebanned($ip) {
    $long_ip = ip2long($ip);
    
    if (!$long_ip) {
      $this->error('Invalid IP.');
    }
    
    $query =<<<SQL
SELECT id FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip
AND active = 1 AND boards = '' AND expires_on = 0
AND ops_only = 0 AND img_only = 0 AND lenient = 0 AND ua_ids = ''
LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    return mysql_num_rows($res) > 0;
  }
  
  private function updateCommit() {
    if (!isset($_POST['id'])) {
      $this->error('Entry not found.');
    }
    
    $tbl = self::TABLE_NAME;
    
    $id = (int)$_POST['id'];
    
    $query = <<<SQL
SELECT no, board, post_num, global, zonly, active, UNIX_TIMESTAMP(now) as created_on,
name, tripcode, host, reverse, reason, UNIX_TIMESTAMP(length) as expires_on,
admin as created_by, template_id FROM $tbl WHERE no = $id LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (0).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Entry not found.');
    }
    
    $entry = mysql_fetch_assoc($res);
    
    $entry = $this->format_entry($entry);
    
    // ---
    
    $set_fields = array();
    
    // Active
    if (isset($_POST['active'])) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    if ((int)$entry['active'] === 1) {
      if ($active === 0) {
        $set_fields[] = 'active = 0';
        $set_fields[] = "unbannedby = '"
          . mysql_real_escape_string($_COOKIE['4chan_auser']) . "'";
        $set_fields[] = 'unbannedon = NOW()';
        
        if ($entry['board'] && $entry['post_num'] &&
          $this->has_ban_thumbnail($entry['template_id'])) {
          $this->delete_ban_thumbnail($entry['board'], $entry['post_num']);
        }
      }
    }
    else {
      if ($active === 1) {
        $set_fields[] = 'active = 1';
      }
    }
    
    // Length
    $permanent = $warn = false;
    $days = $length = null;
    
    if (isset($_POST['permanent'])) {
      $permanent = true;
    }
    else if (isset($_POST['warn'])) {
      $warn = true;
    }
    else if (isset($_POST['days'])) {
      $days = (int)$_POST['days'];
      
      if ($days < 0 || $days > self::MAX_BAN_DAYS) {
        $this->error('Invalid ban length.');
      }
      
      if ($_POST['days'] === '0') {
        $warn = true;
      }
    }
    
    if ($permanent !== $entry['permanent'] && $permanent) {
      $set_fields[] = "length = '0'";
    }
    else if ($warn !== $entry['warn'] && $warn) {
      $set_fields[] = 'length = now';
    }
    else if ($days !== null) {
      if (!isset($entry['length']) || $days !== $this->days_duration($entry['length'])) {
        $set_fields[] = "length = DATE_ADD(now, INTERVAL $days DAY)";
      }
    }
    
    // Global
    if (isset($_POST['global'])) {
      if (!$entry['global']) {
        $set_fields[] = 'global = 1';
      }
    }
    else {
      if ($entry['global']) {
        $set_fields[] = 'global = 0';
      }
    }
    
    // Unappealable
    if ($this->is_manager) {
      if (isset($_POST['zonly'])) {
        if (!$entry['zonly']) {
          $set_fields[] = 'zonly = 1';
        }
      }
      else {
        if ($entry['zonly']) {
          $set_fields[] = 'zonly = 0';
        }
      }
    }
    
    // Public
    $public_reason = null;
    
    if (isset($_POST['public_reason']) && $_POST['public_reason'] !== '') {
      if ($this->contains_html($entry['public_reason'])) {
        $this->error("You can't edit public reasons containing HTML");
      }
      
      $public_reason = nl2br(htmlspecialchars($_POST['public_reason'], ENT_QUOTES), false);
    }
    
    if ($public_reason === $entry['public_reason']) {
      $public_reason = null;
    }
    
    // Private reason
    $private_reason = null;
    
    if (isset($_POST['private_reason']) && $_POST['private_reason'] !== '') {
      $private_reason = $this->process_reason_tags($_POST['private_reason']);
      
      $private_reason = htmlspecialchars($private_reason, ENT_QUOTES);
      
      if ($private_reason === $entry['private_reason']) {
        $private_reason = null;
      }
    }
    
    // Reason field
    if ($public_reason !== null || $private_reason !== null) {
      $reason = '';
      
      if ($public_reason !== null) {
        $reason .= $public_reason;
      }
      else {
        $reason .= $entry['public_reason'];
      }
      
      if ($private_reason !== null) {
        $reason .= "<>$private_reason";
      }
      else {
        $reason .= "<>{$entry['private_reason']}";
      }
      
      $set_fields[] = "reason = '" . mysql_real_escape_string($reason) . "'";
    }
    
    // ---
    
    if (empty($set_fields)) {
      $this->success(self::WEBROOT . '?action=update&id=' . $id);
    }
    
    $set_fields = implode(', ', $set_fields);
    
    $query =<<<SQL
UPDATE `$tbl` SET $set_fields
WHERE no = $id LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (2).');
    }
    
    
    $this->success(self::WEBROOT . '?action=update&id=' . $id);
  }
  
  private function create_ban($ip, $reason, $days, $board = null, $pass_id = null, $pwd = null, $force = false, $no_reverse = false) {
    $long_ip = ip2long($ip);
    
    $html_ip = htmlspecialchars($ip);
    
    if (!$long_ip) {
      return 'Invalid IP: ' . $html_ip;
    }
    
    if ($board) {
      $board = mysql_real_escape_string($board);
      
      if ($board === false) {
        $this->error('Database error (eb0).');
      }
      
      $global = 0;
      $board_clause = "(board = '$board' OR global = 1)";
    }
    else {
      $global = 1;
      $board = '';
      $board_clause = 'global = 1';
    }
    
    if (!$force) {
      $query = "SELECT COUNT(*) FROM banned_users WHERE active = 1 AND $board_clause AND host = '%s'";
      
      $res = mysql_global_call($query, $ip);
      
      if (!$res) {
        return 'Database Error: ' . $html_ip . ' (1)';
      }
      
      if ((int)mysql_fetch_row($res)[0] > 0) {
        return 'Already banned: ' . $html_ip;
      }
    }
    
    if ($no_reverse) {
      $reverse = $ip;
    }
    else {
      $reverse = gethostbyaddr($ip);
    }
    
    if ($days == -1) {
      $length = '0';
    }
    else {
      $length = date('Y-m-d H:i:s', time() + $days * ( 24 * 60 * 60 ));
    }
    
    if (!$pass_id) {
      $pass_id = '';
    }
    
    $query = <<<SQL
INSERT INTO banned_users (global, board, host, reverse, reason, admin, zonly,
length, name, 4pass_id, password, post_num, admin_ip)
VALUES ($global, '$board', '%s', '%s', '%s', '%s', 0, '%s', '', '%s', '%s', 0, '%s')
SQL;
    
    $res = mysql_global_call($query,
      $ip, $reverse, $reason, $_COOKIE['4chan_auser'],
      $length, $pass_id, $pwd, $_SERVER['REMOTE_ADDR']
    );
    
    if (!$res) {
      return 'Database Error: ' . $html_ip . ' (2)';
    }
    
    return "Banned $html_ip (" . htmlspecialchars($reverse) . ")";
  }
  
  /**
   * Ban a list of IPs
   */
  private function ban_ips() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->renderHTML('bans-banips');
      return;
    }
    
    if (!isset($_POST['ips']) || !isset($_POST['public_reason'])) {
      $this->error('Bad Request.');
    }
    
    $public_reason = trim($_POST['public_reason']);
    
    if (!isset($_POST['private_reason'])) {
      $private_reason = '';
    }
    else {
      $private_reason = trim($_POST['private_reason']);
    }
    
    if ($public_reason === '') {
      $this->error('Public reason cannot be empty');
    }
    
    $reason = htmlspecialchars(nl2br($public_reason), ENT_QUOTES) . '<>'
            . htmlspecialchars($private_reason, ENT_QUOTES);
    
    if (!isset($_POST['days']) || $_POST['days'] == '') {
      $days = -1;
    }
    else {
      $days = (int)trim($_POST['days']);
      
      if ($days < -1) {
        $days = -1;
      }
    }
    
    $board = null;
    
    if (isset($_POST['board']) && $_POST['board'] !== '') {
      if ($this->is_board_valid($_POST['board'])) {
        $board = $_POST['board'];
      }
      else {
        $this->error('Invalid board.');
      }
    }
    
    set_time_limit(self::EXEC_LIMIT);
    
    $ips = preg_split('/[\r\n]+/', $_POST['ips']);
    
    if ($this->is_developer && isset($_POST['passid']) && $_POST['passid']) {
      if (count($ips) > 1) {
        $this->error('Only one IP can be banned alongside a 4chan Pass.');
      }
      
      $pass_id = trim($_POST['passid']);
      
      if (!$this->is_pass_valid($pass_id)) {
        $this->error('Invalid 4chan Pass.');
      }
    }
    else {
      $pass_id = null;
    }
    
    if ($this->is_developer && isset($_POST['pwd']) && $_POST['pwd']) {
      if (count($ips) > 1) {
        $this->error('Only one IP can be banned alongside a Password.');
      }
      
      $pwd = trim($_POST['pwd']);
      
      if (!preg_match('/^[a-f0-9]{32}$/', $pwd)) {
        $this->error('Invalid Password.');
      }
    }
    else {
      $pwd = null;
    }
    
    $status = [];
    
    $force = isset($_POST['force']) && $_POST['force'];
    
    $no_reverse = isset($_POST['no_reverse']) && $_POST['no_reverse'];
    
    $no_rangebans = isset($_POST['no_rangebans']) && $_POST['no_rangebans'];
    
    $dups = array();
    
    foreach ($ips as $ip) {
      $ip = trim($ip);
      
      if (strpos($ip, ':') !== false) {
        $ip = preg_replace('/:[0-9]+$/', '', $ip);
      }
      
      if (isset($dups[$ip])) {
        continue;
      }
      
      $dups[$ip] = true;
      
      if ($no_rangebans && $this->is_ip_rangebanned($ip)) {
        $status[] = 'Already rangebanned: ' . htmlspecialchars($ip);
        continue;
      }
      
      $status[] = $this->create_ban($ip, $reason, $days, $board, $pass_id, $pwd, $force, $no_reverse);
    }
    
    $this->status = $status;
    
    $this->renderHTML('bans-banips');
  }
  
  /**
   * Unban
   */
  public function unban() {
    if (!isset($_POST['ids'])) {
      $this->errorJSON('Bad Request');
    }
    
    $ids = array();
    
    $tmp = explode(',', $_POST['ids']);
    
    foreach ($tmp as $id) {
      $id = (int)$id;
      
      if ($id) {
        $ids[] = $id;
      }
    }
    
    if (empty($ids)) {
      $this->errorJSON('Nothing to do');
    }
    
    $tbl = self::TABLE_NAME;
    
    $clause = implode(',', $ids);
    $count = count($ids);
    
    $query =<<<SQL
UPDATE $tbl SET active = 0, unbannedby = '%s', unbannedon = NOW()
WHERE no IN($clause) AND active = 1 LIMIT $count
SQL;
    
    $res = mysql_global_call($query, $_COOKIE['4chan_auser']);
    
    if (!$res) {
      $this->errorJSON('Database Error (0)');
    }
    
    // Delete ban thumbnails
    $query = "SELECT board, post_num FROM $tbl WHERE no IN($clause)";
    
    $res = mysql_global_call($query, $_COOKIE['4chan_auser']);
    
    if (!$res) {
      $this->errorJSON('Database Error (1)');
    }
    
    while ($entry = mysql_fetch_assoc($res)) {
      if ($entry['board'] && $entry['post_num']) {
        $this->delete_ban_thumbnail($entry['board'], $entry['post_num']);
      }
    }
    
    $this->successJSON(array('affected' => mysql_affected_rows()));
  }
  
  /**
   * Update entry
   */
  public function update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->updateCommit();
    }
    else if (isset($_GET['id'])) {
      require_once 'lib/geoip2.php';
      
      $tbl = self::TABLE_NAME;
      
      $id = (int)$_GET['id'];
      
      $query =<<<SQL
SELECT no, board, post_num, global, zonly, active, UNIX_TIMESTAMP(now) as created_on,
name, tripcode, host, reverse, reason, UNIX_TIMESTAMP(length) as expires_on,
admin as created_by, unbannedby, UNIX_TIMESTAMP(unbannedon) as unbanned_on,
template_id, md5, password, 4pass_id, post_json FROM $tbl WHERE no = $id LIMIT 1
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error (1).');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Entry not found.');
      }
      
      $this->item = $this->format_entry(mysql_fetch_assoc($res));
      
      // User info (email field)
      if ($this->item['post_json']) {
        $this->item['user_info'] = decode_user_meta($this->item['post_json']['ua']);
      }
      else {
        $this->item['user_info'] = [];
      }
      
      // Get geolocation
      $geoinfo = GeoIP2::get_country($this->item['host']);
      
      if ($geoinfo && isset($geoinfo['country_code'])) {
        $geo_loc = array();
        
        if (isset($geoinfo['city_name'])) {
          $geo_loc[] = $geoinfo['city_name'];
        }
        
        if (isset($geoinfo['state_code'])) {
          $geo_loc[] = $geoinfo['state_code'];
        }
        
        $geo_loc[] = $geoinfo['country_name'];
        
        $this->item['location'] = htmlspecialchars(implode(', ', $geo_loc), ENT_QUOTES);
      }
      else {
        $this->item['location'] = null;
      }
      
      // Check if the thumbnail is available
      if (is_array($this->item['post_json'])
        && $this->item['post_json']['ext']
        && $this->item['template_id'])
      {
        $this->item['has_thumbnail'] = $this->has_ban_thumbnail($this->item['template_id']);
      }
      else {
        $this->item['has_thumbnail'] = false;
      }
      
      // Count previous bans by IP
      $query = "SELECT COUNT(*) FROM $tbl WHERE host = '%s'";
      
      $res = mysql_global_call($query, $this->item['host']);
      
      if (!$res) {
        $this->error('Database Error (2).');
      }
      
      $count = (int)mysql_fetch_row($res)[0];
      
      if ($count < 0) {
        $count = 0;
      }
      
      $this->item['ban_history'] = $count;
      
      // Count previous bans by Pass (double query for indexes)
      if ($this->item['4pass_id']) {
        $count = 0;
        
        $query = "SELECT COUNT(*) FROM $tbl WHERE active = 1 AND 4pass_id = '%s'";
        
        $res = mysql_global_call($query, $this->item['4pass_id']);
        
        if (!$res) {
          $this->error('Database Error (2-1).');
        }
        
        $count += (int)mysql_fetch_row($res)[0];
        
        $query = "SELECT COUNT(*) FROM $tbl WHERE active = 0 AND 4pass_id = '%s'";
        
        $res = mysql_global_call($query, $this->item['4pass_id']);
        
        if (!$res) {
          $this->error('Database Error (2-2).');
        }
        
        $count += (int)mysql_fetch_row($res)[0];
        
        $this->item['ban_history_pass'] = $count;
      }
      
      if ($this->item['template_id']) {
        $query = 'SELECT name FROM ban_templates WHERE no = ' . (int)$this->item['template_id'];
        $res = mysql_global_call($query);
        $this->item['template_name'] = mysql_fetch_row($res)[0];
      }
      else {
        $this->item['template_name'] = null;
      }
      
      // Appeals
      $appeals_tbl = self::APPEALS_TABLE;
      
      $query = "SELECT plea, plea_history FROM $appeals_tbl WHERE no = $id LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if ($res && mysql_num_rows($res) === 1) {
        $row = mysql_fetch_assoc($res);
        
        if ($row['plea_history']) {
          $appeals = json_decode($row['plea_history'], true);
          
          $appeals = array_reverse($appeals);
        }
        else {
          $appeals = array();
        } 
        
        if ($row['plea'] !== $appeals[0]['plea']) {
          array_unshift($appeals, array('plea' => $row['plea']));
        }
      }
      else {
        $appeals = null;
      }
      
      $this->item['appeals'] = $appeals;
    }
    else {
      $this->item = null;
    }
    
    $this->renderHTML('bans-update');
  }
  
  /**
   * Search
   */
  public function search() {
    if (count($_GET) <= 1) {
      $this->ban_templates = $this->get_ban_templates();
      $this->renderHTML('bans-search');
      return;
    }
    
    require_once 'lib/geoip2.php';
    
    $this->search_mode = true;
    
    $tbl = self::TABLE_NAME;
    
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $url_params = array();
    $sql_clause = array();
    
    $this->items = array();
    
    $valid_fields = array(
      'active'    => array('bool', 'active'),
      'ip'        => array('string', 'host', true),
      'hostname'  => array('text', 'reverse'),
      'name'      => array('text', 'name'),
      'tripcode'  => array('text', 'tripcode'),
      'board'     => array('string', 'board'),
      'post_id'   => array('int', 'post_num'),
      'password'  => array('string', 'password'),
      'md5'       => array('string', 'md5'),
      'banned_by' => array('string', 'admin'),
      'tpl'       => array('int', 'template_id'),
      'reason'    => array('text', 'reason'),
      'ds'        => array('date', 'now'),
      'pass_ref'  => array('pass_ref', '4pass_id'),
      'country'   => array('post_json', 'country', 'string'),
      'ua'        => array('post_json', 'ua', 'text'),
      'sub'       => array('post_json', '%sub', 'text') // rel_sub or sub
    );
    
    if (isset($_GET['tpl'])) {
      if (!$this->is_template_valid($_GET['tpl'])) {
        $this->error('Invalid Ban Template.');
      }
    }
    
    if ($this->is_manager) {
      $valid_fields['pass_id'] = array('string', '4pass_id');
    }
    
    foreach ($valid_fields as $field => $meta) {
      if (!isset($_GET[$field])) {
        continue;
      }
      
      $value = $_GET[$field];
      
      if ($value === '') {
        continue;
      }
      
      $type = $meta[0];
      $col = $meta[1];
      $extra = isset($meta[2]) ? $meta[2] : null;
      
      if ($type === 'bool') {
        $value = $value ? 1 : 0;
        $url_params[] = "$field=$value";
        $sql_clause[] = "$col=$value";
      }
      else if ($type === 'string') {
        $url_params[] = $field . '=' . urlencode($value);
        
        if ($extra && preg_match('/\*$/', $value)) {
          $value = str_replace(array('%', '_'), array("\%", "\_"), $value);
          
          $value = preg_replace('/\*+$/', '%', $value);
          
          if (strlen($value) < 2) {
            $this->error('Empty Query.');
          }
          
          $sql_clause[] = $col . " LIKE '" . mysql_real_escape_string($value) . "'";
        }
        else {
          $sql_clause[] = $col . "='" . mysql_real_escape_string($value) . "'";
        }
        
        if ($col === '4pass_id' && !isset($_GET['active'])) {
          $sql_clause[] = '(active = 1 OR active = 0)';
        }
      }
      else if ($type === 'text') {
        $url_params[] = $field . '=' . urlencode($value);
        $value = str_replace(array('%', '_'), array("\%", "\_"), $value);
        $sql_clause[] = $col . " LIKE '%" . mysql_real_escape_string($value) . "%'";
      }
      else if ($type === 'int') {
        $value = (int)$value;
        $url_params[] = "$field=$value";
        $sql_clause[] = "$col=$value";
      }
      else if ($type === 'date') {
        $url_params[] = "$field=$value";
        
        $date_start = $this->str_to_sql_time($value);
        
        if (!$date_start) {
          $this->error('Invalid start date.');
        }
        
        $date_start = "'$date_start'";
        
        if (isset($_GET['de']) && $_GET['de'] !== '') {
          $date_end = $this->str_to_sql_time($_GET['de']);
          
          if (!$date_end) {
            $this->error('Invalid end date.');
          }
          
          $url_params[] = "de=" . $_GET['de'];
          
          $date_end = "'$date_end'";
        }
        else {
          $date_end = 'NOW()';
        }
        
        $sql_clause[] = "$col BETWEEN $date_start AND $date_end";
      }
      else if ($type === 'pass_ref') {
        $url_params[] = $field . '=' . urlencode($value);
        $pass_id = $this->get_pass_id_from_ref($_GET['pass_ref']);
        $sql_clause[] = "(" . $col . "='" . mysql_real_escape_string($pass_id) . "' AND (active = 1 OR active = 0))";
      }
      else if ($type === 'post_json') {
        $url_params[] = $field . '=' . urlencode($value);
        $value = mysql_real_escape_string($value);
        
        if ($extra === 'text') {
          $value = "%$value%";
        }
        
        $sql_clause[] = "post_json LIKE '%\"$col\":\"$value\"%'";
        
        if ($field === 'country') {
          // Country search needs to skip bans for reports
          $sql_clause[] = "template_id != " . self::REPORT_TEMPLATE;
        }
      }
    }
    
    if (empty($sql_clause)) {
      $this->error('Empty Query.');
    }
    
    set_time_limit(self::EXEC_LIMIT);
    
    $sql_clause = implode(' AND ', $sql_clause);
    
    $query = <<<SQL
SELECT no, board, post_num, global, zonly, active, UNIX_TIMESTAMP(now) as created_on,
name, tripcode, host, reverse, 4pass_id, reason, UNIX_TIMESTAMP(length) as expires_on,
admin as created_by, post_json
FROM $tbl WHERE $sql_clause ORDER BY no DESC LIMIT $offset,$lim
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
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
    
    $_loc_cache = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $row = $this->format_entry($row);
      
      // Get geolocation
      if (isset($_loc_cache[$row['host']])) {
        $row['location'] = $_loc_cache[$row['host']];
      }
      else {
        $geoinfo = GeoIP2::get_country($row['host']);
        
        if ($geoinfo && isset($geoinfo['country_code'])) {
          $geo_loc = array();
          
          if (isset($geoinfo['city_name'])) {
            $geo_loc[] = $geoinfo['city_name'];
          }
          
          if (isset($geoinfo['state_code'])) {
            $geo_loc[] = $geoinfo['state_code'];
          }
          
          $geo_loc[] = $geoinfo['country_name'];
          
          $_ret = htmlspecialchars(implode(', ', $geo_loc), ENT_QUOTES);
          
          $row['location'] = $_ret;
          
          $_loc_cache[$row['host']] = $_ret;
        }
      }
      
      $this->items[] = $row;
    }
    
    if ($this->next_offset) {
      array_pop($this->items);
    }
    
    $url_params = htmlspecialchars(implode('&', $url_params), ENT_QUOTES);
    
    $this->search_qs = 'action=search&amp;' . $url_params . '&amp;';
    
    $this->renderHTML('bans');
  }
  
  /**
   * Index
   */
  public function index() {
    $tbl = self::TABLE_NAME;
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $query =<<<SQL
SELECT SQL_NO_CACHE no, board, post_num, global, zonly, active, UNIX_TIMESTAMP(now) as created_on,
name, tripcode, host, reverse, reason, UNIX_TIMESTAMP(length) as expires_on,
admin as created_by, post_json
FROM $tbl ORDER BY no DESC LIMIT $offset,$lim
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error');
    }
    
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
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $this->format_entry($row);
    }
    
    if ($this->next_offset) {
      array_pop($this->items);
    }
    
    $this->search_qs = null;
    
    $this->renderHTML('bans');
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
