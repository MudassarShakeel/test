// Cricket Sport video render service.
// POST /render  (Authorization: Bearer KEY)  with the match JSON  ->  { id }
// GET  /status/:id                           ->  { status: queued|rendering|done|failed, url?, error? }
// GET  /videos/:id.mp4                       ->  the finished video (Facebook fetches this)
// GET  /health                               ->  { ok: true }
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { bundle } from '@remotion/bundler';
import { renderMedia, selectComposition } from '@remotion/renderer';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.join(HERE, '..');
const PORT = Number(process.env.PORT || 8200);
const KEY = process.env.API_KEY || '';
const PUBLIC_URL = (process.env.PUBLIC_URL || `http://127.0.0.1:${PORT}`).replace(/\/$/, '');
const BROWSER = process.env.BROWSER_EXECUTABLE || undefined;
const EL_KEY = process.env.ELEVENLABS_API_KEY || '';
const EL_VOICE = process.env.ELEVENLABS_VOICE_ID || 'kNgdRSecKh7LrZA0cR8g';
const OUT = path.join(ROOT, 'out', 'jobs');
const MAX_AGE_MS = 7 * 24 * 3600 * 1000;
fs.mkdirSync(OUT, { recursive: true });

if (!KEY) {
	console.error('Set API_KEY first. The plugin sends it as a Bearer token.');
	process.exit(1);
}

const jobs = new Map();   // id -> { status, error, created }
let queue = Promise.resolve();
let serveUrl = null;

const send = (res, code, obj) => {
	res.writeHead(code, { 'Content-Type': 'application/json' });
	res.end(JSON.stringify(obj));
};

const num = (v, d = 0) => (Number.isFinite(Number(v)) ? Number(v) : d);
const str = (v, max = 120) => String(v ?? '').slice(0, max);

/** Keep only the fields the video uses, and cap their length. */
function clean(b) {
	const team = (t) => ({ name: str(t?.name, 40), flag: t?.flag && /^flags\/[a-z]{3}\.png$/.test(t.flag) ? t.flag : null, initials: str(t?.initials, 4), score: str(t?.score, 20), overs: str(t?.overs, 12), won: Boolean(t?.won) });
	const star = (s) => (s && s.name ? { label: str(s.label, 30), name: str(s.name, 40), stat: str(s.stat, 12), small: str(s.small, 24), detail: str(s.detail, 60) } : null);
	if (!Array.isArray(b.teams) || b.teams.length !== 2) throw new Error('teams must have two entries');
	return {
		tournament: str(b.tournament, 60), meta: str(b.meta, 80), winner: str(b.winner, 40), loser: str(b.loser, 40), margin: str(b.margin, 40), toSpare: str(b.toSpare, 40),
		teams: b.teams.map(team), bat: star(b.bat), bowl: star(b.bowl), link: str(b.link || 'facebook.com/cricketsportinfo', 60),
		narration: str(b.narration, 900), voice: b.voice !== false, music: b.music !== false,
	};
}

function ffprobeDuration(file) {
	const r = spawnSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file], { encoding: 'utf8' });
	const d = parseFloat((r.stdout || '').trim());
	return Number.isFinite(d) ? d : 0;
}

/** Make the voiceover with ElevenLabs. Returns { file, seconds } or null (no key, or it failed). */
async function makeVoice(id, text) {
	if (!EL_KEY || !text) return null;
	try {
		const r = await fetch(`https://api.elevenlabs.io/v1/text-to-speech/${EL_VOICE}?output_format=mp3_44100_128`, {
			method: 'POST',
			headers: { 'xi-api-key': EL_KEY, 'Content-Type': 'application/json' },
			body: JSON.stringify({ text, model_id: 'eleven_multilingual_v2' }),
		});
		if (!r.ok) throw new Error('ElevenLabs answered ' + r.status);
		const file = path.join(OUT, `${id}.voice.mp3`);
		fs.writeFileSync(file, Buffer.from(await r.arrayBuffer()));
		return { file, seconds: ffprobeDuration(file) };
	} catch (e) {
		console.error('voice failed:', e.message);
		return null;
	}
}

function narrationFor(m) {
	const parts = [`${m.winner} beat ${m.loser} by ${m.margin}.`];
	const [a, b] = m.teams;
	parts.push(`${a.name} scored ${a.score}. ${b.name} replied with ${b.score}.`);
	if (m.bat) parts.push(`${m.bat.name} top scored with ${m.bat.stat}.`);
	if (m.bowl) parts.push(`${m.bowl.name} took ${m.bowl.stat}.`);
	parts.push('Follow Cricket Sport for every result.');
	return parts.join(' ');
}

