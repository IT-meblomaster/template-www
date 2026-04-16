<?php
declare(strict_types=1);

if (!has_permission($pdo, 'users.view') && !has_permission($pdo, 'users.manage')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!has_permission($pdo, 'users.manage')) {
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        return;
    }

    verify_csrf();

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_active' && $userId > 0) {
        $stmt = $pdo->prepare('
            UPDATE users
            SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
            WHERE id = :id
        ');
        $stmt->execute(['id' => $userId]);
        set_flash('success', 'Status użytkownika został zmieniony.');
        redirect('index.php?page=users');
    }
}

$stmt = $pdo->query('
    SELECT
        u.id,
        u.username,
        u.email,
        u.first_name,
        u.last_name,
        u.is_active,
        u.last_login_at,
        GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ", ") AS roles
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    GROUP BY u.id, u.username, u.email, u.first_name, u.last_name, u.is_active, u.last_login_at
    ORDER BY u.username
');
$users = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Użytkownicy</h1>

    <?php if (has_permission($pdo, 'users.manage')): ?>
        <a href="index.php?page=user_form" class="btn btn-primary">Dodaj użytkownika</a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (!$users): ?>
            <p class="mb-0">Brak użytkowników.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Login</th>
                        <th>Imię i nazwisko</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Ostatnie logowanie</th>
                        <th class="text-end">Akcje</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int)$user['id'] ?></td>
                            <td><?= e($user['username']) ?></td>
                            <td><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td><?= e($user['roles'] ?: '-') ?></td>
                            <td>
                                <?php if ((int)$user['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Aktywny</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Nieaktywny</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($user['last_login_at'] ?: '-') ?></td>
                            <td class="text-end">
                                <?php if (has_permission($pdo, 'users.manage')): ?>
                                    <a href="index.php?page=user_form&id=<?= (int)$user['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Edytuj
                                    </a>

                                    <form method="post" class="d-inline">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <?= (int)$user['is_active'] === 1 ? 'Dezaktywuj' : 'Aktywuj' ?>
                                        </button>
                                    </form>
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