'use strict';

var APP = {
  clearBtn: null,
  previewBtn: null,
  updateBtn: null,
  msgField: null,
  previewCnt: null,
  formEl: null,
  
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    
    APP.clearBtn = $.id('btn-clear');
    APP.previewBtn = $.id('btn-preview');
    APP.updateBtn = $.id('btn-update');
    APP.msgField = $.id('g-msg-field');
    APP.previewCnt = $.id('g-msg-preview');
    APP.formEl = $.id('post-form');
    
    $.on(APP.clearBtn, 'click', APP.onClearClick);
    $.on(APP.previewBtn, 'click', APP.onPreviewClick);
    $.on(APP.updateBtn, 'click', APP.onUpdateClick);
    $.on(APP.msgField, 'keyup', APP.onMsgChange);
  },
  
  onMsgChange: function() {
    APP.formEl.removeAttribute('data-valid');
    if (!$.hasClass(APP.updateBtn, 'disabled')) {
      $.addClass(APP.updateBtn, 'disabled');
    }
  },
  
  onFormSubmit: function(e) {
    if (!this.hasAttribute('data-valid')) {
      e.preventDefault();
      return false;
    }
  },
  
  onClearClick: function() {
    APP.msgField.value = '';
    APP.onPreviewClick();
  },
  
  onPreviewClick: function() {
    var msg, openTagCount, closeTagCount;
    
    msg = APP.msgField.value;
    
    APP.previewCnt.innerHTML = msg;
    /*
    openTagCount = msg.match(/<[^!\/].*?>/g);
    closeTagCount = msg.match(/<\/[^>]+>/g);
    
    openTagCount = openTagCount ? openTagCount.length : 0;
    closeTagCount = closeTagCount ? closeTagCount.length : 0;
    
    if (openTagCount !== closeTagCount) {
      APP.formEl.removeAttribute('data-valid');
      $.addClass(APP.updateBtn, 'disabled');
      alert('Error: HTML tag count mismatch!');
      return;
    }
    */
    $.removeClass(APP.updateBtn, 'disabled');
    APP.formEl.setAttribute('data-valid', '1');
  },
  
  onUpdateClick: function() {
    if ($.hasClass(APP.updateBtn, 'disabled')) {
      return;
    }
    APP.formEl.submit();
  },
};

/**
 * Init
 */
APP.init();
