<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Legal Requests</title>
  <link rel="stylesheet" type="text/css" href="/css/legalrequest.css?3">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">Legal Requests</h1>
</header>
<div id="menu">
<ul>
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<form id="form-add" class="form" action="" method="POST" enctype="multipart/form-data">
  <table>
    <tr>
      <th>Request Type</th>
      <td><select name="type">
        <?php foreach ($this->valid_types as $value => $label): ?>
        <option value="<?php echo $value ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tr>
      <th>Post UIDs<div class="note">/board/postno<br>or post URLs</div></th>
      <td><textarea name="post_uids" rows="8" cols="40"></textarea></td>
    </tr>
    <tr>
      <th>Image URLs</th>
      <td><textarea name="img_urls" rows="8" cols="40"></textarea></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tr>
      <th>Boards</th>
      <td><input class="board-field" type="text" name="board"> (comma-separated list, defaults to all)</td>
    </tr>
    <tr>
      <th>IPs</th>
      <td><textarea name="ips" rows="8" cols="40"></textarea></td>
    </tr>
    <tr>
      <th>User IDs<div class="note">Searches<br>only posts</div></th>
      <td><textarea name="user_ids" rows="8" cols="40"></textarea></td>
    </tr>
    <tr>
      <th>User Names</th>
      <td><textarea name="user_names" rows="8" cols="40"></textarea></td>
    </tr>
    <tr>
      <th>4chan Passes</th>
      <td><textarea name="passes_param" rows="8" cols="40"></textarea></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tr>
      <th>From Date</th>
      <td><input class="date-field" pattern="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}" type="text" placeholder="YYYY-MM-DD HH:mm:ss" name="date_start"></td>
    </tr>
    <tr>
      <th>To Date</th>
      <td><input class="date-field" pattern="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}" type="text" placeholder="YYYY-MM-DD HH:mm:ss" name="date_end"></td>
    </tr>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2"><button class="button btn-other" type="submit" name="action" value="search">Search</button></td>
      </tr>
    </tfoot>
  </table>
  <?php echo csrf_tag() ?>
</form>
</div>
<footer></footer>
</body>
</html>
