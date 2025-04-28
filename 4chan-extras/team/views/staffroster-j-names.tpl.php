<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>/j/ Usernames</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?12">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">/j/ Usernames</h1>
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
<?php if ($this->name): ?>
<table class="items-table">
<thead>
  <tr>
    <th>Post ID</th>
    <th>Username</th>
  </tr>
</thead>
<tbody>
  <tr>
    <td><?php echo $this->post_id ?></td>
    <td><?php echo htmlspecialchars($this->name) ?></td>
  </tr>
</tbody>
</table>
<?php else: ?>
<form class="form" action="" method="get">
  <div class="field-row"><span class="form-label">/j/ Post Number</span> <input type="text" name="post_id" pattern="[0-9]*"> <button class="button btn-other" name="action" value="j_names" type="submit">Check</button></div>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
