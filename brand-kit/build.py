"""Builds the Cricket Sport logo set: SVG files (text turned into outlines), PNG renders and the cover.
Run: python3 build.py   (needs fonttools; PNGs are rendered by Playwright through render.cjs)
"""
import os, json
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

HERE = os.path.dirname(os.path.abspath(__file__))
FONT = os.path.join(HERE, '..', 'sixandfour-live', 'assets', 'fonts', 'Inter-ExtraBold.otf')
NAVY, CYAN, WHITE = '#0A1B44', '#00E5FF', '#FFFFFF'
TEAL = '#00A8C6'   # cyan that stays readable on white

_font = TTFont(FONT)
_gs, _cmap, _upm = _font.getGlyphSet(), _font.getBestCmap(), _font['head'].unitsPerEm
_hmtx = _font['hmtx']


def text_path(txt, size, x=0, y=0, tracking=0.0):
    """Outline a line of text. Returns (path d, width). y is the baseline."""
    sc = size / _upm
    cur, parts = 0.0, []
    for ch in txt:
        g = _cmap[ord(ch)]
        pen = SVGPathPen(_gs)
        _gs[g].draw(TransformPen(pen, (sc, 0, 0, -sc, x + cur, y)))
        parts.append(pen.getCommands())
        cur += _hmtx[g][0] * sc + tracking * size
    return ' '.join(p for p in parts if p), cur - tracking * size


def icon_group(bat, band, arc_a, arc_b, ball, groove, uid):
    """The mark in a 512 box: a bat leaning left and a ball's path cutting across it."""
    return f'''<defs>
  <linearGradient id="arc{uid}" x1="40" y1="440" x2="450" y2="140" gradientUnits="userSpaceOnUse">
    <stop offset="0" stop-color="{arc_a}"/><stop offset=".62" stop-color="{arc_b}"/><stop offset="1" stop-color="{arc_b}" stop-opacity=".0"/>
  </linearGradient>
</defs>
<g transform="rotate(-30 256 256)">
  <rect x="241" y="34" width="30" height="130" rx="12" fill="{bat}"/>
  <rect x="241" y="82" width="30" height="20" fill="{band}"/>
  <path d="M218 172 L294 172 L294 416 L276 454 L236 454 L218 416 Z" fill="{bat}"/>
  <rect x="249" y="198" width="14" height="196" rx="7" fill="{groove}"/>
</g>
<path d="M44 430 Q250 350 446 142" fill="none" stroke="url(#arc{uid})" stroke-width="30" stroke-linecap="round"/>
<path d="M62 424 Q252 350 436 150" fill="none" stroke="{WHITE if bat != WHITE else WHITE}" stroke-opacity=".9" stroke-width="7" stroke-linecap="round" style="mix-blend-mode:normal"/>
<circle cx="446" cy="142" r="23" fill="{ball}"/>'''


def svg(w, h, body, bg=None, title='Cricket Sport'):
    rect = f'<rect width="{w}" height="{h}" fill="{bg}"/>' if bg else ''
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}" role="img" aria-label="{title}"><title>{title}</title>{rect}{body}</svg>\n'


def icon(theme, uid='a'):
    if theme == 'dark':   # for navy or dark backgrounds
        return icon_group(WHITE, CYAN, CYAN, '#7FF3FF', CYAN, NAVY, uid)
    return icon_group(NAVY, CYAN, TEAL, CYAN, CYAN, '#2B3A66', uid)   # for white backgrounds


def wordmark(theme, size=100, x=0, y=0):
    main = WHITE if theme == 'dark' else NAVY
    acc = CYAN if theme == 'dark' else TEAL
    d1, w1 = text_path('CRICKET', size, x, y, 0.06)
    d2, w2 = text_path('SPORT', size, x, y + size * 1.08, 0.06)
    return f'<path d="{d1}" fill="{main}"/><path d="{d2}" fill="{acc}"/>', max(w1, w2)


def horizontal(theme):
    uid = 'h' + theme[0]
    wm, w = wordmark(theme, 112, 560, 244)
    body = f'<g>{icon(theme, uid)}</g>{wm}'
    return svg(int(560 + w + 20), 512, body)


def stacked(theme):
    uid = 's' + theme[0]
    wm, w = wordmark(theme, 100, 0, 0)
    d, _ = text_path('CRICKET', 100, 0, 0, 0.06)
    # centre the words under the mark
    wm, w = wordmark(theme, 100, 0, 0)
    W = max(w, 512) + 60
    wm, w = wordmark(theme, 100, (W - w) / 2, 640)
    body = f'<g transform="translate({(W - 512) / 2:.1f} 0)">{icon(theme, uid)}</g>{wm}'
    return svg(int(W), 840, body)


def avatar(theme, ring=False):
    """Profile picture: the mark on a plain square. Facebook crops it to a circle, so it sits inside a safe area."""
    bg = NAVY if theme == 'dark' else WHITE
    uid = 'a' + theme[0]
    body = f'<g transform="translate(66 66) scale(.742)">{icon(theme, uid)}</g>'
    if ring:
        body += f'<circle cx="256" cy="256" r="244" fill="none" stroke="{CYAN}" stroke-width="10"/>'
    return svg(512, 512, body, bg)


def cover():
    """Facebook cover, 1640 x 624, safe area in the middle for phones."""
    W, H = 1640, 624
    grid = ''.join(f'<path d="M{x} 0V{H}" stroke="#16295C" stroke-width="1"/>' for x in range(0, W, 130)) + \
        ''.join(f'<path d="M0 {y}H{W}" stroke="#16295C" stroke-width="1"/>' for y in range(0, H, 130))
    wm, w = wordmark('dark', 124, 0, 0)
    tag, tw = text_path('LIVE SCORES  •  RESULTS  •  MILESTONES', 30, 0, 0, 0.14)
    sx = (W - w) / 2 + 150
    body = f'''<defs><radialGradient id="glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#12306F" stop-opacity=".9"/><stop offset="1" stop-color="{NAVY}" stop-opacity="0"/></radialGradient></defs>
{grid}<circle cx="{W/2}" cy="{H/2}" r="330" fill="url(#glow)"/>
<path d="M-30 560 Q600 470 1210 250 T1700 40" fill="none" stroke="#00E5FF" stroke-opacity=".10" stroke-width="30" stroke-linecap="round"/>
<g transform="translate({W/2 - w/2 - 210:.1f} 140) scale(.62)">{icon('dark', 'c')}</g>
<g transform="translate({W/2 - w/2 + 140:.1f} 276)">{wm}</g>
<g transform="translate({W/2 - tw/2 + 80:.1f} 470)"><path d="{tag}" fill="#9FB4E6"/></g>'''
    return svg(W, H, body, NAVY)


def write(name, content):
    os.makedirs(os.path.join(HERE, 'svg'), exist_ok=True)
    with open(os.path.join(HERE, 'svg', name), 'w') as f:
        f.write(content)


if __name__ == '__main__':
    for t in ('light', 'dark'):
        write(f'cricket-sport-icon-{t}.svg', svg(512, 512, icon(t, 'i' + t[0])))
        write(f'cricket-sport-horizontal-{t}.svg', horizontal(t))
        write(f'cricket-sport-stacked-{t}.svg', stacked(t))
        write(f'cricket-sport-profile-{t}.svg', avatar(t))
    write('cricket-sport-profile-dark-ring.svg', avatar('dark', True))
    write('cricket-sport-cover.svg', cover())
    print('ok')
