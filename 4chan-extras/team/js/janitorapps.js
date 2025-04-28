'use strict';

var APP = {};

APP.init = function() {
  this.xhr = {};
  
  this.root = '';
  this.panelStack = null;
  
  this.clickCommands = {
    'rate': APP.rateApplication,
    'accept' : APP.onAcceptClick,
    'send-orientation' : APP.onSendOrientationClick,
    'create-account' : APP.onCreateAccountClick,
    'reject' : APP.onRejectClick,
    'toggle-expand': APP.onToggleExpandClick,
    'shift-panel': APP.shiftPanel,
    'toggle-dt': APP.onToggleDTClick
  };
  
  this.keyStartCode = 49;
  this.keyStartCodeNum = 97;
  
  if (localStorage.getItem('dark-theme')) {
    $.addClass($.docEl, 'dark-theme');
  }
  
  $.on(document, 'click', APP.onClick);
  $.on(document, 'DOMContentLoaded', APP.run);
};

APP.run = function() {
  var mode;
  
  $.off(document, 'DOMContentLoaded', APP.run);
  
  mode = document.body.getAttribute('data-mode');
  
  if (localStorage.getItem('dark-theme')) {
    $.id('cfg-cb-dt').checked = true;
  }
  
  if (mode == 'review') {
    APP.root = 'action=review';
    APP.panelStack = $.id('panel-stack');
    $.on($.id('filter-form'), 'submit', APP.onApplyFilter);
    $.on($.id('filter-apply'), 'click', APP.onApplyFilter);
  }
  else if (mode == 'rate') {
    $.on(document, 'keyup', APP.onKeyUp);
    $.on(document, 'keydown', APP.onKeyDown); // FF opens the search box on 4
    $.on($.id('filter-board'), 'change', APP.onBoardChange);
    $.on($.id('filter-board2'), 'change', APP.onBoard2Change);
    $.on(document, 'mouseover', APP.onMouseOver);
    $.on(document, 'mouseout', APP.onMouseOut);
  }
  else if (mode == 'stats') {
    $.on(document, 'mouseover', APP.onMouseOver);
    $.on(document, 'mouseout', APP.onMouseOut);
  }
};

APP.onMouseOver = function(e) {
  var data, target = e.target;
  
  if (target.hasAttribute('data-tip')) {
    Tip.show(target, data);
  }
};

APP.onMouseOut = function(e) {
  var target = e.target;
  
  if (target.hasAttribute('data-tip')) {
    Tip.hide();
  }
};

APP.onToggleDTClick = function() {
  var el = $.id('cfg-cb-dt');
  
  if (el.checked !== $.hasClass($.docEl, 'dark-theme')) {
    if (el.checked) {
      $.addClass($.docEl, 'dark-theme');
      localStorage.setItem('dark-theme', '1');
    }
    else {
      $.removeClass($.docEl, 'dark-theme');
      localStorage.removeItem('dark-theme');
    }
  }
};

APP.onBoardChange = function(e) {
  var board, field;
  
  e && e.preventDefault();
  
  field = $.id('filter-board');
  
  if (field.selectedIndex) {
    board = field.options[field.selectedIndex].textContent;
    location.search = 'board=' + board;
  }
  else {
    location.search = '';
  }
};

APP.onBoard2Change = function(e) {
  var board, field;
  
  e && e.preventDefault();
  
  field = $.id('filter-board2');
  
  if (field.selectedIndex) {
    board = field.options[field.selectedIndex].textContent;
    location.search = 'board2=' + board;
  }
  else {
    location.search = '';
  }
};

APP.onApplyFilter = function(e) {
  var key, filter, field, hash;
  
  e && e.preventDefault();
  
  filter = {};
  
  field = $.id('filter-status');
  if (field.selectedIndex) {
    filter.status = field.options[field.selectedIndex].value;
  }
  
  field = $.id('filter-board');
  if (field.selectedIndex) {
    filter.board = field.options[field.selectedIndex].textContent;
  }
  
  field = $.id('filter-tz');
  if (field && field.selectedIndex) {
    filter.tz = field.options[field.selectedIndex].value;
  }
  
  field = $.id('filter-search');
  if (field.value) {
    filter.search = field.value;
  }
  
  hash = [];
  
  for (key in filter) {
    hash.push(key + '=' + filter[key]);
  }
  
  if (hash[0]) {
    location.search = APP.root + '&' + hash.join('&');
  }
  else {
    location.search = APP.root;
  }
};

/**
 * Notifications
 */
APP.messageTimeout = null;

