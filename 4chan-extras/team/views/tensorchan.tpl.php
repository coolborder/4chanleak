<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="never">
  <title>Tensorchan</title>
  <link rel="stylesheet" type="text/css" href="/css/admincore.css?3">
  <link rel="stylesheet" type="text/css" href="/css/tensorchan.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/tensorchan.js?3"></script>
</head>
<body data-in-dims="<?php echo self::MODEL_INPUT_DIMS ?>" <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Tensorchan</h1>
</header>
<div id="content">
<div id="p-form">
<form id="js-up-form" name="up" class="form" action="<?php echo self::WEBROOT ?>" method="POST">
  <span class="form-label">Test prediction</span><input id="js-img-field" type="file" name="img">
  <label><input type="radio" name="resalg" checked> Resize</label> &ndash; <input id="js-crop" type="radio" name="resalg"> Crop</label> &ndash; <label><input id="js-pad" type="radio" name="resalg"> Pad</label> &ndash; <button type="submit">Predict</button>
  <span id="js-res"></span>
</form>
</div>
<div id="list-ctrl">
  <form action="" method="GET">
    <label>Board: <input type="text" name="board" value="<?php echo $this->html_args['board'] ?>"></label>
    and <label>nsfw &gt;= <input type="text" name="nsfw" value="<?php if (isset($this->html_args['nsfw'])) { echo $this->html_args['nsfw']; } ?>" placeholder="0.0000"></label>
    and <label>nsfw &lt; <input type="text" name="nsfw_less" value="<?php if (isset($this->html_args['nsfw_less'])) { echo $this->html_args['nsfw_less']; } ?>" placeholder="1.0000"></label>
  <button type="submit">Search</button><a href="?">Reset</a></form>
</div>
<?php if ($this->items): ?>
<h4 class="list-hdr">Showing the last <?php echo self::MAX_ITEMS ?> items</h4>
<?php endif ?>
<div class="img-list">
<?php foreach ($this->items as $item): ?>
  <div class="item"><div class="img-cnt"><a href="<?php echo "https://i.4cdn.org/" . $item['board'] . "/" . $item['file_id'] . $item['file_ext'] ?>"><img alt="Post deleted" class="post-thumb" loading="lazy" src="<?php echo "https://i.4cdn.org/" . $item['board'] . "/" . $item['file_id'] . "s.jpg" ?>"></a></div><div class="img-labels"><ul><li>board: <?php echo $item['board'] ?></li><?php echo $this->format_labels($item) ?></ul></div></div>
<?php endforeach ?>
</div>
</html>
