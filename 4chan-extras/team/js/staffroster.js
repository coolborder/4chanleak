var APP = {};

APP.init = function() {
  $.on(document, 'DOMContentLoaded', APP.run);
  
  this.xhr = {};
  
  this.clickCommands = {
    'update-boards': APP.onUpdateBoardsClick,
    'update-flags': APP.onUpdateFlagsClick,
    'update-email': APP.onUpdateEmailClick,
    'promote-janitor': APP.onPromoteJanitorClick,
    'remove-janitor': APP.onRemoveJanitorClick,
    'sort-table': APP.onSortTableClick
  };
  
  if (window.AdminCore) {
    this.clickCommands['toggle-cat'] = AdminCore.onToggleCatClick;
  }
  
  $.on(document, 'click', APP.onClick);
};

APP.run = function() {
  var el;
  
  if (el = $.id('mod-ctrl')) {
    $.on(el, 'change', APP.onModBRScoreChange);
  }
};

// ---

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
  return $.id('item-' + id);
};

APP.getItemUID = function(el) {
  var uid;
  
  if ((uid = el.id.split('-')).length == 2) {
    return +uid[1];
  }
  
  return null;
};

/**
 * Event handlers
 */
APP.onClick = function(e) {
  var t, cmd;
  
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

APP.onXhrError = function() {
  APP.error('Something went wrong');
};

APP.onSortTableClick = function(btn) {
  var tbody, asc, cid, rm, add;
  
  asc = !btn.classList.contains('sorted-asc');
  
  cid = +btn.getAttribute('data-cid');
  
  tbody = btn.closest('table').getElementsByTagName('TBODY')[0];
  
  Array.from(tbody.querySelectorAll('tr'))
    .sort(function(a, b) {
      a = a.children[cid];
      b = b.children[cid];
      
      if (a.hasAttribute('data-ival')) {
        a = +a.getAttribute('data-ival');
        b = +b.getAttribute('data-ival');
      }
      else {
        a = a.textContent;
        b = b.textContent;
      }
      
      if (asc) {
        return a === b ? 0 : (a < b ? -1 : 1);
      }
      else {
        return a === b ? 0 : (a > b ? -1 : 1);
      }
    })
    .forEach(tr => tbody.appendChild(tr));
  
  Array.from(btn.closest('table').querySelectorAll('.js-sort-btn')).forEach(function(el) {
    el.classList.remove('sorted-asc');
    el.classList.remove('sorted-desc');
  });
  
  if (asc) {
    btn.classList.add('sorted-asc');
  }
  else {
    btn.classList.add('sorted-desc');
  }
};

APP.onModBRScoreChange = function() {
  var i, el, nodes;
  
  nodes = $.cls('mod-row');
  
  for (i = 0; el = nodes[i]; ++i) {
    if (this.checked) {
      $.removeClass(el, 'hidden');
    }
    else {
      $.addClass(el, 'hidden');
    }
  }
};

APP.disableItem = function(id) {
  var el = $.id('item-' + id);
  
  if (!el || $.hasClass(el, 'disabled')) {
    return;
  }
  
  el.lastElementChild.textContent = '';
  $.addClass(el, 'disabled');
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
 * Remove janitor account
 */
APP.onRemoveJanitorClick = function(button) {
  var item, uid, otp, username;
  
  item = button.parentNode.parentNode;
  
  if ($.hasClass(item, 'disabled')) {
    return;
  }
  
  if ($.hasClass(item, 'processing')) {
    APP.error('Already processing.');
    return;
  }
  
  if (uid = APP.getItemUID(item)) {
    username = $.id('item-' + uid).firstElementChild.textContent;
    
    otp = prompt('You are about to remove "' + username + '"\nEnter a 2FA One-Time Password');
    
    if (otp === null) {
      return;
    }
    
    if (!/^[0-9]+$/.test(otp)) {
      APP.error('Invalid OTP');
      return;
    }
    
    $.addClass(item, 'processing');
    APP.removeJanitor(uid, otp);
  }
};

APP.removeJanitor = function(uid, otp) {
  var params = {
    action: 'remove_janitor',
    id: uid,
    otp: otp,
    '_tkn': $.getToken()
  };
  
  $.xhr('POST', '',
    {
      onload: APP.onJanitorRemoved,
      onerror: APP.onXhrError,
      withCredentials: true,
      uid: uid
    },
    params
  );
};

APP.onJanitorRemoved = function() {
  var el, resp;
  
  el = APP.getItemNode(this.uid);
  
  $.removeClass(el, 'processing');
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    $.addClass(el, 'disabled');
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Update mod flags
 */
APP.onUpdateFlagsClick = function(button) {
  var item, uid, flags;
  
  item = button.parentNode.parentNode;
  
  if ($.hasClass(item, 'disabled')) {
    return;
  }
  
  if ($.hasClass(item, 'processing')) {
    APP.error('Already processing.');
    return;
  }
  
  if (uid = APP.getItemUID(item)) {
    flags = prompt(
      'Comma separated list of flags',
      $.id('flags-' + uid).textContent
    );
    
    if (flags === null) {
      return;
    }
    
    if (!/^[a-z0-9,]+$/.test(flags)) {
      APP.error('Invalid flag list.');
      return;
    }
    
    $.addClass(item, 'processing');
    
    APP.updateFlags(uid, flags);
  }
};

APP.updateFlags = function(uid, flags) {
  var params = {
    action: 'update_flags',
    id: uid,
    flags: flags,
    '_tkn': $.getToken()
  };
  
  $.xhr('POST', '',
    {
      onload: APP.onFlagsUpdated,
      onerror: APP.onXhrError,
      withCredentials: true,
      uid: uid
    },
    params
  );
};

APP.onFlagsUpdated = function() {
  var el, resp;
  
  el = APP.getItemNode(this.uid);
  
  $.removeClass(el, 'processing');
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    $.id('flags-' + this.uid).textContent = resp.data.flags;
  }
  else {
    APP.error(resp.message);
  }
};


/**
 * Update boards
 */
APP.onUpdateBoardsClick = function(button) {
  var item, uid, boards;
  
  item = button.parentNode.parentNode;
  
  if ($.hasClass(item, 'disabled')) {
    return;
  }
  
  if ($.hasClass(item, 'processing')) {
    APP.error('Already processing.');
    return;
  }
  
  if (uid = APP.getItemUID(item)) {
    boards = prompt(
      'Comma separated list of boards or "all"',
      $.id('boards-' + uid).textContent
    );
    
    if (boards === null) {
      return;
    }
    
    if (boards !== '' && /[^a-z0-9,]/.test(boards)) {
      APP.error('Invalid boardlist.');
      return;
    }
    
    $.addClass(item, 'processing');
    APP.updateBoards(uid, boards);
  }
};

APP.updateBoards = function(uid, boards) {
  var params = {
    action: 'update_boards',
    id: uid,
    boards: boards,
    '_tkn': $.getToken()
  };
  
  $.xhr('POST', '',
    {
      onload: APP.onBoardsUpdated,
      onerror: APP.onXhrError,
      withCredentials: true,
      uid: uid
    },
    params
  );
};

APP.onBoardsUpdated = function() {
  var el, resp;
  
  el = APP.getItemNode(this.uid);
  
  $.removeClass(el, 'processing');
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    $.id('boards-' + this.uid).textContent = resp.data.boards;
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Update email
 */
APP.onUpdateEmailClick = function(button) {
  var item, uid, old_email, new_email, otp;
  
  item = button.parentNode.parentNode;
  
  if ($.hasClass(item, 'disabled')) {
    return;
  }
  
  if ($.hasClass(item, 'processing')) {
    APP.error('Already processing.');
    return;
  }
  
  if (uid = APP.getItemUID(item)) {
    old_email = prompt('Current E-mail address');
    
    if (old_email === null) {
      return;
    }
    
    if (!/@/.test(old_email)) {
      APP.error('Invalid email.');
      return;
    }
    
    new_email = prompt('New E-mail address');
    
    if (new_email === null) {
      return;
    }
    
    if (!/@/.test(new_email)) {
      APP.error('Invalid email.');
      return;
    }
    
    otp = prompt('2FA One-Time Password');
    
    if (otp === null) {
      return;
    }
    
    if (!/^[0-9]+$/.test(otp)) {
      APP.error('Invalid OTP');
      return;
    }
    
    $.addClass(item, 'processing');
    
    APP.updateEmail(uid, old_email, new_email, otp);
  }
};

APP.updateEmail = function(uid, old_email, new_email, otp) {
  var params = {
    action: 'update_email',
    id: uid,
    old_email: old_email,
    new_email: new_email,
    otp: otp,
    '_tkn': $.getToken()
  };
  
  $.xhr('POST', '',
    {
      onload: APP.onEmailUpdated,
      onerror: APP.onXhrError,
      withCredentials: true,
      uid: uid
    },
    params
  );
};

APP.onEmailUpdated = function() {
  var el, resp;
  
  el = APP.getItemNode(this.uid);
  
  $.removeClass(el, 'processing');
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    APP.notify('Done');
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Promote janitor to mod
 */
APP.onPromoteJanitorClick = function(button) {
  var item, uid, old_email, new_email, otp, username;
  
  item = button.parentNode.parentNode;
  
  if ($.hasClass(item, 'disabled')) {
    return;
  }
  
  if ($.hasClass(item, 'processing')) {
    APP.error('Already processing.');
    return;
  }
  
  if (uid = APP.getItemUID(item)) {
    username = $.cls('js-username', item)[0].textContent;
    
    if (!confirm('Promote ' + username + ' to moderator? A 2FA OTP will be asked in the next step.')) {
      return;
    }
    
    otp = prompt('2FA One-Time Password');
    
    if (otp === null) {
      return;
    }
    
    if (!/^[0-9]+$/.test(otp)) {
      APP.error('Invalid OTP');
      return;
    }
    
    $.addClass(item, 'processing');
    
    APP.promoteJanitor(uid, otp);
  }
};

APP.promoteJanitor = function(uid, otp) {
  var params = {
    action: 'promote_janitor',
    id: uid,
    otp: otp,
    '_tkn': $.getToken()
  };
  
  $.xhr('POST', '',
    {
      onload: APP.onJanitorPromoted,
      onerror: APP.onXhrError,
      withCredentials: true,
      uid: uid
    },
    params
  );
};

APP.onJanitorPromoted = function() {
  var el, resp;
  
  el = APP.getItemNode(this.uid);
  
  $.removeClass(el, 'processing');
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    APP.notify("Done. " + resp.data.username
      + " now needs to update their password to complete the process.", 6000);
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Init
 */
APP.init();