APP.showMessage = function(msg, type, timeout) {
  var el;
  
  APP.hideMessage();
  
  el = document.createElement('div');
  el.id = 'feedback';
  el.title = 'Dismiss';
  el.innerHTML = '<span class="feedback-' + type + '">' + msg + '</span>';
  
  $.on(el, 'click', APP.hideMessage);
  
  document.body.appendChild(el);
  
  if (timeout) {
    APP.messageTimeout = setTimeout(APP.hideMessage, timeout);
  }
};

APP.hideMessage = function() {
  var el = $.id('feedback');
  
  if (el) {
    if (APP.messageTimeout) {
      clearTimeout(APP.messageTimeout);
      APP.messageTimeout = null;
    }
    
    $.off(el, 'click', APP.hideMessage);
    
    document.body.removeChild(el);
  }
};

APP.error = function(msg, timeout) {
  if (timeout === undefined) {
    timeout = 5000;
  }
  APP.showMessage(msg || 'Something went wrong', 'error', 5000);
};

APP.notify = function(msg, timeout) {
  if (timeout === undefined) {
    timeout = 3000;
  }
  APP.showMessage(msg, 'notify', timeout);
};

/**
 * Event handlers
 */
APP.onClick = function(e) {
  var t, cmd, tid;
  
  if (e.which != 1 || e.ctrlKey || e.altKey || e.shiftKey || e.metaKey) {
    return;
  }
  
  if ((t = e.target) == document) {
    return;
  }
  
  if ((cmd = t.getAttribute('data-cmd')) && (cmd = APP.clickCommands[cmd])) {
    e.stopPropagation();
    cmd(t, e);
  }
};

APP.onKeyUp = function(e) {
  var id, score, startKey, el = e.target;
  
  if (el.nodeName == 'TEXTAREA' || el.nodeName == 'INPUT') {
    return;
  }
  
  if (e.altKey || e.shiftKey || e.ctrlKey || e.metaKey) {
    return;
  }
  
  if (e.keyCode == 83) {
    e.preventDefault();
    e.stopPropagation();
    
    location.href = location.href;
    
    return;
  }
  
  if (e.keyCode >= APP.keyStartCode && e.keyCode <= APP.keyStartCode + 4) {
    startKey = APP.keyStartCode;
  }
  else if (e.keyCode >= APP.keyStartCodeNum && e.keyCode <= APP.keyStartCodeNum + 4) {
    startKey = APP.keyStartCodeNum;
  }
  else {
    return;
  }
  
  e.preventDefault();
  e.stopPropagation();
  
  score = e.keyCode - startKey + 1;
  
  if (el = $.id('active-score')) {
    el.id = '';
    
    if (score == +el.getAttribute('data-score')) {
      APP.rateApplication(el);
      return;
    }
  }
  
  if (el = $.qs('.score-btn[data-score="' + score + '"]')) {
    el.id = 'active-score';
  }
  else {
    console.log('Invalid score');
  }
};

APP.onKeyDown = function(e) {
  var id, score, el = e.target;
  
  if (el.nodeName == 'TEXTAREA' || el.nodeName == 'INPUT') {
    return;
  }
  
  if (e.altKey || e.shiftKey || e.ctrlKey || e.metaKey) {
    return;
  }
  
  if (e.keyCode < APP.keyStartCode || e.keyCode > APP.keyStartCode + 4) {
    return;
  }
  
  e.preventDefault();
  e.stopPropagation();
};

/**
 * Utils
 */
APP.onXHRError = function() {
  var el;
  
  if (this.type) {
    APP.xhr[this.type] = null;
  }
  
  if (this.id) {
    el = APP.getItemNode(this.id);
    $.removeClass(el, 'processing');
  }
  
  APP.error('Something went wrong');
};

APP.parseResponse = function(data) {
  try {
    return JSON.parse(data);
  }
  catch (e) {
    return {
      status: 'error',
      message: 'Something went wrong (' + e.toString() + ')'
    };
  }
};

APP.getItemNode = function(id) {
  return $.id('app-' + id);
};

APP.getItemUID = function(el) {
  var uid = el.id.split('-');
  
  if (uid[0] == 'app') {
    return uid[1];
  }
  
  return null;
};

/**
 * UI Panels
 */
APP.createPanel = function(id, html, attributes) {
  var attr, panel, marginTop;
  
  panel = document.createElement('div');
  panel.id = id + '-panel';
  
  for (attr in attributes) {
    panel.setAttribute(attr, attributes[attr]);
  }
  
  $.addClass(panel, 'panel');
  
  if (html) {
    panel.innerHTML = html;
  }
  
  return panel;
};

