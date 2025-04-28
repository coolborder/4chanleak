<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Post Filter</title>
  <link rel="stylesheet" type="text/css" href="/css/postfilter.css?9">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/postfilter.js?10"></script>
</head>
<body data-page="search">
<header>
  <h1 id="title">Post Filter</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<form id="search-form" class="form" action="<?php echo self::WEBROOT ?>" method="GET">
  <table>
    <tr>
      <th>ID</th>
      <td><input class="value-field search-field" type="text" name="id"></td>
    </tr>
    <tr>
      <th>Board</th>
      <td><select class="search-field" name="board" size="1">
        <option value="">Any</option>
        <option value="global">Global</option>
        <?php foreach ($this->valid_boards as $board => $_): ?>
        <option value="<?php echo $board ?>"><?php echo $board ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th>Pattern</th>
      <td><input class="value-field search-field" type="text" name="pattern"></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><input class="value-field search-field" type="text" name="description"></td>
    </tr>
    <tr>
      <th>Regex</th>
      <td><input class="search-field" type="checkbox" name="regex"></td>
    </tr>
    <tr>
      <th>Autosage</th>
      <td><input class="search-field" type="checkbox" name="autosage"></td>
    </tr>
    <tr>
      <th>Ban</th>
      <td><input class="search-field" type="checkbox" name="ban"></td>
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
<form class="form" action="<?php echo self::WEBROOT ?>" method="GET">
  <table>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Find filters matching the provided text">Text</span></th>
      <td><textarea name="text" rows="8" cols="40"></textarea></td>
    <tr>
    <tfoot>
      <tr>
        <td colspan="2">
          <button class="button btn-other" type="submit" name="action" value="match">Match</button>
        </td>
      </tr>
    </tfoot>
  </table>
</form>
</div>
<footer></footer>
</body>
</html>
