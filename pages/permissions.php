<?php
declare(strict_types=1);

if (!has_permission($pdo, 'permissions.view') && !has_permission($pdo, 'permissions.manage')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

$errors = [];
$editingPageId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission($pdo, 'permissions.manage')) {
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        return;
    }

    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save_page') {
        $pageId = (int)($_POST['page_id'] ?? 0);
        $slug = trim((string)($_POST['slug'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        $menuVisible = isset($_POST['menu_visible']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 100);
        $permissionIds = array_map('intval', $_POST['permissions'] ?? []);

        if ($slug === '') {
            $errors[] = 'Slug strony jest wymagany.';
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            $errors[] = 'Slug może zawierać tylko litery, cyfry, myślnik i podkreślenie.';
        }

        if ($title === '') {
            $errors[] = 'Tytuł strony jest wymagany.';
        }

        $stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = :slug AND id != :id LIMIT 1');
        $stmt->execute([
            'slug' => $slug,
            'id' => $pageId,
        ]);
        if ($stmt->fetch()) {
            $errors[] = 'Strona o takim slug już istnieje.';
        }

        if (!$errors) {
            $pdo->beginTransaction();

            try {
                if ($pageId > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE pages
                        SET slug = :slug,
                            title = :title,
                            is_public = :is_public,
                            menu_visible = :menu_visible,
                            sort_order = :sort_order
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'slug' => $slug,
                        'title' => $title,
                        'is_public' => $isPublic,
                        'menu_visible' => $menuVisible,
                        'sort_order' => $sortOrder,
                        'id' => $pageId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO pages (slug, title, is_public, menu_visible, sort_order)
                        VALUES (:slug, :title, :is_public, :menu_visible, :sort_order)
                    ');
                    $stmt->execute([
                        'slug' => $slug,
                        'title' => $title,
                        'is_public' => $isPublic,
                        'menu_visible' => $menuVisible,
                        'sort_order' => $sortOrder,
                    ]);
                    $pageId = (int)$pdo->lastInsertId();
                }

                $pdo->prepare('DELETE FROM page_permissions WHERE page_id = :page_id')
                    ->execute(['page_id' => $pageId]);

                if (!$isPublic && $permissionIds) {
                    $stmt = $pdo->prepare('
                        INSERT INTO page_permissions (page_id, permission_id)
                        VALUES (:page_id, :permission_id)
                    ');
                    foreach ($permissionIds as $permissionId) {
                        $stmt->execute([
                            'page_id' => $pageId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Ustawienia strony zostały zapisane.');
                redirect('index.php?page=permissions');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Nie udało się zapisać ustawień strony.';
            }
        }
    }
}

$pageToEdit = [
    'id' => 0,
    'slug' => '',
    'title' => '',
    'is_public' => 0,
    'menu_visible' => 1,
    'sort_order' => 100,
];

$pagePermissionIds = [];

if ($editingPageId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editingPageId]);
    $found = $stmt->fetch();

    if ($found) {
        $pageToEdit = $found;

        $stmt = $pdo->prepare('SELECT permission_id FROM page_permissions WHERE page_id = :page_id');
        $stmt->execute(['page_id' => $editingPageId]);
        $pagePermissionIds = array_map('intval', array_column($stmt->fetchAll(), 'permission_id'));
    }
}

$pages = $pdo->query("
    SELECT
        pg.id,
        pg.slug,
        pg.title,
        pg.is_public,
        pg.menu_visible,
        pg.sort_order,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS permissions_list
    FROM pages pg
    LEFT JOIN page_permissions pp ON pp.page_id = pg.id
    LEFT JOIN permissions p ON p.id = pp.permission_id
    GROUP BY pg.id, pg.slug, pg.title, pg.is_public, pg.menu_visible, pg.sort_order
    ORDER BY pg.sort_order, pg.title
")->fetchAll();

$permissions = $pdo->query("
    SELECT id, name, description
    FROM permissions
    ORDER BY name
")->fetchAll();
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">Dostępy do stron</h1>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (!$pages): ?>
                    <p class="mb-0">Brak zdefiniowanych stron.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Slug</th>
                                <th>Tytuł</th>
                                <th>Publiczna</th>
                                <th>Menu</th>
                                <th>Sort</th>
                                <th>Wymagane uprawnienia</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pages as $pageRow): ?>
                                <tr>
                                    <td><?= (int)$pageRow['id'] ?></td>
                                    <td><code><?= e($pageRow['slug']) ?></code></td>
                                    <td><?= e($pageRow['title']) ?></td>
                                    <td>
                                        <?php if ((int)$pageRow['is_public'] === 1): ?>
                                            <span class="badge text-bg-success">Tak</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Nie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$pageRow['menu_visible'] === 1): ?>
                                            <span class="badge text-bg-primary">Widoczna</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-light">Ukryta</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int)$pageRow['sort_order'] ?></td>
                                    <td><?= e($pageRow['permissions_list'] ?: ($pageRow['is_public'] ? '-' : '-')) ?></td>
                                    <td class="text-end">
                                        <?php if (has_permission($pdo, 'permissions.manage')): ?>
                                            <a href="index.php?page=permissions&edit=<?= (int)$pageRow['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                Edytuj
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if (has_permission($pdo, 'permissions.manage')): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5"><?= $pageToEdit['id'] ? 'Edytuj stronę' : 'Dodaj stronę' ?></h2>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="save_page">
                        <input type="hidden" name="page_id" value="<?= (int)$pageToEdit['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" class="form-control" value="<?= e($pageToEdit['slug']) ?>" required>
                            <div class="form-text">Np. users, roles, permissions, dashboard</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tytuł</label>
                            <input type="text" name="title" class="form-control" value="<?= e($pageToEdit['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kolejność w menu</label>
                            <input type="number" name="sort_order" class="form-control" value="<?= (int)$pageToEdit['sort_order'] ?>">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="is_public"
                                    id="is_public"
                                    value="1"
                                    <?= (int)$pageToEdit['is_public'] === 1 ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="is_public">
                                    Strona publiczna
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="menu_visible"
                                    id="menu_visible"
                                    value="1"
                                    <?= (int)$pageToEdit['menu_visible'] === 1 ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="menu_visible">
                                    Widoczna w menu
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Wymagane uprawnienia</label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow:auto;">
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="permissions[]"
                                            value="<?= (int)$permission['id'] ?>"
                                            id="page_perm_<?= (int)$permission['id'] ?>"
                                            <?= in_array((int)$permission['id'], $pagePermissionIds, true) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="page_perm_<?= (int)$permission['id'] ?>">
                                            <?= e($permission['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">
                                Jeżeli strona jest publiczna, zaznaczone uprawnienia nie będą wymagane.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
                        <a href="index.php?page=permissions" class="btn btn-outline-secondary">Nowa</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>