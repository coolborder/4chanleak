function onXhrLoad() {
  let data = JSON.parse(this.responseText);
  if (data.error) {
    $.id('js-res').textContent = data.error;
  }
  else {
    let out = [];
    
    for (let k in data.data) {
      out.push(k + ': ' + data.data[k]);
    }
    
    $.id('js-res').innerHTML = out.join(' &mdash; ');
  }
}

function sendForm(canvas) {
  canvas.toBlob((blob) => {
    const formData = new FormData();
    formData.append('action', 'predict');
    formData.append("img", blob);
    formData.append('_tkn', $.getToken());
    
    $.id('js-res').innerHTML = 'Loading...';
    
    const r = new XMLHttpRequest();
    r.withCredentials = true;
    r.open("POST", "");
    r.onload = onXhrLoad;
    r.onerror = function() { $.id('js-res').textContent = 'Error'; };
    r.send(formData);
  });
}

function resizeImage(file) {
  let old = document.getElementById('js-preview');
  
  if (old) {
    old.parentNode.removeChild(old);
  }
  
  var canvas = document.createElement("canvas");
  var ctx = canvas.getContext("2d");
  
  let imgSrc = window.URL.createObjectURL(file);
  
  const img = new Image();
  
  img.onload = function() {
    let in_dims = 0 | document.body.getAttribute('data-in-dims');
    
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    ctx.drawImage(img, 0, 0);
    
    var canvas2 = document.createElement("canvas");
    canvas2.width = in_dims;
    canvas2.height = in_dims;
    var ctx2 = canvas2.getContext("2d");
    ctx2.fillStyle = 'rgb(127, 127, 127)';
    ctx2.fillRect(0, 0, canvas2.width, canvas2.height);
    
    let x = 0;
    let y = 0;
    let x2 = 0;
    let y2 = 0;
    let w2 = in_dims;
    let h2 = in_dims;
    
    if ($.id('js-pad').checked) {
      let ratio = Math.min(in_dims / canvas.width, in_dims / canvas.height);
      
      w2 = canvas.width * ratio;
      h2 = canvas.height * ratio;
      
      if (w2 < in_dims) {
        x2 = Math.floor((in_dims - w2) / 2);
      }
      else if (h2 < in_dims) {
        y2 = Math.floor((in_dims - h2) / 2);
      }
    }
    else if ($.id('js-crop').checked) {
      let ratio = Math.max(in_dims / canvas.width, in_dims / canvas.height);
      
      w2 = canvas.width * ratio;
      h2 = canvas.height * ratio;
      
      if (w2 > in_dims) {
        x2 = -Math.floor((w2 - in_dims) / 2);
      }
      else if (h2 > in_dims) {
        y2 = -Math.floor((h2 - in_dims) / 2);
      }
    }
    
    console.log(0, 0, canvas.width, canvas.height, x2, y2, w2, h2);
    ctx2.drawImage(canvas, 0, 0, canvas.width, canvas.height, x2, y2, w2, h2);
    
    canvas2.id = 'js-preview';
    canvas2.style.display = "none";
    document.body.appendChild(canvas2);
    
    sendForm(canvas2);
  }
  
  img.src = imgSrc;
  
  return canvas;
}

function onFormSubmit(e) {
  e.preventDefault();
  
  let img = $.id('js-img-field').files[0];
  
  $.id('js-res').innerHTML = '';
  
  if (!img) {
    console.log('No image selected');
    return;
  }
  
  resizeImage(img);
}

function init() {
  $.id('js-up-form').addEventListener('submit', onFormSubmit, false);
  ImageHover.init();
}

document.addEventListener('DOMContentLoaded', init, false);
