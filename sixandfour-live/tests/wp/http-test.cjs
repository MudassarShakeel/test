// Browser and HTTP test of the plugin inside a running WordPress. See tests/wp/README.md.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost:8099';
const MOCK = 'http://127.0.0.1:8100';
const WORK = process.env.SF_WP_WORK;     // work folder made by setup.sh
const OUT = process.env.OUT || '/tmp/sf-http-out';
fs.mkdirSync(OUT, { recursive: true });
let failures = 0;
const ok = (c, l) => { console.log((c ? '  PASS  ' : '  FAIL  ') + l); if (!c) failures++; };
const runPhp = (script, cmd) => execSync(`php ${path.join(__dirname, 'run.php')} ${script} ${cmd}`, { encoding: 'utf8', env: { ...process.env, SF_WP_WORK: WORK } });
const php = (cmd) => runPhp(path.join(__dirname, 'helpers.php'), cmd).trim().split('\n').pop();
const world = (o) => fetch(MOCK + '/__world', { method: 'POST', body: JSON.stringify(o) });
const mockState = async () => (await fetch(MOCK + '/__state')).json();

async function login(page, user, pass) {
  await page.goto(BASE + '/wp-login.php');
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await page.click('#wp-submit');
  await page.waitForLoadState('load');
}

