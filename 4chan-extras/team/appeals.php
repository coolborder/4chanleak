<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/geoip2.php';

require_once 'lib/archives.php';

define('IN_APP', true);

auth_user();

if (!has_level('mod')) {
  APP::denied();
}
/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  //error_reporting(E_ALL);
  error_reporting(E_ALL & ~E_NOTICE);
}
*/
require_once 'lib/csp.php';

class APP {
  
  private
    $actions = array(
      'appeals',
      'details',
      'accept',
      'deny',
      'contact',
      'logs',
      'stats'
    ),
    
    $_cf_ready = false,
    
    $blockedPreviews = array(
      '1' => true, // Global 1 - Child Pornography (Explicit Image)
      '2' => true, // Global 1 - Violating US Law [Temp]
      '95' => true, // Global 1 - Violating US Law [Perm]
      '123' => true, // Global 1 - Child Model/Sexualized Image of Child
      '126' => true, // Global 1 - Child Pornography (Request or Link)
      '130' => true, // Revoke 4chan Pass (Violating US Law) [Perm]
      '131' => true // Revoke 4chan Pass (Spam/Advertising) [Perm]
    ),
    
    $isManager,
    $statsRange = 30, // days
    $params;
  
  private static
    $pageSize = 50;
  
  const REPORT_TEMPLATE = 190; // template used for report system abuses
  
  const TPL_ROOT = 'views/';
  
  const MAX_BAN_DAYS = 9999;
  
  const
    MATCHED_PWD = 2,
    MATCHED_PASS = 4
  ;
  
  const
    PASS_NONE = 0, // The ban doesn't have a Pass associated with it
    PASS_SAME = 1, // The ban has a Pass and matches the one in the appealed ban
    PASS_OTHER = 2 // The ban has a Pass but doesn't match the one in the appealed ban
  ;
  
  const
    PROXY_VPNGATE = 1,
    PROXY_OPEN = 2
  ;
  
  const
    PROXY_VPNGATE_REASON = 'VPN Gate exit list - unban dynamic IPs',
    PROXY_OPEN_REASON = 'Open proxy list - unban dynamic IPs'
  ;
  
  const BY_AUTOBAN = 'Auto-ban';
  
  public function __construct() {
    $this->params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $this->isManager = has_level('manager');// || has_flag('developer');
  }
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  /**
   * Utils
   */
  static function sort_users($a, $b) {
    if ($a === $b) {
      return 0;
    }
    
    return ($a < $b) ? 1 : -1;
  }
  
  private function is_ip_rangebanned($ip) {
    $long_ip = ip2long($ip);
    
    if (!$long_ip) {
      return false;
    }
    
    $query = <<<SQL
SELECT 1 FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip AND active = 1
AND expires_on = 0 AND ops_only = 0 AND img_only = 0 AND lenient = 0
AND ua_ids = '' AND report_only = 0 AND boards = ''
LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    if (mysql_num_rows($res) === 1) {
      return true;
    }
    
    return false;
  }
  
