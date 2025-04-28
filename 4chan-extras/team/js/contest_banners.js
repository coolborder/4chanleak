'use strict';

var APP = {
  EVENT_TYPES: {
    1: 'Submit',
    2: 'Vote'
  },
  
  init: function() {
    this.xhr = null;
    
    this.preDisableDelay = 3000;
    
    this.clickCommands = {
      'enable': APP.onEnableClick,
      'pre-disable': APP.onPreDisableClick,
      'add-event': APP.onAddEventClick,
      'edit-event': APP.onEditEventClick,
      'pre-del-event': APP.onPreDelEventClick,
      'del-event': APP.onDelEventClick,
      'disable': APP.onDisableClick,
      'preset-live': APP.onPreSetLiveClick,
      'set-live': APP.onSetLiveClick,
      'unset-live': APP.onUnsetLiveClick,
      'dismiss-error': APP.hideMessage,
      'close-panel': APP.onClosePanelClick
    };
    
    Tip.init();
    
    $.on(document, 'DOMContentLoaded', APP.run);
    $.on(document, 'click', APP.onClick);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    $.on($.id('filter-board'), 'change', APP.onBoardChange);
  },
  
  parseResponse: function(data) {
    try {
      return JSON.parse(data);
    }
    catch (e) {
      return {
        status: 'error',
        message: 'Something went wrong.'
      };
    }
  },
  
  onBoardChange: function() {
    var board, field;
    
    field = $.id('filter-board');
    
    if (field.selectedIndex) {
      board = field.options[field.selectedIndex].textContent;
      if (location.search === '') {
        location.search = 'board=' + board;
      }
      else if (/board=[a-z0-9]+/.test(location.search)) {
        location.search = location.search.replace(/board=[a-z0-9]+/, 'board=' + board);
      }
      else {
        location.search += '&board=' + board;
      }
    }
    else {
      location.search = location.search.replace(/&?board=[a-z0-9]+/, '');
    }
  },
  
  onPreDisableClick: function(btn) {
    $.addClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'disable');
    Tip.show(btn, '<span id="del-conf-tip">Confirm</div>');
    setTimeout(APP.resetDisableConfirmBtn, APP.preDisableDelay, btn);
  },
  
  onPreSetLiveClick: function(btn) {
    $.addClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'set-live');
    Tip.show(btn, '<span id="del-conf-tip">Confirm</div>');
    setTimeout(APP.resetDisableConfirmBtn, APP.preDisableDelay, btn);
  },
  
  onPreDelEventClick: function(btn) {
    $.addClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'del-event');
    Tip.show(btn, '<span id="del-conf-tip">Confirm</div>');
    setTimeout(APP.resetDisableConfirmBtn, APP.preDisableDelay, btn);
  },
  
  resetDisableConfirmBtn: function(btn) {
    var el;
    
    if (!btn) {
      return;
    }
    
    if (el = $.id('del-conf-tip')) {
      if (btn.matches ? btn.matches(':hover') : btn.matchesSelector(':hover')) {
        Tip.hide();
      }
    }
    
    $.removeClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'pre-disable');
  },
  
  getItemUID: function(btn) {
    return 0 | btn.parentNode.parentNode.id.split('-')[1];
  },
  
  onDelEventClick: function(btn) {
    var el, uid;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.deleteEvent(uid);
    }
  },
  
  deleteEvent: function(uid) {
    $.xhr('POST', '',
      {
        onload: APP.onItemProcessed,
        onerror: APP.onXhrError,
        withCredentials: true,
        uid: uid,
      },
      '_tkn=' + $.getToken() + '&action=delete_event&id=' + uid
    );
  },
  
  onDisableClick: function(btn) {
    var el, uid;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.setBannerStatus(uid, false);
    }
  },
  
  onEnableClick: function(btn) {
    var el, uid;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.setBannerStatus(uid, true);
    }
  },
  
  onUnsetLiveClick: function(btn) {
    var el, uid;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.setBannerLive(uid, false);
    }
  },
  
  onSetLiveClick: function(btn) {
    var el, uid;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.setBannerLive(uid, true);
    }
  },
  
  setBannerLive: function(uid, enable) {
    $.xhr('POST', '',
      {
        onload: APP.onItemProcessed,
        onerror: APP.onXhrError,
        withCredentials: true,
        uid: uid,
      },
      '_tkn=' + $.getToken() + '&action=' + (enable ? 'set_live' : 'unset_live') + '&id=' + uid
    );
  },
  
  setBannerStatus: function(uid, enable) {
    $.xhr('POST', '',
      {
        onload: APP.onItemProcessed,
        onerror: APP.onXhrError,
        withCredentials: true,
        uid: uid,
      },
      '_tkn=' + $.getToken() + '&action=' + (enable ? 'enable' : 'disable') + '&id=' + uid
    );
  },
  
  onXhrError: function() {
    var el = $.id('item-' + this.uid);
    
    if (!el) {
      return;
    }
    
    $.removeClass(el, 'processing');
    
    Feedback.error();
  },
  
  onItemProcessed: function() {
    var el, resp;
    
    el = $.id('item-' + this.uid);
    
    if (!el) {
      return;
    }
    
    $.removeClass(el, 'processing');
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      $.addClass(el, 'disabled');
    }
    else {
      Feedback.error(resp.message);
    }
  },
  
  onAddEventClick: function(btn, e) {
    e.preventDefault();
    APP.showEventPanel('Create Event');
  },
  
  onEditEventClick: function(btn, e) {
    Feedback.showMessage('Loading...', 'notify');
    
    APP.xhr = $.xhr('GET', '?action=update_event&id=' + APP.getItemUID(btn),
      {
        onload: APP.onEventLoaded,
        onerror: APP.onXhrError,
        withCredentials: true,
      }
    );
  },
  
  onEventLoaded: function() {
    var el, resp;
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      Feedback.hideMessage();
      APP.showEventPanel('Update Event', resp.data);
    }
    else {
      Feedback.error(resp.message);
    }
  },
  
  buildEventForm: function(data) {
    var i, html, types;
    
    data = data || {};
    
    types = '<select autofocus id="form-type">'
    
    for (i in APP.EVENT_TYPES) {
      types += '<option ' + (data.event_type === i ? 'selected ' : '')
        + 'value="' + i + '">' + APP.EVENT_TYPES[i] + '</option>';
    }
    
    types += '</select>';
    
    html = '<form id="event-form"'
      + (data.id ? (' data-id="' + data.id + '"') : '') + 'action=""><table>'
      + '<tr><th>Type</th><td>' + types + '</td>'
      + '<tr><th>Boards</th><td><input id="form-boards" type="text" value="'
        + (data.boards || '') + '"></td></tr>'
      + '<tr><th>Starts on</th><td><input id="form-start" type="text" value="'
        + (data.starts_on || '') + '" placeholder="MM/DD/YY"></td></tr>'
      + '<tr><th>Ends on</th><td><input id="form-end" type="text" value="'
        + (data.ends_on || '') + '" placeholder="MM/DD/YY"></td></tr>'
      + '<tr><th></th><td><button class="button btn-other" type="submit">'
        + (data.id ? 'Update' : 'Create') + '</button></td></tr>'
      + '</form></table>';
    
    return html;
  },
  
  showEventPanel: function(title, data) {
    var el, html;
    
    APP.closeEventPanel();
    
    html = '<div class="panel-header">'
      + '<span data-cmd="close-panel" class="button clickbox">&times;</span>'
      + '<h3>' + title + '</h3>'
      + '</div>' + APP.buildEventForm(data);
    
    el = $.el('div');
    el.id = 'backdrop';
    el.setAttribute('data-cmd', 'close-panel');
    el.className = 'backdrop';
    document.body.appendChild(el);
    
    el = $.el('div');
    el.id = 'panel';
    el.className = 'panel';
    el.innerHTML = html;
    document.body.appendChild(el);
    
    el = $.id('event-form');
    $.on(el, 'submit', APP.onEventFormSubmit);
  },
  
  onClosePanelClick: function() {
    APP.closeEventPanel();
  },
  
  closeEventPanel: function(keep_backdrop) {
    var el;
    
    if (APP.xhr) {
      APP.xhr.abort();
      APP.xhr = null;
    }
    
    if (el = $.id('event-form')) {
      $.off(el, 'submit', APP.onEventFormSubmit);
    }
    
    if (el = $.id('panel')) {
      el.parentNode.removeChild(el);
    }
    
    if (keep_backdrop) {
      return;
    }
    
    if (el = $.id('backdrop')) {
      el.parentNode.removeChild(el);
    }
  },
  
  onEventFormSubmit: function(e) {
    var data, el;
    
    e.preventDefault();
    
    data = {
       _tkn: $.getToken(),
      action: 'update_event',
      event_type: $.id('form-type').value,
      boards: $.id('form-boards').value,
      starts_on: $.id('form-start').value,
      ends_on: $.id('form-end').value
    }
    
    el = $.id('event-form');
    
    if (el.hasAttribute('data-id')) {
      data.id = el.getAttribute('data-id');
    }
    
    el.disabled = true;
    
    Feedback.showMessage('Processing...', 'notify', 3000);
    
    $.xhr('POST', '',
      {
        onload: APP.onEventUpdated,
        onerror: APP.onXhrError,
        withCredentials: true,
      },
      data
    );
    
    APP.closeEventPanel(true);
  },
  
  onEventUpdated: function() {
    Feedback.hideMessage();
    window.location = window.location;
  },
  
  onClick: function(e) {
    var t, cmd;
    
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
  }
  
};
  
/**
 * Notifications
 */
var Feedback = {
  messageTimeout: null,
  
  showMessage: function(msg, type, timeout) {
    var el;
    
    Feedback.hideMessage();
    
    el = document.createElement('div');
    el.id = 'feedback';
    el.title = 'Dismiss';
    el.innerHTML = '<span class="feedback-' + type + '">' + msg + '</span>';
    
    $.on(el, 'click', Feedback.hideMessage);
    
    document.body.appendChild(el);
    
    if (timeout) {
      Feedback.messageTimeout = setTimeout(Feedback.hideMessage, timeout);
    }
  },
  
  hideMessage: function() {
    var el = $.id('feedback');
    
    if (el) {
      if (Feedback.messageTimeout) {
        clearTimeout(Feedback.messageTimeout);
        Feedback.messageTimeout = null;
      }
      
      $.off(el, 'click', Feedback.hideMessage);
      
      document.body.removeChild(el);
    }
  },
  
  error: function(msg) {
    Feedback.showMessage(msg || 'Something went wrong', 'error', 5000);
  },
  
  notify: function(msg) {
    Feedback.showMessage(msg, 'notify', 3000);
  }
};

APP.init();
