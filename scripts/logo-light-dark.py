#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
"""Baut das Portal-Logo: EINE logo.svg, die sich per @media (prefers-color-scheme: dark) selbst umstellt.

  logo-light-dark.py HELL DUNKEL [-o logo.svg]       two versions (SVG, PNG, WebP or JPEG)
  logo-light-dark.py HELL --farbe '#0d71b4=#37a6f1'  one SVG, recoloured in dark mode (repeatable)
  logo-light-dark.py HELL --gleich                   one SVG that looks fine in both modes (checked, unchanged)
  logo-light-dark.py --pruefen logo.svg              exit 0 if the file switches itself

With two versions both sit nested in the file; dark mode switches via `display`.
Raster images are embedded as data: URIs (the portals' CSP allows `img-src data:`).
Both versions should share the aspect ratio, else the dark one sits shrunk and centred.
The script straightens JPEGs with EXIF rotation itself (transform in the SVG, EXIF value set to 1).

Exit codes: 0 ok, 1 error, 2 wrong usage. Special cases the script doesn't solve itself abort:
  3  namespace collision: the same prefix (xmlns:x) maps to different URIs in the two versions
  4  same-named @keyframes in both versions (the dark ones would override the light ones)
"""
import argparse, base64, os, re, struct, sys, xml.dom.minidom

# Own prefixed classes: CSS applies document-wide and must not collide with classes of the inputs
# !important: a root rule of the input (svg{display:block} → .__hd-d{…}) must not cancel the switch
STIL_ZWEI = '<style>.__hd-d{display:none!important}@media (prefers-color-scheme:dark){.__hd-h{display:none!important}.__hd-d{display:inline!important}}</style>'
# Root presentation attributes inherited by the shapes; they move to the inner <svg>
ERBEN = re.compile(r'\s(?!xmlns|viewBox|width|height|version|x=|y=|id=|class=)([\w:-]+)=("[^"]*"|\'[^\']*\')')
RASTER = {b"\x89PNG": "image/png", b"RIFF": "image/webp", b"\xff\xd8": "image/jpeg"}


def fehler(text, code=1):
    print(f"logo-light-dark: {text}", file=sys.stderr)
    sys.exit(code)


def jpeg_drehung(daten):
    """(EXIF orientation, byte offset of its value) from the APP1 block, else (1, None)."""
    i = daten.find(b"Exif\0\0")
    if i < 0: return 1, None
    tiff = i + 6
    o = "<" if daten[tiff:tiff + 2] == b"II" else ">"
    try:
        ifd = tiff + struct.unpack(o + "I", daten[tiff + 4:tiff + 8])[0]
        for k in range(struct.unpack(o + "H", daten[ifd:ifd + 2])[0]):
            e = ifd + 2 + 12 * k
            if struct.unpack(o + "H", daten[e:e + 2])[0] == 0x0112:
                return struct.unpack(o + "H", daten[e + 8:e + 10])[0], (e + 8, o)
    except struct.error: pass
    return 1, None


# EXIF orientation → SVG matrix (a b c d e f) with w/h of the stored image; from 5 on, width and height are swapped
DREHUNG = {2: "-1 0 0 1 {w} 0", 3: "-1 0 0 -1 {w} {h}", 4: "1 0 0 -1 0 {h}",
           5: "0 1 1 0 0 0", 6: "0 1 -1 0 {h} 0", 7: "0 -1 -1 0 {h} {w}", 8: "0 -1 1 0 0 {w}"}


def keyframes(inhalt):
    return set(re.findall(r'@(?:-\w+-)?keyframes\s+([\w-]+)', inhalt))


def rastermass(daten, typ):
    """Width and height from the file header, without an image library."""
    if typ == "image/png":
        return struct.unpack(">II", daten[16:24])
    if typ == "image/webp":
        art = daten[12:16]
        if art == b"VP8X":
            return 1 + int.from_bytes(daten[24:27], "little"), 1 + int.from_bytes(daten[27:30], "little")
        if art == b"VP8L":
            b = int.from_bytes(daten[21:25], "little")
            return 1 + (b & 0x3FFF), 1 + ((b >> 14) & 0x3FFF)
        if art == b"VP8 ":
            w, h = struct.unpack("<HH", daten[26:30])
            return w & 0x3FFF, h & 0x3FFF
    if typ == "image/jpeg":
        i = 2
        while i < len(daten) - 9:
            if daten[i] != 0xFF: i += 1; continue
            marke, laenge = daten[i + 1], struct.unpack(">H", daten[i + 2:i + 4])[0]
            if 0xC0 <= marke <= 0xCF and marke not in (0xC4, 0xC8, 0xCC):
                h, w = struct.unpack(">HH", daten[i + 5:i + 9])
                return w, h
            i += 2 + laenge
    fehler(f"Bildmaße nicht lesbar ({typ})")