  private function days_duration($delta) {
    return (int)floor($delta / 86400);
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
  
  private function getPreciseDuration($delta) {
    if ($delta < 1) {
      return 'moments';
    }
    
    if ($delta < 60) {
      return $delta . ' seconds';
    }
    
    if ($delta < 3600) {
      $count = floor($delta / 60);
      
      if ($count > 1) {
        return $count . ' minutes';
      }
      else {
        return 'one minute';
      }
    }
    
    if ($delta < 86400) {
      $count = floor($delta / 3600);
      
      if ($count > 1) {
        $head = $count . ' hours';
      }
      else {
        $head = 'one hour';
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
      $head = 'one day';
    }
    
    $tail = floor($delta / 3600 - $count * 24);
    
    if ($tail > 1) {
      $head .= ' and ' . $tail . ' hours';
    }
    
    return $head;
  }
  
  private function getSalt() {
    return file_get_contents('/www/keys/legacy.salt');
  }
  
  private function formatHost($host) {
    $bits = explode('.', $host);
    return (count($bits) > 3 ? '*.' : '') . implode('.', array_slice($bits, -3));
  }
  
  /**
   * Gets the report category for bans for report system abuse
   */
  private function get_report_category_title($cat_id) {
    $query = "SELECT title FROM report_categories WHERE id = " . (int)$cat_id;
    
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
  
  /**
   * Linkifies references to bans, md5s and post filter IDs.
   * Returns ban ids, file md5s and post filter ids found in the private reason.
   */
  private function formatPrivateReason($reason, $board = null) {
    $refs_bid = [];
    $refs_md5 = [];
    $refs_fid = [];
    
    $blid_map = [];
    
    // Ban IDs
    $reason = preg_replace_callback('/ban ?ids?[: ]?([0-9, ]+)/', function($m) use (&$refs_bid) {
      preg_match_all('/[0-9]+/', $m[0], $ids);
      
      foreach ($ids[0] as $id) {
        $refs_bid[] = (int)$id;
      }
      
      return preg_replace(
        '/([0-9]+)/',
        '<a data-ref="bid-$1" target="_blank" href="//team.4chan.org/bans?action=update&amp;id=$1">$1</a>',
        $m[0]
      );
    },
    $reason);
    
    // MD5s
    if (preg_match('/^Auto-ban: Blacklisted md5 - ([a-f0-9]+) /', $reason, $md5)) {
      $refs_md5[] = $md5[1];
      
      $reason = preg_replace(
        '/^(Auto-ban: Blacklisted md5 - )([a-f0-9]+) /',
        '$1<a data-ref="md5-$2" target="_blank" href="https://team.4chan.org/bans?action=search&amp;md5=$2">$2</a> ',
        $reason
      );
    }
    
    // Post filter IDs
    if (preg_match('/\(filter ID: ([0-9]+)\)/', $reason, $fid)) {
      $refs_fid[] = (int)$fid[1];
      
      $reason = preg_replace(
        '/\((filter ID: )([0-9]+)(\))/',
        '$1<a data-ref="fid-$2" target="_blank" href="//team.4chan.org/postfilter?action=view&amp;id=$2">$2</a>$3',
        $reason
      );
    }
    
    // Blacklist IDs
    if (preg_match('/\(blacklist ID: ([0-9]+)\)/', $reason, $blid)) {
      $blid = (int)$blid[1];
      
      if (isset($blid_map[$blid])) {
        $md5 = $blid_map[$blid];
      }
      else {
        $md5 = $this->get_md5_from_blacklist_id($blid);
        $blid_map[$blid] = $md5;
      }
      
      $reason = preg_replace(
        '/\((blacklist ID: [0-9]+)\)/',
        '($1, MD5: <a target="_blank" href="https://team.4chan.org/bans?action=search&amp;md5=' . $md5 . '">' . $md5 . '</a>)',
        $reason
      );
    }
    
    // Fully qualified post IDs (/board/pid)
    $reason = preg_replace_callback('/(?:^|\s)\/([a-z0-9]{1,4})\/([0-9]+)(?:$|\s)/', function($m) {
      $link = return_archive_link($m[1], $m[2], false, true);
      
      if ($link !== false) {
        $link = rawurlencode($link);
        
        return preg_replace(
          "/\/{$m[1]}\/$m[2]/",
          '<a target="_blank" href="https://www.4chan.org/derefer?url=' . $link . "\">/{$m[1]}/$m[2]</a>",
          $m[0]
        );
      }
      else {
        return $m[0];
      }
    },
    $reason);
    
    if ($board) {
      // Post IDs (from multisearch bans for example)
      $reason = preg_replace_callback('/\bpid: ([0-9]+)/', function($m) use ($board) {
        $link = return_archive_link($board, $m[1], false, true);
        
        if ($link !== false) {
          $link = rawurlencode($link);
          
          return preg_replace(
            '/([0-9]+)/',
            '<a target="_blank" href="https://www.4chan.org/derefer?url=' . $link . '">$1</a>',
            $m[0]
          );
        }
        else {
          return $m[0];
        }
      },
      $reason);
      
      // Thread bans
      $reason = preg_replace_callback('/^Thread Ban No\.([0-9]+)$/', function($m) use ($board) {
        $link = return_archive_link($board, $m[1], false, true);
        
        if ($link !== false) {
          $link = rawurlencode($link);
          
          return preg_replace(
            '/([0-9]+)/',
            '<a target="_blank" href="https://www.4chan.org/derefer?url=' . $link . '">$1</a>',
            $m[0]
          );
        }
        else {
          return $m[0];
        }
      },
      $reason);
    }
    
    return [$reason, $refs_bid, $refs_md5, $refs_fid];
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
  
  /**
   * Get related bans by ban ID (for parsing ban ids in the private reason)
   */
  private function get_bans_by_id($ids) {
    $sql_ids = [];
    
    $data = [];
    
    foreach ($ids as $id) {
      if (!$id) {
        continue;
      }
      
      $sql_ids[] = (int)$id;
    }
    
    if (!$sql_ids) {
      return $data;
    }
    
    $clause = implode(',', $sql_ids);
    
    $sql = <<<SQL
SELECT SQL_NO_CACHE no, board, post_json, password as pwd, reason, host as ip, reverse,
UNIX_TIMESTAMP(now) as created_on, admin as created_by
FROM banned_users WHERE no IN ($clause)
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      return $data;
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      if ($row['post_json']) {
        $json = json_decode($row['post_json'], true);
        
        list($clean_sub, $is_spoiler) = $this->format_subject($json['sub']);
        
        if ($clean_sub !== '') {
          $row['sub'] = $clean_sub;
        }
        
        $names = $this->format_name($json['name']);
        
        $row['name'] = $names[0];
        
        if ($names[1]) {
          $row['tripcode'] = '!' . $names[1];
        }
        
        if ($json['ext']) {
          $row['filename'] = $json['filename'] . $json['ext'];
        }
        
        if ($json['com'] !== '') {
          $row['com'] = $json['com'];
        }
        
        if (isset($json['ua']) && $json['ua'] !== '') {
          $ua = decode_user_meta($json['ua']);
          $row['ua'] = $ua['browser_id'];
        }
        
        unset($row['post_json']);
      }
      
      list($row['public_reason'], $row['private_reason']) = explode('<>', $row['reason']);
      
      unset($row['reason']);
      
      $geo_loc = $this->get_geo_loc($row['ip']);
      
      if ($geo_loc) {
        $row['geo_loc'] = $geo_loc;
      }
      else {
        $row['geo_loc'] = 'N/A';
      }
      
      $asninfo = GeoIP2::get_asn($row['ip']);
      
      if ($asninfo) {
        $row['asn_name'] = $asninfo['aso'];
      }
      else {
        $row['asn_name'] = 'N/A';
      }
      
      $data[$row['no']] = $row;
    }
    
    return $data;
  }
  
  /**
   * Get related bans by MD5 (for parsing autobans in the private reason)
   */
  private function get_bans_by_md5($md5s) {
    $data = [];
    
    foreach ($md5s as $md5) {
      if (!preg_match('/^[a-f0-9]+$/', $md5)) {
        return $data;
      }
    }
    
    $clause = implode("','", $md5s);
    
    $sql = <<<SQL
SELECT SQL_NO_CACHE no, board, post_json, password as pwd, reason, host as ip, reverse, md5,
UNIX_TIMESTAMP(now) as created_on, admin as created_by
FROM banned_users WHERE md5 IN ('$clause')
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      return $data;
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      if ($row['post_json']) {
        $json = json_decode($row['post_json'], true);
        
        list($clean_sub, $is_spoiler) = $this->format_subject($json['sub']);
        
        if ($clean_sub !== '') {
          $row['sub'] = $clean_sub;
        }
        
        $names = $this->format_name($json['name']);
        
        $row['name'] = $names[0];
        
        if ($names[1]) {
          $row['tripcode'] = '!' . $names[1];
        }
        
        if ($json['ext']) {
          $row['filename'] = $json['filename'] . $json['ext'];
        }
        
        if ($json['com'] !== '') {
          $row['com'] = $json['com'];
        }
        
        if (isset($json['ua']) && $json['ua'] !== '') {
          $ua = decode_user_meta($json['ua']);
          $row['ua'] = $ua['browser_id'];
        }
        
        unset($row['post_json']);
      }
      
      list($row['public_reason'], $row['private_reason']) = explode('<>', $row['reason']);
      
      unset($row['reason']);
      
      $geo_loc = $this->get_geo_loc($row['ip']);
      
      if ($geo_loc) {
        $row['geo_loc'] = $geo_loc;
      }
      
      $asninfo = GeoIP2::get_asn($row['ip']);
      
      if ($asninfo) {
        $row['asn_name'] = $asninfo['aso'];
      }
      
      $data[$row['md5']] = $row;
    }
    
    return $data;
  }
  
  /**
   * Get related post filters by ID (for parsing autobans in the private reason)
   */
  private function get_post_filters_by_id($ids) {
    $sql_ids = [];
    
    $data = [];
    
    foreach ($ids as $id) {
      if (!$id) {
        continue;
      }
      
      $sql_ids[] = (int)$id;
    }
    
    if (!$sql_ids) {
      return $data;
    }
    
    $clause = implode(',', $sql_ids);
    
    $sql = <<<SQL
SELECT SQL_NO_CACHE id, pattern, description
FROM postfilter WHERE id IN ($clause)
SQL;
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      return $data;
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $data[$row['id']] = $row;
    }
    
    return $data;
  }
  
  private function success($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
  }
  
  private function error($message, $code = null, $data = null) {
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
  
  private function errorHTML($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  private function renderJSON($data) {
    header('Content-type: application/json');
    echo json_encode($data);
  }
  
  private function renderHTML($view) {
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function getTemplates() {
    $all_templates = array();
    
    $result = mysql_global_call('SELECT no, name FROM ban_templates');
    
    if (!mysql_num_rows($result)) {
      $this->error("Couldn't get ban templates");
    }
    
    while ($tpl = mysql_fetch_assoc($result)) {
      $all_templates[$tpl['no']] = $tpl['name'];
    }
    
    return $all_templates;
  }
  
  private function get_bans_summary($ip, $pass = null) {
    $base_query = <<<SQL
SELECT SQL_NO_CACHE UNIX_TIMESTAMP(`now`) as created_on,
UNIX_TIMESTAMP(`length`) as expires_on,
UNIX_TIMESTAMP(`unbannedon`) as unbanned_on
FROM banned_users
SQL;
    
    $query = $base_query . " WHERE host = '%s'";
    
    $res = mysql_global_call($query, $ip);
    
    if (!$res) {
      return array();
    }
    
    $bans = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $bans[] = $row;
    }
    
    if ($pass) {
      $query = $base_query . " WHERE active = 0 AND 4pass_id = '%s' AND host != '%s'";
      
      $res = mysql_global_call($query, $pass, $ip);
      
      if ($res) {
        while ($row = mysql_fetch_assoc($res)) {
          $bans[] = $row;
        }
      }
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    $limit = $_SERVER['REQUEST_TIME'] - 31536000; // 1 year
    
    $total_count = count($bans);
    $recent_ban_count = 0;
    $recent_warn_count = 0;
    $recent_duration = 0; // in days
    
    foreach ($bans as $ban) {
      if (!$ban['expires_on']) {
        continue;
      }
      
      if ($ban['created_on'] < $limit) {
        continue;
      }
      
      $ban_len = $ban['expires_on'] - $ban['created_on'];
      
      if ($ban_len <= 10) {
        ++$recent_warn_count;
      }
      else {
        if ($ban['unbanned_on']) {
          $spent_len = $ban['unbanned_on'] - $ban['created_on'];
          
          if ($spent_len > $ban_len) {
            $spent_len = $ban_len;
          }
        }
        else if ($ban['expires_on'] > $now) {
          $spent_len = $now - $ban['created_on'];
        }
        else {
          $spent_len = $ban_len;
        }
        
        $recent_duration += $spent_len;
        ++$recent_ban_count;
      }
    }
    
    if ($recent_duration) {
      $recent_duration = ceil($recent_duration / 86400.0);
    }
    
    return array(
      'total' => $total_count,
      'recent_bans' => $recent_ban_count,
      'recent_warns' => $recent_warn_count,
      'recent_days' => $recent_duration,
    );
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
  
  private function auto_update_appeal($appeal, $unban_in_hours) {
    $unban_in_hours = (int)$unban_in_hours;
    
    if ($unban_in_hours < 0) {
      return false;
    }
    
    $user = self::BY_AUTOBAN;
    
    $id = (int)$appeal['no'];
    
    // accept and unban
    if ($unban_in_hours === 0) {
      $ban_table = SQLLOGBAN;
      
      $query =<<<SQL
UPDATE `$ban_table` SET now = now, length = length, unbannedon = NOW(),
unbannedby = '$user', active = 0
WHERE no = $id
LIMIT 1
SQL;
      
      if (!mysql_global_call($query)) {
        return false;
      }
      
      $query =<<<SQL
UPDATE appeals SET email = ''
WHERE no = $id
LIMIT 1
SQL;
      
      if (!mysql_global_call($query)) {
        return false;
      }
    }
    // deny and unban in X hours
    else {
      $len_field = "DATE_ADD(NOW(), INTERVAL $unban_in_hours HOUR)"; 
      
      $query = "UPDATE banned_users SET length = $len_field WHERE no = $id LIMIT 1";
      
      if (!mysql_global_call($query)) {
        return false;
      }
      
      // plea history
      
      if ($appeal['plea_history']) {
        $plea_history = json_decode($appeal['plea_history']);
      }
      else {
        $plea_history = array();
      }
      
      $hist = array(
        'denied_on' => $_SERVER['REQUEST_TIME'],
        'denied_by' => $user,
        'plea' => $appeal['plea'],
        'matched' => $appeal['closed']
      );
      
      $plea_history[] = $hist;
      
      $plea_history = mysql_real_escape_string(json_encode($plea_history));
      
      $query =<<<SQL
UPDATE appeals
SET closed = 1,
appealcount = appealcount + 1,
closedby = '$user',
plea_history = '$plea_history',
email = ''
WHERE no = $id
SQL;
      
      if (!mysql_global_call($query)) {
        return false;
      }
    }
    
    return true;
  }
  
  private function get_proxy_appeal_type($appeal) {
    if ($appeal['admin'] !== self::BY_AUTOBAN) {
      return false;
    }
    
    if ($appeal['private_reason'] === self::PROXY_VPNGATE_REASON) {
      return self::PROXY_VPNGATE;
    }
    else if ($appeal['private_reason'] === self::PROXY_OPEN_REASON) {
      return self::PROXY_OPEN;
    }
    else {
      return false;
    }
  }
  
  /**
   * Handle appeals for proxy auto-bans automatically
   */
  private function handle_proxy_appeal($appeal, $proxy_type, $country) {
    $safe_isps = '/Verizon Wireless|T-Mobile USA|AT&T Services|^Sprint|Hutchison 3G|Bell Mobility/';
    
    $safe_countries = array('US', 'CA', 'GB', 'AU', 'DE', 'FR');
    
    $susp_countries = array(
      'AD','AE','AF','AG','AI','AL','AM','AN','AO','AQ','AS','AW','AX','AZ',
      'BB','BD','BF','BH','BI','BJ','BL','BM','BN','BO','BQ','BS','BT','BV','BW','BZ',
      'CC','CD','CF','CG','CI','CK','CM','CN','CR','CU','CV','CW','CX',
      'DJ','DM','DO','DZ',
      'EC','EG','EH','ER','ET',
      'FJ','FK','FM','FO',
      'GA','GD','GF','GG','GH','GI','GM','GN','GP','GQ','GS','GT','GU','GW','GY',
      'HK','HM','HN','HT',
      'IM','IO','IQ','IR','JE','JM','JO','KE','KG','KH','KI','KM','KN','KP','KW','KY','KZ',
      'LA','LB','LC','LK','LR','LS','LY',
      'MA','MD','MF','MG','MH','ML','MM','MN','MO','MP','MQ','MR','MS','MU','MV','MW','MZ',
      'NA','NC','NE','NF','NG','NI','NP','NR','NU',
      'OM','PA', 'PE','PF','PG','PK','PM','PN','PS','PW','PY','QA','RE','RW',
      'SA','SB','SC','SD','SH','SJ','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ',
      'TC','TD','TF','TG','TJ','TK','TM','TN','TO','TP','TR','TT','TV','TZ',
      'UG','UM','UZ','VA','VC','VG','VI','VU','WF','WS','YE','YT','YU','ZM','ZW','XX'
    );
    
    $proxy_countries = array('XX', 'A1', 'O1');
    
    $hours_len = 0;
    
    if (!isset($appeal['asn_name']) || !preg_match($safe_isps, $appeal['asn_name'])) {
      if (in_array($country, $safe_countries)) {
        $hours_len += 6;
      }
      else if (in_array($country, $susp_countries)) {
        $hours_len += 48;
      }
      else if (in_array($country, $proxy_countries)) {
        $hours_len += 336;
      }
      else {
        $hours_len += 18;
      }
    }
    
    if ($proxy_type === self::PROXY_VPNGATE) {
      $hours_len += 24;
    }
    
    return $this->auto_update_appeal($appeal, $hours_len);
  }
  
  private function get_geo_loc($ip) {
    $geoinfo = GeoIP2::get_country($ip);
    
    if ($geoinfo && isset($geoinfo['country_code'])) {
      $country = $geoinfo['country_code'];
      
      $geo_loc = array();
      
      if (isset($geoinfo['city_name'])) {
        $geo_loc[] = $geoinfo['city_name'];
      }
      
      if (isset($geoinfo['state_code'])) {
        $geo_loc[] = $geoinfo['state_code'];
      }
      
      $geo_loc[] = $geoinfo['country_name'];
      
      return implode(', ', $geo_loc);
    }
    else {
      return null;
    }
  }
  
  /**
   * Appeals
   */
  public function appeals() {
    $where = null;
    $this->search_query = '';
    
    if (isset($_GET['q'])) {
      $_GET['q'] = trim($_GET['q']);
      if (preg_match('/^[0-9]+$/', $_GET['q'])) {
        $this->search_query = (int)$_GET['q'];
        $where = 'appeals.no = ' . $this->search_query;
      }
      else if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $_GET['q'])) {
        $this->search_query = htmlspecialchars($_GET['q'], ENT_QUOTES);
        $where = "ban.host = '" . mysql_real_escape_string($_GET['q']) . "'";
      }
      else if (preg_match('/^[0-9A-Z]{10}$/', $_GET['q'])) {
        $this->search_query = htmlspecialchars($_GET['q'], ENT_QUOTES);
        $where = "ban.4pass_id = '" . mysql_real_escape_string($_GET['q']) . "'";
      }
    }
    
    if (!$where) {
      $where = 'ban.active = 1 AND appeals.closed != 1 AND (ban.length = 0 or ban.length >= NOW())';
    }
    
    $this->templates = $this->getTemplates();
    
    $query =<<<SQL
SELECT ban.board, ban.name, ban.global, ban.host, ban.reverse, ban.reason, ban.active,
ban.admin, ban.post_num, ban.4pass_id, ban.post_json, ban.xff, ban.template_id,
DATE_FORMAT(ban.now, '%m/%d/%y') as ban_date,
UNIX_TIMESTAMP(ban.now) as ban_start,
UNIX_TIMESTAMP(ban.length) as ban_end,
appeals.*
FROM banned_users ban
INNER JOIN appeals ON appeals.no = ban.no
WHERE $where
ORDER BY 4pass_id DESC, updated DESC
SQL;
    
    $res = mysql_global_call($query);
    
    $this->appeals = array();
    
    $this->references = array(
      'bid' => [],
      'md5' => [],
      'fid' => [],
    );
    
    if (mysql_num_rows($res) > 0) {
      $salt = $this->getSalt();
      
      while ($appeal = mysql_fetch_assoc($res)) {
        // Geo IP
        $geo_loc = $this->get_geo_loc($appeal['host']);
        
        if ($geo_loc) {
          $appeal['geo_loc'] = $geo_loc;
        }
        else {
          $appeal['geo_loc'] = null;
          $country = 'XX';
        }
        
        $asninfo = GeoIP2::get_asn($appeal['host']);
        
        if ($asninfo) {
          $appeal['asn_name'] = $asninfo['aso'];
        }
        
        // ---
        
        $appeal['ban_history'] = $this->get_bans_summary($appeal['host'], $appeal['4pass_id']);
        
        $reasons = explode('<>', $appeal['reason']);
        
        $appeal['reason'] = $reasons[0];
        
        if ($reasons[1] !== '') {
          list($priv, $refs_bid, $refs_md5, $refs_fid) = $this->formatPrivateReason($reasons[1], $appeal['board']);
          
          $appeal['private_reason'] = $priv;
          
          if ($refs_bid) {
            $this->references['bid'] += $this->get_bans_by_id($refs_bid);
          }
          
          if ($refs_md5) {
            $this->references['md5'] += $this->get_bans_by_md5($refs_md5);
          }
          
          if ($refs_fid) {
            $this->references['fid'] += $this->get_post_filters_by_id($refs_fid);
          }
        }
        
        // proxy autoban appeal
        $proxy_type = $this->get_proxy_appeal_type($appeal);
        
        if ($proxy_type) {
          if ($this->handle_proxy_appeal($appeal, $proxy_type, $country)) {
            continue;
          }
        }
        
        // normal appeal
        if ($appeal['post_json']) {
          $appeal['post'] = json_decode($appeal['post_json'], true);
          
          list($clean_sub, $is_spoiler) = $this->format_subject($appeal['post']['sub']);
          
          $appeal['post']['sub'] = $clean_sub;
          $appeal['post']['spoiler'] = $is_spoiler;
          
          if (isset($appeal['post']['ua'])) {
            $appeal['post']['user_info'] = decode_user_meta($appeal['post']['ua']);
          }
        }
        
        $names = $this->format_name($appeal['name']);
        
        $appeal['name'] = $names[0];
        
        if ($names[1]) {
          $appeal['tripcode'] = '!' . $names[1];
        }
        
        if ($appeal['post']['ext'] && !$this->blockedPreviews[$appeal['template_id']]) {
          $appeal['post']['ban_thumb'] = sha1($appeal['board'] . $appeal['post_num'] . $salt);
        }
        
        if ($appeal['ban_end']) {
          $delta = $appeal['ban_end'] - $_SERVER['REQUEST_TIME'];
          $appeal['ban_left'] = $this->getDuration($delta);
        }
        
        $link = return_archive_link($appeal['board'], $appeal['post_num'], false, true);
        
        if ($link !== false) {
          $appeal['link'] = rawurlencode($link);
        }
        
        $delta = $_SERVER['REQUEST_TIME'] - $appeal['ban_start'];
        $appeal['ban_ago'] = $this->getDuration($delta);
        
        // Is IP rangebanned
        if ($this->is_ip_rangebanned($appeal['host'])) {
          $appeal['is_rangebanned'] = true;
        }
        
        $this->appeals[] = $appeal;
      }
      
      if (!$this->references['bid']) {
        unset($this->references['bid']);
      }
      
      if (!$this->references['md5']) {
        unset($this->references['md5']);
      }
      
      if (!$this->references['fid']) {
        unset($this->references['fid']);
      }
    }
    
    $this->renderHTML('appeals');
  }
  
  /**
   * Deny
   */
  public function deny() {
    if (!isset($_POST['id'])) {
      $this->error('Bad request');
    }
    
    $id = (int)$_POST['id'];
    $user = mysql_real_escape_string($_COOKIE['4chan_auser']);
    
    $query = "SELECT admin FROM `" . SQLLOGBAN . "` WHERE no = $id";
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error('Database error (1)');
    }
    
    if (!$this->isManager && mysql_fetch_row($result)[0] == $_COOKIE['4chan_auser']) {
      $this->error("You can't deny appeals for bans you issued yourself.");
    }
    
    $query =<<<SQL
SELECT SQL_NO_CACHE plea, closed, plea_history, (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(updated)) as delay
FROM appeals WHERE no = $id
SQL;
    
    $res = mysql_global_call($query);
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Appeal not found');
    }
    
    $appeal = mysql_fetch_assoc($res);
    
    if ($appeal['closed'] == 1) {
      $this->error('This appeal was already denied.');
    }
    
    $delay = $appeal['delay'];
    
    if ($appeal['plea_history']) {
      $plea_history = json_decode($appeal['plea_history']);
    }
    else {
      $plea_history = array();
    }
    
    if (isset($_POST['days'])) {
      $days = $_POST['days'];
      
      if ($days[0] == '+') {
        $days = ltrim($days, '+');
        $days_relative = true;
      }
      else {
        $days_relative = false;
      }
      
      $days = (int)$days;
      
      if ($days == 0 || $days > self::MAX_BAN_DAYS) {
        $this->error('Invalid ban length.');
      }
      
      if ($days === -1) {
        if ($days_relative) {
          $this->error('Invalid ban length.');
        }
        
        $len_field = "0"; 
      }
      else if ($days_relative) {
        $len_field = "DATE_ADD(NOW(), INTERVAL $days DAY)"; 
      }
      else {
        $len_field = "DATE_ADD(now, INTERVAL $days DAY)"; 
      }
      
      $query = "UPDATE banned_users SET length = $len_field WHERE no = $id LIMIT 1";
      
      mysql_global_call($query);
      
      if (mysql_affected_rows() !== 1) {
        $this->error('Database error (bu1)');
      }
    }
    
    $hist = array(
      'denied_on' => $_SERVER['REQUEST_TIME'],
      'denied_by' => $user,
      'plea' => $appeal['plea'], 
      'matched' => $appeal['closed']
    );
    
    $plea_history[] = $hist;
    
    $plea_history = mysql_real_escape_string(json_encode($plea_history));
    
    $query =<<<SQL
UPDATE appeals
SET closed = 1,
appealcount = appealcount + 1,
closedby = '$user',
plea_history = '$plea_history',
email = ''
WHERE no = $id
SQL;
    
    mysql_global_call($query);
    
    if (mysql_affected_rows() !== 1) {
      $this->error('Database error');
    }
    
    $query =<<<SQL
INSERT INTO appeal_stats (appeal_id, username, accepted, delay)
VALUES ($id, '$user', 0, $delay)
SQL;
    
    mysql_global_call($query);
    
    $this->success();
  }
  
  public function logs() {
    if (!has_flag('developer')) {
      $this->error('Forbidden');
    }
    
    $query = "SELECT appeals.*, banned_users.reason FROM appeals INNER JOIN banned_users ON appeals.no = banned_users.no WHERE closedby = 'Auto-ban' ORDER BY no DESC LIMIT 25";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error');
    }
    
    header('Content-Type: text/plain');
    
    while ($row = mysql_fetch_assoc($res)) {
      print_r($row);
      echo "\n\n";
    }
  }
  
