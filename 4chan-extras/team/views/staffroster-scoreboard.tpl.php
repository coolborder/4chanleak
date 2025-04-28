<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Ban Requests Scoreboard</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?12">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/staffroster.js?7"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Ban Requests Scoreboard</h1>
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
<div class="mod-ctrl"><label><input id="mod-ctrl" type="checkbox"> Include Moderators</label></div>
<table class="items-table">
<thead>
  <tr>
    <th>User</th>
    <th><span data-tip="Accepted / Denied in the past <?php echo self::STATS_PERIOD_MONTHS ?> months" class="wot">Recent A/D</span></th>
    <th><span data-tip="Amended in the past <?php echo self::STATS_PERIOD_MONTHS ?> months" class="wot">Recent Amends</span></th>
    <th><span data-tip="Total in the past <?php echo self::STATS_PERIOD_MONTHS ?> months" class="wot">Recent Total</span></th>
    <th><span data-tip="Accepted / Denied" class="wot">All Time A/D</span></th>
    <th><span data-tip="Potential suspicious activity" class="wot">Extra Logs</span></th>
  </tr>
</thead>
<tbody id="items" class="col-center">
  <?php foreach ($this->user_stats as $user): $recent = isset($this->user_stats_recent[$user['janitor']]) ? $this->user_stats_recent[$user['janitor']] : null; ?>
  <tr<?php if ($this->current_users[$user['janitor']]['level'] != 'janitor') echo ' class="mod-row hidden"'; ?>>
    <td><a data-tip="Show Details" href="?action=scoreboard&amp;id=<?php echo $this->current_users[$user['janitor']]['id'] ?>"><?php echo $user['janitor'] ?></a></td>
    <td><?php if ($recent): ?><span class="type-accept"><?php echo $recent['accepted'] ?></span> / <span class="type-deny"><?php echo $recent['denied'] ?></span> (<?php echo $recent['accepted_ratio'] ?>%)<?php endif ?></td>
    <td><?php if ($recent): ?><?php echo $recent['amended'] ?> (<?php echo $recent['amended_ratio'] ?>%)<?php endif ?></td>
    <td><?php if ($recent): ?><?php echo $recent['accepted'] + $recent['denied'] ?><?php endif ?></td>
    <td class="cell-sep-l"><span class="type-accept"><?php echo $user['approved'] ?></span> / <span class="type-deny"><?php echo $user['denied'] ?></span> (<?php echo ((int)((float)$user['ratio'] * 100.0)) ?>%)</td>
    <td class="cell-sep-l"><a href="/manager/staffroster?action=extra_logs&user=<?php echo $user['janitor'] ?>">View</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
