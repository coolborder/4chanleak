<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>2FA Required</title>
  <link rel="stylesheet" type="text/css" href="/css/otp-session.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<div id="content">
<?php if ($bad_otp): ?>
<div id="error">Invalid or expired OTP.</div>
<?php else: ?>
<div id="otp-protip">
  <h3>Two-factor authentication (2FA) is required to access this tool.</h3>
  <p>Your session will be valid for <?php echo OTPSession::prettyTimeout() ?>.</p></div>
</div>
<form id="otp-form" action="" method="POST" enctype="multipart/form-data">
  <?php echo csrf_tag(); ?>
  <label>One-time code</label>
  <input type="text" maxlength="6" name="otp" required autocomplete="off">
  <button class="button btn-other" type="submit">Authenticate</button>
</form>
<?php endif ?>
</div>
</body>
</html>
