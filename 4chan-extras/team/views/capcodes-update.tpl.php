<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Capcodes</title>
  <link rel="stylesheet" type="text/css" href="/css/capcodes.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
</head>
<body data-tips data-page="update">
<header>
  <h1 id="title">Capcodes</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<?php
if (isset($this->plain_user_key)): ?>
<div id="capcode-created"><p>The capcode for this user is:</p><code>capcode_!<?php echo $this->user_id ?>!<?php echo $this->plain_user_key ?></code></div>
<?php
else:
?>
<form class="form" action="" method="POST">
<?php
/**
 * Edit entry
 */
if ($this->item): ?>
  <input type="hidden" name="id" value="<?php echo $this->item['id'] ?>">
  <table>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if ($this->item['active']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Name</th>
      <td><input class="value-field" required type="text" name="name" value="<?php echo $this->item['name'] ?>"></td>
    </tr>
    <tr>
      <th>E-Mail</th>
      <td><input class="value-field" required type="text" name="email" value="<?php echo $this->item['email'] ?>"></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><input class="value-field" type="text" name="description" value="<?php echo $this->item['description'] ?>"></td>
    </tr>
<?php
/**
 * New entry
 */
else:
?>
  <table>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active" checked="checked"></td>
    </tr>
    <tr>
      <th>Name</th>
      <td><input class="value-field" required type="text" name="name"></td>
    </tr>
    <tr>
      <th>E-Mail</th>
      <td><input class="value-field" required type="text" name="email"></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><input class="value-field" type="text" name="description"></td>
    </tr>
<?php endif ?>
    <tr class="row-sep">
      <td colspan="2"><hr></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2">
          <?php if ($this->item): ?>
          <button class="button btn-other" type="submit" name="action" value="update">Update</button>
          <button data-tip="Reset Capcode" class="button btn-deny" type="submit" name="action" value="reset">Reset</button>
          <button class="button btn-deny" type="submit" name="action" value="delete">Delete</button>
          <?php else: ?>
          <button class="button btn-other" type="submit" name="action" value="update">Create</button>
          <?php endif ?>
        </td>
      </tr>
      <tr class="row-sep">
        <td colspan="2"><hr></td>
      </tr>
    </tfoot>
  <?php echo csrf_tag() ?>
  </table>
</form>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
