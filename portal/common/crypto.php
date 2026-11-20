<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// Encrypted reports. Each case has its own data key; title and message texts are encrypted with it,
// everything the deadline watcher needs (ID, status, dates, who wrote, actions) stays readable. The data key is
// stored wrapped in "keys":
//   melder     AES-GCM, key derived from the token secret (16 random chars ≈ 95 bits, so HKDF suffices)
//   u:<fp>     ECIES to a team member's public key, named by its fingerprint (a new key pair after a password
//              reset thus never matches an old wrapping)
//   notfall    ECIES to the operator's emergency key (<wurzel>/emergency.pub; private part kept offline)
// The token itself is no longer stored, only a verifier derived from it in another context.
// Plain cases (without "keys") stay readable, so portals can be converted one by one: the first login of an authorised
// member encrypts them (auth.php verpackungen_ergaenzen).
// Algorithms follow BSI TR-02102-1 (2026-01), reasons in SECURITY.md: AES-256-GCM, ECIES on brainpoolP384r1 with
// HKDF-SHA256, Argon2id (auth.php). "crypto" in a case names this set, so a later change can find old cases.

const KRYPTO = "tr02102-2026";          // stored as "crypto" in encrypted cases
const SCHLUESSEL_LAENGE = 32;           // AES-256
const KURVE = "brainpoolP384r1";
const NOTFALL_DATEI = "emergency.pub";  // emergency public key (SubjectPublicKeyInfo DER, base64), in the folder of all portals

/** AES-256-GCM, random 96-bit IV, 128-bit tag. Stored form: base64(IV | tag | ciphertext). */
function sym_verschluesseln(string $schluessel, string $klartext, string $kontext = ""): string {
    $iv = random_bytes(12);
    $c = openssl_encrypt($klartext, "aes-256-gcm", $schluessel, OPENSSL_RAW_DATA, $iv, $tag, $kontext, 16);
    return base64_encode($iv . $tag . $c);
}

function sym_entschluesseln(string $schluessel, $b64, string $kontext = ""): ?string {
    $roh = is_string($b64) ? base64_decode($b64, true) : false;
    if ($roh === false || strlen($roh) < 28) return null;
    $k = openssl_decrypt(substr($roh, 28), "aes-256-gcm", $schluessel, OPENSSL_RAW_DATA, substr($roh, 0, 12), substr($roh, 12, 16), $kontext);
    return $k === false ? null : $k;
}

function pem_oeffentlich(string $der): string {
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Public key (DER) of a key or PEM private key, or null. */
function oeffentlich($schluessel): ?string {
    $k = is_string($schluessel) ? openssl_pkey_get_private($schluessel) : $schluessel;
    $pem = $k ? (openssl_pkey_get_details($k)["key"] ?? null) : null;
    return $pem ? base64_decode(preg_replace('/-----[^-]+-----|\s/', "", $pem), true) : null;
}

/** New key pair: the private key as PEM (it contains the public one). */
function schluesselpaar_neu(): string {
    openssl_pkey_export(openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => KURVE]), $pem);
    return $pem;
}

/** ECIES (TR-02102-1 2.3.4): ephemeral ECDH, HKDF-SHA256 over the shared secret bound to both public keys,
 *  AES-256-GCM (its tag is the MAC). Stored form: base64(length of B (2 bytes) | B | AES-GCM). */
function versiegeln(string $oeffentlich, string $klartext): string {
    $e = openssl_pkey_new(["private_key_type" => OPENSSL_KEYTYPE_EC, "curve_name" => KURVE]);
    $b = oeffentlich($e);
    $k = hash_hkdf("sha256", openssl_pkey_derive(openssl_pkey_get_public(pem_oeffentlich($oeffentlich)), $e), SCHLUESSEL_LAENGE, "hinweise-ecies|" . $b . $oeffentlich);
    return base64_encode(pack("n", strlen($b)) . $b . base64_decode(sym_verschluesseln($k, $klartext)));
}

function entsiegeln(string $schluesselpaar, $b64): ?string {
    $roh = is_string($b64) ? base64_decode($b64, true) : false;
    if ($roh === false || strlen($roh) < 2) return null;
    $n = unpack("n", $roh)[1];
    $b = substr($roh, 2, $n);
    $privat = openssl_pkey_get_private($schluesselpaar);
    $gegen = openssl_pkey_get_public(pem_oeffentlich($b));
    $x = $privat && $gegen ? openssl_pkey_derive($gegen, $privat) : false;
    if (!$x) return null;
    $k = hash_hkdf("sha256", $x, SCHLUESSEL_LAENGE, "hinweise-ecies|" . $b . oeffentlich($privat));
    return sym_entschluesseln($k, base64_encode(substr($roh, 2 + $n)));
}