  /**
   * Contact
   */
  public function contact() {
    if (!$this->isManager) {
      $this->error("Can't let you do that");
    }
    
    if (!isset($_POST['id']) || !isset($_POST['message'])) {
      $this->error('Bad request');
    }
    
    if ($_POST['message'] == '') {
      $this->error('Message is empty');
    }
    
    if (mb_strlen($_POST['message']) > 10000) {
      $this->error('Message too long');
    }
    
    $id = (int)$_POST['id'];
    
    // Getting the appeal
    $query = "SELECT email, plea FROM appeals WHERE no = $id";
    
    $result = mysql_global_call($query);
    $appeal = mysql_fetch_assoc($result);
    
    if (!$appeal) {
      $this->error("That appeal doesn't exist.");
    }
    
    $email = $appeal['email'];
    
    $subject = "Regarding Your 4chan Ban Appeal (#$id)";
    
    $message = $_POST['message'] . "\n\n--Anonymous ## Mod\n\n\nYour appeal was:\n================\n\n" . $appeal['plea'];

    $headers = "From: 4chan Ban Appeals <appeals@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    if (mail($email, $subject, $message, $headers, '-f appeals@4chan.org')) {
      $this->success();
    }
    else {
      $this->error('Email rejected');
    }
  }
  
  private function sort_bans_func($a, $b) {
    if ($a['no'] == $b['no']) {
        return 0;
    }
    
    return ($a['no'] > $b['no']) ? -1 : 1;
  }
  
  /**
   * Details
   */
  public function details() {
    if (!isset($_GET['id'])) {
      $this->error('Bad request');
    }
    
    $id = (int)$_GET['id'];
    
    // Getting the appeal
    $query =<<<SQL
SELECT SQL_NO_CACHE no, UNIX_TIMESTAMP(updated) as updated_on, closed, closedby, appealcount, plea_history
FROM appeals
WHERE no = $id
SQL;
    
    $result = mysql_global_call($query);
    $appeal = mysql_fetch_assoc($result);
    
    if (!$appeal) {
      $this->error("That appeal doesn't exist.");
    }
    
    // Getting the ban
    $result = mysql_global_call("SELECT no, host, `4pass_id` FROM banned_users WHERE no = $id");
    $ban = mysql_fetch_assoc($result);
    
    if (!$ban) {
      $this->error("That ban doesn't exist.");
    }
    
    // Getting the ban history
    $host = $ban["host"];
    
    $ip_sql = mysql_real_escape_string($host);
    
    if ($ip_sql === false) {
      $this->error('Database Error (desc1)');
    }
    
    $clauses = array(
      "host = '$ip_sql'"
    );
    
    if (!$ban['4pass_id']) {
      $pass_id = null;
    }
    else {
      $pass_id = $ban['4pass_id'];
      $pass_sql = mysql_real_escape_string($pass_id);
      
      if ($pass_sql === false) {
        $this->error('Database Error (desc2)');
      }
      
      $clauses[] = "`4pass_id` = '$pass_sql' AND (active = 1 OR active = 0)";
    }
    
    $dups = array();
    
    $history = array();
    
    foreach ($clauses as $clause) {
      $query =<<<SQL
SELECT SQL_NO_CACHE no, now, name, host, reverse, board, post_num, global, admin, post_json,
reason, unbannedby, `4pass_id`,
UNIX_TIMESTAMP(now) as ban_start,
UNIX_TIMESTAMP(length) as ban_end,
DATE_FORMAT(now, '%m/%d/%Y %H:%i:%s') as ban_date
FROM banned_users
WHERE $clause
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error (mgc1)');
      }
      
      while ($row = mysql_fetch_assoc($res)) {
        if (isset($dups[$row['no']])) {
          continue;
        }
        
        $dups[$row['no']] = true;
        
        $names = explode('#', $row['name']);
        
        if ($names[1]) {
          $row['name'] = $names[0];
          $row['tripcode'] = '!' . $names[1];
        }
        
        if ($row['ban_end']) {
          $delta = (int)($row['ban_end'] - $row['ban_start']);
          if ($delta < 1) {
            $row['ban_length'] = '';
          }
          else {
            $row['ban_length'] = $this->getDuration($delta);
          }
        }
        else {
          $row['ban_length'] = 'Permanent';
        }
        
        $link = return_archive_link($row['board'], $row['post_num'], false, true);
        
        if ($link !== false) {
          $row['link'] = rawurlencode($link);
        }
        
        if ($row['4pass_id']) {
          if ($row['4pass_id'] == $ban['4pass_id']) {
            $row['pass_status'] = self::PASS_SAME;
          }
          else {
            $row['pass_status'] = self::PASS_OTHER;
          }
        }
        else {
          $row['pass_status'] = self::PASS_NONE;
        }
        
        unset($row['4pass_id']);
        
        $history[] = $row;
      }
    }
    
    usort($history, array($this, 'sort_bans_func'));
    
    if ($pass_id) {
      $appeal['pass_ban'] = true;
    }
    
    if ($appeal['plea_history']) {
      $appeal['plea_history'] = json_decode($appeal['plea_history'], true);
      foreach ($appeal['plea_history'] as &$hist) {
        $hist['denied_on'] = date('m/d/y', $hist['denied_on']);
      }
    }
    
    $data = array(
      'appeal' => $appeal,
      'ip' => $host,
      'history' => $history
    );
    
    $this->success($data);
  }
  
  public function accept() {
    if (!isset($_POST['id'])) {
      $this->error('Bad request');
    }
    
    $this->init_cloudflare();
    
    $id = (int)$_POST['id'];
    $user = mysql_real_escape_string($_COOKIE['4chan_auser']);
    
    $query = "SELECT active, board, no, post_json FROM `" . SQLLOGBAN . "` WHERE no = $id";
    
    $result = mysql_global_call($query);
    
    if (!$result) {
      $this->error('Database error (1)');
    }
    
    $ban = mysql_fetch_assoc($result);
    
    if (!$ban) {
      $this->error('Ban id not found');
    }
    
    if ($ban['active'] == 0) {
      $this->error('This ban is not active');
    }
    
    $salt = file_get_contents('/www/keys/legacy.salt');
    
    $json = json_decode($json, true);
    
    $hash = sha1($ban['board'] . $ban['no'] . $salt);
    
    $fpath = "/www/4chan.org/web/images/bans/thumb/{$ban['board']}/{$hash}s.jpg";
    
    if (file_exists($fpath)) {
      unlink($fpath);
    }
    
    cloudflare_purge_url("http://images.4chan.org/bans/thumb/$board/{$hash}s.jpg");
    
    // Disable ban
    $ban_table = SQLLOGBAN;
    $query =<<<SQL
UPDATE `$ban_table` SET now = now, length = length, unbannedon = NOW(),
unbannedby = '$user', active = 0
WHERE no = $id
LIMIT 1
SQL;
    
    mysql_global_call($query);
    
    // Send the response to client, no need to wait for mail()
    $this->success();
    
    fastcgi_finish_request();
    
    // Send the email
    $query =<<<SQL
SELECT email, plea, (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(updated)) as delay
FROM appeals WHERE no = $id
SQL;
    
    $result = mysql_global_call($query);
    
    $appeal = mysql_fetch_assoc($result);
    
    if (!$appeal) {
      return;
    }
    
    if ($appeal['email'] !== '') {
      $email = $appeal['email'];
      
      $mail_file = 'data/mail_appeal_accepted.txt';
      
      if (!file_exists($mail_file)) {
        die('Cannot find e-mail file.');
      }
      
      $lines = file($mail_file);
      
      $subject = trim(array_shift($lines));
      $message = implode('', $lines);
      
      $values = array(
        '{{ID}}' => $id,
        '{{PLEA}}' => $appeal['plea']
      );
      
      $subject = str_replace(array_keys($values), array_values($values), $subject);
      $message = str_replace(array_keys($values), array_values($values), $message);
      
      $headers = "From: 4chan <noreply@4chan.org>\r\n";
      $headers .= "MIME-Version: 1.0\r\n";
      $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
      
      mail($email, $subject, $message, $headers, '-f noreply@4chan.org');
    }
    
    // Clear PII
    $query =<<<SQL
UPDATE appeals SET email = ''
WHERE no = $id
LIMIT 1
SQL;
    
    mysql_global_call($query);
    
    // cleanup old entries
    $query =<<<SQL
DELETE FROM appeal_stats
WHERE created_on < DATE_SUB(NOW(), INTERVAL {$this->statsRange} DAY)
SQL;
    
    mysql_global_call($query);
    
    // Update stats
    $query =<<<SQL
INSERT INTO appeal_stats (appeal_id, username, accepted, delay)
VALUES ($id, '$user', 1, {$appeal['delay']})
SQL;
    
    mysql_global_call($query);
  }
  
  public function stats() {
    if (!has_level('manager') && !has_flag('developer')) {
      $this->error('Bad request');
    }
    
    // cleanup old entries
    $query =<<<SQL
DELETE FROM appeal_stats
WHERE created_on < DATE_SUB(NOW(), INTERVAL {$this->statsRange} DAY)
SQL;
    
    $res = mysql_global_call($query);
    
    $query = "SELECT username, accepted, delay FROM appeal_stats";
    
    $res = mysql_global_call($query);
    
    $this->total = mysql_num_rows($res);
    $this->average_delay = 0;
    $this->total_accepted = 0;
    $this->users = array();
    
    if (!$this->total) {
      $this->errorHTML('Empty dataset');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->average_delay += (int)$row['delay'];
      
      if (!isset($this->users[$row['username']])) {
        $this->users[$row['username']] = array(
          'accepted' => 0,
          'denied' => 0,
          'total' => 0,
          'accept_rate' => 0.0
        );
      }
      
      $this->users[$row['username']]['total']++;
      
      if ($row['accepted']) {
        $this->users[$row['username']]['accepted']++;
        $this->total_accepted++;
      }
    }
    
    foreach ($this->users as $username => &$val) {
      $val['denied'] = $val['total'] - $val['accepted'];
      $val['accept_rate'] = round(($val['accepted'] / $val['total'] ) * 100, 1);
    }
    
    uasort($this->users, array('APP', 'sort_users'));
    
    $this->average_delay = (int)($this->average_delay / $this->total);
    $this->total_denied = $this->total - $this->total_accepted;
    $this->accept_rate = round(($this->total_accepted / $this->total) * 100, 1);
    
    $this->renderHTML('appeals-stats');
  }
  
  /**
   * Main
   */
  public function run() {
    if (isset($this->params['action'])) {
      $action = $this->params['action'];
    }
    else {
      $action = 'appeals';
    }
    
    if (in_array($action, $this->actions)) {
      $this->$action();
    }
    else {
      $this->error('Bad request');
    }
  }
}

$ctrl = new APP();
$ctrl->run();
