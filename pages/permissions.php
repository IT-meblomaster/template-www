<?php
declare(strict_types=1);

if (!has_permission($pdo, 'permissions.view') && !has_permission($pdo, 'permissions.manage')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

$errors = [];
$editingPageId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$systemSlugs = [
    'home',
    'login',
    'logout',
    'forbidden',
    'dashboard',
    'settings',
    'access_management',
    'user_form',
];

$menuContainerSlugs = [
    'settings',
    'access_management',
];

$hiddenTechnicalSlugs = [
    'login',
    'logout',
    'forbidden',
    'user_form',
];

function get_page_depth(array $pagesById, ?int $pageId): int
{
    $depth = 0;

    while ($pageId !== null && isset($pagesById[$pageId])) {
        $depth++;
        $pageId = $pagesById[$pageId]['parent_id'] !== null ? (int) $pagesById[$pageId]['parent_id'] : null;
    }

    return $depth;
}

function collect_descendant_ids(array $pagesById, int $pageId): array
{
    $result = [];

    foreach ($pagesById as $candidateId => $candidate) {
        $parentId = $candidate['parent_id'] !== null ? (int) $candidate['parent_id'] : null;

        if ($parentId === $pageId) {
            $result[] = (int) $candidateId;
            $result = array_merge($result, collect_descendant_ids($pagesById, (int) $candidateId));
        }
    }

    return $result;
}

$allPagesRaw = $pdo->query("
    SELECT id, slug, title, parent_id, is_public, menu_visible, sort_order
    FROM pages
    ORDER BY sort_order, title
")->fetchAll();

$pagesById = [];
foreach ($allPagesRaw as $pageRow) {
    $pagesById[(int) $pageRow['id']] = $pageRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission($pdo, 'permissions.manage')) {
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        return;
    }

    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save_page') {
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        $menuVisible = isset($_POST['menu_visible']) ? 1 : 0;
        $sortOrder = (int) ($_POST['sort_order'] ?? 100);
        $permissionIds = array_map('intval', $_POST['permissions'] ?? []);

        $originalPage = null;
        if ($pageId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $pageId]);
            $originalPage = $stmt->fetch();
        }

        $isSystemPage = $originalPage && in_array((string) $originalPage['slug'], $systemSlugs, true);

        if ($slug === '') {
            $errors[] = 'Slug strony jest wymagany.';
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            $errors[] = 'Slug może zawierać tylko litery, cyfry, myślnik i podkreślenie.';
        }

        if ($title === '') {
            $errors[] = 'Tytuł strony jest wymagany.';
        }

        if ($pageId > 0 && $isSystemPage && $originalPage && $slug !== $originalPage['slug']) {
            $errors[] = 'Nie można zmieniać slugów stron systemowych.';
        }

        $stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = :slug AND id != :id LIMIT 1');
        $stmt->execute([
            'slug' => $slug,
            'id' => $pageId,
        ]);
        if ($stmt->fetch()) {
            $errors[] = 'Strona o takim slug już istnieje.';
        }

        $parentId = $parentId > 0 ? $parentId : null;

        if ($pageId > 0 && $parentId === $pageId) {
            $errors[] = 'Strona nie może być rodzicem samej siebie.';
        }

        if ($pageId > 0 && $parentId !== null) {
            $descendants = collect_descendant_ids($pagesById, $pageId);
            if (in_array($parentId, $descendants, true)) {
                $errors[] = 'Nie można przypisać strony do własnego potomka.';
            }
        }

        if ($parentId !== null && isset($pagesById[$parentId])) {
            $futureDepth = get_page_depth($pagesById, $parentId) + 1;
            if ($futureDepth > 3) {
                $errors[] = 'Maksymalna głębokość menu to 3 poziomy.';
            }
        }

        if (in_array($slug, $hiddenTechnicalSlugs, true) && $menuVisible === 1) {
            $errors[] = 'Ta strona techniczna nie może być widoczna w menu.';
        }

        if (in_array($slug, $menuContainerSlugs, true) && $isPublic === 1) {
            $errors[] = 'Kontener menu nie może być stroną publiczną.';
        }

        if (in_array($slug, $menuContainerSlugs, true) && $permissionIds !== []) {
            $errors[] = 'Kontener menu nie powinien mieć własnych wymaganych uprawnień.';
        }

        if (!$errors) {
            $pdo->beginTransaction();

            try {
                if ($pageId > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE pages
                        SET slug = :slug,
                            title = :title,
                            parent_id = :parent_id,
                            is_public = :is_public,
                            menu_visible = :menu_visible,
                            sort_order = :sort_order
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'slug' => $slug,
                        'title' => $title,
                        'parent_id' => $parentId,
                        'is_public' => $isPublic,
                        'menu_visible' => $menuVisible,
                        'sort_order' => $sortOrder,
                        'id' => $pageId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO pages (slug, title, parent_id, is_public, menu_visible, sort_order)
                        VALUES (:slug, :title, :parent_id, :is_public, :menu_visible, :sort_order)
                    ');
                    $stmt->execute([
                        'slug' => $slug,
                        'title' => $title,
                        'parent_id' => $parentId,
                        'is_public' => $isPublic,
                        'menu_visible' => $menuVisible,
                        'sort_order' => $sortOrder,
                    ]);
                    $pageId = (int) $pdo->lastInsertId();
                }

                $pdo->prepare('DELETE FROM page_permissions WHERE page_id = :page_id')
                    ->execute(['page_id' => $pageId]);

                if (!in_array($slug, $menuContainerSlugs, true) && !$isPublic && $permissionIds) {
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
    'parent_id' => null,
    'is_public' => 0,
    'menu_visible' => 1,
    'sort_order' => 100,
];

$pagePermissionIds = [];
$modalTitle = 'Dodaj stronę';
$openModal = false;

if ($editingPageId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editingPageId]);
    $found = $stmt->fetch();

    if ($found) {
        $pageToEdit = $found;
        $modalTitle = 'Edytuj stronę: ' . ($pageToEdit['title'] ?: $pageToEdit['slug']);
        $openModal = true;

        $stmt = $pdo->prepare('SELECT permission_id FROM page_permissions WHERE page_id = :page_id');
        $stmt->execute(['page_id' => $editingPageId]);
        $pagePermissionIds = array_map('intval', array_column($stmt->fetchAll(), 'permission_id'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    $pageToEdit = [
        'id' => (int) ($_POST['page_id'] ?? 0),
        'slug' => trim((string) ($_POST['slug'] ?? '')),
        'title' => trim((string) ($_POST['title'] ?? '')),
        'parent_id' => ((int) ($_POST['parent_id'] ?? 0)) ?: null,
        'is_public' => isset($_POST['is_public']) ? 1 : 0,
        'menu_visible' => isset($_POST['menu_visible']) ? 1 : 0,
        'sort_order' => (int) ($_POST['sort_order'] ?? 100),
    ];
    $pagePermissionIds = array_map('intval', $_POST['permissions'] ?? []);
    $modalTitle = $pageToEdit['id'] > 0 ? 'Edytuj stronę' : 'Dodaj stronę';
    $openModal = true;
}

$pages = $pdo->query("
    SELECT
        pg.id,
        pg.slug,
        pg.title,
        pg.parent_id,
        parent.title AS parent_title,
        pg.is_public,
        pg.menu_visible,
        pg.sort_order,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS permissions_list
    FROM pages pg
    LEFT JOIN pages parent ON parent.id = pg.parent_id
    LEFT JOIN page_permissions pp ON pp.page_id = pg.id
    LEFT JOIN permissions p ON p.id = pp.permission_id
    GROUP BY
        pg.id,
        pg.slug,
        pg.title,
        pg.parent_id,
        parent.title,
        pg.is_public,
        pg.menu_visible,
        pg.sort_order
    ORDER BY
        COALESCE(parent.title, ''),
        pg.sort_order,
        pg.title
")->fetchAll();

$permissions = $pdo->query("
    SELECT id, name, description
    FROM permissions
    ORDER BY name
")->fetchAll();

$parentOptions = $pdo->query("
    SELECT id, slug, title, parent_id
    FROM pages
    ORDER BY sort_order, title
")->fetchAll();
?>

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
                        <th>Rodzic</th>
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
                            <td><?= (int) $pageRow['id'] ?></td>
                            <td><code><?= e($pageRow['slug']) ?></code></td>
                            <td><?= e($pageRow['title']) ?></td>
                            <td><?= e($pageRow['parent_title'] ?: '-') ?></td>
                            <td>
                                <?php if ((int) $pageRow['is_public'] === 1): ?>
                                    <span class="badge text-bg-success">Tak</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $pageRow['menu_visible'] === 1): ?>
                                    <span class="badge text-bg-primary">Widoczna</span>
                                <?php else: ?>
                                    <span class="badge text-bg-light">Ukryta</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $pageRow['sort_order'] ?></td>
                            <td><?= e($pageRow['permissions_list'] ?: '-') ?></td>
                            <td class="text-end">
                                <?php if (has_permission($pdo, 'permissions.manage')): ?>
                                    <a href="index.php?page=permissions&edit=<?= (int) $pageRow['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Edytuj
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (has_permission($pdo, 'permissions.manage')): ?>
                <div class="mt-3">
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#pageModal"
                    >
                        Dodaj nową stronę
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (has_permission($pdo, 'permissions.manage')): ?>
    <div class="modal fade" id="pageModal" tabindex="-1" aria-labelledby="pageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_page">
                    <input type="hidden" name="page_id" value="<?= (int) $pageToEdit['id'] ?>">

                    <div class="modal-header">
                        <h5 class="modal-title" id="pageModalLabel"><?= e($modalTitle) ?></h5>
                        <a href="index.php?page=permissions" class="btn-close"></a>
                    </div>

                    <div class="modal-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Slug</label>
                                <input type="text" name="slug" class="form-control" value="<?= e($pageToEdit['slug']) ?>" required>
                                <div class="form-text">Np. users, roles, permissions, dashboard</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tytuł</label>
                                <input type="text" name="title" class="form-control" value="<?= e($pageToEdit['title']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Rodzic w menu</label>
                                <select name="parent_id" class="form-select">
                                    <option value="0">— brak —</option>
                                    <?php foreach ($parentOptions as $parentOption): ?>
                                        <?php
                                        $parentOptionId = (int) $parentOption['id'];
                                        if ($pageToEdit['id'] && $parentOptionId === (int) $pageToEdit['id']) {
                                            continue;
                                        }
                                        ?>
                                        <option
                                            value="<?= $parentOptionId ?>"
                                            <?= (int) ($pageToEdit['parent_id'] ?? 0) === $parentOptionId ? 'selected' : '' ?>
                                        >
                                            <?= e($parentOption['title']) ?> (<?= e($parentOption['slug']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Kolejność w menu</label>
                                <input type="number" name="sort_order" class="form-control" value="<?= (int) $pageToEdit['sort_order'] ?>">
                            </div>

                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="is_public"
                                        id="is_public"
                                        value="1"
                                        <?= (int) $pageToEdit['is_public'] === 1 ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="is_public">
                                        Strona publiczna
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="menu_visible"
                                        id="menu_visible"
                                        value="1"
                                        <?= (int) $pageToEdit['menu_visible'] === 1 ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="menu_visible">
                                        Widoczna w menu
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Wymagane uprawnienia</label>
                                <div class="border rounded p-3" style="max-height: 300px; overflow:auto;">
                                    <?php foreach ($permissions as $permission): ?>
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="permissions[]"
                                                value="<?= (int) $permission['id'] ?>"
                                                id="page_perm_<?= (int) $permission['id'] ?>"
                                                <?= in_array((int) $permission['id'], $pagePermissionIds, true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label" for="page_perm_<?= (int) $permission['id'] ?>">
                                                <?= e($permission['name']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">
                                    Jeżeli strona jest publiczna, zaznaczone uprawnienia nie będą wymagane.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <a href="index.php?page=permissions" class="btn btn-outline-secondary">Anuluj</a>
                        <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($openModal): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('pageModal');
                if (modalEl) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
    <?php endif; ?>
<?php endif; ?>