'use strict';

var APP = {
  init: function() {
    this.xhr = null;
    
    this.clickCommands = {
      'toggle': APP.onToggleClick,
      'delete': APP.onDeleteClick,
      'toggle-active': APP.onToggleActiveClick,
      'toggle-all': APP.onToggleAllClick,
      'dismiss-error': APP.hideStatusMessage,
      'toggle-copy': APP.onToggleCopyClick,
      'escape-html': APP.onEscapeHtmlClick,
      'escape-str': APP.onEscapeStrClick,
      'escape-sage': APP.onEscapeSageClick,
    };
    
    Tip.init();
    
    $.on(document, 'click', APP.onClick);
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    var i, nodes, el, page;
    
    APP.uncheckAll();
    
    page = document.body.getAttribute('data-page');
    
    if (page === 'search') {
      $.on($.id('search-form'), 'submit', APP.onSearchSubmit);
    }
    else if (page === 'update') {
      APP.onToggleCopyClick();
    }
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
  
  encodeHtml: function(str) {
    return str.replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  },
  
  showMessage: function(ids) {
    var el, count, params;
    
    APP.hideMessage();
    
    count = ids.length;
    
    if (!count) {
      return;
    }
    
    if (count > 1) {
      params = 'data-ids="' + ids.join(',') + '"';
    }
    else {
      params = 'data-id="' + ids.join(',') + '"';
    }
    
    el = document.createElement('div');
    el.id = 'feedback';
    el.innerHTML = '<span id="select-prompt" class="feedback">'
      + count + ' entr' + (count == 1 ? 'y' : 'ies') + ' selected: '
      + '<span ' + params + ' data-cmd="delete" class="button btn-deny">Delete</span>'
      + '<span ' + params + ' data-enable data-cmd="toggle-active" class="button">Enable</span>'
      + '<span ' + params + ' data-cmd="toggle-active" class="button">Disable</span>'
      + '</span>';
    
    document.body.appendChild(el);
  },
  
  hideMessage: function() {
    var el = $.id('feedback');
    
    if (el) {
      document.body.removeChild(el);
    }
  },
  
  showStatusMessage: function(msg, type) {
    var el, cnt;
    
    cnt = $.id('feedback');
    
    if (!cnt) {
      return;
    }
    
    el = $.id('select-prompt');
    el && $.addClass(el, 'hidden');
    
    el = $.id('select-status');
    el && cnt.removeChild(el);
    
    el = $.el('span');
    el.id = 'select-status';
    el.className = 'feedback';
    
    if (type) {
      el.className += ' feedback-' + type;
      
      if (type === 'error') {
        el.setAttribute('data-cmd', 'dismiss-error');
        el.setAttribute('title', 'Dismiss');
      }
    }
    
    el.innerHTML = msg;
    
    cnt.appendChild(el);
  },
  
  hideStatusMessage: function() {
    var el = $.id('select-status');
    
    if (el) {
      el.parentNode.removeChild(el);
    }
    
    el = $.id('select-prompt');
    el && $.removeClass(el, 'hidden');
  },
  
  onClick: function(e) {
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
  },
  
  onEscapeHtmlClick: function() {
    let el = $.id('js-pattern-field');
    el.value = APP.encodeHtml(el.value);
  },
  
  onEscapeStrClick: function() {
    let el = $.id('js-pattern-field');
    let val = el.value.replace(/[^a-zA-Z0-9.,/&:;?=~_-]/g, '');
    val = val.toLowerCase();
    el.value = val;
  },
  
  onEscapeSageClick: function() {
    let el = $.id('js-pattern-field');
    let words = el.value.replace(/[.,!:>\/]+|&gt;/g, ' ').toLowerCase().split(/ +/);
    let out = [];
    for (let w of words) {
      out.push(w[0].toUpperCase() + w.slice(1));
    }
    el.value = out.join(' ');
  },
  
  onToggleCopyClick: function(cb, e) {
    var input, fieldset, btn;
    
    if (!cb) {
      cb = $.id('js-copy-toggle');
    }
    
    if (!cb) {
      return;
    }
    
    input = $.id('js-copy-boards');
    fieldset = $.id('js-main-fields');
    btn = $.id('js-copy-btn');
    
    input.disabled = !cb.checked;
    fieldset.disabled = cb.checked;
    btn.disabled = !cb.checked;
  },
  
  onToggleClick: function(button, e) {
    var i, el, nodes, ids;
    
    ids = [];
    nodes = $.cls('filter-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.checked) {
        ids.push(el.getAttribute('data-id'));
      }
    }
    
    APP.showMessage(ids);
  },
  
  onToggleAllClick: function(button) {
    var i, nodes, el, flag, ids;
    
    flag = !$.id('feedback');
    
    ids = [];
    nodes = $.cls('filter-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      el.checked = flag;
      
      if (flag) {
        ids.push(el.getAttribute('data-id'));
      }
    }
    
    APP.showMessage(ids);
  },
  
  uncheckAll: function() {
    var i, nodes, el;
    
    nodes = $.cls('filter-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.checked || el.disabled) {
        el.checked = el.disabled = false;
      }
    }
  },
  
  onToggleActiveClick: function(button, e) {
    var xhr, ids, flag;
    
    e.preventDefault();
    
    if (APP.xhr) {
      return;
    }
    
    ids = button.getAttribute('data-ids') || button.getAttribute('data-id');
    
    if (!ids) {
      return;
    }
    
    xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = APP.onToggleActiveLoaded;
    xhr.onerror = APP.onDeleteError;
    xhr.filterIds = ids;
    
    if (button.hasAttribute('data-enable')) {
      xhr.flagActive = true;
      flag = '1';
    }
    else {
      xhr.flagActive = false;
      flag = '0';
    }
    
    APP.xhr = xhr;
    
    APP.showStatusMessage('Processing...', 'notify');
    
    xhr.send('action=toggle_active&active=' + flag
      + '&_tkn=' + $.getToken() + '&ids=' + ids);
  },
  
  onToggleActiveLoaded: function() {
    var i, id, el, filterIds, cb, resp, pp;
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.hideMessage();
      
      APP.uncheckAll();
      
      filterIds = this.filterIds.split(',');
      
      for (i = 0; id = filterIds[i]; ++i) {
        if (el = $.id('filter-' + id)) {
          cb = $.cls('filter-select', el)[0];
          cb.checked = false;
          
          if (this.flagActive) {
            $.cls('col-act', el)[0].innerHTML = '&#x2713;';
          }
          else {
            $.cls('col-act', el)[0].textContent = '';
          }
        }
      }
    }
    else {
      APP.showStatusMessage(resp.message, 'error');
    }
  },
  
  onDeleteClick: function(button, e) {
    var xhr, ids;
    
    e.preventDefault();
    
    if (APP.xhr) {
      return;
    }
    
    if (!confirm('Are you sure?')) {
      return;
    }
    
    ids = button.getAttribute('data-ids') || button.getAttribute('data-id');
    
    if (!ids) {
      return;
    }
    
    xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = APP.onDeleteLoaded;
    xhr.onerror = APP.onDeleteError;
    xhr.filterIds = ids;
    
    APP.xhr = xhr;
    
    APP.showStatusMessage('Processing...', 'notify');
    
    xhr.send('action=delete&_tkn=' + $.getToken() + '&ids=' + ids);
  },
  
  onDeleteLoaded: function() {
    var i, id, el, filterIds, cb, resp, pp;
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.hideMessage();
      
      APP.uncheckAll();
      
      filterIds = this.filterIds.split(',');
      
      for (i = 0; id = filterIds[i]; ++i) {
        if (el = $.id('filter-' + id)) {
          cb = $.cls('filter-select', el)[0];
          cb.checked = false;
          cb.disabled = true;
          $.removeClass(cb, 'filter-select');
          $.cls('col-act', el)[0].textContent = '';
        }
      }
    }
    else {
      APP.showStatusMessage(resp.message, 'error');
    }
  },
  
  onDeleteError: function() {
    APP.xhr = null;
    APP.showStatusMessage('Something went wrong.', 'error');
  },
  
  onSearchSubmit: function(e) {
    var i, el, nodes, q, v;
    
    e.preventDefault();
    
    nodes = $.cls('search-field', this);
    
    q = [];
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.type === 'text') {
        if (el.value !== '') {
          q.push(el.name + '=' + encodeURIComponent(el.value));
        }
      }
      else if (el.type === 'checkbox') {
        if (el.checked) {
          q.push(el.name + '=1');
        }
      }
      else if (el.nodeName === 'SELECT') {
        v = el.options[el.selectedIndex].value;
        if (v !== '') {
          q.push(el.name + '=' + encodeURIComponent(v));
        }
      }
    }
    
    if (q.length < 1) {
      return;
    }
    
    location.search = '?action=search&' + q.join('&');
  }
};

APP.init();
