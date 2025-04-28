<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Ban Template Usage</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?12">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/staffroster.js?7"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Ban Template Usage</h1>
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
<div id="content" class="col-center">
<h3><?php echo htmlspecialchars($this->user['username']) ?></h3>
<div class="blk-cnt"><?php if (!$this->user['allow'] || $this->user['allow'] == 'all') {
  echo 'Global Janitor';
}
else {
  echo '/' . htmlspecialchars(implode('/, /', explode(',', $this->user['allow']))) . '/';
} ?></div>
<table class="items-table">
<thead>
  <tr>
    <th colspan="7"><span data-tip="Over the past <?php echo self::STATS_PERIOD_ACTION_MONTHS ?> months" class="wot t-cat-lbl">Report Processing Stats</span><span class="button-light btn-xs" data-cmd="toggle-cat" data-cat="repstats">Show</span></th>
  </tr>
</thead>
<tbody class="col-center hidden" id="js-repstats">
  <tr>
    <th>Board</th>
    <th>Total</th>
    <th>Deleted</th>
    <th>Cleared</th>
    <th>Deleted Threads</th>
    <th>Cleared Threads</th>
    <th><span data-tip="Ban Requests tied to deletions only." class="wot">BRs</span></th>
  </tr>
  <?php foreach ($this->action_stats as $board => $stats): ?>
  <tr>
    <td><b><?php echo $board ?></b></td>
    <td><?php $total = (int)$stats['del_total'] + (int)$stats['clear_total']; echo $total; ?></td>
    <td><?php echo (int)$stats['del_total'] ?> (<?php echo $total ? round(((int)$stats['del_total'] / (float)$total) * 100) : 100 ?>%)</td>
    <td><?php echo (int)$stats['clear_total'] ?> (<?php echo $total ? round(((int)$stats['clear_total'] / (float)$total) * 100) : 100 ?>%)</td>
    <td><?php echo (int)$stats['del_threads'] ?> (<?php echo $total ? round(((int)$stats['del_threads'] / (float)$total) * 100) : 100 ?>%)</td>
    <td><?php echo (int)$stats['clear_threads'] ?> (<?php echo $total ? round(((int)$stats['clear_threads'] / (float)$total) * 100) : 100 ?>%)</td>
    <td><?php echo (int)$stats['ban_requests'] ?> (<?php echo $total ? round(((int)$stats['ban_requests'] / (float)$total) * 100) : 100 ?>%)</td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<table class="items-table inline-table">
<thead>
  <tr>
    <th colspan="2"><span data-tip="Over the past <?php echo self::STATS_PERIOD_MONTHS ?> months" class="wot">Most Used Templates</span></th>
  </tr>
</thead>
<tbody class="col-center">
  <?php foreach ($this->global_usage as $tpl_id => $count): ?>
  <tr>
    <td><?php echo (isset($this->ban_templates[$tpl_id]) ? $this->ban_templates[$tpl_id] : $tpl_id) ?></td>
    <td><b><?php echo $count ?></b> (<span data-tip="Acceptance Rate" class="type-accept"><?php
      $denied = isset($this->denied_usage[$tpl_id]) ? $this->denied_usage[$tpl_id] : 0;
      $amended = isset($this->amended_usage[$tpl_id]) ? $this->amended_usage[$tpl_id] : 0;
      echo round(((($count - $denied - $amended)) / (float)$count) * 100);
    ?>%</span> / <span data-tip="Acceptance Rate (Including Amends)" class="type-other"><?php
      if (isset($this->denied_usage[$tpl_id])) {
        echo round(((($count - $this->denied_usage[$tpl_id])) / (float)$count) * 100);
      }
      else {
        echo '100';
      }
    ?>%)</td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<table class="items-table inline-table">
<thead>
  <tr>
    <th colspan="2"><span data-tip="Over the past <?php echo self::STATS_PERIOD_MONTHS ?> months" class="wot">Most Denied Templates</span></th>
  </tr>
</thead>
<tbody class="col-center">
  <?php foreach ($this->denied_usage as $tpl_id => $count): ?>
  <tr>
    <td><?php echo (isset($this->ban_templates[$tpl_id]) ? $this->ban_templates[$tpl_id] : $tpl_id) ?></td>
    <td><b><?php echo $count ?></b></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<table class="items-table inline-table">
<thead>
  <tr>
    <th colspan="2"><span data-tip="Over the past <?php echo self::STATS_PERIOD_MONTHS ?> months" class="wot">Most Amended Templates</span></th>
  </tr>
</thead>
<tbody class="col-center">
  <?php foreach ($this->amended_usage as $tpl_id => $count): ?>
  <tr>
    <td><?php echo (isset($this->ban_templates[$tpl_id]) ? $this->ban_templates[$tpl_id] : $tpl_id) ?></td>
    <td><b><?php echo $count ?></b></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
