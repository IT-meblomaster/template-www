SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE page_permissions;
TRUNCATE TABLE role_permissions;
TRUNCATE TABLE pages;
TRUNCATE TABLE permissions;
TRUNCATE TABLE roles;

INSERT INTO roles (id, name, description) VALUES
(1, 'Administrator', 'Pełny dostęp do systemu'),
(2, 'User', 'Podstawowy użytkownik');

INSERT INTO permissions (id, name, description) VALUES
(1, 'dashboard.view', 'Podgląd dashboardu'),
(2, 'users.view', 'Podgląd użytkowników'),
(3, 'users.manage', 'Zarządzanie użytkownikami'),
(4, 'roles.view', 'Podgląd ról'),
(5, 'roles.manage', 'Zarządzanie rolami'),
(6, 'permissions.view', 'Podgląd uprawnień'),
(7, 'permissions.manage', 'Zarządzanie uprawnieniami'),
(8, 'pages.view', 'Podgląd stron'),
(9, 'pages.manage', 'Zarządzanie dostępem do stron');

INSERT INTO pages (id, parent_id, slug, title, is_public, menu_visible, sort_order) VALUES
(1, NULL, 'home', 'Start', 1, 1, 10),
(2, NULL, 'login', 'Logowanie', 1, 0, 20),
(3, NULL, 'dashboard', 'Dashboard', 0, 1, 20),
(4, 9, 'users', 'Użytkownicy', 0, 1, 10),
(5, 9, 'roles', 'Role', 0, 1, 20),
(6, 9, 'permissions', 'Uprawnienia', 0, 1, 30),
(7, NULL, 'forbidden', 'Brak dostępu', 1, 0, 999),
(8, NULL, 'settings', 'Ustawienia', 0, 1, 50),
(9, 8, 'access_management', 'Zarządzaj dostępem', 0, 1, 10);

INSERT INTO role_permissions (role_id, permission_id) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(2, 1);

INSERT INTO page_permissions (page_id, permission_id) VALUES
(3, 1),
(4, 2),
(4, 3),
(5, 4),
(5, 5),
(6, 6),
(6, 7);

SET FOREIGN_KEY_CHECKS = 1;