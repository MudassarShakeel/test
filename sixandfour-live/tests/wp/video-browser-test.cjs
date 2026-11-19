// A real browser makes the result video by itself on a plugin admin page, uploads it, and the plugin posts it.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost:8099';
const MOCK = 'http://127.0.0.1:8100';
const WORK = process.env.SF_WP_WORK;
const OUT = process.env.OUT || '/tmp/sf-video-out';
fs.mkdirSync(OUT, { recursive: true });
let failures = 0;
const ok = (c, l) => { console.log((c ? '  PASS  ' : '  FAIL  ') + l); if (!c) failures++; };
const php = (cmd) => execSync(`php ${path.join(__dirname, 'run.php')} ${path.join(__dirname, 'helpers.php')} ${cmd}`, { encoding: 'utf8', env: { ...process.env, SF_WP_WORK: WORK, SF_VIDEO_JOB: process.env.SF_VIDEO_JOB || '/tmp/job.json' } }).trim().split('\n').pop();

(async () => {
  php('clear-locks');
  const id = php('video-queue');
  ok(Number(id) > 0, 'a video post is queued in browser mode');
  const state0 = JSON.parse(php('video-state'));
  ok(state0.status === 'approved' && !state0.file, 'it waits for an open page');
  php('video-publish');
  ok(JSON.parse(php('video-state')).status === 'approved', 'the plugin does not post anything without a video file');

  const browser = await chromium.launch();
  const ctx = await browser.newContext(process.env.SF_PAGE === 'console' ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } : { viewport: { width: 1100, height: 800 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/ERR_TUNNEL|net::ERR|401/.test(m.text())) errors.push(m.text()); });
  await page.goto(BASE + '/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'Admin#Pass12345');
  await page.click('#wp-submit');
  await page.waitForLoadState('load');
  const t0 = Date.now();
  const target = process.env.SF_PAGE === 'console' ? php('console-url') : BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=overview';
  console.log('  page: ' + target);
  await page.goto(target);
  await page.waitForSelector('#sf-vm', { timeout: 20000 });
  ok(true, 'the video maker chip appears on the plugin screen');
  let last = '';
  try {
    await page.waitForFunction(() => /Video made and sent|Video maker:/.test(document.getElementById('sf-vm').textContent), null, { timeout: 170000, polling: 1000 });
  } catch (e) { /* reported below */ }
  last = await page.locator('#sf-vm').innerText();
  console.log('  chip: ' + last);
  ok(/Video made and sent/.test(last), 'the page made the video by itself and sent it (' + Math.round((Date.now() - t0) / 1000) + ' s)');
  const st = JSON.parse(php('video-state'));
  ok(!!st.file && /\.mp4$/.test(st.file), 'the plugin saved the MP4: ' + st.file);
  php('video-publish');
  const st2 = JSON.parse(php('video-state'));
  ok(st2.status === 'posted' && !!st2.fb, 'the plugin posted it to Facebook (' + st2.status + ')');
  const state = await (await fetch(MOCK + '/__state')).json();
  const vid = state.fb.filter((f) => f.kind === 'video').pop();
  ok(vid && vid.file_url === st.url && /RESULT \| Asia Cup 2026/.test(vid.description), 'Facebook was given the saved video address and the caption');
  const res = await fetch(st.url);
  const buf = Buffer.from(await res.arrayBuffer());
  fs.writeFileSync(path.join(OUT, 'browser-made.mp4'), buf);
  ok(res.status === 200 && buf.length > 100000, 'the address serves the file (' + Math.round(buf.length / 1024) + ' KB)');
  const probe = JSON.parse(execSync(`ffprobe -v error -show_streams -show_format -of json ${path.join(OUT, 'browser-made.mp4')}`, { encoding: 'utf8' }));
  const v = probe.streams.find((s) => s.codec_type === 'video');
  const a = probe.streams.find((s) => s.codec_type === 'audio');
  ok(v && v.codec_name === 'h264' && v.width === 720 && v.height === 1280, 'video is H.264, 720 x 1280');
  ok(Math.abs(parseFloat(probe.format.duration) - 21.5) < 1, 'it is about 21.5 seconds long (' + probe.format.duration + ')');
  ok(!!a, 'it has an audio track (' + (a ? a.codec_name : 'none') + ')');
  ok(errors.length === 0, 'no JavaScript errors in the page' + (errors.length ? ': ' + errors.slice(0, 2).join('; ') : ''));
  await browser.close();
  php('video-reset');
  console.log(failures ? '\n' + failures + ' FAILED' : '\nAll checks passed');
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.error('Test crashed:', e); process.exit(1); });
