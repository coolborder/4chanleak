var APP = {
  CHUNK_SIZE: 250,
  
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    
    APP.nodeStatusOk = $.id('js-cell-ok');
    APP.nodeStatusSkip = $.id('js-cell-skip');
    APP.nodeStatusError = $.id('js-cell-error');
    
    APP.nodeProgress = $.id('js-progress');
    
    APP.nodeForm = $.id('js-form');
    APP.nodeBtn = $.id('js-submit-btn');
    APP.nodeEntries = $.id('js-entries');
    
    $.on(APP.nodeForm, 'submit', APP.onSubmit);
    $.on(APP.nodeForm, 'reset', APP.onReset);
    
    APP.xhr = null;
    
    APP.dataChunks = null;
    APP.totalChunks = null;
  },
  
  onTabClose: function(e) {
    e.preventDefault();
    e.returnValue = '';
  },
  
  abort: function() {
    APP.xhr && APP.xhr.abort();
    APP.xhr = null;
    APP.nodeBtn.textContent = 'Submit';
    APP.nodeEntries.disabled = false;
    $.off(window, 'beforeunload', APP.onTabClose);
  },
  
  onReset: function(e) {
    e.preventDefault();
    
    if (APP.xhr) {
      return;
    }
    
    APP.nodeEntries.value = '';
    
    APP.nodeProgress.textContent = '';
    
    APP.nodeStatusOk.textContent = '';
    APP.nodeStatusSkip.textContent = '';
    APP.nodeStatusError.textContent = '';
  },
  
  onSubmit: function(e) {
    e.preventDefault();
    
    if (APP.xhr) {
      APP.abort();
      return;
    }
    
    APP.nodeEntries.disabled = true;
    
    APP.nodeBtn.textContent = 'Abort';
    
    APP.dataChunks = APP.getDataChunks();
    APP.totalChunks = APP.dataChunks.length;
    
    APP.nodeProgress.textContent = '0%';
    
    $.on(window, 'beforeunload', APP.onTabClose);
    
    APP.sendNextChunk();
  },
  
  sendNextChunk: function() {
    if (APP.dataChunks.length < 1) {
      APP.abort();
      return;
    }
    
    APP.xhr = $.xhr('POST', APP.nodeForm.action,
      {
        onload: APP.onXhrLoad,
        onerror: APP.onXhrError,
        withCredentials: true,
      },
      {
        entries: APP.dataChunks.shift().join("\n"),
        '_tkn': $.getToken()
      }
    );
  },
  
  onXhrLoad: function(e) {
    var resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.updateStatus(resp.data);
    }
    else {
      console.log(e, resp);
      APP.abort();
      alert('Something went wrong.');
      return;
    }
    
    APP.xhr = null;
    
    APP.sendNextChunk();
  },
  
  onXhrError: function(e) {
    console.log(e);
    APP.abort();
    alert('Connection Error.');
  },
  
  updateStatus: function(data) {
    APP.nodeProgress.textContent = (0 | ((APP.totalChunks - APP.dataChunks.length)
      / APP.totalChunks * 100 + 0.5)) + '%';
    
    if (data.ok.length) {
      APP.nodeStatusOk.appendChild(document.createTextNode(data.ok.join("\n") + "\n"));
    }
    
    if (data.skip.length) {
      APP.nodeStatusSkip.appendChild(document.createTextNode(data.skip.join("\n") + "\n"));
    }
    
    if (data.error.length) {
      APP.nodeStatusError.appendChild(document.createTextNode(data.error.join("\n") + "\n"));
    }
  },
  
  getDataChunks: function() {
    return APP.nodeEntries.value.trim().split(/[\r\n]+/).reduce(
      function(acc, value, index, array) {
        if (index % APP.CHUNK_SIZE === 0) {
          acc.push(array.slice(index, index + APP.CHUNK_SIZE));
        }
        return acc;
      }, []
    );
  },
  
  parseResponse: function(data) {
    try {
      return JSON.parse(data);
    }
    catch (e) {
      return {
        status: 'error',
        message: 'Something went wrong (' + e.toString() + ')'
      };
    }
  }
};

APP.init();
