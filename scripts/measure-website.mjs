/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// measure-website.mjs – measures on a website what the browser actually shows: font family and size of
// body text (body or first paragraph) and the first visible h1–h3. Static CSS reading fails on site builders,
// classes, clamp() and resets; the browser has already resolved all that.
// Usage: node measure-website.mjs <url>   → JSON on stdout (sizes in px). Needs node and chromium.
import { spawn } from "node:child_process";
import { mkdtempSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const url = process.argv[2];
if (!url) { console.error("Aufruf: node measure-website.mjs <url>"); process.exit(2); }
const profil = mkdtempSync(join(tmpdir(), "messen-"));
const port = 9300 + Math.floor(Math.random() * 500);
const browser = spawn("chromium", ["--headless", "--disable-gpu", `--remote-debugging-port=${port}`, `--user-data-dir=${profil}`,
    "--window-size=1280,900", "about:blank"], { stdio: "ignore" });
const ende = (code) => { browser.kill(); try { rmSync(profil, { recursive: true, force: true }); } catch {} process.exit(code); };
setTimeout(() => { console.error("Zeitüberschreitung"); ende(2); }, 45000);

const warte = (ms) => new Promise((r) => setTimeout(r, ms));
let ziel;
for (let i = 0; i < 40 && !ziel; i++) {
    try { ziel = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((t) => t.type === "page"); } catch {}
    if (!ziel) await warte(250);
}
if (!ziel) { console.error("chromium antwortet nicht"); ende(2); }
const ws = new WebSocket(ziel.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener("open", r));
let nr = 0; const offen = new Map();
ws.addEventListener("message", (e) => { const m = JSON.parse(e.data); if (m.id && offen.has(m.id)) { offen.get(m.id)(m); offen.delete(m.id); } });
const cdp = (method, params = {}) => new Promise((r) => { const id = ++nr; offen.set(id, r); ws.send(JSON.stringify({ id, method, params })); });

await cdp("Page.enable");
await cdp("Page.navigate", { url });
await warte(6000);   // loading, fonts, site-builder JS
const js = `(() => {
  const sichtbar = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== "hidden"; };
  // most frequent font by text amount – a big intro paragraph or hero title must not decide
  const haeufigste = (sel) => {
    const z = new Map();
    for (const e of document.querySelectorAll(sel)) {
      if (!sichtbar(e)) continue;
      const n = e.textContent.trim().length; if (!n) continue;
      const c = getComputedStyle(e), k = c.fontFamily + "|" + parseFloat(c.fontSize);
      z.set(k, (z.get(k) || 0) + n);
    }
    if (!z.size) return null;
    const [familie, px] = [...z.entries()].sort((a, b) => b[1] - a[1])[0][0].split("|");
    return { familie, px: parseFloat(px) };
  };
  // widths per 1px font size in the site's fonts: mean character width (German body text) and the longest
  // portal heading – check-fonts.php derives page width (~75 characters) and max h2 size from them
  const breite = (fam, gew, txt) => { const c = document.createElement("canvas").getContext("2d"); c.font = gew + " 100px " + fam; return c.measureText(txt).width / 100; };
  const probe = "Ihre Meldung wird vom Kernteam der internen Meldestelle bearbeitet. Wenn Sie Nachteile befürchten, nutzen Sie bitte eine externe Meldestelle.";
  const tf = (haeufigste("main p, article p, p") || { familie: getComputedStyle(document.body).fontFamily }).familie;
  const he = [...document.querySelectorAll("h2, h1")].find(sichtbar);
  const hf = he ? getComputedStyle(he).fontFamily : tf, hg = he ? getComputedStyle(he).fontWeight : "700";
  const ue = "3. Rechtsverstoß melden oder Bearbeitung einsehen", sys = 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
  // plus the system font: the portal uses it if the site's font isn't free
  const faktoren = { zeichen: breite(tf, "400", probe) / probe.length, ueberschrift: breite(hf, "700", ue),   // portal sets h2 semi-bold (650)
                     zeichen_system: breite(sys, "400", probe) / probe.length, ueberschrift_system: breite(sys, "700", ue) };
  return JSON.stringify({ faktoren, text: haeufigste("main p, article p, [role=main] p") || haeufigste("p:not(footer p):not(nav p):not(header p)") || { familie: getComputedStyle(document.body).fontFamily, px: parseFloat(getComputedStyle(document.body).fontSize) },
    h1: haeufigste("h1"), h2: haeufigste("h2"), h3: haeufigste("h3") });
})()`;
const r = await cdp("Runtime.evaluate", { expression: js, returnByValue: true });
console.log(r.result?.result?.value ?? "{}");
ende(0);
