<?php
require_once '../lib/sec.php';

require_once 'lib/admin.php';
require_once 'lib/auth.php';

define('IN_APP', true);

auth_user();

if (!has_level('admin') && !has_flag('developer')) {
  APP::denied();
}

require_once '../lib/csp.php';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Proxy List Importer</title>
  <link rel="stylesheet" type="text/css" href="/css/bans.css">
  <script type="text/javascript" src="/js/admincore.js"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/reqdump.js?1"></script>
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Legal Request Dumper</h1>
</header>
<div id="menu">
</div>
<div id="content">
<form class="form ban-ips-form" id="js-form" action="" method="POST">
  <table>
    <tfoot>
      <tr>
        <td colspan="2"><span id="js-progress"></span><button id="js-submit-btn" class="button btn-deny" type="submit">Submit</button> <button class="button btn-other" type="reset">Reset</button></td>
      </tr>
    </tfoot>
  </table>
</form>
<table class="items-table compact-table">
  <tr>
    <td class="cnt-pre" id="js-cell-ok"></td>
  </tr>
</table>
</div>
<footer></footer>
</body>
</html>
