<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Donations</title>
  <link rel="stylesheet" type="text/css" href="/css/pass-refund.css?7">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Donations</h1>
</header>
<div id="content">
<?php if (isset($this->donations)): ?>
<table class="items-table">
<thead>
  <tr>
    <th>Reference</th>
    <th>Customer ID</th>
    <th>Transaction ID</th>
    <th>Name</th>
    <th>E-mail</th>
    <th>Message</th>
    <th>IP</th>
    <th>Created on</th>
    <th>Amount (USD)</th>
  </tr>
</thead>
<tbody id="items" class="donation-table">
  <?php foreach ($this->donations as $item): ?>
  <tr>
    <td><?php echo $item['ref_id'] ?></td>
    <td><?php echo $item['customer_id'] ?></td>
    <td><?php echo $item['transaction_id'] ?></td>
    <td><?php echo $item['name'] ?></td>
    <td><?php echo $item['email'] ?></td>
    <td class="cell-msg"><?php echo $item['message'] ?></td>
    <td><?php echo $item['ip'] ?></td>
    <td><?php echo date(self::DATE_FORMAT, $item['created_on']) ?></td>
    <td><?php echo round($item['amount_cents'] / 100, 2) ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php else: ?>
<form id="view-search-form" action="" method="get" enctype="multipart/form-data">
  <input type="text" name="q" placeholder="Reference, Transaction, Customer, E-mail or IP" required>
  <button class="button btn-other" name="action" value="search" type="submit">Search</button>
</form>
<?php if (isset($this->items)): ?>
<h4 class="table-title">Most recent donations</h4>
<table class="items-table">
<thead>
  <tr>
    <th>Name</th>
    <th>Message</th>
    <th>Created on</th>
    <th>Amount (USD)</th>
  </tr>
</thead>
<tbody class="donation-table" id="items">
  <?php foreach ($this->items as $item): ?>
  <tr>
    <td><?php echo $item['name'] ?></td>
    <td class="cell-msg"><?php echo $item['message'] ?></td>
    <td><?php echo date(self::DATE_FORMAT, $item['created_on']) ?></td>
    <td><?php echo round($item['amount_cents'] / 100, 2) ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
<tfoot>
  <tr>
    <td colspan="4" class="page-nav"><?php if ($this->previous_offset !== $this->offset): ?><a href="?offset=<?php echo $this->previous_offset ?>">&laquo; Previous</a><?php endif ?><?php if ($this->next_offset): ?><a href="?offset=<?php echo $this->next_offset ?>">Next &raquo;</a><?php endif ?></td>
  </tr>
</tfoot>
</table>
<?php endif ?>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
