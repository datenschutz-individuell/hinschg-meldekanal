<?php /*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */ ?>
<?php defined("PORTAL_DIR") || exit; include(__DIR__."/customer.php"); ?>

<h1>Informations sur la protection des données</h1>

<p>Ici, vous pouvez signaler des violations de la loi. Il s'agit de violations commises par des membres du personnel de <?=$firma?> ou en son nom. Le service de signalement examine votre signalement. Si nécessaire, il consulte d'autres experts, internes ou externes. Ou il transmet la violation à l'autorité compétente.</p>

<p>Ce portail est un canal de signalement au sens de la <a href="https://www.gesetze-im-internet.de/englisch_hinschg/" target="extern" rel="noopener noreferrer">loi allemande sur la protection des lanceurs d'alerte</a> (HinSchG, traduction anglaise). Nous expliquons ici comment nous traitons vos données à caractère personnel. Il s'agit des données que vous saisissez sur le portail et des données qui naissent de votre signalement.</p>

<p>Nous traitons ces données parce que la HinSchG nous y oblige : nous devons exploiter un service de signalement et traiter les signalements. Nous ne traitons les données particulièrement protégées, par exemple sur la santé, que si c'est nécessaire pour cela. Nous ne pouvons transmettre des données que si cette loi le permet.</p>

<h2>Ce qu'il advient de vos données</h2>

<details class="dse"><summary><h3>Consultation du portail</h3></summary>
<p><?=operation_sentence()?> Le serveur web n'enregistre pas d'adresses IP. Son journal sert uniquement à rechercher des erreurs. Nous le supprimons au plus tard au bout de 2 mois. <?=privacy_text("statistik")?></p>
<p>Le portail n'utilise ni traceurs ni services de tiers. Les personnes qui signalent ne reçoivent pas de cookies.<?php if (portal_anmeldung_aktiv()) { ?> Uniquement lorsque l'équipe se connecte, le portail dépose deux cookies techniquement nécessaires pour la connexion. Ils prennent fin à la déconnexion ou à la fermeture du navigateur.<?php } ?> Nous utilisons vos saisies uniquement pour traiter votre signalement conformément à la HinSchG.</p>
</details>

<details class="dse"><summary><h3>Signalement et code d'accès</h3></summary>
<?php
$anon_level = setting("Level");
if ($anon_level == "erforderlich") {
?>
<!-- Name and contact details are mandatory -->
<p>Ce portail n'accepte que les signalements avec nom et coordonnées, par exemple adresse e-mail ou numéro de téléphone. Ainsi, nous pouvons vous joindre si nous avons des questions. Il vous appartient de veiller à ce que ces informations soient exactes. Vous pouvez signaler sans indiquer votre nom auprès d'un service de signalement externe (voir ci-dessous).</p>
<?php
} elseif ($anon_level == "anonym") {
?>
<!-- Name and contact details are not requested -->
<p>Sur ce portail, vous signalez de manière anonyme. Il n'y a pas de champs pour le nom et les coordonnées. Vous les indiquez quand même, par exemple dans un message ? Nous les utilisons alors uniquement pour clarifier des questions. Sinon, nous ne pouvons vous joindre que par le portail.</p>
<?php
} else { // $anon_level == "optional"
?>
<!-- Name and contact details are voluntary -->
<p>Le nom et les coordonnées sont facultatifs, par exemple adresse e-mail ou numéro de téléphone. Avec ces informations, nous pouvons vous joindre directement si nous avons des questions. Il vous appartient de veiller à ce qu'elles soient exactes. Sans elles, nous ne pouvons vous joindre que par le portail.</p>
<?php
}
?>
<p>Après l'envoi, vous recevez un code d'accès. Il ne s'affiche qu'une seule fois. Il vous permet de lire votre signalement à tout moment, avec nos questions et nos réponses. Tant que le dossier n'est pas clôturé, vous pouvez écrire à l'équipe. Vous pouvez invalider le code d'accès à tout moment. Vous l'avez perdu et n'avez pas indiqué de coordonnées ? Nous ne pouvons alors pas vous répondre.</p>
<p>Vous craignez un préjudice si vous signalez auprès de nous ? Vous pouvez aussi vous adresser à un service de signalement externe, y compris sans indiquer votre nom : au <a href="<?=$bfj?>" target="extern" rel="noopener noreferrer">service de signalement externe de la Fédération auprès de l'Office fédéral de la justice (site en allemand)</a>, pour les services financiers au <a href="https://www.bafin.de/DE/die-bafin/whistleblower/whistleblower_node" target="extern" rel="noopener noreferrer">service des lanceurs d'alerte de la BaFin (site en allemand)</a>, pour les infractions aux règles sur les ententes au <a href="https://www.bundeskartellamt.de/DE/Kartellverbot/Anonyme_Hinweise/anonymehinweise_node.html" target="extern" rel="noopener noreferrer">Bundeskartellamt (site en allemand)</a>, pour les violations de la protection des données à l'<a href="#aufsicht">autorité de contrôle (voir ci-dessous)</a>.</p>
</details>

