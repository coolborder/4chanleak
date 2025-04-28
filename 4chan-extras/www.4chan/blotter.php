<?php
error_reporting(0);

require_once 'lib/db.php';

define('IN_APP', true);

mysql_global_connect();

class Blotter {
  protected
    // Routes
    $actions = array(
      'index'
    ),
    
    // Table name
    $table_name = 'blotter_messages',
    
    // Number of entries per page
    $page_size = 250,
    
    // Number of entries in Atom feed
    $atom_size = 10
    ;
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    include('views/' . $view . '.tpl.php');
  }
  
  /**
   * Atom feed
  */
  private function renderAtomFeed() {
    $table = $this->table_name;
    $limit = $this->atom_size;
    
    $query = "SELECT id, `date`, content FROM $table ORDER BY id DESC LIMIT $limit";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      header('HTTP/1.1 500 Internal Server Error');
      die();
    }
    
    header('Content-Type: application/atom+xml');
    
    if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) {
      $protocol = 'https:';
    }
    else {
      $protocol = 'http:';
    }
    
    $updated = 0;
    $messages = array();
    
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="en-US">';
    echo '<title>4chan Blotter</title>';
    echo '<link type="text/html" rel="alternate" href="' . $protocol . '//www.4chan.org/blotter"/>';
    echo '<link type="application/atom+xml" rel="self" href="' . $protocol . '//www.4chan.org/blotter?atom"/>';
    echo '<id>tag:4chan.org,2003:/blotter</id>';
    
    while ($row = mysql_fetch_assoc($res)) {
      $clean = html_entity_decode($row['content']);
      $title = strip_tags($clean);
      $content = htmlspecialchars($clean);
      $date = date('c', $row['date']);
      
      if ($updated === 0) {
        $updated = $date;
      }
      
      $messages[] = <<<DATA
<entry>
  <id>tag:4chan.org,2003:/blotter/{$row['id']}</id>
  <updated>$date</updated>
  <title>$title</title>
  <author><name>4chan</name></author>
  <content type="html">$content</content>
</entry>
DATA;
    }
    
    if ($updated === 0) {
      $updated = date('c', 0);
    }
    
    echo "<updated>$date</updated>";
    
    echo implode('', $messages);
    
    echo '</feed>';
  }
  
  /**
   * Index page
   */
  public function index() {
    if (isset($_GET['atom'])) {
      $this->renderAtomFeed();
      return;
    }
    
    if (isset($_GET['offset'])) {
      $where = 'WHERE id < ' . (int)$_GET['offset'];
    }
    else {
      $where = '';
    }
    
    $table = $this->table_name;
    $limit = $this->page_size + 1;
    
    $query = "SELECT id, `date`, content FROM $table $where ORDER BY id DESC LIMIT $limit";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      die('Database Error.');
    }
    
    $count = mysql_num_rows($res);
    
    $this->messages = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $this->messages[] = $row;
    }
    
    if ($this->has_next_page = $count > $this->page_size) {
      array_pop($this->messages);
    }
    
    $this->renderHTML('blotter');
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
      die();
    }
  }
}

$ctrl = new Blotter();
$ctrl->run();
