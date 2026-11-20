<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
    // Outputs the HTML attributes that enable the remaining-characters display.
    function remaining_signs_hint(): string {
        return "onkeyup=\"addRemainingSignsHint(this)\" onfocusout=\"removeRemainingSignsHint(this)\"";
    }
?>


<script>
    // Tries the extra classes in order until passt() holds (the last one stays regardless); again when the parent's width
    // changes or web fonts have loaded (other text widths)
    function stufen(el, klassen, passt) {
        const basis = el.className; let breite;
        const waehlen = () => { for (const k of klassen) { el.className = basis + " " + k; if (passt()) return; } };
        new ResizeObserver(([e]) => { if (e.contentRect.width === breite) return; breite = e.contentRect.width; waehlen(); }).observe(el.parentElement);
        document.fonts?.ready.then(waehlen);
    }

    // Start page buttons and back/forward pairs side by side in equal columns; stacked if a label would not fit on one line (style.php)
    document.addEventListener("DOMContentLoaded", () => document.querySelectorAll(".start-knoepfe, .knopfpaar").forEach(c => {
        if (c.children.length > 1) stufen(c, ["gleich", "gleich gestapelt"], () => ![...c.querySelectorAll("button")].some(b => b.scrollWidth > b.clientWidth));
    }));
    // Start page tiles: line break at the "|" points of the texts, for all tiles or none (no extra breaks)
    document.addEventListener("DOMContentLoaded", () => document.querySelectorAll(".schritte").forEach(s =>
        stufen(s, ["zweizeilig", ""], () => ![...s.querySelectorAll(".zeile")].some(z => z.scrollWidth > z.clientWidth))));

    function addRemainingSignsHint(element) {
        let maxTextLength = element.getAttribute("maxlength");
        let textLength = element.value.length;
        let remainingSignsString = String(maxTextLength - textLength);
        let inputHintElement = element.nextSibling;
        if (!inputHintElement || inputHintElement.className !== "hinweis-eingabe") {
            inputHintElement = document.createElement("div");
            // Constant style attributes live in the hinweis-eingabe class in style.css
            inputHintElement.className = "hinweis-eingabe";
            inputHintElement.setAttribute("aria-live", "polite");   // screen readers hear the remaining count
            // JS has no insertAfter; the following if-else emulates it
            if (element.nextSibling) {
                // If the element has a next sibling, insert the new element before it
                element.parentElement.insertBefore(inputHintElement, element.nextSibling);
            }
            else {
                // No next sibling means it is the last one, so append the new element
                element.parentElement.appendChild(inputHintElement);
            }
            let inputHintElementChild = document.createElement("span");
            inputHintElement.appendChild(inputHintElementChild);
        }
        let x = element.offsetLeft;
        let y = element.offsetTop + element.offsetHeight;
        let width = element.offsetWidth;
        // Only context-dependent style attributes (positioning) are set here, all others in style.css
        // Position the remaining-characters display directly below the element
        inputHintElement.setAttribute("style", "left:" + x + "px; top:" + y + "px; width:" + width + "px;");
        inputHintElement.firstChild.innerHTML = <?=json_encode(t("zeichen_rest"))?> + remainingSignsString;
    }

    function removeRemainingSignsHint(element) {
        let inputHintElement = element.nextSibling;
        if (inputHintElement && inputHintElement.className === "hinweis-eingabe") {
            inputHintElement.remove();
        }
    }

    // Add functionality to send form on CONTROL + ENTER
    document.body.addEventListener('keydown', (event) => {
        if(event.key === "Enter" && (event.metaKey || event.ctrlKey)) {
            event.target.form?.requestSubmit()
        }
    });

</script>
