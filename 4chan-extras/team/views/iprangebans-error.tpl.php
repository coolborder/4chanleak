<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Error</title>
  <link rel="stylesheet" type="text/css" href="/css/error.css">
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<div id="error">One or more ranges are invalid:</div>
<ul id="rangelist-error">
<?php if ($this->invalid): ?>
  <?php foreach ($this->invalid as $cidr): ?>
  <li><?php echo htmlspecialchars($cidr) ?></li>
  <?php endforeach ?>
<?php endif ?>
</ul>
</body>
</html>
