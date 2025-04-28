<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Contest Banners</title>
  <link rel="stylesheet" type="text/css" href="/css/contest_banners.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/contest_banners.js?3"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Contest Banners</h1>
</header>
<div id="menu">
  <div id="js-board-menu" class="left select-box"><select data-tip="Board" name="board" id="filter-board">
  <option value="">All</option>
  <?php foreach ($this->boards as $board => $_): ?>
  <option<?php if ($this->filter_board == $board): ?> selected="selected"<?php endif ?>><?php echo $board ?></option>
  <?php endforeach ?>
  </select></div>
  <ul>
    <li><a<?php if ($filter_pending = $this->filter == 'pending') echo ' class="act-filter"' ?> href="<?php echo self::WEBROOT ?>">Pending (<?php echo isset($this->counts[self::STATUS_PENDING]) ? $this->counts[self::STATUS_PENDING] : 0 ?>)</a></li>
    <li><a<?php if ($filter_active = $this->filter == 'active') echo ' class="act-filter"' ?> href="?filter=active">Active (<?php echo isset($this->counts[self::STATUS_ACTIVE]) ? $this->counts[self::STATUS_ACTIVE] : 0 ?>)</a></li>
    <li><a<?php if ($filter_disabled = $this->filter == 'disabled') echo ' class="act-filter"' ?> href="?filter=disabled">Disabled (<?php echo isset($this->counts[self::STATUS_DISABLED]) ? $this->counts[self::STATUS_DISABLED] : 0 ?>)</a></li>
    <li><a<?php if ($filter_live = $this->filter == 'live') echo ' class="act-filter"' ?> href="?filter=live">Live (<?php echo isset($this->counts[self::STATUS_LIVE]) ? $this->counts[self::STATUS_LIVE] : 0 ?>)</a></li>
    <li class="list-sep"></li>
    <li><a href="?action=events">Events</a></li>
  </ul>
</div>
<div id="content">
<table class="items-table" id="items">
<thead>
  <tr>
    <th class="col-id">ID</th>
    <th class="col-board">Board</th>
    <th class="col-name">Name</th>
    <th class="col-email">E-Mail</th>
    <th class="col-meta">IP</th>
    <th>Image</th>
    <th class="col-date">Created on</th><?php if ($filter_active): ?>
    <th class="col-id">Score</th>
    <th class="col-id">Score 2</th>
    <?php endif ?><?php if (!$filter_disabled): ?>
    <th class="col-meta"></th><?php endif ?>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $banner): ?>
  <tr id="item-<?php echo $banner['id'] ?>">
    <td class="col-id"><?php echo $banner['id'] ?></td>
    <td class="col-board"><?php echo $banner['board'] ?></td>
    <td class="col-name"><?php echo $banner['author'] ?></td>
    <td class="col-email"><?php echo $banner['email'] ?></td>
    <td class="col-meta"><?php echo $banner['ip'] ?></td>
    <td><?php if (!$filter_disabled): ?>
    <?php if ($banner['th_width'] > 0): ?>
    <a class="banner-url" href="<?php echo $this->get_image_url($banner, $filter_pending) ?>"><img class="banner-img" width="<?php echo $banner['th_width'] ?>" height="<?php echo $banner['th_height'] ?>" alt="" src="<?php echo $this->get_image_url($banner, $filter_pending, true) ?>"></a>
    <?php else: ?>
    <img class="banner-img" width="<?php echo $banner['width'] ?>" height="<?php echo $banner['height'] ?>" alt="" src="<?php echo $this->get_image_url($banner, $filter_pending) ?>">
    <?php endif ?>
    <?php else: ?>
    <?php echo $banner['file_id'] ?>.<?php echo $banner['file_ext'] ?>
    <?php endif ?></td>
    <td class="col-date"><?php echo date(self::DATE_FORMAT, $banner['created_on']) ?></td><?php if ($filter_active): ?>
    <td class="col-id"><?php echo $banner['score'] ?></td>
    <td class="col-id"><?php echo $banner['score2'] ?></td>
    <?php endif ?><?php if (!$filter_disabled): ?>
    <td class="col-meta"><?php if ($filter_pending): ?><span data-cmd="enable" class="button btn-accept">Enable</span><?php endif ?><?php if (!$filter_disabled && !$filter_live): ?><span data-cmd="pre-disable" class="button btn-other">Disable</span><?php endif ?><?php if ($filter_live): ?><span data-cmd="unset-live" class="button btn-other">Unset Live</span><?php endif ?><?php if ($filter_active && $banner['is_live'] == 0): ?><span data-cmd="preset-live" class="button btn-other">Set Live</span><?php endif ?></td><?php endif ?>
  </tr>
  <?php endforeach ?>
</tbody>
<?php if (isset($this->offset)): $col_count = 5; if (!$filter_disabled) $col_count++; if ($filter_active) $col_count++; ?>
<tfoot>
  <tr>
    <td colspan="<?php echo $col_count ?>" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php echo $this->search_qs ? "{$this->search_qs}&amp;" : '' ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ? "{$this->search_qs}&amp;" : '' ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
<?php endif ?>
</table>
</div>
<footer></footer>
</body>
</html>
