<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once '../lib/sec.php';
require_once '../lib/otp_session.php';

define('IN_APP', true);

auth_user();

if (!has_level('admin') && (!has_level('manager') || !has_flag('legal')) && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

OTPSession::validate();
/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
}
*/
class App {
  protected
    // Routes
    $actions = array(
      'index',
      'view',
      'view_raw',
      //'dump',
      'search',
      'create',
      'save',
      'send',
      'attachment',
      'email'
    ),
    
    $valid_types = array(
      'ed' => 'Emergency Disclosure',
      'po' => 'Preservation Order',
      'sw' => 'Search Warrant',
      'sub' => 'Subpoena'
    ),
    
    $label_to_type = null,
    
    $valid_boards = null,
    
    $now = null,
    
    $debug_log = array(),
    
    $ts_range = array(
      'start' => null,
      'end' => null,
      'start_sql' => null,
      'end_sql' => null,
      'sql_int' => null,
      'sql_ts' => null
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const WEBROOT = '/manager/legalrequest';
  
  const
    TYPE_EM_DISC = 'ed', // Emergency Disclosure
    TYPE_PR_ORDER = 'po', // Preservation Order
    TYPE_SUBPOENA = 'sub',
    TYPE_S_WARRANT = 'sw' // Search Warrant
  ;
  
  const
    EMAIL_EM_DISC = '../data/mail_legalrequest_emergencydisclosure_response.txt',
    EMAIL_PR_ORDER = '../data/mail_legalrequest_preservationorder_confirm.txt',
    EMAIL_SUBPOENA = '../data/mail_legalrequest_subpoena_response.txt',
    EMAIL_S_WARRANT = '../data/mail_legalrequest_searchwarrant_response.txt',
    
    EMAIL_FILENAME = '4chan Report {{REPORT_ID}}{{REQUESTER_DOC_ID}}.txt',
    FROM_NAME = '4chan LEO Support',
    FROM_ADDRESS = 'lawenforcement@4chan.org';
  
  const
    OLD_THRES = 210, // Preservation Orders older than 210 days are marked as such
    DATE_FORMAT_SQL = '%m/%d/%y %H:%i:%s',
    DATE_FORMAT = 'm/d/Y H:i:s T',
    DATE_FORMAT_SHORT = 'm/d/Y',
    PAGE_SIZE = 100
  ;
  
  const
    REQ_TBL = 'preserved_information',
    ATTCH_TBL = 'preserved_information_attachments',
    USER_ACT_TBL = 'user_actions',
    BAN_TBL = 'banned_users',
    DEL_TBL = 'del_log',
    NCMEC_TBL = 'ncmec_reports',
    PASS_TBL = 'pass_users'
  ;
  
  const REPORT_TEMPLATE = 190; // skip the template used for report system abuses
  
  public function __construct() {
    global $mysql_debug_buf;
    
    $this->valid_boards = $this->get_boards();
    
    $this->label_to_type = array_flip($this->valid_types);
    
    $mysql_debug_buf = '';
    
    $this->now = time();
    
    set_time_limit(0);
  }
  
  private function log($str) {
    $this->debug_log[] = $str;
  }
  
  private function get_boards() {
    $query = 'SELECT dir FROM boardlist ORDER BY dir ASC';
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error('Database Error (gb0)');
    }
    
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
  
  private function get_mysql_query_log() {
    global $mysql_debug_buf;
    return $mysql_debug_buf;
  }
  
  private function prettyReqType($type) {
    if ($type === 'Preservation Order') {
      return 'Records preserved pursuant a preservation order (18 U.S.C. § 2703(f)).';
    }
    else if ($type === 'Emergency Disclosure') {
      return 'Records provided pursuant to an emergency disclosure request '
        . '(18 U.S.C. § 2702(b)(8) and § 2702(c)(4)).';
    }
    else {
      return 'Records provided pursuant to a ' . htmlspecialchars(strtolower($type)) . '.';
    }
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function report_start_section($title, $count = null) {
    if ($count) {
      $count = " ($count):";
    }
    else {
      $count = ':';
    }
    
    return <<<TXT

$title$count
=====================

TXT;
  }
  
  private function report_end_section() {
    return "\n\n";
  }
  
  private function format_filename($report_id, $case_id = null) {
    $values = array(
      '{{REPORT_ID}}' => $report_id,
      '{{REQUESTER_DOC_ID}}' => $case_id ? " (Case ID {$case_id})" : ''
    );
    
    return str_replace(array_keys($values), array_values($values), self::EMAIL_FILENAME);
  }
  
  /**
   * Send report as attachment
   */
  private function send_attached_report($email, $subject, $body, $filename, $attachment, $cc_emails = null) {
    $cc_header = '';
    
    if ($cc_emails) {
      $cc_emails = explode(',', $cc_emails);
      $cc_header = implode(', ', $cc_emails);
      $cc_header = "Cc: $cc_header\r\n";
    }
    
    if (!$attachment) {
      $this->error('The attachment is empty.');
    }
    
    $attachment = chunk_split(base64_encode($attachment));
    
    $boundary = 'fourchan' . md5(mt_rand() . microtime());
    
    $headers = "From: " . self::FROM_NAME . " <" . self::FROM_ADDRESS . ">\r\n";
    $headers .= $cc_header;
    $headers .= "Bcc: " . self::FROM_NAME . " <" . self::FROM_ADDRESS . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    
    $message = '--' . $boundary . "\r\n"
      . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
      . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
      . $body . "\r\n\r\n"
      . '--' . $boundary . "\r\n"
      . "Content-Type: text/plain; name=\"$filename\"\r\n"
      . "Content-Disposition: attachment; filename=\"$filename\"\r\n"
      . "Content-Transfer-Encoding: base64\r\n\r\n" . $attachment . "\r\n\r\n"
      . '--' . $boundary . "\r\n";
    
    $opts = '-f ' . self::FROM_ADDRESS;
    
    return mail($email, $subject, $message, $headers, $opts);
  }  
  
  /**
   * No attachment. For Preservation Requests and Emergency Disclosures.
   */
  private function send_text_report($email, $subject, $message, $cc_emails = null) {
    $cc_header = '';
    
    if ($cc_emails) {
      $cc_emails = explode(',', $cc_emails);
      $cc_header = implode(', ', $cc_emails);
      $cc_header = "Cc: $cc_header\r\n";
    }
    
    $headers = "From: " . self::FROM_NAME . " <" . self::FROM_ADDRESS . ">\r\n";
    $headers .= $cc_header;
    $headers .= "Bcc: " . self::FROM_NAME . " <" . self::FROM_ADDRESS . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $opts = '-f ' . self::FROM_ADDRESS;
    
    return mail($email, $subject, $message, $headers, $opts);
  }
  
  /**
   * 
   */
  private function get_email_content($mail_file) {
    if (!file_exists($mail_file)) {
      $this->error('E-mail file not found');
    }
    
    $lines = file($mail_file);
    
    $subject = trim(array_shift($lines));
    $message = implode('', $lines);
    
    return array($subject, $message);
  }
  
  /**
   * 
   */
  private function parse_time_ranges() {
    // YYYY-MM-DD HH:mm:ss
    $pattern = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/';
    
    foreach (array('start', 'end') as $k) {
      $field = 'date_' . $k;
      
      if (!isset($_POST[$field]) || $_POST[$field] === '') {
        continue;
      }
      
      $v = $_POST[$field];
      
      if (!preg_match($pattern, $v, $m)) {
        $this->error('Invalid time range (' . $k . '0)');
      }
      
      list($_, $year, $month, $day, $hour, $min, $sec) = $m;
      
      $ts = mktime($hour, $min, $sec, $month, $day, $year);
      
      if ($ts === false || $ts === -1) {
        $this->error('Invalid time range (' . $k . '1)');
      }
      
      $this->ts_range[$k] = $ts;
      $this->ts_range["{$k}_sql"] = date('Y-m-d H:i:s', $ts);
    }
    
    $clause_int = array();
    $clause_ts = array();
    
    if ($this->ts_range['start']) {
      $clause_int[] = "%s >= {$this->ts_range['start']}";
      $clause_ts[] = "%s >= '{$this->ts_range['start_sql']}'";
    }
    
    if ($this->ts_range['end']) {
      $clause_int[] = "%s <= {$this->ts_range['end']}";
      $clause_ts[] = "%s <= '{$this->ts_range['end_sql']}'";
    }
    
    if (!empty($clause_int)) {
      $this->ts_range['sql_int'] = ' AND ' . implode(' AND ', $clause_int);
      $this->ts_range['sql_ts'] = ' AND ' . implode(' AND ', $clause_ts);
    }
    
    return true;
  }
  
  private function get_range_clause($col, $is_int = false) {
    $r = $this->ts_range;
    
    if ($r['start'] === null && $r['end'] === null) {
      return '';
    }
    
    if ($is_int) {
      if ($r['start'] && $r['start']) {
        return sprintf($r['sql_int'], $col, $col);
      }
      return sprintf($r['sql_int'], $col);
    }
    else {
      if ($r['start'] && $r['start']) {
        return sprintf($r['sql_ts'], $col, $col);
      }
      return sprintf($r['sql_ts'], $col);
    }
  }
  
  private function ips_from_post_times($post_times) {
    $data = array();
    $map = array();
    
    foreach ($post_times as $board => $posts) {
      foreach ($posts as $pid => $post) {
        if (isset($map[$post['ip']])) {
          continue;
        }
        $data[] = $post['ip'];
      }
    }
    
    return $data;
  }
  
  private function board_pids_from_post_uids($post_uids) {
    $board_pids = array();
    
    $post_uids = preg_split('/[\n\r]+/', trim($post_uids));
    
    foreach ($post_uids as $post_uid) {
      $post_uid = trim($post_uid);
      
      if (preg_match('/\/([a-z0-9]+)\/thread\/([0-9]+)(?:\/[^#]+)?(?:#p([0-9]+))?/', $post_uid, $m)) {
        $board = $m[1];
        
        if (isset($m[3])) { // link with a post number fragment (#p123)
          $pid = (int)$m[3];
        }
        else { // link to OP
          $pid = (int)$m[2];
        }
      }
      else if (preg_match('/^[^a-z0-9]*([a-z0-9]+)[^a-z0-9]+([0-9]+)$/i', $post_uid, $m)) {
        $board = $m[1];
        $pid = (int)$m[2];
      }
      else {
        $this->error('Invalid post UID: ' . htmlspecialchars($post_uid));
      }
      /*
      if (!isset($this->valid_boards[$board])) {
        $this->error('Invalid board: ' . htmlspecialchars($board));
      }
      */
      if (!isset($board_pids[$board])) {
        $board_pids[$board] = array($pid);
      }
      else {
        $board_pids[$board][] = $pid;
      }
    }
    
    return $board_pids;
  }
  
  private function merge_post_times($dest, $src) {
    foreach ($src as $board => $posts) {
      if (isset($dest[$board])) {
        $dest[$board] = $dest[$board] + $posts;
      }
      else {
        $dest[$board] = $posts;
      }
    }
    
    return $dest;
  }
  
  private function post_times_from_bans($bans) {
    $results = array();
    
    foreach ($bans as $ban_id => $ban) {
      if (!$ban['post_json']) {
        continue;
      }
      
      if (!isset($results[$ban['board']])) {
        $results[$ban['board']] = array();
      }
      
      $post = json_decode($ban['post_json']);
      
      $results[$ban['board']][$post['no']] = array(
        'ip' => $ban['host'],
        'time' => $post['time']
      );
    }
    
    return $results;
  }
  
  /**
   * Breaks names into name + tripcode
   * Returns array(name, tripcode or null);
   */
  private function nametrips_from_user_names($user_names) {
    $results = array();
    
    foreach ($user_names as $name) {
      $name = htmlspecialchars($name, ENT_QUOTES);
      $tripcode = null;
      
      if (strpos($name, '!') !== false) {
        $parts = explode('!', $name);
        
        $name = $parts[0];
        $tripcode = $parts[1];
      }
      
      $results[] = array($name, $tripcode);
    }
    
    return $results;
  }
  
  /**
   * 
   */
  private function board_tims_from_img_urls($img_urls) {
    $tims = array();
    
    foreach ($img_urls as $url) {
      if (preg_match('/^[0-9]+$/', $url)) {
        $tims[] = $url;
      }
      else if (preg_match('/\/([a-z0-9]+)\/([0-9]+)s?\.....?/', $url, $m)) {
        $board = $m[1];
        $tim = $m[2];
        
        if (!isset($this->valid_boards[$board])) {
          $this->error('Invalid board: ' . htmlspecialchars($$board));
        }
        
        if (!isset($tims[$board])) {
          $tims[$board] = array($tim);
        }
        else {
          $tims[$board][] = $tim;
        }
      }
      else {
        $this->error('Invalid image url: ' . htmlspecialchars($url));
      }
    }
    
    return $tims;
  }
  
  /**
   * Search 4chan Passes by ip.
   * Returns the last used unix timestamp, the ip and the associated email.
   * Associated email is the gift email if present or the purchaser email.
   */
  private function find_passes_by_ip($ips) {
    $tbl = self::PASS_TBL;
    
    if (empty($ips)) {
      return array();
    }
    
    $ips = array_map('mysql_real_escape_string', $ips);
    $ip_clause = implode("','", $ips);
    
    $query = <<<SQL
SELECT user_hash, last_ip as ip, UNIX_TIMESTAMP(last_used) as last_used,
email as user_email, gift_email, customer_id, transaction_id, registration_ip,
UNIX_TIMESTAMP(purchase_date) as purchase_date
FROM `$tbl` WHERE last_ip IN ('$ip_clause')
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (f4bi0)');
    }
    
    $results = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      if ($row['gift_email']) {
        $row['user_email'] = $row['gift_email'];
        unset($row['customer_id']);
        unset($row['transaction_id']);
        unset($row['registration_ip']);
      }
      
      unset($row['gift_email']);
      
      $results[] = $row;
    }
    
    return $results;
  }
  
