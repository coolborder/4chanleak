<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Edit Keywords</title>
  <link rel="stylesheet" type="text/css" href="/css/globalmsgedit.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Edit Keywords</h1>
</header>
<div id="content">
<?php foreach ($this->messages as $type => $message): ?>
<h4><?php echo ucfirst($type) ?></h4>
<form class="keywords-form" action="" method="post"><input type="hidden" name="type" value="<?php echo $type ?>"><?php echo csrf_tag() ?>
  <textarea class="keywords-field" name="message"><?php echo $message ?></textarea>
  <button class="button btn-other" name="action" value="update">Update</button>
</form>
<?php endforeach ?>
</div>
<footer></footer>
</body>
</html>
