<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Add Account</title>
  <link rel="stylesheet" type="text/css" href="/css/addaccount.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/addaccount.js"></script>
</head>
<body>
<header>
  <h1 id="title">Add Account</h1>
</header>
<div id="content">
<form id="form-add-account" autocomplete="off" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <th>Username</th>
      <td><input title="Only alphanumeric characters, dashes and underscores are allowed" required pattern="[-_a-zA-Z0-9]+" maxlength="<?php echo self::USERNAME_MAX_LEN ?>" type="text" name="username"></td>
    </tr>
    <tr>
      <th>E-mail</th>
      <td><input required pattern=".+@.+" type="text" name="email"></td>
    </tr>
    <tr>
      <th>Level</th>
      <td>
      <div class="select-box"><select id="level-field" data-flags="<?php echo self::FLAGS_MIN_LEVEL ?>" name="level">
        <?php foreach ($this->levels as $level): ?>
        <option value="<?php echo $level ?>"><?php echo $this->_level_labels[$level] ?></option>
        <?php endforeach ?>
      </select></div>
      </td>
    </tr><?php if ($this->canSetFlags()): ?>
    <tr>
      <th>Flags</th>
      <td><input pattern="[_a-z0-9]*" id="flags-field" type="text" name="flags"></td>
    </tr><?php endif ?>
    <tr>
      <th>Allow Boards</th>
      <td><input type="text" pattern="[a-z0-9]*" placeholder="Example: a,jp,g or all" name="allow"></td>
    </tr>
    <?php if (has_level('manager')): ?>
    <tr>
      <th>Bypass Agreement</th>
      <td class="cell-left"><input type="checkbox" name="no_agreement"></td>
    </tr><?php endif ?>
    <tr class="otp-row">
      <th><label for="otp" title="One-Time Password">2FA OTP</label></th>
      <td><input id="otp" maxlength="6" required type="text" name="otp"></td>
    </tr>
  </table><input type="hidden" name="action" value="create">
  <button class="button btn-other" type="submit">Create</button><?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>
