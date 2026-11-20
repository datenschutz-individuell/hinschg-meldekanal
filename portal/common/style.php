<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
include_once(__DIR__."/../common/helper.php");
header("Content-type: text/css");
// Hue (0–360) of the customer brand from settings.json "Farbton"; tints dark mode instead of black.
$farbton = is_numeric(setting("Farbton")) ? (int)setting("Farbton") % 360 : 220;
?>
/* Base design of all portals. Customer stylesheets (<portal>/style.css) override
   the variables below, mainly --farbe-hintergrund, --farbe-text, --farbe-link and the button colour. Class names
   stay unchanged because the code sets them. */

:root {
    color-scheme: light dark;
    --farbton:<?=$farbton?>;
    --farbe-hintergrund:#f5f6f8;
    --farbe-flaeche:#ffffff;
    --farbe-text:#1f2430;
    --farbe-text-leise:#5d6575;
    --farbe-rand:#d5d9e0;
    --farbe-akzent:#33507a;
    --farbe-akzent-text:#ffffff;
    --farbe-link:#2b5aa8;
    --farbe-link-besucht:#5b3f99;
    /* mixed from surface, accent and text, so they follow customer CSS and light/dark without own values */
    --farbe-meldung:var(--farbe-flaeche);
    --farbe-meldung-titel:color-mix(in srgb, var(--farbe-text) 7%, var(--farbe-flaeche));
    /* One base colour per conversation side (customer: logo colour or second logo colour, else grey); surfaces only tinted:
       header stronger, text background lighter – mixed towards white in light, towards the background in dark mode */
    --farbe-partei-meldende:var(--farbe-akzent);
    --farbe-partei-team:#808080;
    --farbe-chat-meldende:color-mix(in srgb, var(--farbe-partei-meldende) 10%, #fff);
    --farbe-chat-meldende-titel:color-mix(in srgb, var(--farbe-partei-meldende) 28%, #fff);
    --farbe-chat-team:color-mix(in srgb, var(--farbe-partei-team) 10%, #fff);
    --farbe-chat-team-titel:color-mix(in srgb, var(--farbe-partei-team) 28%, #fff);
    /* Message text in its side's tint, header black/white */
    --farbe-chat-meldende-text:color-mix(in srgb, var(--farbe-partei-meldende) 35%, #111);
    --farbe-chat-team-text:color-mix(in srgb, var(--farbe-partei-team) 35%, #111);
    --farbe-chat-meldende-balken:var(--farbe-partei-meldende);
    --farbe-chat-team-balken:var(--farbe-partei-team);
    --farbe-chat-status:var(--farbe-text-leise);
    --farbe-chat-meldende-titel-text:#000;
    --farbe-chat-team-titel-text:#000;
    /* Input field border: at least 3:1 (WCAG 1.4.11), hence stronger than --farbe-rand */
    --farbe-feld-rand:color-mix(in srgb, var(--farbe-text) 70%, var(--farbe-flaeche));
    /* Focus ring, active field and outline icon buttons: need 3:1 against page, surface and case box (WCAG 1.4.11), which a
       button colour often misses; customers whose --farbe-akzent fails set this one (check-colors.php) */
    --farbe-akzent-linie:var(--farbe-akzent);
    --farbe-erfolg:#1e7a3a;
    --farbe-fehler:#b3261e;
    --radius:10px;
}

@media (prefers-color-scheme: dark) {
    :root {
        /* dark in the brand colour instead of black */
        --farbe-hintergrund:hsl(var(--farbton) 32% 13%);
        --farbe-flaeche:hsl(var(--farbton) 26% 17%);
        --farbe-text:#e4e7ec;
        --farbe-text-leise:#a3abb8;
        --farbe-rand:hsl(var(--farbton) 18% 29%);
        --farbe-akzent:#8fb0e6;
        --farbe-akzent-text:#0f1520;
        --farbe-link:#9dbcf2;
        --farbe-link-besucht:#c2a8f0;
        --farbe-erfolg:#7fd39a;
        --farbe-chat-meldende:color-mix(in srgb, var(--farbe-partei-meldende) 16%, #1c1c1c);
        --farbe-chat-meldende-titel:color-mix(in srgb, var(--farbe-partei-meldende) 40%, #1c1c1c);
        --farbe-chat-team:color-mix(in srgb, var(--farbe-partei-team) 16%, #1c1c1c);
        --farbe-chat-team-titel:color-mix(in srgb, var(--farbe-partei-team) 40%, #1c1c1c);
        --farbe-chat-meldende-titel-text:#fff;
        --farbe-chat-meldende-text:color-mix(in srgb, var(--farbe-partei-meldende) 20%, #fff);
        --farbe-chat-team-text:color-mix(in srgb, var(--farbe-partei-team) 20%, #fff);
        --farbe-chat-team-titel-text:#fff;
        --farbe-fehler:#ff8a80;
    }
}

/* Basics */
*, *::before, *::after { box-sizing:border-box; }
[hidden] { display:none !important; }  /* class rules like .knopfreihe must not reveal hidden elements */
html { -webkit-text-size-adjust:100%; }
body {
    margin:0; background:var(--farbe-hintergrund); color:var(--farbe-text);
    font-family:var(--schrift, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif);  /* --schrift: the portal's fonts.css */
    font-size:var(--schriftgroesse, 1rem); line-height:1.55;  /* sizes: the portal's fonts.css */
}
#main-content { max-width:calc((var(--seitenbreite, calc(64ch + 2.5rem)) - 2.5rem) * 16 / 15 + 2.5rem);  /* check-fonts.php measures --seitenbreite per font for ~75 characters; 16/15 widens to 80 (WCAG 1.4.8 AAA limit) */ margin:0 auto; padding:1.5rem 1.25rem 1rem; }

h1, h2, h3 { line-height:1.25; margin:0; overflow-wrap:break-word; color:var(--farbe-ueberschrift, inherit); font-family:var(--schrift-ueberschrift, inherit); }
h1 { font-size:var(--groesse-h1, 1.75rem); font-weight:700; margin:0.25rem 0 1rem; letter-spacing:-0.01em; }
h2 { font-size:var(--groesse-h2, 1.15rem); font-weight:650; margin:1.75rem 0 0.5rem; }
h3 { font-size:var(--groesse-h3, 1rem); font-weight:650; margin:1.25rem 0 0.25rem; }
p, .p-like { margin:0 0 1rem; }
/* Hyphenation by lang attribute; "pretty" avoids a single word on the last line */
p, li, .p-like { hyphens:auto; -webkit-hyphens:auto; text-wrap:pretty;
    /* only words of 10+ characters, at least 4 before and 5 after the break: no „Si-cherungen“, „Em-mendingen“, „…stel-le“ */
    hyphenate-limit-chars:10 4 5; }
/* Never hyphenate proper names: customer company, address, service provider (class set in common/text/) */
.name, .eingerueckt { hyphens:manual; -webkit-hyphens:manual; }
h1, h2, h3 { text-wrap:balance; }
.p-like { display:block; }
ul, ol { margin:0 0 1rem; padding-left:1.25rem; }
span { overflow-wrap:break-word; }

a { color:var(--farbe-link); text-underline-offset:0.15em; text-decoration-thickness:1px; }
a:visited { color:var(--farbe-link-besucht); }
a:hover { text-decoration-thickness:2px; }
:focus-visible { outline:2px solid var(--farbe-akzent-linie); outline-offset:2px; border-radius:4px; }

/* Header with logo */
.logo { display:block; float:right; max-width:220px; max-height:56px; width:auto; height:auto; margin:0 0 1.25rem 1rem; }
/* hidden mail address (helper.php): no href, hence own link styling; long addresses may wrap */
a.mail { color:var(--farbe-link); text-decoration:underline; cursor:pointer; overflow-wrap:anywhere; }
a.mail svg { vertical-align:-0.15em; }

/* Form elements */
input, textarea, select, button { font:inherit; }
input[type=text], input[type=email], input[type=password], input:not([type]), textarea, select {
    width:100%; padding:0.6rem 0.75rem; color:var(--farbe-text); background:var(--farbe-flaeche);
    border:1px solid var(--farbe-feld-rand); border-radius:8px;
}
textarea { resize:vertical; min-height:8rem; }
input:focus, textarea:focus, select:focus { outline:none; border-color:var(--farbe-akzent-linie); box-shadow:0 0 0 3px color-mix(in srgb, var(--farbe-akzent-linie) 25%, transparent); }
::placeholder { color:var(--farbe-text-leise); opacity:1; }

button {
    display:inline-flex; align-items:center; justify-content:center; gap:0.5em; padding:0.75rem 1.1rem; min-height:3rem; border:0; border-radius:8px; cursor:pointer;
    background-color:var(--farbe-akzent); color:var(--farbe-akzent-text); font-weight:600; line-height:1.3;
    transition:filter 0.15s;
}
button:hover { filter:brightness(1.1); }
button .symbol { width:1.2em; height:1.2em; flex:none; color:inherit; }   /* every button icon in the button's text colour */
button:active { filter:brightness(0.95); }
/* Final steps (invalidating the token) as secondary button, not like the main action */
button[name=invalidate_token], button[name=confirmed_invalidate_token], button[name=vorgang_loeschen] { background:transparent; color:var(--farbe-fehler); border:1.5px solid var(--farbe-fehler); }

/* Label left, field right; stacked below 600px (see below) */
form.label-links { background:var(--farbe-flaeche); border:1px solid var(--farbe-rand); border-radius:var(--radius); padding:1.25rem; }
form.label-links + *, #webauthn-meldung:not(:empty) { margin-top:1rem; }   /* nothing sticks to the box (messages, links, next section) */
form.label-links .anm-art { margin:0 0 1rem; font-weight:600; }
form.label-links .anm-art + button { margin-top:0; }
.anm-oder { text-align:center; margin:0.75rem 0; color:var(--farbe-text-leise); }
form.label-links label { display:flex; align-items:flex-start; gap:1rem; width:100%; margin-top:1.25rem; }   /* same as to the button */
form.label-links label:has([onkeyup]) + label { margin-top:1.75rem; }   /* room for the .hinweis-eingabe counter below */
form.label-links label:first-of-type { margin-top:0; }
form.label-links label > :first-child { flex:0 0 32%; padding-top:0.6rem; font-weight:600; }
form.label-links label > :nth-child(2) { flex:1 1 auto; min-width:0; }
form.label-links label.breit { flex-direction:column; gap:0.35rem; }   /* description: full width instead of right column */
form.label-links label.breit > * { flex:none; width:100%; }
form.label-links label.breit > :first-child { padding-top:0; }
form.label-links button { width:100%; margin-top:1.25rem; }
form.label-links .knopfpaar { margin:1.25rem 0 0; } form.label-links .knopfpaar button { margin-top:0; }   /* pair: same gap as a single button */
p.unter-knopf { margin:0.75rem 0 0; }

/* The two buttons on the start page */
/* Side by side while every label fits on one line with its icon, else stacked (wrap decides on max-content) */
.partner-button-container { display:flex; flex-wrap:wrap; gap:0.75rem; margin:0.75rem 0 0.5rem; }
.partner-button-container > form { margin:0; flex:1 1 max-content; }
.partner-button-container button { width:100%; }
/* With script (js.php): equally wide columns while no label wraps, else equally wide rows */
:is(.start-knoepfe, .knopfpaar).gleich { display:grid; grid-auto-flow:column; grid-auto-columns:minmax(0, 1fr); }
:is(.start-knoepfe, .knopfpaar).gleich button { white-space:nowrap; overflow:hidden; }
:is(.start-knoepfe, .knopfpaar).gleich.gestapelt { grid-auto-flow:row; }
:is(.start-knoepfe, .knopfpaar).gleich.gestapelt button { white-space:normal; }
.knopfpaar.gleich:not(.gestapelt) > .zurueck { order:-1; }   /* back on the left; stacked: forward stays on top */

/* Notices, status */
.font-normal  { font-style:normal; font-weight:normal; }
.font-erfolg  { color:var(--farbe-erfolg); }
.spam-falle   { position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden; }
.font-fehler  { color:var(--farbe-fehler); }
.warnkasten { margin:0 0 1.25rem; padding:0.6rem 0.9rem; border:2px solid var(--farbe-fehler); border-radius:var(--radius);
    background:color-mix(in srgb, var(--farbe-fehler) 8%, var(--farbe-hintergrund)); }
.warnkasten-titel { display:flex; align-items:center; gap:0.4rem; margin:0 0 0.25rem; font-weight:700; color:var(--farbe-fehler); }
.warnkasten-titel .symbol { width:1.25em; height:1.25em; flex:none; color:inherit; }
.warnkasten > p:last-child { margin:0; }
label.loeschgrund { display:grid; gap:0.35rem; margin:0 0 1rem; font-weight:600; }
label.loeschgrund select { font-weight:400; }
.loeschprotokoll { margin-top:1.5rem; }
.loeschprotokoll summary { cursor:pointer; }
.loeschprotokoll > .meldung-container:first-of-type { margin-top:0.75rem; }
/* Deletion set apart from working on the case: own section below a line, final buttons red */
.loeschbereich { margin-top:3rem; padding-top:1.25rem; border-top:1px solid var(--farbe-rand); }
.loeschbereich h2 { margin-top:0; font-size:1.1em; }
.loeschhinweis { margin:0 0 1.25rem; padding:0.75rem 1rem; border-left:4px solid var(--farbe-fehler); border-radius:var(--radius);
    background:color-mix(in srgb, var(--farbe-fehler) 6%, var(--farbe-hintergrund)); }
.loeschhinweis p { margin:0; }
button.gefahr, button[name=sammel_ausfuehren] { background:var(--farbe-fehler); color:var(--farbe-hintergrund); border:1.5px solid var(--farbe-fehler); }
/* Team list: pencil and bin as icon buttons */
.meldung-container > .aktionen { display:flex; gap:0.5rem; justify-content:flex-end; align-items:center; }
.aktionen form { margin:0; }
.meldung-container .symbolknopf { display:inline-flex; align-items:center; justify-content:center; width:2.75rem; min-height:2.75rem; height:2.75rem; padding:0;
    border:1.5px solid var(--farbe-akzent-linie); border-radius:var(--radius); background:transparent; color:var(--farbe-akzent-linie); }
.symbolknopf .symbol { width:1.4rem; height:1.4rem; }
.sammelliste { list-style:none; padding:0; }
.sammelliste li { display:grid; grid-template-columns:1fr 21rem; gap:0.3rem 1rem; align-items:center; margin:0 0 0.9rem; hyphens:manual; -webkit-hyphens:manual; }
.sammelliste li > label, .sammelliste li > .sammeltitel { display:flex; flex-direction:column; }
.sammelliste li > .sammelmeta { grid-column:1; }
@media (max-width:600px) { .sammelliste li { grid-template-columns:1fr; } }
.pruefliste li { display:flex; flex-direction:column; }   /* review page: text only, no selection column */
.sammelliste li.sammelzeile { display:flex; gap:0.6rem; align-items:flex-start; margin:0 0 0.4rem; }
.sammelliste li.sammelzeile > input { flex:none; margin:0.3rem 0 0; width:1.1rem; height:1.1rem; }
.sammelliste li.sammelzeile > label { display:block; flex:1; min-width:0; cursor:pointer; }
.sammelmeta { color:var(--farbe-text-leise); font-size:0.85em; }
.geraeteliste li { grid-template-columns:1fr auto; row-gap:0; }   /* name over date, button beside both */
.geraeteliste li > form { grid-column:2; grid-row:1 / span 2; }
.kontozeile { margin:3rem 0 0; }   /* apart from the content above, close to the footer */
.kontozeile { display:flex; flex-wrap:wrap; justify-content:space-between; gap:0 1rem; }
.kontozeile > span { white-space:nowrap; }
.kontozeile .konto-trenner { display:none; }
.kontozeile:is(.eng, .enger) { justify-content:flex-start; gap:0; }
.kontozeile:is(.eng, .enger) .konto-trenner { display:inline; white-space:pre; }
.kontozeile:is(.enger, .am-engsten) .konto-als { display:none; }
.kontozeile.am-engsten { flex-direction:column; }
/* Privacy page: collapsible sections like the DSi website; heading in the summary, +/− on the right */
details.dse { border-bottom:1px solid var(--farbe-rand); }
details.dse > summary { list-style:none; cursor:pointer; position:relative; padding:0.6rem 2rem 0.6rem 0; }
details.dse > summary::-webkit-details-marker { display:none; }
details.dse > summary::after { content:"+"; position:absolute; right:0.25rem; top:50%; transform:translateY(-50%); font-size:1.4em; line-height:1; color:var(--farbe-ueberschrift, inherit); }
details.dse[open] > summary::after { content:"−"; }
details.dse > summary > h3 { margin:0; }
details.dse > summary:hover > h3, details.dse > summary:hover::after { color:var(--farbe-link); }
details.dse > :last-child { margin-bottom:1rem; }
h2 + details.dse { border-top:1px solid var(--farbe-rand); }
#main-content :is(h2, h3) + p { margin-top:0.6rem; }   /* text must not stick to its heading */
h2.sammelgruppe { font-size:1.05em; margin:1.5rem 0 0.6rem; }
button.zweitrangig { background:transparent; color:var(--farbe-link); border:1.5px solid currentColor; }
form.sammel-start { margin:2rem 0 0; }
.font-status-abgeschlossen { color:var(--farbe-erfolg); font-weight:600; }
.font-status-offen         { color:var(--farbe-link); font-weight:600; }
.font-status-anderer       { color:var(--farbe-fehler); font-weight:600; }
.hinweis-eingabe { position:absolute!important; color:var(--farbe-text-leise); font-size:0.8rem; padding-top:0.15rem; }
.platz-fuer-hinweis { margin-bottom:0.9rem; }  /* .hinweis-eingabe sits absolutely below */
.hinweis-titel   { margin-bottom:0.25rem; color:var(--farbe-text-leise); font-size:0.85rem; }

/* Confirmation overlay (helper.php bestaetigung()): visible 4 s, then fades; never blocks clicks */
.bestaetigung { position:fixed; top:1rem; left:50%; transform:translateX(-50%); z-index:10; max-width:calc(100% - 2rem); padding:0.6rem 1rem;
    background:var(--farbe-flaeche); color:var(--farbe-link); font-weight:600; border:2px solid var(--farbe-link); border-radius:var(--radius);
    box-shadow:0 4px 16px rgb(0 0 0 / 0.25); pointer-events:none; animation:bestaetigung-weg 0.6s ease-in 4s forwards; }
@keyframes bestaetigung-weg { to { opacity:0; visibility:hidden; } }
@media (prefers-reduced-motion: reduce) { .bestaetigung { animation-duration:0s; animation-delay:6s; } }

/* Token after reporting */
.token-container {
    /* Box overhangs the text by padding+border, so the token itself is flush with the text above and below;
       size from the width (--breite: em of the whole code, code_kasten()), at least 1rem: then two lines at the <wbr> */
    margin:0 calc(-1rem - 2px); padding:0.75rem 1rem; text-align:center; border:2px dashed var(--farbe-akzent); border-radius:var(--radius);
    background:var(--farbe-flaeche); font-family:ui-monospace, "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", monospace;
    font-size:max(1rem, min(2rem, 100cqi / var(--breite))); letter-spacing:0.05em; user-select:all;
}
.token-container > span { white-space:nowrap; }
.token-rahmen { container-type:inline-size; margin:0.5rem 0 1rem; }   /* 100cqi = text width, also without access card; containment
    prevents margin collapsing, hence the margin here instead of on the box */

/* Utility classes */
.eingerueckt   { margin-left:1.5rem; }
.neue-zeile    { clear:both; }
.label-rechts  { display:block; width:100%; }
.volle-breite  { width:100%; }
.abstand       { margin-top:1rem; }
.viel-abstand  { margin-top:1.5rem; }
.centered      { text-align:center; }
.pw-switch-button { float:right; width:fit-content!important; min-height:0; padding:0.2rem 0.6rem; margin:0!important; font-size:0.85rem; font-weight:500; }

/* Case list under /team */
.meldung-container { display:flex; flex-wrap:wrap; width:100%; background:var(--farbe-meldung); border:1px solid var(--farbe-rand); border-radius:var(--radius); overflow:hidden; margin-bottom:1rem; }
.meldung-container > :nth-child(3n + 1) { width:100%; padding:0.75rem 1rem; background:var(--farbe-meldung-titel); font-weight:600; }
.meldung-container > :nth-child(3n + 2) { width:75%; padding:0.75rem 1rem; }
.meldung-container > :nth-child(3n + 3) { width:25%; padding:0.5rem 1rem 0.5rem 0; margin:auto; }
.meldung-container button { width:100%; }

/* Conversation of a case */
.chat-text { white-space:pre-wrap; overflow-wrap:break-word; margin:0; }
.chat-meldende-person, .chat-team { border:1px solid; border-radius:var(--radius); overflow:hidden; margin-bottom:1rem; }
.chat-meldende-person { background:var(--farbe-chat-meldende); margin-right:12%; border-color:var(--farbe-chat-meldende-titel); border-left:5px solid var(--farbe-chat-meldende-balken); }
.chat-team { background:var(--farbe-chat-team); margin-left:12%; border-color:var(--farbe-chat-team-titel); border-right:5px solid var(--farbe-chat-team-balken); }
/* Icon before the header: speech bubble = reporter, building = reporting office; colour follows the text */
.chat-meldende-person > :first-child::before, .chat-team > :first-child::before {
    content:""; display:inline-block; width:1.1em; height:1.1em; margin-right:0.45em; vertical-align:-0.2em;
    background:currentColor; -webkit-mask:var(--symbol) center/contain no-repeat; mask:var(--symbol) center/contain no-repeat; }
.chat-meldende-person > :first-child { --symbol:url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M4 3h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z'/></svg>"); }
.chat-team > :first-child { --symbol:url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M3 21V4l9-2v5l9 2v12H3zm3-3h3v-2H6v2zm0-4h3v-2H6v2zm0-4h3V8H6v2zm7 8h3v-2h-3v2zm0-4h3v-2h-3v2zm4 4h2v-2h-2v2zm0-4h2v-2h-2v2z'/></svg>"); }
.chat-meldende-person > *, .chat-team > * { padding:0.6rem 1rem; }
.chat-meldende-person > :nth-child(odd) { background:var(--farbe-chat-meldende-titel); color:var(--farbe-chat-meldende-titel-text); font-size:0.85rem; }
.chat-team > :nth-child(odd) { background:var(--farbe-chat-team-titel); color:var(--farbe-chat-team-titel-text); font-size:0.85rem; }
.chat-meldende-person .chat-text { color:var(--farbe-chat-meldende-text); }
.chat-team .chat-text { color:var(--farbe-chat-team-text); }
.chat-system { margin:0 0 1rem; font-style:italic; font-size:0.9rem; color:var(--farbe-chat-status); text-align:center; }
.chat-grenze { font-weight:600; margin:0.5rem 0 1rem; }

/* Illustrated design (helper.php illustriert()): icons in link colour, whose contrast is already ensured */
.symbol { width:1.6em; height:1.6em; flex:none; color:var(--farbe-link); }
/* Three tiles side by side or stacked, never 2 + 1: below 29rem (3 x 9rem + gaps) the clamp jumps to full width */
.schritte { display:grid; grid-template-columns:repeat(auto-fit, minmax(clamp(100% / 3 - 1rem, (29rem - 100%) * 999, 100%), 1fr)); align-items:stretch; gap:0.5rem 1rem; margin:1rem 0 1.25rem; padding:0; }
/* Icon and heading bottom-aligned in row 1, text below from the icon's left edge */
.schritt { display:grid; grid-template-columns:auto 1fr; column-gap:0.5rem; align-content:start; align-items:end;
    /* Tinted card, not a border: a button-coloured frame would look clickable */
    background:color-mix(in srgb, var(--farbe-akzent) 9%, var(--farbe-hintergrund)); border-radius:8px; padding:0.5rem 0.65rem; }
.schritt > span { grid-column:1 / -1; align-self:start; margin-top:0.3rem; font-size:0.9em; color:var(--farbe-text-leise); hyphens:auto; hyphenate-limit-chars:10 5 5; -webkit-hyphenate-limit-before:5; -webkit-hyphenate-limit-after:5;
    text-wrap:balance; text-wrap:pretty; }   /* hyphenate only words of 10+ letters, 5 on each side; no lone last word (balance where pretty is unknown) */
/* Each part on its own line, only while every part of all tiles fits (js.php) */
.schritte.zweizeilig .zeile { display:block; white-space:nowrap; overflow:hidden; }
.vertrauen { display:flex; align-items:flex-start; gap:0.6rem; margin:0 0 1.25rem; padding:0.5rem 0.75rem; color:var(--farbe-chat-meldende-text);
    background:var(--farbe-chat-meldende); border-left:4px solid var(--farbe-chat-meldende-balken); border-radius:var(--radius); font-size:0.95em; }
.vertrauen .symbol { color:inherit; margin-top:0.15em; }
.vertrauen ul { margin:0; padding:0; list-style:none; }
.vertrauen li { display:inline; }
.vertrauen li + li::before { content:" · "; }
.knopfreihe { display:grid; gap:0.75rem; }
.neu-reihe .knopfreihe { margin-top:0.5rem; }
button.knopf-erfolg { background:var(--farbe-erfolg); color:var(--farbe-hintergrund); }
.fehlerseite { text-align:center; margin-top:2rem; }
.fehlerseite > .symbol { width:4.5rem; height:4.5rem; stroke-width:1.5; }
.fehlerseite h1 { margin-top:0.5rem; }
.fristen-info { margin:0 0 1.5rem; }
.fristen-info ul { margin:0.5rem 0; }
fieldset.gruppe { margin:0 0 1.5rem; padding:0; border:0; min-width:0; }
/* Without illustrations the same markup is shown plain: no card frame, no symbol, no group titles */
body.plain .zugangskarte { display:block; padding:0; border:0; background:none; }
body.plain .zugangskarte > .symbol, body.plain fieldset.gruppe > legend { display:none; }
body.plain .zugangskarte > div > * { margin-left:0; margin-right:0; }   /* undo the narrow-screen card insets */
fieldset.gruppe legend { padding:0; margin:0 0 0.9rem; font-weight:700; font-size:1.05em; color:var(--farbe-ueberschrift, inherit); }
.zugangskarte { display:flex; align-items:flex-start; gap:0.75rem; margin:0 0 1.5rem; padding:1rem calc(1.75rem + 2em) 1rem 1rem; background:var(--farbe-flaeche);
    border:2px solid var(--farbe-akzent); border-radius:var(--radius); }
.zugangskarte h2 { margin-top:0.8rem; }   /* visibly as much space as below the last button (34 px, measured) */
.zugangskarte > .symbol { width:2em; height:2em; margin-top:0.2em; }
.zugangskarte > div { min-width:0; flex:1; }
.zugangskarte > div > :last-child { margin-bottom:0; }
#token_copy_response:empty { display:none; }
.plakette { display:inline-block; padding:0 0.6rem; border:1px solid currentColor; border-radius:999px; font-size:0.85em; font-weight:600; }
.plakette-offen { color:var(--farbe-link); }
.plakette-fertig { color:var(--farbe-erfolg); }
.plakette-anders { color:var(--farbe-fehler); }
.frist { font-size:0.85em; }
.frist-gruen { color:var(--farbe-text-leise); }
.frist-gelb::before { content:"⏳ "; }
.frist-rot { color:var(--farbe-fehler); font-weight:600; }
.frist-rot::before { content:"⚠ "; }

/* Footer */
footer.footer { clear:both; margin-top:2.5rem; font-size:clamp(0.75rem, 0.85em, 0.875rem);   /* smaller than body text, even where that is small (some sites use 14px) */
    color:var(--farbe-text-leise); }
hr.footer { border:0; border-top:1px solid var(--farbe-rand); margin:0 0 0.75rem; }
hr.quellen { border:0; border-top:1px solid var(--farbe-rand); margin:2rem 0 1.25rem; }
nav.fuss-zeile { display:grid; grid-template-columns:1fr auto 1fr; align-items:baseline; gap:0.25rem 1rem; }
/* Centred while both sides fit; otherwise the middle gives way instead of the long side wrapping (cs, lt, lv) */
@media (min-width:601px) { nav.fuss-zeile { grid-template-columns:minmax(max-content, 1fr) auto minmax(max-content, 1fr); } }
.fuss-mitte { justify-self:center; }
.fuss-rechts { justify-self:end; text-align:right; }
/* Separator "•" only between parts of the same line: every part carries one (left: before, right:
   after); the list overhangs the group edge by its width and is clipped there – exactly the separator that would
   stand at the line break. */
.fuss-links, .fuss-rechts { overflow:clip; overflow-clip-margin:3px; }   /* 3px: focus ring stays visible */
.fuss-liste { display:block; }
.fuss-teil { display:inline-block; }
.fuss-links .fuss-teil::before, .fuss-rechts .fuss-teil::after { content:"•" / ""; display:inline-block; width:1.2em; text-align:center; }
.fuss-links .fuss-liste { margin-left:-1.2em; }    /* separator at line start lies in the clipped strip */
.fuss-rechts .fuss-liste { margin-right:-1.2em; }  /* likewise at line end */
details.sprachmenue { position:relative; }
details.sprachmenue summary { cursor:pointer; color:var(--farbe-link); list-style:none; }
details.sprachmenue summary::-webkit-details-marker { display:none; }
details.sprachmenue summary::after { content:" ▾"; }
details.sprachmenue[open] summary::after { content:" ▴"; }
details.sprachmenue ul { position:absolute; bottom:100%; left:50%; transform:translateX(-50%); z-index:1; margin:0 0 0.25rem; padding:0.35rem 0; list-style:none;
    background:var(--farbe-flaeche); border:1px solid var(--farbe-rand); border-radius:0.375rem; box-shadow:0 2px 8px rgb(0 0 0 / 0.15); }
details.sprachmenue li a { display:block; padding:0.3rem 0.9rem; white-space:nowrap; }
/* Narrow: "Sprache:" hidden only visually (screen readers still read it), so the footer stays on one line */
@media (max-width:30rem) { .schmal-aus { position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; } }

/* Narrow screens */
@media (max-width:600px) {
    /* Page margin: boxes and buttons one line width (2px) from the screen edge, left, right and
       bottom; the text stands in one column flush with the token text (--rand: 2px gap, 2px border, 2px padding,
       2px dashed line, 2px to the token – one line width everywhere). */
    #main-content { --rand:10px; padding:1rem var(--rand) 2px; }
    #main-content :is(form.label-links, .zugangskarte, .vertrauen, .warnkasten, .loeschhinweis, form.sammel-start, .meldung-container, .partner-button-container, .volle-breite):not(:is(form.label-links, .zugangskarte, .meldung-container) *) { margin-left:calc(2px - var(--rand)); margin-right:calc(2px - var(--rand)); }
    #main-content .volle-breite:not(:is(form.label-links, .zugangskarte, .meldung-container) *) { width:calc(100% + 2 * var(--rand) - 4px); }
    .meldung-container { width:auto; }
    .schritte { grid-template-columns:1fr; margin-left:calc(2px - var(--rand)); margin-right:calc(2px - var(--rand)); }
    .schritt { padding:0.5rem calc(var(--rand) - 2px); }
    .warnkasten { padding-left:calc(var(--rand) - 4px); padding-right:calc(var(--rand) - 4px); }
    .loeschhinweis { padding-left:calc(var(--rand) - 6px); padding-right:calc(var(--rand) - 2px); }   /* left: 2px gap + 4px bar; right: 2px gap */
    .token-container { margin:0 calc(2px - var(--rand)); padding-left:calc(var(--rand) - 4px); padding-right:calc(var(--rand) - 4px); }
    h1 { font-size:min(var(--groesse-h1, 1.75rem), 1.5rem); }
    .logo { max-width:160px; max-height:44px; }
    .label-rechts { width:100%!important; float:none!important; }
    form.label-links { padding:1rem calc(var(--rand) - 3px); }   /* 2px margin + 1px border */
    form.label-links label { flex-direction:column; gap:0.35rem; }
    form.label-links label > :first-child { flex:none; padding-top:0; }
    form.label-links label > :nth-child(2) { width:100%; }
    .meldung-container > :nth-child(3n + 2), .meldung-container > :nth-child(3n + 3) { width:100%; padding:0.5rem calc(var(--rand) - 3px); }
    .chat-meldende-person { margin-right:1.5rem; }
    .chat-team { margin-left:1.5rem; }
    .eingerueckt { margin-left:1rem; }
    /* Narrow access card: key next to the heading, token box and buttons only one
       line width (2px) from the edge; text flush with token and page text. Card content starts 6px from the screen
       (2px gap, 2px border, 2px padding), the token text additionally after the dashed line (2px) and padding. */
    .zugangskarte { display:grid; grid-template-columns:auto 1fr; column-gap:0.5rem; align-items:center; padding:0.75rem 2px 2px; }
    .zugangskarte > div { display:contents; }
    .zugangskarte > div > * { grid-column:1 / -1; margin-left:calc(var(--rand) - 6px); margin-right:calc(var(--rand) - 6px); }
    .zugangskarte > .symbol { margin:0 0 0 calc(var(--rand) - 6px); }
    .zugangskarte > div > h2 { grid-column:2; margin:0; }
    /* Margins do not collapse in a grid: the paragraphs space the token, the buttons close off the bottom */
    .zugangskarte > div > .token-rahmen { margin:0 0 1rem; }
    .zugangskarte > div > .knopfreihe { margin:0; }
    .zugangskarte .token-container { margin:0; padding:0.75rem calc(var(--rand) - 8px);   /* top/bottom as on desktop */
        font-size:min(2rem, (100cqi - 2 * var(--rand) + 12px) / 18.9); }
}

/* Print: without buttons and footer, e.g. for the token */
@media print {
    button, footer.footer { display:none; }
    body { background:#fff; color:#000; }
    /* Dark mode and customer CSS set light text – invisible on paper */
    :root { --farbe-hintergrund:#fff!important; --farbe-flaeche:#fff!important; --farbe-text:#000!important;
            --farbe-text-leise:#444!important; --farbe-ueberschrift:#000!important; --farbe-link:#000!important;
            --farbe-link-besucht:#000!important; --farbe-rand:#999!important;
            --farbe-chat-meldende:#fff!important; --farbe-chat-team:#fff!important;
            --farbe-chat-meldende-titel:#fff!important; --farbe-chat-team-titel:#fff!important;
            --farbe-chat-meldende-text:#000!important; --farbe-chat-team-text:#000!important;
            --farbe-chat-meldende-titel-text:#000!important; --farbe-chat-team-titel-text:#000!important;
            --farbe-chat-status:#444!important;
            --farbe-chat-meldende-balken:#000!important; --farbe-chat-team-balken:#888!important; }
}