APP.showPanel = function(id, html, title, attributes) {
  var previous, panel, content;
  
  ImageHover.hide();
  
  if (previous = APP.panelStack.lastElementChild) {
    previous.style.display = 'none';
  }
  
  if (!$.hasClass(APP.panelStack, 'backdrop')) {
    $.addClass(APP.panelStack, 'backdrop');
  }
  
  if (title) {
    html = '<div class="panel-header">'
      + '<span data-cmd="shift-panel" class="button clickbox">&times;</span>'
      + '<h3>' + title + '</h3>'
    + '</div>' + (html || '');
  }
  
  panel = APP.createPanel(id, html, attributes);
  APP.panelStack.appendChild(panel);
  
  if (content = $.cls('panel-content', panel)[0]) {
    content.focus();
    content.style.maxHeight =
      ($.docEl.clientHeight - content.getBoundingClientRect().top * 2) + 'px';
  }
};

APP.isPanelStackEmpty = function() {
  var i, panel, nodes;
  
  nodes = APP.panelStack.children;
  
  for (i = 0; panel = nodes[i]; ++i) {
    if (!panel.hasAttribute('data-processing')) {
      return false;
    }
  }
  
  return true;
};

APP.getPreviousPanel = function() {
  var i, panel, nodes;
  
  nodes = APP.panelStack.children;
  
  for (i = nodes.length - 1; panel = nodes[i]; i--) {
    if (panel.style.display == 'none' && !panel.hasAttribute('data-processing')) {
      return panel;
    }
  }
  
  return null;
};

APP.closePanel = function(id) {
  var previous, panel;
  
  if (panel = APP.getPanel(id)) {
    if (!panel.hasAttribute('data-processing')) {
      if (previous = APP.getPreviousPanel()) {
        previous.style.display = 'block';
      }
      else {
        $.removeClass(APP.panelStack, 'backdrop');
      }
    }
    
    APP.panelStack.removeChild(panel);
  }
};

APP.hidePanel = function(id) {
  var previous, panel;
  
  if (panel = APP.getPanel(id)) {
    if (previous = APP.getPreviousPanel()) {
      previous.style.display = 'block';
    }
    else {
      $.removeClass(APP.panelStack, 'backdrop');
    }
    
    panel.style.display = 'none';
    panel.setAttribute('data-processing', '1');
  }
};

APP.shiftPanel = function() {
  var cb, panel = APP.panelStack.lastElementChild;
  
  if (!panel) {
    return;
  }
  
  if (cb = panel.getAttribute('data-close-cb')) {
    APP['close' + cb]();
  }
  else {
    APP.closePanel(APP.getPanelId(panel));
  }
};

APP.getPanelId = function(el) {
  return el.id.split('-').slice(0, -1).join('-');
};

APP.getPanel = function(id) {
  return $.id(id + '-panel');
};

APP.showPanelError = function(id, msg) {
  var panel, cnt;
  
  panel = $.id(id + '-panel');
  
  if (!panel) {
    return;
  }
  
  cnt = $.cls('panel-content', panel)[0];
  
  if (!cnt) {
    return;
  }
  
  cnt.innerHTML = '<div class="panel-error">' + msg + '</div>';
};

/**
 * Expand/collapse applications
 */
APP.onToggleExpandClick = function(button) {
  var cnt = button.parentNode.parentNode;
  
  if ($.hasClass(cnt, 'expanded')) {
    $.removeClass(cnt, 'expanded');
    button.textContent = 'Expand';
    
    if (cnt.offsetTop < window.pageYOffset) {
      cnt.scrollIntoView();
    }
  }
  else {
    $.addClass(cnt, 'expanded');
    button.textContent = 'Collapse';
  }
};

/**
 * Accept
 */
APP.onAcceptClick = function(button) {
  var id, el;
  
  if (!confirm('Are you sure?')) {
    return;
  }
  
  el = button.parentNode.parentNode;
  
  if (id = APP.getItemUID(el)) {
    if ($.hasClass(el, 'processing') || $.hasClass(el, 'disabled')) {
      return;
    }
    
    $.addClass(el, 'processing');
    
    APP.acceptApplication(id);
  }
};

APP.acceptApplication = function(id) {
  $.xhr('POST', '?',
    {
      onload: APP.onApplicationAccepted,
      onerror: APP.onXHRError,
      id: id 
    },
    {
      action: 'accept',
      id: id,
      '_tkn': $.getToken()
    }
  );
};

