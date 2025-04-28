'use strict';

var APP = {
  init: function() {
    this.xhr = null;
    
    this.clickCommands = {
      'revoke': APP.onRevokeClick,
      'update-email': APP.onUpdateEmailClick,
      'send-notice': APP.onSendNoticeClick,
      'force-confirm': APP.onForceConfirmClick
    };
    
    $.on(document, 'click', APP.onClick);
  },
  
  onClick: function(e) {
    var t, cmd;
    
    if (e.which != 1 || e.ctrlKey || e.altKey || e.shiftKey || e.metaKey) {
      return;
    }
    
    if ((t = e.target) == document) {
      return;
    }
    
    if ((cmd = t.getAttribute('data-cmd')) && (cmd = APP.clickCommands[cmd])) {
      e.stopPropagation();
      cmd(t, e);
    }
  },
  
  getItemUID: function(btn) {
    return 0 | btn.parentNode.parentNode.id.split('-')[1];
  },
  
  parseResponse: function(data) {
    try {
      return JSON.parse(data);
    }
    catch (e) {
      return {
        status: 'error',
        message: 'Something went wrong.'
      };
    }
  },
  
  onUpdateEmailClick: function(btn) {
    var el, uid, tid, token, otp, old_email, new_email;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      tid = $.cls('js-tid', el)[0].textContent;
      token = $.cls('js-token', el)[0].textContent;
      
      old_email = $.cls('js-email', el)[0].textContent;
      
      if (!tid || !token) {
        console.log('Bad transaction id or token');
      }
      
      new_email = prompt('New E-Mail (old E-Mail is ' + old_email + ')');
      
      if (!new_email) {
        console.log('Canceled');
        return;
      }
      
      otp = prompt('One-time Password');
      
      if (!otp) {
        console.log('Canceled');
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.updateEmail(tid, token, otp, new_email, uid);
    }
  },
  
  onSendNoticeClick: function(btn) {
    var el, uid, token, email;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
            
      token = $.cls('js-token', el)[0].textContent;
      email = $.cls('js-email', el)[0].textContent;
      
      if (!confirm('Send the expiration email to ' + email + '?')) {
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.sendNotice(token, uid);
    }
  },
  
  sendNotice: function(token, uid) {
    $.xhr('POST', location.pathname,
      {
        onload: APP.onItemProcessed,
        onerror: APP.onXhrError,
        withCredentials: true,
        uid: uid,
        noop: true
      },
      '_tkn=' + $.getToken() + '&action=send_renewal_email&token=' + token
    );
  },
  
  updateEmail: function(tid, token, otp, new_email, uid) {
    $.xhr('POST', location.pathname,
      {
        onload: APP.onEmailUpdated,
        onerror: APP.onXhrError,
        withCredentials: true,
        uid: uid,
      },
      '_tkn=' + $.getToken() + '&action=update_email&tid=' + tid + '&token=' + token
        + '&new_email=' + encodeURIComponent(new_email) + '&otp=' + otp
    );
  },
  
  onRevokeClick: function(btn) {
    var el, uid, tid, token, otp, asIllegal;
    
    if (uid = APP.getItemUID(btn)) {
      el = $.id('item-' + uid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      tid = $.cls('js-tid', el)[0].textContent;
      token = $.cls('js-token', el)[0].textContent;
      
      if (!tid || !token) {
        console.log('Bad transaction id or token');
      }
      
      otp = prompt('One-time Password');
      
      if (!otp) {
        console.log('Canceled');
        return;
      }
      
      asIllegal = btn.hasAttribute('data-illegal');
      
      $.addClass(el, 'processing');
      
      APP.revokePass(tid, token, otp, uid, asIllegal);
    }
  },
  
  revokePass: function(tid, token, otp, uid, asIllegal) {
    if (asIllegal) {
      asIllegal = '&illegal=1';
    }
    else {
      asIllegal = '';
    }
    
    $.xhr('POST', location.pathname,
      {
        onload: APP.onItemProcessed,
        onerror: APP.onXhrError,
        withCredentials: true,
        uid: uid,
      },
      '_tkn=' + $.getToken() + '&action=revoke&tid=' + tid + '&token=' + token
        + '&otp=' + otp + asIllegal
    );
  },
  
  onForceConfirmClick: function(btn) {
    var chargeId, adjustMonths, otp;
    
    if (chargeId = btn.getAttribute('data-charge')) {
      otp = prompt('One-time Password');
      
      if (!otp) {
        console.log('Canceled');
        return;
      }
      
      adjustMonths = $.id('js-adjust-months');
      
      if (adjustMonths && adjustMonths.checked) {
        adjustMonths = adjustMonths.value;
      }
      else {
        adjustMonths = null;
      }
      
      APP.forceConfirmCharge(chargeId, adjustMonths, otp);
    }
  },
  
  forceConfirmCharge: function(chargeId, adjustMonths, otp) {
    if (!adjustMonths) {
      adjustMonths = '';
    }
    else {
      adjustMonths = '&adjust_months=' + adjustMonths;
    }
    
    Feedback.showMessage('Loading…', 'notify', 0);
    
    $.xhr('POST', location.pathname,
      {
        onload: APP.onChargeProcessed,
        onerror: APP.onXhrError,
        withCredentials: true,
      },
      '_tkn=' + $.getToken() + '&action=coinbase_confirm&otp=' + otp
        + '&charge_id=' + chargeId + adjustMonths
    );
  },
  
  onChargeProcessed: function() {
    var resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      Feedback.notify('Done.');
      $.id('js-confirm-btn').display = 'none';
    }
    else {
      Feedback.showMessage(resp.message, 'error');
    }
  },
  
  onXhrError: function() {
    var el = $.id('item-' + this.uid);
    
    if (!el) {
      return;
    }
    
    $.removeClass(el, 'processing');
    
    Feedback.error();
  },
  
  onEmailUpdated: function() {
    var el, resp, cell;
    
    el = $.id('item-' + this.uid);
    
    if (!el) {
      return;
    }
    
    $.removeClass(el, 'processing');
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      cell = $.cls('js-email', el)[0];
      
      cell.textContent = resp.data.new_email;
    }
    else {
      Feedback.error(resp.message);
    }
  },
  
  onItemProcessed: function() {
    var el, resp;
    
    el = $.id('item-' + this.uid);
    
    if (!el) {
      return;
    }
    
    $.removeClass(el, 'processing');
    
    APP.xhr = null;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      if (!this.noop) {
        $.addClass(el, 'disabled');
      }
    }
    else {
      Feedback.error(resp.message);
    }
  }
};

/**
 * Notifications
 */
var Feedback = {
  messageTimeout: null,
  
  showMessage: function(msg, type, timeout) {
    var el;
    
    Feedback.hideMessage();
    
    el = document.createElement('div');
    el.id = 'feedback';
    el.title = 'Dismiss';
    el.innerHTML = '<span class="feedback-' + type + '">' + msg + '</span>';
    
    $.on(el, 'click', Feedback.hideMessage);
    
    document.body.appendChild(el);
    
    if (timeout) {
      Feedback.messageTimeout = setTimeout(Feedback.hideMessage, timeout);
    }
  },
  
  hideMessage: function() {
    var el = $.id('feedback');
    
    if (el) {
      if (Feedback.messageTimeout) {
        clearTimeout(Feedback.messageTimeout);
        Feedback.messageTimeout = null;
      }
      
      $.off(el, 'click', Feedback.hideMessage);
      
      document.body.removeChild(el);
    }
  },
  
  error: function(msg) {
    Feedback.showMessage(msg || 'Something went wrong', 'error', 5000);
  },
  
  notify: function(msg) {
    Feedback.showMessage(msg, 'notify', 3000);
  }
};

/**
 * Init
 */
APP.init();

Tip.init();
