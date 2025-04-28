<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

require_once 'lib/sec.php';

define('IN_APP', true);

auth_user();

// users without the 'postfilter' flag can only run the 'view' action
if (!has_level('mod')) {
  APP::denied();
}

require_once 'lib/csp.php';
/*
if (has_flag('developer')) {
  $mysql_suppress_err = false;
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
}
else {
  die('brb');
}
*/
class App {
  protected
    // Routes
    $actions = array(
      'index',
      'update',
      'view',
      'delete',
      'copy',
      'match',
      'search',
      'test',
      'toggle_active',
      'prune'/*,
      'import',
      'create_table'*/
    ),
    
    $search_mode = false,
    $search_overflow = false
  ;
  
  const TPL_ROOT = 'views/';
  
  const
    TABLE_NAME = 'postfilter',
    TABLE_HITS_NAME = 'postfilter_hits',
    PAGE_SIZE = 80,
    MAX_TEST_RESULTS = 100,
    DATE_FORMAT = 'm/d/Y H:i:s',
    WEBROOT = '/postfilter',
    MIN_LEN_REGEX = 1,
    MIN_LEN_STR = 3,
    HIT_STATS_DAYS = 180
  ;
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
  }
  
  final protected function success($redirect = null) {
    $this->redirect = $redirect;
    $this->renderHTML('success');
    die();
  }
  
  final protected function error($msg) {
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  /**
   * Returns a JSON response
   */
  private function renderJSON($data) {
    header('Content-type: application/json');
    echo json_encode($data);
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
   * Renders HTML template
   */
  private function renderHTML($view) {
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  /*
  public function create_table() {
    $tbl = self::TABLE_NAME;
    
    //$sql = "DROP TABLE `$tbl`";
    //mysql_global_call($sql);
    
    $sql =<<<SQL
CREATE TABLE IF NOT EXISTS `$tbl` (
  `id` int(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `active` tinyint(1) NOT NULL,
  `board` varchar(255) NOT NULL,
  `pattern` text NOT NULL,
  `regex` tinyint(1) NOT NULL,
  `autosage` tinyint(1) NOT NULL,
  `ban_days` int(10) unsigned NOT NULL,
  `description` text NOT NULL,
  `created_on` int(10) unsigned NOT NULL,
  `updated_on` int(10) unsigned NOT NULL,
  `expires_on` int(10) unsigned NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `updated_by` varchar(255) NOT NULL,
  KEY `board_idx` (`active`, `board`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
SQL;
    
    mysql_global_call($sql);
  }*/
  
  /**
   * Plurals
   */
  private function pluralize($count, $one = '', $not_one = 's') {
    return $count == 1 ? $one : $not_one;
  }
  
  // For string filters
  private function normalize_text($text) {
    return preg_replace('@[^a-zA-Z0-9.,/&:;?=~_-]@', '', $text);
  }
  
  private function validateBoards($ary) {
    $boards = $this->get_boards();
    
    foreach ($ary as $b) {
      if (!isset($boards[$b])) {
        $this->error('Invalid board.');
      }
    }
    
    return true;
  }
  
  private function trim_hit_counts() {
    $tbl = self::TABLE_HITS_NAME;
    $days = self::HIT_STATS_DAYS;
    
    $query =<<<SQL
DELETE FROM `$tbl` WHERE created_on <= DATE_SUB(NOW(), INTERVAL $days DAY)
SQL;
    
    return !!mysql_global_call($query);
  }
  
  private function get_active_filter_count() {
    $sql = 'SELECT COUNT(*) FROM postfilter WHERE active = 1';
    
    $res = mysql_global_call($sql);
    
    if (!$res) {
      return 0;
    }
    
    return (int)mysql_fetch_row($res)[0];
  }
  
  private function get_next_hits_param() {
    if (!$this->hits_mode) {
      return 'order=hitsd';
    }
    else if ($this->hits_mode == 'hitsd') {
      return 'order=hitsa';
    }
    else if ($this->hits_mode == 'hitsa') {
      return '';
    }
    return '';
  }
  
  private function get_hit_stats($filter_id) {
    $filter_id = (int)$filter_id;
    
    $tbl = self::TABLE_HITS_NAME;
    
    $query = <<<SQL
SELECT board, COUNT(*) as hits FROM $tbl WHERE filter_id = $filter_id GROUP BY board ORDER BY hits DESC
SQL;
    
    $res = mysql_global_call($query);
    
    $data = array();
    
    if (!$res) {
      return $data;
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $data[] = $row;
    }
    
    return $data;
  }
  
  private function get_hit_counts(&$filters) {
    $ids = array();
    
    if (isset($filters['id'])) {
      $ids[] = (int)$filters['id'];
    }
    else {
      foreach ($filters as $filter) {
        $ids[] = (int)$filter['id'];
      }
    }
    

    if (empty($ids)) {
      return;
    }
    
    $days = self::HIT_STATS_DAYS;
    
    $clause = implode(',', $ids);
    
    $query = <<<SQL
SELECT filter_id, COUNT(*) as hits FROM postfilter_hits WHERE filter_id IN($clause)
AND created_on > DATE_SUB(NOW(), INTERVAL $days DAY)
GROUP BY filter_id
SQL;

    $res = mysql_global_call($query);
    
    if (!$res) {
      return;
    }
    
    if (isset($filters['id'])) {
      $filters['hits'] = (int)mysql_fetch_assoc($res)['hits'];
    }
    else {
      $data = array();
      
      while ($row = mysql_fetch_assoc($res)) {
        $data[$row['filter_id']] = $row['hits'];
      }
      
      foreach ($filters as &$filter) {
        if (isset($data[$filter['id']])) {
          $filter['hits'] = $data[$filter['id']];
        }
      }
    }
  }
  
  private function get_boards() {
    $query = 'SELECT dir FROM boardlist';
    
    $result = mysql_global_call($query);
    
    $boards = array();
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      $boards[$board['dir']] = true;
    }
    
    if (has_flag('developer')) {
      $boards['test'] = true;
    }
    
    return $boards;
  }
  
  private function updateCommit() {
    // Updating existing entry
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
    }
    else {
      $id = null;
    }
    
    $tbl = self::TABLE_NAME;
    
    // Active
    if (isset($_POST['active'])) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    // Never expires
    if (isset($_POST['never_expires'])) {
      $never_expires = 1;
    }
    else {
      $never_expires = 0;
    }
    
    if ($id) {
      $query = "SELECT id FROM $tbl WHERE id = $id LIMIT 1";
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database error (0).');
      }
      
      if (mysql_num_rows($res) < 1) {
        $this->error('Entry not found.');
      }
    }
    
    // boards
    if (isset($_POST['board'])) {
      if ($_POST['board'] === '') {
        $board = '';
      }
      else {
        $valid_boards = $this->get_boards();
        if (!isset($valid_boards[$_POST['board']])) {
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
    
    // Ban length
    if (isset($_POST['ban_days'])) {
      if ($_POST['ban_days'] === '') {
        $ban_days = 0;
      }
      else {
        $ban_days = (int)$_POST['ban_days'];
        
        if ($ban_days < 0) {
          $ban_days = 0;
        }
      }
    }
    else {
      $ban_days = 0;
    }
    
    // Description
    if (isset($_POST['description']) && $_POST['description'] !== '') {
      $description = htmlspecialchars($_POST['description'], ENT_QUOTES);
    }
    else {
      $description = '';
    }
    
    // Pattern (not html-escaped)
    if (!isset($_POST['pattern'])) {
      $this->error('Pattern cannot be empty');
    }
    
    $pattern = $_POST['pattern'];
    
    // Type
    if (isset($_POST['regex'])) {
      $regex = 1;
      
      if (preg_match($pattern, 'test') === false) {
        $this->error("Invalid regular expression");
      }
    }
    else {
      $regex = 0;
    }
    
    // Quiet
    if (isset($_POST['quiet'])) {
      $quiet = 1;
    }
    else {
      $quiet = 0;
    }
    
    // Lenient
    if (isset($_POST['lenient'])) {
      $lenient = 1;
    }
    else {
      $lenient = 0;
    }
    
    // OPs only
    if (isset($_POST['ops_only'])) {
      $ops_only = 1;
    }
    else {
      $ops_only = 0;
    }
    
    // Action
    if (!isset($_POST['act'])) {
      $this->error('Action cannot be empty.');
    }
    else {
      if ($_POST['act'] === 'autosage') {
        $autosage = 1;
      }
      else {
        $autosage = 0;
      }
    }
    
    // Check min length for pattern
    if ($regex) {
      $min_length = self::MIN_LEN_REGEX;
    }
    else {
      $min_length = self::MIN_LEN_STR;
    }
    
    if (mb_strlen($pattern) < $min_length) {
      $this->error('Pattern is too short.');
    }
    
    $now = time();
    
    $username = htmlspecialchars($_COOKIE['4chan_auser'], ENT_QUOTES);
    
    // -----
    // Updating
    // -----
    if ($id) {
      $query =<<<SQL
UPDATE `$tbl` SET
active = $active,
board = '%s',
pattern = '%s',
regex = $regex,
quiet = $quiet,
lenient = $lenient,
ops_only = $ops_only,
autosage = $autosage,
ban_days = $ban_days,
never_expires = $never_expires,
description = '%s',
updated_on = $now,
updated_by = '%s'
WHERE id = $id LIMIT 1
SQL;
      
      $res = mysql_global_call($query,
        $board, $pattern, $description, $username
      );
      
      if (!$res) {
        $this->error('Database error (1).');
      }
    }
    // -----
    // Creating a new entry
    // -----
    else {
      $query =<<<SQL
INSERT INTO `$tbl` (active, board, pattern, regex, quiet, lenient, ops_only, autosage, ban_days,
never_expires, description, created_on, created_by, updated_on, updated_by)
VALUES ($active, '%s', '%s', $regex, $quiet, $lenient, $ops_only, $autosage, $ban_days,
$never_expires, '%s', $now, '%s', 0, '')
SQL;
      $res = mysql_global_call($query,
        $board, $pattern, $description, $username
      );
      
      if (!$res) {
        $this->error('Database error (2).');
      }
    }
    
    $this->success(self::WEBROOT);
  }
  
  /**
   * Delete entries
   */
  public function delete() {
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
      
      $count = 1;
      
      $clause = '= ' . $id;
    }
    else if (isset($_POST['ids'])) {
      $ids = explode(',', $_POST['ids']);
      
      $clause = array();
      
      foreach ($ids as $id) {
        $id = (int)$id;
        
        if (!$id) {
          $this->errorJSON('Invalid ID.');
        }
        
        $clause[] = $id;
      }
      
      $count = count($clause);
      
      $clause = 'IN(' . implode(',', $clause) . ')';
    }
    else {
      $this->error('Bad request.');
    }
    
    $tbl = self::TABLE_NAME;
    
    $query = "DELETE FROM `$tbl` WHERE id $clause LIMIT $count";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      if (isset($_POST['ids'])) {
        $this->errorJSON('Database error.');
      }
      else {
        $this->error('Database error.');
      }
    }
    
    if (isset($_POST['ids'])) {
      $this->successJSON();
    }
    else {
      $this->success(self::WEBROOT);
    }
  }
  
  public function copy() {
    if (isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      
      if (!$id) {
        $this->error('Invalid ID.');
      }
    }
    else {
      $this->error('Bad request.');
    }
    
    
    $tbl = self::TABLE_NAME;
    
    $query = "SELECT * FROM `$tbl` WHERE id = $id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database error (1).');
    }
    
    $filter = mysql_fetch_assoc($res);
    
    if (!$filter) {
      $this->error('Filter not found.');
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    $queries = array();
    
    if (isset($_POST['boards'])) {
      if ($_POST['boards'] === '') {
        $this->error('The board list cannot be empty.');
      }
      else {
        $valid_boards = $this->get_boards();
        
        $boards = preg_split('/[^a-z0-9]+/i', $_POST['boards']);
        
        foreach ($boards as $board) {
          if (!isset($valid_boards[$board])) {
            $this->error('The board list contains an invalid board.');
          }
          else if ($board === $filter['board']) {
            $this->error('A filter already exists for /' . htmlspecialchars($board) . '/.');
          }
          else {
            // Active
            $active = (int)$filter['active'];
            
            // Board
            $board = mysql_real_escape_string($board);
            
            if (!$board) {
              $this->error('Database error (2-1).');
            }
            
            // Pattern
            $pattern = mysql_real_escape_string($filter['pattern']);
            
            if (!$pattern) {
              $this->error('Database error (2-2).');
            }
            
            // Regex
            $regex = (int)$filter['regex'];
            
            // Quiet
            $quiet = (int)$filter['quiet'];
            
            // Lenient
            $lenient = (int)$filter['lenient'];
            
            // OPs only
            $ops_only = (int)$filter['ops_only'];
            
            // Autosage
            $autosage = (int)$filter['autosage'];
            
            // Ban length
            $ban_days = (int)$filter['ban_days'];
            
            // Description
            $description = mysql_real_escape_string($filter['description']);
            
            if ($description === false) {
              $this->error('Database error (2-3).');
            }
            
            // Created by
            $created_by = mysql_real_escape_string($_COOKIE['4chan_auser']);
            
            if (!$created_by) {
              $this->error('Database error (2-4).');
            }
            
            $queries[] = <<<SQL
INSERT INTO `$tbl` (active, board, pattern, regex, quiet, lenient, ops_only, autosage, ban_days,
description, created_on, created_by, updated_on, updated_by)
VALUES ($active, '$board', '$pattern', $regex, $quiet, $lenient, $ops_only, $autosage, $ban_days,
'$description', $now, '$created_by', 0, '')
SQL;
          }
        }
      }
    }
    else {
      $this->error('Bad request.');
    }
    
    if (empty($queries)) {
      $this->error('Nothing to do.');
    }
    
    $had_errors = false;
    
    foreach ($queries as $query) {
      $res = mysql_global_call($query);
      
      if (!$res) {
        $had_errors = true;
      }
    }
    
    if ($had_errors) {
      $this->error('Errors occurred. Not all entries could be copied.');
    }
    else {
      $this->success(self::WEBROOT);
    }
  }
  
  /**
   * Toggle active (XHR only)
   */
  public function toggle_active() {
    if (isset($_POST['ids'])) {
      $ids = explode(',', $_POST['ids']);
      
      $clause = array();
      
      foreach ($ids as $id) {
        $id = (int)$id;
        
        if (!$id) {
          $this->errorJSON('Invalid ID.');
        }
        
        $clause[] = $id;
      }
      
      $count = count($clause);
      
      $clause = 'IN(' . implode(',', $clause) . ')';
    }
    else {
      $this->errorJSON('Bad request.');
    }
    
    if (!isset($_POST['active'])) {
      $this->errorJSON('Bad request.');
    }
    
    $active = (int)$_POST['active'];
    
    if ($active) {
      $active = 1;
    }
    else {
      $active = 0;
    }
    
    $tbl = self::TABLE_NAME;
    
    $query = "UPDATE `$tbl` SET active = $active WHERE id $clause LIMIT $count";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->errorJSON('Database error.');
    }
    
    $this->successJSON();
  }
  
  /**
   * View entry
   */
  public function view() {
    if (!isset($_GET['id'])) {
      $this->item = null;
      $this->renderHTML('postfilter-view');
      return;
    }
    
    $tbl = self::TABLE_NAME;
    
    $id = (int)$_GET['id'];
    
    $query = "SELECT id, active, pattern, regex, description, created_on, updated_on FROM $tbl WHERE id = $id LIMIT 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Entry not found.');
    }
    
    $this->item = mysql_fetch_assoc($res);
    
    $this->renderHTML('postfilter-view');
  }
  
  /**
   * Update entry
   */
  public function update() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->updateCommit();
      return;
    }
    else if (isset($_GET['id'])) {
      $this->trim_hit_counts();
      
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
      
      $this->get_hit_counts($this->item);
      
      $this->hit_stats = $this->get_hit_stats($id);
    }
    else {
      $this->item = null;
    }
    
    $this->valid_boards = $this->get_boards();
    
    $this->renderHTML('postfilter-update');
  }
  
  /**
   * Find filters matching the provided text
   * Not used anymore
   */
  public function match() {
    if (!isset($_GET['text'])) {
      $this->error('Empty Query.');
    }
    
    $this->search_mode = true;
    
    $tbl = self::TABLE_NAME;
    
    $text = nl2br(htmlspecialchars($_GET['text'], ENT_QUOTES));
    
    // For string filters
    $normalized = $this->normalize_text($text);
    // For autosage filters
    $normalized_sage = preg_replace('/[.,!:>\/]+|&gt;/', ' ', $text);
    $normalized_sage = ucwords(strtolower($normalized_sage));
    
    $this->items = array();
    
    $query = "SELECT id, pattern, regex, autosage FROM $tbl";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $ids = array();
    $count = 0;
    
    while ($row = mysql_fetch_assoc($res)) {
      if ($row['regex']) {
        if (preg_match($row['pattern'], $text)) {
          ++$count;
          $ids[] = (int)$row['id'];
        }
      }
      else if ($row['autosage']) {
        if (stripos($normalized_sage, $row['pattern']) !== false) {
          ++$count;
          $ids[] = (int)$row['id'];
        }
      }
      else {
        if (stripos($normalized, $row['pattern']) !== false) {
          ++$count;
          $ids[] = (int)$row['id'];
        }
      }
      
      if ($count > self::PAGE_SIZE) {
        $this->search_overflow = true;
        array_pop($ids);
        break;
      }
    }
    
    if (empty($ids)) {
      $this->renderHTML('postfilter');
      return;
    }
    
    $clause = implode(',', $ids);
    
    $query = "SELECT * FROM $tbl WHERE id IN($clause)";
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    $this->offset = $this->previous_offset = $this->next_offset = 0;
    
    $this->search_qs = '';
    
    $this->renderHTML('postfilter');
  }
  
  public function test() {
    $this->query = null;
    
    $this->overflow = false;
    
    if (!isset($_GET['text'])) {
      return $this->renderHTML('postfilter-test');
    }
    
    $text = nl2br(htmlspecialchars($_GET['text'], ENT_QUOTES));
    
    // For string filters
    $normalized = $this->normalize_text($text);
    // For autosage filters
    $normalized_sage = preg_replace('/[.,!:>\/]+|&gt;/', ' ', $text);
    $normalized_sage = ucwords(strtolower($normalized_sage));
    
    $tbl = self::TABLE_NAME;
    
    $this->items = array();
    
    $query = "SELECT id, board, pattern, regex, autosage FROM $tbl WHERE active = 1";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $filters = [];
    $count = 0;
    
    while ($row = mysql_fetch_assoc($res)) {
      $matched = false;
      
      if ($row['regex']) {
        if (preg_match($row['pattern'], $text)) {
          ++$count;
          $matched = true;
        }
      }
      else if ($row['autosage']) {
        if (stripos($normalized_sage, $row['pattern']) !== false) {
          ++$count;
          $matched = true;
        }
      }
      else {
        if (stripos($normalized, $row['pattern']) !== false) {
          ++$count;
          $matched = true;
        }
      }
      
      if ($matched) {
        if ($count >= self::MAX_TEST_RESULTS) {
          $this->overflow = true;
          break;
        }
        
        $filters[] = $row;
      }
    }
    
    if (empty($filters)) {
      $this->error('No matches.');
    }
    
    $this->filters = $filters;
    
    $this->query = $text;
    
    $this->renderHTML('postfilter-test');
  }
  
  /**
   * Search
   */
  public function search() {
    if (count($_GET) <= 1) {
      $this->valid_boards = $this->get_boards();
      $this->renderHTML('postfilter-search');
      return;
    }
    
    $this->search_mode = true;
    
    $tbl = self::TABLE_NAME;
    
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $url_params = array();
    $sql_clause = array();
    
    $this->items = array();
    
    $valid_fields = array(
      'id'          => array('int', 'id'),
      'board'       => array('string', 'board'),
      'autosage'    => array('bool', 'autosage'),
      'regex'       => array('bool', 'regex'),
      'ban'         => array('bool', 'ban_days'),
      'pattern'     => array('text', 'pattern'),
      'description' => array('text', 'description')
    );
    
    foreach ($valid_fields as $field => $meta) {
      if (!isset($_GET[$field])) {
        continue;
      }
      
      $value = $_GET[$field];
      
      if ($value === '') {
        continue;
      }
      
      $type = $meta[0];
      $col = $meta[1];
      
      if ($type === 'bool') {
        $url_params[] = "$field=1";
        $sql_clause[] = "$col>0";
      }
      else if ($type === 'string') {
        $url_params[] = $field . '=' . urlencode($value);
        if ($field === 'board' && $value === 'global') {
          $value = '';
        }
        $sql_clause[] = $col . "='" . mysql_real_escape_string($value) . "'";
      }
      else if ($type === 'int') {
        $url_params[] = $field . '=' . (int)$value;
        $sql_clause[] = $col . "=" . (int)$value;
      }
      else if ($type === 'text') {
        $url_params[] = $field . '=' . urlencode($value);
        $value = str_replace(array('%', '_'), array("\%", "\_"), $value);
        $sql_clause[] = $col . " LIKE '%" . mysql_real_escape_string($value) . "%'";
      }
    }
    
    if (empty($sql_clause)) {
      $this->error('Empty Query.');
    }
    
    $sql_clause = implode(' AND ', $sql_clause);
    
    // Calculate rows
    $query = "SELECT COUNT(*) FROM $tbl WHERE $sql_clause";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error.');
    }
    
    $this->results_count = (int)mysql_fetch_row($res)[0];
    
    // Fetching
    $query = <<<SQL
