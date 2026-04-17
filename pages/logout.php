<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Nieprawidłowe żądanie wylogowania.');
    redirect('index.php?page=dashboard');
}

logout();
set_flash('success', 'Wylogowano poprawnie.');
redirect('index.php?page=home');