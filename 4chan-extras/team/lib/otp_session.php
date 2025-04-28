<?php

require_once 'lib/auth.php';

final class OTPSession {
  const
    TIMEOUT = 3600, // session TTL in seconds
    TPL_FILE = '../views/otp-session.tpl.php',
    SALT_FILE = '/www/keys/2014_admin.salt',
    COOKIE_NAME = '_otpsid',
    COOKIE_HOST = 'team.4chan.org'
  ;
  
  static public function validate() {
    if (self::isValid()) {
      return true;
    }
    
    if (isset($_POST['otp'])) {
      if (self::authenticate()) {
        return true;
      }
      else {
        self::renderForm(true);
      }
    }
    
    self::renderForm();
    
    die();
  }
  
  static private function renderForm($bad_otp = false) {
    require_once self::TPL_FILE;
    die();
  }
  
  static private function prettyTimeout() {
    $m = (int)round(self::TIMEOUT / 60.0);
    return $m . ' minute' . ($m > 1 ? 's' : '');
  }
  
  /**
   * This doesn't check if the user is logged in. It only validates the OTP.
   */
  static private function authenticate() {
    if (!isset($_COOKIE['4chan_auser']) || !$_COOKIE['4chan_auser']) {
      return false;
    }
    
    if (!isset($_POST['otp']) || !$_POST['otp']) {
      return false;
    }
    
    if (!verify_one_time_pwd($_COOKIE['4chan_auser'], $_POST['otp'])) {
      return false;
    }
    
    $now = time();
    
    $salt = file_get_contents(self::SALT_FILE);
    
    if (!$salt) {
      die('Internal Server Error (otpsid1)');
    }
    
    $now_hashed = password_hash($now.$salt, PASSWORD_DEFAULT);
    
    if (!$now_hashed) {
      die('Internal Server Error (otpsid2)');
    }
    
    $val = $now . '.' . $now_hashed;
    
    setcookie(self::COOKIE_NAME, $val, 0, '/', self::COOKIE_HOST, true, true);
    
    return true;
  }
  
  static private function isValid() {
    if (!isset($_COOKIE[self::COOKIE_NAME])) {
      return false;
    }
    
    $bits = explode('.', $_COOKIE[self::COOKIE_NAME], 2);
    
    if (count($bits) < 2) {
      return false;
    }
    
    $salt = file_get_contents(self::SALT_FILE);
    
    if (!$salt) {
      die('Internal Server Error (otpsid3)');
    }
    
    if (!password_verify($bits[0].$salt, $bits[1])) {
      return false;
    }
    
    $now = time();
    
    if ($now - (int)$bits[0] < self::TIMEOUT) {
      return true;
    }
    
    return false;
  }
}
