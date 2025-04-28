<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Flood Log</title>
  <link rel="stylesheet" type="text/css" href="/css/floodlog.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Recently Blocked Posts</h1>
</header>
<div id="menu" class="center-txt">
<ul>
  <li><a href="<?php echo self::WEBROOT ?>">All</a></li>
  <?php foreach ($this->boards as $board): ?>
  <li><a href="<?php echo self::WEBROOT ?>?board=<?php echo $board ?>"><?php echo $board ?></a></li>
  <?php endforeach ?>
</ul>
</div>
<div id="content">
<table class="items-table compact-table">
<thead>
  <tr>
    <th>ID</th>
    <th>Score</th>
    <th>Board</th>
    <th>Thread ID</th>
    <th>Date</th>
    <th>IP</th>
    <th>Meta</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $item): ?>
  <tr>
    <td><?php echo $item['id'] ?></td>
    <td><?php echo $item['arg_num'] ?></td>
    <td><?php echo $item['board'] ?></td>
    <td><?php echo $item['thread_id'] ?></td>
    <td><?php echo $item['created_on'] ?></td>
    <td><?php echo $item['ip'] ?></td>
    <td class="col-meta"><?php echo $item['meta'] ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
<?php if (isset($this->offset)): ?>
<tfoot>
  <tr>
    <td colspan="7" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php if ($this->current_board) echo 'board=' . $this->current_board . '&amp;' ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php if ($this->current_board) echo 'board=' . $this->current_board . '&amp;' ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
<?php endif ?>
</table>
</div>
<footer></footer>
</body>
</html>
