<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Search 4chan Passes</title>
  <link rel="stylesheet" type="text/css" href="/css/pass-refund.css?8">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/view4chanpass.js?6"></script>
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Search 4chan Passes</h1>
</header>
<div id="content">
<?php if (isset($this->passes)): ?>
<?php if ($this->too_many_results): ?>
<div class="warn-cnt">Too many results. Showing the first <?php echo self::RESULT_LIMIT ?>.</div>
<?php endif ?>
<table class="items-table">
<thead class="hdr-compact">
  <tr>
    <th>Status</th>
    <th>Token</th>
    <th>Search</th>
    <th>Customer ID</th>
    <th>Transaction ID</th>
    <th>E-mail</th>
    <th>Gift E-mail</th>
    <th>Purchased on</th>
    <th>Expires on</th>
    <th>Last Used</th>
    <th><div>Regist. IP</div><div>Last IP</div></th>
    <th><div>Payment</div><div>Country</div></th>
    <th>Renewal</th>
    <th>Revoke</th>
    <th><div>Update</div><div>E-Mail</div></th>
    <th><div>Renewal</div><div>Notice</div></th>
  </tr>
</thead>
<tbody id="items">
  <?php $i = 0; foreach ($this->passes as $pass): ++$i; ?>
  <tr id="item-<?php echo $i ?>">
    <td><?php echo $this->status_str[$pass['status']] ?></td>
    <td class="js-token"><?php echo $pass['user_hash'] ?></td>
    <td class="cell-nowrap"><a class="token-lnk" title="Multisearch" href="//team.4chan.org/search#{&quot;pass_id&quot;:&quot;<?php echo $pass['user_hash'] ?>&quot;}">posts</a> <a class="token-lnk" href="//team.4chan.org/bans?action=search&pass_id=<?php echo $pass['user_hash'] ?>">bans</a></td>
    <td><?php echo $pass['customer_id'] ?></td>
    <td class="js-tid"><?php echo $pass['transaction_id'] ?></td>
    <td class="js-email"><?php echo htmlspecialchars($pass['email']) ?></td>
    <td><?php echo htmlspecialchars($pass['gift_email']) ?></td>
    <td><?php echo $pass['purchase_date'] ?></td>
    <td><?php echo $pass['expiration_date'] ?></td>
    <td><?php echo $pass['last_used'] ?></td>
    <td class="cell-nowrap"><div><span><?php echo $pass['registration_ip'] ?></span> (<?php echo $pass['registration_country'] ?>)</div><div><a title="Multisearch" href="//team.4chan.org/search#{&quot;ip&quot;:&quot;<?php echo $pass['last_ip'] ?>&quot;}"><?php echo $pass['last_ip'] ?></a> (<?php echo $pass['last_country'] ?>)</div></td>
    <td><?php echo $pass['payment_country'] ?></td>
    <td><?php echo $pass['is_renewal'] ? 'Yes' : 'No' ?></td>
    <td class="cell-nowrap"><?php if ($pass['status'] == 0): ?><button class="button btn-deny" data-cmd="revoke">Spam</button> <button class="button btn-deny" data-illegal data-cmd="revoke">Illegal</button><?php endif ?></td>
    <td><button class="button btn-other" data-cmd="update-email">Edit</button></td>
    <td><?php if ($pass['status'] == 1 && $pass['email_expired_sent'] == 1): ?><button class="button btn-other" data-cmd="send-notice">Send</button><?php endif ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php else: ?>
<form class="view-search-form" action="" method="get" enctype="multipart/form-data">
  <input type="text" name="q" placeholder="Token, Transaction ID, Customer ID, E-mail, or Last IP" required>
  <button class="button btn-other" name="action" value="search" type="submit">Search Passes</button>
</form>
<form class="view-search-form" action="" method="get" enctype="multipart/form-data">
  <input type="text" name="q" placeholder="Charge ID or E-Mail" required>
  <button class="button btn-other" name="action" value="coinbase_charges" type="submit">Search Coinbase</button>
</form>
<form class="view-search-form" action="" method="get" enctype="multipart/form-data">
  <input type="text" name="q" placeholder="Order ID, Payement ID, Payer ID or E-Mail" required>
  <button class="button btn-other" name="action" value="paypal_sales" type="submit">Search PayPal</button>
</form>
<div id="recent-purchases">
  <h4>Recent Purchases</h4>
  <table class="items-table">
    <thead class="hdr-compact">
      <tr>
        <th>Token</th>
        <th>E-mail</th>
        <th>Gift E-mail</th>
        <th>Purchased on</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->recent_passes as $pass): ?>
      <tr>
        <td><a href="?q=<?php echo htmlspecialchars($pass['user_hash']) ?>&action=search"><?php echo htmlspecialchars($pass['user_hash']) ?></a></td>
        <td><?php echo htmlspecialchars($pass['email']) ?></td>
        <td><?php echo htmlspecialchars($pass['gift_email']) ?></td>
        <td><?php echo $pass['purchase_date'] ?></td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
