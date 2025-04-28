'use strict';

var APP = {
  init: function() {
    this.clickCommands = {
      'toggle-resolve-form': APP.onResolveFormClick
    };
    
    window.Tip && Tip.init();
    
    $.on(document, 'click', APP.onClick);
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    
    if ($.id('dmca-notice-form')) {
      $.on($.id('dmca-notice-form'), 'submit', APP.onNewNoticeSubmit);
    }
  },
  
  onNewNoticeSubmit: function(e) {
    if ($.id('dmca-email-field').value === '') {
      if (!confirm('The E-Mail field is empty.\nAre you sure you want to continue?')) {
        e.preventDefault();
        e.stopPropagation();
      }
    }
  },
  
  onResolveFormClick: function(t) {
    var cnt = t.nextElementSibling;
    
    if ($.hasClass(cnt, 'hidden')) {
      t.textContent = 'Hide Form';
      $.removeClass(cnt, 'hidden');
    }
    else {
      t.textContent = 'Show Form';
      $.addClass(cnt, 'hidden');
    }
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
      e.preventDefault();
      e.stopPropagation();
      cmd(t, e);
    }
  }
};

/**
 * Init
 */
APP.init();
