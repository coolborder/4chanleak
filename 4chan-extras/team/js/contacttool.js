'use strict';

var APP = {
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
    
    this.clickCommands = {
      'change-group': APP.onGroupClick
    };
    
    $.on(document, 'click', APP.onClick);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
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
  },
  
  onGroupClick: function(t) {
    var i, item, items, group, el, cnt, input, l;
    
    group = $.id('subgroup-' + t.getAttribute('data-group')).cloneNode(true);
    
    cnt = $.id('active-subgroup');
    cnt.innerHTML = '';
    cnt.appendChild(group);
    
    $.removeClass($.id('mail-form'), 'hidden');
  }
};

/**
 * Init
 */
APP.init();
