<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Legal Requests</title>
  <link rel="stylesheet" type="text/css" href="/css/legalrequest.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Legal Requests</h1>
</header>
<div id="menu">
<ul class="right">
  <form action="" method="GET"><input name="q" value="<?php echo $this->search_query ?>"> <button type="submit" class="button button-light">Search</button></form>
</ul>
<ul>
  <?php if ($this->search_query): ?>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
  <?php else: ?>
  <li><a class="button button-light" href="?action=search">Create Report</a></li>
  <?php endif ?>
</ul>
</div>
<div id="content">
<table class="items-table" id="items">
<thead>
  <tr>
    <th>ID</th>
    <th>Sent</th>
    <th>Type</th>
    <th>Description</th>
    <th>Requested by</th>
    <th>Created on</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($this->items as $item): ?>
  <tr>
    <td class="col-id"><a href="?action=view&amp;id=<?php echo $item['id'] ?>"><?php echo $item['id'] ?></a></td>
    <td class="col-act"><?php if ($item['was_sent']): ?>&check;<?php endif ?></td>
    <td class="col-type"><?php echo $item['request_type'] ?></td>
    <td class="col-desc"><?php echo htmlspecialchars($item['description']) ?></td>
    <td class="col-by"><?php echo htmlspecialchars($item['requester'] . " <{$item['requester_email']}>") ?><?php if ($item['requester_doc_id'] !== ''): ?><div class="note"><?php echo $item['requester_doc_id'] ?></div><?php endif ?></td>
    <td class="col-date"><?php echo $item['date'] ?></td>
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
