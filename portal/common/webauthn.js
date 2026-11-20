/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Passkeys and security keys for /team, server side in team/webauthn.php. Texts come from data-* attributes of
// #webauthn-texte, so this file needs no translation. Buttons: #passkey-login, #schluessel-2fa, #neu-passkey, #neu-schluessel.
(() => {
    // Uint8Array, not ArrayBuffer: extensions wrapping navigator.credentials (KeePassXC-Browser in Firefox) failed to pass on the latter
    const b2a = s => Uint8Array.from(atob(s.replace(/-/g, "+").replace(/_/g, "/")), c => c.charCodeAt(0));
    const a2b = b => btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
    const texte = document.getElementById("webauthn-texte")?.dataset || {};
    const meldung = t => { const m = document.getElementById("webauthn-meldung"); if (m) m.textContent = t; };
    // Browser errors in the user's terms. NotAllowedError deliberately means "no matching passkey", "cancelled" and
    // "timed out" alike (sites must not learn which passkeys exist), so the text names what to do in each situation.
    const browserFehler = (e, lage) => meldung(
        e.name === "NotAllowedError" ? texte["nichtErlaubt" + lage] :
        e.name === "InvalidStateError" ? texte.schonDa :
        ["NotSupportedError", "SecurityError"].includes(e.name) ? texte.nichtUnterstuetzt :
        e.message);   // our server's own messages are already user-facing

    async function rufe(aktion, daten = {}) {
        const r = await fetch("/team/webauthn.php?aktion=" + aktion, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(daten), credentials: "same-origin" });
        const j = await r.json().catch(() => ({ fehler: "HTTP " + r.status }));
        if (j.fehler) { const e = new Error(j.fehler); e.antwort = j; throw e; }
        return j;
    }

    function binaer(o) {
        const p = o.publicKey;
        p.challenge = b2a(p.challenge);
        if (p.user) p.user.id = b2a(p.user.id);
        for (const l of [p.allowCredentials, p.excludeCredentials]) for (const c of l || []) c.id = b2a(c.id);
        // A fresh object on every access: KeePassXC-Browser (1.10, Firefox) overwrites prf.eval.first in our options
        // with base64 text and then hands the same options to the browser as fallback, which rejects them.
        // Drop once keepassxreboot/keepassxc-browser#3176 is fixed (check: tests/e2e/firefox-keepassxc.mjs)
        const salz = p.extensions?.prf?.eval?.first;
        if (salz) Object.defineProperty(p, "extensions", { enumerable: true, get: () => ({ prf: { eval: { first: b2a(salz) } } }) });
        return o;
    }

    function antwortDaten(c) {
        const r = c.response, d = { id: a2b(c.rawId), clientDataJSON: a2b(r.clientDataJSON) };
        if (r.attestationObject) d.attestationObject = a2b(r.attestationObject);
        if (r.authenticatorData) { d.authenticatorData = a2b(r.authenticatorData); d.signature = a2b(r.signature); }
        const prf = c.getClientExtensionResults?.().prf;
        if (prf?.results?.first) d.prf = a2b(prf.results.first);
        return d;
    }

    async function anmelden(optionen, pruefen, lage) {
        try {
            const o = binaer((await rufe(optionen)).optionen);
            const c = await navigator.credentials.get(o);
            location.href = (await rufe(pruefen, antwortDaten(c))).weiter;
        } catch (e) { browserFehler(e, lage); }
    }

    async function neu(art) {
        const name = document.getElementById(art === "2fa" ? "neu-name-schluessel" : "neu-name")?.value || "";
        try {
            const j = await rufe("neu_optionen", { art });
            const salz = j.optionen.publicKey.extensions?.prf?.eval?.first;
            const o = binaer(j.optionen);
            const c = await navigator.credentials.create(o);
            const d = Object.assign(antwortDaten(c), { art, name });
            if (art === "passkey" && !d.prf) {
                // Many authenticators give the PRF value only on use, not on creation: ask once more
                if (c.getClientExtensionResults?.().prf?.enabled) {
                    const g = await navigator.credentials.get(binaer({ publicKey: { challenge: a2b(crypto.getRandomValues(new Uint8Array(32))),
                        allowCredentials: [{ type: "public-key", id: a2b(c.rawId) }], userVerification: "required",
                        extensions: { prf: { eval: { first: salz } } } } }));
                    const p = g.getClientExtensionResults?.().prf;
                    if (p?.results?.first) d.prf = a2b(p.results.first);
                }
                if (!d.prf) {
                    // Passkey cannot unlock the key: explain, offer it as second factor
                    meldung(texte.ohnePrf || "");
                    const k = document.getElementById("als-2fa");
                    if (k) { k.hidden = false; k.onclick = async () => { try { location.href = (await rufe("neu_speichern", Object.assign(d, { art: "2fa" }))).weiter; } catch (e) { meldung(e.message); } }; }
                    return;
                }
            }
            location.href = (await rufe("neu_speichern", d)).weiter;
        } catch (e) { browserFehler(e, "Neu"); }
    }

    if (!window.PublicKeyCredential) { meldung(texte.keinWebauthn || ""); return; }
    const k = id => document.getElementById(id);
    document.querySelectorAll("#passkey-reihe, .neu-reihe").forEach(e => e.hidden = false);   // without JS these stay hidden
    k("passkey-login")?.addEventListener("click", () => anmelden("login_optionen", "login_pruefen", "Login"));
    // Second factor starts at once; the button only appears for another try after a failure or cancel
    const s = k("schluessel-2fa");
    if (s) { const los = () => anmelden("2fa_optionen", "2fa_pruefen", "Schluessel").then(() => s.hidden = false); s.addEventListener("click", los); los(); }
    k("neu-passkey")?.addEventListener("click", () => neu("passkey"));
    k("neu-schluessel")?.addEventListener("click", () => neu("2fa"));
})();
