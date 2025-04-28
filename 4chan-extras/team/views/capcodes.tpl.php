<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Capcodes</title>
  <link rel="stylesheet" type="text/css" href="/css/capcodes.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
</head>
<body data-tips <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Capcodes</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="?action=update">Add</a></li>
</ul>
</div>
<div id="content">
<table class="items-table">
<thead>
  <tr>
    <th>Active</th>
    <th>Name</th>
    <th>E-Mail</th>
    <th>Description</th>
    <th>Created on</th>
    <th>Last used</th>
    <th></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $item): ?>
  <tr id="filter-<?php echo $item['id'] ?>">
    <td class="col-act"><?php if ($item['active']): ?>&check;<?php endif ?></td>
    <td class="col-name"><?php echo $item['name'] ?></td>
    <td class="col-name"><?php echo $item['email'] ?></td>
    <td class="col-desc"><?php echo $item['description'] ?></td>
    <td class="col-date"><span data-tip="<?php echo $item['created_by'] ?>"><?php echo date(self::DATE_FORMAT, $item['created_on']) ?></span><?php if ($item['updated_on']): ?><div data-tip="Updated by <?php echo $item['updated_by'] ?>" class="note"><?php echo date(self::DATE_FORMAT, $item['updated_on']) ?></div><?php endif ?></td>
    <td class="col-date"><?php if ($item['last_used']): echo date(self::DATE_FORMAT, $item['last_used']); ?><div class="note"><?php echo $item['last_ip'] ?></div><?php else: ?>Never<?php endif ?></td>
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
</div>
<footer></footer>
</body>
</html>
