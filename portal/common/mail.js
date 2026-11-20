/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Makes the hidden mail addresses (helper.php mail_verbergen) clickable only once someone hovers or clicks.
for (const e of ["mouseover", "focusin", "touchstart", "click"]) {
    document.addEventListener(e, (ev) => {
        const a = ev.target.closest && ev.target.closest("a.mail[data-m]");
        if (a && !a.href) a.href = "mailto:" + [...atob(a.dataset.m)].reverse().join("");
    }, { passive: true, capture: true });
}
