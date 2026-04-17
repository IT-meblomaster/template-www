<?php

declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/permissions.php';
require __DIR__ . '/inc/menu.php';

start_session($config);

$page = current_page();

$publicPages = ['home', 'login', 'forbidden'];

if ($page === 'logout') {
    require __DIR__ . '/pages/logout.php';
    exit;
}

if (!in_array($page, $publicPages, true) && !is_logged_in()) {
    set_flash('warning', 'Zaloguj się, aby kontynuować.');
    redirect('index.php?page=login');
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';

if (!is_file($pageFile)) {
    http_response_code(404);
    $page = 'home';
    $pageFile = __DIR__ . '/pages/home.php';
}

require __DIR__ . '/pages/header.php';
require $pageFile;
require __DIR__ . '/pages/footer.php';