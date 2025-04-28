<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Reset Password</title>
  <link rel="stylesheet" type="text/css" href="/css/addaccount.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Reset Password</h1>
</header>
<div id="content">
<form id="form-add-account" autocomplete="off" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <th>Username</th>
      <td><input required type="text" name="username"></td>
    </tr>
    <tr>
      <th>No E-Mail</th>
      <td><label><input type="checkbox" name="no_email"> Don't email the new password, effectively locking the account.</label></td>
    </tr>
    <tr class="otp-row">
      <th><label for="otp" title="One-Time Password">2FA OTP</label></th>
      <td><input id="otp" maxlength="6" required type="text" name="otp"></td>
    </tr>
  </table><input type="hidden" name="action" value="reset">
  <button class="button btn-other" type="submit">Reset</button><?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>
