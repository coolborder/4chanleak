'use strict';

var APP = {
  POST_DELETED: 1,
  FILE_DELETED: 2,
  
  init: function() {
    this.xhrs = [];
    this.xhrCount = 0;
    this.resultCount = 0;
    this.searchResults = [];
    this.deletedResults = {};
    this.searchAborted = false;
    this.groupBy = null;
    this.isArchived = false;
    
    this.preDelDelay = 3000;
    
    this.banXhr = [];
    
    this.delErrors = [];
    this.delXhrs = [];
    this.delXhrCount = 0;
    
    this.maxResults = null;
    this.maxBoardResults = null;
    this.maxIPBans = null;
    
    this.currentParams = null;
    
    this.banTemplatesCache = null;
    this.banTemplatesLoading = false;
    
    this.savedSearch = [];
    
    this.clearPartialStatus();
    
    this.loadSavedSearch();
    
    this.clickCommands = {
      'ban-post': APP.onBanPostClick,
      'thread-opts': APP.onThreadOptsClick,
      'pre-del-post': APP.onPreDelPostClick,
      'del-post': APP.onDelPostClick,
      'pre-del-all': APP.onPreDelAllClick,
      'del-all': APP.onDelAllClick,
      'ban-multi': APP.onMultiBanClick,
      'submit-ban': APP.onSubmitBanClick,
      'cancel-ban': APP.onCancelBanClick,
      'pre-del-grp': APP.onPreDelGrpClick,
      'del-grp': APP.onDelGrpClick,
      'toggle-grp': APP.onToggleGrpClick,
      'toggle-post': APP.onTogglePostClick,
      'toggle-ih': APP.onToggleIHClick,
      'toggle-dt': APP.onToggleDTClick,
      'save-search': APP.onSaveSearchClick,
      'run-search': APP.onRunSearchClick,
      'del-search': APP.onDelSearchClick,
    };
    
    this.protocol = 'https://';
    this.thumbServer = 'i.4cdn.org';
    this.imageServer = 'i.4cdn.org';
    this.imageServer2 = 'is2.4chan.org';
    this.fileDeleted = 's.4cdn.org/image/filedeleted-res.gif';
    
    this.capcodes = {
      mod: 'Moderator',
      manager: 'Manager',
      founder: 'Founder',
      admin: 'Administrator',
      developer: 'Developer',
      verified: 'Verified'
    };
    
    Tip.init();
    
    if (localStorage.getItem('dark-theme')) {
      $.addClass($.docEl, 'dark-theme');
    }
    
    $.on(window, 'hashchange', APP.onHashChange);
    $.on(document, 'click', APP.onClick);
    $.on(document, 'DOMContentLoaded', APP.run);
    $.on(window, 'storage', APP.syncSavedSearch);
  },
  
  linkToImage: function(board, file, ext) {
    return '//' + this.imageServer + '/' + board + '/' + file + ext;
  },
  
  linkToThumb: function(board, file) {
    return '//' + this.thumbServer + '/' + board + '/' + file + 's.jpg';
  },
  
  linkToSWF: function(fileName) {
    return '//' + this.imageServer + '/f/' + fileName + '.swf';
  },
  
  linkToPost: function(board, pid, tid) {
    return this.protocol + 'boards.' + $L.d(board) + '/' + board + '/thread/'
      + (+tid !== 0 ? (tid + '#p' + pid) : pid);
  },
  
  run: function() {
    $.off(document, 'DOMContentLoaded', APP.run);
    
    if (!localStorage.getItem('search-no-ih')) {
      ImageHover.init();
    }
    else {
      $.id('cfg-cb-ih').checked = false;
    }
    
    if (localStorage.getItem('dark-theme')) {
      $.id('cfg-cb-dt').checked = true;
    }
    
    APP.maxResults = +document.body.getAttribute('data-maxres');
    APP.maxBoardResults = +document.body.getAttribute('data-maxboardres');
    APP.maxIPBans = +document.body.getAttribute('data-maxipbans');
    
    APP.buildSavedSearchList();
    
    APP.onHashChange();
    
    $.on($.id('search-btn'), 'click', APP.onSearchClick);
    $.on($.id('reset-btn'), 'click', APP.onSearchReset);
    $.on($.id('search-form'), 'submit', APP.onSearchSubmit);
    $.on($.id('ban-form'), 'submit', APP.onBanSubmit);
    $.on($.id('group-field'), 'change', APP.onGroupChange);
    $.on($.id('group-sort-field'), 'change', APP.onGroupSortChange);
  },
  
  onToggleIHClick: function() {
    var el = $.id('cfg-cb-ih');
    
    if (el.checked !== ImageHover.enabled) {
      if (el.checked) {
        ImageHover.init();
        localStorage.removeItem('search-no-ih');
      }
      else {
        ImageHover.disable();
        localStorage.setItem('search-no-ih', '1');
      }
    }
  },
  
  onToggleDTClick: function() {
    var el = $.id('cfg-cb-dt');
    
    if (el.checked !== $.hasClass($.docEl, 'dark-theme')) {
      if (el.checked) {
        $.addClass($.docEl, 'dark-theme');
        localStorage.setItem('dark-theme', '1');
      }
      else {
        $.removeClass($.docEl, 'dark-theme');
        localStorage.removeItem('dark-theme');
      }
    }
  },
  
  hasPartialResults: function() {
    return APP.partial.global || APP.partial.error || APP.partial.boards[0];
  },
  
  getPostUID: function(btn) {
    var el;
    
    el = $.cls('post-sel', btn.parentNode)[0];
    
    if (!el) {
      return null;
    }
    
    return {
      board: el.getAttribute('data-board'),
      pid: el.getAttribute('data-pid')
    };
  },
  
  onHashChange: function(e) {
    var i, el, nodes, params;
    
    if (!location.hash || location.hash === '#') {
      if (APP.searchResults.length) {
        APP.onSearchReset();
      }
      return;
    }
    
    // Chrome
    if (location.hash[1] === '%' || location.hash[2] === '%') {
      e && e.preventDefault();
      
      location.hash = params = decodeURIComponent(location.hash.slice(1));
      
      if (location.hash[2] !== '%') {
        return;
      }
    }
    else {
      params = location.hash.slice(1);
    }
    
    try {
      params = JSON.parse(params);
    }
    catch (err) {
      Feedback.error('Invalid parameters.');
      return;
    }
    
    nodes = $.cls('s-p', $.id('search-form'));
    
    for (i = 0; el = nodes[i]; ++i) {
      if (params.hasOwnProperty(el.name)) {
        if (el.type === 'text' || el.type === 'hidden' || el.type === 'select-one') {
          el.value = params[el.name];
        }
        else if (el.type === 'checkbox') {
          el.checked = true;
        }
      }
      else {
        if (el.type === 'text' || el.type === 'hidden') {
          el.value = '';
        }
        else if (el.type === 'checkbox') {
          el.checked = false;
        }
      }
    }
    
    if (params.boards) {
      $.id('boards-field').value = params.boards;
    }
    
    nodes = $.id('group-field').options;
    
    if (params.group) {
      for (i = 0; el = nodes[i]; ++i) {
        if (el.value === params.group) {
          el.selected = true;
        }
        else {
          el.selected = false;
        }
      }
    }
    else {
      for (i = 0; el = nodes[i]; ++i) {
        el.selected = false;
      }
      nodes[0].selected = true;
    }
    
    APP.onSearchSubmit();
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
  
  showFileTip: function(t) {
    return $.escapeHTML(t.getAttribute('data-meta')
      + ', ' + $.prettyBytes(t.getAttribute('data-fsize')));
  },
  
  /**
   * Thread options wndow
   */
  onThreadOptsClick: function(btn) {
    var uid;
    
    uid = APP.getPostUID(btn);
    
    if (!uid) {
      return;
    }
    
    window.open(
      'https://sys.4chan.org/' + uid.board
        + '/admin?mode=admin&admin=opt&id=' + uid.pid,
      '_blank', 'width=400,height=275'
    );
  },
  
  /**
   * Del
   */
  onPreDelPostClick: function(btn) {
    $.addClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'del-post');
    Tip.show(btn, '<span id="del-conf-tip">Confirm</div>');
    setTimeout(APP.resetDelConfirmBtn, APP.preDelDelay, btn);
  },
  
  resetDelConfirmBtn: function(btn) {
    var el;
    
    if (!btn) {
      return;
    }
    
    if (el = $.id('del-conf-tip')) {
      if (btn.matches ? btn.matches(':hover') : btn.matchesSelector(':hover')) {
        Tip.hide();
      }
    }
    
    $.removeClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'pre-del-post');
  },
  
  onDelPostClick: function(btn) {
    var el, uid;
    
    if (uid = APP.getPostUID(btn)) {
      el = $.id(uid.board + '-' + uid.pid);
      
      if ($.hasClass(el, 'processing')) {
        return;
      }
      
      $.addClass(el, 'processing');
      
      APP.deletePost(
        uid.board,
        uid.pid,
        btn.hasAttribute('data-fileonly'),
        APP.isArchived
      );
    }
  },
  
  deletePost: function(board, pid, fileOnly, isArchived) {
    var path, params = 'mode=' + (isArchived ? 'arcdel' : 'usrdel')
      + '&' + pid + '=delete&pwd=janitorise';
    
    if (fileOnly) {
      params += '&onlyimgdel=on';
    }
    
    path = '/imgboard.php';
    
    $.xhr('POST', 'https://sys.4chan.org/' + board + path,
    //$.xhr('GET', '?action=dummy_ok',
      {
        onload: APP.onPostDeleted,
        onerror: APP.onPostDeleteError,
        withCredentials: true,
        board: board,
        pid: pid,
        fileOnly: fileOnly,
      },
      params
    );
  },
  
  onPostDeleteError: function() {
    var el = $.id(this.board + '-' + this.pid);
    
    if (!el) {
      return;
    }
    
    $.removeClass(el, 'processing');
    
    console.log("Couldn't delete " + this.board + ' ' + this.pid);
  },
  
  onPostDeleted: function() {
    var key, el, resp;
    
    key = this.board + '-' + this.pid;
    
    if (!(el = $.id(key))) {
      return;
    }
    
    if (/Updating index|Can't find the post/.test(this.responseText)) {
      $.removeClass(el, 'processing');
      
      if (!this.fileOnly) {
        $.addClass(el, 'disabled');
        
        APP.deletedResults[key] = APP.POST_DELETED;
        
        if (el = $.cls('post-sel', el)[0]) {
          el.disabled = true;
          el.checked = false;
        }
      }
      else if (el = $.cls('post-thumb', el)[0]) {
        APP.deletedResults[key] = APP.FILE_DELETED;
        
        el.src = '//' + APP.fileDeleted;
        $.removeClass(el, 'post-thumb');
      }
    }
    else {
      if (resp = this.responseText.match(/"errmsg"[^>]*>(.*?)<\/span/)) {
        Feedback.error(resp[1]);
      }
      
      APP.onPostDeleteError.call(this);
    }
  },
  
  /**
   * Ban window
   */
  onBanPostClick: function(btn) {
    var uid;
    
    uid = APP.getPostUID(btn);
    
    if (!uid) {
      return;
    }
    
    window.open(
      'https://sys.4chan.org/' + uid.board
        + '/admin?mode=admin&admin=ban&id=' + uid.pid,
      '_blank', 'width=400,height=445'
    );
  },
  
  /**
   * Multi ban
   */
  showBanForm: function(btn, ips) {
    var el, rect, el2;
    
    APP.closeBanForm();
    
    APP.activeMultiBanBtn = btn;
    
    rect = btn.getBoundingClientRect();
    
    el = $.id('ban-form-cnt');
    
    if (ips.length > APP.maxIPBans) {
      $.id('js-btn-no-reverse').checked = true;
    }
    
    if (!APP.banTemplatesCache) {
      APP.loadBanTemplates();
    }
    else {
      APP.buildBanTemplates();
    }
    
    el2 = $.id('ban-ips-field');
    el2.value = JSON.stringify(ips);
    el2.setAttribute('data-size', ips.length);
    
    $.removeClass(el, 'hidden');
    
    el.style.top = rect.top - el.offsetHeight + window.pageYOffset - 10 + 'px';
    el.style.right = ($.docEl.clientWidth - rect.right) + 'px';
    
    if (el.offsetTop < window.pageYOffset) {
      el.scrollIntoView(true);
    }
  },
  
  closeBanForm: function() {
    APP.activeMultiBanBtn = null;
    $.id('ban-ips-field').value = '';
    $.addClass($.id('ban-form-cnt'), 'hidden');
  },
  
  onMultiBanClick: function(btn) {
    var cnt, ips;
    
    if (btn.hasAttribute('data-all')) {
      cnt = null;
    }
    else {
      cnt = btn.parentNode.parentNode.parentNode;
      
      if (!$.hasClass(cnt, 'res-cnt')) {
        console.log('Container mismatch');
        return;
      }
    }
    
    ips = APP.getSelectedIPs($.cls('post-sel', cnt));
    
    if (!ips) {
      Feedback.error('Nothing to do.');
      return;
    }
    
    APP.showBanForm(btn, ips);
  },
  
  onSubmitBanClick: function() {
    $.id('ban-btn-dummy').click();
  },
  
  onCancelBanClick: function() {
    APP.closeBanForm();
  },
  
  onBanSubmit: function(e) {
    var i, el, nodes, data, size;
    
    e && e.preventDefault();
    
    if ($.hasClass(this, 'hidden')) {
      return;
    }
    
    size = +$.id('ban-ips-field').getAttribute('data-size');
    
    if (size > APP.maxIPBans && !$.id('js-btn-no-reverse').checked) {
      Feedback.error("Too many IPs to ban. Use the 'No reverse' option to bypass the limit.");
      return;
    }
    
    data = {};
    
    nodes = $.cls('ban-field');
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.type === 'checkbox') {
        data[el.name] = +el.checked;
      }
      else {
        data[el.name] = el.value;
      }
    }
    
    if (APP.currentParams) {
      data['params'] = JSON.stringify(APP.currentParams);
    }
    
    if ($.id('js-btn-no-reverse').checked) {
       data['no_reverse'] = 1;
    }
    
    APP.banIps(data);
  },
  
  banIps: function(data) {
    APP.toggleBackDrop(true);
    
    Feedback.notify('Processing…', false);
    
    data.action = 'ban';
    data._tkn = $.getToken();
    
    $.xhr('POST', '', {
      onload: APP.onMultiBanLoaded,
      onerror: APP.onMultiBanError
    },
    data);
  },
  
  onMultiBanLoaded: function() {
    var resp;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      Feedback.hideMessage();
      APP.closeBanForm();
    }
    else {
      Feedback.error(resp.message);
    }
    
    console.log(this.responseText);
    
    APP.toggleBackDrop(false);
  },
  
  onMultiBanError: function() {
    Feedback.error('Something went wrong.');
    APP.toggleBackDrop(false);
  },
  
  /**
   * Multiban templates
   */
  loadBanTemplates: function() {
    if (APP.banTemplatesLoading) {
      return;
    }
    
    APP.banTemplatesLoading = true;
    
    $.xhr('GET', '?action=get_templates', {
      onload: APP.onBanTemplatesLoaded,
      onerror: APP.onBanTemplatesError
    });
  },
  
  onBanTemplatesError: function() {
    APP.banTemplatesLoading = false;
    Feedback.error("Could't load ban templates");
  },
  
  onBanTemplatesLoaded: function() {
    var resp;
    
    APP.banTemplatesLoading = false;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      APP.banTemplatesCache = resp.data;
      APP.buildBanTemplates();
    }
    else {
      Feedback.error(resp.message);
    }
  },
  
  buildBanTemplates: function() {
    let sel = $.id('ban-templates-sel');
    
    if (!sel || !APP.activeMultiBanBtn || !APP.banTemplatesCache) {
      return;
    }
    
    let cnt = APP.activeMultiBanBtn.parentNode.parentNode.parentNode;
    
    let postMap = APP.getSelectedPosts($.cls('post-sel', cnt));
    
    let boards = ['global'];
    
    if (postMap) {
      let keys = Object.keys(postMap);
      
      if (keys.length === 1) {
        boards.unshift(keys[0]);
      }
    }
    
    if (sel.childElementCount > 1) {
      sel.innerHTML = '<option></option>';
    }
    else {
      sel.children[0].textContent = '';
      $.on(sel, 'change', APP.onBanTemplateChanged);
    }
    
    for (let board of boards) {
      let templates = APP.banTemplatesCache[board];
      
      if (!templates) {
        continue;
      }
      
      for (let i = 0; i < templates.length; ++i) {
        let tpl = templates[i];
        
        const option = $.el('option');
        option.value = `${board}-${i}`;
        option.innerHTML = tpl.name;
        
        sel.appendChild(option);
      }
    }
  },
  
  onBanTemplateChanged: function() {
    let sel = $.id('ban-templates-sel');
    
    if (!sel) {
      return;
    }
    
    if (!APP.banTemplatesCache) {
      return;
    }
    
    let val = sel.value;
    
    if (!val) {
      return;
    }
    
    val = val.split('-');
    
    let board = val[0];
    let idx = +val[1];
    
    let tpl = APP.banTemplatesCache[board][idx];
    
    $.id('js-ban-reason').value = tpl.reason;
    $.id('js-ban-days').value = tpl.days;
    $.id('js-btn-global').checked = !!tpl.global;
  },
  
  /**
   * Grp del
   */
  onPreDelGrpClick: function(btn) {
    $.removeClass(btn, 'btn-other');
    $.addClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'del-grp');
    Tip.show(btn, '<span id="del-conf-tip">Confirm</div>');
    setTimeout(APP.resetGrpDelConfirmBtn, APP.preDelDelay, btn);
  },
  
  resetGrpDelConfirmBtn: function(btn) {
    var el;
    
    if (!btn) {
      return;
    }
    
    if (el = $.id('del-conf-tip')) {
      if (btn.matches ? btn.matches(':hover') : btn.matchesSelector(':hover')) {
        Tip.hide();
      }
    }
    
    $.addClass(btn, 'btn-other');
    $.removeClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'pre-del-grp');
  },
  
  onDelGrpClick: function(btn) {
    var cnt, postMap;
    
    cnt = btn.parentNode.parentNode.parentNode;
    
    if (!$.hasClass(cnt, 'res-cnt')) {
      console.log('Container mismatch');
      return;
    }
    
    if (APP.hasSelectedStickies(cnt)) {
      Feedback.error('Some threads are stickied. Unsticky them first.');
      return;
    }
    
    postMap = APP.getSelectedPosts($.cls('post-sel', cnt));
    
    if (!postMap) {
      Feedback.error('Nothing to do.');
      return;
    }
    
    APP.deletePosts(postMap, APP.isArchived);
  },
  
  /**
   * Del all
   */
  onPreDelAllClick: function(btn) {
    $.removeClass(btn, 'btn-other');
    $.addClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'del-all');
    Tip.show(btn, '<span id="del-conf-tip">Confirm</div>');
    setTimeout(APP.resetDelAllConfirmBtn, APP.preDelDelay, btn);
  },
  
  resetDelAllConfirmBtn: function(btn) {
    var el;
    
    if (!btn) {
      return;
    }
    
    if (el = $.id('del-conf-tip')) {
      if (btn.matches ? btn.matches(':hover') : btn.matchesSelector(':hover')) {
        Tip.hide();
      }
    }
    
    $.addClass(btn, 'btn-other');
    $.removeClass(btn, 'btn-deny');
    btn.setAttribute('data-cmd', 'pre-del-all');
  },
  
  onDelAllClick: function(btn) {
    var postMap;
    
    if (APP.hasSelectedStickies()) {
      Feedback.error('Some threads are stickied. Unsticky them first.');
      return;
    }
    
    postMap = APP.getSelectedPosts($.cls('post-sel'));
    
    APP.deletePosts(postMap, APP.isArchived, btn.hasAttribute('data-fileonly'));
  },
  
  deletePosts: function(postMap, isArchived, fileOnly) {
    var board, mode, q, callbacks, path, empty;
    
    if (APP.delXhrCount) {
      console.log('Already deleting.');
      return;
    }
    
    empty = true;
    
    for (board in postMap) {
      empty = false;
      break;
    }
    
    if (empty) {
      Feedback.error('Nothing to do.');
      return;
    }
    
    APP.delErrors = false;
    APP.toggleBackDrop(true);
    Feedback.notify('Deleting…', false);
    
    callbacks = {
      onloadend: APP.onMultiDelLoadEnd,
      onload: APP.onMultiDelLoad,
      onerror: APP.onMultiDelError,
      board: null,
      pids: null,
      withCredentials: true
    };
    
    mode = 'mode=' + (isArchived ? 'arcdel' : 'usrdel') + '&tool=search&';
    
    for (board in postMap) {
      callbacks.board = board;
      callbacks.pids = postMap[board];
      
      path = 'https://sys.4chan.org/' + board + '/imgboard.php';
      q = mode + postMap[board].join('=delete&') + '=delete&pwd=janitorise';
      
      if (fileOnly) {
        q += '&onlyimgdel=on';
      }
      
      ++APP.delXhrCount;
      
      APP.delXhrs.push($.xhr('POST', path, callbacks, q));
    }
  },
  
  onMultiDelLoadEnd: function() {
    APP.delXhrCount--;
    
    if (APP.delXhrCount <= 0) {
      APP.onPostDeletionDone();
    }
  },
  
  onMultiDelLoad: function() {
    var resp;
    
    if (/Updating index|Can't find the post/.test(this.responseText)) {
      APP.updatedDeletedPosts(this.board, this.pids);
    }
    else {
      APP.delErrors = true;
      
      if (resp = this.responseText.match(/"errmsg"[^>]*>(.*?)<\/span/)) {
        console.log(resp[1]);
      }
      else {
        console.log('Error.');
      }
    }
  },
  
  onMultiDelError: function() {
    APP.delErrors = true;
    console.log('Connection error.');
  },
  
  updatedDeletedPosts: function(board, pids) {
    var key, v, cnt, el, i, pid, deletedResults;
    
    deletedResults = APP.deletedResults;
    v = APP.POST_DELETED;
    
    for (i = 0; pid = pids[i]; ++i) {
      key = board + '-' + pid;
      
      deletedResults[key] = v;
      
      if (cnt = $.id(key)) {
        $.addClass(cnt, 'disabled');
        
        if (el = $.cls('post-sel', cnt)[0]) {
          el.checked = false;
          el.disabled = true;
        }
        
        if (el = $.cls('post-thumb', cnt)[0]) {
          el.removeAttribute('data-src');
        }
      }
    }
  },
  
  onPostDeletionDone: function() {
    if (APP.delErrors) {
      Feedback.error('Errors occurred. Not all posts could be deleted.');
    }
    else {
      Feedback.hideMessage();
    }
    APP.delErrors = false;
    APP.toggleBackDrop(false);
    APP.delXhrs = [];
  },
  
  toggleBackDrop: function(flag) {
    if (flag) {
      if (!$.hasClass(document.body, 'has-backdrop')) {
        $.addClass(document.body, 'has-backdrop');
      }
    }
    else {
      $.removeClass(document.body, 'has-backdrop');
    }
  },
  
  getSelectedIPs: function(nodes) {
    var i, el, ips, ip, ipMap;
    
    ipMap = {};
    ips = [];
    
    for (i = 0; el = nodes[i]; ++i) {
      if (!el.checked || el.disabled) {
        continue;
      }
      
      ip = el.getAttribute('data-ip');
      
      if (ip && !ipMap[ip]) {
        ips.push({
          ip: ip,
          pwd: el.getAttribute('data-pwd'),
          board: el.getAttribute('data-board'),
          pid: el.getAttribute('data-pid')
        });
        
        ipMap[ip] = true;
      }
    }
    
    if (ips.length) {
      return ips;
    }
    
    return null;
  },
  
  hasSelectedStickies: function(root) {
    var i, el, nodes;
    
    nodes = $.cls('opt-sticky', root);
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el = $.cls('post-sel', el.parentNode)[0]) {
        if (el.checked) {
          return true;
        }
      }
    }
    
    return false;
  },
  
  getSelectedPosts: function(nodes) {
    var i, el, postMap, board, pid;
    
    postMap = {};
    
    for (i = 0; el = nodes[i]; ++i) {
      if (!el.checked || el.disabled) {
        continue;
      }
      
      board = el.getAttribute('data-board');
      pid = el.getAttribute('data-pid');
      
      if (!postMap[board]) {
        postMap[board] = [];
      }
      
      postMap[board].push(pid);
    }
    
    for (i in postMap) {
      return postMap;
    }
    
    return null;
  },
  
  showResultsCtrl: function() {
    var ok, err, el;
    
    el = $.id('results-ctrl');
    
    if ($.hasClass(el, 'hidden')) {
      $.removeClass(el, 'hidden');
    }
    
    el = $.id('ban-all-btn');
    
    $.removeClass(el, 'hidden');
    
    if (APP.isArchived) {
      $.addClass(el, 'hidden');
    }
    
    ok = err = '';
    
    if (APP.hasPartialResults()) {
      err = 'Partial results. ';
      
      if (APP.partial.error) {
        err += 'Errors occurred.';
      }
      else if (APP.partial.boards[0]) {
        err += 'Some boards returned too many posts.';
      }
      else if (APP.partial.global) {
        err += 'Maximum number of results reached.';
      }
    }
    
    if (APP.resultCount) {
      ok = 'Found ' + APP.resultCount
        + ' post' + $.pluralise(APP.resultCount)
        + ' on ' + APP.searchResults.length
        + ' board' + $.pluralise(APP.searchResults.length);
    }
    
    $.id('js-results-ok').textContent = ok;
    $.id('js-results-err').textContent = err;
  },
  
  hideResultsCtrl: function() {
    var el = $.id('results-ctrl');
    
    if (!$.hasClass(el, 'hidden')) {
      $.addClass(el, 'hidden');
    }
  },
  
  onToggleGrpClick: function(btn) {
    var i, el, nodes, grpCnt, flag;
    
    grpCnt = btn.parentNode.parentNode;
    
    flag = btn.checked;
    
    nodes = $.cls('post-sel', grpCnt);
    
    for (i = 0; el = nodes[i]; ++i) {
      if (!el.disabled) {
        el.checked = flag;
      }
    }
  },
  
  onTogglePostClick: function(btn) {
    var i, el, nodes, grpCnt, grpSel, checkedCount;
    
    grpCnt = btn.parentNode.parentNode.parentNode;
    grpSel = $.cls('grp-sel', grpCnt)[0];
    nodes = $.cls('post-sel', grpCnt);
    
    checkedCount = 0;
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.checked) {
        ++checkedCount;
      }
    }
    
    grpSel.checked = checkedCount === nodes.length;
    grpSel.indeterminate = checkedCount > 0 && !grpSel.checked;
  },
  
  showNameTip: function(el) {
    el = el.parentNode;
    
    if (el.textContent.length > 25) {
      return el.innerHTML;
    }
    
    return null;
  },
  
  showFieldTip: function(el) {
    var tip = $.id('tip-' + el.getAttribute('data-type'));
    
    if (!tip) {
      return null;
    }
    
    return tip.innerHTML;
  },
  
  onGroupChange: function() {
    var url_frag, grp;
    
    if (!APP.searchResults.length) {
      return;
    }
    
    url_frag = APP.currentParams || {};
    
    grp = APP.getGroupOptionTag();
    
    if (grp.previousElementSibling) {
      url_frag['group'] = grp.value;
    }
    else {
      delete url_frag['group'];
    }
    
    history.replaceState(null, '', '#' + JSON.stringify(url_frag));
    
    Feedback.notify('Building…', false);
    setTimeout(APP.onSearchResultsReady, 30);
  },
  
  onGroupSortChange: function() {
    var url_frag;
    
    if (!APP.searchResults.length) {
      return;
    }
    
    url_frag = APP.currentParams || {};
    
    if (this.checked) {
      url_frag['gss'] = 1;
    }
    else {
      delete url_frag['gss'];
    }
    
    history.replaceState(null, '', '#' + JSON.stringify(url_frag));
    
    Feedback.notify('Building…', false);
    setTimeout(APP.onSearchResultsReady, 30);
  },
  
  onSearchClick: function() {
    if (APP.xhrCount !== 0) {
      APP.searchAborted = true;
      return;
    }
    $.id('search-btn-dummy').click();
  },
  
  onSearchReset: function() {
    if (APP.xhrCount !== 0) {
      return;
    }
    
    APP.clearSearchState();
    
    APP.hideResultsCtrl();
    
    $.id('search-results').textContent = '';
    $.id('search-form').reset();
    
    if (location.hash) {
      location.hash = '';
    }
  },
  
  onSearchSubmit: function(e) {
    var i, el, nodes, q, b, boards, boards_raw, valid_boards, callbacks, url_frag, grp;
    
    e && e.preventDefault();
    
    if (APP.xhrCount !== 0) {
      return;
    }
    
    APP.closeBanForm();
    APP.clearPartialStatus();
    
    if (el = $.id('no-results')) {
      el.parentNode.removeChild(el);
    }
    
    APP.parseFileUID();
    APP.parseThreadURL();
    APP.parseFileSize();
    APP.parsePassRef();
    
    APP.isArchived = $.id('arc-field').checked;
    
    valid_boards = JSON.parse($.id('data-boards').textContent);
    
    boards = boards_raw = $.id('boards-field').value.toLowerCase();
    
    if ($.id('js-loc-field').value !== '') {
      if (boards === '' || $.id('js-tid-field').value === '') {
        Feedback.error($.id('js-err-loc').textContent);
        return;
      }
    }
    
    if ($.id('js-phash-field').value !== '' && APP.isArchived) {
      Feedback.error($.id('js-err-phash').textContent);
      return;
    }
    
    if (boards === '') {
      boards = valid_boards;
    }
    else {
      let op_not = false;
      
      if (boards.indexOf('!') !== -1) {
        op_not = true;
        boards = boards.replaceAll('!', '');
      }
      
      boards = boards.split(/[^a-z0-9!]+/);
      
      for (i = 0; b = boards[i]; ++i) {
        if (valid_boards.indexOf(b) === -1) {
          alert('Invalid board ' + b);
          return;
        }
      }
      
      if (op_not) {
        boards = valid_boards.filter(b => !boards.includes(b));
      }
    }
    
    el = $.id('spec-field');
    
    if (el && el.checked) {
      $.id('arc-field').checked = false;
    }
    
    nodes = $.cls('s-p', $.id('search-form'));
    
    q = [];
    url_frag = {};
    
    for (i = 0; el = nodes[i]; ++i) {
      if (el.type === 'text' || el.type === 'hidden' || el.type === 'select-one') {
        if (el.value !== '') {
          q.push(el.name + '=' + encodeURIComponent(el.value));
          url_frag[el.name] = el.value;
        }
      }
      else if (el.type === 'checkbox') {
        if (el.checked) {
          q.push(el.name + '=1');
          url_frag[el.name] = 1;
        }
      }
    }
    
    if (q.length < 1) {
      return;
    }
    
    APP.clearSearchState();
    APP.lockSearchForm();
    APP.updateSearchProgress();
    
    q = q.join('&');
    
    grp = APP.getGroupOptionTag();
    
    if (grp.previousElementSibling) {
      url_frag['group'] = grp.value;
    }
    
    if (boards_raw !== '') {
      url_frag['boards'] = boards_raw;
    }
    
    APP.currentParams = url_frag;
    
    history.replaceState(null, '', '#' + JSON.stringify(url_frag));
    
    q = '?action=search&' + q;
    
    callbacks = {
      onload: APP.onSearchLoad,
      onerror: APP.onSearchError,
      onloadend: APP.onSearchLoadEnd
    };
    
    for (i = 0; b = boards[i]; ++i) {
      ++APP.xhrCount;
      APP.xhrs.push($.xhr('GET', q + '&board=' + b, callbacks));
    }
  },
  
  onSaveSearchClick: function() {
    let params = APP.collectSearchParams();
    
    if (!Object.keys(params).length) {
      return Feedback.error('Nothing to save.');
    }
    
    let label = prompt('Enter a short name');
    
    if (label === '') {
      return Feedback.error('Label cannot be empty.');
    }
    
    if (label === null) {
      return;
    }
    
    let data = {
      label: label,
      params: params
    };
    
    let done = false;
    
    for (let i = 0; i < APP.savedSearch.length; ++i) {
      let entry = APP.savedSearch[i];
      
      if (entry.label === label) {
        APP.savedSearch[i] = data;
        done = true;
        break;
      }
    }
    
    if (!done) {
      APP.savedSearch.push(data);
    }
    
    localStorage.setItem('saved-searches', JSON.stringify(APP.savedSearch));
    
    APP.loadSavedSearch();
    APP.buildSavedSearchList();
  },
  
  onRunSearchClick: function(btn) {
    let sid = btn.getAttribute('data-sid');
    
    if (sid === undefined) {
      return;
    }
    
    let data = APP.savedSearch[sid];
    
    if (!data) {
      return;
    }
    
    if (APP.restoreSearchParams(data.params)) {
      APP.onSearchClick();
    }
  },
  
  onDelSearchClick: function(btn) {
    let sid = btn.getAttribute('data-sid');
    
    if (sid === undefined) {
      return;
    }
    
    if (!APP.savedSearch[sid]) {
      return;
    }
    
    APP.savedSearch.splice(sid, 1);
    
    if (APP.savedSearch.length === 0) {
      localStorage.removeItem('saved-searches');
    }
    else {
      localStorage.setItem('saved-searches', JSON.stringify(APP.savedSearch));
    }
    
    APP.buildSavedSearchList();
  },
  
  syncSavedSearch: function(e) {
    if (e.key !== 'saved-searches') {
      return;
    }
    
    if (APP.loadSavedSearch()) {
      APP.buildSavedSearchList();
    }
  },
  
  loadSavedSearch: function() {
    APP.savedSearch = [];
    
    try {
      let data = localStorage.getItem('saved-searches');
      
      if (data) {
        APP.savedSearch = JSON.parse(data);
      }
    }
    catch(e) {
      console.log(e);
      return false;
    }
    
    return true;
  },
  
  buildSavedSearchList: function() {
    let cnt = $.id('js-saved-searches');
    
    cnt.innerHTML = '';
    
    APP.savedSearch.forEach((data, i) => {
      let wrap = $.el('span');
      
      let btn = $.el('button');
      btn.className = 'button btn-other btn-ls';
      btn.type = 'button';
      btn.setAttribute('data-cmd', 'run-search');
      btn.setAttribute('data-sid', i);
      btn.textContent = data.label;
      wrap.appendChild(btn);
      
      let x = $.el('button');
      x.type = 'button';
      x.className = 'button btn-other btn-rs';
      x.innerHTML = '&times;';
      x.setAttribute('data-cmd', 'del-search');
      x.setAttribute('data-sid', i);
      x.setAttribute('data-tip', 'Delete');
      wrap.appendChild(x);
      
      cnt.appendChild(wrap);
    });
  },
  
  collectSearchParams: function() {
    let nodes = $.cls('s-p', $.id('search-form'));
    
    let params = {};
    
    for (let el of nodes) {
      if (el.name[0] === '_') {
        continue;
      }
      if (el.type === 'checkbox') {
        if (el.checked !== el.hasAttribute('checked')) {
          params[el.name] = el.checked;
        }
      }
      else if (el.selectedIndex !== undefined) {
        if (el.selectedIndex > 0) {
          params[el.name] = el.value;
        }
      }
      else {
        if (el.value !== '') {
          params[el.name] = el.value;
        }
      }
    }
    
    let boards = $.id('boards-field');
    let group = $.id('group-field');
    
    if (boards.value !== '') {
      params.boards = boards.value;
    }
    
    if (group.selectedIndex > 0) {
      params.group = group.value;
    }
    
    return params;
  },
  
  restoreSearchParams: function(params) {
    if (APP.xhrCount !== 0) {
      return false;
    }
    
    APP.onSearchReset();
    
    let nodes = $.cls('s-p', $.id('search-form'));
    
    for (let el of nodes) {
      if (params[el.name] === undefined) {
        continue;
      }
      
      if (el.type === 'checkbox') {
        el.checked = params[el.name];
      }
      else {
        el.value = params[el.name];
      }
    }
    
    let boards = $.id('boards-field');
    let group = $.id('group-field');
    
    if (params.boards !== undefined) {
      boards.value = params.boards;
    }
    
    if (params.group !== undefined) {
      group.value = params.group;
    }
    
    return true;
  },
  
  parseFileUID: function() {
    var el, params, val, m, r;
    
    el = document.forms.search.fileuid;
    
    val = el.value;
    
    if (!el || val === '' || val.indexOf('/') === -1) {
      return;
    }
    
    r = /\/([0-9]+)s?\./g;
    
    params = [];
    
    while ((m = r.exec(val)) !== null) {
      params.push(m[1]);
    }
    
    params = params.join(',');
    
    if (params !== '') {
      el.value = params;
    }
  },
  
  parseThreadURL: function() {
    var el, el2, m;
    
    el = document.forms.search.thread_id;
    el2 = $.id('boards-field');
    
    if (!el || !el2 || el.value === '' || el.value.indexOf('/') === -1) {
      return;
    }
    
    m = /\/([a-z0-9]+)\/thread\/([0-9]+)/.exec(el.value);
    
    if (m[1] !== '' && m[2] !== '') {
      el2.value = m[1];
      el.value = m[2];
    }
  },
  
  parseFileSize: function() {
    let el;
    
    el = document.forms.search.filesize;
    
    if (!el || el.value === '' || el.value.toUpperCase().indexOf('B') === -1) {
      return;
    }
    
    el.value = el.value.replace(/([0-9]+) ?([KM])B\b/ig, function(m, p1, p2) {
      let u = 0;
      
      if (p2.toUpperCase() === 'K') {
        u = 1024;
      }
      else if (p2.toUpperCase() === 'M') {
        u = 1024 * 1024;
      }
      
      return p1 * u;
    });
  },
  
  parsePassRef: function() {
    var el, m;
    
    el = document.forms.search.pass_ref;
    
    if (!el || el.value === '') {
      return;
    }
    
    if (el.value.indexOf('/bans?') !== -1) {
      m = /id=([0-9]+)/.exec(el.value);
      
      if (m && m[1]) {
        el.value = m[1];
      }
    }
    else if (el.value.indexOf('/thread/') !== -1) {
      m = /\/([a-z0-9]+)\/thread\/([0-9]+)[^#]*(?:#[qp]([0-9]+))?$/.exec(el.value);
      
      if (m && m[1] && m[2]) {
        if (m[3]) {
          el.value = '/' + m[1] + '/' + m[3];
        }
        else {
          el.value = '/' + m[1] + '/' + m[2];
        }
      }
    }
  },
  
  clearSearchState: function() {
    APP.xhrs = [];
    APP.xhrCount = 0;
    APP.resultCount = 0;
    APP.currentParams = null;
    APP.searchResults = [];
    APP.deletedResults = {};
    APP.searchAborted = false;
    APP.clearPartialStatus();
  },
  
  clearPartialStatus: function() {
    APP.partial = { boards: [] };
  },
  
  updateSearchProgress: function() {
    var perc, cur, total;
    
    cur = APP.xhrCount;
    total = APP.xhrs.length;
    
    if (APP.searchAborted) {
      APP.abortSearch();
      return;
    }
    
    if (total === 0) {
      Feedback.notify('Searching… 0%', false);
      $.id('search-fields').disabled = true;
      return;
    }
    
    if (cur <= 0) {
      Feedback.notify('Building…', false);
      setTimeout(APP.onSearchResultsReady, 30);
      return;
    }
    
    perc = 100 - (0 | (cur / total * 100 + 0.5));
    
    Feedback.replaceMessage('Searching… ' + perc + '%');
  },
  
  lockSearchForm: function() {
    var fields, btn;
    
    fields = $.id('search-fields');
    
    if (fields.disabled) {
      return;
    }
    
    btn = $.id('search-btn');
    
    $.removeClass(btn, 'btn-other');
    $.addClass(btn, 'btn-deny');
    btn.textContent = 'Abort';
    
    fields.disabled = true;
  },
  
  unlockSearchForm: function() {
    var fields, btn;
    
    fields = $.id('search-fields');
    
    if (!fields.disabled) {
      return;
    }
    
    btn = $.id('search-btn');
    
    $.removeClass(btn, 'btn-deny');
    $.addClass(btn, 'btn-other');
    btn.textContent = 'Search';
    
    fields.disabled = false;
  },
  
  onSearchResultsReady: function() {
    APP.buildResults();
    Feedback.hideMessage();
    APP.unlockSearchForm();
  },
  
  onSearchLoad: function() {
    var resp;
    
    resp = APP.parseResponse(this.responseText);
    
    if (resp.status === 'success') {
      if (resp.data.partial) {
        APP.partial.boards.push(resp.data.board);
        console.log('Too many results from /' + resp.data.board + '/');
      }
      
      if (APP.resultCount >= APP.maxResults) {
        APP.partial.global = true;
        return;
      }
      
      if (resp.data.posts.length) {
        APP.resultCount += resp.data.posts.length;
        APP.searchResults.push(resp.data);
      }
    }
    else {
      if (resp.fatal === true) {
        APP.abortSearch();
        Feedback.error(resp.message);
      }
      else {
        APP.partial.error = true;
        console.log(resp.message);
      }
    }
  },
  
  onSearchError: function() {
    APP.partial.error = true;
    console.log('err');
  },
  
  onSearchLoadEnd: function() {
    if (APP.xhrCount < 1) {
      return;
    }
    
    APP.xhrCount--;
    
    APP.updateSearchProgress();
    
    if (APP.xhrCount < 1) {
      APP.xhrs = [];
    }
  },
  
  abortSearch: function() {
    var i, xhr;
    
    for (i = 0; xhr = APP.xhrs[i]; ++i) {
      xhr.abort();
    }
    
    APP.clearSearchState();
    APP.unlockSearchForm();
    Feedback.hideMessage();
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
  
  getGroupOptionTag: function() {
    var el = $.id('group-field');
    return el.options[el.selectedIndex];
  },
  
  getGroupedResults: function() {
    var i, j, post, el, results, resGroup, groupBy, grp, posts, groupNames,
      asInt, groupedResults, sortedGroupNames, partialBoards;
    
    el = APP.getGroupOptionTag();
    
    asInt = el.hasAttribute('data-int');
    groupBy = el.value;
    
    APP.groupBy = groupBy;
    
    groupNames = [];
    groupedResults = {};
    partialBoards = {};
    
    results = APP.searchResults;
    
    if (groupBy === 'board') {
      for (i = 0; resGroup = results[i]; ++i) {
        if (!resGroup.posts[0]) {
          continue;
        }
        grp = resGroup.posts[0].board;
        groupNames.push(grp);
        groupedResults[grp] = resGroup.posts;
        
        if (resGroup.partial) {
          partialBoards[grp] = true;
        }
      }
    }
    else {
      for (i = 0; resGroup = results[i]; ++i) {
        if (!resGroup.posts[0]) {
          continue;
        }
        
        posts = resGroup.posts;
        
        for (j = 0; post = posts[j]; ++j) {
          grp = post[groupBy];
          if (!groupedResults[grp]) {
            groupNames.push(grp);
            groupedResults[grp] = [ post ];
          }
          else {
            groupedResults[grp].push(post);
          }
        }
      }
    }
    
    el = $.id('group-sort-field');
    
    if (el && el.checked) {
      sortedGroupNames = APP.sortGroupsPostCount(groupNames, groupedResults);
    }
    else {
      sortedGroupNames = APP.sortGroups(groupNames, asInt);
    }
    
    return [ groupedResults, sortedGroupNames, partialBoards ];
  },
  
  sortGroups: function(keys, asInt) {
    if (asInt) {
      keys.sort(function(a, b) { return a - b; });
    }
    else {
      keys.sort();
    }
    
    return keys;
  },
  
  sortGroupsPostCount: function(keys, groups) {
    keys.sort(function(a, b) {
      var ac, bc;
      
      ac = groups[a].length;
      bc = groups[b].length;
      
      if (ac < bc) {
        return 1;
      }
      
      if (ac > bc) {
        return -1;
      }
      
      return 0;
    });
    
    return keys;
  },
  
  countResultThreads: function(posts) {
    var p, count = 0;
    
    for (p of posts) {
      if (p.resto === '0') {
        count++;
      }
    }
    
    return count;
  },
  
  buildResults: function() {
    var i, j, results, sortedGroups, group, posts, post, html,
      name, tripcode, ccCls, thumb, hasFile, sub, cnt, frag, resCnt, delStatus,
      postDeleted, checked, partialSel, postOpts, hasOpts, partialBoards,
      postOptsCls, threadCount, resErrCls;
    
    resCnt = $.id('search-results');
    
    results = APP.getGroupedResults();
    
    partialBoards = results[2];
    sortedGroups = results[1];
    results = results[0];
    
    if (!sortedGroups.length) {
      APP.hideResultsCtrl();
      resCnt.innerHTML = '<div id="no-results">Nothing found</div>';
      return;
    }
    
    APP.showResultsCtrl();
    
    resCnt.textContent = '';
    
    partialSel = false;
    
    frag = $.frag();
    
    for (i = 0; (group = sortedGroups[i]) !== undefined; ++i) {
      posts = results[group];
      
      threadCount = APP.countResultThreads(posts);
      
      resErrCls = partialBoards[group] ? ' res-count-err' : '';
      
      html = '<div class="res-head"><input class="grp-sel" '
        + 'data-cmd="toggle-grp" checked type="checkbox">'
        + '<span class="res-title">' + (group === '' ? 'N/A' : group)
        + '</span><span class="res-count st' + resErrCls + '">' + posts.length
          + ' post' + $.pluralise(posts.length) + '</span>'
        + (threadCount ? ('<span class="res-count st stx' + resErrCls + '">' + threadCount
          + ' thread' + $.pluralise(threadCount) + '</span>') : '')
        + '<span class="grp-ctrl">'
        + '<span data-cmd="pre-del-grp" class="button btn-other">Delete</span>'
        + (!APP.isArchived ?
          '<span data-cmd="ban-multi" class="button btn-other">Ban</span>'
          : '') + '</span></div>';
      
      for (j = 0; post = posts[j]; ++j) {
        postDeleted = false;
        hasFile = false;
        checked = ' checked';
        
        if (delStatus = APP.deletedResults[post.board + '-' + post.no]) {
          if (delStatus === APP.FILE_DELETED) {
            post.filedeleted = '1';
          }
          else {
            postDeleted = true;
          }
        }
        
        if (post.capcode) {
          name = post.name + ' ## ' + APP.capcodes[post.capcode];
          ccCls = ' post-capcode-' + post.capcode;
          checked = '';
          if (!postDeleted) {
            partialSel = true;
          }
        }
        else {
          name = post.name;
          ccCls = '';
        }
        
        if (post.tripcode) {
          tripcode = '<span class="post-trip">' + post.tripcode + '</span>';
        }
        else {
          tripcode = '';
        }
        
        if (post.has_opts) {
          if (!postDeleted) {
            partialSel = true;
          }
          checked = '';
          postOpts = [];
          postOptsCls = '';
          hasOpts = true;
          
          if (post.sticky) {
            if (post.undead) {
              postOpts.push('Rolling Sticky');
              postOptsCls = 'opt-undead-sticky';
            }
            else {
              postOpts.push('Sticky');
              postOptsCls = 'opt-sticky';
            }
          }
          
          if (post.closed) {
            postOpts.push('Closed');
            
            if (!post.sticky) {
              postOptsCls = 'opt-closed';
            }
          }
          
          if (post.permasage) {
            postOpts.push('Perma-sage');
            postOptsCls = 'opt-perma-sage';
          }
          
          if (post.permaage) {
            postOpts.push('Perma-age');
            postOptsCls = 'opt-perma-age';
          }
          
          if (post.undead) {
            postOpts.push('Undead');
            
            if (!post.sticky) {
              postOptsCls = 'opt-undead';
            }
          }
        }
        else {
          hasOpts = null;
        }
        
        if (post.ext) {
          if (post.filedeleted) {
            thumb = '<div><img class="post-thumb-deleted" src="//'
              + this.fileDeleted + '" alt="File deleted"></div>';
          }
          else if (post.board === 'f') {
            hasFile = true;
            thumb = '<a target="_blank" href="'
              + this.linkToSWF(post.filename) + '">'
              + '<div class="post-swf" title="' + post.filename + '.swf">'
                + post.filename + '</div></a>';
          }
          else {
            hasFile = true;
            thumb = '<a class="post-thumb-link" target="_blank" href="'
              + this.linkToImage(post.board, post.tim, post.ext) + '">'
              + '<img data-tip data-tip-cb="APP.showFileTip" data-meta="'
              + post.filename + post.ext + "\n" + post.w + '&times;' + post.h
              + '" data-fsize="' + post.fsize + '" class="post-thumb'
              + (post.spoiler ? ' thumb-spoiler' : '')
                + '" src="'
                + this.linkToThumb(post.board, post.tim)
                + '" loading="lazy" data-width="'
                  + post.w + '" alt="">'
              + '</a>';
          }
        }
        else {
          thumb = '';
        }
        
        if (post.sub) {
          sub = '<div class="post-subject">' + post.sub + '</div>';
        }
        else {
          sub = '';
        }
        
        html += '<article id="' + post.board + '-' + post.no
          + '" class="post' + (postDeleted ? ' disabled' : '')
            + '"><div class="post-meta">'
          + '<span class="post-board">/' + post.board + '/</span>'
          + '<div class="post-author">'
          + '<span class="post-name' + ccCls + '" data-tip data-tip-cb="APP.showNameTip">'
          + name + '</span>' + tripcode + '</div>'
          + '<div class="post-host"><span class="cnt-block">'
            + post.host + '</span><span class="post-country">'
            + post.country + '</span></div>'
          + (hasOpts ? ('<span class="post-opts-icon '
            + postOptsCls + '" data-tip="'
            + postOpts.join("\n") +'"></span>') : '')
          + '</div>'
          + '<div class="post-content">' + thumb + sub + post.com + '</div>'
          + '<div class="post-controls">'
          + '<input data-cmd="toggle-post" class="post-sel" type="checkbox" '
            + 'data-ip="' + post.host + '" data-board="'
            + post.board + '" data-pid="' + post.no + '"' + '" data-pwd="' + post.pwd + '"'
              + (postDeleted ? ' disabled' : '') + checked + '>'
          + '<a href="'
            + this.linkToPost(post.board, post.no, post.resto)
            + '" target="_blank" class="post-link button button-light right">View'
            + (post.resto === '0' ? '<span class="post-op">(OP)</span>' : '')
          + '</a>'
          + (!APP.isArchived ?
              ((post.resto === '0' ? '<span data-cmd="thread-opts" data-tip="Thread Options" class="button button-light right">O</span>' : '')
              + '<a href="#{&quot;password&quot;:&quot;'
              + post.pwd + '&quot;}" data-tip="More from this Password" '
              + 'target="_blank" class="button button-light right">M+</a>'
              + '<a href="#{&quot;ip&quot;:&quot;'
              + post.host + '&quot;}" data-tip="More from this IP" '
              + 'target="_blank" class="button button-light right">M</a>'
              + '<span data-cmd="ban-post" class="button button-light right">Ban</span>')
              : '')
          + (hasFile ?
              '<span data-tip="Delete File" data-cmd="pre-del-post" data-fileonly class="button button-light right">File</span>'
              : ''
            )
          + '<span data-cmd="pre-del-post" class="button button-light right">Delete</span>'
          + '</div></article>';
      }
      
      cnt = $.el('div');
      cnt.className = 'res-cnt';
      cnt.innerHTML = html;
      
      if (partialSel) {
        APP.onTogglePostClick($.cls('post-sel', cnt)[0]);
      }
      
      frag.appendChild(cnt);
    }
    
    resCnt.className = 'group-by-' + APP.groupBy;
    resCnt.appendChild(frag);
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

APP.init();
