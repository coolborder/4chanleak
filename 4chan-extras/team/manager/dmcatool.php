<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

if (php_sapi_name() !== 'cli') {
  require_once '../lib/sec.php';
  
  define('IN_APP', true);
  
  auth_user();
  
  if (!has_level('manager') && !has_flag('developer')) {
    APP::denied();
  }
  
  require_once '../lib/csp.php';
  /*
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  */
}

require_once 'lib/ini.php';

load_ini("$configdir/cloudflare_config.ini");
finalize_constants();

define('CLOUDFLARE_EMAIL', 'cloudflare@4chan.org');
define('CLOUDFLARE_ZONE', '4chan.org');
define('CLOUDFLARE_ZONE_2', '4cdn.org');

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'view',
      'create_notice',
      'create_counter',
      'resolve_counter',
      'search',
      'attachment',
      'force_restore_content'/*,
      'debug',
      'truncate',
      'restore_content_debug',
      'create_table_actions',
      'create_table_counters',
      'create_table_offenders',
      'create_table_notices'
      */
    );
  
  const TPL_ROOT = '../views/';
  
  const WEBROOT = '/manager/dmcatool';
  
  const PAGE_SIZE = 50;
  
  const FILE_TTL = 30; // number of days after which stored content is pruned
  
  const
    NOTICES_TABLE = 'dmca_notices',
    ACTIONS_TABLE = 'dmca_actions',
    COUNTERS_TABLE = 'dmca_counternotices'
  ;
  
  const
    WARN_MESSAGE = '../data/warn_dmca_offender.txt',
    BAN_MESSAGE = '../data/ban_dmca_offender.txt',
    CONFIRM_NOTICE_EMAIL = '../data/mail_dmca_takedownnotice_confirm.txt',
    CONFIRM_COUNTER_EMAIL = '../data/mail_dmca_counternotice_confirm.txt',
    NOTIFY_COUNTER_EMAIL = '../data/mail_dmca_counternotice_claimantemail.txt',
    CONFIRM_ORDER_EMAIL = '../data/mail_dmca_courtorder_confirm.txt',
    NOTIFY_ORDER_EMAIL = '../data/mail_dmca_courtorder_infringeremail.txt'
  ;
  
  const
    IMG_ROOT = '/www/4chan.org/web/images/',
    THUMB_ROOT = '/www/4chan.org/web/thumbs/',
    PUBLIC_ROOT = '//i.4cdn.org/'
  ;
  
  const REPEAT_OFFENSES = 5; // Ban at 5 repeat offenses
  
  // Number of days after which the content is automatically restored
  const RESTORE_DELAY = 10;
  
  const DATE_FORMAT = 'm/d/Y H:i';
  const DATE_FORMAT_SHORT = 'm/d/Y'; // used in emails
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  /*
  public function debug() {
    $this->purge_cache_internal('test', '1528804252183.jpg');
  }
  */
  /*
  public function truncate() {
    $sql = "TRUNCATE TABLE `" . self::ACTIONS_TABLE . "`";
    mysql_global_call($sql);
    
    $sql = "TRUNCATE TABLE `" . self::NOTICES_TABLE . "`";
    mysql_global_call($sql);
    
    $sql = "TRUNCATE TABLE `" . self::COUNTERS_TABLE . "`";
    mysql_global_call($sql);
  }

  
  public function create_table_actions() {
    $table = self::ACTIONS_TABLE;
    
    $sql = "DROP TABLE `$table`";
    mysql_global_call($sql);
    
    $sql =<<<SQL
CREATE TABLE IF NOT EXISTS `$table` (
  `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `notice_id` int(10) NOT NULL,
  `blacklist_id` int(10) NOT NULL,
  `contested` int(1) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `pwd` varchar(255) NOT NULL,
  `pass_id` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `restored_on` int(10) NOT NULL DEFAULT 0,
KEY `notice_id` (`notice_id`),
KEY `ip` (`contested`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
SQL;
    
    mysql_global_call($sql);
  }
  
  public function create_table_notices() {
    $table = self::NOTICES_TABLE;
    
    $sql = "DROP TABLE `$table`";
    mysql_global_call($sql);
    
    $sql =<<<SQL
CREATE TABLE IF NOT EXISTS `$table` (
  `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `representative` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(255) NOT NULL,
  `fax` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `urls` text NOT NULL,
  `hide_name` int(1) NOT NULL,
  `notice_content` text NOT NULL,
  `backup_key` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_on` int(10) unsigned NOT NULL,
  `created_by` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
SQL;
    
    mysql_global_call($sql);
  }
  
  public function create_table_counters() {
    $table = self::COUNTERS_TABLE;
    
    $sql = "DROP TABLE `$table`";
    mysql_global_call($sql);
    
    $sql =<<<SQL
CREATE TABLE IF NOT EXISTS `$table` (
  `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `notice_id` int(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(255) NOT NULL,
  `fax` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `content_ids` text NOT NULL,
  `notice_content` text NOT NULL,
  `created_on` int(10) unsigned NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `resolved_on` int(10) unsigned NOT NULL,
  `resolution_content` text NOT NULL,
KEY `notice_id` (`notice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
SQL;
    
    mysql_global_call($sql);
  }
  */
  
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
  
  final protected function error_cli($msg) {
    //$this->error($msg);
    fwrite(STDERR, $msg);
    exit(1);
  }
  
  // Returns image urls: array(thumbnail, full_image)
  private function get_backup_img_urls($board, $tim, $ext, $backup_key) {
    return array(
      self::PUBLIC_ROOT . "$board/dmca_{$backup_key}_{$tim}s.jpg",
      self::PUBLIC_ROOT . "$board/dmca_{$backup_key}_$tim{$ext}"
    );
  }
  
  private function remote_rebuild_sync($board, $ids) {
    $url = "https://sys.int/$board/imgboard.php";
    
    $post = array();
    $post['mode'] = 'rebuild_threads_by_id';
    $post['ids'] = $ids;
    $post = http_build_query($post);
    
    $rpc_ch = rpc_start_request($url, $post, $_COOKIE, true);
    $resp = rpc_finish_request($rpc_ch, $error);
    
    return $resp !== null && $resp === '1';
  }
  
  private function getRandomHexBytes($length = 10) {
    $bytes = openssl_random_pseudo_bytes($length);
    return bin2hex($bytes);
  }
  
  private function purge_cache_internal($board, $file, $no) {
    $url = "http://g0ch4.brazil.jp:24502";
    
    $post = array();
    $post['rmpath'] = "/$board/$file";
    $post['key'] = '6a310437e13935b64beefcf10da8dba3';
    $post = http_build_query($post);
    
    rpc_start_request($url, $post, null, false);
  }
  
  private function prune_old_files() {
    $actions_tbl = self::ACTIONS_TABLE;
    $notices_tbl = self::NOTICES_TABLE;
    
    $thres = time() - self::FILE_TTL * 24 * 60 * 60;
    
    $query = <<<SQL
SELECT backup_key, content
FROM `$actions_tbl`, `$notices_tbl`
WHERE `$actions_tbl`.notice_id = `$notices_tbl`.id
AND created_on < $thres
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $post = json_decode($row['content'], true);
      
      if (!$post['ext']) {
        continue;
      }
      
      $board = $post['board'];
      $ext = $post['ext'];
      $tim = $post['tim'];
      $backup_key = $row['backup_key'];
      
      $file = self::IMG_ROOT . "$board/dmca_{$backup_key}_$tim{$ext}";
      
      if (file_exists($file)) {
        unlink($file);
      }
      
      $file = self::THUMB_ROOT . "$board/dmca_{$backup_key}_{$tim}s.jpg";
      
      if (file_exists($file)) {
        unlink($file);
      }
    }
    
    return true;
  }
  
  private function blacklist_add($claimant, $md5) {
    $description = htmlspecialchars($claimant);
    
    $query = <<<SQL
INSERT INTO blacklist (field, contents, description, ban, addedby)
VALUES('md5', '%s', '%s', '2', '%s')
SQL;
    
    $res = mysql_global_call($query, $md5, $description, $_COOKIE['4chan_auser']);
    
    if (!$res) {
      return false;
    }
    
    return mysql_global_insert_id();
  }
  
  private function blacklist_disable($blacklist_id) {
    $blacklist_id = (int)$blacklist_id;
    
    $query = "UPDATE blacklist SET active = 0 WHERE id = $blacklist_id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    return true;
  }
  
  private function blacklist_enable($blacklist_id) {
    $blacklist_id = (int)$blacklist_id;
    
    $query = "UPDATE blacklist SET active = 1 WHERE id = $blacklist_id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    return true;
  }
  
  private function send_mail($email, $mail_file, $values = null) {
    if (!file_exists($mail_file)) {
      return false;
    }
    
    $lines = file($mail_file);
    
    $subject = trim(array_shift($lines));
    $message = implode('', $lines);
    
    if ($values) {
      $subject = str_replace(array_keys($values), array_values($values), $subject);
      $message = str_replace(array_keys($values), array_values($values), $message);
    }
    
    $headers = "From: 4chan DMCA Agent <dmca@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Bcc: 4chan DMCA Agent <dmca@4chan.org>\r\n";
    
    $opts = '-f dmca@4chan.org';
    
    return mail($email, $subject, $message, $headers, $opts);
  }
  
  /**
   * Ban or Warn offender
   */
  private function warn_offender($notice_id, $claimant, $posts, $is_ban = false) {
    if ($is_ban) {
      $msg_file = self::BAN_MESSAGE;
      $length = '00000000000000';
    }
    else {
      $msg_file = self::WARN_MESSAGE;
      $length = date('Y-m-d H:i:s', time());
    }
    
    if (!file_exists($msg_file)) {
      return false;
    }
    
    $user_ip = $posts[0]['host'];
    $user_pass = $posts[0]['4pass_id'];
    
    if (!$user_ip) {
      return false;
    }
    
    $message = file_get_contents($msg_file);
    
    if (!$is_ban) {
      $content_list = array();
      
      foreach ($posts as $post) {
        $content_list[] = 'Post No.' . $post['no'] . ' on /' . $post['board'] . '/';
      }
      
      $content_list = implode('<br>', $content_list);
      
      if (!$claimant) {
        $by_claimant = '';
      }
      else {
        $by_claimant = ' by ' . $claimant;
      }
      
      $values = array(
        '{{BY_CLAIMANT}}' => htmlspecialchars($by_claimant),
        '{{NOTICE_ID}}' => $notice_id,
        '{{CONTENT_LIST}}' => $content_list
      );
      
      $message = str_replace(array_keys($values), array_values($values), $message);
    }
    
    $message = nl2br($message, false) . '<>';
    
    $query =<<<SQL
INSERT INTO banned_users (
  board, global, zonly, name, host, reverse, xff, reason, length,
  admin, md5, post_num, rule, post_time, template_id, 4pass_id,
  post_json, admin_ip
) 
VALUES ('', 1, 0, '', '%s', '%s', '', '%s', '%s',
  'Auto-ban', '', 0, '', '', 0, '%s',
  '', '')
SQL;
    
    $res = mysql_global_call($query,
      $user_ip, $user_ip, $message, $length,
      $user_pass
    );
    
    return !!$res;
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Returns a hashmap of boards: board_dir => true
   */
  private function get_valid_boards() {
    $query = 'SELECT dir FROM boardlist';
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    $boards = array();
    
    while ($row = mysql_fetch_row($res)) {
      $boards[$row[0]] = true;
    }
    
    $boards['test'] = true;
    
    return $boards;
  }
  
  /**
   * Validates the list of content ids to restore for counter-notices
   * returns true/false
   */
  private function validate_content_ids($content_ids) {
    $ids = preg_split('/[^0-9]+/', $content_ids);
    
    $content_ids = array();
    
    foreach ($ids as $cid) {
      $cid = (int)$cid;
      
      if (!$cid) {
        return false;
      }
      
      $content_ids[] = $cid;
    }
    
    if (empty($content_ids)) {
      return false;
    }
    
    $count = count($content_ids);
    
    $clause = implode(',', $content_ids);
    
    $tbl = self::ACTIONS_TABLE;
    
    $query = "SELECT id FROM $tbl WHERE id IN ($clause)";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    if (mysql_num_rows($res) !== $count) {
      return false;
    }
    
    return $content_ids;
  }
  
  /**
   * Fetches content (posts) from urls
   */
  private function preprocess_urls() {
    if (!isset($_POST['urls']) || $_POST['urls'] === '') {
      $this->error('URL(s) cannot be empty');
    }
    
    $urls = preg_split('/[\r\n]+/', trim($_POST['urls']));
    
    $valid_boards = $this->get_valid_boards();
    
    if (!$valid_boards) {
      $this->error('Database Error (gvb)');
    }
    
    $status = array();
    
    // Hash: board => array of posts
    $affected_content = array();
    
    $sql_fields = 'no, resto, tim, name, sub, com, filename, ext, md5, time, host, pwd, 4pass_id';
    
    $dup_map = array();
    
    foreach ($urls as $url) {
      $url = trim($url);
      
      // links to html content (posts, thread ops)
      if (preg_match('/\/([a-z0-9]+)\/thread\/([0-9]+)(?:\/[^#]+)?(?:\/?#p([0-9]+))?/', $url, $m)) {
        $board = $m[1];
        
        if (!isset($valid_boards[$board])) {
          $this->error('Invalid board: ' . htmlspecialchars($board));
        }
        
        if (!isset($affected_content[$board])) {
          $affected_content[$board] = array();
        }
        
        if (isset($m[3])) { // link with a post number fragment (#p123)
          $post_id = (int)$m[3];
        }
        else { // link to OP
          $post_id = (int)$m[2];
        }
        
        $query = "SELECT $sql_fields FROM `%s` WHERE no = %d";
        
        $res = mysql_board_call($query, $board, $post_id);
        
        if (!$res) {
          $this->error('Database Error (' . $board . ' - ' . $post_id . ')');
        }
        
        if (mysql_num_rows($res) < 1) {
          continue;
        }
        
        $post = mysql_fetch_assoc($res);
        
        // duplicate test
        if (isset($dup_map[$board . '-' . $post['no']])) {
          continue;
        }
        
        $post['board'] = $board;
        
        $dup_map[$board . '-' . $post['no']] = true;
        
        $affected_content[$board][] = $post;
        
        continue;
      }
      
      // links to files (thumbnails, full images)
      if (preg_match('/\/([a-z0-9]+)\/([0-9]+)[sm]?\\.[a-z]{3,4}$/', $url, $m)) {
        $board = $m[1];
        
        if (!isset($valid_boards[$board])) {
          $this->error('Invalid board: ' . htmlspecialchars($board));
        }
        
        if (!isset($affected_content[$board])) {
          $affected_content[$board] = array();
        }
        
        $tim = (int)$m[2];
        
        $query = "SELECT $sql_fields FROM `%s` WHERE tim = %d";
        
        $res = mysql_board_call($query, $board, $tim);
        
        if (!$res) {
          $this->error('Database Error (' . $board . ' - ' . $tim . ')');
        }
        
        if (mysql_num_rows($res) < 1) {
          continue;
        }
        
        $post = mysql_fetch_assoc($res);
        
        // duplicate test
        if (isset($dup_map[$board . '-' . $post['no']])) {
          continue;
        }
        
        $post['board'] = $board;
        
        $dup_map[$board . '-' . $post['no']] = true;
        
        $affected_content[$board][] = $post;
        
        continue;
      }
      
      $this->error('Invalid URL: ' . htmlspecialchars($url));
    }
    
    return $affected_content;
  }
  
  /**
   * Process the content affected by the DMCA notice.
   * Backs-up files, blanks-out posts, saves post json to database,
   * blacklists md5s.
   */
  private function process_content($content, $notice_id, $backup_key, $claimant) {
    // IDs of threads to rebuild: board => hash of ids
    $rebuild_ids = array();
    
    $status = array();
    
    foreach ($content as $board => $posts) {
      $rebuild_ids[$board] = array();
      
      foreach ($posts as $post) {
        $user_ip = $post['host'];
        $user_pwd = $post['pwd'];
        $user_pass_id = $post['4pass_id'];
        
        unset($post['host']);
        unset($post['pwd']);
        unset($post['4pass_id']);
        
        $post_json = json_encode($post);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
          $status[] = "Couldn't encode post content /$board/{$post['no']}";
          $post_json = '{}';
        }
        
        if ($post['ext']) {
          $blacklist_id = $this->blacklist_add($claimant, $post['md5']);
          
          if (!$blacklist_id) {
            $status[] = "Couldn't blacklist md5 for /$board/{$post['no']}";
            $blacklist_id = 0;
          }
        }
        else {
          $blacklist_id = 0;
        }
        
        $tbl = self::ACTIONS_TABLE;
        
        $query = <<<SQL
INSERT INTO `$tbl` (notice_id, blacklist_id, contested, ip, pwd, pass_id, content)
VALUES (%d, %d, 0, '%s', '%s', '%s', '%s')
SQL;
        $res = mysql_global_call($query,
          $notice_id, $blacklist_id, $user_ip, $user_pwd, $user_pass_id, $post_json
        );
        
        if (!$res) {
          $status[] = "Couldn't backup post /$board/{$post['no']}";
        }
        
        // blank out live post content
        $query = <<<SQL
UPDATE `%s` SET name = 'Anonymous', sub = '', filename = '', com = '', filedeleted = 1
WHERE no = %d LIMIT 1
SQL;
        $res = mysql_board_call($query, $board, $post['no']);
        
        if (!$res) {
          $status[] = "/!\ Couldn't blank post /$board/{$post['no']}";
        }
        else {
          $status[] = "Blanked post /$board/{$post['no']}";
        }
        
        if ($post['ext']) {
          // full image
          $img_path = self::IMG_ROOT . '/' . $board . '/' . $post['tim'] . $post['ext'];
          
          // this also purges the thumbnail
          cloudflare_purge_by_basename($board, $post['tim'] . $post['ext']);
          
          if ($post['ext'] !== '.swf') {
            $this->purge_cache_internal($board, $post['tim'] . $post['ext'], $post['no']);
          }
          
          if (file_exists($img_path)) {
            $new_name = self::IMG_ROOT . '/' . $board . '/dmca_' . $backup_key . '_' . $post['tim'] . $post['ext'];
            $ret = rename($img_path, $new_name);
            if ($ret === false) {
              $status[] = " - /!\ Couldn't backup file $board/{$post['tim']}{$post['ext']}";
            }
            else {
              $status[] = " - Backed up file /$board/{$post['tim']}{$post['ext']}";
            }
          }
          else {
            $status[] = " - File not found /$board/{$post['tim']}{$post['ext']}";
          }
          
          // thumbnail
          $img_path = self::THUMB_ROOT . '/' . $board . '/' . $post['tim'] . 's.jpg';
          
          if (file_exists($img_path)) {
            $new_name = self::THUMB_ROOT . '/' . $board . '/dmca_' . $backup_key . '_' . $post['tim'] . 's.jpg';
            $ret = rename($img_path, $new_name);
            if ($ret === false) {
              $status[] = " - /!\ Couldn't backup thumbnail $board/{$post['tim']}s.jpg";
            }
            else {
              $status[] = " - Backed up thumbnail /$board/{$post['tim']}s.jpg";
            }
          }
          else {
            $status[] = " - Thumbnail not found /$board/{$post['tim']}s.jpg";
          }
          
          // mobile resized full images (delete it)
          $img_path = self::THUMB_ROOT . '/' . $board . '/' . $post['tim'] . 'm' . $post['ext'];
          
          if (file_exists($img_path)) {
            unlink($img_path);
          }
        }
        
        $thread_id = $post['resto'] ? $post['resto'] : $post['no'];
        
        $rebuild_ids[$board][$thread_id] = true;
      }
    }
    
    foreach ($rebuild_ids as $board => $ids) {
      if (empty($ids)) {
        continue;
      }
      
      $ids = array_keys($ids);
      
      if ($this->remote_rebuild_sync($board, $ids)) {
        $status[] = "Rebuilt affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
      else {
        $status[] = "Couldn't rebuild affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
    }
    
    return $status;
  }
  
  /**
   * Normally called from CLI by a cron task
   */
  public function restore_content_cli() {
    $tbl = self::COUNTERS_TABLE;
    
    $now = time();
    
    $lim = $now - (self::RESTORE_DELAY * 86400);
    
    $query = "SELECT id FROM $tbl WHERE resolved_on = 0 AND created_on <= $lim";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error_cli('Database Error (rcc1)');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->restore_content($row['id']);
    }
    
    exit(0);
  }
  
  /**
   * Manually restores actions for provided counter-notice IDs
   */
  public function restore_content_debug() {
    if (!isset($_GET['counter_ids'])) {
      $this->error('Bad Request.');
    }
    
    $counter_ids = $_GET['counter_ids'];
    
    foreach ($counter_ids as $counter_id) {
      $status = $this->restore_content($counter_id);
      
      echo implode('<br>', $status);
    }
  }
  
  public function force_restore_content() {
    if (!isset($_GET['action_id'])) {
      $this->error('Bad Request.');
    }
    
    $action_id = (int)$_GET['action_id'];
    
    $tbl = self::ACTIONS_TABLE;
    
    $query = <<<SQL
SELECT * FROM $tbl
WHERE id = $action_id
AND restored_on = 0 LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (rc2)');
    }
    
    $action = mysql_fetch_assoc($res);
    
    if (!$action) {
      $this->error('Nothing to do.');
    }
    
    $notice_id = (int)$action['notice_id'];
    
    // ---
    
    $tbl = self::NOTICES_TABLE;
    
    $query = "SELECT backup_key FROM $tbl WHERE id = $notice_id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (rc22)');
    }
    
    $backup_key = mysql_fetch_row($res)[0];
    
    if (!$backup_key) {
      $this->error("Can't get backup key");
    }
    
    // ---
    
    // IDs of threads to rebuild: board => hash of ids
    $rebuild_ids = array();
    
    $valid_boards = $this->get_valid_boards();
    
    if (!$valid_boards) {
      $this->error('Database Error (rc2)');
    }
    
    $now = time();
    
    $restored_actions = array();
    
    $status = [];
    
    if ($action['blacklist_id']) {
      if (!$this->blacklist_disable($action['blacklist_id'])) {
        $status[] = "Couldn't disable blacklisted md5 for action #{$action['id']}";
      }
    }
    
    $post = json_decode($action['content'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
      $status[] = "Couldn't decode content for action #{$action['id']}";
      continue;
    }
    
    if (!isset($valid_boards[$post['board']])) {
      $status[] = "Invalid board for action #{$action['id']}";
      continue;
    }
    
    // Check if the post exists
    $query = 'SELECT no FROM `%s` WHERE no = %d LIMIT 1';
    
    $res = mysql_board_call($query, $post['board'], $post['no']);
    
    if (!$res) {
      $status[] = "Database error for action #{$action['id']}";
      continue;
    }
    
    $restored_actions[] = $action;
    
    if (mysql_num_rows($res) !== 1) {
      $status[] = "Post was pruned or deleted for action #{$action['id']}";
      continue;
    }
    
    $thread_id = $post['resto'] ? $post['resto'] : $post['no'];
    
    if (!isset($rebuild_ids[$post['board']])) {
      $rebuild_ids[$post['board']] = array();
    }
    
    $rebuild_ids[$post['board']][$thread_id] = true;
    
    // Restoring the post
    $query = <<<SQL
UPDATE `%s` SET filedeleted = 0, com = '%s', sub = '%s', name = '%s', filename = '%s'
WHERE no = %d LIMIT 1
SQL;
    
    $res = mysql_board_call($query, $post['board'], $post['com'], $post['sub'], $post['name'], $post['filename'], $post['no']);
    
    if (!$res) {
      $status[] = "/!\ Couldn't restore post for action #{$action['id']}";
    }
    else {
      $status[] = "Restored post for action #{$action['id']}";
    }
    
    // Restoring files
    if ($post['ext']) {
      // full image
      $dest_path = self::IMG_ROOT . '/' . $post['board'] . '/' . $post['tim'] . $post['ext'];
      $src_path = self::IMG_ROOT . '/' . $post['board'] . '/dmca_' . $backup_key . '_' . $post['tim'] . $post['ext'];
      
      if (file_exists($dest_path)) {
        $status[] = " - /!\ Destination file already exists for action #{$action['id']}";
      }
      else {
        if (file_exists($src_path)) {
          $ret = rename($src_path, $dest_path);
          
          if ($ret === false) {
            $status[] = " - /!\ Couldn't restore file for action #{$action['id']}";
          }
          else {
            $status[] = " - Restored file for action #{$action['id']}";
          }
        }
        else {
          $status[] = " - /!\ Original file not found for action #{$action['id']}";
        }
      }
      
      // thumbnail
      $dest_path = self::THUMB_ROOT . '/' . $post['board'] . '/' . $post['tim'] . 's.jpg';
      $src_path = self::THUMB_ROOT . '/' . $post['board'] . '/dmca_' . $backup_key . '_' . $post['tim'] . 's.jpg';
      
      if (file_exists($dest_path)) {
        $status[] = " - /!\ Destination thumbnail already exists for action #{$action['id']}";
      }
      else {
        if (file_exists($src_path)) {
          $ret = rename($src_path, $dest_path);
          
          if ($ret === false) {
            $status[] = " - /!\ Couldn't restore thumbnail for action #{$action['id']}";
          }
          else {
            $status[] = " - Restored thumbnail for action #{$action['id']}";
          }
        }
        else {
          $status[] = " - /!\ Original thumbnail not found for action #{$action['id']}";
        }
      }
    }
    
    foreach ($rebuild_ids as $board => $ids) {
      if (empty($ids)) {
        continue;
      }
      
      $ids = array_keys($ids);
      
      if ($this->remote_rebuild_sync($board, $ids)) {
        $status[] = "Rebuilt affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
      else {
        $status[] = "Couldn't rebuild affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
    }
    
    echo implode('<br>', $status);
  }
  
  /**
   * Reverts actions by counter-notice id.
   * Values should already be escaped.
   */
  private function restore_content($counter_id) {
    $tbl = self::COUNTERS_TABLE;
    
    $counter_id = (int)$counter_id;
    
    $query = <<<SQL
SELECT notice_id, content_ids FROM $tbl
WHERE resolved_on = 0 AND id = $counter_id LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error_cli('Database Error (rc0)');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error_cli("Counter-notice #$counter_id not found or already resolved.");
    }
    
    $counter_notice = mysql_fetch_assoc($res);
    
    $action_ids = array();
    
    $ids = preg_split('/[^0-9]+/', $counter_notice['content_ids']);
    
    foreach ($ids as $id) {
      $id = (int)$id;
      
      if (!$id) {
        continue;
      }
      
      $action_ids[] = $id;
    }
    
    $notice_id = (int)$counter_notice['notice_id'];
    
    // ---
    
    $tbl = self::NOTICES_TABLE;
    
    $query = <<<SQL
SELECT backup_key FROM $tbl WHERE id = $notice_id LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error_cli('Database Error (rc1)');
    }
    
    $backup_key = mysql_fetch_row($res)[0];
    
    if (!$backup_key) {
      $this->error_cli('Internal Server Error (rc1)');
    }
    
    // Set counter-notice as resolved
    $query = "UPDATE %s SET resolved_on = %d WHERE id = %d LIMIT 1";
    
    $res = mysql_global_call($query, self::COUNTERS_TABLE, time(), $counter_id);
    
    if (!$res) {
      $status[] = "Couldn't set the counter-notice #$counter_id as resolved.";
    }
    
    // ---
    
    $tbl = self::ACTIONS_TABLE;
    
    $in_clause = implode(',', $action_ids);
    
    $query = <<<SQL
SELECT * FROM $tbl
WHERE notice_id = $notice_id
AND id IN ($in_clause)
AND restored_on = 0
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error_cli('Database Error (rc2)');
    }
    
    $actions = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $actions[] = $row;
    }
    
    if (empty($actions)) {
      $this->error_cli('Nothing to do.');
    }
    
    // ---
    
    // IDs of threads to rebuild: board => hash of ids
    $rebuild_ids = array();
    
    $valid_boards = $this->get_valid_boards();
    
    if (!$valid_boards) {
      $this->error_cli('Database Error (rc2)');
    }
    
    $now = time();
    
    $restored_actions = array();
    
    $status = [];
    
    foreach ($actions as $action) {
      if ($action['blacklist_id']) {
        if (!$this->blacklist_disable($action['blacklist_id'])) {
          $status[] = "Couldn't disable blacklisted md5 for action #{$action['id']}";
        }
      }
      
      $post = json_decode($action['content'], true);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        $status[] = "Couldn't decode content for action #{$action['id']}";
        continue;
      }
      
      if (!isset($valid_boards[$post['board']])) {
        $status[] = "Invalid board for action #{$action['id']}";
        continue;
      }
      
      // Check if the post exists
      $query = 'SELECT no FROM `%s` WHERE no = %d LIMIT 1';
      
      $res = mysql_board_call($query, $post['board'], $post['no']);
      
      if (!$res) {
        $status[] = "Database error for action #{$action['id']}";
        continue;
      }
      
      $restored_actions[] = $action;
      
      if (mysql_num_rows($res) !== 1) {
        $status[] = "Post was pruned or deleted for action #{$action['id']}";
        continue;
      }
      
      $thread_id = $post['resto'] ? $post['resto'] : $post['no'];
      
      if (!isset($rebuild_ids[$post['board']])) {
        $rebuild_ids[$post['board']] = array();
      }
      
      $rebuild_ids[$post['board']][$thread_id] = true;
      
      // Restoring the post
      $query = <<<SQL