  /**
   * Search 4chan Passes by user hash.
   * Returns the last used unix timestamp, the ip and the associated email.
   * Associated email is the gift email if present or the purchaser email.
   */
  private function find_passes_by_user_hash($user_hashes) {
    $tbl = self::PASS_TBL;
    
    if (empty($user_hashes)) {
      return array();
    }
    
    $user_hashes = array_map('mysql_real_escape_string', $user_hashes);
    $user_hashes_clause = implode("','", $user_hashes);
    
    $query = <<<SQL
SELECT user_hash, last_ip as ip, UNIX_TIMESTAMP(last_used) as last_used,
email as user_email, gift_email, customer_id, transaction_id, registration_ip,
UNIX_TIMESTAMP(purchase_date) as purchase_date
FROM `$tbl` WHERE user_hash IN ('$user_hashes_clause')
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (f4bi0)');
    }
    
    $results = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      if ($row['gift_email']) {
        $row['user_email'] = $row['gift_email'];
        unset($row['customer_id']);
        unset($row['transaction_id']);
        unset($row['registration_ip']);
      }
      
      unset($row['gift_email']);
      
      $results[] = $row;
    }
    
    return $results;
  }
  
  /**
   * Collects user hashes from posts and bans
   */
  private function collect_user_hashes($all_posts, $all_bans) {
    $user_hashes = array();
    
    foreach ($all_bans as $ban_id => $ban) {
      if ($ban['4pass_id']) {
        $user_hashes[$ban['4pass_id']] = true;
      }
    }
    
    foreach ($all_posts as $board => $posts) {
      foreach ($posts as $pid => $post) {
        if ($post['4pass_id']) {
          $user_hashes[$post['4pass_id']] = true;
        }
      }
    }
    
    return array_keys($user_hashes);
  }
  
  private function find_posts_by_nametrips($board, $nametrips) {
    $range_clause = $this->get_range_clause('time', true);
    
    foreach ($nametrips as $nametrip) {
      $clause = array();
      
      if ($nametrip[0]) {
        $name = trim($nametrip[0]);
        $clause[] = "name LIKE '%%{$name}%%'";
      }
      
      if ($nametrip[1]) {
        $trip = trim($nametrip[1]);
        $clause[] = "name LIKE '%%{$trip}%%'";
      }
      
      $clause = "(" . implode(' OR ', $clause) . ")";
      
      $query = "SELECT * FROM `%s` WHERE $clause$range_clause";
      
      $res = mysql_board_call($query, $board);
      
      if (!$res) {
        $this->error('Database Error (fpbnt0)');
      }
      
      $results = array();
      
      if (!isset($results[$board])) {
        $results[$board] = array();
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $results[$board][$row['no']] = $row;
      }
    }
    
    return $results;
  }
  
  private function find_deletions_by_nametrips($nametrips, $board = null) {
    $range_clause = $this->get_range_clause('time', true);
    
    $tbl = self::DEL_TBL;
    
    $range_clause = $this->get_range_clause('ts');
    
    if ($board) {
      $board_clause = " AND board = '" . mysql_real_escape_string($board) . "'";
    }
    else {
      $board_clause = '';
    }
    
    foreach ($nametrips as $nametrip) {
      $clause = array();
      
      if ($nametrip[0]) {
        $name = mysql_real_escape_string($nametrip[0]);
        $clause[] = "name LIKE '%%{$name}%%'";
      }
      
      if ($nametrip[1]) {
        $trip = mysql_real_escape_string($nametrip[1]);
        $clause[] = "name LIKE '%%{$trip}%%'";
      }
      
      $clause = "(" . implode(' OR ', $clause) . ")";
      
      $query = <<<SQL
SELECT id, postno as no, board, resto, name, sub, com, UNIX_TIMESTAMP(ts) as time,
cleared, filename
FROM `$tbl` WHERE $clause$board_clause$range_clause
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error (fpbnt0)');
      }
      
      $results = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $b = $row['board'];
        
        if (!isset($results[$b])) {
          $results[$b] = array();
        }
        
        $results[$b][$row['no']] = $row;
      }
    }
    
    return $results;
  }
  
  private function find_bans_by_nametrips($nametrips, $board = null) {
    $range_clause = $this->get_range_clause('now');
    
    $tbl = self::BAN_TBL;
    
    if ($board) {
      $board_clause = " AND board = '" . mysql_real_escape_string($board) . "'";
    }
    else {
      $board_clause = '';
    }
    
    $skip_tpl = self::REPORT_TEMPLATE;
    
    foreach ($nametrips as $nametrip) {
      $clause = array();
      
      if ($nametrip[0]) {
        $name = mysql_real_escape_string($nametrip[0]);
        $clause[] = "name LIKE '%%$name%%'";
      }
      
      if ($nametrip[1]) {
        $trip = mysql_real_escape_string($nametrip[1]);
        $clause[] = "tripcode LIKE '%%$trip%%'";
      }
      
      $clause = implode(' OR ', $clause);
      
      $query = <<<SQL
SELECT no as id, board, post_num, name, tripcode, password, 4pass_id,
host as ip, reverse, reason, post_json, md5, UNIX_TIMESTAMP(now) as time
FROM `$tbl`
WHERE template_id != $skip_tpl AND $clause$board_clause$range_clause
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error (fbbnt0)');
      }
      
      $results = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $results[$row['id']] = $row;
      }
    }
    
    return $results;
  }
  
  /**
   * fixme: cs or binary collation
   */
  private function find_posts_by_user_id($board, $user_ids) {
    $range_clause = $this->get_range_clause('time', true);
    
    foreach ($user_ids as $value) {
      $query = "SELECT * FROM `%s` WHERE id = '%s'$range_clause";
      
      $res = mysql_board_call($query, $board, $value);
      
      if (!$res) {
        $this->error('Database Error (fpbui0)');
      }
      
      $results = array();
      
      if (!isset($results[$board])) {
        $results[$board] = array();
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $results[$board][$row['no']] = $row;
      }
    }
    
    return $results;
  }
  
  /**
   * 
   */
  private function find_bans_by_ip($ips, $board = null) {
    $results = array();
    
    if (empty($ips)) {
      return $results;
    }
    
    $ips = array_map('mysql_real_escape_string', $ips);
    
    $ips_clause = "'" . implode("','", $ips) . "'";
    
    if ($board) {
      $board_clause = " AND board = '" . mysql_real_escape_string($board) . "'";
    }
    else {
      $board_clause = '';
    }
    
    $range_clause = $this->get_range_clause('now');
    
    $skip_tpl = self::REPORT_TEMPLATE;
    
    $tbl = self::BAN_TBL;
    
    $query = <<<SQL
SELECT no as id, board, post_num, name, tripcode, password, 4pass_id,
host as ip, reverse, reason, post_json, md5, UNIX_TIMESTAMP(now) as time
FROM `$tbl`
WHERE template_id != $skip_tpl AND host IN ($ips_clause)$board_clause$range_clause
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (fbbi0)');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $results[$row['id']] = $row;
    }
    
    return $results;
  }
  
  /**
   * 
   */
  private function find_bans_by_user_hash($passes, $board = null) {
    $results = array();
    
    if (empty($passes)) {
      return $results;
    }
    
    $passes = array_map('mysql_real_escape_string', $passes);
    
    $pass_clause = "'" . implode("','", $passes) . "'";
    
    if ($board) {
      $board_clause = " AND board = '" . mysql_real_escape_string($board) . "'";
    }
    else {
      $board_clause = '';
    }
    
    $range_clause = $this->get_range_clause('now');
    
    $skip_tpl = self::REPORT_TEMPLATE;
    
    $tbl = self::BAN_TBL;
    
    // Double query because of indexing
    // Active bans
    $query = <<<SQL
SELECT no as id, board, post_num, name, tripcode, password, 4pass_id,
host as ip, reverse, reason, post_json, md5, UNIX_TIMESTAMP(now) as time
FROM `$tbl`
WHERE template_id != $skip_tpl AND active = 1 AND 4pass_id IN ($pass_clause)$board_clause$range_clause
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (fbbuh0)');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $results[$row['id']] = $row;
    }
    
    // Inactive bans
    $query = <<<SQL
