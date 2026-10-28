// Renders each SVG in ./svg to a PNG in ./png with Chromium. Light-version icons keep a transparent background.
const { chromium } = require('playwright');
const fs = require('fs'); const path = require('path');
(async () => {
  const b = await chromium.launch(); const dir = path.join(__dirname, 'svg'); const out = path.join(__dirname, 'png'); fs.mkdirSync(out, { recursive: true });
  const jobs = {
    'cricket-sport-icon-light': [1024, 1024], 'cricket-sport-icon-dark': [1024, 1024],
    'cricket-sport-profile-light': [1024, 1024], 'cricket-sport-profile-dark': [1024, 1024],
    'cricket-sport-horizontal-light': [2000, null], 'cricket-sport-horizontal-dark': [2000, null],
    'cricket-sport-stacked-light': [1200, null], 'cricket-sport-stacked-dark': [1200, null],
    'cricket-sport-cover': [1640, 624],
  };
  for (const [name, [w, h]] of Object.entries(jobs)) {
    const svg = fs.readFileSync(path.join(dir, name + '.svg'), 'utf8');
    const m = svg.match(/viewBox="0 0 (\d+) (\d+)"/); const ratio = +m[2] / +m[1];
    const H = h || Math.round(w * ratio);
    const p = await b.newPage({ viewport: { width: w, height: H } });
    await p.setContent(`<body style="margin:0;background:transparent"><img src="data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}" style="display:block;width:${w}px;height:${H}px"></body>`);
    await p.screenshot({ path: path.join(out, name + '.png'), omitBackground: true });
    await p.close();
  }
  await b.close(); console.log('rendered');
})();
