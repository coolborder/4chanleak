<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Auto Range Bans</title>
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/iprangebans.js?23"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Auto Range Bans</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<table class="items-table" id="items">
<thead>
  <tr>
    <th class="col-active">Active</th>
    <th class="col-scope">Board</th>
    <th class="col-scope">Thread ID</th>
    <th class="col-ip">IP</th>
    <th class="col-ip">CIDR</th>
    <th class="col-ip">Browser ID</th>
    <th>Reason</th>
    <th>Source</th>
    <th class="col-date">Created on</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->ranges as $range): ?>
  <tr id="range-<?php echo $range['id'] ?>">
    <td class="col-act"><?php if ($range['created_on'] + self::AUTO_TTL_SEC > $this->now): ?>&check;<?php endif ?></td>
    <td><?php echo $range['board'] ?></td>
    <td><?php if ($range['thread_id']): ?>
      <a target="_blank" href="https://boards.<?php echo L::d($range['board']) ?>/<?php echo $range['board'] ?>/thread/<?php echo $range['thread_id'] ?>"><?php echo $range['thread_id'] ?></a>
    <?php else: $link = return_archive_link($range['board'], $range['post_id'], false, true); ?>
      <?php if ($link !== false): ?> <a target="_blank" href="https://www.4chan.org/derefer?url=<?php echo rawurlencode($link) ?>">OP</a>
      <?php else: ?>OP
      <?php endif ?>
    <?php endif ?></td>
    <td><?php echo $range['ip'] ?></td>
    <td><?php echo $this->ip_to_range16($range['ip']) ?></td>
    <td><?php echo $range['ua_sig'] ?></td>
    <td class="col-desc"><div class="js-desc"><?php echo $range['tpl_name'] ?></div></td>
    <td><?php echo $range['arg_str'] === '1' ? 'Ban' : 'Ban Request' ?></td>
    <td><?php echo date(self::DATE_FORMAT, $range['created_on']) ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
<?php if (isset($this->offset)): ?>
<tfoot>
  <tr>
    <td colspan="9" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?action=auto&amp;offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?action=auto&amp;offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
<?php endif ?>
</table>
</div>
<footer></footer>
</body>
</html>
