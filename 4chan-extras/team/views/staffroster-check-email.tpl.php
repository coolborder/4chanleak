<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Janitor Emails</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?12">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Janitor Emails</h1>
</header>
<div id="menu"><ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>/staffroster">Roster</a></li>
  <li><a class="button button-light" href="?action=activity">Activity</a></li>
  <li><a class="button button-light" href="?action=scoreboard">Scoreboard</a></li>
  <li><a class="button button-light" href="?action=manage">Manage</a></li>
  <li><a class="button button-light" href="?action=flags">Flags</a></li>
  <li><a class="button button-light" href="?action=j_names">/j/ Names</a></li>
  <li><a class="button button-light" href="?action=check_email">Check E-Mail</a></li>
</ul></div>
<div id="content">
<?php if ($this->email): ?>
<table class="items-table">
<thead>
  <tr>
    <th>E-Mail</th>
    <td><?php echo htmlspecialchars($this->email) ?></td>
  </tr>
</thead>
  <tr>
    <th>Username</th>
    <td><?php echo htmlspecialchars($this->username) ?></td>
  </tr>
</table>
<?php else: ?>
<form class="form" action="" method="post">
  <div class="field-row"><span class="form-label">Janitor E-Mail</span> <input type="text" class="field-long" required name="email" pattern=".+@.+"></div>
  <div class="field-row"><span data-tip="One-Tipe Password" class="form-label">2FA OTP</span> <input class="field-long" type="text" required name="otp"></div>
  <div class="field-row btn-row"><button class="button btn-other" name="action" value="check_email" type="submit">Get Username</button><?php echo csrf_tag() ?></div>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
