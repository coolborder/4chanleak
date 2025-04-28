var APP = {
  init: function() {
    this.xhr = null;
    
    this.clickCommands = {
      'edit': APP.onEditClick,
      'toggle': APP.onToggleClick,
      'delete': APP.onDeleteClick,
      'toggle-active': APP.onToggleActiveClick,
      'match': APP.onMatchClick,
      'search': APP.onSearchClick,
      'toggle-all': APP.onToggleAllClick,
      'dismiss-error': APP.hideStatusMessage,
      'reset-filter': APP.resetFilter,
      'search-more': APP.onSearchMoreClick,
      'use-calc-res': APP.useCalcResult,
    };
    
    Tip.init();
    
    $.on(document, 'click', APP.onClick);
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    var el;
    
    //APP.uncheckAll();
    
    if (el = $.id('filter-ip')) {
      $.on(el, 'keydown', APP.onFilterKeyDown);
      $.on($.id('filter-desc'), 'keydown', APP.onFilterKeyDown);
    }
    
    if (el = $.id('js-update-desc')) {
      APP.onUpdateDescChanged();
      $.on(el, 'change', APP.onUpdateDescChanged);
    }
    
    if (el = $.id('js-calc-cidr-form')) {
      $.on(el, 'submit', APP.onCalcCIDRSubmit);
      $.on($.id('js-calc-ip-form'), 'submit', APP.onCalcIPSubmit);
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
  
  showSpinner: function() {
    var cnt, el;
    
    cnt = $.id('content');
    
    el = document.createElement('div');
    el.id = 'load-spinner';
    el.innerHTML = 'Processing&hellip;';
    
    APP.hideSpinner();
    cnt.insertBefore(el, cnt.firstElementChild);
  },
  
  hideSpinner: function() {
    var el = $.id('load-spinner');
    
    if (el) {
      el.parentNode.removeChild(el);
    }
  },
  
  useCalcResult: function() {
    var el, data;
    
    el = $.id('js-calc-res');
    
    if (!el) {
      return;
    }
    
    if (!(data = el.getAttribute('data-cidr'))) {
      data = el.textContent;
    }
    
    $.id('js-ranges-field').value = data;
  },
  
  resetCalcResCnt: function(el) {
    el.textContent = '';
    el.removeAttribute('data-cidr');
    el.parentNode.classList.add('hidden');
  },
  
  onCalcCIDRSubmit: function(e) {
    var cidr, ip, pfx, res, el;
    
    e.preventDefault();
    
    el = $.id('js-calc-res');
    
    APP.resetCalcResCnt(el);
    
    cidr = $.id('js-calc-cidr').value;
    
    cidr = cidr.match(/^([.0-9]+)\/([0-9]{1,2})$/);
    
    if (!cidr) {
      return;
    }
    
    [cidr, ip, pfx] = cidr;
    
    if (!ip || !pfx || pfx < 1 || pfx > 32) {
      return;
    }
    
    res = IpSubnetCalculator.calculateSubnetMask(ip, pfx);
    
    if (!res) {
      return;
    }
    
    el.setAttribute('data-cidr', cidr);
    
    el.innerHTML = `Start IP: ${res.ipLowStr}\nEnd IP: ${res.ipHighStr}`;
    
    el.parentNode.classList.remove('hidden');
  },
  
  onCalcIPSubmit: function(e) {
    var ipStart, ipEnd, res, el;
    
    e.preventDefault();
    
    el = $.id('js-calc-res');
    
    APP.resetCalcResCnt(el);
    
    ipStart = $.id('js-calc-ip-s').value;
    ipEnd = $.id('js-calc-ip-e').value;
    
    res = IpSubnetCalculator.calculate(ipStart, ipEnd);
    
    if (!res) {
      return;
    }
    
    el.innerHTML = res.map(x => `${x.ipLowStr}/${x.prefixSize}`).join("\n");
    
    el.parentNode.classList.remove('hidden');
  },
  
  onSearchMoreClick: function(btn, e) {
    var el;
    
    el = $.cls('js-desc', btn.parentNode)[0];
    
    if (!el || el.textContent === '') {
      e.preventDefault();
      return;
    }
    
    btn.setAttribute(
      'href',
      '?action=search&mode=desc&q=' + encodeURIComponent(el.textContent)
    );
  },
  
  onUpdateDescChanged: function(e) {
    var el;
    
    if (e) {
      el = this;
    }
    else {
      el = $.id('js-update-desc');
    }
    
    $.id('field-desc').disabled = !el.checked;
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
      + '<span ' + params + ' data-cmd="edit" class="button">Edit</span>'
      + '<span ' + params + ' data-cmd="delete" class="button">Delete</span>'
      + '<span ' + params + ' data-enable data-cmd="toggle-active" class="button">Enable</span>'
      + '<span ' + params + ' data-cmd="toggle-active" class="button">Disable</span>'
      + '<span> | </span>'
      + '<span ' + params + ' data-cmd="toggle-all" class="button">Deselect</span>'
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
    var t, cmd;
    
    if (e.button == 2 || e.ctrlKey || e.altKey || e.shiftKey || e.metaKey) {
      return;
    }
    
    if ((t = e.target) == document) {
      return;
    }
    
    if (e.button == 4 && !t.hasAttribute('data-wheel-ok')) {
      return;
    }
    
    if ((cmd = t.getAttribute('data-cmd')) && (cmd = APP.clickCommands[cmd])) {
      e.stopPropagation();
      cmd(t, e);
    }
  },
  
  onFilterKeyDown: function(e) {
    var t, keyCode = e.keyCode;
    
    t = e.target;
    
    if (keyCode == 13) {
      APP.search(t.value, t.id.split('-')[1]);
    }
  },
  
  search: function(value, mode, opts) {
    var key, opts_params;
    
    if (value === '' && !opts) {
      return;
    }
    
    opts_params = '';
    
    if (opts) {
      for (key in opts) {
        opts_params += '&' + key + '=' + encodeURIComponent(opts[key]);
      }
    }
    
    location.search = '?action=search&mode=' + mode + '&q='
      + encodeURIComponent(value) + opts_params;
  },
  
  resetFilter: function() {
    var i, el, nodes;
    
    if (el = $.id('filter-desc')) {
      el.value = '';
    }
    
    nodes = $.cls('js-search-opt');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.type === 'checkbox') {
        el.checked = false;
      }
      else if (el.type === 'text' && el.value !== '') {
        el.value = '';
      }
    }
  },
  
  onMatchClick: function() {
    APP.search($.id('filter-ip').value, 'ip');
  },
  
  onSearchClick: function() {
    var i, el, nodes, opts = {};
    
    nodes = $.cls('js-search-opt');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.type === 'checkbox' && el.checked) {
        opts[el.name] = 1;
      }
      else if (el.type === 'text' && el.value !== '') {
        opts[el.name] = el.value;
      }
    }
    
    APP.search($.id('filter-desc').value, 'desc', opts);
  },
  
  onToggleClick: function() {
    var i, el, nodes, ids;
    
    ids = [];
    nodes = $.cls('range-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.checked) {
        ids.push(el.getAttribute('data-id'));
      }
    }
    
    APP.showMessage(ids);
  },
  
  onToggleAllClick: function() {
    var i, nodes, el, flag, ids;
    
    flag = !$.id('feedback');
    
    ids = [];
    nodes = $.cls('range-select');
    
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
    
    nodes = $.cls('range-select');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.checked || el.disabled) {
        el.checked = el.disabled = false;
      }
    }
  },
  
  onEditClick: function(button, e) {
    e.preventDefault();
    
    if (button.hasAttribute('data-ids')) {
      location.search = '?action=update&ids=' + button.getAttribute('data-ids');
    }
    else {
      location.search = '?action=update&id=' + button.getAttribute('data-id');
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
    xhr.rangeIds = ids;
    
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
    var i, id, el, rangeIds, cb, resp;
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.hideMessage();
      
      APP.uncheckAll();
      
      rangeIds = this.rangeIds.split(',');
      
      for (i = 0; id = rangeIds[i]; ++i) {
        if (el = $.id('range-' + id)) {
          cb = $.cls('range-select', el)[0];
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
    xhr.rangeIds = ids;
    
    APP.xhr = xhr;
    
    APP.showStatusMessage('Processing...', 'notify');
    
    xhr.send('action=delete&xhr=1&_tkn=' + $.getToken() + '&ids=' + ids);
  },
  
  onDeleteLoaded: function() {
    var i, id, el, rangeIds, cb, resp;
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.hideMessage();
      
      APP.uncheckAll();
      
      rangeIds = this.rangeIds.split(',');
      
      for (i = 0; id = rangeIds[i]; ++i) {
        if (el = $.id('range-' + id)) {
          cb = $.cls('range-select', el)[0];
          cb.checked = false;
          cb.disabled = true;
          $.removeClass(cb, 'range-select');
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
  }
};

APP.init();
