'use strict';

var APP = {
  init: function() {
    this.clickCommands = {
      
    };
    
    Tip.init();
    
    $.on(document, 'DOMContentLoaded', APP.run);
    $.on(document, 'click', APP.onClick);
  },
  
  run: function() {
    var i, nodes, el;
    
    $.off(document, 'DOMContentLoaded', APP.run);
    
    nodes = $.cls('action-lbl');
    
    for (i = 0; el = nodes[i]; ++i) {
      $.on(el.firstElementChild, 'change', APP.onActionChange);
    }
  },
  
  onActionChange: function(e) {
    var i, nodes, el, flag;
    
    flag = !$.id('action-ban-btn').checked;
    
    nodes = $.cls('ban-fields');
    
    for (i = 0; el = nodes[i]; ++i) {
      el.disabled = flag;
    }
  },
  
  showReasonTip: function(t) {
    if ($.hasClass(t.nextElementSibling, 'hidden')) {
      return t.nextElementSibling.innerHTML;
    }
    else {
      return null;
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
