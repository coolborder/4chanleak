<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

set_time_limit(120);
/*
$mysql_suppress_err = false;
ini_set('display_errors', 1);
error_reporting(E_ALL);
*/
class App {
  protected
    // Routes
    $actions = array(
      'index'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Returns the data as json
   */
  final protected function success($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
    die();
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
  
  private function renderView() { ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Proxy List Importer</title>
  <link rel="stylesheet" type="text/css" href="/css/bans.css">
  <script type="text/javascript" src="/js/admincore.js"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/openproxies.js"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Proxy List Importer</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="/developer/vpngate">VPN Gate</a></li>
</ul>
</div>
<div id="content">
<form class="form ban-ips-form" id="js-form" action="" method="POST">
  <table>
    <tr>
      <th class="cell-top">Data</th>
      <td><textarea id="js-entries" name="entries" required></textarea></td>
    </tr>
    <tr>
      <th>Public Reason</th>
      <td><input type="text" value="Proxy/VPN" disabled></td>
    </tr>
    <tr>
      <th>Private Reason</th>
      <td><input type="text" value="Open proxy list - unban dynamic IPs" disabled></td>
    </tr>
    <tr>
      <th>Length</th>
      <td><input id="field-length" type="text" value="90" disabled></td>
    </tr>
    <tr>
      <th>No Rangebans</th>
      <td><label><input type="checkbox" name="no_rangebans" disabled checked value="1"> Skip rangebanned IPs.</label></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><span id="js-progress"></span><button id="js-submit-btn" class="button btn-deny" type="submit">Submit</button> <button class="button btn-other" type="reset">Reset</button></td>
      </tr>
    </tfoot>
  </table>
</form>
<table class="items-table compact-table">
  <tr>
    <th>Banned</th>
    <th>Skipped</th>
    <th>Errors</th>
  </tr>
  <tr>
    <td class="cnt-pre" id="js-cell-ok"></td>
    <td class="cnt-pre" id="js-cell-skip"></td>
    <td class="cnt-pre" id="js-cell-error"></td>
  </tr>
</table>
</div>
<footer></footer>
</body>
</html>
  <?php
  }
  
  private function is_ip_rangebanned($ip) {
    $long_ip = ip2long($ip);
    
    if (!$long_ip) {
      return false;
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
  
  private function ban_ips() {
    $days = 90;
    $reban_interval = '12 HOUR';
    
    $public_reason = 'Proxy/VPN';
    $private_reason = 'Open proxy list - unban dynamic IPs';
    
    $reason = "$public_reason<>$private_reason";
    
    $banned_by = 'Auto-ban';
    
    $all_entries = explode("\n", trim($_POST['entries']));
    
    $length = date('Y-m-d H:i:s', time() + $days * ( 24 * 60 * 60 ));
    
    //$no_rangebans = isset($_POST['no_rangebans']) && $_POST['no_rangebans'];
    $no_rangebans = true;
    
    $status_ok = array();
    $status_skip = array();
    $status_error = array();
    
    $dups = array();
    
    $chunks = array_chunk($all_entries, 50);
    
    foreach ($chunks as $entries) {
      mysql_global_call('START TRANSACTION');
      
      foreach ($entries as $entry) {
        $ip = trim($entry);
        
        if (!$ip) {
          continue;
        }
        
        if (strpos($ip, ':')) {
          $ip = explode(':', $ip)[0];
        }
        
        if (!ip2long($ip)) {
          continue;
        }
        
        if (isset($dups[$ip])) {
          continue;
        }
        
        $dups[$ip] = true;
        
        if ($no_rangebans && $this->is_ip_rangebanned($ip)) {
          $status_skip[] = "Rangebanned: " . htmlspecialchars($ip, ENT_QUOTES);
          continue;
        }
        
        $query = "SELECT no FROM banned_users WHERE host = '%s' AND global = 1 "
          . "AND (length = '0000-00-00 00:00:00' OR length > DATE_ADD(NOW(), INTERVAL $reban_interval)) "
          . "AND (active = 1 OR unbannedon >= DATE_SUB(NOW(), INTERVAL 7 DAY)) LIMIT 1";
        
        $res = mysql_global_call($query, $ip);
        
        if (mysql_num_rows($res)) {
          $status_skip[] = "Already banned: " . htmlspecialchars($ip, ENT_QUOTES);
          continue;
        }
        
        $status_ok[] = htmlspecialchars($ip, ENT_QUOTES);
        
        $query = <<<SQL
INSERT INTO `banned_users` (global, board, host, reverse, reason, admin,
zonly, length, name, 4pass_id, post_num, admin_ip)
VALUES (1, '', '%s', '%s', '%s', '%s',
0, '%s', '', '', 0, '%s')
SQL;
        
        mysql_global_do($query, $ip, $ip, $reason, $banned_by,
          $length, $_SERVER['REMOTE_ADDR']
        );
      }
             
      mysql_global_call('COMMIT');
    }
    
    $this->success(array(
      'ok' => $status_ok,
      'skip' => $status_skip,
      'error' => $status_error)
    );
  }
  
  /**
   * Index
   */
  public function index() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      $this->status = $this->ban_ips();
      return;
    }
    
    $this->renderView();
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