<details class="dse"><summary><h3>Traitement</h3></summary>
<p>Dans un premier temps, seule l'équipe du service de signalement interne lit votre signalement. Pour chaque nouveau signalement et chaque nouveau message, le portail envoie un e-mail à l'équipe. Il contient uniquement l'identifiant du signalement<?=setting("Thema_in_Mail") !== "nein" ? " et le thème" : ""?>, pas le contenu.</p>
<p><?=privacy_text("speicher")?> L'équipe les utilise pour traiter le signalement. Elle ne traite d'autres données à caractère personnel que si c'est nécessaire pour cela.</p>
<p>Pour l'enquête, l'équipe peut aussi conserver des pièces, par exemple des documents, des photos ou des e-mails. Certaines nous sont envoyées par vous, d'autres sont trouvées par l'équipe elle-même. Ces pièces ne se trouvent pas sur le portail, mais dans un espace de stockage protégé du service de signalement. Les mêmes règles s'appliquent : seule l'équipe y a accès, et nous les supprimons en même temps que le signalement.</p>
</details>

<details class="dse"><summary><h3>Transmission</h3></summary>
<p>Nous ne transmettons des informations vous concernant que</p>
<ul>
<li>si c'est nécessaire au traitement,</li>
<li>si vous y avez expressément consenti, ou</li>
<li>si vous avez signalé de fausses informations délibérément ou par négligence grave.</li>
</ul>
<p>Nous vous en informons au préalable, sauf si cela compromettait une enquête ou une procédure judiciaire. Les personnes concernées par votre signalement n'apprennent pas qui l'a effectué. Votre protection prime sur leur droit à l'information et leur droit d'accès.</p>
<p>Vous êtes mentionné·e dans un signalement ? Nous vous informons alors sur vos données dès que cela ne compromet plus l'enquête.</p>
</details>

<details class="dse"><summary><h3>Durée de conservation</h3></summary>
<p>Tant que nous devons conserver un signalement, l'équipe peut voir toutes les informations qui s'y rapportent. Avec le code d'accès, vous pouvez aussi les voir. L'équipe supprime le signalement 3 ans après la clôture du dossier. Plus longtemps uniquement si une loi l'exige. <?=privacy_text("sicherung")?></p>
</details>

<h2>Vos droits et contact</h2>

<p>Vous disposez des droits suivants : accès à vos données, rectification, effacement, limitation du traitement et réclamation auprès d'une autorité de contrôle. Ces droits peuvent être limités. C'est le cas lorsque c'est nécessaire pour protéger les personnes qui signalent et les personnes concernées, ou pour ne pas compromettre l'enquête. Nous ne pouvons effacer les données qu'à la fin de la durée de conservation légale.</p>

<p>Le responsable du traitement de vos données à caractère personnel est :</p>

<p class="eingerueckt">
<?=htmlspecialchars(setting("Firma"))?><br>
<?=mail_verbergen(setting_l("Anschrift"))?></p>

<p>Quelque chose n'est pas clair, ou vous avez des questions sur vos données ? Écrivez-nous : <?=mail_verbergen(setting("DS-Mail"))?>. Ces e-mails parviennent à notre équipe chargée de la protection des données. Notre délégué·e à la protection des données en fait partie. Nous vous répondrons rapidement.</p>

<p id="aufsicht">Vous pouvez aussi introduire directement une réclamation auprès d'<a href="https://www.bfdi.bund.de/DE/Infothek/Anschriften_Links/anschriften_links-node.html" target="extern" rel="noopener noreferrer">une autorité de contrôle de la protection des données</a> (site en allemand). L'autorité compétente pour nous est :</p>

<?=aufsicht_html()?>

<p style="font-style:italic; white-space:nowrap; font-size:min(1em, 3.6vw); margin-top:2em;">Traduction de courtoisie – la <a href="<?=lang_url("de", "/datenschutz.php")?>" hreflang="de">version allemande</a> fait foi.</p>
