/*
 * Scripts Manager By Mudassar – browser console test
 *
 * HOW TO RUN
 *  1. Log in as an ADMINISTRATOR on your TEST site and open any wp-admin page.
 *  2. Press F12 -> Console. (Chrome may ask you to type "allow pasting" first.)
 *  3. Paste this whole file and press Enter. Takes about 1-2 minutes.
 *
 * It uses the real plugin forms/links with your real nonces, creates snippets
 * named MSST-TEST-xxxx, checks the public site as a logged-out visitor, and
 * deletes everything it created. Your global header/footer settings are restored.
 * Run it on a staging/test site, not on a live client site.
 * If the site has page caching, disable it first (visitor checks add a cache-buster).
 */
(async () => {
  const ADMIN = new URL(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', location.href).href.replace(/admin-ajax\.php.*$/, '');
  const HOME = ADMIN.replace(/wp-admin\/$/, '');
  const PAGE = ADMIN + 'options-general.php?page=scripts-manager-by-mudassar&tab=';
  const POST = ADMIN + 'admin-post.php';
  const TAG = 'MSST-TEST-' + Date.now().toString(36);
  const results = [];
  const madeSnippets = [];
  const madePosts = [];
  let restNonce = '';
  let originalGlobal = null;

  const log = (...a) => console.log('%c' + a.join(' '), 'color:#0071e3');
  const check = (name, cond, detail = '') => {
    results.push({ check: name, result: cond ? 'PASS' : 'FAIL', detail: cond ? '' : String(detail).slice(0, 180) });
    console.log((cond ? '%c PASS ' : '%c FAIL ') + name + (cond ? '' : '  -> ' + String(detail).slice(0, 180)),
      cond ? 'color:#248a3d' : 'color:#d70015;font-weight:bold');
  };
  const dom = (t) => new DOMParser().parseFromString(t, 'text/html');
  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  async function get(url, opts = {}) {
    const r = await fetch(url, { credentials: 'same-origin', ...opts });
    const text = await r.text();
    return { status: r.status, url: r.url, text, doc: dom(text) };
  }
  async function visitor(url) {
    const u = url + (url.includes('?') ? '&' : '?') + 'msst_nc=' + Date.now() + Math.random().toString(36).slice(2, 6);
    const r = await fetch(u, { credentials: 'omit', cache: 'no-store' });
    return { status: r.status, text: await r.text() };
  }
  const formNonce = (doc, action) => {
    const input = doc.querySelector(`form input[name="action"][value="${action}"]`);
    return input ? input.closest('form').querySelector('input[name="_wpnonce"]').value : '';
  };

  async function saveSnippet(f) {
    const page = await get(PAGE + 'edit');
    const nonce = formNonce(page.doc, 'msst_save_snippet');
    const body = new URLSearchParams({
      action: 'msst_save_snippet', _wpnonce: nonce, msst_id: String(f.id || 0), msst_title: f.title,
      msst_type: f.type, msst_code: f.code, msst_location: f.location,
      msst_param: String(f.param || 1), msst_priority: String(f.priority || 10),
      msst_start: f.start || '', msst_end: f.end || '',
      msst_pages: (f.rules && f.rules.length) ? 'some' : 'every',
    });
    if (f.active !== false) body.set('msst_active', '1');
    (f.rules || []).forEach((group, gi) => group.forEach((r, ri) => {
      body.set(`msst_rules[${gi}][${ri}][type]`, r.type);
      body.set(`msst_rules[${gi}][${ri}][op]`, r.op);
      body.set(`msst_rules[${gi}][${ri}][value]`, r.value);
    }));
    const res = await fetch(POST, { method: 'POST', credentials: 'same-origin', body });
    const text = await res.text();
    const d = dom(text);
    const m = res.url.match(/snippet=(\d+)/);
    const id = m ? m[1] : null;
    if (id && !madeSnippets.includes(id)) madeSnippets.push(id);
    return { id, flash: (d.querySelector('.msst-note') || {}).textContent || '', status: res.status, url: res.url };
  }

  async function listRow(id) {
    const p = await get(PAGE + 'snippets&s=' + encodeURIComponent(TAG));
    const toggle = [...p.doc.querySelectorAll('a.msst-toggle')].find((a) => new RegExp('snippet=' + id + '(&|$)').test(a.getAttribute('href')));
    if (!toggle) return null;
    const tr = toggle.closest('tr');
    return { on: toggle.classList.contains('is-on'), text: tr.textContent, toggleHref: toggle.getAttribute('href'), doc: p.doc };
  }

  async function restPost(content, title) {
    const r = await fetch(HOME + 'wp-json/wp/v2/posts', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-WP-Nonce': restNonce, 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, status: 'publish', content }),
    });
    const j = await r.json();
    if (j.id) madePosts.push(j.id);
    return j;
  }

  try {
    log('== Scripts Manager By Mudassar – browser console test ==  tag:', TAG);

    // ---------------------------------------------------------------- 1. admin UI
    log('1. Admin tabs');
    const tabs = ['start', 'headers', 'snippets', 'edit', 'conditions', 'library', 'revisions', 'tools', 'logs', 'settings', 'support'];
    for (const t of tabs) {
      const p = await get(PAGE + t);
      check(`tab "${t}" loads with branding`, p.status === 200 && p.text.includes('Mudassar Shakeel') && p.text.includes('Contact Us'), 'HTTP ' + p.status);
    }
    const sup = await get(PAGE + 'support');
    const links = [...sup.doc.querySelectorAll('a[href*="mudassar.work"]')].map((a) => a.getAttribute('href'));
    check('Contact Us / website links all have UTM tags',
      links.length >= 4 && links.every((h) => h.includes('utm_source=scripts-manager-by-mudassar') && h.includes('utm_medium=wordpress-plugin') && h.includes('utm_content=')), links.join(' | '));
    check('contact links point to /contact/', links.some((h) => h.includes('mudassar.work/contact/')), links.join(' | '));
    check('external links have rel=noopener', [...sup.doc.querySelectorAll('a[href*="mudassar.work"]')].every((a) => /noopener/.test(a.rel)), '');

    // ---------------------------------------------------------------- 2. output types
    log('2. HTML / CSS / JS output');
    const html = await saveSnippet({ title: TAG + ' html', type: 'html', location: 'footer', code: `<div id="${TAG}-html">HTML-OK-${TAG}</div>` });
    check('HTML snippet saved', !!html.id, html.flash);
    const css = await saveSnippet({ title: TAG + ' css', type: 'css', location: 'header', code: `.${TAG}{color:red}` });
    const js = await saveSnippet({ title: TAG + ' js', type: 'js', location: 'footer', code: `window.${TAG.replace(/-/g, '_')}=1;` });
    let home = await visitor(HOME);
    check('visitor sees HTML snippet', home.text.includes('HTML-OK-' + TAG), 'status ' + home.status);
    check('CSS wrapped in <style> in <head>', new RegExp(`<style>\\s*\\.${TAG}\\{color:red\\}`).test(home.text), '');
    check('JS wrapped in <script>', new RegExp(`<script>\\s*window\\.${TAG.replace(/-/g, '_')}=1;`).test(home.text), '');

    // ---------------------------------------------------------------- 3. toggle
    log('3. Toggle on/off');
    let row = await listRow(html.id);
    check('snippet row shows as active', row && row.on, '');
    await get(new URL(row.toggleHref, ADMIN).href); // turn off
    home = await visitor(HOME);
    check('deactivated snippet disappears', !home.text.includes('HTML-OK-' + TAG), '');
    row = await listRow(html.id);
    await get(new URL(row.toggleHref, ADMIN).href); // turn on
    home = await visitor(HOME);
    check('re-activated snippet returns', home.text.includes('HTML-OK-' + TAG), '');

    // ---------------------------------------------------------------- 4. PHP
    log('4. PHP snippets');
    const phpOk = await saveSnippet({ title: TAG + ' php ok', type: 'php', location: 'everywhere', code: `add_action('wp_footer', function(){ echo '<!--${TAG}-php-->'; });` });
    check('PHP snippet saved', !!phpOk.id, phpOk.flash);
    home = await visitor(HOME);
    check('PHP snippet runs on the front end', home.text.includes(`<!--${TAG}-php-->`), '');
    const phpBad = await saveSnippet({ title: TAG + ' php bad', type: 'php', location: 'everywhere', code: "add_filter('a',;" });
    check('PHP syntax error is rejected on save', !phpBad.id && phpBad.flash.length > 0, phpBad.flash || phpBad.url);
    const boom = await saveSnippet({ title: TAG + ' php boom', type: 'php', location: 'everywhere', code: `throw new Exception('boom-${TAG}');` });
    home = await visitor(HOME);
    check('site still loads when a snippet throws', home.status === 200, 'status ' + home.status);
    row = await listRow(boom.id);
    check('throwing snippet auto-disabled with error badge', row && !row.on && /turned off automatically/i.test(row.text), row ? row.text.replace(/\s+/g, ' ').slice(0, 120) : 'row not found');
    const logs = await get(PAGE + 'logs');
    check('error recorded in the Error Log tab', logs.text.includes('boom-' + TAG), '');
    const fileBox = logs.doc.querySelector('#msst-error-file');
    check('error log FILE section shows the error', !!fileBox && fileBox.textContent.includes('boom-' + TAG), fileBox ? fileBox.textContent.slice(0, 120) : 'section missing');
    const dl = logs.doc.querySelector('a[href*="action=msst_download_log"]');
    check('download link for the error log file exists', !!dl, '');
    if (dl) {
      const dlRes = await fetch(new URL(dl.getAttribute('href'), ADMIN).href, { credentials: 'same-origin' });
      const dlText = await dlRes.text();
      check('error log file downloads as plain text with our error', dlRes.status === 200 && /text\/plain/.test(dlRes.headers.get('content-type') || '') && dlText.includes('boom-' + TAG), 'HTTP ' + dlRes.status);
      const dlNoNonce = await fetch(new URL(dl.getAttribute('href').replace(/&_wpnonce=[^&]+/, ''), ADMIN).href, { credentials: 'same-origin' });
      check('log download without nonce is refused', dlNoNonce.status === 403, 'HTTP ' + dlNoNonce.status);
      const dlAnon = await fetch(new URL(dl.getAttribute('href'), ADMIN).href, { credentials: 'omit' });
      check('log download for a logged-out visitor is refused', !(await dlAnon.text()).includes('boom-' + TAG), '');
    }

    // ---------------------------------------------------------------- 5. conditions & placement (needs a post)
    log('5. Conditional logic, paragraph insertion, shortcode');
    const nonceRes = await fetch(ADMIN + 'admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' });
    restNonce = (await nonceRes.text()).trim();
    const post = await restPost('<p>P1</p><p>P2</p><p>P3</p>', TAG + ' post');
    check('test post created via REST', !!post.id && !!post.link, JSON.stringify(post).slice(0, 120));
    const front = await saveSnippet({ title: TAG + ' front', type: 'html', location: 'footer', code: `<i>${TAG}-FRONT</i>`, rules: [[{ type: 'page_type', op: 'is', value: 'front_page' }]] });
    const notFront = await saveSnippet({ title: TAG + ' notfront', type: 'html', location: 'footer', code: `<i>${TAG}-NOTFRONT</i>`, rules: [[{ type: 'page_type', op: 'is_not', value: 'front_page' }]] });
    const afterP2 = await saveSnippet({ title: TAG + ' p2', type: 'html', location: 'after_paragraph', param: 2, code: `<b>${TAG}-P2</b>` });
    const sc = await saveSnippet({ title: TAG + ' sc', type: 'html', location: 'shortcode', code: `<u>${TAG}-SC</u>` });
    const postPage = await visitor(post.link);
    home = await visitor(HOME);
    check('rule "is front page": shown on the home page', home.text.includes(`${TAG}-FRONT`) && !home.text.includes(`${TAG}-NOTFRONT`), 'FRONT missing or NOTFRONT present');
    check('rule "is front page": hidden on a post', !postPage.text.includes(`${TAG}-FRONT`), '');
    check('rule "is not front page": shown on a post', postPage.text.includes(`${TAG}-NOTFRONT`), '');
    check('after paragraph 2 inserted in the right place', new RegExp(`P2</p>\\s*<b>${TAG}-P2</b>\\s*<p>P3`).test(postPage.text), '');
    const scPost = await restPost(`[msst_snippet id="${sc.id}"]`, TAG + ' sc post');
    const scPage = await visitor(scPost.link);
    check('shortcode renders the snippet', scPage.text.includes(`${TAG}-SC`), '');
    const phpSc = await saveSnippet({ title: TAG + ' php sc', type: 'php', location: 'everywhere', code: `add_action('wp_footer', function(){ echo '<!--${TAG}-once-->'; });` });
    const scPhpPost = await restPost(`[msst_snippet id="${phpSc.id}"]`, TAG + ' sc php post');
    const scPhpPage = await visitor(scPhpPost.link);
    check('PHP snippets never run from a shortcode (only once, from normal run)', (scPhpPage.text.match(new RegExp(`<!--${TAG}-once-->`, 'g')) || []).length === 1, '');
    const bogusRule = await saveSnippet({ title: TAG + ' bogus', type: 'html', location: 'footer', code: `<i>${TAG}-BOGUS</i>`, rules: [[{ type: 'url', op: 'DROP;--', value: 'x' }]] });
    home = await visitor(HOME);
    check('invalid rule is dropped (snippet shows as unconditional)', home.text.includes(`${TAG}-BOGUS`), '');

    // ---------------------------------------------------------------- 6. schedule
    log('6. Scheduling');
    const expired = await saveSnippet({ title: TAG + ' expired', type: 'html', location: 'footer', code: `<i>${TAG}-EXPIRED</i>`, end: '2000-01-01T00:00' });
    const future = await saveSnippet({ title: TAG + ' future', type: 'html', location: 'footer', code: `<i>${TAG}-FUTURE</i>`, start: '2099-01-01T00:00' });
    home = await visitor(HOME);
    check('expired snippet hidden', !home.text.includes(`${TAG}-EXPIRED`), '');
    check('future snippet hidden', !home.text.includes(`${TAG}-FUTURE`), '');

    // ---------------------------------------------------------------- 7. global header/footer
    log('7. Global header/footer + integrations');
    const hp = await get(PAGE + 'headers');
    const val = (n) => (hp.doc.querySelector(`[name="${n}"]`) || {}).value || '';
    originalGlobal = { header: val('msst_header'), body: val('msst_body'), footer: val('msst_footer'), ga4: val('msst_ga4'), gtm: val('msst_gtm'), meta: val('msst_meta'), tiktok: val('msst_tiktok') };
    const hnonce = formNonce(hp.doc, 'msst_save_headers');
    const saveGlobal = async (o) => fetch(POST, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({
      action: 'msst_save_headers', _wpnonce: hnonce, msst_header: o.header, msst_body: o.body, msst_footer: o.footer,
      msst_ga4: o.ga4, msst_gtm: o.gtm, msst_meta: o.meta, msst_tiktok: o.tiktok }) });
    await saveGlobal({ ...originalGlobal, header: originalGlobal.header + `\n<meta name="${TAG}" content="hdr">`, footer: originalGlobal.footer + `\n<!--${TAG}-ftr-->`, ga4: 'G-TEST1234AB' });
    home = await visitor(HOME);
    check('global header code printed', home.text.includes(`name="${TAG}" content="hdr"`), '');
    check('global footer code printed', home.text.includes(`<!--${TAG}-ftr-->`), '');
    check('GA4 script generated from ID', home.text.includes('G-TEST1234AB'), '');
    await saveGlobal({ ...originalGlobal, ga4: 'G-1");alert(1);//' });
    home = await visitor(HOME);
    check('GA4 injection attempt rejected (nothing printed)', !home.text.includes('alert(1);//'), '');

    // ---------------------------------------------------------------- 8. revisions
    log('8. Revisions');
    await saveSnippet({ id: html.id, title: TAG + ' html', type: 'html', location: 'footer', code: `<div id="${TAG}-html">HTML-V2-${TAG}</div>` });
    const rev = await get(PAGE + 'revisions&snippet=' + html.id);
    check('editing code stores a revision', /See changes/.test(rev.text) && /Go back to this/.test(rev.text), '');
    home = await visitor(HOME);
    check('updated code is live', home.text.includes('HTML-V2-' + TAG), '');

    // ---------------------------------------------------------------- 9. safe mode
    log('9. Safe mode');
    const st = await get(PAGE + 'settings');
    const safeUrl = ((st.doc.querySelector('code.msst-copy') || {}).textContent || '').trim();
    check('safe-mode URL shown on Settings tab', /msst_safe_mode=/.test(safeUrl), safeUrl);
    const safe = await visitor(safeUrl);
    check('safe mode with right secret turns snippets off', !safe.text.includes('HTML-V2-' + TAG) && !safe.text.includes(`<!--${TAG}-php-->`), '');
    const wrong = await visitor(HOME + '?msst_safe_mode=definitely-wrong');
    check('safe mode with wrong secret does nothing', wrong.text.includes('HTML-V2-' + TAG), '');

    // ---------------------------------------------------------------- 10. import/export
    log('10. Export / import');
    const tp = await get(PAGE + 'tools');
    const exp = await fetch(POST, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'msst_export', _wpnonce: formNonce(tp.doc, 'msst_export') }) });
    const expText = await exp.text();
    let expJson = null; try { expJson = JSON.parse(expText); } catch (e) { /* ignore */ }
    check('export returns valid JSON with our snippets', expJson && expJson.plugin === 'scripts-manager-by-mudassar' && expText.includes(TAG), expText.slice(0, 100));
    const fd = new FormData();
    fd.append('action', 'msst_import');
    fd.append('_wpnonce', formNonce(tp.doc, 'msst_import'));
    fd.append('msst_file', new Blob([JSON.stringify({ plugin: 'scripts-manager-by-mudassar', version: '1.1.0', snippets: [
      { title: TAG + ' imported', type: 'html', code: `<i>${TAG}-IMPORTED</i>`, location: 'footer', param: 1, priority: 10, conditions: [], start: 0, end: 0 }] })], { type: 'application/json' }), 'import.json');
    const imp = await fetch(POST, { method: 'POST', credentials: 'same-origin', body: fd });
    const impDoc = dom(await imp.text());
    check('import succeeds', /Imported 1/.test((impDoc.querySelector('.msst-note') || {}).textContent || ''), (impDoc.querySelector('.msst-note') || {}).textContent);
    home = await visitor(HOME);
    check('imported snippet is INACTIVE until reviewed', !home.text.includes(`${TAG}-IMPORTED`), '');
    const badFd = new FormData();
    badFd.append('action', 'msst_import');
    badFd.append('_wpnonce', formNonce(tp.doc, 'msst_import'));
    badFd.append('msst_file', new Blob(['{"plugin":"other","snippets":[]}'], { type: 'application/json' }), 'bad.json');
    const badImp = dom(await (await fetch(POST, { method: 'POST', credentials: 'same-origin', body: badFd })).text());
    check('wrong-format import rejected', /not a valid/i.test((badImp.querySelector('.msst-note') || {}).textContent || ''), '');

    // ---------------------------------------------------------------- 11. security
    log('11. Security');
    const delHref = (await listRow(html.id)).doc.querySelector(`a[href*="action=msst_delete"][href*="snippet=${html.id}"]`).getAttribute('href');
    const noNonce = delHref.replace(/&_wpnonce=[^&]+/, '');
    let r = await fetch(new URL(noNonce, ADMIN).href, { credentials: 'same-origin' });
    check('delete without nonce refused (CSRF)', r.status === 403, 'HTTP ' + r.status);
    r = await fetch(new URL(delHref.replace(/_wpnonce=[^&]+/, '_wpnonce=deadbeef12'), ADMIN).href, { credentials: 'same-origin' });
    check('delete with a wrong nonce refused', r.status === 403, 'HTTP ' + r.status);
    check('snippet still exists after those attempts', !!(await listRow(html.id)), '');
    r = await fetch(POST, { method: 'POST', credentials: 'omit', redirect: 'follow', body: new URLSearchParams({ action: 'msst_save_snippet', msst_title: TAG + ' anon', msst_type: 'html', msst_code: 'x' }) });
    const anonList = await get(PAGE + 'snippets&s=' + encodeURIComponent(TAG + ' anon'));
    check('logged-out visitor cannot use admin-post save (refused, nothing created)', ([400, 401, 403].includes(r.status) || /wp-login\.php/.test(r.url)) && !anonList.text.includes(TAG + ' anon'), 'HTTP ' + r.status + ' ' + r.url);
    const anonPage = await fetch(PAGE + 'snippets', { credentials: 'omit' });
    check('logged-out visitor cannot open the plugin page', /wp-login\.php/.test(anonPage.url), anonPage.url);
    const xss = await saveSnippet({ title: TAG + ' <img src=x onerror=alert(1)>', type: 'html', location: 'footer', code: '<i>x</i>', active: false });
    const xssList = await get(PAGE + 'snippets&s=' + encodeURIComponent(TAG));
    check('snippet title is escaped in the admin list (no XSS)', !/<img[^>]+onerror/i.test(xssList.text), '');
    const dbg = await visitor(HOME);
    check('no PHP warnings/notices printed on the front end', !/(Warning|Notice|Deprecated|Fatal error)\s*:.*(scripts-manager-by-mudassar|msst_)/i.test(dbg.text), '');
  } catch (e) {
    check('script finished without a runtime error', false, e && e.stack || e);
  } finally {
    // ------------------------------------------------------------------ cleanup
    log('Cleaning up…');
    try {
      const bulk = await get(PAGE + 'snippets&s=' + encodeURIComponent(TAG));
      const ids = [...bulk.doc.querySelectorAll('input[name="ids[]"]')].map((i) => i.value);
      if (ids.length) {
        const body = new URLSearchParams({ action: 'msst_bulk', _wpnonce: formNonce(bulk.doc, 'msst_bulk'), bulk_action: 'delete' });
        ids.forEach((i) => body.append('ids[]', i));
        await fetch(POST, { method: 'POST', credentials: 'same-origin', body });
      }
      for (const id of madePosts) {
        await fetch(HOME + `wp-json/wp/v2/posts/${id}?force=true`, { method: 'DELETE', credentials: 'same-origin', headers: { 'X-WP-Nonce': restNonce } });
      }
      if (originalGlobal) {
        const hp = await get(PAGE + 'headers');
        await fetch(POST, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({
          action: 'msst_save_headers', _wpnonce: formNonce(hp.doc, 'msst_save_headers'),
          msst_header: originalGlobal.header, msst_body: originalGlobal.body, msst_footer: originalGlobal.footer,
          msst_ga4: originalGlobal.ga4, msst_gtm: originalGlobal.gtm, msst_meta: originalGlobal.meta, msst_tiktok: originalGlobal.tiktok }) });
      }
      const left = await get(PAGE + 'snippets&s=' + encodeURIComponent(TAG));
      check('cleanup: all test snippets deleted', !left.text.includes(TAG + ' '), '');
    } catch (e) {
      console.warn('Cleanup problem – delete "' + TAG + '" snippets manually.', e);
    }
    const failed = results.filter((x) => x.result === 'FAIL');
    console.log('%c==== ' + (results.length - failed.length) + '/' + results.length + ' checks passed ====', 'font-size:14px;font-weight:bold');
    console.table(results);
    if (failed.length) console.log('%cFAILED:\n' + failed.map((f) => '- ' + f.check + '  ' + f.detail).join('\n'), 'color:#d70015');
    window.msstTestResults = results;
  }
})();
