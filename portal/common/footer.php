<?php /*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<!-- start of footer.php -->
</main>
<footer class="footer" id="impressum">
<hr class="footer">
<nav class="fuss-zeile">
<?php // Portal · company website · Team; without a website „Startseite“ · Team ?>
<span class="fuss-links"><span class="fuss-liste"><span class="fuss-teil"><a href="<?=url("/")?>"><?=t(setting("Webseite") ? "portal_startseite" : "startseite")?></a></span><?php if (setting("Webseite")) { ?><span class="fuss-teil"><a href="<?=setting_l("Webseite")?>" target="extern" rel="noopener noreferrer"><?=t("webseite")?></a></span><?php } ?><span class="fuss-teil"><a href="<?=url("/team/")?>"><?=t("team")?></a></span></span></span>
<?php // Language switch centred; the column stays even when empty ?>
<span class="fuss-mitte"><?=sprachumschalter()?></span>
<span class="fuss-rechts"><span class="fuss-liste"><?php if (setting("Sicherheit")) { ?><span class="fuss-teil"><a href="<?=setting_l("Sicherheit")?>"><?=t("sicherheit")?></a></span><?php } ?><span class="fuss-teil"><a href="<?=url("/datenschutz.php")?>"><?=t("datenschutz")?></a></span><?php if (setting("Impressum")) { ?><span class="fuss-teil"><a href="<?=setting_l("Impressum")?>" target="extern" rel="noopener noreferrer"><?=t("impressum")?></a></span><?php } ?></span></span>
</nav></footer>
</div><script src="/common/mail.js" defer></script></body>
</html>
