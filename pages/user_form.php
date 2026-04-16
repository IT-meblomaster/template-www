<?php
declare(strict_types=1);

if (!has_permission($pdo, 'users.manage')) {
    http_response_code(403);
    require __DIR__ . '/forbidden.php';
    return;
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $userId > 0;

$roles = get_all_roles($pdo);

$user = [
    'id' => 0,
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'is_active' => 1,
];

$userRoleIds = [];
$errors = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $found = $stmt->fetch();

    if (!$found) {
        set_flash('danger', 'Nie znaleziono użytkownika.');
        redirect('index.php?page=users');
    }

    $user = $found;
    $userRoleIds = get_user_role_ids($pdo, $userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $selectedRoles = array_map('intval', $_POST['roles'] ?? []);

    $user['username'] = $username;
    $user['email'] = $email;
    $user['first_name'] = $firstName;
    $user['last_name'] = $lastName;
    $user['is_active'] = $isActive;
    $userRoleIds = $selectedRoles;

    if ($username === '') {
        $errors[] = 'Login jest wymagany.';
    }

    if (!$isEdit && $password === '') {
        $errors[] = 'Hasło jest wymagane przy tworzeniu użytkownika.';
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1');
    $stmt->execute([
        'username' => $username,
        'id' => $isEdit ? $userId : 0,
    ]);
    if ($stmt->fetch()) {
        $errors[] = 'Taki login już istnieje.';
    }

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $stmt->execute([
            'email' => $email,
            'id' => $isEdit ? $userId : 0,
        ]);
        if ($stmt->fetch()) {
            $errors[] = 'Taki adres email już istnieje.';
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();

        try {
            if ($isEdit) {
                $stmt = $pdo->prepare('
                    UPDATE users
                    SET username = :username,
                        email = :email,
                        first_name = :first_name,
                        last_name = :last_name,
                        is_active = :is_active
                    WHERE id = :id
                ');
                $stmt->execute([
                    'username' => $username,
                    'email' => $email !== '' ? $email : null,
                    'first_name' => $firstName !== '' ? $firstName : null,
                    'last_name' => $lastName !== '' ? $lastName : null,
                    'is_active' => $isActive,
                    'id' => $userId,
                ]);

                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                    $stmt->execute([
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $userId,
                    ]);
                }
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO users (username, email, password_hash, first_name, last_name, is_active)
                    VALUES (:username, :email, :password_hash, :first_name, :last_name, :is_active)
                ');
                $stmt->execute([
                    'username' => $username,
                    'email' => $email !== '' ? $email : null,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'first_name' => $firstName !== '' ? $firstName : null,
                    'last_name' => $lastName !== '' ? $lastName : null,
                    'is_active' => $isActive,
                ]);

                $userId = (int)$pdo->lastInsertId();
            }

            save_user_roles($pdo, $userId, $selectedRoles);

            $pdo->commit();

            set_flash('success', $isEdit ? 'Użytkownik został zaktualizowany.' : 'Użytkownik został dodany.');
            redirect('index.php?page=users');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Nie udało się zapisać użytkownika.';
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?= $isEdit ? 'Edytuj użytkownika' : 'Dodaj użytkownika' ?></h1>
    <a href="index.php?page=users" class="btn btn-outline-secondary">Wróć</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
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

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Login</label>
                    <input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Imię</label>
                    <input type="text" name="first_name" class="form-control" value="<?= e($user['first_name']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nazwisko</label>
                    <input type="text" name="last_name" class="form-control" value="<?= e($user['last_name']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        <?= $isEdit ? 'Nowe hasło (opcjonalnie)' : 'Hasło' ?>
                    </label>
                    <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?>>
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= (int)$user['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Użytkownik aktywny
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Role</label>
                    <div class="row">
                        <?php foreach ($roles as $role): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="roles[]"
                                        id="role_<?= (int)$role['id'] ?>"
                                        value="<?= (int)$role['id'] ?>"
                                        <?= in_array((int)$role['id'], $userRoleIds, true) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="role_<?= (int)$role['id'] ?>">
                                        <?= e($role['name']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <?= $isEdit ? 'Zapisz zmiany' : 'Dodaj użytkownika' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>