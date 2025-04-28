<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>IP Range Bans</title>
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/iprangebans.js?23"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">IP Range Bans</h1>
</header>
<div id="menu">
<ul class="left"><?php if (isset($this->search_query)): ?>
  <a href="?" class="button button-light">Return</a><?php endif ?>
  <li><a class="button button-light" href="?action=update">Add</a></li>
  <li><a class="button button-light" href="?action=tools">Tools</a></li>
  <li><a data-tip="Show auto-rangebans" class="button button-light" href="?action=auto">Auto</a></li>
  <li><span class="form-label">IP</span><input id="filter-ip" type="text" value="<?php if ($this->search_mode == 'ip') echo $this->search_query ?>"> <span data-cmd="match" class="button button-light">Match</span></li>
</ul>
<ul class="right">
  <li>
    <label><input class="js-search-opt" name="active_only" type="checkbox"<?php if (isset($this->search_active)) echo ' checked'; ?>> Active only</label>
    <label><input class="js-search-opt" name="ops_only" type="checkbox"<?php if (isset($this->search_ops)) echo ' checked'; ?>> OPs only</label>
    <label><input class="js-search-opt" name="img_only" type="checkbox"<?php if (isset($this->search_img)) echo ' checked'; ?>> Images only</label>
    <label><input class="js-search-opt" name="report_only" type="checkbox"<?php if (isset($this->search_report)) echo ' checked'; ?>> Report only</label>
    <label><input class="js-search-opt" name="lenient" type="checkbox"<?php if (isset($this->search_lenient)) echo ' checked'; ?>> Lenient</label>
    <label><input class="js-search-opt" name="temp" type="checkbox"<?php if (isset($this->search_temp)) echo ' checked'; ?>> Temporary</label>
    <label data-tip="Order by modification date"><input class="js-search-opt" name="recent" type="checkbox"<?php if (isset($this->search_recent)) echo ' checked'; ?>> Updated</label>
    <span class="form-label">Board</span><input class="js-search-opt" id="filter-board" name="board" type="text" value="<?php if (isset($this->board_query)) echo $this->board_query ?>">
    <span class="form-label">Description</span><input id="filter-desc" type="text" value="<?php if ($this->search_mode == 'desc') echo $this->search_query ?>">
    <span data-cmd="search" class="button button-light">Search</span><?php if ($this->search_mode == 'desc'): ?> <span data-cmd="reset-filter" class="button button-light">Reset</span><? endif ?></li>
</ul>
</div>
<div id="content">
<table class="items-table" id="items">
<thead>
  <tr>
    <th class="col-id">ID</th>
    <th class="col-active">Active</th>
    <th class="col-scope">Scope</th>
    <th class="col-ip">CIDR</th>
    <th>Description</th>
    <th class="col-date">Created on</th>
    <th class="col-sel"><span data-cmd="toggle-all" data-tip="Toggle All" class="wot">Toggle</span></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->ranges as $range): ?>
  <tr id="range-<?php echo $range['id'] ?>">
    <td><?php echo $range['id'] ?></td>
    <td class="col-act"><?php if ($range['active']): ?>&check;<?php endif ?></td>
    <td><?php echo $range['boards'] ?><ul class="note"><?php if ($range['ops_only']): ?><li class="val-blue">OPs</li><?php endif ?><?php if ($range['img_only']): ?><li class="val-blue">Images</li><?php endif ?><?php if ($range['lenient']): ?><li class="val-green">Lenient</li><?php endif ?><?php if ($range['ua_ids']): ?><li class="val-green">UA</li><?php endif ?><?php if ($range['report_only']): ?><li class="val-red">Reports</li><?php endif ?></ul></td>
    <td><?php echo $range['cidr'] ?></td>
    <td class="col-desc"><div class="js-desc"><?php echo $range['description'] ?></div><a href="#" data-wheel-ok data-cmd="search-more" data-tip="Search this description" class="more-desc-l">more</a></td>
    <td><?php if ($range['expires_on']): ?><span data-tip="Expires on <?php echo date(self::DATE_FORMAT, $range['expires_on']) ?>" class="re-type">T</span><?php endif ?><span data-tip="<?php echo $range['created_by'] ?>"><?php echo date(self::DATE_FORMAT, $range['created_on']) ?></span><div class="note" data-tip="Updated by <?php echo $range['updated_by'] ?>"><?php if ($range['updated_on']) echo date(self::DATE_FORMAT, $range['updated_on']) ?></div></td>
    <td><input data-id="<?php echo $range['id'] ?>" data-cmd="toggle" type="checkbox" class="range-select"></td>
  </tr>
  <?php endforeach ?>
</tbody>
<?php if (isset($this->offset)): ?>
<tfoot>
  <tr>
    <td colspan="7" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
<?php endif ?>
</table>
</div>
<footer></footer>
</body>
</html>
