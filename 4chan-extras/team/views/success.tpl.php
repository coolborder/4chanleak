<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <?php if (isset($this->redirect) && $this->redirect): ?><meta http-equiv="Refresh" content="1; url=<?php echo $this->redirect ?>" /><?php endif ?>
  <title>Success</title>
  <link rel="stylesheet" type="text/css" href="/css/success.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body><?php if (isset($this->success_msg) && $this->success_msg): ?>
  <div id="message"><?php echo $this->success_msg ?></div>
<?php elseif (isset($this->success_done) && $this->success_done): ?>
  <div id="success"><?php echo $this->success_done ?></div>
<?php else: ?>
  <div id="success">Done.</div>
<?php endif ?>
</body>
</html>
