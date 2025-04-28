<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Contact Tool</title>
  <link rel="stylesheet" type="text/css" href="/css/contacttool.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/contacttool.js"></script>
</head>
<body>
<header>
  <h1 id="title">Contact Tool</h1>
</header>
<div id="content">
<div id="mail-groups">
<h4>Send To:</h4>
<?php foreach ($this->labels as $label => $key): ?>
  <span data-cmd="change-group" data-group="<?php echo $key ?>" class="button btn-other"><?php echo $label ?></span>
<?php endforeach ?>
<div class="hidden">
<?php foreach ($this->groups as $group => $items): ?>
  <div id="subgroup-<?php echo $group ?>">
  <?php foreach ($items as $item): ?>
  <span class="subgroup-item"><label><input type="checkbox" name="<?php echo $group ?>[]" value="<?php echo $item ?>"> <?php echo $item ?></label></span>
  <?php endforeach ?>
  </div>
<?php endforeach ?>
</div>
</div>
<form autocomplete="off" class="hidden" id="mail-form" action="" method="post" enctype="multipart/form-data">
  <div id="active-subgroup"></div>
  <h4>Compose Message:</h4>
  <div id="message-fields">
    <div class="field-grp">
      <label>Subject:</label>
      <input type="text" name="subject" required>
    </div>
    <div class="field-grp">
      <label>Message:</label>
      <textarea required name="message"></textarea>
    </div>
  </div>
  <button class="button btn-other" type="submit" name="action" value="send">Send</button><?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>
