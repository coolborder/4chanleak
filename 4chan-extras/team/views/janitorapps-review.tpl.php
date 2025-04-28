<?php if (!defined('IN_APP')) die() ?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="referrer" content="never">
  <title>Janitor Applications</title>
  <link rel="stylesheet" type="text/css" href="/css/janitorapps.css?9">
  <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
  <script type="text/javascript" src="/js/admincore.js?30"></script>
  <script type="text/javascript" src="/js/helpers.js"></script>
  <script type="text/javascript" src="/js/janitorapps.js?13"></script>
</head>
<body data-mode="review" <?php echo csrf_attr() ?>>
<header>
  <h1 id="title">Janitor Applications</h1><div id="cfg-btn"><span>&hellip;</span><div><label><input data-cmd="toggle-dt" id="cfg-cb-dt" type="checkbox" autocomplete="off">Dark Theme</label></div></div>
</header>
<div id="menu"><a class="button button-light right" href="?action=stats">Stats</a><a class="button button-light right" href="?">Rate Apps</a><form id="filter-form" action="">
<ul>
  <li><span class="form-label">Status</span><div class="select-box"><select name="status" id="filter-status">
  <option value="open">Undecided</option>
  <option <?php if ($this->filterStatus == 'accepted'): ?>selected="selected" <?php endif ?>value="accepted">1. Accepted (<?php echo (int)$this->counts[self::ACCEPTED] ?>)</option>
  <option <?php if ($this->filterStatus == 'signed'): ?>selected="selected" <?php endif ?>value="signed">2. Signed (<?php echo (int)$this->counts[self::SIGNED] ?>)</option>
  <option <?php if ($this->filterStatus == 'oriented'): ?>selected="selected" <?php endif ?>value="oriented">3. Oriented (<?php echo (int)$this->counts[self::ORIENTED] ?>)</option>
  <option <?php if ($this->filterStatus == 'closed'): ?>selected="selected" <?php endif ?>value="closed">4. Completed (<?php echo (int)$this->counts[self::CLOSED] ?>)</option>
  <option <?php if ($this->filterStatus == 'rejected'): ?>selected="selected" <?php endif ?>value="rejected">Rejected</option>
  <option <?php if ($this->filterStatus == 'ignored'): ?>selected="selected" <?php endif ?>value="ignored">Ignored</option>
  <option <?php if ($this->filterStatus == 'all'): ?>selected="selected" <?php endif ?>value="all">All</option>
  </select></div></li>
  <li><span class="form-label">Board</span><div class="select-box"><select name="board" id="filter-board">
  <option value="any">All</option>
  <?php foreach ($this->boards as $board): ?>
  <option<?php if ($this->filterBoard == $board): ?> selected="selected"<?php endif ?>><?php echo $board ?></option>
  <?php endforeach ?>
  </select></div></li>
  <li><span class="form-label">Timezone</span><div class="select-box"><select name="tz" id="filter-tz">
  <option value="any">All</option>
  <?php foreach ($this->timezones as $id => $tz): ?>
  <option<?php if ($this->filterTz === $id): ?> selected="selected"<?php endif ?> value="<?php echo $id ?>"><?php echo $tz ?></option>
  <?php endforeach ?>
  </select></div></li>
  <li><span class="form-label">Search</span><input placeholder="List of e-mails or IDs" name="search" value="<?php if ($this->filterSearch) { echo $this->filterSearch; } ?>" id="filter-search"></li>
  <li><span id="filter-apply" class="button button-light">Apply</span><button id="filter-submit" type="submit"></button></li>
</ul>
</form></div><div id="content" class="collapsed">
<?php if (!$this->apps): ?>
  <div class="load-empty">No applications to review</div>