SELECT * FROM $tbl WHERE $sql_clause ORDER BY id DESC LIMIT $offset,$lim
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
    
    $this->trim_hit_counts();
    
    $this->get_hit_counts($this->items);
    
    $url_params = htmlspecialchars(implode('&', $url_params), ENT_QUOTES);
    
    $this->search_qs = 'action=search&amp;' . $url_params . '&amp;';
    
    $this->renderHTML('postfilter');
  }
  
  /**
   * Prune old entries
   */
  public function prune() {
    $tbl = self::TABLE_NAME;
    $tbl_hits = self::TABLE_HITS_NAME;
    
    $sec = self::HIT_STATS_DAYS * 86400;
    
    $query = <<<SQL
SELECT $tbl.*, COUNT($tbl_hits.id) as hits FROM $tbl LEFT JOIN $tbl_hits
ON filter_id = $tbl.id
WHERE active = 1 AND never_expires = 0 AND postfilter.created_on < UNIX_TIMESTAMP(NOW()) - $sec
GROUP BY $tbl.id
HAVING hits = 0
SQL;

    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error');
    }
    
    if (mysql_num_rows($res) < 1) {
      $this->error('Nothing to prune.');
    }
    
    $this->offset = 0;
    $this->previous_offset = 0;
    $this->next_offset = 0;
    
    $this->items = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->items[] = $row;
    }
    
    $this->prune_mode = true;
    $this->search_mode = false;
    $this->search_qs = '';
    
    $this->renderHTML('postfilter');
  }
  
  /**
   * Index
   */
  public function index() {
    $tbl = self::TABLE_NAME;
    $tbl_hits = self::TABLE_HITS_NAME;
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    if (isset($_GET['order'])) {
      if ($_GET['order'] == 'hitsd') {
        $order = 'hits DESC';
        $this->hits_mode = 'hitsd';
      }
      else if ($_GET['order'] == 'hitsa') {
        $order = 'hits ASC';
        $this->hits_mode = 'hitsa';
      }
      else {
        $this->hits_mode = false;
      }
    }
    else {
      $this->hits_mode = false;
    }
    
    $this->trim_hit_counts();
    
    if ($this->hits_mode) {
      $query = <<<SQL
SELECT $tbl.*, COUNT($tbl_hits.id) as hits FROM $tbl LEFT JOIN $tbl_hits
ON filter_id = $tbl.id GROUP BY $tbl.id ORDER BY $order LIMIT $offset,$lim
SQL;
    }
    else {
      $query = "SELECT * FROM $tbl ORDER BY id DESC LIMIT $offset,$lim";
    }
    
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
    
    if (!$this->hits_mode) {
      $this->get_hit_counts($this->items);
    }
    
    // search query string for pagination
    if ($this->hits_mode) {
      $this->search_qs = 'order=' . $this->hits_mode . '&amp;';
    }
    else {
      $this->search_qs = '';
    }
    
    if (!$offset) {
      $this->active_count = $this->get_active_filter_count();
    }
    else {
      $this->active_count = null;
    }
    
    $this->renderHTML('postfilter');
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
      $mod_actions = ['view', 'test'];
      
      if (!in_array($action, $mod_actions) && (!has_flag('postfilter') && !has_level('manager'))) {
        APP::denied();
      }
      
      $this->$action();
    }
    else {
      $this->error('Bad request');
    }
  }
}

$ctrl = new App();
$ctrl->run();
