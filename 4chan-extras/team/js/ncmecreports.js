'use strict';

var APP = {
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    
    $.id('add-field') && $.on($.id('add-field'), 'click', APP.onAddFieldClick);
  },
  
  onAddFieldClick: function(e) {
    var root, el;
    
    root = $.id('add-field-root');
    
    root.previousElementSibling.innerHTML
    
    el = $.el('tr');
    el.innerHTML = root.previousElementSibling.innerHTML;
    el.firstElementChild.textContent = '';
    
    root.parentNode.insertBefore(el, root);
  }
};

/**
 * Init
 */
APP.init();
