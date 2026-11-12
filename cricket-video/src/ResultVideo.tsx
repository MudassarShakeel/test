import React from 'react';
import { AbsoluteFill, Audio, Img, Sequence, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';

export type Team = { name: string; flag: string; score: string; overs: string; won?: boolean };
export type Star = { label: string; name: string; stat: string; small: string; detail: string };
export type Match = {
	tournament: string;
	meta: string;
	winner: string;
	loser: string;
	margin: string;
	teams: Team[];
	toSpare: string;
	bat: Star;
	bowl: Star;
	link: string;
	audio: string | null;
	music?: string | null;
	musicVolume?: number;
	scenes: { score: number; stars: number; outro: number; end: number };
};

const NAVY = '#0A1B44';
const NAVY2 = '#12306F';
const CYAN = '#00E5FF';
const font = (w: number) => `Inter, system-ui, sans-serif`;

const fontCss = `
@font-face { font-family: Inter; font-weight: 800; src: url(${staticFile('fonts/Inter-ExtraBold.otf')}); }
@font-face { font-family: Inter; font-weight: 700; src: url(${staticFile('fonts/Inter-Bold.otf')}); }
@font-face { font-family: Inter; font-weight: 600; src: url(${staticFile('fonts/Inter-SemiBold.otf')}); }
`;

/** Eases a value in once, from a start frame. */
const useIn = (start: number, damping = 18) => {
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	return spring({ frame: frame - start, fps, config: { damping, stiffness: 110 } });
};

const Background: React.FC = () => {
	const frame = useCurrentFrame();
	const drift = interpolate(frame, [0, 700], [0, -120]);
	return (
		<AbsoluteFill style={{ background: `radial-gradient(900px 900px at 50% 38%, ${NAVY2} 0%, ${NAVY} 70%)` }}>
			<svg width="1080" height="1920" style={{ position: 'absolute', inset: 0, opacity: 0.5 }}>
				{Array.from({ length: 10 }).map((_, i) => (
					<line key={'v' + i} x1={i * 120} y1="0" x2={i * 120} y2="1920" stroke="#16295C" strokeWidth="1" />
				))}
				{Array.from({ length: 17 }).map((_, i) => (
					<line key={'h' + i} x1="0" y1={i * 120} x2="1080" y2={i * 120} stroke="#16295C" strokeWidth="1" />
				))}
			</svg>
			<svg width="1080" height="1920" style={{ position: 'absolute', inset: 0 }}>
				<path d={`M -60 ${1500 + drift * 0.3} Q 520 ${1180 + drift * 0.3} 1160 ${420 + drift * 0.2}`} stroke={CYAN} strokeOpacity="0.16" strokeWidth="34" strokeLinecap="round" fill="none" />
			</svg>
		</AbsoluteFill>
	);
};

const Header: React.FC<{ pill: string; link: string }> = ({ pill, link }) => {
	const p = useIn(0);
	return (
		<div style={{ position: 'absolute', left: 56, right: 56, top: 70, height: 150, borderRadius: 44, background: '#0E2557', border: '2px solid #1B3A82', display: 'flex', alignItems: 'center', padding: '0 34px', gap: 26, transform: `translateY(${(1 - p) * -60}px)`, opacity: p }}>
			<div style={{ width: 96, height: 96, borderRadius: 26, background: '#fff', display: 'grid', placeItems: 'center' }}>
				<Img src={staticFile('img/logo.png')} style={{ width: 80, height: 80, filter: 'invert(1) hue-rotate(180deg) saturate(2.2) brightness(0.55)' }} />
			</div>
			<div style={{ flex: 1 }}>
				<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 46, color: '#fff', letterSpacing: -0.5 }}>Cricket Sport</div>
				<div style={{ fontFamily: 'Inter', fontWeight: 600, fontSize: 27, color: '#9EEFFF' }}>{link}</div>
			</div>
			<div style={{ background: CYAN, color: NAVY, fontFamily: 'Inter', fontWeight: 800, fontSize: 30, padding: '14px 30px', borderRadius: 999 }}>{pill}</div>
		</div>
	);
};