(async () => {
  php('reset'); php('clear-locks'); php('subscriber');
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  const consoleErrors = [];
  page.on('pageerror', e => consoleErrors.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) consoleErrors.push(m.text()); });

  console.log('1. Not signed in');
  let r = await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live');
  ok(/wp-login\.php/.test(page.url()), 'the plugin screen sends visitors to the log-in page');

  console.log('2. Public site hardening');
  const home = await ctx.request.get(BASE + '/');
  const h = home.headers();
  ok(h['x-content-type-options'] === 'nosniff', 'X-Content-Type-Options: nosniff is sent');
  ok(/SAMEORIGIN/i.test(h['x-frame-options'] || ''), 'X-Frame-Options: SAMEORIGIN is sent');
  ok(!/name="generator"/i.test(await home.text()), 'no WordPress generator tag in the page');
  ok(/name=['"]robots['"][^>]*noindex/i.test(await home.text()), 'a noindex robots tag is present');
  const noRedirect = await ctx.request.get(BASE + '/?author=1', { maxRedirects: 0 });
  ok(noRedirect.status() === 301, '?author=1 is redirected away (status ' + noRedirect.status() + ')');
  const users = await ctx.request.get(BASE + '/?rest_route=/wp/v2/users');
  ok([401, 403, 404].includes(users.status()), 'the public users list is closed (status ' + users.status() + ')');
  const xml = await ctx.request.post(BASE + '/xmlrpc.php', { data: '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>', headers: { 'Content-Type': 'text/xml' } });
  ok(xml.status() === 403 && /turned off/i.test(await xml.text()), 'XML-RPC is refused with a 403 (status ' + xml.status() + ')');

  console.log('3. Log-in limiter');
  const lim = await (await browser.newContext()).newPage();
  for (let i = 0; i < 5; i++) { await lim.goto(BASE + '/wp-login.php'); await lim.fill('#user_login', 'admin'); await lim.fill('#user_pass', 'wrong' + i); await lim.click('#wp-submit'); await lim.waitForLoadState('load'); }
  await login(lim, 'admin', 'Admin#Pass12345');
  const lockText = await lim.locator('#login_error').innerText().catch(() => '');
  ok(/too many failed/i.test(lockText), 'the right password is refused while locked: "' + lockText.replace(/\s+/g, ' ').slice(0, 70) + '"');
  php('clear-locks');

  console.log('4. Administrator');
  await login(page, 'admin', 'Admin#Pass12345');
  ok(/wp-admin/.test(page.url()), 'administrator signs in');
  const tabs = ['overview', 'queue', 'cards', 'engage', 'tournaments', 'teams', 'players', 'manual', 'security', 'setup'];
  for (const t of tabs) {
    const resp = await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=' + t);
    const body = await page.content();
    const good = resp.status() === 200 && body.includes('sf-wrap') && !/Fatal error|Warning:|Notice:|Deprecated:/.test(body);
    ok(good, 'screen "' + t + '" loads cleanly (status ' + resp.status() + ')');
    if (['overview', 'security', 'setup'].includes(t)) await page.screenshot({ path: path.join(OUT, 'wp-' + t + '.png'), fullPage: true });
  }
  const cssLoaded = await page.evaluate(() => [...document.styleSheets].some(s => (s.href || '').includes('sixandfour-live/assets/admin.css')));
  ok(cssLoaded, 'the plugin stylesheet is loaded');

  console.log('5. Forms need a valid token');
  const noNonce = await page.request.post(BASE + '/wp-admin/admin-post.php', { form: { action: 'sf_save_security', sec_login_limit: '1' } });
  ok(noNonce.status() === 403 && /expired|not allowed|permission/i.test(await noNonce.text()), 'a form post without a nonce is refused (status ' + noNonce.status() + ')');
  const badNonce = await page.request.post(BASE + '/wp-admin/admin-post.php', { form: { action: 'sf_save_setup', _wpnonce: 'deadbeef00', fb_token: 'x' } });
  ok(badNonce.status() === 403, 'a form post with a wrong nonce is refused (status ' + badNonce.status() + ')');
  const getSample = await page.request.get(BASE + '/wp-admin/admin-post.php?action=sf_sample&type=live');
  ok(getSample.status() === 403, 'a sample card without a nonce is refused (status ' + getSample.status() + ')');

  console.log('6. A subscriber is kept out');
  const sctx = await browser.newContext();
  const sp = await sctx.newPage();
  await login(sp, 'fan1', 'Fan#Pass12345');
  const sr = await sp.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live');
  ok(sr.status() === 403 || !(await sp.content()).includes('sf-wrap'), 'the plugin screen is closed to a subscriber (status ' + sr.status() + ')');
  const spost = await sp.request.post(BASE + '/wp-admin/admin-post.php', { form: { action: 'sf_manual_post', caption: 'hello' } });
  ok(spost.status() === 403, 'a subscriber cannot post through the handler (status ' + spost.status() + ')');
  ok(!(await sp.content()).includes('Cricket Sport Live'), 'no plugin menu leaks into the subscriber page');

  console.log('7. Setup and secrets');
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=setup');
  await page.fill('#fb_page_id', '1001234567');
  await page.fill('#fb_token', 'EAABSECRETTOKEN1234567890abcdef');
  await page.fill('#api_key', 'mock-cricket-key-0001');
  await page.fill('#api_key2', 'mock-backup-key-0002');
  await page.fill('#api_key3', 'mock-backup-key-0003');
  await page.fill('#quiet_start', '00:00'); await page.fill('#quiet_end', '00:00');
  await Promise.all([page.waitForNavigation(), page.click('button.sf-primary')]);
  const notice = await page.locator('.sf-notice').first().innerText();
  ok(/Settings saved/.test(notice) && /Connected to Cricket Sport/.test(notice), 'settings saved and the Page was verified: "' + notice.replace(/\s+/g, ' ') + '"');
  const raw = php('raw-option');
  ok(!raw.includes('SECRETTOKEN') && !raw.includes('mock-cricket-key') && !raw.includes('mock-backup-key') && raw.includes('sf1:'), 'the saved option holds only encrypted secrets');
  const setupHtml = await page.content();
  ok(!setupHtml.includes('SECRETTOKEN') && !setupHtml.includes('mock-cricket-key') && !setupHtml.includes('mock-backup-key'), 'the Setup page never prints the secrets back');
  ok(/A token is saved/.test(setupHtml), 'it says a token is saved instead');
  ok(/of 300 used over 3 keys/.test(setupHtml.replace(/\s+/g,' ')) || /used over 3 keys/.test(setupHtml), 'Setup counts three keys and shows the calls used');
  ok(/Top teams/.test(setupHtml) && /Skip women/.test(setupHtml), 'Setup shows the match filter');
  await page.screenshot({ path: path.join(OUT, 'wp-setup-saved.png'), fullPage: true });

  console.log('8. Security screen');
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=security');
  const checksText = await page.locator('.sf-group').first().innerText();
  ok(/Secrets encrypted/.test(checksText) && /Log-in limiter/.test(checksText), 'the health check lists its rows');
  ok(!/Secrets encrypted[\s\S]{0,200}Fix/.test(checksText), 'secrets show as encrypted');
  await page.uncheck('input[name=sec_headers]', { force: true });
  await Promise.all([page.waitForNavigation(), page.click('form:has(input[value=sf_save_security]) button.sf-primary')]);
  ok(/Security settings saved/.test(await page.locator('.sf-notice').first().innerText()), 'a hardening switch saves');
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=security');
  ok(!(await page.locator('input[name=sec_headers]').isChecked()), 'and stays off after reloading');
  await page.check('input[name=sec_headers]', { force: true });
  await Promise.all([page.waitForNavigation(), page.click('form:has(input[value=sf_save_security]) button.sf-primary')]);

  console.log('9. Engine through the browser');
  const now = Math.floor(Date.now() / 1000);
  const iso = (t) => new Date(t * 1000).toISOString().slice(0, 19);
  await world({ fb: [], fbEdits: [], hits: 0, matches: [{ id: 'hm1', name: 'England vs Australia, 14th Match', matchType: 't20', status: 'Australia won the toss and elected to bat first', venue: 'Harbour Oval, Harbour', dateTimeGMT: iso(now + 1800), teams: ['England', 'Australia'], teamInfo: [{ name: 'England', shortname: 'ENG' }, { name: 'Australia', shortname: 'AUS' }], score: [], series_id: 's1', matchStarted: false, matchEnded: false }] });
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=overview');
  await Promise.all([page.waitForNavigation(), page.click('text=Check matches now')]);
  const st = await mockState();
  const photos = st.fb.filter(x => x.kind === 'photo');
  ok(photos.length === 2 && photos.every(p => p.png), 'two PNG cards reached the fake Facebook (toss and pick a side)');
  ok(photos.some(p => /TOSS/.test(p.caption)) && photos.some(p => /WHO WINS/.test(p.caption)), 'with the right captions');
  ok(photos.every(p => p.tokenInBody), 'the token travels in the request body');
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=overview');
  const ov = await page.content();
  ok(/England v Australia/.test(ov) && /Posted/.test(ov), 'Overview shows the match and the posted items');
  await page.screenshot({ path: path.join(OUT, 'wp-overview-live.png'), fullPage: true });

  console.log('10. Manual post and uploads');
  const jpg = path.join(OUT, 'evil.jpg');
  execSync(`php -r '$im=imagecreatetruecolor(600,400); imagefill($im,0,0,imagecolorallocate($im,40,120,80)); imagejpeg($im,"${jpg}"); file_put_contents("${jpg}", "\\n<?php system(\\"id\\"); ?>", FILE_APPEND);'`);
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=manual');
  await page.setInputFiles('#sf-image', jpg);
  await page.fill('#sf-caption', 'Test manual post');
  await Promise.all([page.waitForNavigation(), page.click('button.sf-primary')]);
  const mnote = await page.locator('.sf-notice').first().innerText();
  ok(/Posted to Facebook/.test(mnote), 'a manual picture posts: "' + mnote.replace(/\s+/g, ' ') + '"');
  const st2 = await mockState();
  const mp = st2.fb.filter(x => x.kind === 'photo').pop();
  ok(mp && mp.jpeg && !mp.php, 'the uploaded JPEG was re-drawn, the hidden PHP code did not reach Facebook');
  const upDir = php('uploads-dir');
  const found = execSync(`find ${upDir}manual -type f`, { encoding: 'utf8' }).trim().split('\n').filter(Boolean);
  ok(found.length >= 1 && found.every(f => !fs.readFileSync(f).includes('<?php')), 'no stored file contains PHP code (' + found.length + ' file)');
  ok(found.every(f => /\.(jpg|png)$/.test(f)), 'stored files are only .jpg or .png');

  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=manual');
  const shell = path.join(OUT, 'shell.php'); fs.writeFileSync(shell, '<?php echo 1;');
  await page.setInputFiles('#sf-image', { name: 'shell.php', mimeType: 'image/jpeg', buffer: fs.readFileSync(shell) });
  await page.fill('#sf-caption', 'bad file');
  await Promise.all([page.waitForNavigation(), page.click('button.sf-primary')]);
  ok(/not allowed|file type|could not be read|not a JPG/i.test(await page.locator('.sf-notice').first().innerText()), 'a .php file posing as an image is refused');
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=manual');
  await page.setInputFiles('#sf-image', { name: 'fake.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('this is plain text, not an image') });
  await page.fill('#sf-caption', 'text as jpg');
  await Promise.all([page.waitForNavigation(), page.click('button.sf-primary')]);
  ok(/not allowed|file type|could not be read|not a JPG/i.test(await page.locator('.sf-notice').first().innerText()), 'a text file named .jpg is refused');
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=manual');
  await Promise.all([page.waitForNavigation(), page.click('button.sf-primary')]);
  ok(/Add a picture or some text/.test(await page.locator('.sf-notice').first().innerText()), 'an empty post is refused');

  console.log('11. A failing Facebook token');
  await world({ fbFail: true });
  await page.goto(BASE + '/wp-admin/admin.php?page=sixandfour-live&tab=manual');
  await page.fill('#sf-caption', 'This will fail');
  await Promise.all([page.waitForNavigation(), page.click('button.sf-primary')]);
  const fnote = await page.locator('.sf-notice').first().innerText();
  ok(/did not accept|expired|token/i.test(fnote), 'the error is shown in plain words: "' + fnote.replace(/\s+/g, ' ').slice(0, 90) + '"');
  await world({ fbFail: false });
  const logs = php('raw-option') + (runPhp(path.join(__dirname, 'helpers.php'), 'posts'));
  ok(!logs.includes('SECRETTOKEN'), 'no secret appears in saved posts or errors');

  ok(consoleErrors.length === 0, 'no JavaScript errors in the browser' + (consoleErrors.length ? ': ' + consoleErrors.slice(0, 2).join(' | ') : ''));
  await browser.close();
  console.log('\n' + (failures ? failures + ' FAILED' : 'All checks passed'));
  process.exit(failures ? 1 : 0);
})().catch(e => { console.error('Test crashed:', e); process.exit(2); });