UPDATE `%s` SET filedeleted = 0, com = '%s', sub = '%s', name = '%s', filename = '%s'
WHERE no = %d LIMIT 1
SQL;
      
      $res = mysql_board_call($query, $post['board'], $post['com'], $post['sub'], $post['name'], $post['filename'], $post['no']);
      
      if (!$res) {
        $status[] = "/!\ Couldn't restore post for action #{$action['id']}";
      }
      else {
        $status[] = "Restored post for action #{$action['id']}";
      }
      
      // Restoring files
      if ($post['ext']) {
        // full image
        $dest_path = self::IMG_ROOT . '/' . $post['board'] . '/' . $post['tim'] . $post['ext'];
        $src_path =  self::IMG_ROOT . '/' . $post['board'] . '/dmca_' . $backup_key . '_' . $post['tim'] . $post['ext'];
        
        if (file_exists($dest_path)) {
          $status[] = " - /!\ Destination file already exists for action #{$action['id']}";
        }
        else {
          if (file_exists($src_path)) {
            $ret = rename($src_path, $dest_path);
            
            if ($ret === false) {
              $status[] = " - /!\ Couldn't restore file for action #{$action['id']}";
            }
            else {
              $status[] = " - Restored file for action #{$action['id']}";
            }
          }
          else {
            $status[] = " - /!\ Original file not found for action #{$action['id']}";
          }
        }
        
        // thumbnail
        $dest_path = self::THUMB_ROOT . '/' . $post['board'] . '/' . $post['tim'] . 's.jpg';
        $src_path = self::THUMB_ROOT . '/' . $post['board'] . '/dmca_' . $backup_key . '_' . $post['tim'] . 's.jpg';
        
        if (file_exists($dest_path)) {
          $status[] = " - /!\ Destination thumbnail already exists for action #{$action['id']}";
        }
        else {
          if (file_exists($src_path)) {
            $ret = rename($src_path, $dest_path);
            
            if ($ret === false) {
              $status[] = " - /!\ Couldn't restore thumbnail for action #{$action['id']}";
            }
            else {
              $status[] = " - Restored thumbnail for action #{$action['id']}";
            }
          }
          else {
            $status[] = " - /!\ Original thumbnail not found for action #{$action['id']}";
          }
        }
      }
    }
    
    // Set actions as restored
    foreach ($restored_actions as $action) {
      $query = "UPDATE %s SET restored_on = %d WHERE id = %d AND notice_id = %d LIMIT 1";
      
      $res = mysql_global_call($query, self::ACTIONS_TABLE, $now, $action['id'], $notice_id);
      
      if (!$res) {
        $status[] = "Couldn't set the content as restored for action #{$action['id']}";
      }
    }
    
    foreach ($rebuild_ids as $board => $ids) {
      if (empty($ids)) {
        continue;
      }
      
      $ids = array_keys($ids);
      
      if ($this->remote_rebuild_sync($board, $ids)) {
        $status[] = "Rebuilt affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
      else {
        $status[] = "Couldn't rebuild affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
    }
    
    return $status;
  }
  
  /**
   * Saves offenders' information to database.
   * Sends warnings and bans repeat offenders.
   * Returns an array of status messages.
   */
  private function process_offenders($notice_id, $claimant, $content) {
    // Hash: user => [ posts ]
    $offenders = array();
    
    foreach ($content as $board => $posts) {
      foreach ($posts as $post) {
        $user_ip = $post['host'];
        $user_pwd = $post['pwd'];
        $user_pass = $post['4pass_id'];
        
        if ($user_pass) {
          $uid = $user_pass;
        }
        else {
          $uid = $user_ip;
        }
        
        if (!isset($offenders[$uid])) {
          $offenders[$uid] = array();
        }
        
        $offenders[$uid][] = $post;
      }
    }
    
    $status = array();
    
    $tbl = self::ACTIONS_TABLE;
    
    foreach ($offenders as $uid => $posts) {
      if (empty($posts)) {
        continue;
      }
      // posts contains offender's information
      $post = $posts[0];
      
      if ($post['4pass_id']) {
        $query = "SELECT COUNT(DISTINCT notice_id) FROM $tbl WHERE contested = 0 AND pass_id = '%s'";
        $res = mysql_global_call($query, $post['4pass_id']);
      }
      else {
        $query = "SELECT COUNT(DISTINCT notice_id) FROM $tbl WHERE contested = 0 AND ip = '%s'";
        $res = mysql_global_call($query, $post['host']);
      }
      
      if (!$res) {
        $status[] = "Couldn't process offender $uid";
        continue;
      }
      
      $count = (int)mysql_fetch_row($res)[0];
      
      if ($count >= self::REPEAT_OFFENSES) {
        if ($this->warn_offender($notice_id, $claimant, $posts, true)) {
          $status[] = "Banned offender $uid";
        }
        else {
          $status[] = "Couldn't ban offender $uid";
        }
      }
      
      if ($this->warn_offender($notice_id, $claimant, $posts)) {
        $status[] = "Warned offender $uid";
      }
      else {
        $status[] = "Couldn't warn offender $uid";
      }
    }
    
    return $status;
  }
  
  /**
   * Inserts the notice into the database
   * Returns an array [ newly created ID, formatted claimant name ]
   * formatted claimant name is either the company name or the claimant's name
   */
  private function process_notice($backup_key) {
    // Name
    if (!isset($_POST['name'])) {
      $name = '';
    }
    else {
      $name = $_POST['name'];
    }
    
    // Hide name
    if (!isset($_POST['hide_name'])) {
      $hide_name = 0;
    }
    else {
      $hide_name = $_POST['hide_name'] === '1' ? 1 : 0;
    }
    
    // Authorized Representative
    if (!isset($_POST['representative'])) {
      $representative = '';
    }
    else {
      $representative = $_POST['representative'];
    }
    
    // Urls
    if (!isset($_POST['urls']) || $_POST['urls'] === '') {
      $this->error('URL(s) cannot be empty');
    }
    else {
      $urls = $_POST['urls'];
    }
    
    // Email
    if (!isset($_POST['email'])) {
      $email = '';
    }
    else {
      $email = trim($_POST['email']);
    }
    
    // Copy of email
    if (!isset($_POST['notice_content']) || $_POST['notice_content'] === '') {
      $this->error('Copy of E-Mail cannot be empty');
    }
    else {
      $notice_content = $_POST['notice_content'];
    }
    
    // Company
    if (!isset($_POST['company'])) {
      $company = '';
    }
    else {
      $company = $_POST['company'];
    }
    
    // Address
    if (!isset($_POST['address'])) {
      $address = '';
    }
    else {
      $address = $_POST['address'];
    }
    
    // Telephone number
    if (!isset($_POST['phone'])) {
      $phone = '';
    }
    else {
      $phone = $_POST['phone'];
    }
    
    // Fax number
    if (!isset($_POST['fax'])) {
      $fax = '';
    }
    else {
      $fax = $_POST['fax'];
    }
    
    // Attached file
    $file_name = '';
    $file_data = '';
    
    if (isset($_FILES['doc_file']) && is_array($_FILES['doc_file'])) {
      $file = $_FILES['doc_file'];
      
      if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
          $this->error('Upload failed.');
        }
        
        if (!is_uploaded_file($file['tmp_name'])) {
          $this->error('Internal Server Error (file0)');
        }
        
        $file_name = basename($file['name']);
        $file_data = file_get_contents($file['tmp_name']);
      }
    }
    
    // ---
    
    $tbl = self::NOTICES_TABLE;
    
    $now = time();
    
    $query = <<<SQL