def einlesen(pfad):
    """(viewBox, content, namespaces, root attributes, width/height, root selectors) – content: SVG body or <image>."""
    daten = open(pfad, "rb").read()
    for kopf, typ in RASTER.items():
        if daten.startswith(kopf) and (typ != "image/webp" or daten[8:12] == b"WEBP"):
            w, h = rastermass(daten, typ)
            dreh, stelle = jpeg_drehung(daten) if typ == "image/jpeg" else (1, None)
            trans, aw, ah = "", w, h
            if dreh in DREHUNG:
                # browsers would apply the EXIF rotation on top: value to 1, the SVG does the rotation
                pos, o = stelle
                daten = daten[:pos] + struct.pack(o + "H", 1) + daten[pos + 2:]
                trans = f' transform="matrix({DREHUNG[dreh].format(w=w, h=h)})"'
                if dreh >= 5: aw, ah = h, w
            uri = f"data:{typ};base64," + base64.b64encode(daten).decode()
            return f"0 0 {aw} {ah}", f'<image width="{w}" height="{h}"{trans} href="{uri}"/>', {}, "", "", []
    try: text = daten.decode("utf-8")
    except UnicodeDecodeError: fehler(f"{pfad}: SVG bitte in UTF-8")
    m = re.search(r"<svg\b([^>]*)>(.*)</svg>", text, re.S)
    if not m: fehler(f"{pfad}: weder SVG noch PNG/WebP/JPEG")
    kopf, koerper = m.group(1), m.group(2)
    ns = {k: v for k, _, v in re.findall(r'\b(xmlns:[\w-]+)=(["\'])(.*?)\2', kopf)}
    erben = "".join(f" {k}={v}" for k, v in ERBEN.findall(kopf))
    # svg, #id and .class of the root: rules on them now hit the inner <svg class="__hd-…">
    wurzel = ["svg"] + [f"#{i}" for i in re.findall(r'\sid=["\']([^"\']+)', kopf)] + \
             [f".{k}" for c in re.findall(r'\sclass=["\']([^"\']+)', kopf) for k in c.split()]
    w, h = (re.search(rf'(?<![-\w]){a}=["\']([\d.]+)(px)?["\']', kopf) for a in ("width", "height"))
    mass = f' width="{w.group(1)}" height="{h.group(1)}"' if w and h else ""
    vb = re.search(r'viewBox=["\']([^"\']+)', kopf)
    if vb: return vb.group(1), koerper, ns, erben, mass, wurzel
    if not mass: fehler(f"{pfad}: weder viewBox noch width/height")
    return f"0 0 {w.group(1)} {h.group(1)}", koerper, ns, erben, mass, wurzel


def ids_praefix(inhalt, p):
    """Rename the dark version's own ids so references (url(#…), href="#…") don't point to the light one."""
    for i in set(re.findall(r'\bid=["\']([^"\']+)', inhalt)):
        e = re.escape(i)
        inhalt = re.sub(rf'\bid=(["\']){e}\1', rf'id=\g<1>{p}{i}\g<1>', inhalt)
        inhalt = re.sub(rf'(url\(\s*["\']?#){e}(["\']?\s*\))', rf'\g<1>{p}{i}\g<2>', inhalt)
        inhalt = re.sub(rf'(href=(["\'])#){e}\2', rf'\g<1>{p}{i}\g<2>', inhalt)
        inhalt = re.sub(r'<style\b.*?</style>', lambda m: re.sub(rf'#{e}(?![\w-])', f'#{p}{i}', m.group(0)), inhalt, flags=re.S)
    return inhalt


def stil_eingrenzen(inhalt, klasse, wurzel):
    """Restrict every selector in <style> to its own version: CSS applies document-wide (path{…}, .cls-1{…})."""
    w = "|".join(re.escape(t) for t in wurzel)
    def selektor(t):
        t = t.strip()
        ohne = re.sub(rf'^({w})(?![\w-])', f'.{klasse}', t)
        return ohne if ohne != t else f".{klasse} {t}"
    def regeln(css):
        return re.sub(r'(^|[{};])(\s*)([^{}@;]+?)(\s*\{)',
                      lambda r: r[1] + r[2] + ",".join(map(selektor, re.split(r',(?![^(]*\))', r[3]))) + r[4], css)
    def block(m):
        # @keyframes holds keyframe offsets (from, 50%), not selectors
        teile = re.split(r'(@(?:-\w+-)?keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\})', m[2])
        return m[1] + "".join(t if k % 2 else regeln(t) for k, t in enumerate(teile)) + m[3]
    return re.sub(r'(<style\b[^>]*>\s*(?:<!\[CDATA\[)?)(.*?)((?:\]\]>)?\s*</style>)', block, inhalt, flags=re.S)


def umstellend(text):
    return bool(re.search(r"<style[^>]*>\s*(<!\[CDATA\[)?[^<]*prefers-color-scheme\s*:\s*dark", text))


