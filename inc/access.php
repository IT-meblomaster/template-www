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

function get_page_by_slug(PDO $pdo, string $pageSlug): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, parent_id, slug, title, is_public, menu_visible, sort_order
        FROM pages
        WHERE slug = :slug
        LIMIT 1
    ");
    $stmt->execute(['slug' => $pageSlug]);
    $page = $stmt->fetch();

    return $page ?: null;
}

function is_page_public(PDO $pdo, string $pageSlug): bool
{
    $page = get_page_by_slug($pdo, $pageSlug);

    if (!$page) {
        return false;
    }

    return (int) $page['is_public'] === 1;
}

function page_has_children(PDO $pdo, int $pageId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pages
        WHERE parent_id = :parent_id
    ");
    $stmt->execute(['parent_id' => $pageId]);

    return (int) $stmt->fetchColumn() > 0;
}

function is_menu_container(PDO $pdo, string $pageSlug): bool
{
    $page = get_page_by_slug($pdo, $pageSlug);

    if (!$page) {
        return false;
    }

    return page_has_children($pdo, (int) $page['id']);
}

function can_access_page(PDO $pdo, string $pageSlug): bool
{
    $page = get_page_by_slug($pdo, $pageSlug);

    if (!$page) {
        return false;
    }

    if ((int) $page['is_public'] === 1) {
        return true;
    }

    if (page_has_children($pdo, (int) $page['id'])) {
        return false;
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
        ORDER BY
            CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END,
            parent_id,
            sort_order,
            title
    ");

    return $stmt->fetchAll();
}

function get_visible_menu_tree(PDO $pdo): array
{
    $pages = get_menu_pages($pdo);

    $indexed = [];
    foreach ($pages as $page) {
        $page['children'] = [];
        $indexed[(int) $page['id']] = $page;
    }

    foreach ($indexed as $id => $page) {
        $slug = (string) $page['slug'];
        $isContainer = false;

        foreach ($indexed as $candidate) {
            $candidateParentId = $candidate['parent_id'] !== null ? (int) $candidate['parent_id'] : null;
            if ($candidateParentId === $id) {
                $isContainer = true;
                break;
            }
        }

        if ($isContainer) {
            continue;
        }

        if (!can_access_page($pdo, $slug)) {
            unset($indexed[$id]);
        }
    }

    foreach ($indexed as $id => $page) {
        $parentId = $page['parent_id'] !== null ? (int) $page['parent_id'] : null;

        if ($parentId !== null && isset($indexed[$parentId])) {
            $indexed[$parentId]['children'][] = &$indexed[$id];
        }
    }

    $tree = [];

    foreach ($indexed as $id => $page) {
        $parentId = $page['parent_id'] !== null ? (int) $page['parent_id'] : null;

        if ($parentId === null) {
            $tree[] = &$indexed[$id];
        }
    }

    $tree = array_values(array_filter($tree, static function (array $item): bool {
        if (!empty($item['children'])) {
            return true;
        }

        return true;
    }));

    return $tree;
}