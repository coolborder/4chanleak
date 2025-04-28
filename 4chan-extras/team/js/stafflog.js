'use strict';

var APP = {};

APP.init = function() {
  Tip.init();
  
  if (localStorage.getItem('dark-theme')) {
    $.addClass($.docEl, 'dark-theme');
  }
  
  $.on(document, 'DOMContentLoaded', APP.run);
};

APP.run = function() {
  if (localStorage.getItem('dark-theme')) {
    $.id('cfg-cb-dt').checked = true;
  }
  
  $.off(document, 'DOMContentLoaded', APP.run);
  $.on($.id('filter-form'), 'submit', APP.onApplyFilter);
  $.on($.id('filter-apply'), 'click', APP.onApplyFilter);
  $.on($.id('cfg-cb-dt'), 'click', APP.onToggleDTClick);
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

APP.validateFilter = function(filter) {
  if (!filter.board && filter.post) {
    APP.error('You need to select a board to search by post ID');
    return false;
  }
  
  return true;
};

APP.onApplyFilter = function(e) {
  var key, filter, field, hash;
  
  e && e.preventDefault();
  
  filter = {};
  
  field = $.id('filter-type');
  if (field.selectedIndex) {
    filter.type = field.options[field.selectedIndex].value;
  }
  
  field = $.id('filter-user');
  if (field.selectedIndex) {
    filter.user = field.options[field.selectedIndex].textContent;
  }
  
  field = $.id('filter-board');
  if (field.selectedIndex) {
    filter.board = field.options[field.selectedIndex].textContent;
  }
  
  field = $.id('filter-date');
  if (field.value) {
    filter.date = field.value;
  }
  
  field = $.id('filter-post');
  if (field.value) {
    filter.post = field.value;
  }
  /*
  field = $.id('filter-thread');
  if (field.value) {
    filter.thread = field.value;
  }
  */
  field = $.id('filter-ops');
  if (field.checked) {
    filter.ops = 1;
  }
  
  field = $.id('filter-manual');
  if (field.checked) {
    filter.manual = 1;
  }
  
  if (!APP.validateFilter(filter)) {
    return;
  }
  
  hash = [];
  
  for (key in filter) {
    hash.push(key + '=' + encodeURIComponent(filter[key]));
  }
  
  location.search = hash.join('&');
};

APP.init();
