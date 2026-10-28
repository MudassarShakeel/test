const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1366, height: 800 }, baseURL: 'http://localhost:8099' });
  const p = await ctx.newPage();
  await p.goto('/wp-login.php'); await p.fill('#user_login','admin'); await p.fill('#user_pass','Admin#Pass12345'); await p.click('#wp-submit'); await p.waitForLoadState('load');
  await p.goto('/'); await p.waitForSelector('.sfc-tabs');
  for (const t of ['home','queue','post','settings']) { await p.click('[data-tab='+t+']'); await p.waitForTimeout(300); await p.screenshot({ path: '/tmp/sf-mobile-out/desk-'+t+'.png' }); }
  console.log(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await b.close();
})();
