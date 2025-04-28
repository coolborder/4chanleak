<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Create 4chan Pass</title>
  <link rel="stylesheet" type="text/css" href="/css/addaccount.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Create 4chan Pass</h1>
</header>
<div id="menu"><ul>
  <li><a class="button button-light" href="?action=batches">Batch Create</a></li>
</ul>
</div>
<div id="content">
<?php if (isset($this->user_hash)): ?>
<div id="success">Done.</div>
<div id="pass-created">
  <ul>
    <li>Token: <?php echo $this->user_hash ?></li>
    <li>PIN: <?php echo $this->plain_pin ?></li>
    <li>Paid: $<?php echo $this->price_paid_dollars ?></li>
  </ul>
</div>
<?php else: ?>
<form id="form-add-account" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <th>E-mail</th>
      <td><input autocomplete="off" required pattern=".+@.+" type="text" name="email"></td>
    </tr>
    <tr>
      <th>Gift E-mail</th>
      <td><input autocomplete="off" pattern=".+@.+" type="text" name="gift_email"></td>
    </tr>
    <tr>
      <th>Transaction ID</th>
      <td><input required type="text" name="transaction"></td>
    </tr>
    <tr>
      <th>Price in cents</th>
      <td><input autocomplete="off" required pattern="[0-9]+" type="text" name="price_paid"></td>
    </tr>
    <tr>
      <th><span class="wot" title="The expiration date will be set automatically on first usage.">No expiration</span></th>
      <td><input type="checkbox" name="preloaded"></td>
    </tr>
    <tr class="otp-row">
      <th><label for="otp" title="One-Time Password">2FA OTP</label></th>
      <td><input id="otp" maxlength="6" required type="text" name="otp"></td>
    </tr>
  </table><input type="hidden" name="action" value="create">
  <button class="button btn-other" type="submit">Create</button><?php echo csrf_tag() ?>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