SELECT no as id, board, post_num, name, tripcode, password, 4pass_id,
host as ip, reverse, reason, post_json, md5, UNIX_TIMESTAMP(now) as time
FROM `$tbl`
WHERE template_id != $skip_tpl AND active = 0 AND 4pass_id IN ($pass_clause)$board_clause$range_clause
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (fbbuh0)');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $results[$row['id']] = $row;
    }
    
    return $results;
  }
  
  /**
   * 
   */
  private function find_bans_by_post($board_pids) {
    $results = array();
    
    $tbl = self::BAN_TBL;
    
    $skip_tpl = self::REPORT_TEMPLATE;
    
    $range_clause = $this->get_range_clause('now');
    
    foreach ($board_pids as $board => $pids) {
      if (empty($pids)) {
        continue;
      }
      
      $pids_clause = implode(',', array_map('intval', $pids));
      
      $query = <<<SQL
SELECT no as id, board, post_num, name, tripcode, password, 4pass_id,
host as ip, reverse, reason, post_json, md5, UNIX_TIMESTAMP(now) as time
FROM `$tbl`
WHERE template_id != $skip_tpl
AND board = '%s' AND post_num IN ($pids_clause)$range_clause
SQL;
      
      $res = mysql_global_call($query, $board);
      
      if (!$res) {
        $this->error('Database Error (fbbp0)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $results[$row['id']] = $row;
      }
    }
    
    return $results;
  }
  
  /**
   * 
   */
  private function find_ncmec_reports($board_posts) {
    $results = array();
    
    $tbl = self::NCMEC_TBL;
    
    $range_clause = $this->get_range_clause('report_sent_timestamp');
    
    foreach ($board_posts as $board => $pids) {
      if (empty($pids)) {
        $results[$board] = array();
        continue;
      }
      
      $pids_clause = implode(',', array_map('intval', $pids));
      
      $query = <<<SQL
SELECT report_ncmec_id as id, board, post_num, post_json FROM `$tbl` as n
WHERE n.board = '%s' AND n.post_num IN($pids_clause)$range_clause
UNION
SELECT report_ncmec_id as id, board, post_num, post_json FROM `{$tbl}_old` as o
WHERE o.board = '%s' AND o.post_num IN($pids_clause)$range_clause
SQL;
      
      $res = mysql_global_call($query, $board, $board);
      
      if (!$res) {
        $this->error('Database Error (fnr0)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $results[$board][$row['post_num']] = $row;
      }
    }
    
    return $results;
  }
  
  /**
   * 
   */
  private function find_deleted_posts($board_posts) {
    $results = array();
    
    $tbl = self::DEL_TBL;
    
    $range_clause = $this->get_range_clause('ts');
    
    foreach ($board_posts as $board => $pids) {
      if (empty($pids)) {
        $results[$board] = array();
        continue;
      }
      
      $pids_clause = implode(',', array_map('intval', $pids));
      
      $query = <<<SQL
SELECT id, postno as no, resto, name, sub, com, UNIX_TIMESTAMP(ts) as time,
cleared, filename
FROM `$tbl`
WHERE board = '%s' AND postno IN($pids_clause)$range_clause
SQL;
      
      $res = mysql_global_call($query, $board);
      
      if (!$res) {
        $this->error('Database Error (fptbpi0)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $results[$board][$row['no']] = $row;
      }
    }
    
    return $results;
  }
  
  /**
   * Includes archived posts
   */
  private function find_posts_by_pid($board_posts) {
    $results = array();
    
    $range_clause = $this->get_range_clause('time', true);
    
    foreach ($board_posts as $board => $pids) {
      if (empty($pids)) {
        $results[$board] = array();
        continue;
      }
      
      $pids_clause = implode(',', array_map('intval', $pids));
      
      $query = <<<SQL
SELECT * FROM `%s`
WHERE no IN($pids_clause)$range_clause
SQL;
      
      $res = mysql_board_call($query, $board);
      
      if (!$res) {
        $this->error('Database Error (fptbpi0)');
      }
      
      if (!isset($results[$board])) {
        $results[$board] = array();
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $results[$board][$row['no']] = $row;
      }
    }
    
    return $results;
  }
  
  /**
   * Find live posts by IP
   */
  private function find_posts_by_ip($board, $ips) {
    if (empty($ips)) {
      return array();
    }
    
    $range_clause = $this->get_range_clause('time', true);
    
    $pids_clause = implode("','", array_map('mysql_real_escape_string', $ips));
    
    $query = <<<SQL
SELECT * FROM `%s`
WHERE host IN('$pids_clause')$range_clause
SQL;
    
    $res = mysql_board_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error (fpbi0)');
    }
    
    if (!mysql_num_rows($res)) {
      return array();
    }
    
    $results = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $results[$row['no']] = $row;
    }
    
    return array($board => $results);
  }
  
  /**
   * Find live posts by 4chan Pass
   */
  private function find_posts_by_user_hash($board, $passes) {
    if (empty($passes)) {
      return array();
    }
    
    $range_clause = $this->get_range_clause('time', true);
    
    $passes_clause = implode("','", array_map('mysql_real_escape_string', $passes));
    
    $query = <<<SQL
SELECT * FROM `%s`
WHERE 4pass_id IN('$passes_clause')$range_clause
SQL;
    
    $res = mysql_board_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error (fpbi0)');
    }
    
    if (!mysql_num_rows($res)) {
      return array();
    }
    
    $results = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $results[$row['no']] = $row;
    }
    
    return array($board => $results);
  }
  
  /**
   * 
   */
  private function find_post_times_by_post_id($board_posts) {
    $results = array();
    
    $range_clause = $this->get_range_clause('time');
    $range_clause_int = $this->get_range_clause('time', true);
    
    $tbl = self::USER_ACT_TBL;
    
    foreach ($board_posts as $board => $pids) {
      if (empty($pids)) {
        $results[$board] = array();
        continue;
      }
      
      $data = array();
      
      foreach ($pids as $pid) {
        // Try user actions
        $query = <<<SQL
SELECT ip, UNIX_TIMESTAMP(time) as time FROM `$tbl`
WHERE (action = 'new_thread' OR action = 'new_reply')
AND board = '%s' AND postno = %d $range_clause
SQL;
        $res = mysql_global_call($query, $board, $pid);
        
        if (!$res) {
          $this->error('Database Error (fptbpi0)');
        }
        
        if (mysql_num_rows($res) < 1) {
          // Try live and archived posts
          $query = <<<SQL
SELECT host as ip, time as time FROM `%s`
WHERE no = %d $range_clause_int
SQL;
          $res = mysql_board_call($query, $board, $pid);
          
          if (!$res) {
            $this->error('Database Error (fptbpi1)');
          }
          
          if (mysql_num_rows($res) > 0) {
            $row = mysql_fetch_assoc($res);
            $data[$pid] = $row;
          }
        }
        else {
          $row = mysql_fetch_assoc($res);
          $row['ip'] = long2ip($row['ip']);
          $data[$pid] = $row;
        }
      }
      
      $results[$board] = $data;
    }
    
    return $results;
  }
  
  /**
   * 
   */
  private function find_post_times_by_ip($ips, $board = null) {
    if (empty($ips)) {
      return array();
    }
    
    $range_clause = $this->get_range_clause('time');
    
    $tbl = self::USER_ACT_TBL;
    
    if ($board) {
      $board_clause = " AND board = '" . mysql_real_escape_string($board) . "'";
    }
    else {
      $board_clause = '';
    }
    
    $ips_clause = implode(',', array_map('ip2long', $ips));
    
    $query = <<<SQL
SELECT ip, UNIX_TIMESTAMP(time) as time, postno as no, board FROM `$tbl`
WHERE ip IN($ips_clause)
AND (action = 'new_thread' OR action = 'new_reply')$board_clause$range_clause
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (fpbi0)');
    }
    
    $results = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $row['ip'] = long2ip($row['ip']);
      
      $board = $row['board'];
      $pid = $row['no'];
      
      unset($row['board']);
      unset($row['no']);
      
      if (!isset($results[$board])) {
        $results[$board] = array($pid => $row);
      }
      else {
        $results[$board][$pid] = $row;
      }
    }
    
    return $results;
  }
  
  private function find_ips_by_post_id($board_pids) {
    $ips = array();
    
    $ip_map = array();
    
    $user_act_tbl = self::USER_ACT_TBL;
    
    $range_clause = $this->get_range_clause('time');
    $range_clause_int = $this->get_range_clause('time', true);
    
    foreach ($board_pids as $board => $post_ids) {
      if (!isset($this->valid_boards[$board])) {
        continue;
      }
      
      foreach ($post_ids as $pid) {
        $query = "SELECT host FROM `%s` WHERE no = %d AND archived = 0$range_clause_int";
        
        $res = mysql_board_call($query, $board, $pid);
        
        if (!$res) {
          $this->error('Database Error (fibpi0)');
        }
        
        // IP found among live posts
        if (mysql_num_rows($res)) {
          $ip = mysql_fetch_row($res)[0];
          
          if (!isset($ip_map[$ip])) {
            $ips[] = $ip;
            $ip_map[$ip] = true;
          }
        }
        // IP not among live posts, check the action log
        else {
          $query = <<<SQL
SELECT ip FROM `$user_act_tbl`
WHERE board = '%s' AND postno = %d 
AND (action = 'new_thread' OR action = 'new_reply')$range_clause
SQL;
          
          $res = mysql_global_call($query, $board, $pid);
          
          if (!$res) {
            $this->error('Database Error (fibpi1)');
          }
          
          if (mysql_num_rows($res)) {
            $ip = long2ip(mysql_fetch_row($res)[0]);
            
            if (!isset($ip_map[$ip])) {
              $ips[] = $ip;
              $ip_map[$ip] = true;
            }
          }
        }
      }
    }
    
    return $ips;
  }
  
  /**
   * 
   */
  private function find_post_times_by_tim($board_tims) {
    $data = array();
    
    $user_act_tbl = self::USER_ACT_TBL;
    
    $range_clause = $this->get_range_clause('time');
    $range_clause_int = $this->get_range_clause('time', true);
    
    foreach ($board_tims as $board => $tims) {
      foreach ($tims as $tim) {
        $query = <<<SQL
SELECT host as ip, time, no
FROM `%s` WHERE tim = '%s'$range_clause_int
SQL;
        
        $res = mysql_board_call($query, $board, $tim);
        
        if (!$res) {
          $this->error('Database Error (fiptbt0)');
        }
        
        // IP found among live posts
        // fixme: multiple entries for a given timestamp
        if (mysql_num_rows($res)) {
          $board_data = array();
          
          while ($row = mysql_fetch_assoc($res)) {
            $pid = $row['no'];
            unset($row['no']);
            $board_data[$pid] = $row;
          }
          
          $data[$board] = $board_data;
        }
        // IP not among live posts, check the user actions table
        // fixme: multiple entries for a given timestamp
        // old tool also checks the actions table for unix timestamps
        // built from tims: substr($tim, 0, strlen($tim)-3);
        else {
          $query = <<<SQL
SELECT ip, UNIX_TIMESTAMP(time) as time, postno as no FROM `$user_act_tbl`
WHERE board = '%s' AND uploaded = %d 
AND (action = 'new_thread' OR action = 'new_reply')$range_clause
SQL;
          
          $res = mysql_global_call($query, $board, $tim);
          
          if (!$res) {
            $this->error('Database Error (fiptbt1)');
          }
          
          if (!mysql_num_rows($res)) {
            continue;
          }
          
          $board_data = array();
          
          while ($row = mysql_fetch_assoc($res)) {
            $row['ip'] = long2ip($row['ip']);
            $pid = $row['no'];
            unset($row['no']);
            $board_data[$pid] = $row;
          }
          
          $data[$board] = $board_data;
        }
      }
    }
    
    return $data;
  }
  
  private function find_previous_reports($params) {
    $tbl = self::REQ_TBL;
    
    $results = array();
    
    foreach ($params as $key => $value) {
      if (!$value) {
        continue;
      }
      
      $query = "SELECT id FROM `$tbl` WHERE report LIKE '%%%s%%'";
      
      if (is_array($value)) {
        $value = implode("\n", $value);
      }
      
      $res = mysql_global_call($query, $value);
      
      if (!$res) {
        continue;
      }
      
      while ($row = mysql_fetch_row($res)) {
        $results[$row[0]] = true;
      }
    }
    
    return array_keys($results);
  }
  
  /**
   * Download attachment
   */
  public function attachment() {
    if (!isset($_GET['id'])) {
      $this->error('Bad Request');
    }
    
    $id = (int)$_GET['id'];
    
    $tbl = self::ATTCH_TBL;
    
    $query = "SELECT filename, data FROM `$tbl` WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error');
    }
    
    if (!mysql_num_rows($res)) {
      $this->error('Attachment not found');
    }
    
    $file = mysql_fetch_assoc($res);
    
    if ($file['filename'] !== '') {
      $filename = $file['filename'];
    }
    else {
      $filename = 'untitled_' . $file['id'];
    }
    
    header('Content-type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $file['data'];
  }
  
  /**
   * View request details
   */
  public function view() {
    if (isset($_GET['id'])) {
      $id = (int)$_GET['id'];
    }
    else {
      $this->error('Bad Request');
    }
    
    $tbl = self::REQ_TBL;
    
    $query = <<<SQL
SELECT id, description, date, requester, requester_email, requester_doc_id,
was_sent, request_type, request_date, date, report,
UNIX_TIMESTAMP(sent_on) as sent_on, email_content, cc_emails
FROM `$tbl`
WHERE id = $id
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (1).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Nothing found.');
    }
    
    $this->report = mysql_fetch_assoc($res);
    
    $tbl = self::ATTCH_TBL;
    
    $query = "SELECT id, filename FROM `$tbl` WHERE request_id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (2).');
    }
    
    $this->attachments = array();
    
    while ($file = mysql_fetch_assoc($res)) {
      $this->attachments[] = $file;
    }
    
    $this->renderHTML('legalrequest-view');
  }
  
  // FIXME
  /*
  public function dump() {
    if (isset($_GET['id'])) {
      $id = (int)$_GET['id'];
    }
    else {
      $this->error('Bad Request');
    }
    
    echo $id;
    
    $tbl = self::REQ_TBL;
    
    $query = <<<SQL
SELECT id, description, date, requester, requester_email, requester_doc_id,
was_sent, request_type, request_date, date, report,
UNIX_TIMESTAMP(sent_on) as sent_on, email_content, cc_emails
FROM `$tbl`
WHERE id = $id
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      echo('Database error (1).');
      return;
    }
    
    if (mysql_num_rows($res) < 1) {
      echo('Nothing found.');
      return;
    }
    
    $this->report = mysql_fetch_assoc($res);
    
    $tbl = self::ATTCH_TBL;
    
    $query = "SELECT id, filename, data FROM `$tbl` WHERE request_id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      echo('Database error (2).');
      return;
    }
    
    $out_dir = "/home/dsw/reqs/$id";
    
    if (file_exists($out_dir)) {
      echo " [EXISTS]";
      return;
    }
    else {
      mkdir($out_dir);
    }
    
    $this->attachments = array();
    
    $t = Transliterator::create("Any-Latin; nfd; [:nonspacing mark:] remove; nfkc; Latin-ASCII");
    
    while ($file = mysql_fetch_assoc($res)) {
      $this->attachments[] = $file;
      $filename = preg_replace('/\.\.+/', '_', $file['filename']);
      
      $filename = $t->transliterate($filename);
      $filename = preg_replace('/[^@\[\(\)\]\-\+_a-z0-9.]/i', '_', $filename);
      
      if (file_put_contents("$out_dir/$filename", $file['data']) === false) {
        echo " !!! Error writing attachment: $filename";
      }
      else {
        echo " *** $filename";
      }
    }
    
    ob_start();
    $this->renderHTML('legalrequest-dump');
    $html = ob_get_contents();
    ob_end_clean();
    
    if (file_put_contents("$out_dir/legal_request_$id.html", $html) === false) {
      echo " !!! Error writing html";
    }
    else {
      echo " [OK]";
    }
  }
  */
  /**
   * View unformatted report
   */
  public function view_raw() {
    if (isset($_GET['id'])) {
      $id = (int)$_GET['id'];
    }
    else {
      $this->error('Bad Request');
    }
    
    $tbl = self::REQ_TBL;
    
    $query = "SELECT id, raw_info, request_date, date FROM `$tbl` WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Nothing found.');
    }
    
    $this->raw_report = mysql_fetch_assoc($res);
    
    $this->renderHTML('legalrequest-view');
  }
  
  /**
   * Find old reports
   */
  public function find_old_reports($clauses) {
    $data = array();
    
    $esc = array();
    
    foreach ($clauses as $c) {
      $esc[] = str_replace(array('', '_'), array("\%", "\_"), $c);
    }
    
    $regex = "[ /](" . implode('|', $esc) . ")(,|\s|$)";
    
    $sql = "SELECT id from preserved_information WHERE description REGEXP '%s'";
    
    $res = mysql_global_call($sql, $regex);
    
    if ($res) {
      while ($row = mysql_fetch_row($res)) {
        $data[] = $row[0];
      }
    }
    
    return $data;
  }
  
  /**
   * Search content before creating a request
   */
  public function search() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->renderHTML('legalrequest-search');
      return;
    }
    
    if (!isset($_POST['post_ids']) && !isset($_POST['ips']) && !isset($_POST['user_ids'])) {
      die('params');
    }
    
    $this->parse_time_ranges();
    
    $board = null;
    $board_pids = null;
    $ips = null;
    $user_ids = null;
    $user_names = null;
    $passes_param = null;
    $img_urls = null;
    
    $old_report_clauses = array();
    
    if (!isset($_POST['type']) || !isset($this->valid_types[$_POST['type']])) {
      $this->error('Invalid request type');
    }
    
    $req_type = $_POST['type'];
    
    $is_em_disc = $req_type === self::TYPE_EM_DISC;
    
    if (!$is_em_disc && isset($_POST['board']) && $_POST['board'] !== '') {
      // todo: validate board here
      $board = mysql_real_escape_string($_POST['board']);
    }
    
    if (isset($_POST['post_uids']) && $_POST['post_uids'] !== '') {
      $board_pids = $this->board_pids_from_post_uids($_POST['post_uids']);
      
      foreach ($board_pids as $_b => $_p) {
        $old_report_clauses = array_merge($old_report_clauses, $_p);
      }
    }
    
    if (!$is_em_disc && isset($_POST['ips']) && $_POST['ips'] !== '') {
      $ips = preg_split('/[\r\n]+/', $_POST['ips']);
      $ips = array_map('trim', $ips);
      $old_report_clauses = array_merge($old_report_clauses, $ips);
    }
    
    if (!$is_em_disc && isset($_POST['user_ids']) && $_POST['user_ids'] !== '') {
      $user_ids = preg_split('/[\r\n]+/', $_POST['user_ids']);
      $user_ids = array_map('trim', $user_ids);
      $old_report_clauses = array_merge($old_report_clauses, $user_ids);
    }
    
    if (!$is_em_disc && isset($_POST['user_names']) && $_POST['user_names'] !== '') {
      $user_names = preg_split('/[\r\n]+/', $_POST['user_names']);
      $user_names = array_map('trim', $user_names);
      $old_report_clauses = array_merge($old_report_clauses, $user_names);
    }
    
    if (!$is_em_disc && isset($_POST['passes_param']) && $_POST['passes_param'] !== '') {
      $passes_param = preg_split('/[\r\n]+/', $_POST['passes_param']);
      $passes_param = array_map('trim', $passes_param);
      $old_report_clauses = array_merge($old_report_clauses, $passes_param);
    }
    
    if (!$is_em_disc && isset($_POST['img_urls']) && $_POST['img_urls'] !== '') {
      $img_urls = preg_split('/[\r\n]+/', $_POST['img_urls']);
      $img_urls = array_map('trim', $img_urls);
      $old_report_clauses = array_merge($old_report_clauses, $img_urls);
    }
    
    // ---
    
    $all_ips = array();
    $all_times = array();
    $all_posts = array();
    $deletions = array();
    $all_bans = array();
    $passes = array();
    $ncmec_reports = array();
    $this->old_report_ids = array();
    
    if ($board_pids) {
      $this->log('Searching ips by post uid');
      $data = $this->find_ips_by_post_id($board_pids);
      $all_ips = array_merge($all_ips, $data);
      
      $this->log('Searching post times by post uid');
      $data = $this->find_post_times_by_post_id($board_pids);
      $all_times = $this->merge_post_times($all_times, $data);
      
      $this->log('Searching deletions by post uid');
      $data = $this->find_deleted_posts($board_pids);
      $deletions = array_merge($deletions, $data);
      
      $this->log('Searching NCMEC reports by post uid');
      $ncmec_reports = $this->find_ncmec_reports($board_pids);
      
      $this->log('Searching bans by post uid');
      $all_bans = $all_bans + $this->find_bans_by_post($board_pids);
      
      $this->log('Searching posts by post uid');
      $data = $this->find_posts_by_pid($board_pids);
      $all_posts = array_merge($all_posts, $data);
    }
    
    if ($ips) {
      $this->log('Searching post times by ip');
      $data = $this->find_post_times_by_ip($ips, $board);
      $all_times = $this->merge_post_times($all_times, $data);
      
      if ($req_type === self::TYPE_SUBPOENA || $req_type === self::TYPE_S_WARRANT) {
        $this->log('Searching 4chan Passes by ip');
        $passes = $this->find_passes_by_ip($ips);
      }
      
      $this->log('Searching bans by ip');
      $all_bans = $all_bans + $this->find_bans_by_ip($ips, $board);
      
      if ($board) {
        $this->log('Searching live posts by ip on ' . $board);
        $data = $this->find_posts_by_ip($board, $ips);
        $all_posts = array_merge($all_posts, $data);
      }
      else {
        $this->log('Searching live posts by ip on every board');
        foreach ($this->valid_boards as $b => $_) {
          $data = $this->find_posts_by_ip($b, $ips);
          $all_posts = array_merge($all_posts, $data);
        }
      }
    }
    
    if ($user_ids) {
      if ($board) {
        $this->log('Searching posts by user id on ' . $board);
        $data = $this->find_posts_by_user_id($board, $user_ids);
        $all_posts = array_merge($all_posts, $data);
      }
      else {
        $this->log('Searching posts by user id on every board');
        foreach ($this->valid_boards as $b => $_) {
          $data = $this->find_posts_by_user_id($b, $user_ids);
          $all_posts = array_merge($all_posts, $data);
        }
      }
    }
    
    if ($user_names) {
      $nametrips = $this->nametrips_from_user_names($user_names);
      
      if ($board) {
        $this->log('Searching posts by nametrip on ' . $board);
        
        if (strpos($board, ',') !== false) {
          $_boards = preg_split('/[^_a-z0-9]+/i', $board);
        
          foreach ($_boards as $b) {
            $data = $this->find_posts_by_nametrips($b, $nametrips);
            $all_posts = array_merge($all_posts, $data);
          }
        }
        else {
          $data = $this->find_posts_by_nametrips($board, $nametrips);
          $all_posts = array_merge($all_posts, $data);
        }
      }
      else {
        $this->log('Searching posts by nametrip on every board');
        foreach ($this->valid_boards as $b => $_) {
          $data = $this->find_posts_by_nametrips($b, $nametrips);
          $all_posts = array_merge($all_posts, $data);
        }
      }
      
      $this->log('Searching bans by nametrip');
      $all_bans = $all_bans + $this->find_bans_by_nametrips($nametrips, $board);
      
      $this->log('Searching deleted posts by nametrip');
      $data = $this->find_deletions_by_nametrips($nametrips, $board);
      $deletions = array_merge($deletions, $data);
    }
    
    if ($passes_param) {
      if ($board) {
        $this->log('Searching posts by 4chan Pass on ' . $board);
        $data = $this->find_posts_by_user_hash($board, $passes_param);
        $all_posts = array_merge($all_posts, $data);
      }
      else {
        $this->log('Searching posts by 4chan Pass on every board');
        foreach ($this->valid_boards as $b => $_) {
          $data = $this->find_posts_by_user_hash($b, $passes_param);
          $all_posts = array_merge($all_posts, $data);
        }
      }
      
      $this->log('Searching bans by 4chan Pass');
      $all_bans = $all_bans + $this->find_bans_by_user_hash($passes_param, $board);
    }
    
    if ($img_urls) {
      $board_tims = $this->board_tims_from_img_urls($img_urls);
      
      $this->log('Searching post times by image url');
      $data = $this->find_post_times_by_tim($board_tims);
      $all_times = $this->merge_post_times($all_times, $data);
      
      $this->log('Searching posts by pid from image url times');
      
      $tim_board_pids = array();
      
      foreach ($data as $tim_board => $tim_pids) {
        $tim_board_pids[$tim_board] = array_keys($tim_pids);
      }
      
      $data = $this->find_posts_by_pid($tim_board_pids);
      $all_posts = array_merge($all_posts, $data);
    }
    
    if ($old_report_clauses) {
      $this->old_report_ids = $this->find_old_reports($old_report_clauses);
    }
    
    // Find emails from passes used in posts
    if ($req_type === self::TYPE_SUBPOENA || $req_type === self::TYPE_S_WARRANT) {
      $user_hashes = $this->collect_user_hashes($all_posts, $all_bans);
      
      if ($passes_param) {
        $user_hashes = array_merge($user_hashes, $passes_param);
      }
      
      $data = $this->find_passes_by_user_hash($user_hashes);
      $passes = array_merge($passes, $data);
    }
    
    /**
     * Formatting the report
     */
    $report = '';
    
    $header_boards = array();
    
    if ($board_pids) {
      foreach ($board_pids as $_board => $_pids) {
        foreach ($_pids as $_pid) {
          $header_boards[] = "/$_board/$_pid";
        }
      }
    }
    
    $header_params = array(
      'Board' => $board,
      'Post Numbers' => $header_boards,
      'IPs' => $ips,
      'User IDs' => $user_ids,
      'User names' => $user_names,
      '4chan Passes' => $passes_param,
      'File URLs' => $img_urls,
      'Date range' => $this->get_formatted_date_range()
    );
    
    $previous_reports = $this->find_previous_reports($header_params);
    
    if ($previous_reports) {
      $this->previous_reports = $this->report_start_section(
        'Related previous report numbers',
        count($previous_reports)
      );
      $this->previous_reports .= '#' . implode(", #", $previous_reports);
      $this->previous_reports .= $this->report_end_section();
    }
    else {
      $this->previous_reports = '';
    }
    
    $this->description = $this->format_description($header_params);
    
    $report .= $this->report_format_header($req_type, $header_params);
    
    // Emergency disclosures only need the IP
    if ($req_type == self::TYPE_EM_DISC) {
      $txt = $this->report_format_emergency_req($all_times, $all_bans, $count);
      $report .= $this->report_start_section('Post times', $count);
      $report .= $txt;
      $this->req_type = $req_type;
      $this->report = $report;
      
      $lines = array();
      
      foreach ($deletions as $board => $posts) {
        foreach ($posts as $pid => $post) {
          $lines[$board.'-'.$pid] = $this->report_format_deletion($board, $post);
        }
      }
      foreach ($all_bans as $ban_id => $ban) {
        if (!$ban['board']) {
          continue;
        }
        $lines[$ban['board'].'-'.$ban['post_num']] = $this->report_format_ban($ban);
      }
      foreach ($all_posts as $board => $posts) {
        foreach ($posts as $pid => $post) {
          $lines[$board.'-'.$pid] = $this->report_format_post($board, $post);
        }
      }
      
      if ($lines) {
        $this->post_contents = implode("\n\n", array_values($lines));
      }
      else {
        $this->post_contents = null;
      }
      
      $this->renderHTML('legalrequest-results');
      return;
    }
    
    // All IPs
    if (!empty($all_ips)) {
      $report .= $this->report_start_section('Associated IPs', count($all_ips));
      $report .= implode("\n", $all_ips);
      $report .= $this->report_end_section();
    }
    
    // All post times
    if (!empty($all_times)) {
      $lines = array();
      foreach ($all_times as $board => $times) {
        foreach ($times as $pid => $post) {
          $lines[] = $this->report_format_post_time($board, $pid, $post);
        }
      }
      if ($lines) {
        $report .= $this->report_start_section('Post times', count($lines));
        $report .= implode("\n", $lines);
        $report .= $this->report_end_section();
      }
    }
    
    // All posts
    if ($req_type !== self::TYPE_SUBPOENA && !empty($all_posts)) {
      $lines = array();
      foreach ($all_posts as $board => $posts) {
        foreach ($posts as $pid => $post) {
          $lines[] = $this->report_format_post($board, $post);
        }
      }
      if ($lines) {
        $report .= $this->report_start_section('Found posts', count($lines));
        $report .= implode("\n\n", $lines);
        $report .= $this->report_end_section();
      }
    }
    
    // Deletions
    if (!empty($deletions)) {
      $lines = array();
      foreach ($deletions as $board => $posts) {
        foreach ($posts as $pid => $post) {
          $lines[] = $this->report_format_deletion($board, $post);
        }
      }
      $report .= $this->report_start_section('Deletion records', count($lines));
      $report .= implode("\n\n", $lines);
      $report .= $this->report_end_section();
    }
    
    // Bans
    if (!empty($all_bans)) {
      $lines = array();
      foreach ($all_bans as $ban_id => $ban) {
        $lines[] = $this->report_format_ban($ban);
      }
      $report .= $this->report_start_section('Ban records', count($lines));
      $report .= implode("\n\n", $lines);
      $report .= $this->report_end_section();
    }
    
    // Passes
    if (!empty($passes)) {
      $lines = array();
      foreach ($passes as $pass) {
        $lines[] = $this->report_format_pass($pass);
      }
      $report .= $this->report_start_section('4chan Passes', count($lines));
      $report .= implode("\n\n", $lines);
      $report .= $this->report_end_section();
    }
    
    // NCMEC reports
    if (!empty($ncmec_reports)) {
      $lines = array();
      foreach ($ncmec_reports as $board => $ncmecs) {
        foreach ($ncmecs as $pid => $ncmec) {
          $lines[] = $this->report_format_ncmec($ncmec);
        }
      }
      $report .= $this->report_start_section('Post records sent to NCMEC', count($lines));
      $report .= implode("\n\n", $lines);
      $report .= $this->report_end_section();
    }
    
    // ---
    
    $this->req_type = $req_type;
    
    $this->report = $report;
    
    $this->renderHTML('legalrequest-results');
  }
  
  private function report_format_header($req_type, $params) {
    $txt = "This report was generated using the following inputs:\n";
    $txt .= "=====================================================\n";
    
    foreach ($params as $key => $value) {
      if (!$value) {
        continue;
      }
      
      $txt .= "$key:\n"
        . (is_array($value) ? implode("\n", $value) : $value) . "\n\n";
    }
    
    return $txt;
  }
  
  private function report_format_post_time($board, $pid, $post) {
    $datetime = date(self::DATE_FORMAT, $post['time']);
    return "/$board/$pid was posted by {$post['ip']} on {$datetime}.";
  }
  
  private function report_format_emergency_req($all_times, $all_bans, &$count) {
    $lines = array();
    
    foreach ($all_times as $board => $times) {
      foreach ($times as $pid => $post) {
        $lines[] = $this->report_format_post_time($board, $pid, $post);
      }
    }
    
    if (!empty($all_bans)) {
      foreach ($all_bans as $ban_id => $ban) {
        if (!$ban['board'] || !$ban['post_json']) {
          continue;
        }
        
        $board = $ban['board'];
        
        $post = json_decode($ban['post_json'], true);
        
        if (!$post['host']) {
          continue;
        }
        
        $pid = $post['no'];
        
        if (isset($all_times[$board]) && isset($all_times[$board][$pid])) {
          continue;
        }
        
        $post['ip'] = $post['host'];
        
        $lines[] = $this->report_format_post_time($board, $pid, $post);
      }
    }
    
    $count = count($lines);
    
    return implode("\n", $lines);
  }
  
  private function report_format_ncmec($ncmec) {
    $lines = array();
    
    $post = json_decode($ncmec['post_json'], true);
    
    $lines[] = "Post #{$post['no']}";
    $lines[] = "---------------------";
    $lines[] = $this->report_format_post($ncmec['board'], $post);
    $lines[] = "---------------------";
    
    return implode("\n", $lines);
  }
  
  private function report_format_pass($pass) {
    $datetime = date(self::DATE_FORMAT, $pass['last_used']);
    
    $lines = array();
    
    $lines[] = "4chan Pass: {$pass['user_hash']}";
    
    $datetime = date(self::DATE_FORMAT, $pass['purchase_date']);
    $lines[] = "Purchased on: {$datetime}";
    
    if (isset($pass['customer_id'])) {
      $lines[] = "Registration IP: {$pass['registration_ip']}";
      $lines[] = "Transaction ID: {$pass['transaction_id']}";
      $lines[] = "Customer ID: {$pass['customer_id']}";
    }
    
    $lines[] = "Last known IP: {$pass['ip']}";
    $lines[] = "Last used on: $datetime";
    $lines[] = "E-mail: {$pass['user_email']}";
    
    return implode("\n", $lines);
  }
  
  private function report_format_ban($ban) {
    $ban_datetime = date(self::DATE_FORMAT, $ban['time']);
    
    $lines = array();
    
    $lines[] = "Ban #{$ban['id']}";
    $lines[] = "---------------------";
    
    $reasons = explode('<>', $ban['reason'], 2);
    
    $lines[] = "Banned IP: {$ban['ip']} (Hostname: {$ban['reverse']})";
    $lines[] = "Banned on: $ban_datetime";
    $lines[] = "Public ban reason: {$reasons[0]}";
    
    if (isset($reasons[1])) {
      $lines[] = "Private ban reason: {$reasons[1]}";
    }
    
    if ($ban['post_json'] !== '') {
      $post = json_decode($ban['post_json'], true);
      
      if (!is_array($post)) {
        $post = null;
      }
    }
    else {
      $post = null;
    }
    
    if ($post) {
      $post['pwd'] = $ban['password'];
      $post['4pass_id'] = $ban['4pass_id'];
      unset($post['host']);
      $lines[] = $this->report_format_post($ban['board'], $post);
    }
    else {
      if ($ban['post_num']) {
        $lines[] = "Post No.: {$ban['post_num']}";
      }
      
      if ($ban['name'] !== '') {
        $lines[] = "Name: {$ban['name']}";
      }
      
      if ($ban['tripcode'] !== '') {
        $lines[] = "Tripcode: {$ban['tripcode']}";
      }
      
      if ($ban['password'] !== '') {
        $lines[] = "Deletion password: {$ban['password']}";
      }
      
      if ($ban['4pass_id'] !== '') {
        $lines[] = "4chan Pass: {$ban['4pass_id']}";
      }
      
      if ($ban['md5'] !== '') {
        $lines[] = "File MD5: {$ban['md5']}";
      }
    }
    
    $lines[] = "---------------------";
    
    return implode("\n", $lines);
  }
  
  private function report_format_deletion($board, $post) {
    $datetime = date(self::DATE_FORMAT, $post['time']);
    
    $lines = array();
    
    $lines[] = "Deletion #{$post['id']}";
    $lines[] = "---------------------";
    
    list($name, $tripcode) = $this->format_name($post['name']);
    
    list($sub, $spoiler) = $this->format_subject($post['sub']);
    
    if ($post['cleared']) {
      $lines[] = "Cleared: this entry doesn't correspond to a deletion.";
      $lines[] = "Cleared on: $datetime";
    }
    else {
      $lines[] = "Deleted on: $datetime";
    }
    
    $lines[] = "Board: /$board/";
    $lines[] = "Post No.: {$post['no']}";
    
    if ($post['resto']) {
      $lines[] = "Reply to thread No.: {$post['resto']}";
    }
    
    $lines[] = "Name: $name";
    
    if ($tripcode) {
      $lines[] = "Tripcode: $tripcode";
    }
    
    if ($sub !== null) {
      $lines[] = "Subject: $sub";
    }
    
    if ($spoiler) {
      $lines[] = "Image spoiler: yes";
    }
    
    if ($post['filename'] !== '') {
      $lines[] = "Original filename: {$post['filename']}";
    }
    
    if ($post['com'] !== '') {
      $lines[] = "Comment:\n{$post['com']}";
    }
    
    $lines[] = "---------------------";
    
    return implode("\n", $lines);
  }
  
  private function report_format_post($board, $post) {
    $datetime = date(self::DATE_FORMAT, $post['time']);
    
    $lines = array();
    
    list($name, $tripcode) = $this->format_name($post['name']);
    
    if (isset($post['sub']) && $post['sub'] !== '') {
      list($sub, $spoiler) = $this->format_subject($post['sub']);
    }
    else {
      $sub = $spoiler = null;
    }
    
    $lines[] = "Board: /$board/";
    $lines[] = "Post No.: {$post['no']}";
    $lines[] = "Posted on: {$datetime}";
    
    if ($post['resto']) {
      $lines[] = "Reply to thread No.: {$post['resto']}";
    }
    
    $lines[] = "Name: $name";
    
    if ($tripcode) {
      $lines[] = "Tripcode: $tripcode";
    }
    
    if (isset($post['host']) && $post['host'] !== '') {
      $lines[] = "IP: {$post['host']}";
    }
    
    if (isset($post['pwd']) && $post['pwd'] !== '') {
      $lines[] = "Deletion password: {$post['pwd']}";
    }
    
    if (isset($post['4pass_id']) && $post['4pass_id'] !== '') {
      $lines[] = "4chan Pass: {$post['4pass_id']}";
    }
    
    if ($sub !== null) {
      $lines[] = "Subject: $sub";
    }
    
    if ($spoiler) {
      $lines[] = "Image spoiler: yes";
    }
    
    if (isset($post['ext']) && $post['ext'] !== '') {
      $lines[] = "Original filename: {$post['filename']}{$post['ext']}";
      $lines[] = "File size: {$post['fsize']} bytes";
      $lines[] = "File dimensions: {$post['w']}x{$post['h']} pixels";
      
      if (isset($post['md5'])) {
        $lines[] = "File MD5: {$post['md5']}";
      }
    }
    
    if ($post['com'] !== '') {
      $lines[] = "Comment:\n{$post['com']}";
    }
    
    return implode("\n", $lines);
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
   * Splits the name and returns array($name, $tripcode or null)
   */
  private function format_name($name) {
    $name = str_replace('&#039;', "'", $name);
    
    if (strpos($name, '#')) {
      return explode('#', $name);
    }
    
    if (strpos($name, '<span ')) {
      $parts = explode('</span> <span class="postertrip">', $name);
      
      if ($parts[1]) {
        $parts[1] = str_replace('!!', '!', $parts[1]);
      }
      
      return $parts;
    }
    
    return array($name, null);
  }
  
  /**
   * Header to append to reports before sending/displaying
   */
  private function format_report_meta($r, $escape_html = false) {
    $lines = array();
    
    $lines[] = "Report #{$r['id']} generated on {$r['date']} " . date('T', $r['date_ts']) . ".";
    
    if ($r['requester'] !== '') {
      $line = "Requested by {$r['requester']}"
        . ($r['requester_email'] !== '' ? " <{$r['requester_email']}>" : '') . ".";
      
      if ($escape_html) {
        $line = htmlspecialchars($line, ENT_QUOTES);
      }
      
      $lines[] = $line;
    }
    
    if ($r['requester_doc_id'] !== '') {
      $lines[] = "Case ID: {$r['requester_doc_id']}.";
    }
    
    $req_type = $this->label_to_type[$r['request_type']];
    
    if ($req_type === self::TYPE_EM_DISC) {
      $lines[] = 'Records provided pursuant to an emergency disclosure request '
        . '(18 U.S.C. § 2702(b)(8) and § 2702(c)(4)).';
    }
    else if ($req_type === self::TYPE_PR_ORDER) {
      $lines[] = 'Records preserved pursuant to a preservation order '
        . '(18 U.S.C. § 2703(f)).';
    }
    else {
      $lines[] = 'Records provided pursuant to a '
        . strtolower($r['request_type']) . ".";
    }
    
    $lines[] = "All records made and kept in the regular course of business.";
    
    return implode("\n", $lines) . "\n\n";
  }
  
  private function format_description($params) {
    $items = array();
    
    foreach ($params as $key => $value) {
      if (!$value) {
        continue;
      }
      
      if (is_array($value)) {
        $value = implode(', ', $value);
      }
      
      $items[] = "$key: $value";
    }
    
    return implode("\n", $items);
  }
  
  private function get_formatted_date_range() {
    $parts = array();
    
    if ($this->ts_range['start']) {
      $parts[] = "from " . date(self::DATE_FORMAT, $this->ts_range['start']);
    }
    
    if ($this->ts_range['end']) {
      $parts[] = "until " . date(self::DATE_FORMAT, $this->ts_range['end']);
    }
    
    if (!$parts) {
      return null;
    }
    
    return implode(' ', $parts);
  }
  
  /**
   * Save report
   * Stored raw. No html escaping.
   */
  public function save() {
    // Requester name
    if (!isset($_POST['req_name']) || $_POST['req_name'] === '') {
      $this->error('Requester name cannot be empty');
    }
    
    $req_name = trim($_POST['req_name']);
    
    // Requester email
    if (!isset($_POST['req_email']) || $_POST['req_email'] === '') {
      $this->error('Requester email cannot be empty');
    }
    
    $req_email = strtolower(trim($_POST['req_email']));
    
    // Cc emails
    if (isset($_POST['req_cc_emails']) && $_POST['req_cc_emails'] !== '') {
      $req_cc_emails = $_POST['req_cc_emails'];
    }
    else {
      $req_cc_emails = '';
    }
    
    // Description
    if (isset($_POST['description']) && $_POST['description'] !== '') {
      $description = $_POST['description'];
    }
    else {
      $description = '';
    }
    
    // Request date
    if (!isset($_POST['req_date']) || $_POST['req_date'] === '') {
      $this->error('Request date cannot be empty');
    }
    
    if (!preg_match('/^\d\d\/\d\d\/\d\d\d\d$/', $_POST['req_date'])) {
      $this->error('Invalid date');
    }
    
    $req_date = explode('/', $_POST['req_date']);
    $req_date = $req_date[2] . '-' . $req_date[0] . '-' . $req_date[1];
    
    // Copy of email
    if (!isset($_POST['email_content']) || $_POST['email_content'] === '') {
      $this->error('Copy of E-mail cannot be empty');
    }
    
    $email_content = $_POST['email_content'];
    
    // Request type and label
    if (!isset($_POST['req_type']) || $_POST['req_type'] === '') {
      $this->error('Request type cannot be empty');
    }
    
    $req_type = trim($_POST['req_type']);
    
    if (!isset($this->valid_types[$req_type])) {
      $this->error('Invalid request type');
    }
    
    $req_label = $this->valid_types[$req_type];
    
    // Raw report
    if (!isset($_POST['raw_report']) || $_POST['raw_report'] === '') {
      $this->error('Raw report cannot be empty');
    }
    
    $raw_report = $_POST['raw_report'];
    
    // Report
    if (!isset($_POST['report']) || $_POST['report'] === '') {
      $this->error('Report cannot be empty');
    }
    
    $report = $_POST['report'];
    
    // Doc ID
    if (isset($_POST['doc_id']) && $_POST['doc_id'] !== '') {
      $doc_id = trim($_POST['doc_id']);
    }
    else {
      $doc_id = '';
    }
    
    // Attached files
    $attachments = array();
    
    if (isset($_FILES['doc_file']) && is_array($_FILES['doc_file'])) {
      $files = $_FILES['doc_file'];
      
      $count = count($files['tmp_name']);
      
      for ($i = 0; $i < $count; ++$i) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
          continue;
        }
        
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
          $this->error('Upload failed.');
        }
        
        if (!is_uploaded_file($files['tmp_name'][$i])) {
          $this->error('Internal Server Error (0)');
        }
        
        $attachments[] = array(
          'filename' => basename($files['name'][$i]),
          'data' => file_get_contents($files['tmp_name'][$i])
        );
      }
    }
    
    // Insert request
    $tbl = self::REQ_TBL;
    
    $query = <<<SQL
