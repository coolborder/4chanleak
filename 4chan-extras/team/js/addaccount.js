'use strict';

var APP = {
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    
    APP.onLevelChanged.call($.id('level-field'));
    
    $.on($.id('level-field'), 'change', APP.onLevelChanged);
  },
  
  onLevelChanged: function() {
    var thres, val, el;
    
    thres = +this.getAttribute('data-flags');
    val = +this.options[this.selectedIndex].value;
    
    el = $.id('flags-field');
    
    if (!el) {
      return;
    }
    
    if (val < thres) {
      if (!$.hasClass(el, 'disabled')) {
        $.addClass(el, 'disabled');
      }
      el.disabled = true;
    }
    else {
      $.removeClass(el, 'disabled');
      el.disabled = false;
    }
  }
};

/**
 * Init
 */
APP.init();
