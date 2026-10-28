// A mock of the cricket API and the Facebook Graph API for tests. Run: node mock-servers.js
// GET /__state returns what the mock received. POST /__world sets the matches the cricket API returns.
const http = require('http');
const state = { matches: [], scorecards: {}, hits: 0, fb: [], fbEdits: [], fbFail: false };
function send(res, code, obj) { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(obj)); }
http.createServer((req, res) => {
  const chunks = [];
  req.on('data', c => chunks.push(c));
  req.on('end', () => {
    const body = Buffer.concat(chunks);
    const url = new URL(req.url, 'http://x');
    const p = url.pathname;
    if (p === '/__state') return send(res, 200, state);
    if (p === '/__world') { Object.assign(state, JSON.parse(body.toString() || '{}')); return send(res, 200, { ok: true }); }
    if (p.startsWith('/cricket/')) {
      state.hits++;
      const info = { hitsToday: state.hits, hitsLimit: 100 };
      if (p.endsWith('/currentMatches')) return send(res, 200, { status: 'success', data: state.matches, info });
      if (p.endsWith('/match_scorecard')) return send(res, 200, { status: 'success', data: state.scorecards[url.searchParams.get('id')] || {}, info });
      return send(res, 200, { status: 'success', data: [], info });
    }
    if (p.startsWith('/graph/')) {
      if (state.fbFail) return send(res, 400, { error: { message: 'Error validating access token: Session has expired', code: 190 } });
      if (req.method === 'GET') return send(res, 200, { name: 'Six & Four Live', username: 'sixandfourlive', fan_count: 9800 });
      if (p.endsWith('/photos')) {
        const text = body.toString('latin1');
        state.fb.push({ kind: 'photo', bytes: body.length, png: text.includes('\x89PNG'), jpeg: text.includes('\xff\xd8\xff'), php: text.includes('<?php'), caption: (text.match(/name="caption"\r\n\r\n([\s\S]*?)\r\n--/) || [])[1] || '', tokenInBody: text.includes('name="access_token"') });
        return send(res, 200, { id: 'P' + state.fb.length, post_id: '1_' + state.fb.length });
      }
      if (p.endsWith('/feed')) { state.fb.push({ kind: 'feed', body: body.toString() }); return send(res, 200, { id: '1_feed' + state.fb.length }); }
      state.fbEdits.push({ path: p, body: body.toString() });
      return send(res, 200, { success: true });
    }
    send(res, 404, {});
  });
}).listen(8100, '127.0.0.1', () => console.log('mock servers on 8100'));
