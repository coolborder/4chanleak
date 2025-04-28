'use strict';

var APP = {};

APP.init = function() {
  this.xhr = {};
  
  this.clickCommands = {
    'preview': APP.onPreviewClick,
    'submit' : APP.onSubmitClick,
    'delete' : APP.onDeleteClick,
  };
  
  $.on(document, 'click', APP.onClick);
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

APP.onXhrError = function() {
  APP.error('Something went wrong');
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
 * Preview
 */
APP.onPreviewClick = function(button) {
  var data;
  
  data = $.id('post-msg').value;
  
  if (data === '') {
    APP.error('Message cannot be empty');
    return;
  }
  
  APP.notify('Processing', false);
  
  APP.xhr.preview = $.xhr('POST', '',
    {
      onload: APP.onPreviewLoaded,
      onerror: APP.onXhrError
    },
    {
      action: 'preview',
      content: data,
      '_tkn': $.getToken()
    }
  );
};

APP.onPreviewLoaded = function() {
  var resp;
  
  APP.xhr.preview = null;
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    APP.hideMessage();
    $.id('preview').innerHTML = resp.data.message;
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Submit
 */
APP.onSubmitClick = function(button) {
  var data;
  
  data = $.id('post-msg').value;
  
  if (data === '') {
    APP.error('Message cannot be empty');
    return;
  }
  
  APP.notify('Processing', false);
  
  APP.xhr.submit = $.xhr('POST', '',
    {
      onload: APP.onSubmitLoaded,
      onerror: APP.onXhrError
    },
    {
      action: 'submit',
      content: data,
      '_tkn': $.getToken()
    }
  );
};

APP.onSubmitLoaded = function() {
  var resp;
  
  APP.xhr.submit = null;
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    APP.notify('Done');
    location.href = location.href;
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Delete
 */
APP.onDeleteClick = function(button) {
  var id;
  
  if (!confirm('Sure?')) {
    return;
  }
  
  id = button.getAttribute('data-id');
  
  $.xhr('POST', '',
    {
      onload: APP.onDeleteLoaded,
      onerror: APP.onXhrError,
      id: id
    },
    {
      action: 'delete',
      id: id,
      '_tkn': $.getToken()
    }
  );
};

APP.onDeleteLoaded = function() {
  var resp;
  
  resp = APP.parseResponse(this.responseText);
  
  if (resp.status === 'success') {
    APP.disableItem(this.id);
  }
  else {
    APP.error(resp.message);
  }
};

/**
 * Init
 */
APP.init();
