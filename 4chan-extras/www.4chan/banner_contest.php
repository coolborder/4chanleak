<?php
require_once 'lib/db.php';
require_once 'lib/admin.php';
require_once 'lib/auth.php';
require_once 'lib/captcha.php';

define('IN_APP', true);

mysql_global_connect();

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'delete',
      'confirm_delete',
      'check_status',
      'submit',
      'vote'/*,
      'debug'*/
    );
  
  private $_salt = null;
  
  private $_board_cache = array();
  
  private $_event_types = array(
    1 => 'Submit',
    2 => 'Vote'
  );
  
  //private $allowed_filetypes = array('jpg', 'png', 'gif');
  private $allowed_filetypes = array('jpg', 'png');
  
  const
    IMG_WIDTH = 468,
    IMG_HEIGHT = 60,
    //IMG_MAX_FILESIZE = 153600, // 150KB
    IMG_MAX_FILESIZE = 5242880, // 5MB
    
    // set to 0 to ignore
    IMG_MAX_WIDTH = 2048,
    IMG_MAX_HEIGHT = 2048,
    TH_MAX_DIMS = 250,
    
    FIELD_MAX_LEN = 150, // author and email fields limits
    
    COOLDOWN = 30,
    
    VOTES_PER_USER = 1,
    
    PAGE_SIZE = 1000,
    
    EMBED_DELTA = 51200,
    
    ALL_BOARDS_TAG = 'all',
    
    NAME_KEY = '846e2fd927ee70a5', // For naming pending banners
    
    DATE_FORMAT_SHORT ='m/d/y',
    
    SALT_PATH = '/www/keys/2014_admin.salt'
  ;
  
  const
    //BANNERS_TABLE = 'contest_banners',
    //VOTES_TABLE = 'contest_banner_votes',
    //EVENTS_TABLE = 'contest_banner_events',
    BANNERS_TABLE = 'contest_imgs',
    VOTES_TABLE = 'contest_img_votes',
    EVENTS_TABLE = 'contest_img_events',
    MODS_TABLE = 'mod_users',
    BLACKLIST_TABLE = 'blacklist',
    IMG_ROOT = '/www/global/static/image/contest_banners'
  ;
  
  const
    EVENT_EXPIRED = -1,
    EVENT_SUBMIT = 1,
    EVENT_VOTE = 2
  ;
  
  const
    S_BAD_CAPTCHA = 'You seem to have mistyped the CAPTCHA.',
    S_BAD_BOARD = 'Invalid board.',
    S_BAD_BANNER = 'Banner not found.',
    S_ALREADY_VOTED = 'You have already voted for this banner.',
    S_MAX_VOTES = "You don't have any votes left on this board.",
    S_NO_FILE = 'You forgot to upload your banner.',
    S_MAX_FILESIZE = 'The uploaded file is too big.',
    S_FILE_FORMAT = 'Please make sure your banner is of allowed format.',
    S_FILE_DIMS = 'Please make sure your banner has the right dimensions (%d×%d)',
    S_FILE_MAX_DIMS = 'Please make sure your image has the right dimensions (%d×%d max.)',
    S_BAD_EMAIL = 'Invalid E-Mail address.',
    S_LONG_FIELD = '%s is too long.',
    S_DUP_FILE = 'This banner already exists.',
    S_FILE_COOLDOWN = 'You have to wait a while before submitting another banner.',
    S_VOTE_NODEL = 'This banner is already in the voting phase.',
    S_IS_ACTIVE = 'This banner was approved and will appear in the next voting phase.',
    S_IS_DISABLED = 'This banner was rejected.',
    S_IS_PENDING = 'This banner is pending approval.',
    S_EMBEDDED_DATA = 'Your image appears to contain embedded data.'
  ;
  
  const
    STATUS_PENDING = 0,
    STATUS_ACTIVE = 1,
    STATUS_DISABLED = 2,
    STATUS_LOST = 3 // Special status for vtuber images which didn't go to round 2
  ;
  
  public function debug() {
    
  }
  
  final protected function success($msg = null, $redirect = null) {
    $this->redirect = $redirect;
    $this->message = $msg;
    $this->renderHTML('success');
    die();
  }
  
  final protected function error($msg, $code = null) {
    if ($code) {
      http_response_code($code);
    }
    $this->message = $msg;
    $this->renderHTML('error');
    die();
  }
  
  /**
   * Returns the data as json
   */
  final protected function successJSON($data = null) {
    $this->renderJSON(array('status' => 'success', 'data' => $data));
  }
  
  /**
   * Returns the error as json and exits
   */
  final protected function errorJSON($message, $code = null, $data = null) {
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
  
  /**
   * Returns a JSON response
   */
  private function renderJSON($data) {
    header('Content-type: application/json');
    echo json_encode($data);
  }
  
  /**
   * Renders HTML template
   */
  private function renderHTML($view) {
    include('views/' . $view . '.tpl.php');
  }
  
  private function get_csrf_token() {
    return bin2hex(openssl_random_pseudo_bytes(16));
  }
  
  private function set_csrf_token($tkn) {
    setcookie('_bctkn', $tkn, 0, '/banner-contest', 'www.4chan.org', true);
  }
  
  private function set_cache($flag) {
    if (!$flag) {
      header('Cache-Control: no-cache');
    }
  }
  
  private function cleanup_uploaded_file($file, $full_size, $type) {
    switch ($type) {
      case 'png':
        $clean_size = $this->get_clean_png_size($file);
        break;
      case 'jpg':
        $clean_size = $this->get_clean_jpg_size($file);
        break;
      case 'gif':
        $clean_size = $this->get_clean_gif_size($file);
        break;
      default:
        return false;
    }
    
    if ($clean_size === false) {
      return false;
    }
    
    $delta_size = $full_size - $clean_size;
    
    if ($delta_size > self::EMBED_DELTA) {
      if ($type === 'gif') {
        $file = escapeshellcmd($file);
        $res = system("/usr/local/bin/gifsicle \"$file\" -o \"$file\" >/dev/null 2>&1");
        if ($res !== false) {
          return true;
        }
      }
      else {
        $this->error(self::S_EMBEDDED_DATA);
      }
    }
    
    return false;
  }
  
  private function get_clean_gif_size($file) {
    $file = escapeshellcmd($file);
    
    $binary = '/usr/local/bin/gifsicle';
    
    $res = shell_exec("$binary --sinfo \"$file\" 2>&1");
    
    if ($res !== null) {
      $size = 0;
      
      if (preg_match_all('/compressed size ([0-9]+)/', $res, $m)) {
        foreach ($m[1] as $frame_size) {
          $size += (int)$frame_size;
        }
        
        return $size;
      }
    }
    
    return false;
  }
  
  private function get_clean_png_size($file) {
    $file = escapeshellcmd($file);
    
    $binary = '/usr/local/bin/pngcrush';
    
    $res = shell_exec("$binary -n \"$file\" 2>&1");
    
    if ($res !== null) {
      if (preg_match('/in critical chunks\s+=\s+([0-9]+)/', $res, $m)) {
        return (int)$m[1];
      }
    }
    
    return false;
  }
  
  private function get_clean_jpg_size($file) {
    $eof = false;
    
    $img = fopen($file, 'rb');
    
    $data = fread($img, 2);
    
    if ($data !== "\xff\xd8") {
      fclose($img);
      return false;
    }
    
    while (!feof($img)) {
      $data = fread($img, 1);
      
      if ($data !== "\xff") {
        continue;
      }
      
      while (!feof($img)) {
        $data = fread($img, 1);
        
        if ($data !== "\xff") {
          break;
        }
      }
      
      if (feof($img)) {
        break;
      }
      
      $byte = unpack('C', $data)[1];
      
      if ($byte === 217) {
        $eof = ftell($img);
        break;
      }
      
      if ($byte === 0 || $byte === 1 || ($byte >= 208 && $byte <= 216)) {
        continue;
      }
      
      $data = fread($img, 2);
      
      $length = unpack('n', $data)[1];
      
      if ($length < 1) {
        break;
      }
      
      fseek($img, $length - 2, SEEK_CUR);
    }
    
    fclose($img);
    
    return $eof;
  }
  
  public function get_image_url($banner, $thumbnail = false) {
    $root = 'https://s.4cdn.org/image/contest_banners/';
    
    if ($thumbnail) {
      $img_url = $root . $banner['file_id'] . '_th.jpg';
    }
    else {
      $img_url = $root . $banner['file_id'] . '.' . $banner['file_ext'];
    }
    
    return $img_url;
  }
  
  private function get_remote_thumb_blob($params) {
    $curl = curl_init();
    
    $url = "https://sys.int/a/post";
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_USERAGENT, '4chan.org');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    
    $resp = curl_exec($curl);
    
    if (!$resp) {
      return false;
    }
    
    return $resp;
  }
  
  private function make_thumbnail_remote($infile, $file_ext, $src_width, $src_height, $outfile) {
    $max_dims = self::TH_MAX_DIMS;
    
    if ($src_width > $max_dims || $src_height > $max_dims) {
      $src_ratio = $src_width / $src_height;
      
      if ($src_ratio >= 1) {
        $th_width = $max_dims;
        $th_height = ceil($max_dims / $src_ratio);
      }
      else {
        $th_width = ceil($max_dims * $src_ratio);
        $th_height = $max_dims;
      }
    }
    else {
      $th_width = $src_width;
      $th_height = $src_height;
    }
        
    $params = array(
      'mode' => 'make_remote_thumbnail',
      'file_ext' => $file_ext,
      'src_width' => $src_width,
      'src_height' => $src_height,
      'th_width' => $th_width,
      'th_height' => $th_height,
      'file' => new CurlFile($infile)
    );
    
    $resp = $this->get_remote_thumb_blob($params);
    
    if (!$resp) {
      return false;
    }
    
    $ret = file_put_contents($outfile, $resp);
    
    if ($ret === false) {
      return false;
    }
    
    return array($th_width, $th_height);
  }
  
  private function make_thumbnail($infile, $file_ext, $src_width, $src_height, $outfile) {
    $max_dims = self::TH_MAX_DIMS;
    
    $jpeg_quality = 65;
    
    switch ($file_ext) {
      case 'gif':
        $img_in = ImageCreateFromGIF($infile);
        break;
      case 'jpg':
        $img_in = ImageCreateFromJPEG($infile);
        break;
      case 'png':
        $img_in = ImageCreateFromPNG($infile);
        break;
      default :
        $img_in = null;
    }
    
    if (!$img_in) {
      return false;
    }
    
    if ($src_width > $max_dims || $src_height > $max_dims) {
      $src_ratio = $src_width / $src_height;
      
      if ($src_ratio >= 1) {
        $th_width = $max_dims;
        $th_height = ceil($max_dims / $src_ratio);
      }
      else {
        $th_width = ceil($max_dims * $src_ratio);
        $th_height = $max_dims;
      }
    }
    else {
      $th_width = $src_width;
      $th_height = $src_height;
    }
        
    
    $img_out = ImageCreateTrueColor($th_width, $th_height);
    
    if (!$img_out) {
      return false;
    }
    
    ImageCopyResampled($img_out, $img_in, 0, 0, 0, 0, $th_width, $th_height, $src_width, $src_height);
    
    ImageDestroy($img_in);
    
    $ret = ImageJPEG($img_out, $outfile, $jpeg_quality);
    
    ImageDestroy($img_out);
    
    if ($ret === false) {
      return false;
    }
    
    return array($th_width, $th_height);
  }
  
  /**
   * Verify captcha. Dies on failure.
   */
  private function verifyCaptcha() {
    global $recaptcha_public_key, $recaptcha_private_key;
    
    $response = $_POST["g-recaptcha-response"];
    
    if (!$response) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    $response = urlencode($response);
    
    $rlen = strlen($response);
    
    if ($rlen > 2048) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    $api_url = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_private_key}&response=$response";
    
    $recaptcha_ch = rpc_start_request($api_url, null, null, false);
    
    if (!$recaptcha_ch) {
      $this->error(self::S_BAD_CAPTCHA); // not really
    }
    
    $ret = rpc_finish_request($recaptcha_ch, $error, $httperror);
    
    // BAD
    // 413 Request Too Large is bad; it was caused intentionally by the user.
    if ($httperror == 413) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    // BAD
    if ($ret == null) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    $resp = json_decode($ret, true);
    
    // BAD
    // Malformed JSON response from Google
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->error(self::S_BAD_CAPTCHA);
    }
    
    // GOOD
    if ($resp['success']) {
      return true;
    }
    
    // BAD
    $this->error(self::S_BAD_CAPTCHA);
  }
  
  private function validate_csrf($ref_only = false) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != ''
        && !preg_match('/^https?:\/\/([_a-z0-9]+)\.4chan\.org/', $_SERVER['HTTP_REFERER'])) {
        return false;
      }
      
      if ($ref_only) {
        return true;
      }
      
      if (!isset($_COOKIE['_bctkn']) || !isset($_POST['_bctkn'])
        || $_COOKIE['_bctkn'] == '' || $_POST['_bctkn'] == ''
        || $_COOKIE['_bctkn'] !== $_POST['_bctkn']) {
        return false;
      }
    }
    
    return true;
  }
  
  private function init_cloudflare() {
    require_once 'lib/ini.php';
    
    global $configdir;
    
    load_ini("$configdir/cloudflare_config.ini");
    finalize_constants();
    
    define('CLOUDFLARE_EMAIL', 'cloudflare@4chan.org');
    define('CLOUDFLARE_ZONE', '4chan.org');
    define('CLOUDFLARE_ZONE_2', '4cdn.org');
  }
  
  private function get_next_events() {
    $tbl = self::EVENTS_TABLE;
    
    $now = $_SERVER['REQUEST_TIME'];
    
    $query = "SELECT * FROM `$tbl` WHERE ends_on > $now ORDER BY starts_on ASC";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error('Database Error (gne)');
    }
    
    $items = array();
    
    while ($row = mysql_fetch_assoc($res)) {
      $items[] = $row;
    }
    
    return $items;
  }
  
  // boards should already be escaped
  private function get_board_names($boards) {
    if (empty($boards)) {
      return array();
    }
    
    $clause = implode("','", $boards);
    
    $query = "SELECT dir, name FROM boardlist WHERE dir IN ('$clause') ORDER BY dir ASC";
    
    $result = mysql_global_call($query);
    
    $board_names = array();
    
    if (!$result) {
      return $boards;
    }
    
    while ($board = mysql_fetch_assoc($result)) {
      $board_names[$board['dir']] = $board['name'];
    }
    
    $board_map = array();
    
    foreach ($boards as $board) {
      if (isset($board_names[$board])) {
        $board_map[$board] = $board_names[$board];
      }
      else {
        $board_map[$board] = $board;
      }
    }
    
    return $board_map;
  }
  
  private function is_board_valid_submit($b) {
    $boards = $this->get_event_boards(self::EVENT_SUBMIT);
    return in_array($b, $boards);
  }
  
  private function is_board_valid_vote($b) {
    $boards = $this->get_event_boards(self::EVENT_VOTE);
    return in_array($b, $boards);
  }
  
  private function is_board_valid_view($b) {
    $boards = $this->get_event_boards(self::EVENT_EXPIRED);
    return in_array($b, $boards);
  }
  
  private function get_event_boards($event_type) {
    $event_type = (int)$event_type;
    
    if (isset($this->_board_cache[$event_type])) {
      return $this->_board_cache[$event_type];
    }
    
    $tbl = self::EVENTS_TABLE;
    
    $now = $_SERVER['REQUEST_TIME'];
    
    if ($event_type === self::EVENT_EXPIRED) {
      $evt = self::EVENT_VOTE;
      
      $query = <<<SQL
SELECT boards FROM `$tbl` WHERE
event_type = $evt AND starts_on <= $now AND ends_on < $now
SQL;
    }
    else {
      $query = <<<SQL
SELECT boards FROM `$tbl` WHERE
event_type = $event_type AND starts_on <= $now AND ends_on > $now
SQL;
    }
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return array();
    }
    
    $boards = array();
    
    while ($row = mysql_fetch_row($res)) {
      if ($row[0] === '') {
        $boards[] = self::ALL_BOARDS_TAG;
      }
      else {
        $boards = array_merge($boards, explode(',', $row[0]));
      }
    }
    
    $this->_board_cache[$event_type] = $boards;
    
    return $boards;
  }
  
  private function init_salt() {
    if (!$this->_salt) {
      $this->_salt = file_get_contents(self::SALT_PATH);
    }
    
    if (!$this->_salt) {
      $this->error('Internal Server Error (is0)');
    }
  }
  
  private function get_voter_id() {
    if (!$this->_salt) {
      $this->init_salt();
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    if (isset($_COOKIE['pass_id']) && $_COOKIE['pass_id'] !== '') {
      $pass_parts = explode('.', $_COOKIE['pass_id']);
      
      $pass_user = $pass_parts[0];
      $pass_session = $pass_parts[1];
      
      if (!$pass_user || !$pass_session) {
        return false;
      }
      
      $query = <<<SQL
SELECT user_hash, session_id, status,
UNIX_TIMESTAMP(expiration_date) as expiration_date
FROM pass_users WHERE pin != '' AND user_hash = '%s'
SQL;
      
      $res = mysql_global_call($query, $pass_user);
      
      if (!$res) {
        $this->error('Database Error (gvi1)');
      }
      
      $pass = mysql_fetch_assoc($res);
      
      if (!$pass || !$pass['session_id']) {
        return false;
      }
      
      $hashed_pass_session = substr(hash('sha256', $pass['session_id'] . $this->_salt), 0, 32);
      
      if ($hashed_pass_session !== $pass_session) {
        return false;
      }
      
      if ((int)$pass['expiration_date'] <= $now) {
        return false;
      }
      
      if ($pass['status'] != 0) {
        return false;
      }
      
      return hash('sha256', $pass['user_hash'] . $this->_salt);
    }
    else if (isset($_COOKIE['4chan_auser']) && $_COOKIE['4chan_auser'] !== '') {
      $username = $_COOKIE['4chan_auser'];
      $password = $_COOKIE['apass'];
      
      if (!$username || !$password) {
        return false;
      }
      
      $query = "SELECT * FROM `" . self::MODS_TABLE . "` WHERE `username` = '%s' LIMIT 1";
      
      $res = mysql_global_call($query, $username);
      
      if (!$res) {
        $this->error('Database Error (gvi2)');
      }
      
      $user = mysql_fetch_assoc($res);
      
      if (!$user) {
        return false;
      }
      
      $hashed_admin_password = hash('sha256', $user['username'] . $user['password'] . $this->_salt);
      
      if ($hashed_admin_password !== $password) {
        return false;
      }
      
      if ($user['password_expired'] == 1) {
        return false;
      }
      
      return hash('sha256', $user['id'] . $this->_salt);
    }
    
    return false;
  }
  
  private function is_image_blacklisted($file_path) {
    $md5 = md5_file($file_path);
    
    if (!$md5) {
      return true;
    }
    
    $tbl = self::BLACKLIST_TABLE;
    
    $query = "SELECT id FROM $tbl WHERE field = 'md5' AND contents = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $md5);
    
    if (!$res) {
      return true;
    }
    
    if (mysql_num_rows($res) === 0) {
      return false;
    }
    
    return true;
  }
  
  /**
   * Default page
   */
  public function index() {
    //$this->set_cache(true);
    
    $tbl = self::BANNERS_TABLE;
    $lim = self::PAGE_SIZE + 1;
    
    if (isset($_GET['offset'])) {
      $offset = (int)$_GET['offset'];
    }
    else {
      $offset = 0;
    }
    
    $where = 'status = ' . self::STATUS_ACTIVE;
    
    // Banner listing
    if (isset($_GET['board']) && $_GET['board'] !== '') {
      if (!$this->is_board_valid_vote($_GET['board'])) {
        $this->votable = false;
        
        if (!$this->is_board_valid_view($_GET['board'])) {
          $this->error(self::S_BAD_BOARD);
        }
      }
      else {
        $this->votable = true;
      }
      
      $where .= " AND board = '" . mysql_real_escape_string($_GET['board']) . "'";
      $this->board = htmlspecialchars($_GET['board'], ENT_QUOTES);
      $this->search_qs = 'board=' . $this->board . '&amp;';
      
      // Order
      if ($this->votable) {
        $order = 'id';
      }
      else {
        $order = 'score';
      }
      
      $query =<<<SQL
SELECT id, board, file_id, file_ext, score, width, height, th_width, th_height
FROM `$tbl` WHERE $where ORDER BY $order DESC LIMIT $offset,$lim
SQL;
      
      $res = mysql_global_call($query);
      
      if (!$res) {
        $this->error('Database Error', 500);
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
      
      if ($this->votable) {
        shuffle($this->items);
      }
    }
    // Schedule
    else {
      $this->items = $this->get_next_events();
      $this->board = null;
    }
    
    $this->vote_boards = $this->get_board_names($this->get_event_boards(self::EVENT_VOTE));
    $this->view_boards = $this->get_board_names($this->get_event_boards(self::EVENT_EXPIRED));
    $this->submit_boards = $this->get_board_names($this->get_event_boards(self::EVENT_SUBMIT));
    
    $this->renderHTML('banner-contest-vtube');
  }
  
  /**
   * Vote
   */
  public function vote() {
    $this->set_cache(false);
    
    // FIXME
    //$this->errorJSON("Can't let you do this right now.");
    
    if (!$this->validate_csrf()) {
      $this->errorJSON('Bad request.');
    }
    
    if (!isset($_POST['id']) || $_POST['id'] === '') {
      $this->errorJSON('Bad request.');
    }
    
    $voter_id = $this->get_voter_id();
    
    if (!$voter_id) {
      $this->errorJSON('Only 4chan Pass users can vote.');
    }
    
    $query = 'SELECT * FROM `' . self::BANNERS_TABLE . "` WHERE id = %d";
    
    $res = mysql_global_call($query, $_POST['id']);
    
    if (!$res) {
      $this->errorJSON('Database Error (1)');
    }
    
    $banner = mysql_fetch_assoc($res);
    
    if (!$banner) {
      $this->errorJSON(self::S_BAD_BANNER);
    }
    
    $status = (int)$banner['status'];
    
    if ($status !== self::STATUS_ACTIVE) {
      $this->errorJSON(self::S_BAD_BANNER);
    }
    
    $banners_tbl = self::BANNERS_TABLE;
    $votes_tbl = self::VOTES_TABLE;
    
    // Already voted
    $query = "SELECT id FROM `$votes_tbl` WHERE voter_id = '%s' AND banner_id = %d";
    
    $res = mysql_global_call($query, $voter_id, $banner['id']);
    
    if (!$res) {
      $this->errorJSON('Database Error (2-2)');
    }
    
    if (mysql_num_rows($res) > 0) {
      $this->errorJSON(self::S_ALREADY_VOTED, 100);
    }
    
    // Vote limit
    $query = "SELECT COUNT(*) FROM `$votes_tbl` WHERE voter_id = '%s' AND board = '%s'";
    
    $res = mysql_global_call($query, $voter_id, $banner['board']);
    
    if (!$res) {
      $this->errorJSON('Database Error (2-1)');
    }
    
    $vote_count = (int)mysql_fetch_row($res)[0];
    
    if ($vote_count >= self::VOTES_PER_USER) {
      $this->errorJSON(self::S_MAX_VOTES);
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    //mysql_global_call('START TRANSACTION');
    
    $query = <<<SQL
INSERT INTO `$votes_tbl` (voter_id, board, banner_id, created_on)
VALUES ('%s', '%s', '%s', %d)
SQL;
    
    $res = mysql_global_call($query, $voter_id, $banner['board'], $banner['id'], $now);
    
    if (!$res) {
      $this->errorJSON('Database Error (3)');
    }
    
    $query = "UPDATE `$banners_tbl` SET `score2` = `score2` + 1 WHERE id = %d LIMIT 1";
    
    $res = mysql_global_call($query, $banner['id']);
    
    if (!$res) {
      //mysql_global_call('ROLLBACK');
      $this->errorJSON('Database Error (4)');
    }
    
    //mysql_global_call('COMMIT');
    
    $votes_left = self::VOTES_PER_USER - $vote_count - 1;
    
    $plural = $votes_left === 1 ? '' : 's';
    
    $this->successJSON("Done. You have $votes_left vote$plural left on this board.");
  }
  
  /**
   * Submit
   */
  public function submit() {
    $this->set_cache(false);
    
    if (!$this->validate_csrf(true)) {
      $this->error('Bad request.');
    }
    
    // Captcha
    $this->verifyCaptcha();
    
    $tbl = self::BANNERS_TABLE;
    
    if (!isset($_FILES['file'])) {
      $this->error(self::S_NO_FILE);
    }
    
    $up_meta = $_FILES['file'];  
    
    if ($up_meta['error'] !== UPLOAD_ERR_OK) {
      if ($up_meta['error'] === UPLOAD_ERR_INI_SIZE) {
        $this->error(self::S_MAX_FILESIZE);
      }
      else if ($up_meta['error'] === UPLOAD_ERR_NO_FILE) {
        $this->error(self::S_NO_FILE);
      }
      else {
        $this->error('Internal Server Error (1)');
      }
    }
    
    if ($this->is_image_blacklisted($up_meta['tmp_name'])) {
      $this->error('Internal Server Error (1-1)');
    }
    
    if (!is_uploaded_file($up_meta['tmp_name'])) {
      $this->error('Internal Server Error (2)');
    }
    
    $file_size = filesize($up_meta['tmp_name']);
    
    if ($file_size > self::IMG_MAX_FILESIZE) {
      $this->error(self::S_MAX_FILESIZE);
    }
    
    $filemeta = getimagesize($up_meta['tmp_name']);
    
    if (!is_array($filemeta)) {
      $this->error(self::S_FILE_FORMAT);
    }
    
    // Check the filetype
    switch ($filemeta[2])
    {
      case IMAGETYPE_GIF:
        $file_ext = 'gif';
        break;
      case IMAGETYPE_JPEG:
        $file_ext = 'jpg';
        break;
      case IMAGETYPE_PNG:
        $file_ext = 'png';
        break;
      default:
        $this->error(self::S_FILE_FORMAT);
        break;
    }
    
    if (!in_array($file_ext, $this->allowed_filetypes)) {
      $this->error(self::S_FILE_FORMAT);
    }
    
    // Check image dimensions
    if (self::IMG_MAX_WIDTH > 0 && self::IMG_MAX_HEIGHT > 0) {
      if ($filemeta[0] > self::IMG_MAX_WIDTH || $filemeta[1] > self::IMG_MAX_HEIGHT) {
        $this->error(sprintf(self::S_FILE_MAX_DIMS, self::IMG_MAX_WIDTH, self::IMG_MAX_HEIGHT));
      }
    }
    else {
      if ($filemeta[0] !== self::IMG_WIDTH || $filemeta[1] !== self::IMG_HEIGHT) {
        $this->error(sprintf(self::S_FILE_DIMS, self::IMG_WIDTH, self::IMG_HEIGHT));
      }
    }
    
    // Check embedded data
    $this->cleanup_uploaded_file($up_meta['tmp_name'], $file_size, $file_ext);
    
    // Board
    if (!isset($_POST['board'])) {
      $this->error(self::S_BAD_BOARD);
    }
    
    $board = $_POST['board'];
    
    if (!$this->is_board_valid_submit($board)) {
      $this->error(self::S_BAD_BOARD);
    }
    
    // E-mail
    if (isset($_POST['email']) && $_POST['email'] !== '') {
      $email = $_POST['email'];
      
      if (strpos($email, '@') === false) {
        $this->error(self::S_BAD_EMAIL);
      }
      
      if (mb_strlen($email) > self::FIELD_MAX_LEN) {
        $this->error(sprintf(self::S_LONG_FIELD, 'E-Mail'));
      }
      
      $email = htmlspecialchars($email, ENT_QUOTES);
    }
    else {
      $email = '';
    }
    
    // Author
    if (isset($_POST['author']) && $_POST['author'] !== '') {
      $author = $_POST['author'];
      
      if (mb_strlen($author) > self::FIELD_MAX_LEN) {
        $this->error(sprintf(self::S_LONG_FIELD, 'Name'));
      }
      
      $author = htmlspecialchars($author, ENT_QUOTES);
    }
    else {
      $author = '';
    }
    
    // IP and cooldowns
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $thres = $_SERVER['REQUEST_TIME'] - self::COOLDOWN;
    
    $query = "SELECT id FROM `$tbl` WHERE ip = '%s' AND created_on > %d LIMIT 1";
    $res = mysql_global_call($query, $ip, $thres);
    
    if (!$res) {
      $this->error('Database Error (cd)');
    }
    
    if (mysql_num_rows($res) > 0) {
      $this->error(self::S_FILE_COOLDOWN);
    }
    
    // Created on
    $created_on = $_SERVER['REQUEST_TIME'];
    
    // Private ID used for deletion
    $private_id = bin2hex(openssl_random_pseudo_bytes(16));
    
    // File hash
    $file_id = sha1_file($up_meta['tmp_name']);
    
    $query = "SELECT id FROM `$tbl` WHERE file_id = '%s'";
    $res = mysql_global_call($query, $file_id);
    
    if (!$res) {
      $this->error('Database Error (fid)');
    }
    
    if (mysql_num_rows($res) > 0) {
      $this->error(self::S_DUP_FILE);
    }
    
    $file_path = self::IMG_ROOT . '/' . self::NAME_KEY . '_' . $file_id . '.' . $file_ext;
    
    // Thumbnail
    if (self::TH_MAX_DIMS > 0) {
      $th_path = self::IMG_ROOT . '/' . self::NAME_KEY . '_' . $file_id . '_th.jpg';
      
      $ret = $this->make_thumbnail_remote($up_meta['tmp_name'], $file_ext, $filemeta[0], $filemeta[1], $th_path);
      
      if (!$ret) {
        $this->error('Internal Server Error (mt)');
      }
      
      list($th_width, $th_height) = $ret;
    }
    else {
      $th_width = 0;
      $th_height = 0;
    }
    
    //mysql_global_call('START TRANSACTION');
    
    if (move_uploaded_file($up_meta['tmp_name'], $file_path) === false) {
      //mysql_global_call('ROLLBACK');
      $this->error('Internal Server Error (mf)');
    }
    
    $query =<<<SQL
INSERT INTO `$tbl`(
  `private_id`, `email`, `author`, `ip`, `board`, `file_id`, `file_ext`,
  `width`, `height`, `th_width`, `th_height`,`created_on`
) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, %d, %d, %d)
SQL;
    
    $res = mysql_global_call($query,
      $private_id,
      $email,
      $author,
      $ip,
      $board,
      $file_id,
      $file_ext,
      $filemeta[0],
      $filemeta[1],
      $th_width, 
      $th_height,
      $created_on
    );
    
    if (!$res || mysql_affected_rows() !== 1) {
      $this->error('Database Error (i)');
    }
    
    //mysql_global_call('COMMIT');
    
    $this->private_id = $private_id;
    
    $this->renderHTML('banner-contest-sent-vtube');
  }
  
  public function delete() {
    $this->set_cache(false);
    
    if (!isset($_GET['key']) || $_GET['key'] === '' || !preg_match('/^[a-f0-9]+$/', $_GET['key'])) {
      $this->error(self::S_BAD_BANNER);
    }
    
    $this->private_id = htmlspecialchars($_GET['key'], ENT_QUOTES);
    
    $this->_tkn = $this->get_csrf_token();
    $this->set_csrf_token($this->_tkn);
    
    $this->renderHTML('banner-contest-delete-vtube');
  }
  
  public function confirm_delete() {
    $this->set_cache(false);
    
    if (!$this->validate_csrf()) {
      $this->error('Bad request.');
    }
    
    if (!isset($_POST['key']) || $_POST['key'] === '') {
      $this->error('Bad request.');
    }
    
    // Captcha
    $this->verifyCaptcha();
    
    // Deleting
    $query = 'SELECT * FROM `' . self::BANNERS_TABLE . "` WHERE private_id = '%s'";
    
    $res = mysql_global_call($query, $_POST['key']);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    $banner = mysql_fetch_assoc($res);
    
    if (!$banner) {
      $this->error(self::S_BAD_BANNER);
    }
    
    $status = (int)$banner['status'];
    
    if ($status !== self::STATUS_PENDING && $status !== self::STATUS_ACTIVE) {
      $this->error(self::S_BAD_BANNER);
    }
    
    if ($this->is_board_valid_vote($banner['board'])) {
      $this->error(self::S_VOTE_NODEL);
    }
    
    // Deleting database entry
    $query = 'DELETE FROM `' . self::BANNERS_TABLE . "` WHERE private_id = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $_POST['key']);
    
    if (!$res) {
      $this->error('Database Error (2).');
    }
    
    // Deleting file
    $file_id = $banner['file_id'];
    $file_ext = $banner['file_ext'];
    
    if (!preg_match('/^[a-f0-9]+$/', $file_id) || !preg_match('/^[a-z]+$/', $file_ext)) {
      $this->error('Internal Server Error');
    }
    
    $file_name = $file_id . '.' . $file_ext;
    
    if ($status === self::STATUS_PENDING) {
      $file_name = self::NAME_KEY . '_' . $file_name;
    }
    
    $file_path = self::IMG_ROOT . '/' . $file_name;
    
    unlink($file_path);
    
    // thumbnail
    if ($banner['th_width'] > 0) {
      $th_name = $file_id . '_th.jpg';
      
      if ($status === self::STATUS_PENDING) {
        $th_name = self::NAME_KEY . '_' . $th_name;
      }
      
      $th_path = self::IMG_ROOT . '/' . $th_name;
      
      unlink($th_path);
    }
    else {
      $th_path = null;
    }
    
    // Purging cache
    $this->init_cloudflare();
    
    $url = 'https://s.4cdn.org/image/contest_banners/' . $file_name;
    cloudflare_purge_url($url, true);
    
    if ($th_path) {
      $url = 'https://s.4cdn.org/image/contest_banners/' . $th_name;
      cloudflare_purge_url($url, true);
    }
    
    $this->success();
  }
  
  public function check_status() {
    $this->set_cache(false);
    
    if (!$this->validate_csrf()) {
      $this->error('Bad request.');
    }
    
    if (!isset($_POST['key']) || $_POST['key'] === '') {
      $this->error('Bad request.');
    }
    
    // Captcha
    $this->verifyCaptcha();
    
    // Deleting
    $query = 'SELECT * FROM `' . self::BANNERS_TABLE . "` WHERE private_id = '%s'";
    
    $res = mysql_global_call($query, $_POST['key']);
    
    if (!$res) {
      $this->error('Database Error (1).');
    }
    
    $banner = mysql_fetch_assoc($res);
    
    if (!$banner) {
      $this->error(self::S_BAD_BANNER);
    }
    
    $status = (int)$banner['status'];
    
    if ($status === self::STATUS_ACTIVE) {
      $this->success(self::S_IS_ACTIVE);
    }
    else if ($status === self::STATUS_DISABLED) {
      $this->error(self::S_IS_DISABLED);
    }
    else if ($status === self::STATUS_PENDING) {
      $this->error(self::S_IS_PENDING);
    }
    else {
      $this->error(self::S_BAD_BANNER);
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
      die();
    }
  }
}

$ctrl = new App();
$ctrl->run();
