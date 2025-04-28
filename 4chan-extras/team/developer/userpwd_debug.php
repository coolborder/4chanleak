<?php
//require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/userpwd.php';

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index'
    )
  ;
  
  /**
   * Index
   */
  public function index() {?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Userpwd</title>
</head>
<body>
<form method="POST" action="/developer/userpwd_debug.php">ip: <input type="text" name="ip"><br>pwd: <input type="text" name="pwd"><br>domain: <select name="domain"><option value="4chan.org">4chan.org</option><option value="4channel.org">4channel.org</option></select><button type="submit">Submit</button></form>
</body>
</html>
<?php }
  private function getPreciseDuration($delta) {
    if ($delta < 1) {
      return '0';
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
  
  public function process() {
    $userpwd = new UserPwd($_POST['ip'], $_POST['domain'], $_POST['pwd']);
    
    if (!$userpwd) {
      die('Bad pwd');
    }
    
    header('Content-Type: text/plain');
    
    echo("pwd: " . $userpwd->getPwd() . "\n");
    echo("pwdLifetime: " . $this->getPreciseDuration($userpwd->pwdLifetime()) . "\n");
    echo("maskLifetime: " . $this->getPreciseDuration($userpwd->maskLifetime()) . "\n");
    echo("ipLifetime: " . $this->getPreciseDuration($userpwd->ipLifetime()) . "\n");
    echo("idleLifetime: " . $this->getPreciseDuration($userpwd->idleLifetime()) . "\n");
    echo("isNeverUsed: " . (int)$userpwd->isNeverUsed() . "\n");
    echo("isUsedOnlyOnce: " . (int)$userpwd->isUsedOnlyOnce() . "\n");
    echo("isNew: " . (int)$userpwd->isNew() . "\n");
    echo("postCount: " . (int)$userpwd->postCount() . "\n");
    echo("imgCount: " . (int)$userpwd->imgCount() . "\n");
    echo("threadCount: " . (int)$userpwd->threadCount() . "\n");
    echo("reportCount: " . (int)$userpwd->reportCount() . "\n");
    echo("ipChangeScore: " . (int)$userpwd->ipChangeScore() . "\n");
    echo("lastActionLifetime: " . $this->getPreciseDuration($userpwd->lastActionLifetime()) . "\n");
    echo("Error: " . $userpwd->errno);
  }
  
  /**
   * Main
   */
  public function run() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->process();
    }
    else {
      $this->index();
    }
  }
}

$ctrl = new App();
$ctrl->run();
