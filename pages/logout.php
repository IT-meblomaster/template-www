<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php?page=login');
}

if (!verify_csrf()) {
    redirect('index.php?page=dashboard');
}

logout();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

redirect('index.php?page=login');