<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';
require_once 'lib/csp.php';

define('IN_APP', true);

auth_user();

if (!has_level('mod')) {
  APP::denied();
}

require_once 'lib/csp.php';
/*
if (has_flag('developer')) {
  //$mysql_suppress_err = false;
  //ini_set('display_errors', 1);
  //error_reporting(E_ALL);
}
else {
  die('503');
}
*/
require_once 'lib/ini.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'recent_images',
      'spamtest'
    ),
    
    $stats_mode = false,
    
    $option_threads = null
  ;
  
  const TPL_ROOT = 'views/';
  
  const
    TIME_RANGE = 72, // hours
    TIME_RANGE_2 = 168, // hours
    TIME_RANGE_3 = 24, // hours
    USER_ACTIONS_TABLE = 'user_actions',
    ACTIONS_LOG_TABLE = 'actions_log',
    BOARD_STATS_TABLE = 'board_stats',
    BANS_TABLE = 'banned_users',
    DEL_LOG_TABLE = 'del_log',
    ARCHIVE_ACTION_ID = 131, // 128 + 3, defined in /stafflog.php
    RES_HARD_LIMIT = 2500,
    WEBROOT = '/stats',
    
    MAX_MONTHS = 13
  ;
  
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
  
  private function pretty_ttl($delta) {
    if ($delta < 60) {
      return '< 1 minute';
    }
    
    if ($delta < 3600) {
      $count = floor($delta / 60);
      
      if ($count > 1) {
        return $count . ' minutes';
      }
      else {
        return '1 minute';
      }
    }
    
    if ($delta < 86400) {
      $count = floor($delta / 3600);
      
      if ($count > 1) {
        $head = $count . ' hours';
      }
      else {
        $head = '1 hour';
      }
      
      $tail = floor($delta / 60 - $count * 60);
      
      if ($tail > 1) {
        $head .= ' and ' . $tail . ' minutes';
      }
      
      return $head;
    }
    
    $count = floor($delta / 86400);
    
    if ($count > 1) {
      $head = $count . ' days';
    }
    else {
      $head = '1 day';
    }
    
    $tail = floor($delta / 3600 - $count * 24);
    
    if ($tail > 1) {
      $head .= ' and ' . $tail . ' hours';
    }
    
    return $head;
  }
  
  private function get_board_titles($board = null) {
    if ($board !== null) {
      $query = "SELECT name FROM boardlist WHERE dir = '%s'";
      
      $res = mysql_global_call($query, $board);
      
      if (!$res) {
        $this->error('Database Error.');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Invalid board.');
      }
      
      return mysql_fetch_row($res)[0];
    }
    
    $query = "SELECT dir, name FROM boardlist ORDER BY dir";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $boards = array();
    
    while ($row = mysql_fetch_row($res)) {
      $boards[$row[0]] = $row[1];
    }
    
    return $boards;
  }
  
  private function get_mobile_ua_stats($board) {
    $sql = <<<SQL
SELECT SUBSTR(email, 1, 1) as ua, COUNT(*) as cnt FROM `%s`
WHERE archived = 0 AND email != '' GROUP BY SUBSTR(email, 1, 1)
SQL;
    
    $res = mysql_board_call($sql, $board);
    
    if (!$res) {
      return null;
    }
    
    $data = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $data[$row['ua']] = (int)$row['cnt'];
    }
    
    return $data;
  }
  
  /**
   * Number of new threads and posts per hour.
   * Doesn't take into account deleted posts.
   * returns array('threads' => array(threads_per_hour),
   *  'posts' => array(posts_per_hour))
   */
  private function get_posting_rate($board) {
    $actions_tbl = self::USER_ACTIONS_TABLE;
    
    $now = $_SERVER['REQUEST_TIME'];
    $now_hour = (int)date('G', $now);
    $thres = $now - 26 * 3600;
    $now_timegrp = floor($now / 3600);
    
    // New threads
    $query = <<<SQL
SELECT COUNT(*) AS val, FLOOR(UNIX_TIMESTAMP(time) / 3600) AS timegrp
FROM `$actions_tbl`
WHERE action = 'new_thread' AND board = '%s' AND time >= DATE_SUB(NOW(), INTERVAL 26 HOUR)
GROUP BY timegrp
ORDER BY timegrp
SQL;
    
    $res = mysql_global_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $data = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $data[(int)$row['timegrp']] = (int)$row['val'];
    }
    
    $total_threads = 0;
    
    $new_threads = array();
    
    for ($delta = 1; $delta < 25; ++$delta) {
      $timegrp = $now_timegrp - $delta;
      
      $hour = $now_hour - $delta;
      
      if ($hour < 0) {
        $hour += 24;
      }
      
      if (isset($data[$timegrp])) {
        $new_threads[$hour] = $data[$timegrp];
        $total_threads += $data[$timegrp];
      }
      else {
        $new_threads[$hour] = 0;
      }
    }
    
    ksort($new_threads);
    
    $new_threads = array_values($new_threads);
    
    // New posts
    $query = <<<SQL
