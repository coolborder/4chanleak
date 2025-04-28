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
      'send'
    ),
    
    $labels = array(
      'Levels' => 'level',
      'Flags' => 'flags',
      'Users' => 'username',
      'Boards' => 'boards',
      'Agreement' => 'agreement'
    )
  ;
  
  const TPL_ROOT = '../views/';
  
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
  
  private function send_emails($users, $subject, $message) {
    $sender = strtolower(str_replace(' ', '', $_COOKIE['4chan_auser']));
    
    // !!!
    if ($sender === 'rapeape') {
      $sender = 'grapeape';
    }
    
    $count = 0;
    
    if (count($users) === 1) {
      $user = $users[0];
      
      if ($user['email'] == '') {
        return 0;
      }
      
      $dest_username = strtolower(str_replace(' ', '', $user['username']));
      $dest_email = "{$user['username']} <{$user['email']}>";
      $bcc = "Bcc: 4chan Administrators <contacttool@4chan.org>";
      
      $count = 1;
    }
    else {
      // Send to sender's email and use other emails as BCC
      $bcc = array();
      
      $bcc[] = "4chan Administrators <contacttool@4chan.org>";
      
      $dest_email = "$sender@4chan.org";
      
      foreach ($users as $user) {
        if ($user['email'] != '') {
          $bcc[] = "{$user['username']} <{$user['email']}>";
          ++$count;
        }
      }
      
      $bcc = implode(',', $bcc);
      $bcc = "Bcc: $bcc";
    }
    
    $headers = "From: {$_COOKIE['4chan_auser']} <$sender@4chan.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= $bcc;
    
    mail($dest_email, '[Team 4chan] ' . $subject, $message, $headers, "-f $sender@4chan.org" );
    
    return $count;
  }
  
  private function getUsersBy($group, $values) {
    $users = array();
    
    if (empty($values)) {
      return $users;
    }
    
    $query = "SELECT username, level, allow, flags, email, signed_agreement FROM mod_users";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (gub1)');
    }
    
    $map = array();
    
    foreach ($values as $val) {
      $map[$val] = true;
    }
    
    if ($group == 'level') {
      while ($user = mysql_fetch_assoc($res)) {
        if (isset($map[$user['level']])) {
          $users[] = $user;
        }
      }
    }
    else if ($group == 'flags') {
      while ($user = mysql_fetch_assoc($res)) {
        $flags = explode(',', $user['flags']);
        foreach ($flags as $flag) {
          if ($flag !== '' && isset($map[$flag])) {
            $users[] = $user;
            break;
          }
        }
      }
    }
    else if ($group == 'boards') {
      if (isset($map['Global'])) {
        $map['all'] = true;
      }
      while ($user = mysql_fetch_assoc($res)) {
        $boards = explode(',', $user['allow']);
        foreach ($boards as $board) {
          if ($board !== '' && isset($map[$board])) {
            $users[] = $user;
            break;
          }
        }
      }
    }
    else if ($group == 'agreement') {
      while ($user = mysql_fetch_assoc($res)) {
        if ($user['signed_agreement']) {
          $key = 'yes';
        }
        else {
          $key = 'no';
        }
        if (isset($map[$key])) {
          $users[] = $user;
        }
      }
    }
    else if ($group == 'username') {
      while ($user = mysql_fetch_assoc($res)) {
        if (isset($map[$user['username']])) {
          $users[] = $user;
        }
      }
    }
    
    return $users;
  }
  
  /**
   * Send email
   */
  public function send() {
    set_time_limit(0);
    
    if (!isset($_POST['subject']) || $_POST['subject'] == '') {
      $this->error('Subject cannot be empty.');
    }
    
    if (!isset($_POST['message']) || $_POST['message'] == '') {
      $this->error('Message cannot be empty.');
    }
    
    $subject = $_POST['subject'];
    
    $message = $_POST['message'];
    
    $group = $values = null;
    
    $groups = array('level', 'flags', 'boards', 'username', 'agreement');
    
    foreach ($groups as $g) {
      if (isset($_POST[$g])) {
        $group = $g;
        $values = $_POST[$g];
        break;
      }
    }
    
    if (!$group) {
      $this->error('Nothing to do.');
    }
    
    $users = $this->getUsersBy($group, $values);
    
    $count = $this->send_emails($users, $subject, $message);
    
    $this->success_done = "Sent $count e-mail" . ($count == 1 ? '' : 's');
    
    $this->success();
  }
  
  /**
   * index
   */
  public function index() {
    $query = 'SELECT username, level, flags FROM mod_users';
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    $groups = array(
      'username' => array(),
      'flags' => array(),
      'level' => array(),
      'agreement' => array('yes', 'no')
    );
    
    // usernames, flags, levels
    while ($user = mysql_fetch_assoc($res)) {
      foreach($user as $col => $value) {
        if (!isset($groups[$col])) {
          $groups[$col] = array();
        }
        
        if ($value == '') {
          continue;
        }
        
        if ($col == 'flags') {
          $flags = explode(',', $value);
          foreach ($flags as $flag) {
            $groups['flags'][$flag] = true;
          }
        }
        else {
          $groups[$col][$value] = true;
        }
      }
    }
    
    // boards
    $query = 'SELECT dir FROM boardlist';
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (2).');
    }
    
    $boards = array();
    
    while ($row = mysql_fetch_row($res)) {
      $boards[] = $row[0];
    }
    
    sort($boards);
    
    array_unshift($boards, 'Global');
    
    $groups['boards'] = $boards;
    
    // ---
    
    foreach (array('username', 'flags', 'level') as $g) {
      $group = array_keys($groups[$g]);
      natcasesort($group);
      $groups[$g] = $group;
    }
    
    $this->groups = $groups;
    
    $this->renderHTML('contacttool');
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
