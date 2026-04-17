<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Nieprawidłowe żądanie wylogowania.');
    redirect('index.php?page=dashboard');
}

logout();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

set_flash('success', 'Wylogowano poprawnie.');
redirect('index.php?page=login');