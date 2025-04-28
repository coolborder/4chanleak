<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Blacklist</title>
  <link rel="stylesheet" type="text/css" href="/css/blacklist.css?2">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/helpers.js?16"></script>
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/blacklist.js"></script>
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
<form id="form-edit-rule" action="" method="POST" enctype="multipart/form-data">
<?php
/**
 * Edit entry
 */
if ($this->item):
$dmca_off =  $this->item['ban'] === '2' ? 'disabled ' : '';
?>
  <input type="hidden" name="id" value="<?php echo $this->item['id'] ?>">
  <table>
    <tr>
      <th>Active</th>
      <td><input type="checkbox" name="active"<?php if ($this->item['active']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Board</th>
      <td><select <?php echo $dmca_off ?>name="board" size="1">
        <option value="">All</option>
        <option <?php if ($this->item['boardrestrict'] === self::WS_BOARD_TAG) echo 'selected ' ?>value="<?php echo self::WS_BOARD_TAG ?>">All Worksafe</option>
        <?php foreach ($this->valid_boards as $board => $_): ?>
        <option <?php if ($this->item['boardrestrict'] == $board) echo 'selected ' ?>value="<?php echo $board ?>"><?php echo $board ?></option>
        <?php endforeach ?>
      </select>
      </td>
    </tr>
    <tr>
      <th>Field</th>
      <td><select <?php echo $dmca_off ?>name="field" size="1">
        <?php foreach ($this->valid_fields as $field => $label): ?>
        <option <?php if (isset($this->field_tips[$field])) echo('title="' . $this->field_tips[$field] . '" ') ?><?php if ($this->item['field'] === $field) echo 'selected ' ?>value="<?php echo $field ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th>Value</th>
      <td><input <?php echo $dmca_off ?>class="value-field" type="text" name="value" value="<?php echo htmlspecialchars($this->item['contents'], ENT_QUOTES) ?>"></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><textarea <?php echo $dmca_off ?>name="description" rows="8" cols="40"><?php echo $this->item['description'] ?></textarea></td>
    </tr>
    <?php if ($this->item['ban'] === '2'): ?>
    <tr>
      <th>Action</th>
      <td>Reject (DMCA)</td>
    </tr>
    <?php else: ?>
    <tr>
      <th>Action</th>
      <td><label class="action-lbl"><input id="action-ban-btn" type="radio" name="act"<?php if ($this->item['ban'] === '1') echo ' checked' ?> value="ban"> Ban</label> <label class="action-lbl"><input type="radio" name="act"<?php if ($this->item['ban'] === '0') echo ' checked' ?> value="reject"> Reject</label></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Don't show errors and make it look like the post went through successfuly">Quiet</span></th>
      <td><input type="checkbox" name="quiet"<?php if ($this->item['quiet']) echo ' checked="checked"' ?>></td>
    </tr>
    <tr>
      <th>Ban Length</th>
      <td><input class="banlen-field ban-fields"<?php if ($this->item['ban'] === '0') echo ' disabled' ?> type="text" name="ban_length" value="<?php echo $this->item['banlength'] > 0 ? $this->item['banlength'] : '' ?>"> day(s). Defaults to a permanent ban.</td>
    </tr>
    <tr>
      <th>Public Ban Reason</th>
      <td><textarea class="ban-fields"<?php if ($this->item['ban'] === '0') echo ' disabled' ?> name="ban_reason" rows="8" cols="40"><?php echo $this->item['banreason'] ?></textarea></td>
    </tr>
    <?php endif ?>
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
      <th>Board</th>
      <td><select name="board" size="1">
        <option value="">All</option>
        <option <?php if ($this->item['boardrestrict'] === self::WS_BOARD_TAG) echo 'selected ' ?>value="<?php echo self::WS_BOARD_TAG ?>">All Worksafe</option>
        <?php foreach ($this->valid_boards as $board => $_): ?>
        <option value="<?php echo $board ?>"><?php echo $board ?></option>
        <?php endforeach ?>
      </select>
      </td>
    </tr>
    <tr>
      <th>Field</th>
      <td><select name="field" size="1">
        <?php foreach ($this->valid_fields as $field => $label): ?>
        <option <?php if (isset($this->field_tips[$field])) echo('title="' . $this->field_tips[$field] . '" ') ?>value="<?php echo $field ?>"><?php echo $label ?></option>
        <?php endforeach ?>
      </select></td>
    </tr>
    <tr>
      <th>Value</th>
      <td><input class="value-field" type="text" name="value"></td>
    </tr>
    <tr>
      <th>Description</th>
      <td><textarea name="description" rows="8" cols="40"></textarea></td>
    </tr>
    <tr>
      <th>Action</th>
      <td><label class="action-lbl"><input id="action-ban-btn" type="radio" name="act" value="ban"> Ban</label> <label class="action-lbl"><input type="radio" name="act" value="reject" checked> Reject</label></td>
    </tr>
    <tr>
      <th><span class="wot" data-tip="Don't show errors and make it look like the post went through successfuly">Quiet</span></th>
      <td><input type="checkbox" name="quiet"></td>
    </tr>
    <tr>
      <th>Ban Length</th>
      <td><input class="banlen-field ban-fields" type="text" name="ban_length" value="" disabled> day(s). Defaults to a permanent ban.</td>
    </tr>
    <tr>
      <th><span data-tip="Public Reason">Ban Reason</span></th>
      <td><textarea class="ban-fields" name="ban_reason" rows="8" cols="40" disabled></textarea></td>
    </tr>
<?php endif ?>
    <tfoot>
      <tr>
        <td colspan="2">
          <?php if ($this->item): if ($this->item['ban'] !== '2' || $this->canEditDMCAEntries): ?>
          <button class="button btn-other" type="submit" name="action" value="update">Update</button>
          <button class="button btn-deny" type="submit" name="action" value="delete">Delete</button>
          <?php endif; else: ?>
          <button class="button btn-other" type="submit" name="action" value="update">Create</button>
          <?php endif ?>
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
