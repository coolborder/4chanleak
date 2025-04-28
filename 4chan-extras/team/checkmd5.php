<?php
require_once 'lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('mod')) {
  APP::denied();
}

require_once 'lib/csp.php';

class App {
  protected
    // Routes
    $actions = array(
      'index'
    )
  ;
  
  const TPL_ROOT = 'views/';
  
  static public function denied() {
    require_once(self::TPL_ROOT . 'denied.tpl.php');
    die();
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
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  /**
   * Strips tags from webms
   * $file must be safe to use as shell argument
   */
  private function strip_webm_tags($file) {
    $binary = '/usr/local/bin/ffmpeg-ayase';
    
    $out_file = $file . '_tmpff';
    
    shell_exec("$binary -f webm -i \"$file\" -map_metadata -1 -bitexact -c copy -f webm -y \"$out_file\"");
    
    if (!file_exists($out_file)) {
      return false;
    }
    
    return rename($out_file, $file);
  }
  
  /**
   * Strips extensions and other extra data from gifs
   * $file must be safe to use as shell argument
   */
  private function strip_gif_extensions($file) {
    $binary = '/usr/local/bin/gifsicle';
    
    $res = system("$binary --no-comments --no-extensions \"$file\" -o \"$file\" >/dev/null 2>&1");
    
    if ($res !== false) {
      return true;
    }
  }
  
  /**
   * Strips non-whitelisted chunks from png images
   */
  private function strip_png_chunks($file, $max_chunk_len = 16 * 1024 * 1024) {
    $keep_chunks = [
      'ihdr',
      'plte',
      'idat',
      'iend',
      'trns',
      'gama',
      'sbit',
      'phys',
      'srgb',
      'bkgd',
      'time',
      'chrm'
    ];
    
    $img = fopen($file, 'rb');
    
    if (!$img) {
      return -9;
    }
    
    $data = fread($img, 8);
    
    if ($data !== "\x89PNG\r\n\x1a\n") {
      fclose($img);
      return -1;
    }
    
    $output = '';
    
    $skip_count = 0;
    
    while (!feof($img)) {
      $chunk_len_buf = fread($img, 4);
      
      if (!$chunk_len_buf) {
        break;
      }
      
      if (strlen($chunk_len_buf) !== 4) {
        return -1;
      }
      
      $chunk_len = unpack('N', $chunk_len_buf)[1];
      
      if ($chunk_len > $max_chunk_len) {
        return -1;
      }
      
      $chunk_type_buf = fread($img, 4);
      
      if (strlen($chunk_type_buf) !== 4) {
        return -1;
      }
      
      $chunk_type = strtolower($chunk_type_buf);
      
      // aPNG is not supported
      if ($chunk_type === 'actl' || $chink_type === 'fctl' || $chink_type === 'fdat') {
        return -2;
      }
      
      if (in_array($chunk_type, $keep_chunks)) {
        if ($chunk_len > 0) {
          $data = fread($img, $chunk_len);
          
          if (strlen($data) !== $chunk_len) {
            return -1;
          }
        }
        else {
          $data = '';
        }
        
        $crc = fread($img, 4);
        
        if (strlen($crc) !== 4) {
          return -1;
        }
        
        $output .= $chunk_len_buf . $chunk_type_buf . $data . $crc;
        
        if ($chunk_type === 'iend') {
          fread($img, 1);
          if (!feof($img)) {
            $skip_count++;
          }
          break;
        }
      }
      else {
        fseek($img, $chunk_len + 4, SEEK_CUR);
        $skip_count++;
      }
    }
    
    fclose($img);
    
    if ($output === '') {
      return -1;
    }
    
    if ($skip_count === 0) {
      return 0;
    }
    
    $out_file = $file . '_pngtmp';
    
    $out = fopen($out_file, 'wb');
    
    if (!$out) {
      return -9;
    }
    
    if (fwrite($out, "\x89PNG\r\n\x1a\n") === false) {
      return -9;
    }
    
    if (fwrite($out, $output) === false) {
      return -9;
    }
    
    fclose($out);
    
    if (rename($out_file, $file) === false) {
      return -9;
    }
    
    return $skip_count;
  }
  
  /**
   * Index
   */
  public function index() {
    $this->md5_noexif = $this->md5 = null;
    
    if (isset($_FILES['file'])) {
      $file = $_FILES['file']['tmp_name'];
      
      $this->md5 = md5_file($file);
      
      $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
      
      if (!$ext) {
        $this->error('Unknown file format');
      }
      
      $ext = strtolower($ext);
      
      // Process jpeg
      if ($ext === 'jpg' || $ext === 'jpeg') {
        $size = getimagesize($file);
        
        if (is_array($size) && $size[2] === 2) {
          system("/usr/local/bin/jpegtran -copy none -outfile '$file' '$file'");
          $this->md5_noexif = md5_file($file);
        }
        else {
          $this->error('Not a JPEG file');
        }
      }
      // Process gif
      else if ($ext === 'gif') {
        if ($this->strip_gif_extensions($file) === true) {
          $this->md5_noexif = md5_file($file);
        }
      }
      // Process png
      else if ($ext === 'png') {
        if ($this->strip_png_chunks($file) >= 0) {
          $this->md5_noexif = md5_file($file);
        }
        else {
          $this->md5_noexif = 'Invalid PNG file';
        }
      }
      // Process webm
      else if ($ext === 'webm') {
        if ($this->strip_webm_tags($file) === false) {
          $this->error('Internal Server Error (iw0)');
        }
        $this->md5_noexif = md5_file($file);
      }
    }
    
    $this->renderHTML('checkmd5');
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