function ist_verschluesselt(array $m): bool {
    return isset($m["keys"]);
}

/** Token secret → [wrapping key, verifier]: HKDF-SHA256 in two contexts; the salt makes equal secrets differ between cases. */
function token_ableitung(string $geheim, string $salz): array {
    return [hash_hkdf("sha256", $geheim, SCHLUESSEL_LAENGE, "hinweise-wrap", $salz), base64_encode(hash_hkdf("sha256", $geheim, 32, "hinweise-check", $salz))];
}

/** Emergency public key (DER) of this operator, or null (then cases lack the emergency wrapping: error_log). */
function notfall_schluessel(string $wurzel): ?string {
    static $je = [], $gemeldet = [];   // a found key is read once per request; a missing one is logged once
    if (isset($je[$wurzel])) return $je[$wurzel];
    $k = base64_decode(trim(preg_replace('/^#.*$/m', "", (string)@file_get_contents("$wurzel/" . NOTFALL_DATEI))), true);   // "#" lines: header
    if ($k && kurve_passt($k)) return $je[$wurzel] = $k;
    if (!isset($gemeldet[$wurzel])) error_log("Notfallschlüssel fehlt oder ungültig: $wurzel/" . NOTFALL_DATEI);
    $gemeldet[$wurzel] = true;
    return null;
}

/** Public key (DER) on our curve: versiegeln() fails on any other. */
function kurve_passt(string $pub): bool {
    $k = openssl_pkey_get_public(pem_oeffentlich($pub));
    return $k && (openssl_pkey_get_details($k)["ec"]["curve_name"] ?? "") === KURVE;
}

function schluessel_fp(string $pub): string {
    return substr(hash("sha256", $pub), 0, 16);
}

/** Encrypts a new or plain case. $empfaenger: [fingerprint => public key]. Returns the stored form. */
function fall_verschluesseln(array $m, string $k, string $token_geheim, array $empfaenger, ?string $notfall): array {
    $salz = random_bytes(32);
    [$wrap, $pruef] = token_ableitung($token_geheim, $salz);
    $m["crypto"] = KRYPTO;
    $m["salt"] = base64_encode($salz);
    $m["verifier"] = $pruef;
    $m["keys"] = ["melder" => sym_verschluesseln($wrap, $k)];
    foreach ($empfaenger as $id => $pub) $m["keys"]["u:$id"] = versiegeln($pub, $k);
    if ($notfall) $m = notfall_verpacken($m, $notfall, $k);
    $m["title"] = sym_verschluesseln($k, (string)$m["title"]);
    foreach ($m["conversation"] as $i => $e) if (isset($e["text"])) $m["conversation"][$i]["text"] = sym_verschluesseln($k, (string)$e["text"]);
    unset($m["token"]);
    return $m;
}

/** Emergency wrapping with the fingerprint of its key: the rekey tells done cases by notfall_fp. */
function notfall_verpacken(array $m, string $notfall, string $k): array {
    $m["keys"]["notfall"] = versiegeln($notfall, $k);
    $m["notfall_fp"] = schluessel_fp($notfall);
    return $m;
}

/** Data key for the reporting person, or null if the token does not fit (or was invalidated). */
function fallschluessel_token(array $m, string $token_geheim): ?string {
    $salz = base64_decode((string)($m["salt"] ?? ""), true);
    if (!$salz || !isset($m["keys"]["melder"], $m["verifier"])) return null;
    [$wrap, $pruef] = token_ableitung($token_geheim, $salz);
    if (!hash_equals((string)$m["verifier"], $pruef)) return null;
    return sym_entschluesseln($wrap, $m["keys"]["melder"]);
}

/** Data key for a member from their key pair, or null if not wrapped for them (yet). */
function fallschluessel_mitglied(array $m, string $schluesselpaar): ?string {
    $fp = schluessel_fp((string)oeffentlich($schluesselpaar));
    return isset($m["keys"]["u:$fp"]) ? entsiegeln($schluesselpaar, $m["keys"]["u:$fp"]) : null;
}

/** Readable copy for display. Plain cases unchanged; a text that does not decrypt becomes null. */
function fall_klartext(array $m, ?string $k): array {
    if (!ist_verschluesselt($m)) return $m;
    $m["title"] = $k ? sym_entschluesseln($k, $m["title"] ?? null) : null;
    foreach ($m["conversation"] ?? [] as $i => $e) if (isset($e["text"])) $m["conversation"][$i]["text"] = $k ? sym_entschluesseln($k, $e["text"]) : null;
    return $m;
}

/** Text as stored: encrypted if the case is. */
function text_speichern(array $m, ?string $k, string $text): string {
    return ist_verschluesselt($m) ? sym_verschluesseln($k, $text) : $text;
}
