'use strict';

var APP = {
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    var el;
    
    $.off(document, 'DOMContentLoaded', APP.run);
    
    if (el = $.id('email-preview')) {
      $.on(el, 'submit', APP.onEmailSubmit);
    }
    else if (el = $.id('add-file')) {
      $.on(el, 'click', APP.onAddFileClick);
      el = $.id('debug-toggle');
      $.on(el, 'click', APP.onDebugToggleClick);
    }
  },
  
  onDebugToggleClick: function() {
    var el, cnt;
    
    if (el = $.id('raw-report')) {
      el.parentNode.removeChild(el);
      
      if (el = $.id('debug-report')) {
        el.parentNode.removeChild(el);
      }
      
      return;
    }
    
    cnt = $.id('content');
    
    el = $.el('div');
    el.id = 'raw-report';
    el.className = 'pre-block';
    el.textContent = $.id('raw-data').value;
    cnt.insertBefore(el, cnt.firstElementChild);
    
    el = $.el('div');
    el.id = 'debug-report';
    el.className = 'pre-block';
    el.innerHTML = $.id('debug-data').innerHTML;
    cnt.insertBefore(el, cnt.firstElementChild);
  },
  
  onEmailSubmit: function(e) {
    if (!confirm('Are you sure?')) {
      e.preventDefault();
      e.stopPropagation();
      return;
    }
  },
  
  onAddFileClick: function(e) {
    var el;
    
    el = $.el('input');
    el.type = 'file';
    el.name = 'doc_file[]';
    
    $.id('file-cnt').appendChild(el);
  }
};

/**
 * Init
 */
APP.init();
