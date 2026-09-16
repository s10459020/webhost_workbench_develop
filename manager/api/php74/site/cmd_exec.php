<?php
declare(strict_types=1);
$isWin = (stripos(PHP_OS_FAMILY, 'Windows') === 0);
require_once __DIR__ . ('/../../' . ($isWin ? 'osWin' : 'osLinux') . '/site/cmd_exec.php');
