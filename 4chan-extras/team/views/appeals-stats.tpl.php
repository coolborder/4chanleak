<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Appeal Stats</title>
  <link rel="stylesheet" type="text/css" href="/css/appeals.css?103">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?3"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/appeals.js?32"></script>
</head>
<body id="appeal-stats">
<header>
  <h1 id="title">Appeal Stats</h1>
</header>
<div id="menu">Statistics for the past <?php echo $this->statsRange ?> days<a href="/appeals" class="button button-light right">Appeals</a></div>
<table id="stats-table" class="items-table">
  <tr>
    <th>Total</th>
    <th>Accepted</th>
    <th>Denied</th>
    <th>Accept Rate</th>
    <th>Average Wait Time</th>
  </tr>
  <tr>
    <td><?php echo $this->total ?></td>
    <td><?php echo $this->total_accepted ?></td>
    <td><?php echo $this->total_denied ?></td>
    <td><?php echo $this->accept_rate ?>%</td>
    <td><?php echo $this->getPreciseDuration($this->average_delay) ?></td>
  </tr>
</table>
<table id="stats-table" class="items-table">
  <tr>
    <th>User</th>
    <th>Total</th>
    <th>Accepted</th>
    <th>Denied</th>
    <th>Accept Rate</th>
  </tr>
<?php foreach ($this->users as $username => $data): ?>
  <tr>
    <td><?php echo $username ?></td>
    <td><?php echo $data['total'] ?></td>
    <td><?php echo $data['accepted'] ?></td>
    <td><?php echo $data['denied'] ?></td>
    <td><?php echo $data['accept_rate'] ?>%</td>
  </tr>
<?php endforeach ?>
</table>
</body>
</html>
