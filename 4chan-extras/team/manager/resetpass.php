<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'reset'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const
    EMAIL_FILE = '../data/mail_reset_password.txt',
    SALT_FILE = '/www/keys/2014_admin.salt'
  ;
  
  const
    HTPASSWD_CMD = '/usr/local/www/bin/htpasswd -b %s %s %s' // nginx
  ;
  
  const
    MODS = '/www/global/htpasswd/moderators',
    JANITORS = '/www/global/htpasswd/janitors',
    JANITORS_NGINX = '/www/global/htpasswd/janitors_nginx',
    ADMINS = '/www/global/htpasswd/admins',
    MANAGERS = '/www/global/htpasswd/managers',
    DEVS = '/www/global/htpasswd/developers',
    DEVS_NGINX = '/www/global/htpasswd/developers_nginx'
  ;
  
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
  
  private function sendEmail($email, $values = null) {
    $mail_file = self::EMAIL_FILE;
    
    if (!file_exists($mail_file)) {
      $this->error('Cannot find e-mail file.');
    }
    
    $lines = file($mail_file);
    
    $subject = trim(array_shift($lines));
    $message = implode('', $lines);
    
    if ($values) {
      $message = str_replace(array_keys($values), array_values($values), $message);
    }
    
    $headers = "From: Team 4chan <janitorapps@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $opts = '-f janitorapps@4chan.org';
    
    return mail($email, $subject, $message, $headers, $opts);
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    require_once(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  private function getRandomHexBytes($length = 10) {
    $bytes = openssl_random_pseudo_bytes($length);
    return bin2hex($bytes);
  }
  
  private function updateHtpasswd($file, $username, $password, $nginx = false) {
    if ($nginx) {
      $args = '-b';
    }
    else {
      $args = '-Bb -C 10';
    }
    
    system("/usr/local/www/bin/htpasswd $args "
      . escapeshellarg($file) . " "
      . escapeshellarg($username) . " "
      . escapeshellarg($password),
      $ret_status
    );
    
    if ($ret_status != 0) {
      $this->error(self::S_ERROR . ' (pwd' . (int)$ret_status . ')');
    }
  }
  
  /**
   * Index
   */
  public function index() {
    $this->renderHTML('resetpass');
  }
  
  /**
   * Create user
   */
  public function reset() {
    // from lib/auth.php
    global $levelorderf;
    
    if (!isset($_POST['username']) || $_POST['username'] == '') {
      $this->error('Username cannot be empty.');
    }
    
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid or expired OTP.');
    }
    
    $username = $_POST['username'];
    
    $query = "SELECT * FROM mod_users WHERE username = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $username);
    
    if (!$res) {
      $this->error('Database error (1)');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error("This user doesn't exist");
    }
    
    $user = mysql_fetch_array($res);
    
    $email = $user['email'];
    $level = $user['level'];
    $flags = $user['flags'];
    
    $num_level = $levelorderf[$level];
    
    if (!has_level('admin') && $num_level >= $levelorderf['manager']) {
      $this->error('You cannot reset users equal to or above your own level.');
    }
    
    $plain_password = $this->getRandomHexBytes(8);
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    
    $query = <<<SQL
UPDATE mod_users SET password_expired = 1, password = '%s'
WHERE username = '%s' LIMIT 1
SQL;
    
    $res = mysql_global_call($query, $hashed_password, $username);
    
    if (!$res) {
      $this->error('Database error (2)');
    }
    
    if (mysql_affected_rows() < 1) {
      $this->error('Database error (3)');
    }
    
    $isAdmin = $user['level'] === 'admin';
    $isManager = ($user['level'] === 'manager') || ($user['username'] === 'desuwa');
    $isMod = $user['level'] === 'mod';
    $isJanitor = $user['level'] === 'janitor';
    $isDev = strpos($user['flags'], 'developer') !== false;
    
    $this->updateHtpasswd(self::JANITORS, $username, $plain_password);
    $this->updateHtpasswd(self::JANITORS_NGINX, $username, $plain_password, true);
    
    if ($isAdmin || $isManager || $isMod) {
      $this->updateHtpasswd(self::MODS, $username, $plain_password);
    }
    
    if ($isAdmin || $isManager) {
      $this->updateHtpasswd(self::MANAGERS, $username, $plain_password);
    }
    
    if ($isAdmin) {
      $this->updateHtpasswd(self::ADMINS, $username, $plain_password);
    }
    
    if ($isDev) {
      $this->updateHtpasswd(self::DEVS, $username, $plain_password);
      $this->updateHtpasswd(self::DEVS_NGINX, $username, $plain_password, true);
    }
    
    // Send instructions email
    $values = array(
      '{{PWD}}' => $plain_password,
    );
    
    $this->no_email = isset($_POST['no_email']) && $_POST['no_email'];
    
    if ($this->no_email) {
      $this->success_msg = 'Done. The account is now inaccessible.';
    }
    
    // Don't wait for sendEmail()
    $this->success(null, true);
    
    fastcgi_finish_request();
    
    if (!$this->no_email) {
      $this->sendEmail($email, $values);
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
