<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Filter Test</title>
  <link rel="stylesheet" type="text/css" href="/css/postfilter.css?8">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/postfilter.js?10"></script>
</head>
<body data-page="search">
<header>
  <h1 id="title">Filter Test</h1>
</header>
<div id="menu">
</div>
<div id="content">
<?php if ($this->query): ?>
<h4>Matches found on the following boards<?php if ($this->overflow): ?> (showing the first <?php echo $self::MAX_TEST_RESULTS ?> results)<?php endif ?></h4>
<ul class="items-list">
<?php foreach ($this->filters as $filter): ?>
  <li><?php if ($filter['board']): ?>/<?php echo $filter['board'] ?>/<?php else: ?>Global<?php endif ?><?php if ($filter['autosage']): ?> (autosage)<?php endif ?></li>
<?php endforeach ?>
</ul>
<?php else: ?>
<h4>Test whether a string matches an active filter</h4>
<?php endif ?>
<form class="form" action="<?php echo self::WEBROOT ?>" method="GET">
  <table>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tr>
      <th>Text</th>
      <td><textarea name="text" rows="8" cols="40"><?php if ($this->query) { echo $this->query; } ?></textarea></td>
    <tr>
    <tfoot>
      <tr>
        <td colspan="2">
          <button class="button btn-other" type="submit" name="action" value="test">Match</button>
        </td>
      </tr>
    </tfoot>
  </table>
</form>
</div>
<footer></footer>
</body>
</html>
