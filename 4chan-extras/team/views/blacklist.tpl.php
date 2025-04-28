<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Blacklist</title>
  <link rel="stylesheet" type="text/css" href="/css/blacklist.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/blacklist.js"></script>
</head>
<body>
<header>
  <h1 id="title">Blacklist</h1>
</header>
<div id="menu">
<ul class="left">
  <?php if (isset($this->search_mode)): ?>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
  <?php else: ?>
  <li><a class="button button-light" href="?action=update">Add</a></li>
  <li><a class="button button-light" href="?action=purge">Remove MD5s</a></li>
  <?php endif ?>
</ul>
<ul class="right">
  <li><form action="" method="get">
    <input name="q" required placeholder="Description or Values" value="<?php if (isset($this->search_query)) echo $this->search_query ?>">
    <button class="button button-light" name="action" value="search" type="submit">Search</button>
  </form></li>
</ul>
</div>
<div id="content">
<?php if (isset($this->search_mode) && empty($this->items)): ?>
<div id="no-results">Nothing found</div>
<?php else: ?>
<table class="items-table">
<thead>
  <tr>
    <th>Active</th>
    <th>Description</th>
    <th>Field</th>
    <th>Value</th>
    <th>Action</th>
    <th>Created on</th>
    <th></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $item): ?>
  <tr>
    <td class="col-act"><?php if ($item['active']): ?>&check;<?php endif ?></td>
    <td class="col-desc"><?php echo $item['description'] ?></td>
    <td><?php echo $item['field'] ?></td>
    <td><?php echo htmlspecialchars($item['contents']) ?></td>
    <td><span <?php if ($item['ban'] === '1'): ?>data-tip data-tip-cb="APP.showReasonTip" <?php endif ?>class="bl-action"><?php echo $this->pretty_action($item) ?></span><?php if ($item['ban'] === '1'): ?><div class="hidden"><?php echo $item['banreason'] ?></div><?php endif ?></td>
    <td class="col-date"><span data-tip="<?php echo $item['created_by'] ?>"><?php echo date(self::DATE_FORMAT, $item['created_on']) ?></span></td>
   <td class="col-edit"><a href="?action=update&amp;id=<?php echo $item['id'] ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
<tfoot>
  <tr>
    <td colspan="7" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
