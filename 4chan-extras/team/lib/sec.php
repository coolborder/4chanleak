<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (!isset($_COOKIE['_tkn']) || !isset($_POST['_tkn'])
    || $_COOKIE['_tkn'] == '' || $_POST['_tkn'] == ''
    || $_COOKIE['_tkn'] !== $_POST['_tkn']) {
    die('Bad token.');
  }
}

?>