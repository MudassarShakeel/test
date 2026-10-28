// Phone-sized browser test: passkeys, email links, passwords-off and the home-page console.
const { chromium, devices } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost:8099';
const WORK = process.env.SF_WP_WORK;
const OUT = process.env.OUT || '/tmp/sf-mobile-out';
fs.mkdirSync(OUT, { recursive: true });
let failures = 0;
const ok = (c, l) => { console.log((c ? '  PASS  ' : '  FAIL  ') + l); if (!c) failures++; };
const php = (cmd, ...a) => execSync(`php ${path.join(__dirname, 'run.php')} ${path.join(__dirname, 'helpers.php')} ${cmd} ${a.join(' ')}`, { encoding: 'utf8', env: { ...process.env, SF_WP_WORK: WORK } }).trim().split('\n').pop();
const mails = () => { try { return JSON.parse(fs.readFileSync(WORK + '/site/wp-content/mail-capture.json', 'utf8')); } catch (e) { return []; } };
const noSideScroll = (page) => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);
const smallTargets = (page, sel) => page.evaluate((s) => [...document.querySelectorAll(s)].filter(e => e.offsetParent && (e.getBoundingClientRect().height < 40 || e.getBoundingClientRect().width < 40)).map(e => e.id || e.className || e.tagName), sel);

async function newPhone(browser) {
  const ctx = await browser.newContext({ ...devices['iPhone 13'], baseURL: BASE });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => !/profile\.php/.test(page.url()) && errors.push(e.message + ' @' + page.url()));
  page.on('console', m => { if (m.type() === 'error' && !/ERR_TUNNEL|net::ERR|401/.test(m.text())) errors.push('console: ' + m.text() + ' @' + page.url()); });
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  const auth = await cdp.send('WebAuthn.addVirtualAuthenticator', { options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true } });
  return { ctx, page, errors, cdp, authId: auth.authenticatorId };
}
async function pwLogin(page, user, pass) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await page.click('#wp-submit');
  await page.waitForLoadState('load');
}

