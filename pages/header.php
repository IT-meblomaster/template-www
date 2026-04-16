<?php
declare(strict_types=1);

$flashMessages = get_flash_messages();
$currentPage = current_page();

$canManageUsers = has_permission($pdo, 'users.view') || has_permission($pdo, 'users.manage');
$canManageRoles = has_permission($pdo, 'roles.view') || has_permission($pdo, 'roles.manage');
$canManagePermissions = has_permission($pdo, 'permissions.view') || has_permission($pdo, 'permissions.manage');

$showAccessMenu = $canManageUsers || $canManageRoles || $canManagePermissions;
$settingsActive = in_array($currentPage, ['users', 'user_form', 'roles', 'permissions'], true);
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app']['name'] ?? 'Template') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(($config['app']['base_url'] ?? '') . '/assets/css/style.css') ?>" rel="stylesheet">
    <style>
        @media (min-width: 992px) {
            .dropdown-menu .dropdown-submenu {
                position: relative;
            }

            .dropdown-menu .dropdown-submenu > .dropdown-menu {
                top: 0;
                left: 100%;
                margin-top: -1px;
                display: none;
            }

            .dropdown-menu .dropdown-submenu:hover > .dropdown-menu {
                display: block;
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php"><?= e($config['app']['name'] ?? 'Template') ?></a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" href="index.php?page=home">
                        Start
                    </a>
                </li>

                <?php if (can_access_page($pdo, 'dashboard')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="index.php?page=dashboard">
                            Dashboard
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($showAccessMenu): ?>
                    <li class="nav-item dropdown">
                        <a
                            class="nav-link dropdown-toggle <?= $settingsActive ? 'active' : '' ?>"
                            href="#"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Ustawienia
                        </a>

                        <ul class="dropdown-menu">
                            <li class="dropdown-submenu">
                                <a
                                    class="dropdown-item dropdown-toggle"
                                    href="#"
                                    onclick="return false;"
                                >
                                    Zarządzaj dostępem
                                </a>

                                <ul class="dropdown-menu">
                                    <?php if ($canManageUsers): ?>
                                        <li>
                                            <a class="dropdown-item <?= in_array($currentPage, ['users', 'user_form'], true) ? 'active' : '' ?>" href="index.php?page=users">
                                                Użytkownicy
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($canManageRoles): ?>
                                        <li>
                                            <a class="dropdown-item <?= $currentPage === 'roles' ? 'active' : '' ?>" href="index.php?page=roles">
                                                Role
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($canManagePermissions): ?>
                                        <li>
                                            <a class="dropdown-item <?= $currentPage === 'permissions' ? 'active' : '' ?>" href="index.php?page=permissions">
                                                Uprawnienia
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item me-2 d-flex align-items-center">
                        <span class="navbar-text">Zalogowano</span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-secondary btn-sm" href="index.php?page=logout">Wyloguj</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm" href="index.php?page=login">Zaloguj</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?php foreach ($flashMessages as $flash): ?>
        <?php
        $type = $flash['type'] ?? 'info';
        $message = $flash['message'] ?? '';
        ?>
        <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
            <?= e($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>