def main():
    a = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    a.add_argument("hell", nargs="?")
    a.add_argument("dunkel", nargs="?")
    a.add_argument("--farbe", action="append", default=[], metavar="ALT=NEU")
    a.add_argument("--gleich", action="store_true")
    a.add_argument("--pruefen", metavar="SVG")
    a.add_argument("-o", "--ausgabe", default="logo.svg")
    o = a.parse_args()
    if o.pruefen:
        ok = umstellend(open(o.pruefen, encoding="utf-8", errors="replace").read())
        if not ok: print(f"{o.pruefen}: stellt sich nicht selbst auf den dunklen Modus um (scripts/logo-light-dark.py)")
        sys.exit(0 if ok else 1)
    if not o.hell: a.error("HELL fehlt")
    if (bool(o.dunkel) + bool(o.farbe) + o.gleich) != 1: a.error("genau eins von DUNKEL, --farbe, --gleich")
    if any(os.path.exists(o.ausgabe) and os.path.samefile(e, o.ausgabe) for e in (o.hell, o.dunkel) if e):
        fehler(f"{o.ausgabe} ist zugleich Eingabe – andere Ausgabe mit -o wählen")
    if o.dunkel:
        (vb_h, inhalt_h, ns, erben_h, mass, wu_h), (vb_d, inhalt_d, ns_d, erben_d, _, wu_d) = einlesen(o.hell), einlesen(o.dunkel)
        for k in ns.keys() & ns_d.keys():
            if ns[k] != ns_d[k]: fehler(f"Namensraum-Kollision: {k} ist hell {ns[k]}, dunkel {ns_d[k]} – Präfix in einer Fassung umbenennen", 3)
        if gleich := keyframes(inhalt_h) & keyframes(inhalt_d):
            fehler(f"@keyframes in beiden Fassungen: {', '.join(sorted(gleich))} – in einer Fassung umbenennen", 4)
        ns = {"xmlns:xlink": "http://www.w3.org/1999/xlink", **ns_d, **ns}
        inhalt_h, inhalt_d = stil_eingrenzen(inhalt_h, "__hd-h", wu_h), stil_eingrenzen(ids_praefix(inhalt_d, "dunkel-"), "__hd-d", wu_d)
        mh, md = [list(map(float, re.split(r"[\s,]+", v.strip()))) for v in (vb_h, vb_d)]
        if abs(mh[2] / mh[3] - md[2] / md[3]) > 0.02 * mh[2] / mh[3]:
            print("Hinweis: Seitenverhältnis von hell und dunkel weicht ab", file=sys.stderr)
        kopf_ns = "".join(f' {k}="{v}"' for k, v in ns.items())
        svg = (f'<svg xmlns="http://www.w3.org/2000/svg"{kopf_ns} viewBox="{vb_h}"{mass}>{STIL_ZWEI}'
               f'<svg class="__hd-h" viewBox="{vb_h}"{erben_h}>{inhalt_h}</svg>'
               f'<svg class="__hd-d" viewBox="{vb_d}"{erben_d}>{inhalt_d}</svg></svg>\n')
    else:
        # One version: file stays as is (prolog, DOCTYPE, root); only the style is added
        try: text = open(o.hell, encoding="utf-8").read()
        except UnicodeDecodeError: fehler(f"{o.hell}: --farbe/--gleich nur für SVG in UTF-8")
        m = re.search(r"<svg\b[^>]*>", text)
        if not m: fehler(f"{o.hell}: keine SVG")
        if not re.search(r'viewBox=', m.group(0)):
            vb = einlesen(o.hell)[0]
            m_ende = m.end() - (2 if m.group(0).endswith("/>") else 1)
            text = text[:m_ende] + f' viewBox="{vb}"' + text[m_ende:]
            m = re.search(r"<svg\b[^>]*>", text)
        regeln, attr = [], ("fill", "stroke", "stop-color", "flood-color")
        for paar in o.farbe:
            alt, _, neu = paar.partition("=")
            if not re.fullmatch(r"#[0-9a-fA-F]{3,8}", alt) or not neu: a.error(f"--farbe {paar}: ALT=NEU, ALT als #rrggbb")
            formen = {alt, "#" + "".join(c * 2 for c in alt[1:])} if len(alt) == 4 else {alt}
            if not any(re.search(rf'\b{p}=["\']{re.escape(f)}["\']', text, re.I) for p in attr for f in formen):
                fehler(f"--farbe {alt}: kommt in keinem Attribut {'/'.join(attr)} vor")
            # as attribute, any case (i); style="…" and <style> classes beat any rule
            regeln += [f'[{p}="{f}" i]{{{p}:{neu}}}' for p in attr for f in sorted(formen)]
        if o.farbe and re.search(r'(style="[^"]*|<style\b[^>]*>[^<]*)(fill|stroke|stop-color|flood-color)\s*:', text):
            print("Hinweis: Farben in style=\"…\" oder <style> werden nicht umgestellt – als Attribut speichern (Inkscape: optimiertes SVG)", file=sys.stderr)
        stil = "".join(regeln) or "/* im dunklen Modus geprüft, unverändert */"
        text = text[:m.end()] + "<style>@media (prefers-color-scheme:dark){" + stil + "}</style>" + text[m.end():]
        svg = text
    try: xml.dom.minidom.parseString(svg.encode("utf-8"))
    except Exception as x: fehler(f"Ergebnis ist kein gültiges XML ({x}) – Eingabe vereinfachen (z. B. Inkscape: optimiertes SVG)")
    open(o.ausgabe, "w", encoding="utf-8").write(svg)

if __name__ == "__main__":
    main()
