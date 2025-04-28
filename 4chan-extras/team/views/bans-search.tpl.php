<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Bans - Search</title>
  <link rel="stylesheet" type="text/css" href="/css/bans.css?25">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/bans.js?12"></script>
</head>
<body data-page="search">
<header>
  <h1 id="title">Search Bans</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<form id="search-form" name="search" class="form search-form" action="<?php echo self::WEBROOT ?>" method="GET">
  <table>
    <tr>
      <th><span data-tip="Supports trailing wildcards (*)" class="wot">IP</span></th>
      <td><input class="s-f" type="text" name="ip"></td>
    </tr>
    <tr>
      <th>Name</th>
      <td><input class="s-f" type="text" name="name"></td>
    </tr>
    <tr>
      <th>Tripcode</th>
      <td><input class="s-f" type="text" name="tripcode"></td>
    </tr>
    <tr>
      <th>Password</th>
      <td><input class="s-f" type="text" name="password"></td>
    </tr>
    <tr>
      <th>Hostname</th>
      <td><input class="s-f" type="text" name="hostname"></td>
    </tr>
    <tr>
      <th>Country</th>
      <td><input class="s-f" type="text" name="country"></td>
    </tr>
    <tr>
      <th>File MD5</th>
      <td><input class="s-f" type="text" name="md5"></td>
    </tr>
    <tr>
      <th><span data-tip="Post or Ban referencing a 4chan Pass. Can be a post URL, ban URL, ban ID or /board/post_id" class="wot">Pass Ref.</span></th>
      <td><input class="s-f" type="text" name="pass_ref"></td>
    </tr>
    <?php if ($this->is_manager): ?>
    <tr>
      <th>4chan Pass</th>
      <td><input class="s-f" type="text" name="pass_id"></td>
    </tr>
    <tr>
      <th>Browser ID</th>
      <td><input class="s-f" type="text" name="ua"></td>
    </tr>
    <?php endif ?>
    <tr>
      <th>Board</th>
      <td><input id="js-board" class="s-f" type="text" name="board"></td>
    </tr>
    <tr>
      <th>Post No.</th>
      <td><input class="s-f" type="text" name="post_id"></td>
    </tr>
    <tr>
      <th><span data-tip="The Board field needs to be provided." class="wot">Thread Sub.</span></th>
      <td><input id="js-t-sub" class="s-f" type="text" name="sub"></td>
    </tr>
    <tr>
      <th>Banned by</th>
      <td><input class="s-f" type="text" name="banned_by"></td>
    </tr>
    <tr>
      <th>Template</th>
      <td><select class="s-f" name="tpl">
        <option value=""></option>
      <?php foreach ($this->ban_templates as $board => $templates): ?>
        <optgroup label="<?php echo $board === 'global' ? 'Global' : "/$board/" ?>">
        <?php foreach ($templates as $tpl): ?>
          <option value="<?php echo $tpl['no'] ?>"><?php echo $tpl['name'] ?></option>
        <?php endforeach ?>
        </optgroup>
      <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th>Reason</th>
      <td><input class="s-f" type="text" name="reason"></td>
    </tr>
    <tr>
      <th>Date</th>
      <td><input id="js-dsf" placeholder="MM/DD/YY" class="s-f date-field" type="text" name="ds"> — <input id="js-def" placeholder="MM/DD/YY" class="s-f date-field" type="text" name="de"></td>
    </tr>
    <tr>
      <th>Active Only</th>
      <td><input class="s-f" type="checkbox" name="active"></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2">
          <button class="button btn-other" type="submit" name="action" value="search">Search</button>
        </td>
      </tr>
    </tfoot>
  </table>
</form>
</div>
<footer></footer>
</body>
</html>
