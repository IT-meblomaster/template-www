<?php
declare(strict_types=1);

$flashMessages = get_flash_messages();
$currentPage = current_page();
$menuTree = get_visible_menu_tree($pdo);

function render_menu_tree(array $items, string $currentPage, int $level = 0): void
{
    foreach ($items as $item) {
        $slug = (string) ($item['slug'] ?? '');
        $title = (string) ($item['title'] ?? '');
        $children = $item['children'] ?? [];
        $hasChildren = is_array($children) && $children !== [];

        $isActive = $currentPage === $slug;
        if (!$isActive && $hasChildren) {
            foreach ($children as $child) {
                $childSlug = (string) ($child['slug'] ?? '');
                $grandChildren = $child['children'] ?? [];

                if ($currentPage === $childSlug) {
                    $isActive = true;
                    break;
                }

                if (is_array($grandChildren)) {
                    foreach ($grandChildren as $grandChild) {
                        if ($currentPage === (string) ($grandChild['slug'] ?? '')) {
                            $isActive = true;
                            break 2;
                        }
                    }
                }
            }
        }

        if ($level === 0) {
            if ($hasChildren) {
                ?>
                <li class="nav-item dropdown">
                    <a
                        class="nav-link dropdown-toggle <?= $isActive ? 'active' : '' ?>"
                        href="#"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <?= e($title) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <?php render_menu_tree($children, $currentPage, 1); ?>
                    </ul>
                </li>
                <?php
            } else {
                ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="index.php?page=<?= e($slug) ?>">
                        <?= e($title) ?>
                    </a>
                </li>
                <?php
            }
            continue;
        }

        if ($hasChildren) {
            ?>
            <li class="dropdown-submenu">
                <a
                    class="dropdown-item dropdown-toggle <?= $isActive ? 'active' : '' ?>"
                    href="#"
                    onclick="return false;"
                >
                    <?= e($title) ?>
                </a>
                <ul class="dropdown-menu">
                    <?php render_menu_tree($children, $currentPage, $level + 1); ?>
                </ul>
            </li>
            <?php
        } else {
            ?>
            <li>
                <a class="dropdown-item <?= $isActive ? 'active' : '' ?>" href="index.php?page=<?= e($slug) ?>">
                    <?= e($title) ?>
                </a>
            </li>
            <?php
        }
    }
}
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
                <?php render_menu_tree($menuTree, $currentPage); ?>
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