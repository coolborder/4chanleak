<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Post Filter</title>
  <link rel="stylesheet" type="text/css" href="/css/postfilter.css?9">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/postfilter.js?5"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Post Filter</h1>
</header>
<div id="menu">
<ul class="left">
  <?php if ($this->search_mode): ?>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
  <?php else: ?>
  <li><a class="button button-light" href="?action=update">Add</a></li>
  <?php endif ?>
</ul>
<ul class="right">
  <?php if ($this->active_count !== null): ?>
  <li class="txt-xs"><?php echo $this->active_count ?> active filters</li>
  <?php endif ?>
  <li><a class="button button-light" href="?action=search">Search</a></li>
</ul>
</div>
<div id="content">
<?php if ($this->search_mode && empty($this->items)): ?>
<div id="no-results">Nothing found</div>
<?php else: ?>
<?php if ($this->search_mode): ?>
<h3>Search Results (<?php echo $this->results_count ?>)</h3>
<?php endif ?>
<?php if (isset($this->prune_mode)): ?>
<div>
  <p><?php echo count($this->items) ?> entries are stale.</p>
</div>
<?php endif ?>
<table class="items-table">
<thead>
  <tr>
    <th><input type="checkbox" id="toggle-all" data-cmd="toggle-all" data-tip="Toggle All"></th>
    <th>ID</th>
    <th>Active</th>
    <th>Board</th>
    <th>Pattern</th>
    <th>Action</th>
    <th>Description</th>
    <th>Created on</th>
    <?php if ($this->search_mode): ?>
    <th><span class="wot" data-tip="Number of hits in the past <?php echo self::HIT_STATS_DAYS ?> days">Hits</span></th><?php else: ?>
    <th><a id="hit-mode-toggle" href="?<?php echo $this->get_next_hits_param() ?>"><span class="wot" data-tip="Click to sort by number of hits in the past <?php echo self::HIT_STATS_DAYS ?> days">Hits</span></a> <?php if ($this->hits_mode == 'hitsd'): ?>▼<?php elseif ($this->hits_mode == 'hitsa'): ?>▲<?php else: ?>•<?php endif ?></th>
    <?php endif ?>
    <th></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $item): ?>
  <tr id="filter-<?php echo $item['id'] ?>">
    <td class="col-edit"><input data-id="<?php echo $item['id'] ?>" data-cmd="toggle" type="checkbox" class="filter-select"></td>
    <td class="col-id"><?php echo $item['id'] ?></td>
    <td class="col-act"><?php if ($item['active']): ?>&check;<?php endif ?></td>
    <td class="col-board"><?php echo $item['board'] ?><?php if ($item['regex']): ?><span data-tip="Regular Expression" class="re-type">R</span><?php endif ?></td>
    <td class="col-pattern"><?php echo htmlspecialchars($item['pattern']) ?></td>
    <td class="col-action"><?php if ($item['autosage']): ?>Autosage<?php elseif ($item['ban_days']): ?>Ban <?php echo($item['ban_days'] . ' day' . $this->pluralize($item['ban_days'])); ?><?php else: ?>Reject<?php endif ?></td>
    <td class="col-desc"><?php echo $item['description'] ?></td>
    <td class="col-date"><span data-tip="<?php echo $item['created_by'] ?>"><?php echo date(self::DATE_FORMAT, $item['created_on']) ?></span><?php if ($item['updated_on']): ?><div data-tip="Updated by <?php echo $item['updated_by'] ?>" class="note"><?php echo date(self::DATE_FORMAT, $item['updated_on']) ?></div><?php endif ?></td>
    <td class="col-hits"><?php echo isset($item['hits']) ? $item['hits'] : 0 ?></td>
    <td class="col-meta"><a href="?action=update&amp;id=<?php echo $item['id'] ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
<tfoot>
  <tr>
    <td colspan="9" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