INSERT INTO `$tbl` (name, company, representative, address, phone, fax, email,
urls, hide_name, notice_content, file_name, file_data, backup_key, created_on, created_by)
VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', %d, '%s')
SQL;
    
    $res = mysql_global_call($query,
      $name, $company, $representative, $address, $phone, $fax, $email,
      $urls, $hide_name, $notice_content, $file_name, $file_data, $backup_key, $now, $_COOKIE['4chan_auser']
    );
    
    if (!$res) {
      $this->error('Database Error (pn1)');
    }
    
    $notice_id = mysql_global_insert_id();
    
    if (!$notice_id) {
      $this->error('Database Error (pn2)');
    }
    
    if ($company !== '') {
      $email_claimant = $company;
      $claimant = $company;
    }
    else {
      $email_claimant = $name;
      $claimant = $name;
    }
    
    if ($representative !== '') {
      $email_claimant = $representative;
    }
    
    // send confirmation email to Claimant
    if ($email) {
      $values = array(
        '{{CLAIMAINT_NAME_OR_COMPANY}}' => $email_claimant,
        '{{NOTICE_ID}}' => $notice_id,
        '{{NOTICE_DATE}}' => date(self::DATE_FORMAT_SHORT, $now),
        '{{NOTICE_COPY}}' => $notice_content
      );
      
      $this->send_mail($email, self::CONFIRM_NOTICE_EMAIL, $values);
    }
    
    return array($notice_id, $claimant, $hide_name === 1);
  }
  
  /**
   * Download attachment
   */
  public function attachment() {
    if (!isset($_GET['id'])) {
      $this->error('Bad Request.');
    }
    
    $notice_id = (int)$_GET['id'];
    
    // fetch the notice
    $query = "SELECT file_name, file_data FROM " . self::NOTICES_TABLE . " WHERE id = $notice_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (0).');
    }
    
    $notice = mysql_fetch_assoc($res);
    
    if (!$notice) {
      $this->error("Can't find this DMCA notice.");
    }
    
    if ($notice['file_data'] === '') {
      $this->error('This notice does not have an attachment.');
    }
    
    if ($notice['file_name'] !== '') {
      $filename = $notice['file_name'];
    }
    else {
      $filename = 'untitled_' . $notice['id'];
    }
    
    header('Content-type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $notice['file_data'];
  }
  
  /**
   * Toggles the contested flag for actions
   */
  private function set_contested($action_ids, $flag = true) {
    $flag = $flag ? 1 : 0;
    
    $clause = implode(',', $action_ids);
    
    $tbl = self::ACTIONS_TABLE;
    
    $query = "UPDATE $tbl SET contested = $flag WHERE id IN($clause)";
    
    $res = mysql_global_call($query);
    
    return !!$res;
  }
  
  /**
   * Inserts the counter notice into the database
   */
  private function process_counter() {
    if (!isset($_POST['notice_id']) || $_POST['notice_id'] === '') {
      $this->error('Notice ID cannot be empty');
    }
    else {
      $notice_id = (int)$_POST['notice_id'];
    }
    
    // Name
    if (!isset($_POST['name']) || $_POST['name'] === '') {
      $this->error('Name cannot be empty');
    }
    else {
      $name = $_POST['name'];
    }
    
    // Content ids
    if (!isset($_POST['content_ids']) || $_POST['content_ids'] === '') {
      $this->error('Content IDs cannot be empty');
    }
    else {
      $content_ids = $_POST['content_ids'];
    }
    
    // Email
    if (!isset($_POST['email'])) {
      $email = '';
    }
    else {
      $email = trim($_POST['email']);
    }
    
    // Copy of email
    if (!isset($_POST['notice_content']) || $_POST['notice_content'] === '') {
      $this->error('Copy of E-Mail cannot be empty');
    }
    else {
      $notice_content = $_POST['notice_content'];
    }
    
    // Company
    if (!isset($_POST['company'])) {
      $company = '';
    }
    else {
      $company = $_POST['company'];
    }
    
    // Address
    if (!isset($_POST['address'])) {
      $address = '';
    }
    else {
      $address = $_POST['address'];
    }
    
    // Telephone number
    if (!isset($_POST['phone'])) {
      $phone = '';
    }
    else {
      $phone = $_POST['phone'];
    }
    
    // Fax number
    if (!isset($_POST['fax'])) {
      $fax = '';
    }
    else {
      $fax = $_POST['fax'];
    }
    
    // ---
    
    $query = "SELECT id, name, email, company, representative FROM `%s` WHERE id = %d LIMIT 1";
    
    $res = mysql_global_call($query, self::NOTICES_TABLE, $notice_id);
    
    if (!$res) {
      $this->error('Database Error (pc1)');
    }
    
    if (!mysql_num_rows($res)) {
      $this->error("Couldn't find notice #" . $notice_id);
    }
    
    $original_notice = mysql_fetch_assoc($res);
    
    if (!$original_notice) {
      $this->error("Couldn't fetch notice #" . $notice_id);
    }
    
    // ---
    
    $content_ids_ary = $this->validate_content_ids($content_ids);
    
    if ($content_ids_ary === false) {
      $this->error("One or more content IDs are invalid");
    }
    
    if (!$this->set_contested($content_ids_ary, true)) {
      $this->error("Couldn't set actions as contested");
    }
    
    // ---
    
    $tbl = self::COUNTERS_TABLE;
    
    $now = time();
    
    $query = <<<SQL
INSERT INTO `$tbl` (notice_id, name, company, address, phone, fax, email, content_ids,
notice_content, created_on, created_by)
VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s')
SQL;
    
    $res = mysql_global_call($query,
      $notice_id, $name, $company, $address, $phone, $fax, $email, $content_ids,
      $notice_content, $now, $_COOKIE['4chan_auser']
    );
    
    if (!$res) {
      $this->error('Database Error (pn1)');
    }
    
    $counter_notice_id = mysql_global_insert_id();
    
    if (!$counter_notice_id) {
      $this->error('Database Error (pn2)');
    }
    
    // Send confirmation email to counter-claimant
    if ($company !== '') {
      $claimant = $company;
    }
    else {
      $claimant = $name;
    }
    
    if ($email) {
      $values = array(
        '{{COUNTERNOTICE_NAME_OR_COMPANY}}' => $claimant,
        '{{NOTICE_ID}}' => $notice_id,
        '{{COUNTERNOTICE_ID}}' => $counter_notice_id,
        '{{COUNTERNOTICE_DATE}}' => date(self::DATE_FORMAT_SHORT, $now),
        '{{COUNTERNOTICE_COPY}}' => $notice_content
      );
      
      $this->send_mail($email, self::CONFIRM_COUNTER_EMAIL, $values);
    }
    
    // Send notification to the original claimant
    if ($original_notice['representative'] !== '') {
      $original_claimant = $original_notice['representative'];
    }
    else if ($original_notice['company'] !== '') {
      $original_claimant = $original_notice['company'];
    }
    else {
      $original_claimant = $original_notice['name'];
    }
    
    if ($original_notice['email']) {
      $values = array(
        '{{CLAIMAINT_NAME_OR_COMPANY}}' => $original_claimant,
        '{{NOTICE_ID}}' => $notice_id,
        '{{COUNTERNOTICE_ID}}' => $counter_notice_id,
        '{{COUNTERNOTICE_DATE}}' => date(self::DATE_FORMAT_SHORT, $now),
        '{{COUNTERNOTICE_COPY}}' => $notice_content
      );
      
      $this->send_mail($original_notice['email'], self::NOTIFY_COUNTER_EMAIL, $values);
    }
    
    return $notice_id;
  }
  
  /**
   * Add DMCA notice to database
   */
  public function create_notice() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $status = array();
      
      $content = $this->preprocess_urls();
      
      $backup_key = $this->getRandomHexBytes(16);
      
      list($notice_id, $claimant, $hide_name) = $this->process_notice($backup_key);
      
      if (!empty($content)) {
        $s = $this->process_content($content, $notice_id, $backup_key, $claimant);
        $status = array_merge($status, $s);
        
        if ($hide_name) {
          $claimant = '';
        }
        
        $s = $this->process_offenders($notice_id, $claimant, $content);
        $status = array_merge($status, $s);
      }
      
      $status[] = '<br><a href="?action=view&id=' . (int)$notice_id . '">Back</a>';
      
      $this->success_msg = implode('<br>', $status);
      
      //$this->success('?action=view&id=' . (int)$notice_id);
      $this->success();
   }
    else {
      $this->renderHTML('dmca-input');
    }
  }
  
  private function re_backup_content($counter_id) {
    $counter_id = (int)$counter_id;
    
    $query = "SELECT notice_id, content_ids FROM " . self::COUNTERS_TABLE . " WHERE id = $counter_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (pc1)');
    }
    
    if (mysql_num_rows($res) !== 1) {
      $this->error('Counter-notice not found.');
    }
    
    $counter_notice = mysql_fetch_assoc($res);
    
    $action_ids = array();
    
    $ids = preg_split('/[^0-9]+/', $counter_notice['content_ids']);
    
    foreach ($ids as $id) {
      $id = (int)$id;
      
      if (!$id) {
        continue;
      }
      
      $action_ids[] = $id;
    }
    
    $notice_id = (int)$counter_notice['notice_id'];
    
    // ---
    
    $tbl = self::NOTICES_TABLE;
    
    $query = <<<SQL