const Headline: React.FC<{ m: Match }> = ({ m }) => {
	const words = [m.winner, 'beat', m.loser, 'by', m.margin];
	const frame = useCurrentFrame();
	const { fps } = useVideoConfig();
	const sub = useIn(24);
	return (
		<div style={{ position: 'absolute', left: 70, right: 70, top: 760 }}>
			<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 40, color: '#8FA6DE', marginBottom: 26, opacity: sub, transform: `translateY(${(1 - sub) * 20}px)` }}>
				{m.tournament} <span style={{ color: '#5C73B4' }}>|</span> {m.meta.split(',')[0]}
			</div>
			<div style={{ display: 'flex', flexWrap: 'wrap', gap: '0 26px', lineHeight: 1.04 }}>
				{words.map((w, i) => {
					const s = spring({ frame: frame - 8 - i * 6, fps, config: { damping: 16, stiffness: 130 } });
					const hot = i === 0 || i === 4;
					return (
						<span key={i} style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 150, letterSpacing: -5, color: hot ? CYAN : '#fff', opacity: s, transform: `translateY(${(1 - s) * 50}px)`, display: 'inline-block' }}>
							{w}
						</span>
					);
				})}
			</div>
		</div>
	);
};

const TeamRow: React.FC<{ t: Team; delay: number; y: number }> = ({ t, delay, y }) => {
	const p = useIn(delay);
	return (
		<div style={{ position: 'absolute', left: 60, right: 60, top: y, height: 240, borderRadius: 44, background: t.won ? '#16398A' : '#102A66', border: `3px solid ${t.won ? CYAN : '#1F3F8F'}`, display: 'flex', alignItems: 'center', padding: '0 44px', transform: `translateX(${(1 - p) * -900}px)`, opacity: p }}>
			<Img src={staticFile(t.flag)} style={{ width: 150, height: 112, borderRadius: 16, objectFit: 'cover' }} />
			<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 76, color: '#fff', marginLeft: 40, flex: 1, letterSpacing: -1.5 }}>{t.name}</div>
			<div style={{ textAlign: 'right' }}>
				<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 112, color: t.won ? CYAN : '#fff', letterSpacing: -3, lineHeight: 1 }}>{t.score}</div>
				<div style={{ fontFamily: 'Inter', fontWeight: 600, fontSize: 32, color: '#8FA6DE' }}>{t.overs}</div>
			</div>
		</div>
	);
};

const ResultBar: React.FC<{ m: Match; delay: number; y: number }> = ({ m, delay, y }) => {
	const p = useIn(delay);
	return (
		<div style={{ position: 'absolute', left: 60, right: 60, top: y, borderRadius: 40, background: CYAN, padding: '44px 50px', transform: `scale(${0.85 + 0.15 * p})`, opacity: p }}>
			<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 70, color: NAVY, letterSpacing: -2, lineHeight: 1.08 }}>{m.winner} won by {m.margin}</div>
			<div style={{ fontFamily: 'Inter', fontWeight: 700, fontSize: 40, color: '#0A3A66', marginTop: 10 }}>{m.toSpare}</div>
		</div>
	);
};

const StarCard: React.FC<{ s: Star; delay: number; y: number }> = ({ s, delay, y }) => {
	const p = useIn(delay);
	const initials = s.name.split(' ').map((x) => x[0]).join('');
	return (
		<div style={{ position: 'absolute', left: 60, right: 60, top: y, height: 520, borderRadius: 48, background: '#102A66', border: '3px solid #1F3F8F', padding: '44px 50px', transform: `translateY(${(1 - p) * 160}px)`, opacity: p }}>
			<div style={{ display: 'flex', alignItems: 'center', gap: 34 }}>
				<div style={{ width: 150, height: 150, borderRadius: 38, background: CYAN, color: NAVY, fontFamily: 'Inter', fontWeight: 800, fontSize: 70, display: 'grid', placeItems: 'center' }}>{initials}</div>
				<div>
					<div style={{ fontFamily: 'Inter', fontWeight: 700, fontSize: 38, color: '#8FA6DE' }}>{s.label}</div>
					<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 70, color: '#fff', letterSpacing: -2 }}>{s.name}</div>
				</div>
			</div>
			<div style={{ display: 'flex', alignItems: 'baseline', gap: 26, marginTop: 34 }}>
				<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 210, color: CYAN, letterSpacing: -8, lineHeight: 1 }}>{s.stat}</div>
				<div style={{ fontFamily: 'Inter', fontWeight: 700, fontSize: 52, color: '#C8D6FF' }}>{s.small}</div>
			</div>
			<div style={{ fontFamily: 'Inter', fontWeight: 600, fontSize: 40, color: '#8FA6DE', marginTop: 14 }}>{s.detail}</div>
		</div>
	);
};

