<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>IP Lookup</title>
  <link rel="stylesheet" type="text/css" href="/css/iplookup.css?5">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">IP Lookup</h1>
</header>
<div id="content">
<?php if (isset($this->type)): ?>
<div class="search-result">
<?php if (!$this->results): ?>
Nothing found.
<?php elseif ($this->type == 'ip'): ?>
<h4>Post history for <?php echo $this->value ?></h4>
<?php if ($this->req_sig): ?><h5>Req. Sig.: <i><?php echo $this->req_sig ?></i></h5><?php endif ?>
<table class="items-table table-compact">
<thead>
  <tr>
    <th>Board</th>
    <th>Post ID</th>
    <th>Date</th>
    <th></th>
  </tr>
  <?php foreach ($this->results as $row): $link = return_archive_link($row['board'], $row['no'], false, true); ?>
  <tr>
    <td><?php echo $row['board'] ?></td>
    <td><?php echo $row['no'] ?><?php if ($row['action'] == 'new_thread'): ?> <span class="st">(OP)</span><?php endif ?></td>
    <td><?php echo date(self::DATE_FORMAT, $row['time']) ?></td>
    <td><?php if ($link): ?><a href="https://www.4chan.org/derefer?url=<?php echo rawurlencode($link) ?>" target="_blank">Archive</a><?php endif ?></td>
  </tr>
  <?php endforeach ?>
</table>
<?php if (!empty($this->req_sigs)): ?>
<table class="items-table table-compact xs-tbl">
<thead>
  <tr>
    <th>Board</th>
    <th>Req Sig</th>
    <th>Date</th>
  </tr>
  <?php foreach ($this->req_sigs as $row): ?>
  <tr>
    <td><?php echo $row['board'] ?></td>
    <td><?php echo $row['req_sig'] ?></td>
    <td><?php echo date(self::DATE_FORMAT, $row['ts']) ?></td>
  </tr>
  <?php endforeach ?>
</table>
<?php endif ?>
<?php else: ?>
/<?php echo $this->board ?>/<?php echo $this->results['no'] ?> was posted by <a title="Multisearch" href="//team.4chan.org/search#{&quot;ip&quot;:&quot;<?php echo $this->results['host'] ?>&quot;}"><?php echo $this->results['host'] ?></a> on <?php echo date(self::DATE_FORMAT, $this->results['time']) ?>
<?php endif ?>
</div>
<?php endif ?>
<form id="search-form" action="" method="get" enctype="multipart/form-data">
  <div class="field-row"><span class="form-label">Board</span> <input type="text" name="board"></div>
  <div class="field-row"><span class="form-label">Post Number</span> <input type="text" name="no"></div>
  <div class="field-row"><span title="Internal numeric filename" class="form-label">Filename</span> <input type="text" name="tim"></div>
  <button class="button btn-other" name="action" value="search" type="submit">Search</button>
  <hr>
  <div class="field-row"><span class="form-label">IP</span> <input type="text" name="ip"></div>
  <button class="button btn-other" name="action" value="by_ip" type="submit">Show History</button>
</form>
</div>
<footer></footer>
</body>
</html>
