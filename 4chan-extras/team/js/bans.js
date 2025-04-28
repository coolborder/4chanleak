'use strict';

var APP = {
  init: function() {
    this.xhr = null;
    
    this.checkboxCount = 0;
    
    this.clickCommands = {
      'unban': APP.onUnbanClick,
      'toggle': APP.onToggleClick,
      'toggle-all': APP.onToggleAllClick,
      'dismiss-error': APP.hideStatusMessage,
      'toggle-appeals': APP.onToggleAppealsClick,
      'toggle-dt': APP.onToggleDTClick
    };
    
    this.pp = {
      node: null,
      timeout: null,
      delay: 150,
      thumbMaxSize: 175
    };
    
    Tip.init();
    
    if (localStorage.getItem('dark-theme')) {
      $.addClass($.docEl, 'dark-theme');
    }
    
    $.on(document, 'click', APP.onClick);
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    var i, nodes, el, page;
    
    $.off(document, 'DOMContentLoaded', APP.run);
    
    if (localStorage.getItem('dark-theme')) {
      $.id('cfg-cb-dt').checked = true;
    }
    
    page = document.body.getAttribute('data-page');
    
    if (page === 'update') {
      nodes = $.cls('js-length-radio');
      
      for (i = 0; el = nodes[i]; ++i) {
        $.on(el.firstElementChild, 'change', APP.onLengthPresetChange);
      }
    }
    else if (page === 'index') {
      APP.uncheckAll();
      
      nodes = $.cls('pp-link');
      
      for (i = 0; el = nodes[i]; ++i) {
        $.on(el, 'mouseover', APP.onPPMouseOver);
        $.on(el, 'mouseout', APP.onPPMouseOut);
      }
    }
    else if (page === 'search') {
      $.on($.id('search-form'), 'submit', APP.onSearchSubmit);
    }
    
    if (el = $.id('front-form-qs')) {
      $.on(el, 'submit', APP.onQuickSearchSubmit);
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
  
  onToggleDTClick: function() {
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
  },
  
  onPPMouseOver: function(e) {
    if (APP.pp.timeout) {
      clearTimeout(APP.pp.timeout);
      APP.pp.timeout = null;
    }
    
    APP.pp.timeout = setTimeout(APP.showPP, APP.pp.delay, e.target);
  },
  
  onPPMouseOut: function() {
    if (APP.pp.timeout) {
      clearTimeout(APP.pp.timeout);
      APP.pp.timeout = null;
    }
    
    APP.hidePP();
  },
  
  showPP: function(t) {
    var el, rect, style, left, top, attr, html, ths, maxSize, w, h, ratio;
    
    rect = t.getBoundingClientRect();
    
    el = document.createElement('div');
    el.id = 'post-preview';
    
    html = '';
    
    if (attr = t.getAttribute('data-sub')) {
      html += '<div class="pp-sub">' + attr + '</div>';
    }
    else if (attr = t.getAttribute('data-rel-sub')) {
      html += '<div class="pp-sub pp-rel-sub">' + attr + '</div>';
    }
    
    html += '<div class="pp-com">';
    
    if (attr = t.getAttribute('data-thumb')) {
      ths = t.getAttribute('data-ths').split('x');
      w = ths[0];
      h = ths[1];
      
      maxSize = APP.pp.thumbMaxSize;
      
      if (w > maxSize) {
        ratio = maxSize / w;
        w = maxSize;
        h = h * ratio;
      }
      
      if (h > maxSize) {
        ratio = maxSize / h;
        h = maxSize;
        w = w * ratio;
      }
      
      html += '<img class="pp-thumb" alt="Thumbnail Unavailable" src="'
        + attr + '" width="'
        + w + '" height="' + h + '">';
    }
    
    if (attr = t.getAttribute('data-com')) {
      html += attr;
    }
    
    html += '</div>';
    
    el.innerHTML = html;
    
    document.body.appendChild(el);
    
    top = rect.top + rect.height / 2 - el.offsetHeight / 2;
    left = rect.left + rect.width + 5;
    
    if (top + el.offsetHeight > $.docEl.clientHeight) {
      top = rect.bottom - el.offsetHeight - 2;
    }
    
    if (top < 0) {
      top = 2;
    }
    
    style = el.style;
    style.display = 'none';
    style.top = (top + window.pageYOffset) + 'px';
    style.left = left + window.pageXOffset + 'px';
    style.display = '';
    
    APP.pp.node = el;
  },
  
  hidePP: function() {
    if (APP.pp.node) {
      document.body.removeChild(APP.pp.node);
      APP.pp.node = null;
    }
  },
  
  onToggleAppealsClick: function(btn) {
    var el = $.id('js-appeals-cnt');
    
    if ($.hasClass(el, 'hidden')) {
      $.removeClass(el, 'hidden');
      btn.textContent = 'Hide';
    }
    else {
      $.addClass(el, 'hidden');
      btn.textContent = 'Show';
    }
  },
  
  onQuickSearchSubmit: function(e) {
    var el, val, action, name;
    
    el = $.id('front-field-qs');
    
    val = el.value = el.value.trim();
    
    if (/^[0-9]+$/.test(val)) {
      name = 'id';
      action = 'update';
    }
    else if (/^[.*0-9]+$/.test(val)) {
      name = 'ip';
      action = 'search';
    }
    else if (/^[a-f0-9]{32}$/.test(val)) {
      name = 'md5';
      action = 'search';
    }
    else {
      name = 'reason';
      action = 'search';
    }
    
    $.id('front-field-act').value = action;
    el.name = name;
  },
  
  onSearchSubmit: function(e) {
    var i, el, nodes, q, v;
    
    e.preventDefault();
    
    APP.parsePassRef();
    
    nodes = $.cls('s-f', this);
    
    q = [];
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.type === 'text' || el.nodeName === 'SELECT') {
        if (el.value !== '') {
          q.push(el.name + '=' + encodeURIComponent(el.value));
        }
      }
      else if (el.type === 'checkbox') {
        if (el.checked) {
          q.push(el.name + '=1');
        }
      }
    }
    
    if (q.length < 1) {
      return;
    }
    
    if ($.id('js-t-sub').value !== '' && $.id('js-board').value === '') {
      alert('The board field cannot be empty when searching by thread subject.');
      return;
    }
    
    if ($.id('js-def').value !== '' && $.id('js-dsf').value === '') {
      alert('Start date cannot be empty.');
      return;
    }
    
    location.search = '?action=search&' + q.join('&');
  },
  
  onLengthPresetChange: function(e) {
    var i, nodes, el, disabled;
    
    nodes = $.cls('js-length-radio');
    
    disabled = this.checked;
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.firstElementChild !== this) {
        el.firstElementChild.checked = false;
      }
    }
    
    $.id('field-length').disabled = disabled;
  },
  
  parsePassRef: function() {
    var el, m;
    
    el = document.forms.search.pass_ref;
    
    if (!el || el.value === '') {
      return;
    }
    
    if (el.value.indexOf('/bans?') !== -1) {
      m = /id=([0-9]+)/.exec(el.value);
      
      if (m && m[1]) {
        el.value = m[1];
      }
    }
    else if (el.value.indexOf('/thread/') !== -1) {
      m = /\/([a-z0-9]+)\/thread\/([0-9]+)[^#]*(?:#[qp]([0-9]+))?$/.exec(el.value);
      
      if (m && m[1] && m[2]) {
        if (m[3]) {
          el.value = '/' + m[1] + '/' + m[3];
        }
        else {
          el.value = '/' + m[1] + '/' + m[2];
        }
      }
    }
  },
  
  showUnbanPrompt: function(ids) {
    var el, count, params;
    
    APP.hideUnbanPrompt();
    
    count = ids.length;
    
    if (!count) {
      return;
    }
    
    el = $.id('toggle-all');
    
    el.checked = count >= APP.checkboxCount;
    el.indeterminate = !el.checked;
    el.setAttribute('data-partial', el.indeterminate);
    
    params = 'data-ids="' + ids.join(',') + '"';
    
    el = document.createElement('div');
    el.id = 'feedback';
    el.innerHTML = '<span id="unban-prompt" class="feedback">'
      + count + ' entr' + (count == 1 ? 'y' : 'ies') + ' selected: '
      + '<span ' + params + ' data-cmd="unban" class="button btn-accept">Unban</span>'
      + '</span>';
    
    document.body.appendChild(el);
  },
  
  hideUnbanPrompt: function() {
    var el = $.id('feedback');
    
    if (el) {
      document.body.removeChild(el);
      el = $.id('toggle-all');
      el.checked = el.indeterminate = false;
    }
  },
  
  showStatusMessage: function(msg, type) {
    var el, cnt;
    
    cnt = $.id('feedback');
    
    if (!cnt) {
      return;
    }
    
    el = $.id('unban-prompt');
    el && $.addClass(el, 'hidden');
    
    el = $.id('unban-status');
    el && cnt.removeChild(el);
    
    el = $.el('span');
    el.id = 'unban-status';
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
    var el = $.id('unban-status');
    
    if (el) {
      el.parentNode.removeChild(el);
      el = $.id('toggle-all');
      el.checked = el.indeterminate = false;
    }
    
    el = $.id('unban-prompt');
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
  
  onToggleClick: function(button, e) {
    var i, el, nodes, ids;
    
    if (APP.xhr) {
      e.preventDefault();
      return;
    }
    
    ids = [];
    nodes = $.cls('range-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.checked) {
        ids.push(el.getAttribute('data-id'));
      }
    }
    
    APP.showUnbanPrompt(ids);
  },
  
  onToggleAllClick: function(button, e) {
    var i, nodes, el, flag, ids;
    
    if (APP.xhr) {
      e.preventDefault();
      return;
    }
    
    flag = !$.id('feedback');
    
    ids = [];
    nodes = $.cls('range-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.disabled) {
        continue;
      }
      
      el.checked = flag;
      
      if (flag) {
        ids.push(el.getAttribute('data-id'));
      }
    }
    
    APP.showUnbanPrompt(ids);
  },
  
  uncheckAll: function() {
    var i, nodes, el, count;
    
    if (el = $.id('toggle-all')) {
      el.checked = el.indeterminate = false;
    }
    
    count = 0;
    
    nodes = $.cls('range-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      el.checked = el.disabled = false;
      ++count;
    }
    
    APP.checkboxCount = count;
  },
  
  onUnbanClick: function(button, e) {
    var xhr, ids;
    
    e.preventDefault();
    
    if (APP.xhr) {
      return;
    }
    
    if (!button.hasAttribute('data-ids')) {
      return;
    }
    
    ids = button.getAttribute('data-ids');
    
    xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.onload = APP.onUnbanLoaded;
    xhr.onerror = APP.onUnbanError;
    xhr.banIds = ids;
    
    APP.xhr = xhr;
    
    APP.showStatusMessage('Processing...', 'notify');
    
    xhr.send('action=unban&_tkn=' + $.getToken() + '&ids=' + ids);
  },
  
  onUnbanLoaded: function() {
    var i, id, el, banIds, cb, resp, pp;
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.hideUnbanPrompt();
      
      banIds = this.banIds.split(',');
      
      for (i = 0; id = banIds[i]; ++i) {
        if (el = $.id('ban-' + id)) {
          cb = $.cls('range-select', el)[0];
          cb.checked = false;
          cb.disabled = true;
          $.removeClass(cb, 'range-select');
          $.cls('col-act', el)[0].textContent = '';
          
          if (pp = $.cls('pp-link', el)[0]) {
            $.removeClass(pp, 'pp-link');
            $.off(pp, 'mouseover', APP.onPPMouseOver);
            $.off(pp, 'mouseout', APP.onPPMouseOut);
          }
        }
      }
      
      APP.uncheckAll();
    }
    else {
      APP.showStatusMessage(resp.message, 'error');
    }
  },
  
  onUnbanError: function() {
    APP.xhr = null;
    APP.showStatusMessage('Something went wrong', 'error');
  }
};

APP.init();
