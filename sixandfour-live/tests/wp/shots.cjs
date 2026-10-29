const { chromium } = require('playwright');
(async()=>{const b=await chromium.launch();const c=await b.newContext({viewport:{width:1100,height:900}});const p=await c.newPage();
await p.goto('http://localhost:8099/wp-login.php');await p.fill('#user_login','admin');await p.fill('#user_pass','Admin#Pass12345');await p.click('#wp-submit');await p.waitForLoadState('load');
for (const t of ['overview','engage','setup']) {await p.goto('http://localhost:8099/wp-admin/admin.php?page=sixandfour-live&tab='+t);await p.screenshot({path:'/tmp/sf-mobile-out/adm-'+t+'.png',fullPage:false});}
await b.close();})();
