"""Facebook cover (1640 x 624): logo, name, Page link, ten team flags and stylised player silhouettes.
Silhouettes are drawn from simple joint positions, so they are original artwork and show no real person.
"""
import base64, os
from build import icon, text_path, svg, NAVY, CYAN, WHITE, HERE

W, H = 1640, 624
FLAGS = os.path.join(HERE, '..', 'sixandfour-live', 'assets', 'flags')
TEAMS = ['ind', 'aus', 'eng', 'rsa', 'nzl', 'pak', 'sri', 'ban', 'afg', 'WI']

import math

# Poses are joint positions in a box about 100 units tall. Limbs are tapered: (from, to, radius at start, radius at end).
POSES = {
    'drive': dict(
        parts=[
            ((52, 22), (46, 48), 6.8, 5.6),            # torso
            ((46, 48), (58, 67), 5.6, 4.4), ((58, 67), (61, 89), 4.6, 3.4), ((61, 89), (67, 93), 3.2, 2.6),   # front leg and pad
            ((46, 48), (37, 69), 5.4, 4.2), ((37, 69), (29, 89), 4.4, 3.2), ((29, 89), (26, 94), 3.0, 2.4),   # back leg
            ((53, 22), (63, 18), 4.2, 3.2), ((63, 18), (71, 10), 3.2, 3.0),                                    # front arm
            ((51, 23), (59, 27), 4.0, 3.2), ((59, 27), (69, 12), 3.2, 3.0),                                    # back arm
        ],
        head=(53, 11, 5.4), helmet=True, bat=((71, 9), (90, -17), 2.6, 4.6), ball=None),
    'bowler': dict(
        parts=[
            ((50, 23), (48, 49), 6.8, 5.6),
            ((48, 49), (61, 62), 5.6, 4.6), ((61, 62), (66, 87), 4.6, 3.2), ((66, 87), (72, 91), 3.0, 2.4),
            ((48, 49), (35, 60), 5.4, 4.2), ((35, 60), (20, 66), 4.4, 3.2), ((20, 66), (14, 62), 3.0, 2.4),
            ((51, 23), (57, 8), 4.2, 3.2), ((57, 8), (58, -9), 3.2, 2.8),
            ((49, 24), (38, 25), 4.0, 3.2), ((38, 25), (29, 35), 3.2, 2.8),
        ],
        head=(51, 13, 5.4), helmet=False, bat=None, ball=(58, -12, 2.8)),
    'stance': dict(
        parts=[
            ((47, 22), (50, 49), 6.8, 5.8),
            ((50, 49), (58, 70), 5.6, 4.4), ((58, 70), (57, 91), 4.6, 3.4), ((57, 91), (62, 95), 3.2, 2.6),
            ((50, 49), (41, 71), 5.4, 4.2), ((41, 71), (39, 91), 4.4, 3.2), ((39, 91), (44, 95), 3.0, 2.4),
            ((48, 24), (57, 36), 4.2, 3.2), ((57, 36), (51, 52), 3.2, 3.0),
            ((48, 25), (56, 39), 4.0, 3.2), ((56, 39), (50, 55), 3.2, 3.0),
        ],
        head=(46, 12, 5.4), helmet=True, bat=((50, 54), (45, 95), 2.4, 4.4), ball=None),
    'catch': dict(
        parts=[
            ((46, 29), (52, 57), 6.8, 5.6),
            ((52, 57), (67, 71), 5.6, 4.4), ((67, 71), (83, 78), 4.4, 3.2),
            ((52, 57), (44, 76), 5.4, 4.2), ((44, 76), (50, 94), 4.2, 3.0),
            ((47, 31), (41, 15), 4.2, 3.2), ((41, 15), (37, -1), 3.2, 3.0),
            ((49, 31), (47, 13), 4.0, 3.2), ((47, 13), (45, -3), 3.2, 3.0),
        ],
        head=(48, 21, 5.4), helmet=False, bat=None, ball=(41, -6, 2.8)),
    'pull': dict(
        parts=[
            ((46, 21), (48, 49), 6.8, 5.8),
            ((48, 49), (58, 68), 5.6, 4.4), ((58, 68), (56, 90), 4.6, 3.4), ((56, 90), (61, 94), 3.2, 2.6),
            ((48, 49), (38, 70), 5.4, 4.2), ((38, 70), (34, 91), 4.4, 3.2), ((34, 91), (39, 95), 3.0, 2.4),
            ((47, 24), (60, 25), 4.2, 3.2), ((60, 25), (71, 22), 3.2, 3.0),
            ((46, 25), (58, 31), 4.0, 3.2), ((58, 31), (70, 25), 3.2, 3.0),
        ],
        head=(44, 11, 5.4), helmet=True, bat=((70, 23), (94, 8), 2.6, 4.6), ball=None),
}


