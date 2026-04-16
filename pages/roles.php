<?php
declare(strict_types=1);

if (!has_permission($pdo, 'roles.view') && !has_permission($pdo, 'roles.manage')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

$errors = [];
$editingRoleId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission($pdo, 'roles.manage')) {
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        return;
    }

    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'save_role') {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $permissionIds = array_map('intval', $_POST['permissions'] ?? []);

        if ($name === '') {
            $errors[] = 'Nazwa roli jest wymagana.';
        }

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name AND id != :id LIMIT 1');
        $stmt->execute([
            'name' => $name,
            'id' => $roleId,
        ]);
        if ($stmt->fetch()) {
            $errors[] = 'Rola o takiej nazwie już istnieje.';
        }

        if (!$errors) {
            $pdo->beginTransaction();

            try {
                if ($roleId > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE roles
                        SET name = :name, description = :description
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description !== '' ? $description : null,
                        'id' => $roleId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO roles (name, description)
                        VALUES (:name, :description)
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'description' => $description !== '' ? $description : null,
                    ]);
                    $roleId = (int)$pdo->lastInsertId();
                }

                $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id')
                    ->execute(['role_id' => $roleId]);

                if ($permissionIds) {
                    $stmt = $pdo->prepare('
                        INSERT INTO role_permissions (role_id, permission_id)
                        VALUES (:role_id, :permission_id)
                    ');
                    foreach ($permissionIds as $permissionId) {
                        $stmt->execute([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Rola została zapisana.');
                redirect('index.php?page=roles');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Nie udało się zapisać roli.';
            }
        }
    }
}

$roleToEdit = [
    'id' => 0,
    'name' => '',
    'description' => '',
];
$rolePermissionIds = [];

if ($editingRoleId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editingRoleId]);
    $found = $stmt->fetch();

    if ($found) {
        $roleToEdit = $found;

        $stmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id');
        $stmt->execute(['role_id' => $editingRoleId]);
        $rolePermissionIds = array_map('intval', array_column($stmt->fetchAll(), 'permission_id'));
    }
}

$roles = $pdo->query("
    SELECT
        r.id,
        r.name,
        r.description,
        COUNT(DISTINCT ur.user_id) AS users_count,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS permissions_list
    FROM roles r
    LEFT JOIN user_roles ur ON ur.role_id = r.id
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    LEFT JOIN permissions p ON p.id = rp.permission_id
    GROUP BY r.id, r.name, r.description
    ORDER BY r.name
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
            <h1 class="h3 mb-0">Role</h1>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (!$roles): ?>
                    <p class="mb-0">Brak ról.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nazwa</th>
                                <th>Opis</th>
                                <th>Użytkownicy</th>
                                <th>Uprawnienia</th>
                                <th class="text-end">Akcje</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><?= (int)$role['id'] ?></td>
                                    <td><?= e($role['name']) ?></td>
                                    <td><?= e($role['description'] ?: '-') ?></td>
                                    <td><?= (int)$role['users_count'] ?></td>
                                    <td><?= e($role['permissions_list'] ?: '-') ?></td>
                                    <td class="text-end">
                                        <?php if (has_permission($pdo, 'roles.manage')): ?>
                                            <a href="index.php?page=roles&edit=<?= (int)$role['id'] ?>" class="btn btn-sm btn-outline-primary">
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
        <?php if (has_permission($pdo, 'roles.manage')): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5"><?= $roleToEdit['id'] ? 'Edytuj rolę' : 'Dodaj rolę' ?></h2>

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
                        <input type="hidden" name="action" value="save_role">
                        <input type="hidden" name="role_id" value="<?= (int)$roleToEdit['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Nazwa roli</label>
                            <input type="text" name="name" class="form-control" value="<?= e($roleToEdit['name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Opis</label>
                            <textarea name="description" class="form-control" rows="3"><?= e($roleToEdit['description']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Uprawnienia</label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow:auto;">
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="permissions[]"
                                            value="<?= (int)$permission['id'] ?>"
                                            id="perm_<?= (int)$permission['id'] ?>"
                                            <?= in_array((int)$permission['id'], $rolePermissionIds, true) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="perm_<?= (int)$permission['id'] ?>">
                                            <?= e($permission['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Zapisz rolę</button>
                        <a href="index.php?page=roles" class="btn btn-outline-secondary">Nowa</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>