APP.onApplicationAccepted = function() {
  var resp, el;
  
  resp = APP.parseResponse(this.responseText);
  
  el = APP.getItemNode(this.id);
  
  $.removeClass(el, 'processing');
  
  if (resp.status === 'success') {
    $.addClass(el, 'disabled');
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Send orientation email
 */
APP.onSendOrientationClick = function(button) {
  var id, el;
  
  if (!confirm('Are you sure?')) {
    return;
  }
  
  el = button.parentNode.parentNode;
  
  if (id = APP.getItemUID(el)) {
    if ($.hasClass(el, 'processing') || $.hasClass(el, 'disabled')) {
      return;
    }
    
    $.addClass(el, 'processing');
    
    APP.sendOrientation(id);
  }
};

APP.sendOrientation = function(id) {
  $.xhr('POST', '?',
    {
      onload: APP.onOrientationSent,
      onerror: APP.onXHRError,
      id: id 
    },
    {
      action: 'send_orientation',
      id: id,
      '_tkn': $.getToken()
    }
  );
};

APP.onOrientationSent = function() {
  var resp, el;
  
  resp = APP.parseResponse(this.responseText);
  
  el = APP.getItemNode(this.id);
  
  $.removeClass(el, 'processing');
  
  if (resp.status === 'success') {
    $.addClass(el, 'disabled');
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Reject
 */
APP.onRejectClick = function(button) {
  var el, id;
  
  el = button.parentNode.parentNode;
  
  if (id = APP.getItemUID(el)) {
    if ($.hasClass(el, 'processing') || $.hasClass(el, 'disabled')) {
      return;
    }
    
    $.addClass(el, 'processing');
    
    APP.rejectApplication(id);
  }
};

APP.rejectApplication = function(id) {
  $.xhr('POST', '?',
    {
      onload: APP.onApplicationRejected,
      onerror: APP.onXhrError,
      id: id
    },
    {
      action: 'reject',
      id: id,
      '_tkn': $.getToken()
    }
  );
};

APP.onApplicationRejected = function() {
  var resp, el;
  
  resp = APP.parseResponse(this.responseText);
  
  el = APP.getItemNode(this.id);
  
  $.removeClass(el, 'processing');
  
  if (resp.status === 'success') {
    $.addClass(el, 'disabled');
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Create account
 */
APP.onCreateAccountClick = function(button) {
  var el, id, node, defaultUsername, defaultBoards, username, board, otp;
  
  el = button.parentNode.parentNode;
  
  if (id = APP.getItemUID(el)) {
    if ($.hasClass(el, 'processing') || $.hasClass(el, 'disabled')) {
      return;
    }
    
    node = APP.getItemNode(id);
    
    defaultUsername = $.cls('app-username', node)[0].textContent;
    defaultBoards = $.cls('app-boards', node)[0].textContent.split(' ');
    
    username = prompt('Select username:', defaultUsername);
    
    if (!username) {
      return;
    }
    
    if (defaultUsername === username) {
      username = null;
    }
    
    board = prompt('Select board(s):', defaultBoards[0]);
    
    if (!board) {
      return;
    }
    
    otp = prompt('One-time Password');
    
    if (!otp) {
      return;
    }
    
    if (defaultBoards[0] === board) {
      board = null;
    }
    
    $.addClass(el, 'processing');
    
    APP.createAccount(id, username, board, otp);
  }
};

APP.createAccount = function(id, username, board, otp) {
  var data = {
    action: 'create_account',
    id: id,
    otp: otp,
    '_tkn': $.getToken()
  };
  
  if (username) {
    data.username = username;
  }
  
  if (board) {
    data.board = board;
  }
  
  $.xhr('POST', '?',
    {
      onload: APP.onAccountCreated,
      onerror: APP.onXhrError,
      id: id
    },
    data
  );
};

APP.onAccountCreated = function() {
  var resp, el;
  
  resp = APP.parseResponse(this.responseText);
  
  el = APP.getItemNode(this.id);
  
  $.removeClass(el, 'processing');
  
  if (resp.status === 'success') {
    $.addClass(el, 'disabled');
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Rate
 */
APP.rateApplication = function(button) {
  var id, score; 
  
  if (APP.xhr.rate) {
    return;
  }
  
  APP.notify('Processing', false);
  
  id = button.parentNode.parentNode.parentNode.id.split('-')[1];
  score = button.getAttribute('data-score');
  
  APP.xhr.rate = $.xhr('GET', '?action=rate&id=' + id + '&score=' + score, {
    onload: APP.onRateLoaded,
    onerror: APP.onXHRError,
    type: 'rate'
  });
};

APP.onRateLoaded = function() {
  var resp = APP.parseResponse(this.responseText);
  
  APP.xhr.rate = null;
  
  if (resp.status == 'error') {
    APP.error(resp.message);
    return;
  }
  
  location.href = location.href;
};

APP.init();
