<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Bans</title>
  <link rel="stylesheet" type="text/css" href="/css/bans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/bans.js?12"></script>
</head>
<body data-page="index" <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Bans</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>"><?php echo isset($this->search_mode) ? 'Return' : 'Refresh' ?></a></li>
</ul>
<ul class="right">
  <li><a class="button button-light" href="?action=ban_ips">Ban IP</a></li>
  <li><a class="button button-light" href="?action=search">Search</a></li>
</ul>
<form id="front-form-qs" action="<?php echo self::WEBROOT ?>" method="GET" style=""><input id="front-field-act" type="hidden" name="action" value="search"><input id="front-field-qs" placeholder="Quicksearch: IP, Ban ID, MD5 or Reason" name="ip" type="text"></form>
</div>
<div id="content">
<?php if (isset($this->search_mode) && empty($this->items)): ?>
<div id="no-results">Nothing found</div>
<?php else: ?>
<?php if (isset($this->search_mode)): ?>
<h3>Search Results</h3>
<?php endif ?>
<table class="items-table">
<thead>
  <tr>
    <th><input type="checkbox" id="toggle-all" data-cmd="toggle-all" data-tip="Toggle All"></th>
    <th>Active</th>
    <th>Board</th>
    <th>Name</th>
    <th>IP</th>
    <th>Reason</th>
    <th>Banned on</th>
    <th>Length</th>
    <th>Banned by</th>
    <th></th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $item): $post = null; $is_op = $item['post_json'] && !$item['post_json']['resto']; ?>
  <tr id="ban-<?php echo $item['no'] ?>">
    <td class="col-edit"><input type="checkbox"<?php if (!$item['active']): echo ' disabled'; else: ?> data-id="<?php echo $item['no'] ?>" data-cmd="toggle" class="range-select"<?php endif ?>></td>
    <td class="col-act"><?php if ($item['active']): ?><span<?php if (!$item['permanent'] && !$item['warn'] && $item['expires_on'] <= $this->now): ?> class="act-expired"<?php else: ?> class="act-active"<?php endif ?>>&check;</span><?php endif ?></td>
    <td class="col-board"><?php if ($item['board'] !== ''): ?><?php if ($item['post_json']): $post = $item['post_json']; ?><span class="ban-board pp-link"<?php
      if (isset($post['rel_sub']) && $post['rel_sub'] !== '') {
        echo ' data-rel-sub="' . htmlspecialchars($this->strip_html($this->format_subject($post['rel_sub'])[0]), ENT_QUOTES) . '"';
      }
      else if (isset($post['sub']) && $post['sub'] !== '') {
        echo ' data-sub="' . htmlspecialchars($this->strip_html($this->format_subject($post['sub'])[0]), ENT_QUOTES) . '"';
      }
      if ($item['active'] && isset($post['ext']) && $post['ext'] !== '') echo ' data-thumb="' . $this->get_ban_thumbnail($item['board'], $item['post_num']) . '" data-ths="' . $post['tn_w'] . 'x' . $post['tn_h'] . '"' ?><?php if (isset($post['com']) && $post['com'] !== '') echo ' data-com="' . htmlspecialchars($this->strip_html($post['com']), ENT_QUOTES) . '"' ?>>
    <?php else: ?><span class="ban-board">
    <?php endif ?>/<?php echo $item['board'] ?>/</span><?php endif ?><?php if ($item['warn']): ?>
    <div class="ban-notes">Warn</div>
    <?php elseif ($item['global']): ?>
    <div class="ban-notes">Global</div>
    <?php endif ?><?php if ($is_op): ?><div class="ban-notes is-op">OP</div><?php endif ?></td>
    <td class="col-name"><span class="ban-name"><?php echo $item['name'] ?></span><?php if ($item['tripcode']): ?> <span class="ban-tripcode"><?php echo $item['tripcode'] ?></span><?php endif ?></td>
    <td class="col-ip"><span class="ban-host"><?php echo $item['host'] ?></span><?php if ($item['reverse'] !== $item['host']): $rev = $this->format_reverse($item['reverse']); ?><div<?php if ($rev[1]): ?> data-tip="<?php echo $item['reverse'] ?>"<?php endif ?> class="ban-reverse"><?php echo $rev[0] ?></div><?php endif ?><?php if (isset($item['location'])): ?><div class="ban-geoloc sxt"><?php echo $item['location'] ?></div><?php endif ?><?php if ($item['4pass_id']): ?><div data-tip="<?php echo $this->hash_pass_id($item['4pass_id']) ?>" class="ban-notes is-pass">Pass</div><?php endif ?></td>
    <td class="col-reason"><div class="ban-pubreason"><?php echo $item['public_reason'] ?></div><?php if ($item['private_reason']): ?><div class="ban-privreason"><?php echo $item['private_reason'] ?></div><?php endif ?></td>
    <td class="col-date"><?php echo date(self::DATE_FORMAT, $item['created_on']) ?></td>
    <td class="col-length"><span<?php if (!$item['permanent'] && !$item['warn']): ?> data-tip="Expires on <?php echo date(self::DATE_FORMAT, $item['expires_on']) ?>"<?php endif ?>><?php if ($item['permanent']) { echo 'Permanent'; } else if ($item['warn']) { echo 'Warning'; } else { echo $this->pretty_duration($item['length']); } ?></span></td>
    <td class="col-by"><?php echo $item['created_by'] ?></td>
    <td class="col-edit"><a href="?action=update&amp;id=<?php echo $item['no'] ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
<tfoot>
  <tr>
    <td colspan="10" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?<?php echo $this->search_qs ?>offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
