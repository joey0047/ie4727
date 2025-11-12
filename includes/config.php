<?php
/**
 * CONFIG.PHP
 * Auto-detects the correct base URL including nested folders.
 * Works for:
 *   http://localhost/project/
 *   http://127.0.0.1/IE4727/ie4727/
 *   http://domain.com/app/
 */

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ? "https://" : "http://";

$host = $_SERVER['HTTP_HOST'];

// directory of the current project
$projectDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');

// build final BASE_URL
define('BASE_URL', $protocol . $host . $projectDir);