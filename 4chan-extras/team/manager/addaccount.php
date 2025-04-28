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
      'create'
    ),
    
    // corresponds to levels from lib/auth.php
    $_levels = array(
      1 => 'janitor',
      10 => 'mod',
      20 => 'manager'
    ),
    
    // matches keys from the $_levels array above
    $_level_labels = array(
      1 => 'Janitor',
      10 => 'Moderator',
      20 => 'Manager'
    ),
    
    // matches keys from the $_levels array above
    $_allowed_levels = array(
      'admin' => array(1, 10, 20),
      'manager' => array(1)
    )
  ;
  
  const TPL_ROOT = '../views/';
  
  const
    EMAIL_FILE = '../data/mail_add_account.txt',
    EMAIL_FILE_DIRECT = '../data/mail_account_created.txt',
    SALT_FILE = '/www/keys/2014_admin.salt',
    HTPASSWD_CMD = '/usr/local/www/bin/htpasswd -b %s %s %s',
    HTPASSWD_TMP =  '/www/global/htpasswd/temp_agreement_nginx',
    USERNAME_MAX_LEN = 50,
    FLAGS_MIN_LEVEL = 10 // minimal level for having flags
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
  
  private function sendEmail($email, $mail_file, $values = null) {
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
  
  private function getAllowedLevels() {
    if (has_level('admin') || has_flag('developer')) {
      return $this->_allowed_levels['admin'];
    }
    else if (has_level('manager')) {
      return $this->_allowed_levels['manager'];
    }
    else {
      return false;
    }
  }
  
  private function canSetFlags() {
    return has_level('admin');
  }
  
  /**
   * Index
   */
  public function index() {
    $this->levels = $this->getAllowedLevels();
    
    if (!$this->levels) {
      $this->error('Internal Server Error');
    }
    
    $this->renderHTML('addaccount');
  }
  
  /**
   * Create user
   */
  public function create() {
    $fields = array('username' => 'Username', 'email' => 'E-mail', 
      'level' => 'Level', 'otp' => '2FA OTP');
    
    foreach ($fields as $field => $label) {
      if (!isset($_POST[$field]) || $_POST[$field] == '') {
        $this->error($label . ' cannot be empty.');
      }
    }
    
    // OTP
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      $this->error('Invalid or expired OTP.');
    }
    
    // Username
    $username = $_POST['username'];
    
    if (strlen($username) > self::USERNAME_MAX_LEN) {
      $this->error('Username cannot be longer than ' . self::USERNAME_MAX_LEN . ' characters.');
    }
    
    if (!preg_match('/^[-_a-zA-Z0-9]+$/', $username)) {
      $this->error('Username contains invalid characters.');
    }
    
    // E-mail
    $email = $_POST['email'];
    
    // Level and Flags
    $level = (int)$_POST['level'];
    
    $allowed_levels = $this->getAllowedLevels();
    
    if (!$allowed_levels) {
      $this->error('Internal Server Error (1)');
    }
    
    if (!in_array($level, $allowed_levels)) {
      $this->error("You are not allowed to create accounts of this level.");
    }
    
    if (!isset($this->_levels[$level])) {
      $this->error('Invalid level');
    }
    
    $str_level = $this->_levels[$level];
    
    $has_dev_flag = false;
    
    if (isset($_POST['flags']) && $_POST['flags'] !== '' && $this->canSetFlags()) {
      if ($level < self::FLAGS_MIN_LEVEL) {
        $this->error('You cannot set flags for an account of this level.');
      }
      $flags = preg_split('/[^_a-z0-9]+/i', $_POST['flags']);
      
      $has_dev_flag = in_array('developer', $flags);
      
      $flags = implode(',', $flags);
    }
    else {
      $flags = '';
    }
    
    $allow = preg_split('/[^a-z0-9]+/i', $_POST['allow']);
    $allow = implode(',', $allow);
    
    // ---
    
    $query = "SELECT username FROM mod_users WHERE LOWER(username) = '%s'";
    $res = mysql_global_call($query, strtolower($username));
    
    if (mysql_num_rows($res) !== 0) {
      $this->error('This user already exists.');
    }
    
    // ---
    
    $create_direct = has_level('manager') && isset($_POST['no_agreement']) && $_POST['no_agreement'] !== '';
    
    if ($create_direct) {
      $plain_password = $this->getRandomHexBytes(8);
      $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
      
      // Insert user FIXME (signed agreement and pass expires should be 1)
      $query = <<<SQL
INSERT INTO mod_users( username, password, flags, level, allow, deny, email,
janitorapp_id, agreement_key, signed_agreement, password_expired)
VALUES('%s', '%s', '%s', '%s', '%s', '', '%s', 0, '', 1, 1)
SQL;
      
      $res = mysql_global_call($query,
        $username, $hashed_password, $flags,
        $str_level, $allow, $email
      );
    }
    else {
      // FIXME
      //$this->error("Account creation is locked");
      
      $plain_password = $this->getRandomHexBytes(32);
      $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
      
      $auth_key = $this->getRandomHexBytes(32);
      $temp_http_passwd = $this->getRandomHexBytes(32);
      $admin_salt = file_get_contents(self::SALT_FILE);
      
      if (!$auth_key || ! $temp_http_passwd || !$admin_salt) {
        $this->error('Internal Server Error (2)');
      }
      
      $hashed_auth_key = hash('sha256', $auth_key . $admin_salt);
      
      // Check if the auth key is already used somewhere else
      $query = "SELECT * FROM janitor_apps WHERE agreement_key = '%s' LIMIT 1";
      $res = mysql_global_call($query, $hashed_auth_key);
      
      if (mysql_num_rows($res) !== 0) {
        die('Database error (1)');
      }
      
      $query = "SELECT * FROM signed_agreements WHERE agreement_key = '%s' LIMIT 1";
      $res = mysql_global_call($query, $hashed_auth_key);
      
      if (mysql_num_rows($res) !== 0) {
        die('Database error (2)');
      }
      
      // Insert user
      $query = <<<SQL
INSERT INTO mod_users( username, password, flags, level, allow, deny, email,
janitorapp_id, agreement_key)
VALUES('%s', '%s', '%s', '%s', '%s', '', '%s', 0, '%s')
SQL;
      
      $res = mysql_global_call($query,
        $username, $hashed_password, $flags,
        $str_level, $allow, $email, $hashed_auth_key
      );
    }
    
    if (!$res) {
      $this->error('Database error (3)');
    }
    
    if (mysql_affected_rows() < 1) {
      $this->error('Database error (4)');
    }
    
    /**
     * Create account directly
     */
    if ($create_direct) {
      $isAdmin = $str_level === 'admin';
      $isManager = $str_level === 'manager';
      $isMod = $str_level === 'mod';
      
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
      
      if ($has_dev_flag) {
        $this->updateHtpasswd(self::DEVS, $username, $plain_password);
        $this->updateHtpasswd(self::DEVS_NGINX, $username, $plain_password, true);
      }
      
      $values = array(
        '{{USERNAME}}' => $username,
        '{{PASSWORD}}' => $plain_password
      );
      
      $this->success(null, true);
      
      fastcgi_finish_request();
      
      $this->sendEmail($email, self::EMAIL_FILE_DIRECT, $values);
    }
    /**
     * Follow usual steps (agreement, etc)
     */
    else {
      $cmd = sprintf(self::HTPASSWD_CMD,
        self::HTPASSWD_TMP,
        escapeshellarg($hashed_auth_key),
        escapeshellarg($temp_http_passwd)
      );
      
      if (system($cmd) === false) {
        $this->error('Internal Server Error (2).');
      }
      
      $values = array(
        '{{LOGIN}}' => $hashed_auth_key,
        '{{PWD}}' => $temp_http_passwd,
        '{{KEY}}' => $auth_key
      );
      
      $this->success(null, true);
      
      fastcgi_finish_request();
      
      $this->sendEmail($email, self::EMAIL_FILE, $values);
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