(async () => {
  php('mobile-reset'); php('clear-locks'); php('reset'); php('subscriber');
  const browser = await chromium.launch();

  console.log('1. Home page before setup');
  let a = await newPhone(browser);
  let { page } = a;
  await pwLogin(page, 'admin', 'Admin#Pass12345');
  await page.goto('/wp-admin/admin.php?page=sixandfour-live&tab=mobile');
  ok(await noSideScroll(page), 'Mobile home screen has no sideways scroll at 390px');
  await Promise.all([page.waitForNavigation(), page.click('text=Create the mobile home page')]);
  ok(/mobile console/.test(await page.locator('.sf-notice').first().innerText()), 'the home page is created');

  console.log('2. Admin screens fit a phone');
  for (const tab of ['overview', 'queue', 'cards', 'engage', 'tournaments', 'teams', 'manual', 'security', 'mobile', 'setup']) {
    await page.goto('/wp-admin/admin.php?page=sixandfour-live&tab=' + tab);
    ok(await noSideScroll(page), 'tab "' + tab + '" has no sideways scroll');
  }
  await page.goto('/wp-admin/admin.php?page=sixandfour-live&tab=security');
  const smallSec = await smallTargets(page, '.sf-wrap .sf-btn, .sf-wrap button');
  ok(smallSec.length === 0, 'buttons on Security are at least 40px (' + smallSec.join(',') + ')');
  await page.screenshot({ path: path.join(OUT, 'wp-admin-security-phone.png'), fullPage: true });

  console.log('3. Add a passkey');
  await page.fill('#sf-pk-name', 'Test phone');
  await Promise.all([page.waitForNavigation({ timeout: 20000 }), page.click('#sf-pk-add')]);
  ok(php('passkey-count') === '1', 'one passkey is stored');
  ok(/Test phone/.test(await page.locator('.sf-wrap').innerText()), 'the passkey is listed by name');

  console.log('4. Home page as a guest, then passkey sign-in');
  const guest = await newPhone(browser);
  await guest.page.goto('/');
  await guest.page.waitForSelector('.sfc-login');
  ok(await noSideScroll(guest.page), 'guest card has no sideways scroll');
  ok(await guest.page.locator('.sfc-main').count() === 0, 'a guest sees no controls');
  ok(!/sf_options|fb_token/.test(await guest.page.content()), 'a guest sees no settings data');
  const gr = await guest.page.request.get('/index.php?rest_route=/sixandfour/v1/console/state');
  ok(gr.status() === 401 || gr.status() === 403, 'console state is closed to a guest (status ' + gr.status() + ')');
  await guest.page.screenshot({ path: path.join(OUT, 'console-guest-phone.png') });
  // Share the passkey with the guest device.
  const creds = await a.cdp.send('WebAuthn.getCredentials', { authenticatorId: a.authId });
  for (const c of creds.credentials) await guest.cdp.send('WebAuthn.addCredential', { authenticatorId: guest.authId, credential: c });
  await Promise.all([guest.page.waitForNavigation({ timeout: 20000 }), guest.page.click('#g-pk')]).catch(() => {});
  await guest.page.waitForSelector('.sfc-tabs', { timeout: 20000 }).catch(() => {});
  ok(await guest.page.locator('.sfc-tabs').count() === 1, 'passkey sign-in opens the console on the home page');
  const gp = guest.page;

  console.log('5. Console on a phone');
  ok(await noSideScroll(gp), 'console Home has no sideways scroll');
  const small = await smallTargets(gp, '.sfc-tabs button, .sfc-btn');
  ok(small.length === 0, 'tab bar and buttons are at least 40px (' + small.join(',') + ')');
  ok(await gp.locator('#wpadminbar').count() === 0, 'WordPress admin bar is hidden on the console');
  await gp.screenshot({ path: path.join(OUT, 'console-home-phone.png') });
  php('add-pending'); await gp.reload(); await gp.waitForSelector('.sfc-tabs');
  await gp.click('[data-tab=queue]');
  await gp.waitForSelector('.sfc-post');
  ok(await gp.locator('.sfc-post').count() >= 1, 'Queue lists the pending post');
  await gp.screenshot({ path: path.join(OUT, 'console-queue-phone.png') });
  await gp.click('.q-skip');
  await gp.waitForFunction(() => !document.querySelector('.q-skip'), null, { timeout: 10000 }).catch(() => {});
  ok(await gp.locator('.q-skip').count() === 0, 'Skip removes the post from the queue');
  await gp.click('[data-tab=post]');
  ok(await noSideScroll(gp), 'Post tab has no sideways scroll');
  await gp.screenshot({ path: path.join(OUT, 'console-post-phone.png') });
  await gp.click('[data-tab=settings]');
  ok(await noSideScroll(gp), 'Settings tab has no sideways scroll');
  await gp.screenshot({ path: path.join(OUT, 'console-settings-phone.png') });
  await gp.click('[data-tab=home]');
  await gp.click('#a-pause');
  await gp.waitForTimeout(1200);
  ok(php('option', 'paused') === '1', 'Pause from the phone is saved');
  await gp.click('#a-pause');
  await gp.waitForTimeout(1200);
  ok(php('option', 'paused') === '0', 'Resume is saved');

  console.log('6. Console REST rules');
  const noNonce = await gp.request.post('/index.php?rest_route=/sixandfour/v1/console/pause', { data: { pause: true } });
  ok(noNonce.status() === 401 || noNonce.status() === 403, 'a console write without a nonce is refused (status ' + noNonce.status() + ')');
  const sub = await newPhone(browser);
  await pwLogin(sub.page, 'fan1', 'Fan#Pass12345');
  await sub.page.goto('/');
  await sub.page.waitForSelector('.sfc-login, .sfc-tabs');
  ok(await sub.page.locator('.sfc-tabs').count() === 0, 'a subscriber does not get the controls');
  const sr = await sub.page.request.get('/index.php?rest_route=/sixandfour/v1/console/state');
  ok(sr.status() === 403 || sr.status() === 401, 'console state is closed to a subscriber (status ' + sr.status() + ')');

  console.log('7. Email link');
  php('clear-locks');
  const ml = await newPhone(browser);
  await ml.page.goto('/');
  await ml.page.click('#g-ml-open');
  await ml.page.fill('#g-login', 'admin');
  await ml.page.click('#g-ml-send');
  await ml.page.waitForFunction(() => /link is on its way/.test(document.getElementById('g-msg').textContent), null, { timeout: 10000 }).catch(() => {});
  ok(/link is on its way/.test(await ml.page.locator('#g-msg').innerText()), 'the page says a link is on its way');
  const m = mails();
  ok(m.length === 1, 'one email was sent');
  const link = (m[0] && m[0].message.match(/https?:\/\/\S+sf_magic=\S+/) || [''])[0];
  ok(!!link, 'the email contains a sign-in link');
  const scan = await ml.page.request.get(link);
  ok(scan.status() === 200 && /Tap the button/.test(await scan.text()), 'opening the link only shows a button (scanner-safe)');
  const scan2 = await ml.page.request.get(link);
  ok(/Tap the button/.test(await scan2.text()), 'a second look still works, nothing was used up');
  await ml.page.goto(link);
  await Promise.all([ml.page.waitForNavigation(), ml.page.click('button[name=sf_magic_go]')]);
  await ml.page.goto('/');
  await ml.page.waitForSelector('.sfc-tabs', { timeout: 15000 }).catch(() => {});
  ok(await ml.page.locator('.sfc-tabs').count() === 1, 'tapping the button signs in');
  const reuse = await (await newPhone(browser)).page.request.post(link, { form: { sf_magic_go: '1' }, maxRedirects: 0 });
  ok(/expired or was already used/.test(await reuse.text()), 'the link cannot be used twice');
  const unknown = await ml.page.request.post('/index.php?rest_route=/sixandfour/v1/magic/request', { data: { login: 'nobody@example.test' } });
  ok(unknown.status() === 200 && mails().length === 1, 'an unknown user gets the same answer and no email');
  const subm = await ml.page.request.post('/index.php?rest_route=/sixandfour/v1/magic/request', { data: { login: 'fan1' } });
  ok(subm.status() === 200 && mails().length === 1, 'a subscriber gets no sign-in email');

  console.log('8. Passwords off for administrators');
  await page.goto('/wp-admin/admin.php?page=sixandfour-live&tab=security');
  await page.check('input[name=passwordless_only]', { force: true });
  await Promise.all([page.waitForNavigation(), page.click('form:has(input[value=sf_save_signin]) button.sf-primary')]);
  ok(php('option', 'passwordless_only') === '1', 'passwords-off is saved');
  const po = await newPhone(browser);
  await pwLogin(po.page, 'admin', 'Admin#Pass12345');
  ok(/wp-login/.test(po.page.url()) && !/wp-admin/.test(po.page.url()), 'the admin password is refused');
  ok(/passkey|email link|without a password/i.test(await po.page.locator('body').innerText()), 'the sign-in page explains why');
  ok(await po.page.locator('#sf-pk-login').count() === 1, 'the sign-in page offers a passkey button');
  await po.page.screenshot({ path: path.join(OUT, 'wp-login-phone.png') });
  const fan = await newPhone(browser);
  await pwLogin(fan.page, 'fan1', 'Fan#Pass12345');
  ok(!/wp-login/.test(fan.page.url()), 'other users keep their passwords');
  await po.page.goto('/wp-login.php');
  await po.page.evaluate(() => 0);
  const login2 = await newPhone(browser);
  const fresh = await guest.cdp.send('WebAuthn.getCredentials', { authenticatorId: guest.authId });
  for (const c of fresh.credentials) await login2.cdp.send('WebAuthn.addCredential', { authenticatorId: login2.authId, credential: c });
  await login2.page.goto('/wp-login.php');
  await Promise.all([login2.page.waitForURL(/wp-admin/, { timeout: 20000 }).catch(() => {}), login2.page.click('#sf-pk-login')]);
  ok(/wp-admin/.test(login2.page.url()), 'passkey sign-in works from the wp-login page: ' + login2.page.url() + ' ' + (await login2.page.locator('#sf-pl-msg').innerText().catch(() => '')));

  console.log('9. Cannot lock yourself out');
  php('mobile-reset');
  await page.goto('/wp-admin/admin.php?page=sixandfour-live&tab=security');
  await page.check('input[name=passwordless_only]', { force: true });
  await Promise.all([page.waitForNavigation(), page.click('form:has(input[value=sf_save_signin]) button.sf-primary')]);
  ok(php('option', 'passwordless_only') === '0', 'passwords-off is refused without a passkey');

  console.log('10. Restore the old home page');
  await page.goto('/wp-admin/admin.php?page=sixandfour-live&tab=mobile');
  await Promise.all([page.waitForNavigation(), page.click('text=Restore my old home page')]);
  ok(/back to how it was/.test(await page.locator('.sf-notice').first().innerText()), 'the old home page is restored');

  const all = [a, guest, sub, ml, po, fan, login2].flatMap(x => x.errors);
  ok(all.length === 0, 'no JavaScript errors in the browser ' + all.slice(0, 2).join('; '));
  await browser.close();
  console.log(failures ? '\n' + failures + ' FAILED' : '\nAll checks passed');
  process.exit(failures ? 1 : 0);
})().catch(e => { console.error('Test crashed:', e); process.exit(1); });