const Outro: React.FC<{ m: Match }> = ({ m }) => {
	const p = useIn(0);
	const q = useIn(10);
	const r = useIn(22);
	return (
		<AbsoluteFill style={{ alignItems: 'center', justifyContent: 'center' }}>
			<Img src={staticFile('img/avatar.png')} style={{ width: 360, height: 360, borderRadius: 180, transform: `scale(${p})`, boxShadow: '0 0 0 10px #00E5FF55' }} />
			<div style={{ fontFamily: 'Inter', fontWeight: 800, fontSize: 112, color: '#fff', marginTop: 60, letterSpacing: -3, opacity: q, transform: `translateY(${(1 - q) * 40}px)` }}>Cricket Sport</div>
			<div style={{ fontFamily: 'Inter', fontWeight: 700, fontSize: 50, color: '#C8D6FF', marginTop: 12, opacity: q }}>Every result, as it happens</div>
			<div style={{ marginTop: 54, background: CYAN, color: NAVY, fontFamily: 'Inter', fontWeight: 800, fontSize: 50, padding: '26px 56px', borderRadius: 999, opacity: r, transform: `scale(${0.9 + 0.1 * r})` }}>Follow the Page</div>
			<div style={{ fontFamily: 'Inter', fontWeight: 600, fontSize: 40, color: '#9EEFFF', marginTop: 34, opacity: r }}>{m.link}</div>
		</AbsoluteFill>
	);
};

export const ResultVideo: React.FC<Match> = (m) => {
	const { fps } = useVideoConfig();
	const f = (s: number) => Math.round(s * fps);
	const end = f(m.scenes.end);
	const fade = (from: number, to: number) => {
		// Fades a scene out near its end.
		return { from, dur: to - from };
	};
	const A = fade(0, f(m.scenes.score) + 12);
	const B = fade(f(m.scenes.score), f(m.scenes.stars) + 12);
	const C = fade(f(m.scenes.stars), f(m.scenes.outro) + 12);
	const D = fade(f(m.scenes.outro), end);
	return (
		<AbsoluteFill style={{ background: NAVY }}>
			<style>{fontCss}</style>
			<Background />
			{m.music ? (
				<Audio
					src={staticFile(m.music)}
					// Quiet under a voice, a little louder when there is none, and a fade-out over the last two seconds.
					volume={(frame) => (m.musicVolume ?? 0.3) * (m.audio ? 0.45 : 1) * interpolate(frame, [end - 2 * fps, end], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })}
				/>
			) : null}
			{m.audio ? <Audio src={staticFile(m.audio)} /> : null}
			<Sequence from={A.from} durationInFrames={A.dur}>
				<Header pill="MATCH RESULT" link={m.link} />
				<Headline m={m} />
			</Sequence>
			<Sequence from={B.from} durationInFrames={B.dur}>
				<Header pill="SCORES" link={m.link} />
				<TeamRow t={m.teams[0]} delay={0} y={560} />
				<TeamRow t={m.teams[1]} delay={Math.round(fps * 3.2)} y={830} />
				<ResultBar m={m} delay={Math.round(fps * 5.6)} y={1150} />
			</Sequence>
			<Sequence from={C.from} durationInFrames={C.dur}>
				<Header pill="STAND-OUTS" link={m.link} />
				<StarCard s={m.bat} delay={0} y={420} />
				<StarCard s={m.bowl} delay={Math.round(fps * 3)} y={1010} />
			</Sequence>
			<Sequence from={D.from} durationInFrames={D.dur}>
				<Outro m={m} />
			</Sequence>
		</AbsoluteFill>
	);
};