def _hull(a, b, ra, rb):
    (x1, y1), (x2, y2) = a, b
    d = math.hypot(x2 - x1, y2 - y1) or 1e-6
    ang = math.atan2(y2 - y1, x2 - x1)
    al = math.acos(max(-1, min(1, (ra - rb) / d)))
    pts = []
    for sgn in (1, -1):
        t = ang + sgn * al
        pts.append((x1 + ra * math.cos(t), y1 + ra * math.sin(t)))
    for sgn in (-1, 1):
        t = ang + sgn * al
        pts.append((x2 + rb * math.cos(t), y2 + rb * math.sin(t)))
    return 'M' + ' L'.join(f'{x:.2f} {y:.2f}' for x, y in pts) + 'Z'


def figure(name, x, y, h, fill, opacity=1.0, flip=False, accent=None):
    p = POSES[name]
    s = h / 100.0
    sx = -s if flip else s
    d = []
    circles = []
    for a, b, ra, rb in p['parts']:
        d.append(_hull(a, b, ra, rb))
        circles += [(a[0], a[1], ra), (b[0], b[1], rb)]
    hx, hy, hr = p['head']
    circles.append((hx, hy, hr))
    if p['helmet']:   # peak of the helmet
        d.append(_hull((hx + 1, hy - 1.5), (hx + hr + 3.5, hy + 1.8), 2.6, 1.8))
        circles.append((hx, hy - 0.6, hr + 0.9))
    if p['bat']:
        a, b, ra, rb = p['bat']
        d.append(_hull(a, b, ra, rb))
    out = f'<path d="{" ".join(d)}"/>' + ''.join(f'<circle cx="{cx:.2f}" cy="{cy:.2f}" r="{r:.2f}"/>' for cx, cy, r in circles)
    ball = ''
    if p['ball']:
        bx, by, br = p['ball']
        ball = f'<circle cx="{bx}" cy="{by}" r="{br}" fill="{accent or "#FFFFFF"}"/>'
    return f'<g transform="translate({x} {y}) scale({sx:.3f} {s:.3f})" opacity="{opacity}"><g fill="{fill}">{out}</g>{ball}</g>'


