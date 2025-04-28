'use strict';

var APP = {};

APP.init = function() {
  Tip.init();
  
  $.on(document, 'DOMContentLoaded', APP.run);
};

APP.run = function() {
  $.off(document, 'DOMContentLoaded', APP.run);
  $.on($.id('filter-form'), 'submit', APP.onApplyFilter);
  $.on($.id('filter-apply'), 'click', APP.onApplyFilter);
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
