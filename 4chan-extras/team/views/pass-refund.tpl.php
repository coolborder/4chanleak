<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Refund 4chan Pass</title>
  <link rel="stylesheet" type="text/css" href="/css/pass-refund.css?4">
  <script type="text/javascript" src="/js/helpers.js"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Refund 4chan Pass</h1>
</header>
<div id="calc">
  <label><span class="form-label">Price paid (in cents)</span><input placeholder="2000" id="calc-pp" type="text"></label>
  <label><span class="form-label">Number of days to refund</span><input id="calc-days" type="text"></label>
  <label><span class="form-label">Refund amount:</span>$<span id="calc-out">0</span></label>
  <script type="text/javascript">
    function calcexe() {
      var pp, days, out;
      
      pp = $.id('calc-pp');
      days = $.id('calc-days');
      out = $.id('calc-out');
      
      if (!/^[0-9]+$/.test(pp.value) || !/^[0-9]+$/.test(days.value)) {
        out.textContent = '0';
        return;
      }
      
      pp = +pp.value;
      days = +days.value;
      
      out.textContent = (Math.round((pp / 365) * days) / 100).toFixed(2);
    }
    $.on($.id('calc-pp'), 'keyup', calcexe);
    $.on($.id('calc-days'), 'keyup', calcexe);
  </script>
</div>
<div id="content">
<?php if (isset($this->passes)): ?>
<div id="prorate">Prorated to <?php echo date('d F Y', $this->prorate_ts); ?></div>
<table class="items-table">
<thead>
  <tr>
    <th>Token</th>
    <th>Customer ID</th>
    <th>Transaction ID</th>
    <th>E-mail</th>
    <th>Purchased on</th>
    <th>Expires on</th>
    <th>Price paid</th>
    <th>Days to refund</th>
    <th>Price to refund</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->passes as $pass): ?>
  <tr>
    <td><?php echo $pass['user_hash'] ?></td>
    <td><?php echo $pass['customer_id'] ?></td>
    <td><?php echo $pass['transaction_id'] ?></td>
    <td><?php echo htmlspecialchars($pass['email']) ?></td>
    <td><?php echo $pass['purchase_date'] ?></td>
    <td><?php echo $pass['expiration_date'] ?></td>
    <td>$<?php echo (int)$pass['price_paid'] / 100.0 ?></td>
    <td><?php echo $pass['refund_days'] ?></td>
    <td>$<?php echo (int)$pass['refund_cents'] / 100.0 ?> <small>(<?php echo $pass['refund_cents'] ?> cents)</small></td>
    <td><?php if (isset($pass['not_refundable'])): ?>
      Not Refundable
    <?php else: ?><a class="refund-btn" href="javascript:;" data-href="?action=refund&amp;transaction_id=<?php echo $pass['transaction_id'] ?>&amp;prorate=<?php echo $this->prorate_ts ?>">Refund</a><?php endif ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<script type="text/javascript">
  var i, el, nodes;
  
  function onRefundClick(e) {
    var otp;
    
    e.preventDefault();
    
    otp = prompt('2FA OTP');
    
    if (otp === null) {
      return;
    }
    
    window.open(this.getAttribute('data-href') + '&otp=' + otp);
  }
  
  nodes = $.cls('refund-btn');
  
  for (i = 0; el = nodes[i]; ++i) {
    $.on(el, 'click', onRefundClick);
  }
</script>
<?php if (isset($this->total_cents)): ?>
  Total: <?php echo $this->total_count ?> passes for $<?php echo $this->total_cents / 100.0 ?> <small>(<?php echo $this->total_cents ?> cents)</small>
<?php endif ?>
<?php elseif (isset($this->refunds)): ?>
<div id="prorate">Total refunded $<?php echo $this->total_refunded / 100.0 ?></div>
<table class="items-table">
<thead>
  <tr>
    <th>IP</th>
    <th>Token</th>
    <th>Customer ID</th>
    <th>Transaction ID</th>
    <th>E-mail</th>
    <th>Purchased on</th>
    <th>Expires on</th>
    <th>Price paid</th>
    <th>Refunded</th>
    <th></th>
  </tr>
</thead>
<tbody id="items">
  <?php foreach ($this->refunds as $pass): ?>
  <tr>
    <td><?php echo $pass['ip'] ?></td>
    <td><?php echo $pass['user_hash'] ?></td>
    <td><?php echo $pass['customer_id'] ?></td>
    <td><?php echo $pass['transaction_id'] ?></td>
    <td><?php echo htmlspecialchars($pass['email']) ?></td>
    <td><?php echo $pass['purchase_date'] ?></td>
    <td><?php echo $pass['expiration_date'] ?></td>
    <td>$<?php echo (int)$pass['price_paid'] / 100.0 ?></td>
    <td>$<?php echo (int)$pass['amount'] / 100.0 ?> <small>(<?php echo $pass['amount'] ?> cents)</small></td>
    <td><?php if ($pass['status'] === '2') { ?><span style="color:green">&bull;</span><?php } ?></td>
  </tr>
  <?php endforeach ?>
</tbody>
</table>
<?php else: ?>
<form id="search-form" action="" method="get" enctype="multipart/form-data">
  <input type="hidden" name="action" value="search">
  <ul>
    <li><span class="form-label">Query</span> <input type="text" name="q" value="" required><br><span class="form-label"></span> <small>Token, Transaction ID, Customer ID, or E-mail</small></li>
    <li><span class="form-label">Prorated to</span> <input type="text" name="to_date" placeholder="MM/DD/YYYY"><br><span class="form-label"></span> <small>Defaults to current date</small</li>
  </ul>
  <button class="button btn-other" type="submit">Search</button>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
