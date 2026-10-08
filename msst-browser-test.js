/*
 * Scripts Manager By Mudassar v2 – browser console test
 *
 * HOW TO RUN
 *  1. Log in as an ADMINISTRATOR on your TEST site and open any wp-admin page.
 *  2. Press F12 -> Console. (Chrome may ask you to type "allow pasting" first.)
 *  3. Paste this whole file and press Enter. Takes about 1-2 minutes.
 *
 * It uses the real plugin forms and links with your real nonces, creates snippets
 * named MSST-TEST-xxxx plus a few test pages/posts, checks the public site as a
 * logged-out visitor, then deletes everything it created.
 * Run it on a staging/test site, not on a live client site.
 * If the site has page caching, disable it first (visitor checks add a cache-buster).
 */
(async () => {
  const ADMIN = new URL(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', location.href).href.replace(/admin-ajax\.php.*$/, '');
  const HOME = ADMIN.replace(/wp-admin\/$/, '');
  const PAGE = (slug) => ADMIN + 'admin.php?page=' + slug;
  const LIST = PAGE('scripts-manager');
  const ADD = PAGE('scripts-manager-add');
  const POST = ADMIN + 'admin-post.php';
  const TAG = 'MSST-TEST-' + Date.now().toString(36);
  const results = [];
  const posts = []; // [restBase, id]
  let restNonce = '';

  const log = (...a) => console.log('%c' + a.join(' '), 'color:#0071e3');
  const check = (name, cond, detail = '') => {
    results.push({ check: name, result: cond ? 'PASS' : 'FAIL', detail: cond ? '' : String(detail).slice(0, 200) });
    console.log((cond ? '%c PASS ' : '%c FAIL ') + name + (cond ? '' : '  -> ' + String(detail).slice(0, 200)),
      cond ? 'color:#248a3d' : 'color:#d70015;font-weight:bold');
  };
  const dom = (t) => new DOMParser().parseFromString(t, 'text/html');

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
  const nonceOf = (doc, action) => {
    const input = doc.querySelector(`form input[name="action"][value="${action}"]`);
    return input ? input.closest('form').querySelector('input[name="_wpnonce"]').value : '';
  };

  async function save(f) {
    const page = await get(ADD);
    const body = new URLSearchParams({
      action: 'msst_save_snippet', _wpnonce: nonceOf(page.doc, 'msst_save_snippet'), msst_id: String(f.id || 0),
      msst_name: f.name, msst_type: f.type || 'html', msst_code: f.code,
      msst_display_on: f.display || 'site_wide', msst_location: f.location || 'footer',
      msst_device: f.device || 'all', msst_status: f.status === false ? '0' : '1',
    });
    const t = f.targets || {};
    Object.keys(t).forEach((k) => t[k].forEach((v) => body.append('msst_' + k + '[]', String(v))));
    const res = await fetch(POST, { method: 'POST', credentials: 'same-origin', body });
    const doc = dom(await res.text());
    const m = res.url.match(/[?&]id=(\d+)/);
    return { id: m ? m[1] : null, flash: ((doc.querySelector('.msst-note') || {}).textContent || '').trim(), url: res.url };
  }

  async function row(id) {
    const p = await get(LIST + '&s=' + encodeURIComponent(TAG));
    const toggle = [...p.doc.querySelectorAll('a.msst-toggle')].find((a) => new RegExp('[?&]id=' + id + '(&|$)').test(a.getAttribute('href')));
    if (!toggle) return null;
    const tr = toggle.closest('tr');
    return { on: toggle.classList.contains('is-on'), text: tr.textContent.replace(/\s+/g, ' '), toggle: toggle.getAttribute('href'), del: tr.querySelector('a.msst-danger').getAttribute('href'), doc: p.doc };
  }

  async function rest(base, payload) {
    const r = await fetch(HOME + 'wp-json/wp/v2/' + base, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-WP-Nonce': restNonce, 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
    });
    const j = await r.json();
    if (j.id) posts.push([base, j.id]);
    return j;
  }

  try {
    log('== Scripts Manager By Mudassar v2 – browser test ==  tag:', TAG);
    restNonce = (await (await fetch(ADMIN + 'admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })).text()).trim();

    // ------------------------------------------------------------ 1. screens
    log('1. Screens and branding');
    for (const [name, slug] of [['All Snippets', 'scripts-manager'], ['Add New', 'scripts-manager-add'], ['Tools', 'scripts-manager-tools'], ['Settings', 'scripts-manager-settings']]) {
      const p = await get(PAGE(slug));
      check(`${name} screen loads with branding`, p.status === 200 && p.text.includes('Mudassar Shakeel') && p.text.includes('Contact Us'), 'HTTP ' + p.status);
    }
    const set = await get(PAGE('scripts-manager-settings'));
    const links = [...set.doc.querySelectorAll('a[href*="mudassar.work"]')];
    const hrefs = links.map((a) => a.getAttribute('href'));
    check('Contact Us / website links all have UTM tags', hrefs.length >= 4 && hrefs.every((h) => h.includes('utm_source=scripts-manager-by-mudassar') && h.includes('utm_medium=wordpress-plugin') && h.includes('utm_content=')), hrefs.join(' | '));
    check('contact links point to /contact/', hrefs.some((h) => h.includes('mudassar.work/contact/')), hrefs.join(' | '));
    check('external links have rel=noopener', links.every((a) => /noopener/.test(a.rel)), '');
    const form = (await get(ADD)).doc;
    const labels = [...form.querySelectorAll('.msst-row > .msst-label')].map((x) => x.textContent.trim());
    check('form has Snippet Name, Type, Site Display, Location, Device, Status, Code', ['Snippet Name', 'Snippet Type', 'Site Display', 'Location', 'Device Display', 'Status', 'Snippet / Code'].every((l) => labels.includes(l)), labels.join(', '));
    const displays = [...form.querySelectorAll('#msst_display_on option')].map((o) => o.value);
    check('Site Display has all 11 choices', ['site_wide', 'posts', 'pages', 'categories', 'post_types', 'tags', 'home', 'search', 'archives', 'latest', 'shortcode'].every((d) => displays.includes(d)), displays.join(','));

    // ------------------------------------------------------------ 2. types and locations
    log('2. HTML / CSS / JS in header, footer, content');
    const html = await save({ name: TAG + ' html', code: `<div id="${TAG}-html">HTML-OK-${TAG}</div>`, location: 'footer' });
    check('HTML snippet saved', !!html.id, html.flash);
    const css = await save({ name: TAG + ' css', type: 'css', code: `.${TAG}{color:red}`, location: 'header' });
    const js = await save({ name: TAG + ' js', type: 'js', code: `window.${TAG.replace(/-/g, '_')}=1;`, location: 'header' });
    let home = await visitor(HOME);
    check('HTML printed in the footer', home.text.includes('HTML-OK-' + TAG), 'HTTP ' + home.status);
    check('CSS wrapped in <style> inside <head>', new RegExp(`<head[\\s\\S]*<style>\\s*\\.${TAG}\\{color:red\\}[\\s\\S]*</head>`).test(home.text), '');
    check('JS wrapped in <script>', new RegExp(`<script>\\s*window\\.${TAG.replace(/-/g, '_')}=1;`).test(home.text), '');
    const post = await rest('posts', { title: TAG + ' post', status: 'publish', content: `<p>CONTENT-MARK-${TAG}</p>` });
    const before = await save({ name: TAG + ' before', code: `<i>BEFORE-${TAG}</i>`, location: 'before_content' });
    const after = await save({ name: TAG + ' after', code: `<i>AFTER-${TAG}</i>`, location: 'after_content' });
    const pv = await visitor(post.link);
    const iB = pv.text.indexOf('BEFORE-' + TAG), iC = pv.text.indexOf('CONTENT-MARK-' + TAG), iA = pv.text.indexOf('AFTER-' + TAG);
    check('Before Content / After Content wrap the post text', iB > -1 && iC > iB && iA > iC, `${iB} ${iC} ${iA}`);

    // ------------------------------------------------------------ 3. toggle
    log('3. ON / OFF');
    let r = await row(html.id);
    check('new snippet shows as ON in the list', r && r.on, '');
    await get(new URL(r.toggle, ADMIN).href);
    home = await visitor(HOME);
    check('turned OFF: snippet disappears', !home.text.includes('HTML-OK-' + TAG), '');
    r = await row(html.id);
    await get(new URL(r.toggle, ADMIN).href);
    home = await visitor(HOME);
    check('turned ON again: snippet returns', home.text.includes('HTML-OK-' + TAG), '');
    const inactive = await save({ name: TAG + ' inactive', code: `<i>INACTIVE-${TAG}</i>`, status: false });
    home = await visitor(HOME);
    check('saved as Inactive: not shown', !home.text.includes('INACTIVE-' + TAG), '');

    // ------------------------------------------------------------ 4. site display
    log('4. Site Display options');
    const pageA = await rest('pages', { title: TAG + ' page A', status: 'publish', content: '<p>A</p>' });
    const pageB = await rest('pages', { title: TAG + ' page B', status: 'publish', content: '<p>B</p>' });
    const cat = await rest('categories', { name: TAG + ' cat' });
    const catPost = await rest('posts', { title: TAG + ' cat post', status: 'publish', content: '<p>C</p>', categories: [cat.id] });
    const onlyPages = await save({ name: TAG + ' pages', code: `<i>ONLYPAGE-${TAG}</i>`, display: 'pages', targets: { pages: [pageA.id] } });
    const onlyPosts = await save({ name: TAG + ' posts', code: `<i>ONLYPOST-${TAG}</i>`, display: 'posts', targets: { posts: [post.id] } });
    const onlyCat = await save({ name: TAG + ' cats', code: `<i>ONLYCAT-${TAG}</i>`, display: 'categories', targets: { categories: [cat.id] } });
    const onlyType = await save({ name: TAG + ' ptype', code: `<i>ONLYTYPE-${TAG}</i>`, display: 'post_types', targets: { post_types: ['page'] } });
    const onlyHome = await save({ name: TAG + ' home', code: `<i>ONLYHOME-${TAG}</i>`, display: 'home' });
    const onlySearch = await save({ name: TAG + ' search', code: `<i>ONLYSEARCH-${TAG}</i>`, display: 'search' });
    const wide = await save({ name: TAG + ' wide ex', code: `<i>WIDEEX-${TAG}</i>`, display: 'site_wide', targets: { ex_pages: [pageB.id] } });
    const scOnly = await save({ name: TAG + ' shortcode', code: `<u>SHORTCODE-${TAG}</u>`, display: 'shortcode' });
    const [vA, vB, vPost, vCatPost, vHome, vSearch] = await Promise.all([visitor(pageA.link), visitor(pageB.link), visitor(post.link), visitor(catPost.link), visitor(HOME), visitor(HOME + '?s=' + TAG)]);
    const has = (v, t) => v.text.includes(t + '-' + TAG);
    check('Specific Pages: shows on the chosen page only', has(vA, 'ONLYPAGE') && !has(vB, 'ONLYPAGE') && !has(vHome, 'ONLYPAGE'), '');
    check('Specific Posts: shows on the chosen post only', has(vPost, 'ONLYPOST') && !has(vCatPost, 'ONLYPOST') && !has(vA, 'ONLYPOST'), '');
    check('Specific Categories: shows on a post in the category', has(vCatPost, 'ONLYCAT') && !has(vPost, 'ONLYCAT'), '');
    const catArchive = await visitor(cat.link);
    check('Specific Categories: shows on the category archive', has(catArchive, 'ONLYCAT'), '');
    check('Specific Post Types: shows on pages, not posts', has(vA, 'ONLYTYPE') && !has(vPost, 'ONLYTYPE'), '');
    check('Home Page: shows on the home page only', has(vHome, 'ONLYHOME') && !has(vA, 'ONLYHOME'), '');
    check('Search Page: shows on search results only', has(vSearch, 'ONLYSEARCH') && !has(vHome, 'ONLYSEARCH'), '');
    check('Site Wide with Exclude Pages: hidden on the excluded page', !has(vB, 'WIDEEX') && has(vA, 'WIDEEX') && has(vHome, 'WIDEEX'), '');
    check('Shortcode Only: not shown automatically', !has(vHome, 'SHORTCODE') && !has(vPost, 'SHORTCODE'), '');
    const scPost = await rest('posts', { title: TAG + ' sc post', status: 'publish', content: `[msst_snippet id="${scOnly.id}"]` });
    const vSc = await visitor(scPost.link);
    check('Shortcode renders the snippet', has(vSc, 'SHORTCODE'), '');

    // ------------------------------------------------------------ 5. devices
    log('5. Device Display');
    const desk = await save({ name: TAG + ' desk', code: `<i>DESKONLY-${TAG}</i>`, device: 'desktop' });
    const mob = await save({ name: TAG + ' mob', code: `<i>MOBONLY-${TAG}</i>`, device: 'mobile' });
    home = await visitor(HOME);
    check('Only Desktop: shown to a desktop browser', has(home, 'DESKONLY'), '');
    check('Only Mobile: hidden from a desktop browser', !has(home, 'MOBONLY'), '');

    // ------------------------------------------------------------ 6. PHP
    log('6. PHP snippets');
    const phpOk = await save({ name: TAG + ' php ok', type: 'php', location: 'everywhere', code: `add_action('wp_footer', function(){ echo '<!--${TAG}-php-->'; });` });
    check('PHP snippet saved', !!phpOk.id, phpOk.flash);
    home = await visitor(HOME);
    check('PHP snippet runs on the public site', home.text.includes(`<!--${TAG}-php-->`), '');
    const phpBad = await save({ name: TAG + ' php bad', type: 'php', location: 'everywhere', code: "add_filter('a',;" });
    check('PHP syntax error is refused (nothing saved)', !phpBad.id && phpBad.flash.length > 0, phpBad.flash || phpBad.url);
    const phpSc = await save({ name: TAG + ' php sc', type: 'php', display: 'shortcode', code: 'echo 1;' });
    check('PHP cannot be Shortcode Only', !phpSc.id && /shortcode/i.test(phpSc.flash), phpSc.flash);
    const boom = await save({ name: TAG + ' php boom', type: 'php', location: 'everywhere', code: `throw new Exception('boom-${TAG}');` });
    home = await visitor(HOME);
    check('site still loads when a snippet throws', home.status === 200, 'HTTP ' + home.status);
    r = await row(boom.id);
    check('throwing snippet turned OFF automatically, error shown in the list', r && !r.on && r.text.includes('boom-' + TAG), r ? r.text.slice(0, 160) : 'row missing');

    // ------------------------------------------------------------ 7. safe mode
    log('7. Safe Mode');
    const safeUrl = ((set.doc.querySelector('code.msst-copy') || {}).textContent || '').trim();
    check('Safe Mode link is shown on Settings', /msst_safe_mode=/.test(safeUrl), safeUrl);
    const safe = await visitor(safeUrl);
    check('Safe Mode turns every snippet off', !safe.text.includes('HTML-OK-' + TAG) && !safe.text.includes(`<!--${TAG}-php-->`), '');
    const wrong = await visitor(HOME + '?msst_safe_mode=definitely-wrong');
    check('wrong Safe Mode secret does nothing', wrong.text.includes('HTML-OK-' + TAG), '');

    // ------------------------------------------------------------ 8. tools
    log('8. Export / Import');
    const tools = await get(PAGE('scripts-manager-tools'));
    const expBody = new URLSearchParams({ action: 'msst_export', _wpnonce: nonceOf(tools.doc, 'msst_export') });
    [html.id, css.id].forEach((i) => expBody.append('ids[]', i));
    const exp = await fetch(POST, { method: 'POST', credentials: 'same-origin', body: expBody });
    const expText = await exp.text();
    let expJson = null; try { expJson = JSON.parse(expText); } catch (e) { /* ignore */ }
    check('export gives JSON with only the selected snippets', expJson && expJson.plugin === 'scripts-manager-by-mudassar' && expJson.snippets.length === 2, expText.slice(0, 100));
    const noSel = await get(POST, { method: 'POST', body: new URLSearchParams({ action: 'msst_export', _wpnonce: nonceOf(tools.doc, 'msst_export') }) });
    check('export with nothing selected asks you to select', /Select at least one/.test(noSel.text), '');
    const fd = new FormData();
    fd.append('action', 'msst_import'); fd.append('_wpnonce', nonceOf(tools.doc, 'msst_import'));
    fd.append('msst_file', new Blob([JSON.stringify({ plugin: 'scripts-manager-by-mudassar', snippets: [{ name: TAG + ' imported', type: 'html', code: `<i>IMPORTED-${TAG}</i>`, display_on: 'site_wide', location: 'footer', device: 'all', status: 1 }] })], { type: 'application/json' }), 'import.json');
    const imp = dom(await (await fetch(POST, { method: 'POST', credentials: 'same-origin', body: fd })).text());
    check('import succeeds', /Imported 1/.test((imp.querySelector('.msst-note') || {}).textContent || ''), (imp.querySelector('.msst-note') || {}).textContent);
    home = await visitor(HOME);
    check('imported snippet arrives OFF', !has(home, 'IMPORTED'), '');
    const bad = new FormData();
    bad.append('action', 'msst_import'); bad.append('_wpnonce', nonceOf(tools.doc, 'msst_import'));
    bad.append('msst_file', new Blob(['{"plugin":"other","snippets":[]}'], { type: 'application/json' }), 'bad.json');
    const badRes = dom(await (await fetch(POST, { method: 'POST', credentials: 'same-origin', body: bad })).text());
    check('wrong-format import is rejected', /not a valid/i.test((badRes.querySelector('.msst-note') || {}).textContent || ''), '');

    // ------------------------------------------------------------ 9. list features and security
    log('9. List and security');
    const lp = await get(LIST + '&s=' + encodeURIComponent(TAG));
    const cols = [...lp.doc.querySelectorAll('.msst-table thead th')].map((x) => x.textContent.replace(/[▲▼]/g, '').trim());
    check('list has ID, Status, Snippet Name, Display On, Location, Snippet Type, Devices, Shortcode', ['ID', 'Status', 'Snippet Name', 'Display On', 'Location', 'Snippet Type', 'Devices', 'Shortcode'].every((c) => cols.includes(c)), cols.join(', '));
    check('list has no status links or type filter', !lp.doc.querySelector('.msst-views') && !lp.doc.querySelector('select[name="type"]'), '');
    const sorted = await get(LIST + '&orderby=id&order=desc&s=' + encodeURIComponent(TAG));
    const ids = [...sorted.doc.querySelectorAll('.msst-table tbody tr td:nth-child(2)')].map((x) => parseInt(x.textContent, 10));
    check('sorting by ID descending works', ids.length > 2 && ids.every((v, i) => i === 0 || ids[i - 1] > v), ids.join(','));
    r = await row(css.id);
    const noNonce = r.del.replace(/&_wpnonce=[^&]+/, '');
    let res = await fetch(new URL(noNonce, ADMIN).href, { credentials: 'same-origin' });
    check('delete without a nonce is refused (CSRF)', res.status === 403, 'HTTP ' + res.status);
    res = await fetch(new URL(r.del.replace(/_wpnonce=[^&]+/, '_wpnonce=deadbeef12'), ADMIN).href, { credentials: 'same-origin' });
    check('delete with a wrong nonce is refused', res.status === 403, 'HTTP ' + res.status);
    check('snippet still exists after those attempts', !!(await row(css.id)), '');
    res = await fetch(POST, { method: 'POST', credentials: 'omit', redirect: 'follow', body: new URLSearchParams({ action: 'msst_save_snippet', msst_name: TAG + ' anon', msst_type: 'html', msst_code: 'x' }) });
    const anon = await get(LIST + '&s=' + encodeURIComponent(TAG + ' anon'));
    check('logged-out visitor cannot save a snippet (nothing created)', ([400, 401, 403].includes(res.status) || /wp-login\.php/.test(res.url)) && !anon.text.includes(TAG + ' anon'), 'HTTP ' + res.status);
    const anonPage = await fetch(LIST, { credentials: 'omit' });
    check('logged-out visitor cannot open the plugin screen', /wp-login\.php/.test(anonPage.url), anonPage.url);
    const xss = await save({ name: TAG + ' <img src=x onerror=alert(1)>', code: '<i>x</i>', status: false });
    const xssList = await get(LIST + '&s=' + encodeURIComponent(TAG));
    check('snippet names are escaped in the list (no XSS)', !/<img[^>]+onerror/i.test(xssList.text), '');
    const dbg = await visitor(HOME);
    check('no PHP warnings printed on the public site', !/(Warning|Notice|Deprecated|Fatal error)\s*:.*(scripts-manager-by-mudassar|msst_)/i.test(dbg.text), '');
  } catch (e) {
    check('script finished without a runtime error', false, (e && e.stack) || e);
  } finally {
    log('Cleaning up…');
    try {
      for (let round = 0; round < 3; round++) {
        const lp = await get(LIST + '&s=' + encodeURIComponent(TAG));
        const ids = [...lp.doc.querySelectorAll('input[name="ids[]"]')].map((i) => i.value);
        if (!ids.length) break;
        const body = new URLSearchParams({ action: 'msst_bulk', _wpnonce: nonceOf(lp.doc, 'msst_bulk'), bulk_action: 'delete' });
        ids.forEach((i) => body.append('ids[]', i));
        await fetch(POST, { method: 'POST', credentials: 'same-origin', body });
      }
      for (const [base, id] of posts) {
        await fetch(HOME + `wp-json/wp/v2/${base}/${id}?force=true`, { method: 'DELETE', credentials: 'same-origin', headers: { 'X-WP-Nonce': restNonce } });
      }
      const left = await get(LIST + '&s=' + encodeURIComponent(TAG));
      check('cleanup: all test snippets deleted', !left.doc.querySelector('input[name="ids[]"]'), '');
    } catch (e) {
      console.warn('Cleanup problem – delete "' + TAG + '" items manually.', e);
    }
    const failed = results.filter((x) => x.result === 'FAIL');
    console.log('%c==== ' + (results.length - failed.length) + '/' + results.length + ' checks passed ====', 'font-size:14px;font-weight:bold');
    console.table(results);
    if (failed.length) console.log('%cFAILED:\n' + failed.map((f) => '- ' + f.check + '  ' + f.detail).join('\n'), 'color:#d70015');
    window.msstTestResults = results;
  }
})();
