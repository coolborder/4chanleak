var APP = {
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    
    APP.nodeStatusOk = $.id('js-cell-ok');
    
    APP.nodeProgress = $.id('js-progress');
    
    APP.nodeForm = $.id('js-form');
    APP.nodeBtn = $.id('js-submit-btn');
    
    $.on(APP.nodeForm, 'submit', APP.onSubmit);
    $.on(APP.nodeForm, 'reset', APP.onReset);
    
    APP.actionBase = '/manager/legalrequest?action=dump&id=';
    
    APP.xhr = null;
    
    APP.dataChunks = null;
    APP.totalChunks = null;
    
    APP.currentId = 0;
    APP.startId = 2251//2240;
    APP.endId = 2722;
  },
  
  onTabClose: function(e) {
    e.preventDefault();
    e.returnValue = '';
  },
  
  abort: function() {
    APP.xhr && APP.xhr.abort();
    APP.xhr = null;
    APP.nodeBtn.textContent = 'Submit';
    $.off(window, 'beforeunload', APP.onTabClose);
  },
  
  onReset: function(e) {
    e.preventDefault();
    
    if (APP.xhr) {
      return;
    }
    
    APP.nodeProgress.textContent = '';
    
    APP.nodeStatusOk.textContent = '';
    
    APP.currentId = 0;
  },
  
  onSubmit: function(e) {
    e.preventDefault();
    
    if (APP.xhr) {
      APP.abort();
      return;
    }
    
    APP.nodeBtn.textContent = 'Abort';
    
    APP.nodeProgress.textContent = '0';
    
    $.on(window, 'beforeunload', APP.onTabClose);
    
    APP.currentId = APP.startId;
    
    APP.sendNextChunk();
  },
  
  sendNextChunk: function() {
    if (APP.currentId > APP.endId) {
      APP.abort();
      return;
    }
    
    APP.xhr = $.xhr('GET', APP.actionBase + APP.currentId,
      {
        onload: APP.onXhrLoad,
        onerror: APP.onXhrError,
        withCredentials: true,
      },
      {
        '_tkn': $.getToken()
      }
    );
  },
  
  onXhrLoad: function(e) {
    if (/!!! Error/.test(this.responseText)) {
      APP.abort();
      alert(this.responseText);
      return;
    }
    
    APP.updateStatus(this.responseText);
    
    APP.xhr = null;
    
    APP.currentId += 1;
    
    APP.sendNextChunk();
  },
  
  onXhrError: function(e) {
    console.log(e);
    APP.abort();
    alert('Connection Error.');
  },
  
  updateStatus: function(data) {
    APP.nodeProgress.textContent = APP.currentId;
    
    APP.nodeStatusOk.appendChild(document.createTextNode(data + "\n"));
  },
};

APP.init();