SELECT COUNT(*) AS val, FLOOR(UNIX_TIMESTAMP(time) / 3600) AS timegrp
FROM `$actions_tbl`
WHERE action = 'new_reply' AND board = '%s' AND time >= DATE_SUB(NOW(), INTERVAL 26 HOUR)
GROUP BY timegrp
ORDER BY timegrp
SQL;
    
    $res = mysql_global_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $data = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $data[(int)$row['timegrp']] = (int)$row['val'];
    }
    
    $total_replies = 0;
    
    $new_replies = array();
    
    for ($delta = 1; $delta < 25; ++$delta) {
      $timegrp = $now_timegrp - $delta;
      
      $hour = $now_hour - $delta;
      
      if ($hour < 0) {
        $hour += 24;
      }
      
      if (isset($data[$timegrp])) {
        $new_replies[$hour] = $data[$timegrp];
        $total_replies += $data[$timegrp];
      }
      else {
        $new_replies[$hour] = 0;
      }
    }
    
    ksort($new_replies);
    
    $new_replies = array_values($new_replies);
    
    return array(
      'threads' => $new_threads,
      'replies' => $new_replies,
      'total_threads' => $total_threads,
      'total_replies' => $total_replies
    );
  }
  
  private function get_unique_ips_count($board) {
    $actions_tbl = self::USER_ACTIONS_TABLE;
    $thres = self::TIME_RANGE_3;
    
    $query = <<<SQL
SELECT COUNT(DISTINCT(ip)) as cnt FROM `$actions_tbl` WHERE board = '%s' AND
time > DATE_SUB(NOW(), INTERVAL $thres HOUR) AND
(action = 'new_reply' OR action = 'new_thread')
SQL;
    
    $res = mysql_global_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error (guic1)');
    }
    
    return (int)mysql_fetch_row($res)[0];
  }
  
  private function get_option_threads($board) {
    if ($this->option_threads !== null) {
      return $this->option_threads;
    }
    
    $actions_log_tbl = self::ACTIONS_LOG_TABLE;
    
    $thres_th_ttl = (int)self::TIME_RANGE_2;
    
    $query = <<<SQL
SELECT postno FROM `$actions_log_tbl`
WHERE board = '%s'
AND ts > DATE_SUB(NOW(), INTERVAL $thres_th_ttl HOUR)
ORDER BY id DESC LIMIT 500
SQL;
    
    $res = mysql_global_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $in_clause = array();
    
    while ($row = mysql_fetch_row($res)) {
      $in_clause[] = (int)$row[0];
    }
    
    $this->option_threads = $in_clause;
    
    return $in_clause;
  }
  
  /*
  private function get_unique_posters_count($board) {
    $query = "SELECT pwd, host FROM `%s` WHERE archived = 0";
    
    $res = mysql_board_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $ips_per_pwd = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      if (!isset($ips_per_pwd[$row['pwd']])) {
        $ips_per_pwd[$row['pwd']] = [];
      }
      
      $ips_per_pwd[$row['pwd']][] = $row['host'];
    }
    
    $dup_ips = [];
    
    $count = 0;
    
    foreach ($ips_per_pwd as $pwd => $ips) {
      $skip = false;
      
      foreach ($ips as $ip) {
        if (isset($dup_ips[$ip])) {
          $skip = true;
        }
        else {
          $dup_ips[$ip] = true;
        }
      }
      
      if (!$skip) {
        $count++;
      }
    }
    
    return $count;
  }
  */
  private function get_board_bump_limit($board) {
    global $yconfgdir;
    
    if (!preg_match('/^[0-9a-z]{1,6}$/', $board)) {
      return 0;
    }
    
    $bump_limit = 0;
    
    // using parse_ini fails for some reason
    $cfg = file_get_contents("$yconfgdir/boards/$board.config.ini");
    
    if (!$cfg) {
      return 0;
    }
    
    if (preg_match('/^MAX_RES ?= ?([0-9]+)/m', $cfg, $m)) {
      $bump_limit = (int)$m[1];
    }
    else {
      $cfg = file_get_contents("$yconfgdir/global_config.ini");
      
      if (!$cfg) {
        return 0;
      }
      
      if (preg_match('/^MAX_RES ?= ?([0-9]+)/m', $cfg, $m)) {
        $bump_limit = (int)$m[1];
      }
    }  
    
    return $bump_limit;
  }
  
  /**
   * Compiles stats about thread lifetimes
   */
  private function get_thread_ttl_stats($board) {
    $actions_log_tbl = self::ACTIONS_LOG_TABLE;

    $thres = (int)self::TIME_RANGE;
    
    $bump_limit = $this->get_board_bump_limit($board);
    
    if (!$bump_limit) {
      return null;
    }
    
    $board_sql = mysql_real_escape_string($board);
    
    if (!$board_sql) {
      return null;
    }
    
    // Skip threads with modified options (manually archived, permasaged, etc)
    $in_clause = $this->get_option_threads($board);
    
    if (!empty($in_clause)) {
      $in_clause = " AND no NOT IN (" . implode(',', $in_clause) . ")";
    }
    else {
      $in_clause = '';
    }
    
    // Get last reply times for archived threads
    $query = <<<SQL
SELECT IF(resto = 0, no, resto) as tid, MAX(IF(permasage = 0, time, 0)) as upd, COUNT(*) as cnt FROM `$board_sql`
WHERE resto IN(
  SELECT no FROM `$board_sql`
  WHERE archived = 1 AND resto = 0 AND permasage = 0 AND root > DATE_SUB(NOW(), INTERVAL $thres HOUR) $in_clause
)
OR no IN(
  SELECT no FROM `$board_sql`
  WHERE archived = 1 AND resto = 0 AND permasage = 0 AND root > DATE_SUB(NOW(), INTERVAL $thres HOUR) $in_clause
)
GROUP BY tid HAVING cnt <= $bump_limit
SQL;
    
    $res = mysql_board_call($query);
    
    if (!$res) {
      $this->error('Database Error (gtts0)');
    }
    
    $threads = [];
    $in_clause = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      $in_clause[] = (int)$row['tid'];
      $threads[$row['tid']] = (int)$row['upd'];
    }
    
    if (!empty($in_clause)) {
      $in_clause = implode(',', $in_clause);
    }
    else {
      // No archived threads
      return null;
    }
    
    // Get thread archiving times
    $query = <<<SQL
