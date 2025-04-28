<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Staff Activity</title>
  <link rel="stylesheet" type="text/css" href="/css/staffroster.css?12">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/staffroster.js?8"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-page="activity" data-tips>
<header>
  <h1 id="title">Staff Activity</h1>
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
<table class="items-table">
<thead>
  <tr>
    <th><span class="js-sort-btn clickable sorted-asc" data-cid="0" data-cmd="sort-table">User</a></th>
    <th>Boards</th>
    <th><span class="js-sort-btn clickable" data-cid="2" data-cmd="sort-table">Last Action</a></th>
    <th><span class="js-sort-btn clickable" data-cid="3" data-cmd="sort-table" data-tip="Actions in the past <?php echo self::STATS_PERIOD_ACTIVITY_DAYS ?> day(s). Counts deleted posts, cleared reports, processed appeals and ban requests.">Recent Actions</a></th>
  </tr>
</thead>
<tbody id="items" class="col-center">
  <?php
  foreach ($this->last_actions as $username => $delta):
    if (isset($this->action_count[$username])) {
      $actions = $this->action_count[$username];
    }
    else {
      $actions = 0;
    }
  ?>
  <tr>
    <td class="user-<?php echo $this->levels[$username] ?>"><?php echo $username ?></td>
    <td><?php echo implode(', ', explode(',', $this->user_boards[$username])); ?></td>
    <td data-ival="<?php echo $delta ?>"><span class="count-<?php
      $count = count($user_ids);
      if ($delta > self::ACTIVITY_THRES) {
        echo 'red';
      }
      else {
        echo 'ok';
      }
    ?>"><?php echo $this->getPreciseDuration($delta) ?> ago</span></td>
    <td data-ival="<?php echo $actions ?>"><?php echo $actions ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
