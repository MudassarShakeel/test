#!/usr/bin/env python3
"""Create an invoice PDF whose number and file name are generated from the issue date.

Number format: <prefix>-DDMMYYYY  (3 Oct 2026 -> MMDS-03102026)
If that number already exists in the output folder, -2, -3 ... is appended.

Usage:
  python3 make_invoice.py --to "Client Name" --place "City – Country" \
      --desc "Payment for the example website" --amount 150
  Optional: --date 2026-10-03  --due-days 14  --out ./out  --gdoc
"""
import argparse, base64, html, json, subprocess, sys
from datetime import date, timedelta
from pathlib import Path

HERE = Path(__file__).resolve().parent
CFG = json.loads((HERE / "invoice.config.json").read_text())
CHROMIUM = "/opt/pw-browsers/chromium"

def fmt_date(d):  # 3 Oct 2026
    return f"{d.day} {d:%b %Y}"

def make_number(d, out_dir):
    base = f"{CFG['prefix']}-{d:%d%m%Y}"
    n, i = base, 1
    while (out_dir / f"{n}.pdf").exists():
        i += 1
        n = f"{base}-{i}"
    return n

def build_html(number, issue, due, to, place, desc, amount, logo_src, font_css):
    e = html.escape
    cur = CFG["currency"]
    frm = CFG["from"]
    from_lines = "".join(f'<p class="b13">{e(l)}</p>' for l in frm["lines"])
    amt = f"{amount:g} {cur}"
    return f"""<!doctype html><html><head><meta charset="utf-8"><title>{e(number)}</title>
<style>{font_css}
@page{{size:A4;margin:16mm 20mm}}
body{{font-family:'Alegreya Sans',Arial,sans-serif;font-size:12pt;color:#141414;margin:0}}
td{{font-family:'Alegreya Sans',Arial,sans-serif;font-size:12pt;vertical-align:top;color:#141414;border:none}}
p{{margin:0}} .b13{{font-size:13pt}} .h{{font-weight:bold;font-size:13pt}}
table{{width:100%;border-collapse:collapse;border:none}}
</style></head><body>
<table border="0" cellspacing="0" cellpadding="0"><tr>
<td style="width:50%"><img src="{logo_src}" width="130" height="130"></td>
<td style="width:50%;padding-top:20pt"><p style="font-size:38pt;font-weight:bold;line-height:1">INVOICE</p><p>Invoice number : {e(number)}</p></td></tr></table>
<p>&nbsp;</p>
<table border="0" cellspacing="0" cellpadding="0"><tr>
<td style="width:50%"><p class="h">From</p><p>&nbsp;</p><p class="h">{e(frm['name'])}</p>{from_lines}</td>
<td style="width:50%"><p class="h">Date of issue</p><p>&nbsp;</p><p class="b13">{fmt_date(issue)}</p><p>&nbsp;</p>
<p class="h">Due date</p><p>&nbsp;</p><p class="b13">{fmt_date(due)}</p><p>&nbsp;</p>
<p class="h">To</p><p>&nbsp;</p><p class="b13">{e(to)}</p><p class="b13">{e(place)}</p></td></tr></table>
<p>&nbsp;</p>
<table border="0" cellspacing="0" cellpadding="0">
<tr><td style="width:75%;padding-top:10pt;border-top:3px double #141414"><p class="h">Description</p></td>
<td style="width:25%;padding-top:10pt;border-top:3px double #141414"><p class="h" style="text-align:right">Amount({'€' if cur=='EUR' else cur})</p></td></tr>
<tr><td style="padding-top:14pt;padding-bottom:50pt"><p class="b13">{e(desc)}</p></td>
<td style="padding-top:14pt"><p class="b13" style="text-align:right">{e(amt)}</p></td></tr>
<tr><td style="padding:12pt 0;border-top:1px solid #141414"><p class="h" style="text-align:right">Total:</p></td>
<td style="padding:12pt 0;border-top:1px solid #141414"><p class="b13" style="text-align:right">{e(amt)}</p></td></tr>
<tr><td style="border-top:3px double #141414"><p style="font-size:4pt">&nbsp;</p></td><td style="border-top:3px double #141414"><p style="font-size:4pt">&nbsp;</p></td></tr></table>
<p>&nbsp;</p>
<p style="text-align:center;font-size:11pt"><b>Payment:</b> {e(CFG['payment_note'])}</p>
<p>&nbsp;</p>
<p style="text-align:center;font-size:11pt">If you have any questions, please contact me at <a href="mailto:{CFG['contact_email']}" style="color:#141414">{CFG['contact_email']}</a> or call me at {CFG['contact_phone']}.</p>
<p>&nbsp;</p><p>&nbsp;</p>
<p style="text-align:center;font-size:11pt">Thanks</p>
<p>&nbsp;</p><p>&nbsp;</p>
<p style="text-align:center;font-size:7pt">{e(CFG['disclaimer'])}</p>
</body></html>"""

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--to", required=True); ap.add_argument("--place", required=True)
    ap.add_argument("--desc", required=True); ap.add_argument("--amount", type=float, required=True)
    ap.add_argument("--date", help="issue date YYYY-MM-DD (default: today)")
    ap.add_argument("--due-days", type=int, default=CFG["due_days"])
    ap.add_argument("--out", default=str(HERE / "out"))
    ap.add_argument("--gdoc", action="store_true", help="also write <number>.gdoc.html (for Google Docs import)")
    a = ap.parse_args()
    issue = date.fromisoformat(a.date) if a.date else date.today()
    due = issue + timedelta(days=a.due_days)
    out = Path(a.out); out.mkdir(parents=True, exist_ok=True)
    number = make_number(issue, out)
    def font(w, f): return f"@font-face{{font-family:'Alegreya Sans';font-weight:{w};src:url(data:font/woff2;base64,{base64.b64encode((HERE/'fonts'/f).read_bytes()).decode()}) format('woff2')}}"
    font_css = font(400, "alegreya-sans-latin-400-normal.woff2") + font(700, "alegreya-sans-latin-700-normal.woff2")
    logo = "data:image/svg+xml;base64," + base64.b64encode((HERE / "logo.svg").read_bytes()).decode()
    page = build_html(number, issue, due, a.to, a.place, a.desc, a.amount, logo, font_css)
    tmp = out / f".{number}.html"; tmp.write_text(page)
    pdf = out / f"{number}.pdf"
    subprocess.run([CHROMIUM, "--headless", "--no-sandbox", "--no-pdf-header-footer", f"--print-to-pdf={pdf}", f"file://{tmp}"], check=True, capture_output=True)
    tmp.unlink()
    if a.gdoc:
        (out / f"{number}.gdoc.html").write_text(build_html(number, issue, due, a.to, a.place, a.desc, a.amount, "LOGO_DATA_URI", ""))
    print(f"{number}\n{pdf}")

if __name__ == "__main__":
    main()