SELECT backup_key FROM $tbl WHERE id = $notice_id LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (pc1)');
    }
    
    $backup_key = mysql_fetch_row($res)[0];
    
    if (!$backup_key) {
      $this->error('Internal Server Error (pc1)');
    }
    
    // ---
    
    $tbl = self::ACTIONS_TABLE;
    
    $in_clause = implode(',', $action_ids);
    
    $query = <<<SQL
SELECT * FROM $tbl
WHERE notice_id = $notice_id
AND id IN ($in_clause)
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error_cli('Database Error (pc2)');
    }
    
    $actions = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $actions[] = $row;
    }
    
    if (empty($actions)) {
      return array(); // empty status array
    }
    
    // ---
    
    // IDs of threads to rebuild: board => hash of ids
    $rebuild_ids = array();
    
    $valid_boards = $this->get_valid_boards();
    
    if (!$valid_boards) {
      $this->error_cli('Database Error (pc2)');
    }
    
    $now = time();
    
    $restored_actions = array();
    
    $status = [];
    
    foreach ($actions as $action) {
      if ($action['blacklist_id']) {
        if (!$this->blacklist_enable($action['blacklist_id'])) {
          $status[] = "Couldn't enable blacklisted md5 for action #{$action['id']}";
        }
      }
      
      $post = json_decode($action['content'], true);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        $status[] = "Couldn't decode content for action #{$action['id']}";
        continue;
      }
      
      if (!isset($valid_boards[$post['board']])) {
        $status[] = "Invalid board for action #{$action['id']}";
        continue;
      }
      
      // Check if the post exists
      $query = 'SELECT no FROM `%s` WHERE no = %d LIMIT 1';
      
      $res = mysql_board_call($query, $post['board'], $post['no']);
      
      if (!$res) {
        $status[] = "Database error for action #{$action['id']}";
        continue;
      }
      
      $restored_actions[] = $action;
      
      if (mysql_num_rows($res) !== 1) {
        continue;
      }
      
      $thread_id = $post['resto'] ? $post['resto'] : $post['no'];
      
      if (!isset($rebuild_ids[$post['board']])) {
        $rebuild_ids[$post['board']] = array();
      }
      
      $rebuild_ids[$post['board']][$thread_id] = true;
      
      // Blanking out the post
        $query = <<<SQL
