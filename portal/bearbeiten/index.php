<?php
/*
 * SPDX-FileCopyrightText: 2023 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
include_once(__DIR__."/../common/header.php");
include_once(__DIR__."/../common/case.php");


function is_token_valid($token): bool {
    if(strlen($token) != 24) {
        echo warnkasten(t("token_laenge", $token));
        return false;
    }

    $case_id = get_case_id_from_token($token);

    $case_data = load_case_data($case_id);
    if (empty($case_data)) {
        echo warnkasten(t("token_id", $case_id));
        return false;
    }

    // Encrypted cases store only a verifier; the secret part also unlocks the data key
    $gueltig = ist_verschluesselt($case_data) ? fallschluessel_token($case_data, substr($token, CASE_ID_LENGTH)) !== null
        : hash_equals((string)($case_data["token"] ?? ""), (string)$token);
    if (!$gueltig) {
        echo warnkasten(t("token_secret"));
        return false;
    }

    return true;
}


// MAIN

if(isset($_POST['token'])) {
    $token = clean_token($_POST['token']);
    if (is_token_valid($token)) {
        $case_id = get_case_id_from_token($token);
        display_case($case_id, false, substr($token, CASE_ID_LENGTH));
    }
}
else {
    ?>

    <h1><?=t("einsehen_titel")?></h1>

    <form action="" method="post">
        <label>
            <span class="p-like"><?=t("einsehen_text")?></span>
            <input class="volle-breite viel-abstand" type="text" name="token" required placeholder="<?=t("einsehen_platz")?>">
        </label>
        <button class="volle-breite abstand" type="submit"><?=symbol("zugangscode")?><?=t("einsehen_absenden")?></button>
    </form>

    <?php
}
?>

<?php
include_once(__DIR__."/../common/footer.php");
?>