async function render(id, props) {
	const job = jobs.get(id);
	job.status = 'rendering';
	try {
		const m = clean(props);
		let end = 21.5;
		let audio = null;
		if (m.voice) {
			const v = await makeVoice(id, m.narration || narrationFor(m));
			if (v) {
				audio = `${PUBLIC_URL}/files/${id}.voice.mp3`;
				end = Math.max(16, v.seconds + 2.2);
			}
		}
		if (!m.bat && !m.bowl) end = Math.min(end, audio ? end : 17);
		const k = end / 21.5;
		const input = {
			...m,
			audio,
			music: m.music ? 'music.mp3' : null,
			musicVolume: 0.5,
			scenes: { score: +(4 * k).toFixed(2), stars: +(11 * k).toFixed(2), outro: +((m.bat || m.bowl ? 17 : 11) * k).toFixed(2), end: +end.toFixed(2) },
		};
		const composition = await selectComposition({ serveUrl, id: 'ResultVideo', inputProps: input, browserExecutable: BROWSER, logLevel: 'error' });
		await renderMedia({ composition, serveUrl, codec: 'h264', outputLocation: path.join(OUT, `${id}.mp4`), inputProps: input, browserExecutable: BROWSER, concurrency: Number(process.env.CONCURRENCY || 2), logLevel: 'error' });
		job.status = 'done';
	} catch (e) {
		job.status = 'failed';
		job.error = String(e.message || e).slice(0, 300);
		console.error('render failed', id, job.error);
	}
}

function cleanup() {
	const now = Date.now();
	for (const f of fs.readdirSync(OUT)) {
		const p = path.join(OUT, f);
		if (now - fs.statSync(p).mtimeMs > MAX_AGE_MS) fs.rmSync(p, { force: true });
	}
}

const same = (a, b) => a.length === b.length && crypto.timingSafeEqual(Buffer.from(a), Buffer.from(b));

const server = http.createServer(async (req, res) => {
	const url = new URL(req.url, 'http://x');
	try {
		if (url.pathname === '/health') return send(res, 200, { ok: true, ready: Boolean(serveUrl) });
		if (req.method === 'POST' && url.pathname === '/render') {
			const auth = (req.headers.authorization || '').replace(/^Bearer\s+/i, '');
			if (!same(auth, KEY)) return send(res, 401, { error: 'Wrong key.' });
			if (!serveUrl) return send(res, 503, { error: 'Still starting up. Try again in a minute.' });
			const chunks = [];
			let size = 0;
			for await (const c of req) {
				size += c.length;
				if (size > 200_000) return send(res, 413, { error: 'Too large.' });
				chunks.push(c);
			}
			let body;
			try { body = JSON.parse(Buffer.concat(chunks).toString('utf8')); clean(body); } catch (e) { return send(res, 400, { error: 'Bad match data: ' + e.message }); }
			const id = crypto.randomBytes(8).toString('hex');
			jobs.set(id, { status: 'queued', created: Date.now() });
			queue = queue.then(() => render(id, body));
			return send(res, 202, { id, status: 'queued' });
		}
		const st = url.pathname.match(/^\/status\/([a-f0-9]{16})$/);
		if (req.method === 'GET' && st) {
			const j = jobs.get(st[1]);
			if (!j) return send(res, 404, { status: 'failed', error: 'Unknown job.' });
			return send(res, 200, { status: j.status, error: j.error, url: j.status === 'done' ? `${PUBLIC_URL}/videos/${st[1]}.mp4` : undefined });
		}
		const vid = url.pathname.match(/^\/(videos|files)\/([a-f0-9]{16})(\.voice)?\.(mp4|mp3)$/);
		if (req.method === 'GET' && vid) {
			const file = path.join(OUT, `${vid[2]}${vid[3] || ''}.${vid[4]}`);
			if (!fs.existsSync(file)) return send(res, 404, { error: 'Not found.' });
			res.writeHead(200, { 'Content-Type': vid[4] === 'mp4' ? 'video/mp4' : 'audio/mpeg', 'Content-Length': fs.statSync(file).size, 'Cache-Control': 'public, max-age=86400' });
			return fs.createReadStream(file).pipe(res);
		}
		send(res, 404, { error: 'Not found.' });
	} catch (e) {
		send(res, 500, { error: 'Server error.' });
	}
});

console.log('Bundling the video...');
serveUrl = await bundle({ entryPoint: path.join(ROOT, 'src', 'index.ts'), publicDir: path.join(ROOT, 'public') });
cleanup();
setInterval(cleanup, 6 * 3600 * 1000);
server.listen(PORT, () => console.log(`Video service ready on ${PORT} (${PUBLIC_URL})`));
