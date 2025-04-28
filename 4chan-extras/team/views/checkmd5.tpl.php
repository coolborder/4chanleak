<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Check MD5</title>
  <link rel="stylesheet" type="text/css" href="/css/checkmd5.css?3">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Check MD5</h1>
</header>
<div id="content">
<form class="md5-form" action="" method="post" enctype="multipart/form-data">
    <input type="file" name="file"><button class="button btn-other" type="submit">Upload</button><?php echo csrf_tag() ?>
</form>
<?php if ($this->md5): ?>
<ul class="result-cnt">
  <li><strong>MD5 (original): </strong><code><?php echo $this->md5 ?></code></li>
  <?php if ($this->md5_noexif): ?>
  <li><strong>MD5 (stripped): </strong><code><?php echo $this->md5_noexif ?></code></li>
  <?php endif ?>
</ul>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
