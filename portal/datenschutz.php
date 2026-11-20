<?php
/*
 * SPDX-FileCopyrightText: 2026 Olav Seyfarth, Contributors <https://github.com/datenschutz-individuell/hinschg-meldekanal/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: LicenseRef-hinschg-meldekanal
 */
include_once(__DIR__."/common/helper.php");
include_once(__DIR__."/common/header.php");
include(__DIR__."/common/text/privacy.".lang().".php");
echo sources_html();
include_once(__DIR__."/common/footer.php");
