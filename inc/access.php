<?php
declare(strict_types=1);

function get_user_permissions(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.name
        FROM permissions p
        INNER JOIN role_permissions rp ON rp.permission_id = p.id
        INNER JOIN user_roles ur ON ur.role_id = rp.role_id
        WHERE ur.user_id = :user_id
        ORDER BY p.name
    ");
    $stmt->execute(['user_id' => $userId]);

    return array_column($stmt->fetchAll(), 'name');
}

function has_permission(PDO $pdo, string $permission): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $userId = current_user_id();
    if (!$userId) {
        return false;
    }

    static $cache = [];

    if (!isset($cache[$userId])) {
        $cache[$userId] = get_user_permissions($pdo, $userId);
    }

    return in_array($permission, $cache[$userId], true);
}

function get_page_permissions(PDO $pdo, string $pageSlug): array
{
    $stmt = $pdo->prepare("
        SELECT p.name
        FROM page_permissions pp
        INNER JOIN pages pg ON pg.id = pp.page_id
        INNER JOIN permissions p ON p.id = pp.permission_id
        WHERE pg.slug = :slug
        ORDER BY p.name
    ");
    $stmt->execute(['slug' => $pageSlug]);

    return array_column($stmt->fetchAll(), 'name');
}

function is_page_public(PDO $pdo, string $pageSlug): bool
{
    $stmt = $pdo->prepare("
        SELECT is_public
        FROM pages
        WHERE slug = :slug
        LIMIT 1
    ");
    $stmt->execute(['slug' => $pageSlug]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    return (int) $row['is_public'] === 1;
}

function can_access_page(PDO $pdo, string $pageSlug): bool
{
    if (is_page_public($pdo, $pageSlug)) {
        return true;
    }

    if (!is_logged_in()) {
        return false;
    }

    $requiredPermissions = get_page_permissions($pdo, $pageSlug);

    if (!$requiredPermissions) {
        return false;
    }

    foreach ($requiredPermissions as $permission) {
        if (has_permission($pdo, $permission)) {
            return true;
        }
    }

    return false;
}

function get_menu_pages(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            id,
            slug,
            title,
            parent_id,
            is_public,
            menu_visible,
            sort_order
        FROM pages
        WHERE menu_visible = 1
        ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC, title ASC
    ");

    return $stmt->fetchAll();
}

function get_visible_menu_tree(PDO $pdo): array
{
    $pages = get_menu_pages($pdo);

    $indexed = [];
    $tree = [];

    foreach ($pages as $page) {
        $page['children'] = [];
        $indexed[(int) $page['id']] = $page;
    }

    foreach ($indexed as $id => $page) {
        $slug = (string) $page['slug'];
        $hasChildren = false;

        foreach ($indexed as $candidate) {
            if ((int) ($candidate['parent_id'] ?? 0) === $id) {
                $hasChildren = true;
                break;
            }
        }

        $visible = $hasChildren ? true : can_access_page($pdo, $slug);

        if (!$visible) {
            unset($indexed[$id]);
        }
    }

    foreach ($indexed as $id => $page) {
        $parentId = $page['parent_id'] !== null ? (int) $page['parent_id'] : null;

        if ($parentId !== null && isset($indexed[$parentId])) {
            $indexed[$parentId]['children'][] = &$indexed[$id];
        } else {
            $tree[] = &$indexed[$id];
        }
    }

    return $tree;
}