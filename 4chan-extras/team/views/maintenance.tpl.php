<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Maintenance Tools</title>
  <link rel="stylesheet" type="text/css" href="/css/maintenance.css">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body data-tips>
<header>
  <h1 id="title">Maintenance Tools</h1>
</header>
<div id="content">
  <div class="section">
    <form action="" method="post"><?php echo csrf_tag() ?>
      <h4>Rebuild &amp; Restart</h4>
      <?php if ($this->isAdmin || $this->isDev): ?>
      <p><button data-tip="Regenerates all pages on all boards" class="button btn-other" name="action" value="rebuild_all" type="submit">Rebuild Boards</button></p>
      <?php endif ?>
      <p><button data-tip="Restarts the service responsible for rebuilding board pages on some boards" class="button btn-other" name="action" value="restart_daemons" type="submit">Restart Rebuild Daemons</button></p>
    </form>
    <?php if ($this->isAdmin || $this->isDev): ?>
    <form action="https://sys.4chan.org/a/imgboard.php" method="get">
      <p><button data-tip="Rebuilds the boards.json API file" class="button btn-other" name="mode" value="rebuildboardsjson" type="submit">Rebuild boards.json</button></p>
    </form>
    <form action="" method="get">
      <p><button data-tip="Prints JSON strings of SFW and NSFW boards" class="button btn-other" name="action" value="get_ws_boards" type="submit">Print SFW/NSFW boards</button></p>
    </form>
    <form action="https://sys.4chan.org/a/imgboard.php" method="get">
      <p><button data-tip="Rebuilds the search page" class="button btn-other" name="mode" value="rebuildsearchpage" type="submit">Rebuild Search Page</button></p>
    </form>
    <form action="https://sys.4chan.org/a/imgboard.php" method="get">
      <p><button data-tip="Rebuilds the syncframe page" class="button btn-other" name="mode" value="rebuildsyncframepage" type="submit">Rebuild SyncFrame Page</button></p>
    </form>
    <?php endif ?>
  </div>
  <div class="section">
    <h4>Purge CloudFlare Cache</h4>
    <p>URL(s):</p>
    <form action="" method="post"><?php echo csrf_tag() ?>
      <textarea required rows="8" cols="40" name="purge_list"></textarea>
      <button class="button btn-other" name="action" value="purge_cache" type="submit">Purge</button>
    </form>
  </div>
</div>
<footer></footer>
</body>
</html>
