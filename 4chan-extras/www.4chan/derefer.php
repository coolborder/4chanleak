<?php
require_once 'lib/admin.php';
require_once 'lib/auth.php';

if (!isset($_COOKIE['4chan_auser'])) {
  die();
}

mysql_global_connect();

auth_user();

if (!has_level('janitor')) {
  die();
}

if (isset($_GET['url'])) {
  echo "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0; URL=" . htmlspecialchars(rawurldecode($_GET['url']), ENT_QUOTES) . "\">";
}
