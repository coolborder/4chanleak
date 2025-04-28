'use strict';

var APP = {
  PASS_NONE: 0,
  PASS_SAME: 1,
  PASS_OTHER: 2
};

APP.init = function() {
  this.settings = this.getSettings();
  
  this.xhr = {};
  
  this.focusedAppeal = null;
  
  this.clickCommands = {
    'refresh': APP.refreshAppeals,
    'details' : APP.onDetailsClick,
    'contact' : APP.onContactClick,
    'accept' : APP.onAcceptClick,
    'deny' : APP.onDenyClick,
    'edit' : APP.onEditClick,
    'edit-deny' : APP.onEditDenyClick,
    'edit-cancel': APP.closeEditForm,
    'edit-preset': APP.onEditPresetClick,
    'toggle-checked': APP.onCheckboxClick,
    'show-settings': APP.showSettings,
    'save-settings': APP.onSaveSettingsClick,
    'focus': APP.onFocusClick,
    'shift-panel': APP.shiftPanel
  };
  
  this.delayedJob = null;
  this.panelStack = null;
  
  this.currentCount = 0;
  
  Keybinds.init(this);
  
  Tip.init();
  
  $.on(document, 'click', APP.onClick);
  $.on(document, 'DOMContentLoaded', APP.run);
  $.on(window, 'beforeunload', APP.onBeforeUnload);
};

APP.run = function() {
  $.off(document, 'DOMContentLoaded', APP.run);
  
  if (APP.settings.enableKeybinds) {
    if (APP.settings.keyRemap) {
      Keybinds.remap(APP.settings.keyRemap);
    }
    Keybinds.enable();
  }
  
  $.on($.id('edit-len-field'), 'keyup', APP.onEditLengthChange);
  
  APP.currentCount = $.id('items').children.length;
  
  APP.panelStack = $.id('panel-stack');
};

APP.derefer = function(url) {
  return 'https://www.4chan.org/derefer?url=' + url;
};

// ---

APP.addCommands = function(cmds) {
  var key;
  
  for (key in cmds) {
    APP.clickCommands[key] = cmds[key];
  }
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
  return $.id('appeal-' + id);
};

APP.getItemUID = function(el) {
  var uid = el.id.split('-');
  
  if (uid[0] == 'appeal') {
    return uid[1];
  }
  
  return null;
};

