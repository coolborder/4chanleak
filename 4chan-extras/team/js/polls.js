'use strict';

var APP = {
  init: function() {
    Tip.init();
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    var i, el, nodes;
    
    if (el = $.id('add-option')) {
      $.on(el, 'click', APP.onAddOptionClick);
    }
  },
  
  onAddOptionClick: function(e) {
    var li, el;
    
    el = $.el('input');
    el.type = 'text';
    el.name = 'new_options[]';
    
    li = $.el('li');
    li.className = 'poll-opt';
    
    li.appendChild(el);
    
    $.id('opts-cnt').appendChild(li);
  }
};

APP.init();