def flag_chip(code, cx, cy, r):
    if code == 'WI':   # no national flag: a maroon badge, like the plugin's initials badge
        inner = f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="#7B1E3A"/>'
        d, w = text_path('WI', r * 0.95, 0, 0, 0.02)
        inner += f'<g transform="translate({cx - w / 2:.1f} {cy + r * 0.34:.1f})"><path d="{d}" fill="#FDD835"/></g>'
        return inner + f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="#FFFFFF" stroke-opacity=".9" stroke-width="2.5"/>'
    b64 = base64.b64encode(open(os.path.join(FLAGS, code + '.png'), 'rb').read()).decode()
    cid = 'c' + code
    return (f'<clipPath id="{cid}"><circle cx="{cx}" cy="{cy}" r="{r}"/></clipPath>'
            f'<image href="data:image/png;base64,{b64}" x="{cx - r * 1.34:.1f}" y="{cy - r}" width="{r * 2.68:.1f}" height="{r * 2}" clip-path="url(#{cid})" preserveAspectRatio="xMidYMid slice"/>'
            f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="#FFFFFF" stroke-opacity=".9" stroke-width="2.5"/>')


def cover():
    grid = ''.join(f'<path d="M{x} 0V{H}" stroke="#14275A" stroke-width="1"/>' for x in range(0, W, 120)) + \
        ''.join(f'<path d="M0 {y}H{W}" stroke="#14275A" stroke-width="1"/>' for y in range(0, H, 120))
    defs = f'''<defs>
<radialGradient id="glow" cx=".5" cy=".46" r=".52"><stop offset="0" stop-color="#16408F" stop-opacity=".85"/><stop offset=".6" stop-color="#0E2A6B" stop-opacity=".35"/><stop offset="1" stop-color="{NAVY}" stop-opacity="0"/></radialGradient>
<linearGradient id="fig" gradientUnits="userSpaceOnUse" x1="0" y1="-15" x2="0" y2="98"><stop offset="0" stop-color="#2EE8FF"/><stop offset="1" stop-color="#0E6FA8"/></linearGradient>
<linearGradient id="arcg" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="{CYAN}" stop-opacity="0"/><stop offset=".5" stop-color="{CYAN}" stop-opacity=".55"/><stop offset="1" stop-color="{CYAN}" stop-opacity="0"/></linearGradient>
<linearGradient id="floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{NAVY}" stop-opacity="0"/><stop offset="1" stop-color="#081538"/></linearGradient>
</defs>'''
    # Silhouettes sit on both wings. The middle is the phone-safe area, so nothing important is outside x 265..1375.
    figs = (figure('stance', 250, 190, 270, '#162F6B', 1) +
            figure('drive', 40, 120, 390, 'url(#fig)', .97) +
            figure('stance', 1295, 205, 255, '#162F6B', 1, flip=True) +
            figure('bowler', 1215, 135, 395, 'url(#fig)', .97, accent='#FFFFFF'))
    arc = f'<path d="M-40 560 Q820 360 1680 90" fill="none" stroke="url(#arcg)" stroke-width="10" stroke-linecap="round"/>'
    # Centre block: mark, name, link.
    wm1, w1 = text_path('CRICKET', 90, 0, 0, 0.06)
    wm2, w2 = text_path('SPORT', 90, 0, 0, 0.06)
    tw = max(w1, w2)
    icon_s = 0.5
    block_w = 512 * icon_s + 34 + tw
    bx = (W - block_w) / 2
    link, lw = text_path('facebook.com/cricketsportinfo', 27, 0, 0, 0.01)
    chip_w = lw + 64
    centre = (f'<g transform="translate({bx:.1f} 112) scale({icon_s})">{icon("dark", "cv")}</g>'
              f'<g transform="translate({bx + 512 * icon_s + 34:.1f} 196)"><path d="{wm1}" fill="{WHITE}"/></g>'
              f'<g transform="translate({bx + 512 * icon_s + 34:.1f} 288)"><path d="{wm2}" fill="{CYAN}"/></g>'
              f'<rect x="{(W - chip_w) / 2:.1f}" y="338" width="{chip_w:.1f}" height="62" rx="31" fill="#102A66" stroke="{CYAN}" stroke-opacity=".55" stroke-width="2"/>'
              f'<g transform="translate({(W - lw) / 2:.1f} 379)"><path d="{link}" fill="#C8F7FF"/></g>')
    tag, tgw = text_path('LIVE SCORES   •   RESULTS   •   MILESTONES', 22, 0, 0, 0.16)
    tagline = f'<g transform="translate({(W - tgw) / 2:.1f} 450)"><path d="{tag}" fill="#8FA6DE"/></g>'
    # Ten teams.
    r, pitch = 27, 76
    x0 = W / 2 - pitch * 4.5
    flags = ''.join(flag_chip(c, x0 + i * pitch, 548, r) for i, c in enumerate(TEAMS))
    body = (defs + f'<rect width="{W}" height="{H}" fill="{NAVY}"/>' + grid +
            f'<rect width="{W}" height="{H}" fill="url(#glow)"/>' + arc + figs +
            f'<rect y="{H - 190}" width="{W}" height="190" fill="url(#floor)"/>' + centre + tagline + flags)
    return svg(W, H, body, None, 'Cricket Sport cover')


if __name__ == '__main__':
    open(os.path.join(HERE, 'svg', 'cricket-sport-cover.svg'), 'w').write(cover())
    print('cover ok')