APP.formatHost = function(host) {
  var bits = host.split(/\./);
  return (bits.length > 3 ? '*.' : '') + bits.slice(-3).join('.');
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
    e.preventDefault();
    e.stopPropagation();
    cmd(t, e);
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

APP.disableItem = function(el) {
  if ($.hasClass(el, 'disabled')) {
    return;
  }
  
  $.addClass(el, 'disabled');
  APP.currentCount--;
  APP.setPageTitle(APP.currentCount);
};

// ---

APP.onCheckboxClick = function(button) {
  var klass = 'checked', check = '✔';
  
  if ($.hasClass(button, klass)) {
    $.removeClass(button, klass);
    button.textContent = '';
  }
  else {
    $.addClass(button, klass);
    button.textContent = check;
  }
};

APP.setPageTitle = function(count) {
  if (count < 0) {
  	APP.currentCount = count = 0;
  }
  
  document.title = 'Appeals (' + count + ')';
};

APP.refreshAppeals = function() {
  window.location = window.location;
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
  
  $.addClass($.docEl, 'no-scroll');
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
        $.removeClass($.docEl, 'no-scroll');
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

APP.error = function(msg) {
  APP.showMessage(msg || 'Something went wrong', 'error', 5000);
};

APP.notify = function(msg) {
  APP.showMessage(msg, 'notify', 3000);
};

/**
 * Settings
 */
APP.settingsList = {
  enableKeybinds: [ 'Enable keyboard shortcuts', true ],
};

APP.getSettings = function() {
  var key, settings, keyRemap;
  
  if (settings = $.getItem('appeals-settings')) {
    settings = JSON.parse(settings);
    
  }
  else {
    settings = {};
  }
  
  for (key in APP.settingsList) {
    if (settings[key] === undefined) {
      settings[key] = APP.settingsList[key][1];
    }
  }
  
  if (keyRemap = $.getItem('appeals-keyremap')) {
    settings.keyRemap = JSON.parse(keyRemap);
  }
  
  return settings;
};

APP.saveSettings = function(settings, keyMap) {
  var i, json, clear;
  
  clear = true;
  
  for (i in settings) {
    json = JSON.stringify(settings);
    $.setItem('appeals-settings', json);
    clear = false;
    break;
  }
  
  if (clear) {
    $.removeItem('appeals-settings');
  }
  
  if (keyMap) {
    json = JSON.stringify(keyMap);
    $.setItem('appeals-keyremap', json);
  }
  else {
    $.removeItem('appeals-keyremap');
  }
};

APP.onSaveSettingsClick = function() {
  var i, el, settings, panel, opts, nodes, keyRemap, keyMap,
    fromCode, toCode;
  
  settings = {};
  
  panel = APP.getPanel('settings');
  
  opts = $.cls('option-item', panel);
  
  for (i = 0; el = opts[i]; ++i) {
    settings[el.getAttribute('data-key')] = $.hasClass(el, 'checked');
  }
  
  Keybinds.disable();
  
  nodes = $.tag('kbd', panel);
  keyRemap = [];
  keyMap = [];
  
  for (i = 0; el = nodes[i]; ++i) {
    if (toCode = +el.getAttribute('data-remap')) {
      fromCode = +el.getAttribute('data-id');
      
      if (fromCode != toCode) {
        keyMap.push([ fromCode, toCode ]);
      }
      
      keyRemap.push([ fromCode, toCode ]);
    }
    
  }
  
  if (keyRemap.length > 0) {
    if (!Keybinds.remap(keyRemap, true)) {
      return;
    }
  }
  
  if (keyMap.length === 0) {
    keyMap = null;
  }
  
  if (settings.enableKeybinds) {
    Keybinds.enable();
  }
  
  APP.settings = settings;
  
  APP.saveSettings(settings, keyMap);
  
  APP.closeSettings();
};

APP.showSettings = function() {
  var i, label, key, html;
  
  if (APP.getPanel('settings')) {
    APP.closeSettings();
    return;
  }
  
  html = '<div class="panel-content"><h4>Options</h4>'
    + '<ul class="options-set">';
  
  for (key in APP.settingsList) {
    html += '<li><span data-cmd="toggle-checked"'
      + ' data-key="' + key + '"'
      + ' class="option-item button clickbox'
      + (APP.settings[key] ? ' checked">✔' : '">')
      + '</span> ' + APP.settingsList[key][0] + '</li>';
  }
  
  html += '</ul><h4>Keyboard Shortcuts (click key icon to change)</h4><ul class="options-set">';
  
  for (key in Keybinds.labels) {
    label = Keybinds.labels[key];
    html += '<li><kbd data-cmd="prompt-key" data-id="'
      + key + '"' + (label[2] ? ('data-remap="' + label[2] + '"') : '') + '>'
      + label[0] + '</kbd> '
      + label[1] + '</li>';
  }
  
  html += '</div><div class="panel-footer">'
      + '<span data-cmd="save-settings" class="button">Save</span>'
    + '</div>';
  
  APP.showPanel('settings', html, 'Settings');
};

APP.closeSettings = function() {
  APP.closePanel('settings');
};

/**
 * Focusing
 */
APP.onFocusClick = function(el) {
  APP.focusItem(el.parentNode);
};

APP.focusNext = function() {
  var el, node;
  
  node = null;
  
  if (el = APP.focusedAppeal) {
    while (el = el.nextElementSibling) {
      if (!$.hasClass(el, 'disabled') && !$.hasClass(el, 'processing')) {
        node = el;
        break;
      }
    }
  }
  
  APP.focusItem(node || $.id('items').firstElementChild);
};

APP.focusPrevious = function() {
  var el, node;
  
  node = null;
  
  if (el = APP.focusedAppeal) {
    while (el = el.previousElementSibling) {
      if (!$.hasClass(el, 'disabled') && !$.hasClass(el, 'processing')) {
        node = el;
        break;
      }
    }
    
    APP.focusItem(node);
  }
};

APP.focusItem = function(el) {
  var cnt, rect, focusMargin, focused;
  
  focusMargin = 10;
  
  ImageHover.hide();
  
  focused = APP.getFocusedItem();
  
  if (focused) {
    $.removeClass(focused, 'focused');
  }
  
  if (!el) {
    APP.setFocusedItem(null);
    return;
  }
  
  $.addClass(el, 'focused');
  
  rect = el.getBoundingClientRect();
  
  if (rect.top < 0 || rect.bottom > $.docEl.clientHeight) {
    window.scrollBy(0, rect.top - focusMargin);
  }
  
  APP.setFocusedItem(el);
};

APP.setFocusedItem = function(el) {
  APP.focusedAppeal = el;
};

APP.getFocusedItem = function() {
  return APP.focusedAppeal;
};

APP.setDisabled = function(uid) {
  var el;
  
  el = $.id('appeal-' + uid);
  
  if (!el) {
    return;
  }
  
  $.removeClass(el, 'processing');
  
  if ($.hasClass(el, 'disabled')) {
    return;
  }
  
  APP.currentCount--;
  APP.setPageTitle(APP.currentCount);
  
  $.addClass(el, 'disabled');
};

APP.setProcessing = function(uid) {
  var el;
  
  if (el = $.id('appeal-' + uid)) {
    $.addClass(el, 'processing');
  }
};

APP.unsetProcessing = function(uid) {
  var el;
  
  if (el = $.id('appeal-' + uid)) {
    $.removeClass(el, 'processing');
  }
};

APP.onXhrError = function() {
  APP.error('Something went wrong');
};

/**
 * Ban summary tooltip
 */
APP.showBanTip = function(t) {
  var ban_summary, ban_tip;
  
  ban_summary = $.tag('SCRIPT', t.parentNode)[0];
  
  if (!ban_summary) {
    return null;
  }
  
  ban_summary = JSON.parse(ban_summary.text);
  
  ban_tip = [];
  
  if (ban_summary.recent_bans > 0) {
    ban_tip.push(ban_summary.recent_bans + ' ban'
      + $.pluralise(ban_summary.recent_bans));
  }
  
  if (ban_summary.recent_warns > 0) {
    ban_tip.push(ban_summary.recent_warns + ' warning'
      + $.pluralise(ban_summary.recent_warns));
  }
  
  if (ban_summary.recent_days > 0) {
    ban_tip.push(ban_summary.recent_days + ' day'
      + $.pluralise(ban_summary.recent_days)
      + ' spent banned');
  }
  
  if (!ban_tip.length) {
    ban_tip.push('None');
  }
  
  return '<strong>Past 12 months history</strong><ul class="ban-tip-cnt"><li>'
    + ban_tip.join('</li><li>') + '</li></ul>';
};

/**
 * Details
 */
APP.onDetailsClick = function(button) {
  var appeal, id;
  
  appeal = button.parentNode.parentNode;
  
  if (id = APP.getItemUID(appeal)) {
    APP.showDetails(id);
  }
};

APP.showFocusedDetails = function() {
  var id;
  
  if (APP.focusedAppeal) {
    id = APP.getItemUID(APP.focusedAppeal);
    APP.showDetails(id);
  }
};

APP.showDetails = function(id) {
  var query, html;
  
  APP.closeDetails();
  
  html = '<div class="panel-content" tabindex="-1" id="ban-details">'
      + '<div class="spinner"></div>'
    + '</div>';
  
  APP.showPanel('details', html, 'History', { 'data-close-cb': 'Details' });
  
  query = '?action=details&id=' + id;
  
  APP.xhr.get = $.xhr('GET', query, {
    onload: APP.onDetailsLoaded,
    onerror: APP.onDetailsError
  });
};

APP.closeDetails = function() {
  if (APP.xhr.get) {
    APP.xhr.get.abort();
    APP.xhr.get = null;
  }
  
  APP.closePanel('details');
};

APP.onDetailsLoaded = function() {
  var resp;
  
  APP.xhr.get = null;
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    APP.buildDetails(resp.data);
  }
  else {
    APP.showPanelError('details', resp.message);
  }
};

APP.onDetailsError = function() {
  APP.showPanelError('details', 'Error retrieving details');
  APP.xhr.get = null;
};

APP.getPassStatus = function(status) {
  if (status == APP.PASS_SAME) {
    return '<span data-tip="Same Pass in the appealed ban" class="str-xs d-line pass-status pass-status-'
      + status + '">Pass</span>';
  }
  
  if (status == APP.PASS_OTHER) {
    return '<span data-tip="Different Pass in the appealed ban" class="str-xs pass-status d-line pass-status-'
      + status + '">Pass</span>';
  }
  
  return '';
};

APP.buildDetails = function(data) {
  var i, appeal, ban, cnt, reasons, plea_history, html, hist, scope, aip, apass;
  
  appeal = data.appeal;
  
  cnt = $.id('ban-details');
  
  html = '';
  
  if (appeal.plea_history !== '') {
    plea_history = appeal.plea_history;
    
    html += '<h4>Appeals</h4><table class="history-table items-table"><thead>'
      + '<tr>'
        + '<th>Plea</th>'
        + '<th>Date</th>'
        + '<th>Denied By</th>'
      + '</tr>'
      + '</thead><tbody>';
    
    for (i = 0; hist = plea_history[i]; ++i) {
      html += '<tr>'
          + '<td class="history-plea">' + hist.plea + '</td>'
          + '<td>' + hist.denied_on + '</td>'
          + '<td>' + hist.denied_by + '</td>'
        + '</tr>';
    }
    
    html += '</tbody></table>';
  }
  
  if (data.history.length) {
    aip = '<a data-tip="Detailed IP ban history" target="_blank" href="bans?action=search&amp;ip='
      + data.history[0].host + '">IP</a>';
    
    if (appeal.pass_ban) {
      apass = '<a data-tip="Detailed Pass ban history" target="_blank" href="bans?action=search&amp;pass_ref='
        + appeal.no + '">Pass</a>';
    }
    
    html += '<h4>Bans for this ' + aip + (appeal.pass_ban ? (' and ' + apass) : '') + '</h4><table class="history-table items-table"><thead>'
      + '<tr>'
        + '<th>Board</th>'
        + '<th>Name</th>'
        + '<th>Reason</th>'
        + '<th>Date</th>'
        + '<th>Banned By</th>'
        + '<th></th>'
      + '</tr>'
      + '</thead><tbody>';
    
    for (i = 0; ban = data.history[i]; ++i) {
      reasons = ban.reason.split('<>');
      
      if (ban.ban_end != '0' && (ban.ban_end - ban.ban_start < 1)) {
        scope = '<div class="d-line ban-warn">warn</div>';
      }
      else if (ban.global === '1') {
        scope = '<div class="d-line ban-global">global</div>';
      }
      else {
        scope = '';
      }
      
      html += '<tr>'
          + '<td class="col-board">'
            + (ban.board ? ('<div class="ban-board">/' + ban.board + '/</div>') : '')
            + scope
            + APP.getPassStatus(ban.pass_status)
          + '</td>'
          + '<td class="col-name">'
            + '<div class="user-name">' + ban.name
              + (ban.tripcode ? (' <span class="user-tripcode">' + ban.tripcode + '</span>') : '')
            + '</div>'
          + '</td>'
          + '<td>'
            + '<div class="public-reason">' + reasons[0] + '</div>'
            + (reasons[1] ? ('<div class="private-reason">' + reasons[1] + '</div>') : '')
          + '</td>'
          + '<td class="col-date">'
            + '<div class="ban-start">' + ban.ban_date + '</div><div class="d-line str-xs">' + ban.ban_length + '</div>'
          + '</td>'
          + '<td class="col-name"><div>' + ban.admin + '</div>'
            + (ban.unbannedby ? ('<div data-tip="Unbanned by" class="d-line str-xs str-accept">' + ban.unbannedby + '</div>') : '') + '</td>'
          + '<td class="col-edit">'
               + '<div><a target="_blank" href="/bans?action=update&amp;id=' + ban.no + '">Details</a></div>'
             + (ban.link && ban.post_num !== '0' ? ('<div class="d-line str-xs"><a data-tip="Archive Link" href="' + APP.derefer(ban.link)
              + '" target="_blank">No.' + ban.post_num + '</a></div>') : '')
            + '</td>'
        + '</tr>';
    }
    
    html += '</tbody></table>';
  }
  
  if (html === '') {
    APP.showPanelError('details', 'No previous bans');
    return;
  }
  
  cnt.innerHTML = html;
};

/**
 * Contact
 */
APP.onContactClick = function(button) {
  var appeal, id;
  
  appeal = button.parentNode.parentNode;
  
  if (id = APP.getItemUID(appeal)) {
    APP.showContact(id, button.getAttribute('data-email'));
  }
};

APP.contactFocused = function() {
  var id, button;
  
  if (APP.focusedAppeal) {
    id = APP.getItemUID(APP.focusedAppeal);
    
    button = $.qs('span[data-cmd="contact"]', APP.focusedAppeal);
    
    if (!button) {
      return;
    }
    
    APP.showContact(id, button.getAttribute('data-email'));
  }
};

APP.showContact = function(id, email) {
  var query, html;
  
  APP.closeContact();
  
  html = '<div class="panel-content" tabindex="-1" id="contact-form">'
      + '<div id="contact-email"></div>'
      + '<div><span class="contact-label">From:</span> 4chan Ban Appeals &lt;appeals@4chan.org&gt;</div>'
      + '<div><span class="contact-label">Subject:</span> Your 4chan Ban Appeal ' + id + '</div>'
      + '<div class="contact-label">Message:</div>'
      + '<textarea tabindex="-1" id="contact-message"></textarea>'
      + '<span data-id="' + id
        + '" class="button btn-other" tabindex="1" id="contact-submit">Send</span>'
    + '</div>';
  
  APP.showPanel('contact', html, 'Contact', { 'data-close-cb': 'Contact' });
  
  $.id('contact-message').focus();
  $.id('contact-email').textContent = email;
  $.on($.id('contact-submit'), 'click', APP.onSendClick);
};

APP.closeContact = function() {
  var el = $.id('contact-submit');
  
  if (el) {
    $.off(el, 'click', APP.onSendClick);
    APP.closePanel('contact');
  }
};

APP.onSendClick = function() {
  if ($.id('contact-message').value === '') {
    return APP.error('Message is empty');
  }
  
  $.xhr('POST', '',
    {
      onload: APP.onMailSent,
      onerror: APP.onXHRError
    },
    {
      action: 'contact',
      message: $.id('contact-message').value,
      id: this.getAttribute('data-id'),
      '_tkn': $.getToken()
    }
  );
  
  APP.closeContact();
};

APP.onMailSent = function() {
  var resp;
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    APP.notify('Email sent');
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Accept
 */
APP.acceptFocused = function() {
  var uid;
  
  if (APP.focusedAppeal) {
    uid = APP.getItemUID(APP.focusedAppeal);
    
    $.addClass(APP.focusedAppeal, 'processing');
    
    APP.addDelayedJob(APP.acceptAppeal, uid);
    
    APP.focusNext();
  }
};

APP.onAcceptClick = function(button) {
  var appeal, uid;
  
  appeal = button.parentNode.parentNode;
  
  if (uid = APP.getItemUID(appeal)) {
    $.addClass(appeal, 'processing');
    APP.addDelayedJob(APP.acceptAppeal, uid);
  }
};

APP.acceptAppeal = function(id) {
  $.xhr('POST', '',
    {
      onload: APP.onAppealAccepted,
      onerror: APP.onXhrError,
      id: id
    },
    {
      action: 'accept',
      id: id,
      '_tkn': $.getToken()
    }
  );
};

APP.onAppealAccepted = function() {
  var resp, el;
  
  resp = APP.parseResponse(this.responseText);
  
  el = APP.getItemNode(this.id);
  $.removeClass(el, 'processing');
  
  if (resp.status === 'success') {
    APP.disableItem(el);
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Deny
 */
APP.denyFocused = function() {
  var uid;
  
  if (APP.focusedAppeal) {
    uid = APP.getItemUID(APP.focusedAppeal);
    
    $.addClass(APP.focusedAppeal, 'processing');
    
    APP.addDelayedJob(APP.denyAppeal, uid);
    
    APP.focusNext();
  }
};

APP.onDenyClick = function(button) {
  var appeal, uid;
  
  appeal = button.parentNode.parentNode;
  
  if (uid = APP.getItemUID(appeal)) {
    $.addClass(appeal, 'processing');
    APP.addDelayedJob(APP.denyAppeal, uid);
  }
};

APP.denyAppeal = function(id) {
  $.xhr('POST', '',
    {
      onload: APP.onAppealDenied,
      onerror: APP.onXhrError,
      id: id
    },
    {
      action: 'deny',
      id: id,
      '_tkn': $.getToken()
    }
  );
};

APP.onAppealDenied = function() {
  var resp, el;
  
  resp = APP.parseResponse(this.responseText);
  
  el = APP.getItemNode(this.id);
  $.removeClass(el, 'processing');
  
  if (resp.status === 'success') {
    APP.disableItem(el);
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Edit
 */
APP.closeEditForm = function() {
  $.id('edit-len-field').value = '';
  $.id('edit-id-field').value = '';
  $.addClass($.id('edit-form-cnt'), 'hidden');
};

APP.getDaysDuration = function(start, end) {
  var days, delta;
  
  delta = end - start;
  
  if (delta < 0) {
    return 0;
  }
  
  return 0 | (delta / 86400);
}

APP.onEditClick = function(button) {
  var el, rect, appeal, uid, end;
  
  APP.closeEditForm();
  
  appeal = button.parentNode.parentNode;
  
  uid = APP.getItemUID(appeal);
  
  $.id('edit-id-field').value = uid;
  $.id('edit-len-field').value = appeal.getAttribute('data-len');
  $.id('edit-len-field').setAttribute('data-orig', appeal.getAttribute('data-len'));
  $.id('js-eb-fa').textContent = $.ago(button.getAttribute('data-start'));
  $.id('js-eb-ne').setAttribute('data-start', button.getAttribute('data-start'));
  
  APP.onEditLengthChange();
  
  el = $.id('edit-form-cnt');
  
  $.removeClass(el, 'hidden');
  
  rect = button.getBoundingClientRect();
  
  el.style.top = rect.top - el.offsetHeight + window.pageYOffset - 10 + 'px';
  el.style.right = ($.docEl.clientWidth - rect.right) + 'px';
  
  if (el.offsetTop < window.pageYOffset) {
    el.scrollIntoView(true);
  }
};

APP.onEditPresetClick = function(btn) {
  $.id('edit-len-field').value = btn.getAttribute('data-len');
  APP.onEditLengthChange();
};

APP.onEditLengthChange = function() {
  var el, input, days, start, end;
  
  el = $.id('js-eb-ne');
  input = $.id('edit-len-field');
  
  if (input.value === '') {
    el.textContent = '0';
    return;
  }
  
  start = +el.getAttribute('data-start');
  end = start + (+input.value * 86400);
  
  start = 0 | (Date.now() / 1000);
  
  days = APP.getDaysDuration(start, end);
  
  if (days < 1) {
    $.addClass(el, 'val-deny');
  }
  else {
    $.removeClass(el, 'val-deny');
  }
  
  if (input.value != input.getAttribute('data-orig')) {
    $.addClass(el, 'val-changed');
  }
  else {
    $.removeClass(el, 'val-changed');
  }
  
  el.textContent = days;
};

APP.onEditDenyClick = function(btn) {
  var id, len, appeal;
  
  id = +$.id('edit-id-field').value;
  len = +$.id('edit-len-field').value;
  
  appeal = APP.getItemNode(id);
  
  if (!appeal) {
    APP.closeEditForm();
    return;
  }
  
  if (len == 0) {
    alert('Invalid length.');
    return;
  }
  
  $.addClass(appeal, 'processing');
  
  APP.closeEditForm();
  
  $.xhr('POST', '',
    {
      onload: APP.onAppealDenied,
      onerror: APP.onXhrError,
      id: id
    },
    {
      action: 'deny',
      id: id,
      days: len,
      '_tkn': $.getToken()
    }
  );
};

/**
 * Delayed jobs
 */
APP.delayedJobTimeout = 3000;

APP.addDelayedJob = function() {
  var fn, args;
  
  if (APP.delayedJob) {
    APP.runDelayedJob();
  }
  
  args = Array.prototype.slice.call(arguments);
  
  APP.delayedJob = {
    fn: args.shift(),
    args: args,
    timeOut: setTimeout(APP.runDelayedJob, APP.delayedJobTimeout)
  };
};

APP.runDelayedJob = function() {
  var job = APP.delayedJob;
  
  clearTimeout(job.timeOut);
  
  job.fn.apply(this, job.args);
  
  APP.delayedJob = null;
};

APP.cancelDelayedJob = function() {
  var args, item;
  
  if (!APP.delayedJob) {
    return;
  }
  
  args = APP.delayedJob.args;
  
  clearTimeout(APP.delayedJob.timeOut);
  
  item = APP.getItemNode(args[0]);
  
  $.removeClass(item, 'processing');
  
  if (APP.getFocusedItem()) {
    APP.focusItem(item);
  }
  
  APP.delayedJob = null;
};

/**
 * Keybinds
 */
Keybinds.map = {
  // Esc
  27: APP.shiftPanel,
  // Left arrow
  37: APP.focusPrevious,
  // Right arrow
  39: APP.focusNext,
  // A
  65: APP.acceptFocused,
  // C
  67: APP.contactFocused,
  // D
  68: APP.denyFocused,
  // H
  72: APP.showFocusedDetails,
  // R
  82: APP.refreshAppeals,
  // U
  85: APP.cancelDelayedJob,
};
  
Keybinds.labels = {
  82: [ 'R', 'Refresh page' ],
  39: [ '&#8594;', 'Focus next appeal' ],
  37: [ '&#8592;', 'Focus previous appeal' ],
  27: [ 'Esc', 'Close panel' ],
  65: [ 'A', 'Accept focused' ],
  68: [ 'D', 'Deny focused' ],
  67: [ 'C', 'Contact focused' ],
  72: [ 'H', 'Show ban history' ],
  85: [ 'U', 'Undo last action' ]
};

APP.closeKeyPrompt = function() {
  $.off(document, 'keydown', Keybinds.resolvePrompt);
  
  if (APP.settings.enableKeybinds) {
    Keybinds.enable();
  }
  
  APP.closePanel('key-prompt');
};

/**
 * Init
 */
APP.init();
