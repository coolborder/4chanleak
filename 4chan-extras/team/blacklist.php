<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('manager') && !has_flag('blacklist') && !has_flag('developer')) {
  APP::denied();
}

require_once 'lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'update',
      'delete',
      'search',
      'purge'
    ),
    
    $valid_fields = array(
      'md5' => 'File MD5', 
      'name' => 'Name',
      'trip' => 'Tripcode',
      'nametrip' => 'Name+Tripcode',
      //'email' => 'E-Mail',
      'sub' => 'Subject',
      'com' => 'Comment',
      'pwd' => 'Password',
      'xff' => 'X-Forwarded-For IP',
      'filename' => 'Filename'
    ),
    
    $field_tips = array(
      'trip' => 'No leading # or ! characters',
      'nametrip' => 'Name #Tricode or Name #!SecureTripcode',
      'filename' => 'No extension'
    ),
    
    $valid_actions = array(
      'ban' => 1,
      'reject' => 0
    )
  ;
  
  const TPL_ROOT = 'views/';
  
  const
    TABLE_NAME = 'blacklist',
    PAGE_SIZE = 100,
    DATE_FORMAT = 'm/d/Y H:i:s',
    WEBROOT = '/blacklist',
    
    WS_BOARD_TAG = '_ws_' // special board to cover all worksafe boards
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
  
  private function get_boards() {
    $query = 'SELECT dir FROM boardlist ORDER BY dir ASC';
    
    $result = mysql_global_call($query);
    
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
  
  /**
   * Returns what a blacklist entry does
   */
  private function pretty_action($entry) {
    if (!$entry['ban']) {
      return 'Reject';
    }
    else {
      if ($entry['ban'] === '2') {
        return 'DMCA';
      }
      else if ($entry['ban'] === '1') {
        if ($entry['banlength'] === '-1') {
          return 'Ban permanently';
        }
        else {
          $days = (int)$entry['banlength'];
          return 'Ban for ' . $days . ' day' . ($days > 1 ? 's' : '');
        }
      }
      else {
        return 'Not implemented';
      }
    }
  }
  
  private function updateCommit() {
    // Updating existing entry
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
    }
    else {
      $id = null;
    }
    
    // Active
    if (isset($_POST['active'])) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    if ($id) {
      $query = 'SELECT ban FROM ' . self::TABLE_NAME . " WHERE id = $id LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database error (0).');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Entry not found.');
      }
      
      $entry = mysql_fetch_assoc($res);
      
      // DMCA entries can only be enabled or disabled.
      if ($entry['ban'] === '2') {
        if (!$this->canEditDMCAEntries) {
          $this->error("Can't let you do that.");
        }
        
        $query = "UPDATE " . self::TABLE_NAME
          . " SET active = $active WHERE id = $id LIMIT 1";
        
        $res = mysql_global_call($query);
        
        if (!$res) {
          $this->error('Database error (1).');
        }
        
        $this->success(self::WEBROOT);
      }
    }
    
    // boards
    if (isset($_POST['board'])) {
      if ($_POST['board'] === '') {
        $board = '';
      }
      else {
        $valid_boards = $this->get_boards();
        if (!isset($valid_boards[$_POST['board']]) && $_POST['board'] !== self::WS_BOARD_TAG) {
          $this->error('Invalid board.');
        }
        else {
          $board = $_POST['board'];
        }
      }
    }
    else {
      $board = '';
    }
    
    // Description
    if (isset($_POST['description']) && $_POST['description'] !== '') {
      $description = htmlspecialchars($_POST['description'], ENT_QUOTES);
    }
    else {
      $description = '';
    }
    
    // Field
    if (!isset($_POST['field'])) {
      $this->error('Field cannot be empty');
    }
    
    if (!isset($this->valid_fields[$_POST['field']])) {
      $this->error('Invalid field.');
    }
    
    $field = $_POST['field'];
    
    // Value (not html-escaped)
    if (!isset($_POST['value'])) {
      $this->error('Value cannot be empty');
    }
    
    $value = $_POST['value'];
    
    // Action
    if (!isset($_POST['act'])) {
      $this->error('Action cannot be empty.');
    }
    else {
      if (!isset($this->valid_actions[$_POST['act']])) {
        $this->error('Invalid action.');
      }
      
      $action = $this->valid_actions[$_POST['act']];
    }
    
    // Quiet
    if (isset($_POST['quiet'])) {
      $quiet = 1;
    }
    else {
      $quiet = 0;
    }
    
    // Ban length
    if (isset($_POST['ban_length'])) {
      if ($_POST['ban_length'] === '') {
        $ban_length = -1;
      }
      else {
        $ban_length = (int)$_POST['ban_length'];
        
        if ($ban_length < 0) {
          $ban_length = -1;
        }
      }
    }
    else {
      $ban_length = -1;
    }
    
    // Ban reason
    if (isset($_POST['ban_reason'])) {
      $ban_reason = htmlspecialchars($_POST['ban_reason'], ENT_QUOTES);
    }
    else {
      $ban_reason = '';
    }
    
    $username = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    $tbl = self::TABLE_NAME;
    
    // -----
    // Updating
    // -----
    if ($id) {
      $query =<<<SQL
UPDATE `$tbl` SET
active = %d,
boardrestrict = '%s',
field = '%s',
contents = '%s',
description = '%s',
ban = %d,
banlength = %d,
banreason = '%s',
addedby = '%s',
quiet = %d
WHERE id = $id LIMIT 1
SQL;
      
      $res = mysql_global_call($query,
        $active, $board, $field, $value, $description,
        $action, $ban_length, $ban_reason, $username, $quiet
      );
      
      if (!$res) {
        $this->error('Database error (2).');
      }
    }
    // -----
    // Creating a new entry
    // -----
    else {
      $query =<<<SQL
INSERT INTO `$tbl` (active, boardrestrict, field, contents, description,
ban, banlength, banreason, addedby, quiet)
VALUES (%d, '%s', '%s', '%s', '%s',
%d, %d, '%s', '%s', %d)
SQL;
      $res = mysql_global_call($query,
        $active, $board, $field, $value, $description,
        $action, $ban_length, $ban_reason, $username, $quiet
      );
      
      if (!$res) {
        $this->error('Database error (3).');
      }
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Delete entry
   */
  public function delete() {
    if (!isset($_POST['id'])) {
      $this->error('Bad Request');
    }
    
    $id = (int)$_POST['id'];
    
    $query = 'SELECT ban FROM ' . self::TABLE_NAME . " WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (0).');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Entry not found.');
    }
    
    $entry = mysql_fetch_assoc($res);
    
    // Only managers can delete DMCA entries
    if ($entry['ban'] === '2' && !$this->canEditDMCAEntries) {
      $this->error("Can't let you do that.");
    }
    
    $query = "DELETE FROM " . self::TABLE_NAME . " WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (1).');
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Update entry
   */
  public function update() {
    $this->canEditDMCAEntries = has_level('manager') || has_flag('developer');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->updateCommit();
      return;
    }
    else if (isset($_GET['id'])) {
      $tbl = self::TABLE_NAME;
      
      $id = (int)$_GET['id'];
      
      $query = "SELECT * FROM $tbl WHERE id = $id LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error.');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Entry not found.');
      }
      
      $this->item = mysql_fetch_assoc($res);
    }
    else {
      $this->item = null;
    }
    
    $this->valid_boards = $this->get_boards();
    
    $this->renderHTML('blacklist-update');
  }
  
  /**
   * Search
   */
  public function search() {
    $this->search_mode = true;
    
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $this->items = array();
    
    if (isset($_GET['q']) && $_GET['q'] !== '') {
      $tbl = self::TABLE_NAME;
      
      $q = str_replace(array('%', '_'), array("\%", "\_"), $_GET['q']);
      
      $q = mysql_real_escape_string($q);
      
      $this->search_query = htmlspecialchars(urlencode($_GET['q']), ENT_QUOTES);
      
      $query = <<<SQL
SELECT id, field, contents, description, UNIX_TIMESTAMP(added) as created_on,
active, ban, banlength, banreason, boardrestrict, addedby as created_by
FROM $tbl WHERE contents LIKE '%$q%' OR description LIKE '%$q%' LIMIT $offset,$lim
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error.');
      }
      
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
      
      while ($row = mysql_fetch_assoc($res)) {
        $this->items[] = $row;
      }
      
      if ($this->next_offset) {
        array_pop($this->items);
      }
      
      $this->search_qs = 'action=search&amp;q=' . $this->search_query . '&amp;';
    }
    else {
      $this->search_qs = '';
    }
    
    $this->renderHTML('blacklist');
  }
  
  /**
   * Mass-remove file hashes
   */
  public function purge() {
    if (isset($_POST['md5'])) {
      $lines = preg_split('/[\r\n]+/', trim($_POST['md5']));
      
      $items = array();
      
      foreach ($lines as $line) {
        $md5 = strtolower(trim($line));
        
        if (!preg_match('/[0-9a-f]{32}/', $md5)) {
          $this->error('Submitted data contains invalid entries.');
        }
        
        $items[] = $md5;
      }
      
      $tbl = self::TABLE_NAME;
      
      foreach ($items as $md5) {
        $query =<<<SQL
UPDATE $tbl SET active = 0 WHERE field = 'md5' AND contents = '%s' LIMIT 1
SQL;
        
        $res = mysql_global_call($query, $md5);
        
        if (!$res) {
          $this->error('Database Error.');
        }
      }
      
      $this->success(self::WEBROOT);
    }
    else {
      $this->renderHTML('blacklist-purge');
    }
  }
  
  /**
   * Index
   */
  public function index() {
    $tbl = self::TABLE_NAME;
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $query =<<<SQL
SELECT id, field, contents, description, UNIX_TIMESTAMP(added) as created_on,
active, ban, banlength, banreason, boardrestrict, addedby as created_by
FROM $tbl ORDER BY id DESC LIMIT $offset,$lim
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error');
    }
    
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
    
    // search query string for pagination
    $this->search_qs = '';
    
    $this->renderHTML('blacklist');
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
