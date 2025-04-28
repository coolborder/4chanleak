<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>NCMEC Reports</title>
  <link rel="stylesheet" type="text/css" href="/css/ncmecreports.css?1">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">NCMEC Reports</h1>
</header>
<div id="menu">
<?php if (isset($this->report)): ?>
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT . ($this->old_mode ? '?old' : '') ?>">Return</a></li>
</ul>
<?php else: ?>
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>?search">Search</a></li>
</ul>
<?php endif ?>
<form action="" method="get"><input name="id" placeholder="4chan ID" type="text"><button class="button button-light" name="action" value="view" type="submit">View</button><?php if ($this->old_mode): ?><input type="hidden" name="old"><?php endif ?></form>
<form action="" method="get"><input name="ncmec_id" placeholder="NCMEC ID" type="text"><button class="button button-light" name="action" value="view" type="submit">View</button><?php if ($this->old_mode): ?><input type="hidden" name="old"><?php endif ?></form>
</div>
<?php if (!isset($this->report)): ?>
<div id="nav-links">
  <?php if ($this->unsent_mode): ?><a href="<?php echo self::WEBROOT ?>">All</a>
  <?php else: ?><a href="?unsent">Unsent (<?php echo $this->unsent_count ?>)</a><?php endif ?>
  <?php if ($this->old_mode): ?><a href="<?php echo self::WEBROOT ?>">New Reports</a>
  <?php else: ?><a href="?old">Old Reports</a><?php endif ?>
</div>
<?php endif ?>
<div id="content">
<?php /**
 * Showing individual reports
 */
if (isset($this->report)): $r = $this->report; ?>
<div class="pre-block">4chan Report ID: <?php echo $r['id'] ?>

NCMEC Report ID: <?php echo $r['report_ncmec_id'] ?>

Report Transmitted: <?php echo $r['report_sent_timestamp'] ?>

Post: /<?php echo $r['board'] ?>/<?php echo $r['post_num'] ?>

Report XML Document:

<?php echo htmlspecialchars($r["report_copy"]) ?>
</div>
<?php
/**
 * Table view
 */
else: ?>
<table class="items-table" id="items">
<thead>
  <tr>
    <th>ID</th>
    <th>ID NCMEC</th>
    <th>Board</th>
    <th>Post No.</th>
    <th>Sent on</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $item): $old_qs = $this->old_mode ? 'old&amp;' : '';?>
  <tr>
    <td><a href="?<?php echo $old_qs ?>action=view&amp;id=<?php echo $item['id'] ?>"><?php echo $item['id'] ?></a></td>
    <td><?php echo $item['ncmec_id'] ?></td>
    <td><?php echo $item['board'] ?></td>
    <td><?php echo $item['post_num'] ?></td>
    <td><?php if ($item['report_sent']) echo $item['sent_on'] ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
<?php if (isset($this->offset)): ?>
<tfoot>
  <tr>
    <td colspan="5" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
<?php endif ?>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
