<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Janitor Applications<?php echo ' (' . $this->ratedCount . '/' . $this->unratedCount . ')'; ?></title>
  <link rel="stylesheet" type="text/css" href="/css/janitorapps.css?9">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/janitorapps.js?13"></script>
</head>
<body data-mode="rate" <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Janitor Applications</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu"><div class="left"><span class="form-label">Board</span><div class="select-board select-box"><select data-tip="First choice" name="board" id="filter-board">
  <option value="any">All</option>
  <?php foreach ($this->boards as $board): ?>
  <option<?php if ($this->filterBoard == $board): ?> selected="selected"<?php endif ?>><?php echo $board ?></option>
  <?php endforeach ?>
  </select></div><div class="select-board select-box"><select data-tip="Any choice" name="board2" id="filter-board2">
  <option value="any">All</option>
  <?php foreach ($this->boards as $board): ?>
  <option<?php if ($this->filterBoard2 == $board): ?> selected="selected"<?php endif ?>><?php echo $board ?></option>
  <?php endforeach ?>
  </select></div></div><?php if ($this->canReview): ?><a class="button button-light right" href="?action=stats">Stats</a><a class="button button-light right" href="?action=review">Review Apps</a><?php endif ?><div id="protip">Rate apps from 1 (worst) to 5 (best). Use your <kbd>1-5</kbd> keys to select a score, then press a second time to confirm and move on to the next app.</div></div>
<?php $app = $this->app ?>
<div id="content">
<?php if (!$app): ?>
  <div class="load-empty">No applications to review</div>
<?php else: ?>
  <div id="app-<?php echo $app['id'] ?>" class="application">
    <span class="app-no">App No.<?php if ($this->canReview): ?><a href="https://team.4chan.org/janitorapps?action=review&search=<?php echo $app['id'] ?>" target="_blank"><?php echo $app['id'] ?></a><?php else: ?><?php echo $app['id'] ?><?php endif ?></span>
    <div class="app-ctrl score-ctrl score-ctrl-top">
      <span class="score-lbl">Rating</span>
      <span class="score-set"><span data-cmd="rate" data-score="1" class="score-btn">1</span><span data-cmd="rate" data-score="2" class="score-btn">2</span><span data-cmd="rate" data-score="3" class="score-btn">3</span><span data-cmd="rate" data-score="4" class="score-btn">4</span><span data-cmd="rate" data-score="5" class="score-btn">5</span></span>
    </div>
    <div class="app-content">
      <?php if ($this->is_supadmin):
      if ($app['tz'] > 12) {
        $tz = 12;
      }
      elseif ($app['tz'] < -12) {
        $tz = -12;
      }
      else {
        $tz = (int)$app['tz'];
      }
      
      $tz_name = timezone_name_from_abbr('', $tz * 3600, 0);
      
      if (!$tz_name) {
        $tz_name = timezone_name_from_abbr('', (1 + $tz) * 3600, 0);
      }
      ?>
      <div class="app-cat app-cat-info">
        <h3>Candidate Info</h3>
        <p><?php echo "<strong>Name:</strong> &quot;{$app['firstname']}&quot; <span data-email=\"{$app['email']}\" class=\"app-email\">&lt;{$app['email']}&gt;</span>" ?></p>
        <p><?php echo "<strong>Username:</strong> <span class=\"app-username\">{$app['handle']}</span>" ?></p>
        <p><strong>Age:</strong> <?php echo $app['age'] ?></p>
        <p><?php echo "<strong>Timezone:</strong> UTC " . substr('+' . $app['tz'], -2) . " (" . str_replace('_', ' ', $tz_name) . ")<br><strong>Time available:</strong> {$app['times']}<br><strong>Hours browsing:</strong> {$app['hours']}" ?><br><strong>Ban history:</strong> <?php echo $app['ban_count'] ?> bans<?php if ($app['ban_count'] > 0): ?> [<a href="/bans?action=search&amp;ip=<?php echo $app['ip'] ?>" target="_blank">Show</a>]<?php endif ?></p>
      </div>
      <?php endif ?>
      <div class="app-cat">
        <h3>Board(s) Desired</h3>
        <?php echo "{$app['board1']} {$app['board2']}" ?>
      </div>
      <div class="app-cat">
        <h3><?php echo self::STR_Q1 ?></h3>
        <?php echo nl2br($app['q1']) ?>
      </div>
      <div class="app-cat">
        <h3><?php echo self::STR_Q2 ?></h3>
        <?php echo nl2br($app['q2']) ?>
      </div>
      <div class="app-cat">
        <h3><?php echo self::STR_Q3 ?></h3>
        <?php echo nl2br($app['q3']) ?>
      </div>
      <div class="app-cat">
        <h3><?php echo self::STR_Q4 ?></h3>
        <?php echo nl2br($app['q4']) ?>
      </div>
    </div>
    <a title="Key: S" href="" class="button button-light left skip-btn-d">Skip</a>
    <div class="app-ctrl score-ctrl score-ctrl-bottom">
      <span class="score-lbl">Rating</span>
      <span class="score-set"><span data-cmd="rate" data-score="1" class="score-btn">1</span><span data-cmd="rate" data-score="2" class="score-btn">2</span><span data-cmd="rate" data-score="3" class="score-btn">3</span><span data-cmd="rate" data-score="4" class="score-btn">4</span><span data-cmd="rate" data-score="5" class="score-btn">5</span></span>
    </div>
    <a title="Key: S" href="" class="button button-light skip-btn-m">Skip</a>
  </div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
