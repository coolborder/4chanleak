<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Legal Requests</title>
  <link rel="stylesheet" type="text/css" href="/css/legalrequest.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/legalrequest.js"></script>
</head>
<body>
<header>
  <h1 id="title">Legal Requests</h1>
</header>
<div id="menu"><?php $r = $this->report; ?>
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>?action=view&amp;id=<?php echo $r['id'] ?>">Return</a></li>
</ul>
</div>
<div id="content">
<h3>Report #<?php echo $r['id'] ?></h3>
<form action="<?php echo self::WEBROOT ?>" method="POST" id="email-preview">
  <div><strong>From:</strong> <?php echo self::FROM_NAME ?> &lt;<?php echo self::FROM_ADDRESS ?>&gt;</div>
  <div><strong>To:</strong> <?php echo htmlspecialchars("{$r['requester']} <{$r['requester_email']}>") ?></div>
  <?php if ($r['cc_emails']): ?><div><strong>Cc:</strong> <?php echo htmlspecialchars($r['cc_emails']) ?></div><?php endif ?>
  <?php if ($this->attachment): ?><div><strong>Filename:</strong> <?php echo htmlspecialchars($this->filename) ?></div><?php endif ?>
  <div><strong>Subject:</strong><input name="subject" value="<?php echo htmlspecialchars($this->subject, ENT_QUOTES) ?>"></div>
  <div><strong>Message:</strong><textarea name="message"><?php echo htmlspecialchars($this->message) ?></textarea></div>
  <div class="send-cnt"><button name="action" value="send" type="submit" class="button btn-other">Send</button></div>
  <input type="hidden" name="id" value="<?php echo $r['id'] ?>"><?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>
