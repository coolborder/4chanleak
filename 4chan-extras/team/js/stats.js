'use strict';

var APP = {
  MAX_XHR: 5,
  
  init: function() {
    this.statsData = null;
    this.xhrs = [];
    this.xhrProtos = [];
    this.xhrCount = 0;
    this.xhrTotal = 0;
    this.xhrActiveCount = 0;
    this.xhrResults = [];
    this.xhrInterval = null;
    
    Tip.init();
    
    $.on(document, 'DOMContentLoaded', APP.run);
  },
  
  run: function() {
    var el;
    
    $.off(document, 'DOMContentLoaded', APP.run);
    
    $.removeClass(document.body, 'has-backdrop');
    
    $.on($.id('board-select'), 'change', APP.onBoardChange);
    
    Chart.defaults.global.animation = false;
    Chart.defaults.global.tooltipXPadding = 3;
    Chart.defaults.global.tooltipYPadding = 3;
    Chart.defaults.global.tooltipCornerRadius = 3;
    Chart.defaults.Pie.segmentStrokeWidth = 1;
    Chart.defaults.Pie.tooltipTemplate = APP.tipTemplate;
    Chart.defaults.global.customTooltips = APP.tipFunc;
    
    if (el = $.id('stats-data')) {
      APP.statsData = JSON.parse(el.textContent);
      
      APP.plotReplyTypes();
      APP.plotFileTypes();
      APP.plotSINAD();
      //APP.plotReportStats();
      APP.plotPostingRates();
    }
    else if (document.body.hasAttribute('data-global')) {
      APP.generateGlobalStats();
    }
    else if (document.body.hasAttribute('data-monthly')) {
      APP.plotMonthlyStats();
    }
  },
  
  tipTemplate: function(data) {
    return data.label + ': ' + data.value;
  },
  
  prettyNum: function(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  },
  
  tipFunc: function(tooltip) {
    var canvas, el;
    
    Tip.hide();
    
    if (tooltip === false) {
      return;
    }
    
    canvas = tooltip.chart.canvas;
    
    el = Tip.showCustom(
      APP.prettyNum(tooltip.text),
      canvas.offsetLeft + tooltip.x,
      canvas.offsetTop + tooltip.y
    );
    
    $.addClass(el, 'tip-chart');
  },
  
  roundVal: function(val, total) {
    return Math.round(val / total * 1000) / 10;
  },
  
  onBoardChange: function() {
    var el = this.options[this.selectedIndex];
    
    if (el.value) {
      $.addClass(document.body, 'has-backdrop');
      
      location.href = '?board=' + el.value;
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
  
  generateGlobalStats: function() {
    var i, el, nodes, q;
    
    $.addClass(document.body, 'has-backdrop');
    
    APP.xhrCount = 0;
    APP.xhrTotalCount = 0;
    APP.xhrActiveCount = 0;
    
    APP.xhrResults = [];
    APP.xhrProtos = [];
    APP.xhrs = [];
    
    q = '?json=1&board=';
    
    nodes = $.id('board-select').options;
    
    APP.updateXhrProgress();
    
    for (i = 0; el = nodes[i]; ++i) {
      if (!el.value || el.value === 'global' || el.value === 'monthly') {
        continue;
      }
      
      ++APP.xhrCount;
      
      APP.xhrProtos.push(q + el.value);
    }
    
    APP.xhrTotalCount = APP.xhrCount;
    
    APP.tryPopXhr();
  },
  
  tryPopXhr: function() {
    var callbacks;
    
    if (!APP.xhrProtos.length) {
      return;
    }
    
    APP.xhrInterval = setTimeout(APP.tryPopXhr, 100);
    
    if (APP.xhrActiveCount >= APP.MAX_XHR) {
      return;
    }
    
    ++APP.xhrActiveCount;
    
    callbacks = {
      onload: APP.onStatXhrLoad,
      onerror: APP.onStatXhrError,
      onloadend: APP.onStatXhrLoadEnd
    };
    
    APP.xhrs.push($.xhr('GET', APP.xhrProtos.pop(), callbacks));
    
  },
  
  onStatXhrLoad: function() {
    var resp;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.xhrResults.push(resp.data);
    }
    else {
      APP.abortStatXhrs();
      alert(resp.message);
    }
  },
  
  onStatXhrError: function() {
    APP.abortStatXhrs();
    alert('Connection Error');
  },
  
  onStatXhrLoadEnd: function() {
    APP.xhrCount--;
    APP.xhrActiveCount--;
    
    APP.updateXhrProgress();
    
    if (APP.xhrCount === 0) {
      APP.xhrs = [];
      APP.xhrProtos = [];
    }
  },
  
  abortStatXhrs: function() {
    var i, xhr;
    
    clearInterval(APP.xhrInterval);
    
    for (i = 0; xhr = APP.xhrs[i]; ++i) {
      xhr.abort();
    }
  },
  
  updateXhrProgress: function() {
    var perc, cur, total;
    
    cur = APP.xhrCount;
    total = APP.xhrTotalCount;
    
    if (total === 0) {
      Feedback.notify('Processing… 0%', false);
      return;
    }
    
    if (cur <= 0) {
      APP.onXhrResultsReady();
      return;
    }
    
    perc = 100 - (0 | (cur / total * 100 + 0.5));
    
    Feedback.replaceMessage('Processing… ' + perc + '%');
  },
  
  onXhrResultsReady: function() {
    var i, k, len, data, stats;
    
    Feedback.hideMessage();
    
    stats = null;
    
    for (i = 0; data = APP.xhrResults[i]; ++i) {
      if (stats === null) {
        stats = data;
        continue;
      }
      
      stats.replyTypes.imageReplies += data.replyTypes.imageReplies;
      stats.replyTypes.textReplies += data.replyTypes.textReplies;
      
      for (k in data.fileTypes) {
        if (stats.fileTypes[k] === undefined) {
          stats.fileTypes[k] = 0;
        }
        stats.fileTypes[k] += data.fileTypes[k];
      }
      
      for (k = 0, len = data.newThreads.length; k < len; ++k) {
        if (stats.newThreads[k] === undefined) {
          stats.newThreads[k] = 0;
        }
        stats.newThreads[k] += data.newThreads[k];
      }
      
      for (k = 0, len = data.newReplies.length; k < len; ++k) {
        if (stats.newReplies[k] === undefined) {
          stats.newReplies[k] = 0;
        }
        stats.newReplies[k] += data.newReplies[k];
      }
      
      for (k = 0, len = data.newReplies.length; k < len; ++k) {
        if (stats.newReplies[k] === undefined) {
          stats.newReplies[k] = 0;
        }
        stats.newReplies[k] += data.newReplies[k];
      }
      
      stats.livePosts += data.livePosts;
      stats.archivedPosts += data.archivedPosts;
      stats.reports += data.reports;
    }
    
    APP.statsData = stats;
    
    APP.plotReplyTypes();
    APP.plotFileTypes();
    APP.plotPostingRates();
    
    $.id('stats-live-posts').textContent
      = stats.livePosts.toLocaleString('en-US');
    
    $.id('stats-archived-posts').textContent
      = stats.archivedPosts.toLocaleString('en-US');
    
    $.id('stats-reports').textContent
      = stats.reports.toLocaleString('en-US');
    
    $.removeClass(document.body, 'has-backdrop');
  },
  
  plotReplyTypes: function() {
    var params, data, ctx, total;
    
    ctx = $.id('reply-types-chart').getContext('2d');
    
    data = APP.statsData.replyTypes;
    
    total = data.imageReplies + data.textReplies;
    
    params = [
      {
        value: data.imageReplies,
        color: '#F7464A',
        highlight: '#FF5A5E',
        label: 'Image (' + APP.roundVal(data.imageReplies, total) + '%)'
      },
      {
        value: data.textReplies,
        color: '#46BFBD',
        highlight: '#5AD3D1',
        label: 'Text (' + APP.roundVal(data.textReplies, total) + '%)'
      }
    ];
    
    new Chart(ctx).Pie(params);
  },
  
  plotFileTypes: function() {
    var k, cid, params, data, ctx, colors, total;
    
    colors = [
      [ '#F7464A', '#FF5A5E' ],
      [ '#46BFBD', '#5AD3D1' ],
      [ '#FDB45C', '#FFC870' ],
      [ '#1d8dc4', '#adcad9' ],
      [ '#57bf47', '#7ade6a' ]
    ];
    
    params = [];
    
    ctx = $.id('file-types-chart').getContext('2d');
    
    data = APP.statsData.fileTypes;
    
    cid = 0;
    
    total = 0;
    
    for (k in data) {
      total += data[k];
    }
    
    for (k in data) {
      if (cid >= colors.length) {
        cid = 0;
      }
      
      params.push({
        value: data[k],
        label: k.slice(1).toUpperCase()
          + ' (' + APP.roundVal(data[k], total) + '%)',
        color: colors[cid][0],
        highlight: colors[cid][1]
      });
      
      ++cid;
    }
    
    new Chart(ctx).Pie(params);
  },
  
  plotSINAD: function() {
    var params, data, key, ctx, total, cid, colors;
    
    data = APP.statsData.sinad;
    
    if (!data) {
      return;
    }
    
    ctx = $.id('sinad-chart').getContext('2d');
    
    total = 0;
    
    colors = [
      [ '#1d8dc4', '#adcad9' ],
      [ '#46BFBD', '#5AD3D1' ]
    ];
    
    params = [];
    
    cid = 0;
    
    for (key in data) {
      total += data[key];
    }
    
    for (key in data) {
      params.push(
        {
          value: data[key],
          color: colors[cid][0],
          highlight: colors[cid][1],
          label: $.capitalise(key) + ' (' + APP.roundVal(data[key], total) + '%)'
        }
      );
      
      ++cid;
    }
    
    new Chart(ctx).Pie(params);
  },
  /*
  plotReportStats: function() {
    var params, data, ctx;
    
    data = APP.statsData.reports;
    
    if (!data) {
      return;
    }
    
    ctx = $.id('reports-chart').getContext('2d');
    
    params = [
      {
        value: data.missed_count,
        color: '#1d8dc4',
        highlight: '#adcad9',
        label: 'Unattended (' + data.missed_ratio + '%)'
      },
      {
        value: data.clear_count,
        color: '#46BFBD',
        highlight: '#5AD3D1',
        label: 'Cleared (' + data.clear_ratio + '%)'
      },
      {
        value: data.del_count,
        color: '#FDB45C',
        highlight: '#FFC870',
        label: 'Deleted (' + data.del_ratio + '%)'
      }
    ];
    
    new Chart(ctx).Pie(params);
  },
  */
  plotPostingRates: function() {
    var k, charts, ctx, labels, params;
    
    labels = [];
    
    for (var i = 0; i < 24; ++i) {
      labels.push(('0' + i).slice(-2) + ':00');
    }
    
    charts = {
      threads: APP.statsData.newThreads,
      replies: APP.statsData.newReplies
    };
    
    for (k in charts) {
      ctx = $.id(k + '-chart').getContext('2d');
      
      params = {
        labels: labels,
        datasets: [{
          fillColor: "rgba(151,187,205,0.2)",
          strokeColor: "rgba(151,187,205,1)",
          pointColor: "rgba(151,187,205,1)",
          pointStrokeColor: "#fff",
          pointHighlightFill: "#fff",
          pointHighlightStroke: "rgba(151,187,205,1)",
          data: charts[k]
        }]
      };
      
      new Chart(ctx).Line(params);
    }
  },
  
  plotMonthlyStats: function() {
    var el, m, data, ctx, labels, params, values;
    
    el = $.id('monthly-data');
    data = JSON.parse(el.textContent);
    
    labels = [];
    values = [];
    
    for (var i = 0; m = data[i]; ++i) {
      labels.push(m[0]);
      values.push(m[1]);
    }
    
    ctx = $.id('monthly-chart').getContext('2d');
    
    params = {
      labels: labels,
      datasets: [{
        fillColor: "rgba(151,187,205,0.2)",
        strokeColor: "rgba(151,187,205,1)",
        pointColor: "rgba(151,187,205,1)",
        pointStrokeColor: "#fff",
        pointHighlightFill: "#fff",
        pointHighlightStroke: "rgba(151,187,205,1)",
        data: values
      }]
    };
    
    new Chart(ctx).Bar(params);
  }
};

var Feedback = {
  messageTimeout: null,
  
  showMessage: function(msg, type, timeout, onClick) {
    var el;
    
    Feedback.hideMessage();
    
    el = document.createElement('div');
    el.id = 'feedback';
    el.title = 'Dismiss';
    el.innerHTML = '<span class="feedback feedback-' + type + '">' + msg + '</span>';
    
    $.on(el, 'click', onClick || Feedback.hideMessage);
    
    document.body.appendChild(el);
    
    if (timeout) {
      Feedback.messageTimeout = setTimeout(Feedback.hideMessage, timeout);
    }
  },
  
  replaceMessage: function(msg) {
    var el = $.id('feedback');
    
    if (el) {
      el.firstElementChild.innerHTML = msg;
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
  
  error: function(msg, timeout, onClick) {
    if (timeout === undefined) {
      timeout = 5000;
    }
    
    Feedback.showMessage(msg, 'error', timeout, onClick);
  },
  
  notify: function(msg, timeout, onClick) {
    if (timeout === undefined) {
      timeout = 3000;
    }
    
    Feedback.showMessage(msg, 'notify', timeout, onClick);
  }
};

/**
 * Init
 */
APP.init();
