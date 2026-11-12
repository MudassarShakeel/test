"""Makes an original 22-second sports-style backing track (no samples, nothing copyrighted).
124 BPM, A minor: kick, clap, hats, bass, pad chords and a plucked arpeggio. Run: python3 make_music.py
"""
import numpy as np, wave, sys

SR = 44100
BPM = 124
SEC = 22.0
N = int(SR * SEC)
beat = 60 / BPM
rng = np.random.default_rng(7)
mix_l = np.zeros(N)
mix_r = np.zeros(N)
duck = np.ones(N)   # sidechain: the kick pushes the other parts down for a moment


def add(buf, start, sig, gain=1.0):
    i = int(start * SR)
    if i >= N:
        return
    seg = sig[: N - i] * gain
    buf[i : i + len(seg)] += seg


def env(n, a=0.002, d=0.1, s=0.0, r=0.05):
    t = np.arange(n) / SR
    e = np.where(t < a, t / a, np.exp(-(t - a) / d))
    return np.maximum(e, s) * np.clip((n / SR - t) / r, 0, 1)


def kick():
    n = int(SR * 0.28)
    t = np.arange(n) / SR
    f = 45 + 95 * np.exp(-t * 28)
    ph = 2 * np.pi * np.cumsum(f) / SR
    return np.sin(ph) * np.exp(-t * 11) * 1.15


def clap():
    n = int(SR * 0.22)
    t = np.arange(n) / SR
    noise = rng.standard_normal(n)
    # high-pass by differencing, three quick bursts then a tail
    noise = np.diff(noise, prepend=0)
    e = np.exp(-t * 30) * (1 + 0.7 * (np.sin(t * 2 * np.pi * 110) > 0.3))
    return noise * e * 0.35


def hat(open_=False):
    n = int(SR * (0.16 if open_ else 0.045))
    t = np.arange(n) / SR
    noise = np.diff(rng.standard_normal(n), n=2, prepend=[0, 0])
    return noise * np.exp(-t * (22 if open_ else 90)) * 0.18


def saw(f, n):
    t = np.arange(n) / SR
    return 2 * ((t * f) % 1) - 1


def lowpass(x, a):
    y = np.zeros_like(x)
    acc = 0.0
    for i, v in enumerate(x):
        acc += a * (v - acc)
        y[i] = acc
    return y


def midi(m):
    return 440 * 2 ** ((m - 69) / 12)


bars = int(SEC / (beat * 4)) + 1
# Chord roots per bar: Am F C G, repeated.
prog = [(57, [57, 60, 64]), (53, [53, 57, 60]), (48, [52, 55, 60]), (55, [55, 59, 62])]
pent = [69, 72, 74, 76, 79, 81]   # A minor pentatonic, up an octave

for bar in range(bars):
    t0 = bar * beat * 4
    root, chord = prog[bar % 4]
    # Drums. The first bar is lighter so the intro breathes.
    for b in range(4):
        tb = t0 + b * beat
        add(mix_l, tb, kick(), 0.9)
        add(mix_r, tb, kick(), 0.9)
        i0 = int(tb * SR)
        if i0 < N:
            duck[i0 : min(N, i0 + int(SR * 0.22))] *= np.linspace(0.45, 1.0, min(N, i0 + int(SR * 0.22)) - i0)
        if b in (1, 3) and bar >= 1:
            c = clap()
            add(mix_l, tb, c, 0.8)
            add(mix_r, tb + 0.004, c, 0.8)
    if bar >= 1:
        for e in range(8):
            te = t0 + e * beat / 2
            h = hat(open_=(e % 2 == 1))
            add(mix_l, te, h, 0.7 if e % 2 else 1.0)
            add(mix_r, te + 0.002, h, 0.7 if e % 2 else 1.0)
    # Bass: eighth notes, octave jump on the off-beats.
    for e in range(8):
        te = t0 + e * beat / 2
        f = midi(root - 12 + (12 if e % 4 == 3 else 0))
        n = int(SR * beat / 2 * 0.9)
        note = lowpass(saw(f, n), 0.09) * env(n, 0.004, 0.25, 0.0, 0.03) * 0.55
        if te * SR >= N:
            continue
        dk = duck[int(te * SR)]
        add(mix_l, te, note * dk, 1.0)
        add(mix_r, te, note * dk, 1.0)
    # Pad: the chord, soft and wide.
    n = int(SR * beat * 4)
    pad = np.zeros(n)
    for k, m in enumerate(chord):
        pad += lowpass(saw(midi(m + 12) * (1 + 0.003 * (k - 1)), n), 0.03) + 0.5 * np.sin(2 * np.pi * midi(m + 12) * np.arange(n) / SR)
    pad *= env(n, 0.5, 5.0, 0.0, 0.4) * 0.05
    sl = int(t0 * SR)
    seg = pad[: max(0, N - sl)]
    pd = duck[sl : sl + len(seg)]
    mix_l[sl : sl + len(seg)] += seg * pd
    mix_r[sl : sl + len(seg)] += np.roll(seg, 180) * pd
    # Arpeggio from bar 2: sixteenth plucks.
    if bar >= 2:
        pat = [0, 2, 4, 2, 5, 3, 4, 1, 0, 2, 4, 5, 3, 2, 1, 4]
        for s16 in range(16):
            ts = t0 + s16 * beat / 4
            f = midi(pent[pat[s16] % len(pent)])
            n = int(SR * 0.16)
            t = np.arange(n) / SR
            pluck = (np.sin(2 * np.pi * f * t) + 0.35 * np.sin(2 * np.pi * 2 * f * t)) * np.exp(-t * 22) * 0.09
            g = 0.7 + 0.3 * (s16 % 4 == 0)
            add(mix_l, ts, pluck, g * (0.6 + 0.4 * (s16 % 2)))
            add(mix_r, ts + 0.006, pluck, g * (1.0 - 0.4 * (s16 % 2)))

out = np.stack([mix_l, mix_r], axis=1)
# Fade in over half a second and out over the last two seconds.
tt = np.arange(N) / SR
fade = np.clip(tt / 0.5, 0, 1) * np.clip((SEC - tt) / 2.0, 0, 1)
out *= fade[:, None]
out = np.tanh(out * 1.2)           # gentle glue
out *= 0.7 / np.max(np.abs(out))
pcm = (out * 32767).astype(np.int16)
with wave.open(sys.argv[1] if len(sys.argv) > 1 else 'music.wav', 'wb') as w:
    w.setnchannels(2)
    w.setsampwidth(2)
    w.setframerate(SR)
    w.writeframes(pcm.tobytes())
print('ok')
