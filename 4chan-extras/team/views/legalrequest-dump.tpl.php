<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Legal Requests</title>
  <link rel="stylesheet" type="text/css" href="../legalrequest.css">
</head>
<body>
<div id="content">
<?php if (isset($this->raw_report)): $r = $this->raw_report; ?>
<h3 title="Dated: <?php if ($r['request_date'] !== '0000-00-00') echo $r['request_date']; else echo $r['date'] ?>">Raw Report #<?php echo $r['id'] ?></h3>
<div class="pre-block"><?php echo $r['raw_info'] ?></div>
<?php else: $r = $this->report; ?>
<h3>Report #<?php echo $r['id'] ?></h3>
<div class="report-status"><?php if ($r['was_sent']): ?>
This report was <?php if ($r['sent_on']): ?>sent on <?php echo date(self::DATE_FORMAT_SHORT, $r['sent_on']); ?><?php else: ?>already sent<?php endif ?>. <a href="?action=email&amp;id=<?php echo $r['id'] ?>">Resend Now</a>
<?php else: ?>
This report hasn't been sent yet.
<?php endif ?></div>
<div class="pre-block"><?php echo $this->format_report_meta($r, true); echo htmlspecialchars($r['report']) ?></div>
<?php endif ?>
<?php if (!isset($this->raw_report)): ?>
<?php if ($r['email_content']): ?>
<h3>Copy of Request E-mail</h3>
<div class="pre-block"><?php echo htmlspecialchars($r['email_content']) ?></div>
<div class="req-meta">
<div><span>Request Type:</span><span><?php echo $r['request_type'] ?></span></div>
<div><span>From:</span><span><?php echo htmlspecialchars("{$r['requester']} <{$r['requester_email']}>") ?></span></div>
<?php if ($r['cc_emails']): ?><div><span>Cc: E-mail(s):</span><span><?php echo htmlspecialchars($r['cc_emails']) ?></span></div><?php endif ?>
</div>
<?php endif ?>
<?php if (!empty($this->attachments)): ?>
<h3>Attachments</h3>
<ul>
  <?php foreach ($this->attachments as $file): ?>
  <li><?php echo $file['filename'] !== '' ? htmlspecialchars($file['filename']) : "untitled_{$file['id']}" ?></li>
  <?php endforeach ?>
</ul>
<?php endif ?>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
