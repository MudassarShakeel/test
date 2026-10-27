// MinEditor design preview: front-end behaviour only, no real login or storage.
(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---------- screen switching ---------- */
  function show(name) {
    $$('.screen').forEach(s => s.classList.toggle('is-active', s.id === 'screen-' + name));
    $$('.screens [data-screen]').forEach(b => b.classList.toggle('is-active', b.dataset.screen === name));
    window.scrollTo(0, 0);
    if (name === 'editor') render();
  }
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-screen],[data-screen-go]');
    if (a) show(a.dataset.screen || a.dataset.screenGo);
    if (e.target.closest('[data-open-login]')) openLogin();
  });

  /* ---------- login popup: email -> 6-digit code ---------- */
  const modal = $('#login'), stepEmail = $('#stepEmail'), stepCode = $('#stepCode');
  const otp = $$('#otp input');
  let lastFocus = null;

  function setStep(which) {
    stepEmail.classList.toggle('is-active', which === 'email');
    stepCode.classList.toggle('is-active', which === 'code');
  }
  function openLogin() {
    lastFocus = document.activeElement;
    modal.hidden = false;
    setStep('email');
    $('#emailErr').hidden = true;
    setTimeout(() => $('#email').focus(), 30);
  }
  function closeLogin() {
    modal.hidden = true;
    if (lastFocus) lastFocus.focus();
  }
  modal.addEventListener('click', e => { if (e.target === modal || e.target.closest('[data-close]')) closeLogin(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closeLogin(); });

  stepEmail.addEventListener('submit', e => {
    e.preventDefault();
    const v = $('#email').value.trim();
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    $('#emailErr').hidden = ok;
    if (!ok) return;
    $('#sentTo').textContent = v;
    otp.forEach(i => (i.value = ''));
    $('#codeErr').hidden = true;
    setStep('code');
    otp[0].focus();
  });

  otp.forEach((box, i) => {
    box.addEventListener('input', () => {
      box.value = box.value.replace(/\D/g, '').slice(0, 1);
      if (box.value && otp[i + 1]) otp[i + 1].focus();
    });
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !box.value && otp[i - 1]) otp[i - 1].focus();
    });
    box.addEventListener('paste', e => {
      const t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (!t) return;
      e.preventDefault();
      [...t].forEach((c, k) => { if (otp[k]) otp[k].value = c; });
      otp[Math.min(t.length, 5)].focus();
    });
  });

  stepCode.addEventListener('submit', e => {
    e.preventDefault();
    const ok = otp.every(i => i.value);
    $('#codeErr').hidden = ok;
    if (!ok) return;
    closeLogin();
    show('dashboard');
  });
  $('#changeEmail').addEventListener('click', () => { setStep('email'); $('#email').focus(); });
  $('#resend').addEventListener('click', e => {
    e.target.textContent = 'Code sent!';
    setTimeout(() => (e.target.textContent = 'Resend code'), 2000);
  });

  /* ---------- editor: live preview + visual editing ---------- */
  const SAMPLE = `<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; padding: 40px; color: #111; }
    h1 { font-size: 40px; font-weight: 800; margin: 0 0 12px; }
    p  { font-size: 18px; color: #555; max-width: 30em; }
    a.btn { display: inline-block; background: #FE4C1C; color: #fff;
            padding: 12px 26px; border-radius: 99px; text-decoration: none; font-weight: 700; }
  </style>
</head>
<body>
  <h1>Hello from MinEditor</h1>
  <p>Edit the code on the left or click any element in this preview to change it.</p>
  <a class="btn" href="#">Get started</a>
</body>
</html>`;

  const src = $('#source'), frame = $('#frame'), gutter = $('#gutter');
  src.value = SAMPLE;

  // Injected into the preview so clicks select elements and errors reach the console.
  const BRIDGE = `<script>
(function(){
  var sel;
  function path(el){return el.tagName.toLowerCase()+(el.className&&typeof el.className==='string'?'.'+el.className.trim().split(/\\s+/)[0]:'');}
  document.addEventListener('mouseover',function(e){e.target.style.outline='2px dashed #FE4C1C';});
  document.addEventListener('mouseout',function(e){if(e.target!==sel)e.target.style.outline='';});
  document.addEventListener('click',function(e){
    e.preventDefault();
    if(sel)sel.style.outline='';
    sel=e.target;sel.style.outline='2px solid #FE4C1C';
    var cs=getComputedStyle(sel);
    parent.postMessage({type:'select',path:path(sel),text:sel.childElementCount?'':sel.textContent,
      color:cs.color,size:parseInt(cs.fontSize),weight:cs.fontWeight},'*');
  },true);
  window.addEventListener('message',function(e){
    if(!sel||!e.data||e.data.type!=='apply')return;
    var d=e.data;
    if(d.text!==undefined&&!sel.childElementCount)sel.textContent=d.text;
    if(d.color)sel.style.color=d.color;
    if(d.size)sel.style.fontSize=d.size+'px';
    if(d.weight)sel.style.fontWeight=d.weight;
  });
  window.addEventListener('error',function(e){parent.postMessage({type:'error',msg:e.message},'*');});
})();
<\/script>`;

  function lines() {
    const n = src.value.split('\n').length;
    gutter.textContent = Array.from({ length: n }, (_, i) => i + 1).join('\n');
  }
  function render() {
    frame.srcdoc = src.value.replace('</body>', BRIDGE + '</body>');
    setConsole();
    $('#insForm').hidden = true;
    $('#insEmpty').hidden = false;
  }
  function setConsole(msg) {
    $('#console').innerHTML = '<b>Console</b>' + (msg ? `<span class="bad">${msg}</span>` : '<span>No errors</span>');
  }
  let t;
  src.addEventListener('input', () => {
    lines();
    $('#saveState').textContent = 'Saving…';
    clearTimeout(t);
    t = setTimeout(() => { render(); $('#saveState').textContent = 'Saved'; }, 400);
  });
  src.addEventListener('scroll', () => (gutter.scrollTop = src.scrollTop));
  src.addEventListener('keydown', e => {
    if (e.key === 'Tab') {
      e.preventDefault();
      const s = src.selectionStart;
      src.setRangeText('  ', s, src.selectionEnd, 'end');
      src.dispatchEvent(new Event('input'));
    }
  });
  lines();

  $$('.devices button').forEach(b => b.addEventListener('click', () => {
    $$('.devices button').forEach(x => x.classList.toggle('is-active', x === b));
    frame.style.width = b.dataset.w;
  }));

  const fText = $('#fText'), fColor = $('#fColor'), fSize = $('#fSize'), fWeight = $('#fWeight');
  const hex = rgb => '#' + (rgb.match(/\d+/g) || [0, 0, 0]).slice(0, 3).map(n => (+n).toString(16).padStart(2, '0')).join('');
  const apply = patch => frame.contentWindow.postMessage({ type: 'apply', ...patch }, '*');

  window.addEventListener('message', e => {
    const d = e.data || {};
    if (d.type === 'select') {
      $('#insEmpty').hidden = true;
      $('#insForm').hidden = false;
      $('#crumb').textContent = d.path;
      fText.value = d.text; fText.disabled = !d.text && d.text !== '';
      fColor.value = hex(d.color);
      fSize.value = d.size; $('#fSizeOut').textContent = d.size + 'px';
      fWeight.value = +d.weight >= 700 ? '800' : +d.weight >= 500 ? '600' : '400';
    } else if (d.type === 'error') {
      setConsole(d.msg);
    }
  });
  fText.addEventListener('input', () => apply({ text: fText.value }));
  fColor.addEventListener('input', () => apply({ color: fColor.value }));
  fSize.addEventListener('input', () => { $('#fSizeOut').textContent = fSize.value + 'px'; apply({ size: +fSize.value }); });
  fWeight.addEventListener('change', () => apply({ weight: fWeight.value }));
  $('#swatches').addEventListener('click', e => {
    const c = e.target.closest('button')?.dataset.c;
    if (c) { fColor.value = c; apply({ color: c }); }
  });
})();