SELECT no, UNIX_TIMESTAMP(root) as arc FROM `$board_sql`
WHERE no IN($in_clause)
SQL;

    $res = mysql_board_call($query);
    
    if (!$res) {
      $this->error('Database Error (gtts1)');
    }
    
    $ttl = [];
    
    while ($row = mysql_fetch_assoc($res)) {
      if (isset($threads[$row['no']])) {
        $ttl[] = (int)$row['arc'] - $threads[$row['no']];
      }
    }
    
    sort($ttl, SORT_NUMERIC);
    
    $size = count($ttl);
    
    $data = [];
    
    $data['min_ttl'] = $ttl[0];
    
    if ($size > 1) {
      $perc = ceil($size * 0.01);
      
      $data['min_ttl_low'] = $ttl[$perc];
    }
    else {
      $data['min_ttl_low'] = $data['min_ttl'];
    }
    
    return $data;
  }
  
  /**
   * Compiles moderation stats (deletions, bans, reports)
   */
  private function get_moderation_stats($board) {
    $actions_tbl = self::USER_ACTIONS_TABLE;
    $del_log_tbl = self::DEL_LOG_TABLE;
    
    $thres = (int)self::TIME_RANGE_2;
    
    $data = [];
    
    // ---
    
    $query = <<<SQL
SELECT COUNT(*) as cnt FROM `$actions_tbl`
WHERE board = '%s'
AND action = 'report'
AND time > DATE_SUB(NOW(), INTERVAL $thres HOUR)
SQL;

    $res = mysql_global_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $data['reports'] = (int)mysql_fetch_row($res)[0];
    
    // ---
    
    $query = <<<SQL
SELECT COUNT(*) as cnt FROM `$del_log_tbl`
WHERE board = '%s'
AND cleared = 0
AND ts > DATE_SUB(NOW(), INTERVAL $thres HOUR)
SQL;

    $res = mysql_global_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $data['deletions'] = (int)mysql_fetch_row($res)[0];
    
    return $data;
  }
  
  private function get_thread_size_stats($board) {
    $thres = (int)self::TIME_RANGE;
    
    $b = mysql_real_escape_string($board);
    
    $query = <<<SQL
SELECT COUNT(*) as cnt FROM `$b` WHERE
resto IN(SELECT no FROM `$b` WHERE resto = 0 AND sticky = 0 AND (archived = 0 OR root > DATE_SUB(NOW(), INTERVAL $thres HOUR)))
GROUP BY resto
ORDER BY cnt ASC
SQL;
    
    $res = mysql_board_call($query);
    
    if (!$res) {
      $this->error('Database Error (gtss1)');
    }
    
    $counts = [];
    
    while ($row = mysql_fetch_row($res)) {
      $counts[] = (int)$row[0];
    }
    
    $size = count($counts);
    
    if (!$size) {
      return array('med' => 0, 'avg' => 0);
    }
    
    $idx = floor($size / 2);
    
    if ($size & 1) {
      $median = $counts[$idx];
    }
    else {
      $median = floor(($counts[$idx - 1] + $counts[$idx]) / 2);
    }
    
    $average = floor(array_sum($counts) / $size);
    
    return array('med' => $median, 'avg' => $average);
  }
  
  /**
   * 
   */
  private function get_post_stats($board) {
    $actions_tbl = self::USER_ACTIONS_TABLE;
    $actions_log_tbl = self::ACTIONS_LOG_TABLE;
    
    $thres = (int)self::TIME_RANGE;
    $thres_th_ttl = (int)self::TIME_RANGE_2;
    
    $thres_ts = $_SERVER['REQUEST_TIME'] - self::TIME_RANGE * 3600;
    
    $data = array();
    
    // Count live and archived posts
    foreach (array('live' => 0, 'archived' => 1) as $label => $val) {
      $query = "SELECT COUNT(*) FROM `%s` WHERE archived = $val";
      
      $res = mysql_board_call($query, $board);
      
      if (!$res) {
        $this->error('Database Error.');
      }
      
      $data[$label . '_posts'] = (int)mysql_fetch_row($res)[0];
    }
    
    // Thread TTL before getting pruned
    $in_clause = $this->get_option_threads($board);
    
    if (!empty($in_clause)) {
      $in_clause = " AND no NOT IN (" . implode(',', $in_clause) . ")";
    }
    else {
      $in_clause = '';
    }
    
    $query = <<<SQL
SELECT
COUNT(*) as cnt,
MIN(UNIX_TIMESTAMP(root) - time) as min_ttl,
ROUND(AVG(UNIX_TIMESTAMP(root) - time)) as avg_ttl,
MAX(UNIX_TIMESTAMP(root) - time) as max_ttl
FROM `%s`
WHERE resto = 0 AND archived = 1 AND permasage = 0
AND root > DATE_SUB((SELECT root FROM `%s` WHERE archived = 1 ORDER BY root DESC LIMIT 1),
INTERVAL $thres_th_ttl HOUR)$in_clause
SQL;
    
    $res = mysql_board_call($query, $board, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $data['thread_ttl'] = mysql_fetch_assoc($res);
    
    $_cnt = (int)$data['thread_ttl']['cnt'];
    
    unset($data['thread_ttl']['cnt']);
    
    if ($_cnt > 0) {
      $_perc_low = ceil(($_cnt - 1) * 0.01);
      
      $query = <<<SQL
SELECT (UNIX_TIMESTAMP(root) - time) as min_ttl
FROM `%s`
WHERE resto = 0 AND archived = 1 AND permasage = 0
AND root > DATE_SUB((SELECT root FROM `%s` WHERE archived = 1 ORDER BY root DESC LIMIT 1),
INTERVAL $thres_th_ttl HOUR)$in_clause
ORDER BY min_ttl ASC
LIMIT $_perc_low, 1
SQL;
      
      $res = mysql_board_call($query, $board, $board);
      
      if (!$res) {
        $this->error('Database Error.');
      }
      
      $data['thread_ttl']['min_ttl_low'] = (int)mysql_fetch_row($res)[0];
    }
    
    // File type breakdown
    $query = <<<SQL
SELECT COUNT(*) as cnt, ext FROM `%s`
WHERE ext != '' AND time >= $thres_ts
GROUP BY ext
SQL;
    
    $res = mysql_board_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $file_types = array();
    
    while ($row = mysql_fetch_row($res)) {
      $file_types[$row[1]] = (int)$row[0];
    }
    
    $data['file_types'] = $file_types;
    
    // Recent unique IPs
    $data['unique_ips'] = $this->get_unique_ips_count($board);
    
    // Image and text replies in the past TIME_RANGE hours
    $query = <<<SQL
SELECT SUM(had_image) as img, SUM(had_image = 0) as txt
FROM `$actions_tbl`
WHERE action = 'new_reply' AND board = '%s'
AND time >= DATE_SUB(NOW(), INTERVAL $thres HOUR)
SQL;
    
    $res = mysql_global_call($query, $board);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $row = mysql_fetch_assoc($res);
    
    $data['image_replies'] = (int)$row['img'];
    
    $data['text_replies'] = (int)$row['txt'];
    
    return $data;
  }
  
  /**
   * Index
   */
  public function index() {
    $this->is_manager = has_level('manager') || has_flag('developer');
    
    if (isset($_GET['board'])) {
      if ($this->is_manager) {
        if ($_GET['board'] === 'global') {
          $this->active_board = 'global';
          $this->boards = $this->get_board_titles();
          $this->renderHTML('stats-global');
          return;
        }
        
        if ($_GET['board'] === 'monthly') {
          $this->active_board = 'monthly';
          $this->monthly();
          return;
        }
      }
      
      $this->stats_mode = true;
      
      $raw_board = $_GET['board'];
      
      $post_stats = $this->get_post_stats($raw_board);
      
      $this->mod_stats = $this->get_moderation_stats($raw_board);
      
      $this->posting_rate = $this->get_posting_rate($raw_board);
      
      $data = array(
        'replyTypes' => array(
          'imageReplies' => $post_stats['image_replies'],
          'textReplies' => $post_stats['text_replies']
        ),
        'fileTypes' => $post_stats['file_types'],
        'newThreads' => $this->posting_rate['threads'],
        'newReplies' => $this->posting_rate['replies'],
      );
      
      if (isset($_GET['json'])) {
        $data['livePosts'] = $post_stats['live_posts'];
        $data['archivedPosts'] = $post_stats['archived_posts'];
        $data['reports'] = $this->mod_stats['reports'];
        $this->successJSON($data);
        return;
      }
      
      $this->active_board = htmlspecialchars($raw_board);
      
      $board_title = $this->get_board_titles($raw_board);
      
      $this->page_title = " - /{$this->active_board}/ - $board_title";
      
      
      $this->ttl_stats = $this->get_thread_ttl_stats($raw_board);
      
      $this->thread_size_stats = $this->get_thread_size_stats($raw_board);
      
      $this->plot_data = json_encode($data);
      
      $this->post_stats = $post_stats;
      
      if ($this->is_manager) {
        $this->ua_stats = $this->get_mobile_ua_stats($raw_board);
      }
      else {
        $this->ua_stats = null;
      }
    }
    else {
      $this->stats_mode = false;
      $this->page_title = '';
    }
    
    $this->boards = $this->get_board_titles();
    
    $this->renderHTML('stats');
  }
  
  private function monthly() {
    $tbl = self::BOARD_STATS_TABLE;
    
    $interval = new DateInterval('P1M');
    
    $s_date = new DateTime();
    
    $e_date = new DateTime();
    $e_date->add($interval);
    
    $data = array();
    
    for ($i = 0; $i <= self::MAX_MONTHS; $i++) {
      $s_clause = $s_date->format('Y-m-01 00:00:00');
      $e_clause = $e_date->format('Y-m-01 00:00:00');
      
      $query =<<<SQL
SELECT MIN(post_count) as post_count FROM $tbl
WHERE created_on BETWEEN '$s_clause' AND '$e_clause'
GROUP BY board
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error.');
      }
      
      $month_name = $s_date->format('M');
      
      $total = 0;
      
      while ($row = mysql_fetch_array($res)) {
        $total += (int)$row[0];
      }
      
      $data[] = array($month_name, $total);
      
      $s_date->sub($interval);
      $e_date->sub($interval);
    }
    
    if (empty($data)) {
      $this->error('Internal Server Error.');
    }
    
    $lim = count($data) - 1;
    
    $data = array_reverse($data);
    
    for ($i = 0; $i < $lim; $i++) {
      $data[$i][1] = $data[$i + 1][1] - $data[$i][1];
    }
    
    array_pop($data);
    
    $this->plot_data = json_encode($data);
    
    $this->boards = $this->get_board_titles();
    
    $this->renderHTML('stats-monthly');
  }
  
  private function sort_val_desc($a, $b) {
    if ($a == $b) {
      return 0;
    }
    
    return ($a > $b) ? -1 : 1;
  }
  
  public function spamtest() {
    if (isset($_GET['type']) && $_GET['type']) {
      $type = mysql_real_escape_string($_GET['type']);
    }
    else {
      $type = 'blocked_g';
    }
    
    if (isset($_GET['nopost'])) {
      $lim = 5000;
      $cols = 'id, board, ip, created_on';
    }
    else {
      $lim = 500;
      $cols = '*';
    }
    
    $wc = strpos($type, '*');
    
    if ($wc && $wc === (strlen($type) - 1)) {
      $type = str_replace('*', '%', $type);
      $type = str_replace('_', "\_", $type);
      $type_sql = "LIKE '$type'";
    }
    else {
      $type_sql = "= '$type'";
    }
    
    $query = "SELECT $cols FROM event_log WHERE type $type_sql ORDER BY id DESC LIMIT $lim";
    
    $res = mysql_global_call($query);?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>4chan</title>
  <link rel="stylesheet" type="text/css" href="/css/bans.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<div id="content">
<table class="items-table compact-table">
  <tr>
    <th>ID</th>
    <th>Type</th>
    <th>Date</th>
    <th>Board</th>
    <th>TID</th>
    <th>PID</th>
    <th>IP</th>
    <th>Num Arg</th>
    <th>Str Arg</th>
    <th>Meta</th>
  </tr>
  <?php while ($row = mysql_fetch_assoc($res)) { ?>
  <tr>
    <td class="cnt-pre"><?php echo $row['id'] ?></td>
    <td class="cnt-pre"><?php echo $row['type'] ?></td>
    <td class="cnt-pre"><?php echo $row['created_on'] ?></td>
    <td class="cnt-pre"><?php echo $row['board'] ?></td>
    <td class="cnt-pre"><?php echo $row['thread_id'] ?></td>
    <td class="cnt-pre"><?php echo $row['post_id'] ?></td>
    <td class="cnt-pre"><?php echo $row['ip'] ?></td>
    <td class="cnt-pre"><?php echo $row['arg_num'] ?></td>
    <td class="cnt-pre"><?php echo $row['arg_str'] ?></td>
    <td class="cnt-pre"><?php echo $row['meta'] ?></td>
  </tr>
  <?php } ?>
</table>
</div>
</body>
</html>
  <?php
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
