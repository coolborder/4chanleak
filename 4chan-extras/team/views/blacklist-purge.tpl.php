<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Blacklist</title>
  <link rel="stylesheet" type="text/css" href="/css/blacklist.css">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Blacklist</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<h3>Remove Blacklisted MD5s</h3>
<form id="form-edit-rule" class="purge-form" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <td><textarea required class="purge-list" name="md5" rows="8" cols="40"></textarea></td>
    </tr>
    <tfoot>
      <tr>
        <td>
          <button class="button btn-other" type="submit" name="action" value="purge">Remove</button>
        </td>
      </tr>
    </tfoot>
  <?php echo csrf_tag() ?>
  </table>
</form>
</div>
<footer></footer>
</body>
</html>
