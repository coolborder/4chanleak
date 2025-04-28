'use strict';

var $ = {};

$.id = function(id) {
  return document.getElementById(id);
};

$.cls = function(klass, root) {
  return (root || document).getElementsByClassName(klass);
};

$.byName = function(name) {
  return document.getElementsByName(name);
};

$.tag = function(tag, root) {
  return (root || document).getElementsByTagName(tag);
};

$.el = function(tag) {
  return document.createElement(tag);
};

$.frag = function() {
  return document.createDocumentFragment();
};

$.qs = function(selector, root) {
  return (root || document).querySelector(selector);
};

$.qsa = function(selector, root) {
  return (root || document).querySelectorAll(selector);
};

$.extend = function(destination, source) {
  for (var key in source) {
    destination[key] = source[key];
  }
};

$.escapeHTML = function(str) {
  if (!/["'&<>]/.test(str)) {
    return str;
  }
  
  return str.replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
};

if (!document.documentElement.classList) {
  $.hasClass = function(el, klass) {
    return (' ' + el.className + ' ').indexOf(' ' + klass + ' ') != -1;
  };
  
  $.addClass = function(el, klass) {
    el.className = (el.className === '') ? klass : el.className + ' ' + klass;
  };
  
  $.removeClass = function(el, klass) {
    el.className = (' ' + el.className + ' ').replace(' ' + klass + ' ', '');
  };
}
else {
  $.hasClass = function(el, klass) {
    return el.classList.contains(klass);
  };
  
  $.addClass = function(el, klass) {
    el.classList.add(klass);
  };
  
  $.removeClass = function(el, klass) {
    el.classList.remove(klass);
  };
}

$.on = function(n, e, h, opts) {
  n.addEventListener(e, h, opts);
};

$.off = function(n, e, h, opts) {
  n.removeEventListener(e, h, opts);
};

$.xhr = function(method, url, callbacks, data) {
  var key, xhr, form;
  
  xhr = new XMLHttpRequest();
  
  xhr.open(method, url, true);
  
  if (callbacks) {
    for (key in callbacks) {
      xhr[key] = callbacks[key];
    }
  }
  
  if (data) {
    if (typeof data === 'string') {
      xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    }
    else {
      form = new FormData();
      for (key in data) {
        form.append(key, data[key]);
      }
      data = form;
    }
  }
  else {
    data = null;
  }
  
  xhr.send(data);
  
  return xhr;
};

$.getItem = function(key) {
  return localStorage.getItem(key);
};

$.setItem = function(key, value) {
  return localStorage.setItem(key, value);
};

$.removeItem = function(key) {
  return localStorage.removeItem(key);
};

$.getCookie = function(name) {
  var i, c, ca, key;
  
  key = name + '=';
  ca = document.cookie.split(';');
  
  for (i = 0; c = ca[i]; ++i) {
    while (c.charAt(0) == ' ') {
      c = c.substring(1, c.length);
    }
    if (c.indexOf(key) === 0) {
      return decodeURIComponent(c.substring(key.length, c.length));
    }
  }
  
  return null;
};

$.getToken = function() {
  return document.body.getAttribute('data-tkn');
};

$.ago = function(timestamp) {
  var delta, count, head, tail, ago;
  
  delta = Date.now() / 1000 - timestamp;
  
  if (delta < 1) {
    return 'moments ago';
  }
  
  if (delta < 60) {
    return (0 | delta) + ' seconds ago';
  }
  
  if (delta < 3600) {
    count = 0 | (delta / 60);
    
    if (count > 1) {
      return count + ' minutes ago';
    }
    else {
      return 'one minute ago';
    }
  }
  
  if (delta < 86400) {
    count = 0 | (delta / 3600);
    
    if (count > 1) {
      head = count + ' hours';
    }
    else {
      head = 'one hour';
    }
    
    tail = 0 | (delta / 60 - count * 60);
    
    if (tail > 1) {
      head += ' and ' + tail + ' minutes';
    }
    
    return head + ' ago';
  }
  
  count = 0 | (delta / 86400);
  
  if (count > 1) {
    head = count + ' days';
  }
  else {
    head = 'one day';
  }
  
  tail = 0 | (delta / 3600 - count * 24);
  
  if (tail > 1) {
    head += ' and ' + tail + ' hours';
  }
  
  return head + ' ago';
};

$.capitalise = function(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
};

$.pluralise = function(count, s, p) {
  return count === 1 ? (s || '') : (p || 's');
};

$.prettyBytes = function(bytes) {
  var size;
  
  if (bytes >= 1048576) {
    size = ((0 | (bytes / 1048576 * 100 + 0.5)) / 100) + ' MB';
  }
  else if (bytes > 1024) {
    size = (0 | (bytes / 1024 + 0.5)) + ' KB';
  }
  else {
    size = bytes + ' B';
  }
  
  return size;
};

$.parentTag = function(root, name) {
  while (root !== document) {
    if (root.tagName === name) {
      break;
    }
    
    root = root.parentNode;
  }
  
  if (root === document) {
    return null;
  }
  
  return root;
}

$.hidden = 'hidden';
$.visibilitychange = 'visibilitychange';

if (typeof document.hidden === 'undefined') {
  if ('mozHidden' in document) {
    $.hidden = 'mozHidden';
    $.visibilitychange = 'mozvisibilitychange';
  }
  else if ('webkitHidden' in document) {
    $.hidden = 'webkitHidden';
    $.visibilitychange = 'webkitvisibilitychange';
  }
  else if ('msHidden' in document) {
    $.hidden = 'msHidden';
    $.visibilitychange = 'msvisibilitychange';
  }
}

$.docEl = document.documentElement;
