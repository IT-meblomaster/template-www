sed -n '1,220p' index.php
<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';

$debugEnabled = !empty($config['debug']);
$logErrors = array_key_exists('log_errors', $config) ? !empty($config['log_errors']) : true;
$errorLogFile = isset($config['error_log']) && is_string($config['error_log']) && $config['error_log'] !== ''
    ? $config['error_log']
    : null;

if ($debugEnabled) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

ini_set('log_errors', $logErrors ? '1' : '0');

if ($errorLogFile !== null) {
    ini_set('error_log', $errorLogFile);
}

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/csrf.php';
require __DIR__ . '/inc/access.php';

start_session($config);
$pdo = db($config);

$page = current_page();
$pageFile = __DIR__ . '/pages/' . $page . '.php';

if ($page === 'logout') {
    require __DIR__ . '/pages/logout.php';
    exit;
}

if (!is_file($pageFile)) {
    http_response_code(404);
    $page = 'forbidden';
    $pageFile = __DIR__ . '/pages/forbidden.php';
}

if (!can_access_page($pdo, $page)) {
    if (!is_logged_in()) {
        redirect('index.php?page=login');
    }

    http_response_code(403);
    $page = 'forbidden';
    $pageFile = __DIR__ . '/pages/forbidden.php';
}

require __DIR__ . '/pages/header.php';
require $pageFile;
require __DIR__ . '/pages/footer.php';[root@patrol template.local]# printf '\n===== logout.php =====\n'

===== logout.php =====
[root@patrol template.local]# sed -n '1,220p' pages/logout.php
<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php?page=login');
}

if (!verify_csrf()) {
    redirect('index.php?page=dashboard');
}

logout();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

redirect('index.php?page=login');[root@patrol template.local]# printf '\n===== helpers.php =====\n'

===== helpers.php =====
[root@patrol template.local]# sed -n '1,120p' inc/helpers.php
<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_page(): string
{
    $page = $_GET['page'] ?? 'home';
    $page = preg_replace('/[^a-z0-9_-]/i', '', (string) $page);

    return $page !== '' ? $page : 'home';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    $messages = get_flash_messages();

    if ($messages === []) {
        return null;
    }

    return $messages[0];
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];

    if (!is_array($messages)) {
        $messages = [];
    }

    unset($_SESSION['flash']);

    return $messages;
}[root@patrol template.local]# printf '\n===== header.php =====\n'

===== header.php =====
[root@patrol template.local]# sed -n '130,170p' pages/header.php
                                <?= e($currentUser['username'] ?? 'Użytkownik') ?>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="index.php?page=logout">
                                        Wyloguj
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?page=login">Zaloguj</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-2 page-content">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-submenu-toggle="true"]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var parentLi = button.closest('.dropdown-submenu');