INSERT INTO `$tbl` (request_type, requester, requester_email, requester_doc_id,
raw_info, report, cc_emails, email_content, request_date, description)
VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
SQL;
    
    $res = mysql_global_call($query,
      $req_label, $req_name, $req_email,
      $doc_id, $raw_report, $report, $req_cc_emails, $email_content,
      $req_date, $description
    );
    
    if (!$res) {
      $this->error('Database error');
    }
    
    $request_id = mysql_global_insert_id();
    
    if (!$request_id) {
      $this->error('Database Error (req_id)');
    }
    
    // Insert attachments
    $tbl = self::ATTCH_TBL;
    
    foreach ($attachments as $file) {
      $query = <<<SQL
INSERT INTO `$tbl` (request_id, filename, data)
VALUES (%d, '%s', '%s')
SQL;
      
      $res = mysql_global_call($query,
        $request_id, $file['filename'], $file['data']
      );
      
      if (!$res) {
        $this->error('Database error. Attachment not saved.');
      }
    }
    
    $this->success('?action=view&amp;id=' . $request_id);
  }
  
  /**
   * Preview email before sending
   */
  public function email() {
    if (!isset($_GET['id'])) {
      $this->error('Bad Request');
    }
    
    $id = (int)$_GET['id'];
    
    // Grab the report
    $tbl = self::REQ_TBL;
    
    $query = <<<SQL
SELECT *, UNIX_TIMESTAMP(request_date) as request_ts,
UNIX_TIMESTAMP(date) as date_ts
FROM $tbl WHERE id = %d
SQL;
    
    $res = mysql_global_call($query, $id);
    
    if (!$res) {
      $this->error('Database Error (0)');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Report not found.');
    }
    
    $report = mysql_fetch_assoc($res);
    
    $report['report'] = $this->format_report_meta($report) . $report['report'];
    
    $req_type = $this->label_to_type[$report['request_type']];
    
    $email = $report['requester_email'];
    
    $cc_emails = $report['cc_emails'];
    
    $values = array(
      '{{REPORT_ID}}' => $report['id'],
      '{{REQUESTER}}' => $report['requester']
    );
    
    if ($report['request_date'] !== '0000-00-00') {
      $values['{{REQUEST_DATE}}'] = date(self::DATE_FORMAT_SHORT, $report['request_ts']);
    }
    else {
      $values['{{REQUEST_DATE}}'] = date(self::DATE_FORMAT_SHORT, $report['date_ts']);
    }
    
    if ($report['requester_doc_id'] !== '') {
      $values['{{REQUESTER_DOC_ID}}'] = " (Case ID: {$report['requester_doc_id']})";
    }
    else {
      $values['{{REQUESTER_DOC_ID}}'] = '';
    }
    
    // Preservation Order. Send only a confirmation.
    if ($req_type === self::TYPE_PR_ORDER) {
      $this->attachment = false;
      $mail_file = self::EMAIL_PR_ORDER;
    }
    else {
      $this->attachment = $report['report'];
      
      // Subpoena
      if ($req_type === self::TYPE_SUBPOENA) {
        $mail_file = self::EMAIL_SUBPOENA;
      }
      // Search Warrant
      else if ($req_type === self::TYPE_S_WARRANT) {
        $mail_file = self::EMAIL_S_WARRANT;
      }
      // Emergency Disclosure
      else if ($req_type === self::TYPE_EM_DISC) {
        $mail_file = self::EMAIL_EM_DISC;
      }
      else {
        $this->error('Invalid request type');
      }
    }
    
    list($subject, $message) = $this->get_email_content($mail_file);
    
    $this->subject = str_replace(array_keys($values), array_values($values), $subject);
    $this->message = str_replace(array_keys($values), array_values($values), $message);
    
    if ($this->attachment) {
      $this->filename = $this->format_filename($report['id'], $report['requester_doc_id']);
    }
    
    $this->report = $report;
    
    $this->renderHTML('legalrequest-email');
  }
  
  /**
   * Send report by email
   */
  public function send() {
    if (!isset($_POST['id'])) {
      $this->error('Bad Request');
    }
    
    if (!isset($_POST['subject']) || $_POST['subject'] === '') {
      $this->error('Subject can not be empty.');
    }
    
    if (!isset($_POST['message']) || $_POST['message'] === '') {
      $this->error('Message can not be empty.');
    }
    
    $id = (int)$_POST['id'];
    
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Grab the report
    $tbl = self::REQ_TBL;
    
    $query = <<<SQL
SELECT *, UNIX_TIMESTAMP(request_date) as request_ts,
UNIX_TIMESTAMP(date) as date_ts
FROM $tbl WHERE id = %d
SQL;
    
    $res = mysql_global_call($query, $id);
    
    if (!$res) {
      $this->error('Database Error (0)');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Report not found.');
    }
    
    $report = mysql_fetch_assoc($res);
    
    $report['report'] = $this->format_report_meta($report) . $report['report'];
    
    $req_type = $this->label_to_type[$report['request_type']];
    
    $email = $report['requester_email'];
    
    $cc_emails = $report['cc_emails'];
    
    // Preservation Order. Send only a confirmation.
    if ($req_type === self::TYPE_PR_ORDER) {
      $ret = $this->send_text_report($email, $subject, $message, $cc_emails);
    }
    else {
      $attachment = $report['report'];
      
      $filename = $this->format_filename($report['id'], $report['requester_doc_id']);
      
      $ret = $this->send_attached_report(
        $email, $subject, $message, $filename, $attachment, $cc_emails
      );
    }
    
    if (!$ret) {
      $this->error('E-mail rejected');
    }
    
    $query = <<<SQL
UPDATE `$tbl` SET was_sent = 1, sent_on = NOW() WHERE id = $id
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error("Couldn't update request's status (the report was sent succesfully");
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Default page
   */
  public function index() {
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    if (isset($_GET['q']) && $_GET['q'] !== '') {
      $q = $_GET['q'];
      $this->search_query = htmlspecialchars($q, ENT_QUOTES);
      $this->search_qs = 'q=' . $this->search_query . '&amp;';
      
      $int_q = (int)$q;
      $sql_q = mysql_real_escape_string($q);
      
      $clause = <<<SQL
WHERE description LIKE '%$sql_q%'
OR requester LIKE '%$sql_q%'
OR requester_email LIKE '%$sql_q%'
OR id = $int_q
SQL;
    
    }
    else {
      $q = null;
      $clause = $this->search_qs = $this->search_query = '';
    }
    
    $tbl = self::REQ_TBL;
    
    $lim = self::PAGE_SIZE + 1;
    
    // Not used?
    $date_fmt = self::DATE_FORMAT_SQL;
    
    $query = <<<SQL
SELECT id, description, date, requester, requester_doc_id, request_type,
was_sent, requester_email
FROM `$tbl` $clause
ORDER BY id DESC
LIMIT $offset,$lim
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error.');
    }
    
    // ---
    
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
    
    // ---
    
    $this->renderHTML('legalrequest');
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
