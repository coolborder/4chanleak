<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Range Bans</title>
  <link rel="stylesheet" type="text/css" href="/css/iprangebans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Range Bans</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
  <li><a class="button button-light" href="?action=update">Add</a></li>
</ul>
</div>
<div id="content">
<?php if (!isset($this->trim_result)): ?>
<h4>List of IPs to trim</h4>
<form id="form-dedup" action="" method="POST" enctype="multipart/form-data"><div><textarea name="ips" rows="8" cols="40"></textarea></div><button class="button btn-other" name="action" value="test_ips" type="submit">Trim</button><?php echo csrf_tag(); ?>
</form>
<?php else: ?>
<?php if (empty($this->trim_result)): ?>
<h4>Supplied IPs are already banned.</h4>
<?php else: ?>
<h4>The following IPs are not banned yet:</h4>
<pre id="range-list">
<?php foreach ($this->trim_result as $range): ?><?php echo $range ?>

<?php endforeach ?>
</pre>
<?php endif ?>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
