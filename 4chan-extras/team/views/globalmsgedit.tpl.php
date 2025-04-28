<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Edit <?php echo $this->title ?></title>
  <link rel="stylesheet" type="text/css" href="/css/globalmsgedit.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/globalmsgedit.js"></script>
</head>
<body>
<header>
  <h1 id="title">Edit <?php echo $this->title ?></h1>
</header>
<div id="content">
<?php if (isset($this->type)): ?>
<form id="post-form" action="" method="post"><input type="hidden" name="type" value="<?php echo $this->type ?>"><input type="hidden" name="action" value="update"><?php echo csrf_tag() ?>
  <textarea id="g-msg-field" name="message"><?php echo $this->message ?></textarea>
  <span id="btn-clear" class="button btn-deny">Clear</span>
  <span id="btn-preview" class="button btn-other">Preview</span>
  <span id="btn-update" class="button btn-accept disabled">Update</span>
</form>
<div id="g-msg-preview"></div>
<?php else: ?>
<div id="msg-types">
  <a href="?type=global" class="button btn-other">Global Message</a>
  <a href="?type=front" class="button btn-other">Front Page Message</a>
</div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
