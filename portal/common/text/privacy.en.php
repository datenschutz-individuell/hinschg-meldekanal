<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Privacy Notice</h1>

<p>Use this portal to report breaches of the law committed by employees of <?=$firma?> or by anyone acting on their behalf. The reporting office reviews your report. Where necessary, it consults other internal or external experts, or refers the breach to the competent authority.</p>

<p>This portal is a reporting channel under the German <a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">Whistleblower Protection Act</a> (HinSchG). This notice explains how we handle your personal data: the data you enter in the portal and the data that arise from your report.</p>

<p>We process these data because the HinSchG obliges us to operate a reporting office and to handle reports. We process particularly sensitive data, such as health data, only where this is necessary for that purpose. We may disclose data only where this Act permits it.</p>

<h2>What happens to your data</h2>

<details class="dse"><summary><h3>Visiting the portal</h3></summary>
<p><?=operation_sentence()?> The web server does not store IP addresses. Its log is used for troubleshooting only. We delete it after two months at the latest. <?=privacy_text("statistik")?></p>
<p>The portal uses no trackers and no third-party services. Reporting sets no cookies.<?php if (portal_anmeldung_aktiv()) { ?> Only when the team logs in does the portal set two technically necessary cookies for the login. They end at logout or when you close the browser.<?php } ?> We use what you enter only to handle your report under the HinSchG.</p>
</details>

<details class="dse"><summary><h3>Reporting and access code</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>This portal only accepts reports with your name and contact details, for example an email address or phone number. This lets us reach you if we have questions. You are responsible for these details being correct. You can report without your name to an external reporting office (see below).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Reports in this portal are anonymous. There are no fields for your name or contact details. Do you give them anyway, for example in a message? Then we use them only to clarify questions. Otherwise we can reach you only through the portal.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Your name and contact details are voluntary, for example an email address or phone number. With them we can reach you directly if we have questions. You are responsible for them being correct. Without them we can reach you only through the portal.</p>
<?php
}
?>
<p>After submitting, you receive an access code (“token”). It is shown only once. With it you can read your report at any time, including our questions and answers. Until the case is closed, you can write to the team. You can invalidate the access code at any time. If it is lost and you gave no contact details, we cannot reply to you.</p>
<p>Are you worried about disadvantages if you report to us? Then you can also contact an external reporting office, where you can also report without your name: the <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">Federal external reporting office at the Federal Office of Justice (website in German)</a>; for financial services, <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">BaFin's whistleblower office (website in German)</a>; for cartel infringements, the <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Federal Cartel Office (website in German)</a>; for data protection breaches, the <a href="#aufsicht">supervisory authority (see below)</a>.</p>
</details>

<details class="dse"><summary><h3>Handling</h3></summary>
<p>At first, only the team of the internal reporting office reads your report. For new reports and messages, the portal sends the team an email. It contains only the report ID<?=setting("Thema_in_Mail") !== "nein" ? " and the topic" : ""?>, not the content.</p>
<p><?=privacy_text("speicher")?> The team uses them to handle the report. It processes other personal data only where this is necessary for that purpose.</p>
<p>While investigating, the team may also keep documents, for example files, photos or emails. Some you send us, others the team finds itself. These documents are not kept in the portal but in a protected storage of the reporting office. The same applies to them: only the team has access, and we delete them together with the report.</p>
</details>

<details class="dse"><summary><h3>Disclosure</h3></summary>
<p>We disclose information about you only</p>
<ul>
<li>where this is necessary for handling the report,</li>
<li>where you have given your explicit consent, or</li>
<li>where you have reported false information intentionally or through gross negligence.</li>
</ul>
<p>We let you know beforehand, unless this would jeopardise investigations or court proceedings. Persons concerned by your report do not learn who made it. Your protection takes precedence over their right to information and access.</p>
<p>Are you named in a report? Then we inform you about your data as soon as this no longer jeopardises the investigation.</p>
</details>

<details class="dse"><summary><h3>Storage period</h3></summary>
<p>As long as we must keep a report, the team can see all information about it. With the access code, you can see it too. The team deletes the report three years after the case is closed, later only where a law requires it. <?=privacy_text("sicherung")?></p>
</details>

<h2>Your rights and contact</h2>

<p>You have these rights: access to your data, rectification, erasure, restriction of processing, and lodging a complaint with a supervisory authority. These rights may be restricted where necessary to protect reporting persons and persons concerned or to avoid jeopardising the investigation. We can erase data only after the statutory retention period has ended.</p>

<p>The controller responsible for processing your personal data is</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Is anything unclear, or do you have questions about your data? Then write to us: <?=mail_verbergen(setting("DS-Mail"))?>. These emails go to our data protection team, which includes our data protection officer. We will get back to you quickly.</p>

<p id="aufsicht">You can also lodge a complaint directly with <a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">a data protection supervisory authority</a> (website in German). The authority responsible for us is</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Courtesy translation – the <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">German version</a> is binding.</p>
