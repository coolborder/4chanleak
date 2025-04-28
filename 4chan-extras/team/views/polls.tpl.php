<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Polls</title>
  <link rel="stylesheet" type="text/css" href="/css/polls.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/polls.js"></script>
</head>
<body>
<header>
  <h1 id="title">Polls</h1>
</header>
<div id="menu">
<ul>
  <li><a class="button button-light" href="?action=update">Create Poll</a></li>
</ul>
</div>
<div id="content">
<table class="items-table loading" id="items">
<thead>
  <tr>
    <th class="col-id">ID</th>
    <th class="col-active">Active</th>
    <th>Title</th>
    <th class="col-votes">Votes</th>
    <th class="col-date">Created on</th>
    <th class="col-date">Expires on</th>
    <th class="col-meta"></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $poll): ?>
  <tr id="range-<?php echo $poll['id'] ?>">
    <td><?php echo $poll['id'] ?></td>
    <td><?php if ($poll['status']): ?>&check;<?php endif ?></td>
    <td><?php echo $poll['title'] ?></td>
    <td><?php echo $poll['vote_count'] ?></td>
    <td><span data-tip="<?php if ($poll['updated_on']) { echo 'Updated by '; } echo $poll['updated_by'] ?>"><?php echo date(self::DATE_FORMAT, $poll['created_on']) ?></span></td>
    <td><?php if ($poll['expires_on']) echo date(self::DATE_FORMAT, $poll['expires_on']) ?></td>
    <td><a href="?action=view&amp;id=<?php echo $poll['id'] ?>">View</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
<?php if (isset($this->offset)): ?>
<tfoot>
  <tr>
    <td colspan="6" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
<?php endif ?>
</table>
</div>
<footer></footer>
</body>
</html>
