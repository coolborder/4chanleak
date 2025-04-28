<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Post Filter</title>
  <link rel="stylesheet" type="text/css" href="/css/postfilter.css?10">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/postfilter.js?10"></script>
</head>
<body data-page="update">
<header>
  <h1 id="title">Post Filter</h1>
</header>
<div id="menu">
<ul class="left">
  <li><a class="button button-light" href="<?php echo self::WEBROOT ?>">Return</a></li>
</ul>
</div>
<div id="content">
<div class="cnt-col">
<form class="form" action="" method="POST" enctype="multipart/form-data"><fieldset id="js-main-fields">
<?php
/**
 * Edit entry
 */
if ($this->item): ?>
  <input type="hidden" name="id" value="<?php echo $this->item['id'] ?>">
  <table>
    <tr>
      <th>ID</th>
      <td><?php echo ($this->item['id']) ?></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Hits in the past <?php echo self::HIT_STATS_DAYS ?> days">Hits</span></th>
      <td><?php echo ($this->item['hits']) ?></td>
    </tr>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if ($this->item['active']) echo ' checked="checked"' ?>><div class="form-inner-grp"><label for="f-never-expires" class="wot" data-tip="Prevent the filter from getting disabled automatically for lack of hits">Never Expires</label><input type="checkbox" id="f-never-expires" name="never_expires"<?php if ($this->item['never_expires']) echo ' checked="checked"' ?>></div></td>
    </tr>
    <tr>
      <th>Board</th>
      <td><select name="board" size="1">
        <option value="">All</option>
        <?php foreach ($this->valid_boards as $board => $_): ?>
        <option <?php if ($this->item['board'] === $board) echo 'selected ' ?> value="<?php echo $board ?>"><?php echo $board ?></option>
        <?php endforeach ?>
      </select>
      </td>
    </tr>
    <tr>
      <th>Pattern</th>
      <td><input id="js-pattern-field" class="value-field" type="text" name="pattern" value="<?php echo htmlspecialchars($this->item['pattern'], ENT_QUOTES) ?>"></td>
    </tr>
    <tr>
      <th></th>
      <td><div class="tree-wrap">Transform: <span data-cmd="escape-html" class="button btn-net">Escape HTML</span> <span data-cmd="escape-str" class="button btn-net">For Strings</span> <span data-cmd="escape-sage" class="button btn-net">For Autosage</span></div></td>
    </tr>
    <tr>
      <th>Regex</th>
      <td><input id="field-regex" type="checkbox" name="regex"<?php if ($this->item['regex']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Don't show any errors and pretend the post when through successfuly">Quiet</span></th>
      <td><input type="checkbox" name="quiet"<?php if ($this->item['quiet']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Only affect users with no known posting history">Lenient</span></th>
      <td><input type="checkbox" name="lenient"<?php if ($this->item['lenient']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>OPs only</th>
      <td><input type="checkbox" name="ops_only"<?php if ($this->item['ops_only']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Action</th>
      <td><label class="action-lbl"><input type="radio" name="act"<?php if ($this->item['autosage'] === '0') echo ' checked' ?> value="reject"> Reject</label> <label class="action-lbl"><input id="field-autosage" type="radio" name="act"<?php if ($this->item['autosage'] === '1') echo ' checked' ?> value="autosage"> Autosage</label></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Only relevant for Reject actions">Ban Length</span></th>
      <td><input id="field-banlen"<?php if ($this->item['autosage']) echo ' disabled' ?> class="banlen-field" type="text" name="ban_days" value="<?php echo $this->item['ban_days'] > 0 ? $this->item['ban_days'] : '' ?>"> day(s). Optional.</td>
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
      <td><input type="checkbox" name="active" checked="checked"><div class="form-inner-grp"><label for="f-never-expires" class="wot" data-tip="Prevent the filter from getting disabled automatically for lack of hits">Never Expires</label><input type="checkbox" id="f-never-expires" name="never_expires"<?php if ($this->item['never_expires']) echo ' checked="checked"' ?>></div></td>
    </tr>
    <tr>
      <th>Board</th>
      <td><select name="board" size="1">
        <option value="">All</option>
        <?php foreach ($this->valid_boards as $board => $_): ?>
        <option value="<?php echo $board ?>"><?php echo $board ?></option>
        <?php endforeach ?>
      </select>
      </td>
    </tr>
    <tr>
      <th>Pattern</th>
      <td><input id="js-pattern-field" class="value-field" type="text" name="pattern"></td>
    </tr>
    <tr>
      <th></th>
      <td><div class="tree-wrap">Transform: <span data-cmd="escape-html" class="button btn-net">Escape HTML</span> <span data-cmd="escape-str" class="button btn-net">For Strings</span> <span data-cmd="escape-sage" class="button btn-net">For Autosage</span></div></td>
    </tr>
    <tr>
      <th>Regex</th>
      <td><input id="field-regex" type="checkbox" name="regex"></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Don't show errors and make it look like the post went through successfuly">Quiet</span></th>
      <td><input type="checkbox" name="quiet"></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Only affect users with no known posting history">Lenient</span></th>
      <td><input type="checkbox" name="lenient"></td>
    </tr>
    <tr>
      <th>OPs only</th>
      <td><input type="checkbox" name="ops_only"<?php if ($this->item['ops_only']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Action</th>
      <td><label class="action-lbl"><input type="radio" name="act" value="reject" checked> Reject</label> <label class="action-lbl"><input id="field-autosage" type="radio" name="act" value="autosage"> Autosage</label></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Only relevant for Reject actions">Ban Length</span></th>
      <td><input id="field-banlen" class="banlen-field" type="text" name="ban_days"> day(s). Optional.</td>
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
  </table><?php echo csrf_tag() ?></fieldset>
</form>
<?php
/**
 * Copy entry
 */
if ($this->item): ?>
<form class="form" action="" method="POST" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?php echo $this->item['id'] ?>">
  <table>
    <tr>
      <th><label for="js-copy-toggle"><span class="wot" data-tip="Copy this filter to other boards">Copy</span></label></th>
      <td><input data-cmd="toggle-copy" id="js-copy-toggle" type="checkbox"></td>
    </tr>
    <tr>
      <th>Boards</th>
      <td><input id="js-copy-boards" class="value-field" type="text" name="boards" required></td>
    </tr>
    <tfoot>
      <tr>
        <td colspan="2">
          <button id="js-copy-btn" class="button btn-other" type="submit" name="action" value="copy">Copy</button>
        </td>
      </tr>
      <tr class="row-sep">
        <td colspan="2"><hr></td>
      </tr>
    </tfoot>
  </table><?php echo csrf_tag() ?>
</form><?php if (!empty($this->hit_stats)): ?>
<div id="hit-stats-cnt">
  <h4>Hit stats (<?php echo self::HIT_STATS_DAYS ?> days)</h4>
  <table class="items-table">
    <thead>
      <tr><th>Board</th><th>Hits</th></tr>
    </thead>
    <tbody>
      <?php foreach ($this->hit_stats as $hits): ?>
      <tr><td>/<?php echo $hits['board'] ?>/</td><td><?php echo $hits['hits'] ?></td></tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div><?php endif ?>
<?php endif ?>
</div>
<div class="cnt-col">
<div id="filter-doc">
  <h4>How posts are processed before going through the filter:</h4>
  <ul>
  <li>For all filters:<ul>
    <li>HTML is escaped</li>
    <li>Non-ASCII characters are converted to their ASCII equivalents</li>
    <li><code>[spoiler]</code>, <code>[code]</code> and <code>[sjis]</code> tags are removed</li>
    </ul></li>
  <li>For all regex filters:<ul><li><code>{name} {subject} {comment}</code></li></ul></li>
  <li>For string filters:<ul>
    <li><code>{name}{subject}{filename}{comment}</code></li>
    <li>Translit to ASCII, downcase and remove zero-width characters</li>
    <li>Remove characters other than <code>a-zA-Z0-9.,/&amp;:;?=~_-</code></li>
    </ul>
  </li>
  <li>For autosage string filters:<ul>
    <li><code>{subject} {comment} {name}</code></li>
    <li>Translit to ASCII, downcase and capitalise words</li>
    <li>Replace <code>.,!:>/</code> with spaces</li>
    </ul>
  </li>
</ul>
</div>
</div>
</div>
<footer></footer>
</body>
</html>
