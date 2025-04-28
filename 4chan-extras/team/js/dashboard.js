var APP = {
  init: function() {
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    var a, actions = ['clr', 'del', 'fence_skip'];
    
    for (a of actions) {
      $.xhr('GET', '/?action=staff_overview&mode=' + a, {
        _action: a,
        onerror: APP.onXhrError,
        onload: APP.onStaffOverviewLoad,
      });
    }
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
  
  onXhrError: function() {
    APP.showWidgetError(this._action);
  },
  
  onStaffOverviewLoad: function() {
    var data = APP.parseResponse(this.responseText);
    
    if (data.status === 'success') {
      APP.showStaffWidgetData(this._action, data.data);
    }
    else {
      APP.showWidgetError(this._action, data.message);
    }
  },
  
  showWidgetError: function(action, error) {
    console.log(error);
    $.id('js-widget-' + action).innerHTML = '<span class="widget-error">Error</span>';
  },
  
  showStaffWidgetData: function(action, json) {
    var tbl, row, cell, k, cnt = $.id('js-widget-' + action);
    
    if (!cnt) {
      return;
    }
    
    cnt.innerHTML = '';
    
    tbl = $.el('table');
    
    tbl.className = 'widget-tbl';
    
    for (k in json) {
      row = $.el('tr');
      
      cell = $.el('td');
      cell.innerHTML = '<a href="/manager/staffroster?action=extra_logs&user=' + k + '">' + k + '</a>';
      
      row.appendChild(cell);
      
      cell = $.el('td');
      cell.textContent = '×' + json[k];
      
      row.appendChild(cell);
      
      tbl.appendChild(row);
    }
    
    cnt.appendChild(tbl);
  }
};

APP.init();
