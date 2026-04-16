<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';

$debugEnabled = !empty($config['debug']);

if ($debugEnabled) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/csrf.php';
require __DIR__ . '/inc/access.php';

start_session($config);
$pdo = db($config);

$page = current_page();
$pageFile = __DIR__ . '/pages/' . $page . '.php';

if (!is_file($pageFile)) {
    http_response_code(404);
    $page = 'forbidden';
    $pageFile = __DIR__ . '/pages/forbidden.php';
}

if (!can_access_page($pdo, $page)) {
    if (!is_logged_in()) {
        redirect('index.php?page=login');
    }

    http_response_code(403);
    $page = 'forbidden';
    $pageFile = __DIR__ . '/pages/forbidden.php';
}

require __DIR__ . '/pages/header.php';
require $pageFile;
require __DIR__ . '/pages/footer.php';