<?php else: ?>
  <?php foreach ($this->apps as $app):
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
  <div id="app-<?php echo $app['id'] ?>" class="application">
    <span class="app-no">App No.<?php echo $app['id'] ?></span>
    <div class="app-ctrl">
      <span class="score-sum-lbl">
      <?php if ($app['total']): ?>
      Average Rating: <strong><?php echo $app['avg'] ?></strong>, Total Points: <?php echo $app['total'] ?>, Reviewer Count: <?php echo $app['votes'] ?>
      <?php else: ?>
      This application hasn't been rated yet.
      <?php endif ?>
      </span>
    </div>
    <div class="app-content">
      <div class="app-cat app-cat-info">
        <h3>Candidate Info</h3>
        <p><?php echo "<strong>Name:</strong> &quot;{$app['firstname']}&quot; <span data-email=\"{$app['email']}\" class=\"app-email\">&lt;{$app['email']}&gt;</span>" ?></p>
        <p><?php echo "<strong>Username:</strong> <span class=\"app-username\">{$app['handle']}</span>" ?></p>
        <p><strong>Age:</strong> <?php echo $app['age'] ?></p>
        <p><?php echo "<strong>Timezone:</strong> UTC " . substr('+' . $app['tz'], -2) . " (" . str_replace('_', ' ', $tz_name) . ")<br><strong>Time available:</strong> {$app['times']}<br><strong>Hours browsing:</strong> {$app['hours']}" ?><br><strong>Ban history:</strong> <?php echo $app['ban_count'] ?> bans [<a href="/bans?action=search&amp;ip=<?php echo $app['ip'] ?>" target="_blank">Show</a>] [<a href="/search#{%22ip%22:%22<?php echo $app['ip'] ?>%22}" target="_blank">Multisearch</a>] [<span class="cnt-block"><?php echo $app['ip'] ?></span>]</p>
        <p><strong>IP Location:</strong> <span><?php echo $app['geo_loc'] ?></span></p>
        <p><strong>User Agent:</strong> <span class="txt-xs"><?php echo $app['http_ua'] ?></span></p>
        <p><strong>UA Language:</strong> <span class="txt-xs"><?php echo $app['http_lang'] ?></span></p>
      </div>
      <div class="app-cat app-cat-boards">
        <h3>Board(s) Desired</h3>
        <span class="app-boards"><?php echo "{$app['board1']} {$app['board2']}" ?></span>
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
    <div class="app-ctrl app-review-ctrl">
      <span data-cmd="toggle-expand" class="button button-light app-toggle left">Expand</span>
      <?php if ($app['closed'] != self::CLOSED): ?>
      <?php if ($app['closed'] == self::OPEN || $app['closed'] == self::REJECTED || $app['closed'] == self::IGNORED): ?><span data-cmd="accept" class="button btn-accept">Accept&hellip;</span>
      <?php elseif ($app['closed'] == self::ACCEPTED): ?><span class="date-accepted">Accepted on <?php echo date('m/d/y', $app['updated_on']) ?></span><span data-cmd="accept" class="button btn-other">Resend Acceptance E-mail&hellip;</span>
      <?php elseif ($app['closed'] == self::SIGNED): ?><span data-cmd="send-orientation" class="button btn-other">Send Orientation E-mail&hellip;</span>
      <?php elseif ($app['closed'] == self::ORIENTED): ?><span data-cmd="create-account" class="button btn-other">Create Account&hellip;</span><?php endif ?>
      <?php if ($app['closed'] != self::REJECTED): ?><span data-cmd="reject" class="button btn-deny">Reject &cross;</span><?php endif ?>
      <?php endif ?>
    </div>
  </div>
  <?php endforeach ?>
<?php endif ?>
</div>
<footer>
  <div class="page-ctrl">
  <?php if ($this->previousOffset !== false): ?>
    <a class="button" href="?action=review<?php echo $this->previousOffset ?>">Previous</a>
  <?php endif ?>
  <?php if ($this->nextOffset !== false): ?>
    <a class="button" href="?action=review<?php echo $this->nextOffset ?>">Next</a>
  <?php endif ?>
  </div>
</footer>
<div data-cmd="shift-panel" id="panel-stack" tabindex="-1"></div>
</body>
</html>