UPDATE `%s` SET name = 'Anonymous', sub = '', filename = '', com = '', filedeleted = 1
WHERE no = %d LIMIT 1
SQL;
      
      $res = mysql_board_call($query, $post['board'], $post['no']);
      
      if (!$res) {
        $status[] = "/!\ Couldn't delete post for action #{$action['id']}";
      }
      
      $board = $post['board'];
      
      if ($post['ext']) {
        // full image
        $img_path = self::IMG_ROOT . '/' . $board . '/' . $post['tim'] . $post['ext'];
        
        // this also purges the thumbnail
        cloudflare_purge_by_basename($board, $post['tim'] . $post['ext']);
        
        if (file_exists($img_path)) {
          $new_name = self::IMG_ROOT . '/' . $board . '/dmca_' . $backup_key . '_' . $post['tim'] . $post['ext'];
          
          $ret = rename($img_path, $new_name);
          if ($ret === false) {
            $status[] = " - /!\ Couldn't backup file $board/{$post['tim']}{$post['ext']}";
          }
          else {
            $status[] = " - Backed up file /$board/{$post['tim']}{$post['ext']}";
          }
        }
        else {
          $status[] = " - File not found /$board/{$post['tim']}{$post['ext']}";
        }
        
        // thumbnail
        $img_path = self::THUMB_ROOT . '/' . $board . '/' . $post['tim'] . 's.jpg';
        
        if (file_exists($img_path)) {
          $new_name = self::THUMB_ROOT . '/' . $board . '/dmca_' . $backup_key . '_' . $post['tim'] . 's.jpg';
          
          $ret = rename($img_path, $new_name);
          if ($ret === false) {
            $status[] = " - /!\ Couldn't backup thumbnail $board/{$post['tim']}s.jpg";
          }
          else {
            $status[] = " - Backed up thumbnail $board/{$post['tim']}s.jpg";
          }
        }
        else {
            $status[] = " - Thumbnail not found $board/{$post['tim']}s.jpg";
        }
      }
    }
    
    // Set actions as not restored
    foreach ($restored_actions as $action) {
      $query = "UPDATE %s SET restored_on = 0 WHERE id = %d AND notice_id = %d LIMIT 1";
      
      $res = mysql_global_call($query, self::ACTIONS_TABLE, $action['id'], $notice_id);
      
      if (!$res) {
        $status[] = "Couldn't clear the restored flag for action #{$action['id']}";
      }
    }
    
    foreach ($rebuild_ids as $board => $ids) {
      if (empty($ids)) {
        continue;
      }
      
      $ids = array_keys($ids);
      
      if ($this->remote_rebuild_sync($board, $ids)) {
        $status[] = "Rebuilt affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
      else {
        $status[] = "Couldn't rebuild affected threads on /$board/ (No." . implode(', No.', $ids) . ')';
      }
    }
    
    return $status;
  }
  
  /**
   * Sets a counter-notice as resolved
   */
  public function resolve_counter() {
    if (!isset($_POST['counter_id'])) {
      $this->error('Bad Request.');
    }
    
    if (!isset($_POST['resolution_content']) || $_POST['resolution_content'] === '') {
      $this->error('E-mail content cannot be empty.');
    }
    
    $counter_id = (int)$_POST['counter_id'];
    
    $resolution_content = $_POST['resolution_content'];
    
    //
    // Fetch the counter-notice
    //
    $tbl = self::COUNTERS_TABLE;
    
    $query =<<<SQL
SELECT id, name, company, notice_id, resolution_content, email, content_ids
FROM $tbl
WHERE id = $counter_id
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (rc1)');
    }
    
    if (mysql_num_rows($res) !== 1) {
      $this->error('Counter-notice not found.');
    }
    
    $counter_notice = mysql_fetch_assoc($res);
    
    // Set actions as not contested
    $action_ids_ary = $this->validate_content_ids($counter_notice['content_ids']);
    
    if ($action_ids_ary !== false) {
      if (!$this->set_contested($action_ids_ary, false)) {
        $this->error("Couldn't unset the contested flag for actions.");
      }
    }
    
    if ($counter_notice['resolution_content'] !== '') {
      $this->error('This counter-notice is already fully resolved.');
    }
    
    if ($counter_notice['company'] !== '') {
      $counter_claimant = $counter_notice['company'];
    }
    else {
      $counter_claimant = $counter_notice['name'];
    }
    
    //
    // Fetch the notice
    //
    $query = "SELECT id, name, company, email, representative FROM "
      . self::NOTICES_TABLE . " WHERE id = " . (int)$counter_notice['notice_id'];
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (rc2)');
    }
    
    if (mysql_num_rows($res) !== 1) {
      $this->error('Notice not found.');
    }
    
    $notice = mysql_fetch_assoc($res);
    
    if ($notice['representative'] !== '') {
      $claimant = $notice['representative'];
    }
    else if ($notice['company'] !== '') {
      $claimant = $notice['company'];
    }
    else {
      $claimant = $notice['name'];
    }
    
    //
    // Purge
    //
    $status = $this->re_backup_content($counter_id);
    
    $query = "UPDATE %s SET resolved_on = %d, resolution_content = '%s' WHERE id = %d LIMIT 1";
    
    $res = mysql_global_call($query,
      self::COUNTERS_TABLE, time(), $resolution_content, $counter_id
    );
    
    if (!$res) {
      $this->error('Database Error (rc3)');
    }
    
    $now = time();
    
    //
    // Send confirmation email to claimant
    //
    if ($notice['email']) {
      $values = array(
        '{{CLAIMAINT_NAME_OR_COMPANY}}' => $claimant,
        '{{NOTICE_ID}}' => (int)$notice['id'],
        '{{COUNTERNOTICE_ID}}' => (int)$counter_notice['id'],
        '{{COURTORDER_DATE}}' => date(self::DATE_FORMAT_SHORT, $now),
        '{{COURTORDER_COPY}}' => $resolution_content
      );
      
      $this->send_mail($notice['email'], self::CONFIRM_ORDER_EMAIL, $values);
    }
    
    //
    // Send notification email to counter-claimant
    //
    if ($counter_notice['email']) {
      $values = array(
        '{{COUNTERNOTICE_NAME_OR_COMPANY}}' => $counter_claimant,
        '{{NOTICE_ID}}' => (int)$notice['id'],
        '{{COUNTERNOTICE_ID}}' => (int)$counter_notice['id'],
        '{{COURTORDER_DATE}}' => date(self::DATE_FORMAT_SHORT, $now)
      );
      
      $this->send_mail($counter_notice['email'], self::NOTIFY_ORDER_EMAIL, $values);
    }
    
    // ---
    
    $this->success_msg = implode('<br>', $status);
    
    $this->success();
  }
  
  /**
   * Shows a DMCA notice and its counter-notice if available
   */
  public function view() {
    if (!isset($_GET['id'])) {
      $this->error('Bad Request.');
    }
    
    $notice_id = (int)$_GET['id'];
    
    // fetch the notice
    $query = "SELECT * FROM " . self::NOTICES_TABLE . " WHERE id = $notice_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    $this->notice = mysql_fetch_assoc($res);
    
    if (!$this->notice) {
      $this->error("Can't find this DMCA notice.");
    }
    
    // fetch actions
    $this->dmca_actions = array();
    
    $query = "SELECT * FROM " . self::ACTIONS_TABLE . " WHERE notice_id = $notice_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (2).');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->dmca_actions[] = $row;
    }
    
    // fetch counter-notice if available
    $query = "SELECT * FROM " . self::COUNTERS_TABLE . " WHERE notice_id = $notice_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (3).');
    }
    
    $this->counter_notices = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->counter_notices[] = $row;
    }
    
    $this->renderHTML('dmca-view');
  }
  
  /**
   * Add DMCA counter-notice to database
   */
  public function create_counter() {
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
      $this->error('Bad Request.');
    }
    
    $this->process_counter();
    
    $this->success('?action=view&id=' . (int)$_POST['notice_id']);
  }
  
  /**
   * Search by notice ID, name, company or email
   */
  public function search() {
    if (!isset($_GET['q'])) {
      $this->error('Bad request');
    }
    
    if ($_GET['q'] === '') {
      $this->error('Query cannot be empty');
    }
    
    $q = $_GET['q'];
    
    $notices = array();
    $counter_notices = array();
    
    if (preg_match('/^[0-9]+$/', $q)) {
      // Notices
      $query = "SELECT * FROM %s WHERE id = %d LIMIT 1";
      
      $res = mysql_global_call($query, self::NOTICES_TABLE, $q);
      
      if (!$res) {
        $this->error('Database Error (1)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $notices[] = $row;
      }
      
      // Counter notices
      $query = "SELECT * FROM %s WHERE id = %d LIMIT 1";
      
      $res = mysql_global_call($query, self::COUNTERS_TABLE, $q);
      
      if (!$res) {
        $this->error('Database Error (1-2)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $counter_notices[] = $row;
      }
      
      // Black list IDs
      $query = "SELECT %s.* FROM %s LEFT JOIN %s ON %s.id = notice_id WHERE blacklist_id = %d LIMIT 1";
      
      $res = mysql_global_call($query, self::NOTICES_TABLE, self::ACTIONS_TABLE, self::NOTICES_TABLE, self::NOTICES_TABLE, $q);
      
      if (!$res) {
        $this->error('Database Error (1-3)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $notices[] = $row;
      }
    }
    else {
      $q = mysql_real_escape_string($q);
      
      // Fetch notices
      $tbl = self::NOTICES_TABLE;
      
      $query = <<<SQL
SELECT * FROM $tbl WHERE
name LIKE '%$q%' OR company LIKE '%$q%' OR email LIKE '%$q%'
SQL;
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error (2)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $notices[] = $row;
      }
      
      // Fetch counter-notices
      $tbl = self::COUNTERS_TABLE;
      
      $query = <<<SQL
SELECT * FROM $tbl WHERE
name LIKE '%$q%' OR company LIKE '%$q%' OR email LIKE '%$q%'
SQL;
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error (2)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        $counter_notices[] = $row;
      }
    }
    
    if (empty($notices) && empty($counter_notices)) {
      $this->error('Nothing Found.');
    }
    
    $this->notices = $notices;
    $this->counter_notices = $counter_notices;
    
    $this->renderHTML('dmca-search');
  }
  
  /**
   * Default page
   */
  public function index() {
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $query = "SELECT * FROM " . self::NOTICES_TABLE
      . " ORDER BY id DESC LIMIT $offset,$lim";
    
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
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    if ($this->next_offset) {
      array_pop($this->items);
    }
    
    $this->renderHTML('dmca');
  }
  
  /**
   * Main
   */
  public function run() {
    $method = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (php_sapi_name() === 'cli') {
      $this->restore_content_cli();
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
