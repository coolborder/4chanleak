<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Reset 4chan Pass</title>
  <link rel="stylesheet" type="text/css" href="/css/reset4chanpass.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Reset 4chan Pass</h1>
</header>
<div id="content">
<?php if (isset($this->user_hash)): ?>
<div id="success">Done.</div>
<div id="pass-created">
  <ul>
    <li>Token: <?php echo $this->user_hash ?></li>
    <li>Pin: <?php echo $this->plain_pin ?></li>
  </ul>
</div>
<?php else: ?>
<form id="form-add-account" autocomplete="off" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <td>Token or Transaction ID</td>
    </tr>
    <tr>
      <td><input required type="text" name="uid"></td>
    </tr>
    <tr class="otp-row">
      <td><label for="otp" title="One-Time Password">2FA OTP</label></td>
    </tr>
    <tr>
      <td><input id="otp" maxlength="6" required type="text" name="otp"></td>
    </tr>
  </table><input type="hidden" name="action" value="reset">
  <button class="button btn-other" type="submit">Reset</button><?php echo csrf_tag() ?>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
