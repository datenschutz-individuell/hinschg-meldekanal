<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
// TOTP per RFC 6238 (SHA-1, 6 digits, 30 s) – what every authenticator app understands. One step of clock skew
// either way; a code used once is refused afterwards (last counter stored).

const B32 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

function base32(string $roh): string {
    $bits = "";
    foreach (str_split($roh) as $c) $bits .= str_pad(decbin(ord($c)), 8, "0", STR_PAD_LEFT);
    return implode("", array_map(fn($g) => B32[bindec(str_pad($g, 5, "0"))], str_split($bits, 5)));
}

function base32_roh(string $b32): string {
    $bits = "";
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', "", $b32))) as $c) $bits .= str_pad(decbin(strpos(B32, $c)), 5, "0", STR_PAD_LEFT);
    return implode("", array_map(fn($b) => chr(bindec($b)), array_filter(str_split($bits, 8), fn($b) => strlen($b) === 8)));
}

function totp_neu(): string {
    return base32(random_bytes(20));
}

function totp_code(string $geheim, int $zaehler): string {
    $h = hash_hmac("sha1", pack("J", $zaehler), base32_roh($geheim), true);
    $o = ord($h[19]) & 0x0f;
    $n = ((ord($h[$o]) & 0x7f) << 24 | ord($h[$o + 1]) << 16 | ord($h[$o + 2]) << 8 | ord($h[$o + 3])) % 1000000;
    return str_pad((string)$n, 6, "0", STR_PAD_LEFT);
}

/** Counter of the matching step, or null; $zuletzt: counter used last (replay). */
function totp_pruefen(string $geheim, string $code, int $zuletzt = -1, ?int $jetzt = null): ?int {
    $code = preg_replace('/\D/', "", $code);
    if (strlen($code) !== 6) return null;
    $z = intdiv($jetzt ?? time(), 30);
    foreach ([$z, $z - 1, $z + 1] as $c) if ($c > $zuletzt && hash_equals(totp_code($geheim, $c), $code)) return $c;
    return null;
}

function totp_uri(string $geheim, string $konto): string {
    $aussteller = setting("Domain");
    return "otpauth://totp/" . rawurlencode("$aussteller:$konto") . "?secret=$geheim&issuer=" . rawurlencode($aussteller);
}
