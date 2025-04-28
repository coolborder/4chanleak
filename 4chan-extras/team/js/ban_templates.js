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
    
    let sel = $.id('js-postban-sel');
    
    if (!sel) {
      return;
    }
    
    $.on(sel, 'change', APP.onActionChange);
    
    APP.onActionChange.call(sel);
  },
  
  onActionChange: function(e) {
    let cnt = $.id('js-postban-arg-cnt');
    let el = $.id('js-postban-arg-field');
    
    if (this.value === 'move') {
      cnt.style.display = 'table-row';
      el.required = true;
    }
    else {
      cnt.style.display = 'none';
      el.required = false;
    }
  },
};

/**
 * Init
 */
APP.init();
