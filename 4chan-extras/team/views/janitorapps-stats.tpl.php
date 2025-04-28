<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Janitor Applications</title>
  <link rel="stylesheet" type="text/css" href="/css/janitorapps.css?9">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/janitorapps.js?13"></script>
</head>
<body data-mode="stats">
<header>
  <h1 id="title">Janitor Applications</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu"><a class="button button-light right" href="?">Rate Apps</a><a class="button button-light right" href="?action=review">Review Apps</a></div>
<div id="content">
<div id="wrapper">
<table class="items-table">
<thead>
  <tr>
    <th colspan="3">Applications</th>
  </tr>
  <tr>
    <th>Total</th>
    <th><span class="wut" data-tip="Apps with less than <?php echo $this->voteCountThreshold ?> vote(s)">Unrated</span></th>
    <th>Ignored</th>
  </tr>
  <tr>
    <td><?php echo $this->totals['total'] ?></td>
    <td><?php echo $this->totals['open'] ?></td>
    <td><?php echo $this->totals['ignored'] ?></td>
  </tr>
    <th>Accepted</th>
    <th>Oriented</th>
    <th>Completed</th>
  </tr>
  <tr>
    <td><?php echo $this->totals['accepted'] ?></td>
    <td><?php echo $this->totals['oriented'] ?></td>
    <td><?php echo $this->totals['completed'] ?></td>
  </tr>
  <tr>
    <th>Board</th>
    <th><span class="wut" data-tip="First choice only">Primary</span></th>
    <th>Total</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->boards as $board => $stats): ?>
  <tr>
    <th>/<?php echo $board ?>/</th>
    <td><?php echo $stats[0] ? $stats[0] : 0?></td>
    <td><?php echo $stats[0] + $stats[1] ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<table class="items-table">
<thead>
  <tr>
    <th colspan="3">Activity</th>
  </tr>
  <tr>
    <th>User</th>
    <th>Apps Rated</th>
    <th>Avg. Rating</th>
  </tr>
</thead>
<tbody>
  <?php $total = 0; $score = 0; foreach ($this->users as $username => $stats): $total += $stats[0]; $score += $stats[1]; ?>
  <tr>
    <th><?php echo $username ?></th>
    <td><?php echo $stats[0] ? $stats[0] : 0 ?></td>
    <td><?php echo $stats[0] ? round($stats[1] / $stats[0], 2) : 0 ?></td>
  </tr>
  <?php endforeach ?>
  <tr>
    <th colspan="3"></th>
  </tr>
  <tr>
    <th>Total</th>
    <td><?php echo $total ?></td>
    <td><?php echo $score ? round($score / $total, 2) : 0 ?></td>
  </tr>
</tbody>
</table>
</div>
</div>
<footer></footer>
<div data-cmd="shift-panel" id="panel-stack" tabindex="-1"></div>
</body>
</html>
