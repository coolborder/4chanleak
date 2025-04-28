'use strict';

var APP = {};

APP.init = function() {
  $.on(document, 'click', APP.onClick);
  $.on(document, 'DOMContentLoaded', APP.run);
  
  Tip.init();
};

APP.run = function() {
  var i, el, nodes;
  
  $.off(document, 'DOMContentLoaded', APP.run);
  
  nodes = $.cls('dismiss-link');
  
  for (i = 0; el = nodes[i]; ++i) {
    $.on(el, 'click', APP.onDismissClick);
  }
  
  nodes = $.cls('approve-link');
  
  for (i = 0; el = nodes[i]; ++i) {
    $.on(el, 'click', APP.onApproveClick);
  }
  
  if (el = $.id('form-dismiss')) {
    $.on(el, 'click', APP.onFormDismissClick);
  }
};

APP.onFormDismissClick = function(e) {
  if (!confirm('Are you sure?')) {
    e.preventDefault();
    e.stopPropagation();
    return;
  }
};

APP.dismiss = function(id) {
  $.xhr('POST', '',
    {},
    {
      action: 'dismiss',
      id: id
    }
  );
};

APP.approveDraft = function(id) {
  $.xhr('POST', '',
    {},
    {
      action: 'approve',
      id: id
    }
  );
};

APP.onApproveClick = function(e) {
  var el, id;
  
  el = e.target;
  
  if (id = el.getAttribute('data-id')) {
    e.preventDefault();
    APP.approveDraft(id);
    $.addClass(el.parentNode.parentNode, 'disabled');
    el.parentNode.textContent = '';
  }
};

APP.onDismissClick = function(e) {
  var el, id;
  
  el = e.target;
  
  if (id = el.getAttribute('data-id')) {
    e.preventDefault();
    
    if (!confirm('Are you sure?')) {
      return;
    }
    
    APP.dismiss(id);
    $.addClass(el.parentNode.parentNode, 'disabled');
    el.parentNode.textContent = '';
  }
};

APP.init();
