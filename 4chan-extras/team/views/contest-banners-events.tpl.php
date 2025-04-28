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
  <script type="text/javascript" src="/js/contest_banners.js"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Contest Banners</h1>
</header>
<div id="menu">
  <ul>
    <li><a href="<?php echo self::WEBROOT ?>">Pending (<?php echo isset($this->counts[self::STATUS_PENDING]) ? $this->counts[self::STATUS_PENDING] : 0 ?>)</a></li>
    <li><a href="?filter=active">Active (<?php echo isset($this->counts[self::STATUS_ACTIVE]) ? $this->counts[self::STATUS_ACTIVE] : 0 ?>)</a></li>
    <li><a href="?filter=disabled">Deleted (<?php echo isset($this->counts[self::STATUS_DISABLED]) ? $this->counts[self::STATUS_DISABLED] : 0 ?>)</a></li>
    <li><a href="?filter=live">Live (<?php echo isset($this->counts[self::STATUS_LIVE]) ? $this->counts[self::STATUS_LIVE] : 0 ?>)</a></li>
    <li class="list-sep"></li>
    <li><a data-cmd="add-event" href="">Add New Event</a></li>
  </ul>
</div>
<div id="content">
<table class="items-table table-compact" id="items">
<thead>
  <tr>
    <th class="col-boards">Boards</th>
    <th class="col-type">Type</th>
    <th class="col-date">Starts on</th>
    <th class="col-date">Ends on</th>
    <th class="col-meta"></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $event): ?>
  <tr id="item-<?php echo $event['id'] ?>">
    <td class="col-boards"><?php echo $event['boards'] ?></td>
    <td class="col-type"><?php echo $this->_event_types[$event['event_type']] ?></td>
    <td class="col-date"><?php echo $event['starts_on'] ? date(self::DATE_FORMAT_SHORT, $event['starts_on']) : '' ?></td>
    <td class="col-date"><?php echo $event['ends_on'] ? date(self::DATE_FORMAT_SHORT, $event['ends_on']) : '' ?></td>
    <td class="col-meta"><span data-cmd="edit-event" class="button btn-other">Edit</span><span data-cmd="pre-del-event" class="button btn-other">Delete</span></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
</div>
<footer></footer>
</body>
</html>
