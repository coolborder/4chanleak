<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Search 4chan Passes</title>
  <link rel="stylesheet" type="text/css" href="/css/pass-refund.css?7">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/view4chanpass.js?3"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Search 4chan Passes</h1>
</header>
<div id="content">
<?php if (isset($this->passes)): ?>
<table class="items-table">
<thead>
  <tr>
    <th>Order ID</th>
    <th>Status</th>
    <th>Payment ID</th>
    <th>Payer ID</th>
    <th>Created on</th>
    <th>E-mail</th>
    <th>Gift E-mail</th>
    <th>IP</th>
    <th>Renewal</th>
  </tr>
</thead>
<tbody id="items">
  <?php $i = 0; foreach ($this->passes as $pass): ++$i; ?>
  <tr id="item-<?php echo $i ?>">
    <td><?php echo htmlspecialchars($pass['order_id']) ?></td>
    <td><?php echo htmlspecialchars($pass['status']) ?></td>
    <td><?php echo htmlspecialchars($pass['payment_id']) ?></td>
    <td><?php echo htmlspecialchars($pass['payer_id']) ?></td>
    <td><?php echo htmlspecialchars($pass['created_on']) ?></td>
    <td><?php echo htmlspecialchars($pass['email']) ?></td>
    <td><?php echo htmlspecialchars($pass['gift_email']) ?></td>
    <td><?php echo $pass['ip'] ?></a> (<?php echo $pass['country'] ?>)</td>
    <td><?php echo $pass['renewal_id'] ? 'Yes' : 'No' ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
