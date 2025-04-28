<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Staff Roster</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?12">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Staff Roster</h1>
</header>
<?php if ($this->action == 'ips'): ?>
<div id="menu"><ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>/staffroster">Roster</a></li>
  <li><a class="button button-light" href="?action=activity">Activity</a></li>
  <li><a class="button button-light" href="?action=scoreboard">Scoreboard</a></li>
  <li><a class="button button-light" href="?action=manage">Manage</a></li>
  <li><a class="button button-light" href="?action=flags">Flags</a></li>
  <li><a class="button button-light" href="?action=j_names">/j/ Names</a></li>
  <li><a class="button button-light" href="?action=check_email">Check E-Mail</a></li>
</ul>
</div>
<div id="content">
<table id="ips-table" class="items-table">
  <tr><th></th><th></th></tr>
<?php
foreach ($this->ips as $user):
$ips = json_decode($user['ips'], true);
if ($ips === null) {
  $ips = [];
}
arsort($ips);?>
  <tr>
    <td><a href="?action=ips&username=<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>"><?php echo $user['username'] ?></a></td>
    <td><?php if ($user['last_ua']): ?><div class="user-note"><?php echo htmlspecialchars($user['last_ua']) ?></div><?php endif ?><?php $now = $_SERVER['REQUEST_TIME']; foreach($ips as $ip => $timestamp):
      if (strpos($ip, '10.0.0') === 0) {
        continue;
      }
      if ($this->recent) {
        if (!$timestamp || (($now - $this->recent) > $timestamp)) {
          continue;
        }
      }
    ?>
    <?php echo $ip . ' (' . date('m/d/y H:i:s', $timestamp) . ($this->need_geo ? (' - ' . $this->getIPLoc($ip)) : '') . ')' ?><br>
  <?php endforeach ?></td>
  </tr>
<?php endforeach ?>
</table>
</div>
<?php else: ?>
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
<table id="staff-table" class="items-table">
<thead>
  <tr>
    <th>Board</th>
    <th>Count</th>
    <th>Janitors</th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->boards as $board => $user_ids): ?>
  <tr>
    <td class="col-board"><?php echo $board ?></td>
    <td class="col-count"><span class="count-<?php
      $count = count($user_ids);
      if ($count < self::ROSTER_THRES) {
        echo 'red';
      }
      else {
        echo 'ok';
      }
    ?>"><?php echo $count ?></span></td>
    <td class="col-users"><?php
      $tmp = array();
      foreach ($user_ids as $id) {
        $tmp[] = $this->users[$id]['username'];
      }
      echo implode(', ', $tmp);
    ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<?php endif ?>
<footer></footer>
</body>
</html>
