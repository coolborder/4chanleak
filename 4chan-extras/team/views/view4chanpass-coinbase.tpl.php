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
  <script type="text/javascript" src="/js/view4chanpass.js?4"></script>
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
    <th>Charge ID</th>
    <th>Local Status</th>
    <th>Created on</th>
    <th>E-mail</th>
    <th>Gift E-mail</th>
    <th>IP</th>
    <th>Renewal</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php $i = 0; foreach ($this->passes as $pass): ++$i; ?>
  <tr id="item-<?php echo $i ?>">
    <td><?php echo htmlspecialchars($pass['charge_code']) ?></td>
    <td><?php echo htmlspecialchars($pass['status']) ?></td>
    <td><?php echo htmlspecialchars($pass['created_on']) ?></td>
    <td><?php echo htmlspecialchars($pass['email']) ?></td>
    <td><?php echo htmlspecialchars($pass['gift_email']) ?></td>
    <td><?php echo $pass['ip'] ?></a> (<?php echo $pass['country'] ?>)</td>
    <td><?php echo $pass['renewal_id'] ? 'Yes' : 'No' ?></td>
    <td><a href="?action=coinbase_view&amp;q=<?php echo htmlspecialchars($pass['charge_code']) ?>">View</a></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php elseif (isset($this->charge)): $charge = $this->charge; $our_charge = $this->our_charge ?>
<table class="data-table">
  <tr>
    <th>Charge ID</th>
    <td><?php echo htmlspecialchars($charge['code']) ?></td>
  </tr>
  <tr>
    <th>Local Status</th>
    <td><?php echo htmlspecialchars($our_charge['status']) ?></td>
  </tr>
  <tr>
    <th>Created on</th>
    <td><?php echo $this->format_time($charge['created_at']) ?></td>
  </tr>
  <tr>
    <th>Confirmed on</th>
    <td><?php echo htmlspecialchars($our_charge['confirmed_on']) ?></td>
  </tr>
  <tr>
    <th>E-mail</th>
    <td><?php echo htmlspecialchars($our_charge['email']) ?></td>
  </tr>
  <tr>
    <th>Gift E-mail</th>
    <td><?php echo htmlspecialchars($our_charge['gift_email']) ?></td>
  </tr>
  <tr>
    <th>IP</th>
    <td><?php echo $our_charge['ip'] ?> (<?php echo $our_charge['country'] ?>)</td>
  </tr>
  <tr>
    <th>Renewal</th>
    <td><?php echo $our_charge['renewal_id'] ? 'Yes' : 'No' ?></td>
  </tr>
  <tr>
    <th>To Pay</th>
    <td><?php echo htmlspecialchars($our_charge['usd_price']) ?> USD</td>
  </tr>
  <tr>
    <th>Payments</th>
    <td><?php if ($charge['payments']): ?>
    <?php $total_paid = 0.0; foreach ($charge['payments'] as $payment): ?>
      <ul class="cb-payment">
        <li><strong>Network:</strong> <?php echo htmlspecialchars($payment['network']) ?></li>
        <li><strong>Transaction ID:</strong> <?php echo htmlspecialchars($payment['transaction_id']) ?></li>
        <li><strong>Status:</strong> <?php echo htmlspecialchars($payment['status']) ?></li>
        <li><strong>Local price:</strong> <?php $price = $payment['value']['local']; if ($price['currency'] === 'USD') { $total_paid += (float)$price['amount']; } echo htmlspecialchars($price['amount']) ?> <?php echo htmlspecialchars($price['currency']) ?></li>
        <li><strong>Crypto price:</strong> <?php $price = $payment['value']['crypto']; echo htmlspecialchars($price['amount']) ?> <?php echo htmlspecialchars($price['currency']) ?></li>
        <li><strong>Confirmations:</strong> <?php echo htmlspecialchars($payment['block']['confirmations']) ?></li>
        <li><strong>Confirmations (required):</strong> <?php echo htmlspecialchars($payment['block']['confirmations_required']) ?></li>
      </ul>
    <?php endforeach ?>
      <ul class="cb-payment">
        <li><strong>Total paid:</strong> <?php echo sprintf("%.2f", $total_paid) ?> USD</li>
        <?php $days_delta = $this->get_days_from_payment($total_paid, $our_charge['usd_price']); ?>
        <?php if ($days_delta !== 0): ?>
          <?php if ($days_delta < 0): ?>
          <li class="lbl-deny">Underpayed: 
          <?php else: ?>
          <li class="lbl-accept">Overpayed: 
          <?php endif ?>
          <?php echo sprintf("%.2f", (float)$our_charge['usd_price'] - $total_paid) ?> USD = <?php echo abs($days_delta) ?> month(s)</li>
        <?php endif ?>
      </ul>
    <?php endif ?></td>
  </tr>
  <tr>
    <th>Timeline</th>
    <td><?php if ($charge['timeline']): ?>
    <?php foreach ($charge['timeline'] as $event): ?>
      <ul class="cb-event">
        <li><strong>Time:</strong> <?php echo $this->format_time($event['time']) ?></li>
        <li><strong>Status:</strong> <?php echo htmlspecialchars($event['status']) ?></li>
        <?php if ($event['status'] === self::CHARGE_STATUS_UNRESOLVED): ?><li><strong>Status:</strong> <?php echo htmlspecialchars($event['context']) ?></li><?php endif ?>
      </ul>
    <?php endforeach ?>
    <?php endif ?>
    </td>
  </tr>
  <?php if ($our_charge['status'] !== self::CHARGE_STATUS_CONFIRMED): ?>
  <tr class="cell-footer">
    <th></th>
    <td>
      <?php if (isset($days_delta) && $days_delta !== 0): ?><label class="adjust-lbl"><input type="checkbox" id="js-adjust-months" value="<?php echo $days_delta ?>"> <b><?php echo $days_delta > 0 ? 'Increase' : 'Reduce' ?></b> Pass duration by <?php echo abs($days_delta) ?> month(s)</label><?php endif ?> <button id="js-confirm-btn" data-tip="Confirm the charge and create the corresponding Pass" class="button btn-accept" data-cmd="force-confirm" data-charge="<?php echo htmlspecialchars($charge['code']) ?>">Confirm Charge</button>
    </td>
  </tr>
  <?php else: ?>
  <tr class="cell-footer"><th></th><td></td></tr>
  <?php endif ?>
</table>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
