<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Team 4chan</title>
  <link rel="stylesheet" type="text/css" href="/css/index.css?9">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Scoreboard</h1>
</header>
<div id="content">
<h3>Past 30 days activity</h3>
<table class="items-table">
  <thead>
    <tr>
      <th>User</th>
      <th>Ban Requests</th>
      <th><span data-tip="Includes bans from ban requests" class="wot">Total Bans</span></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($this->brs as $user_id => $count): if (!isset($this->users[$user_id])) { continue; } ?>
    <tr>
      <td><?php echo htmlspecialchars($this->users[$user_id]) ?></td>
      <td><?php echo $this->brs[$user_id] ?></td>
      <td><?php echo $this->bans[$this->users[$user_id]] ?></td>
    </tr>
    <?php endforeach ?>
  </tbody>
</table>
</div>
</body>
</html>
