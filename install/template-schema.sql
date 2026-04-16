-- template-schema.sql
-- Schemat startowy dla projektu template-www
-- MariaDB / MySQL

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS page_permissions;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED DEFAULT NULL,
    slug VARCHAR(100) NOT NULL,
    title VARCHAR(150) NOT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    menu_visible TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_slug (slug),
    KEY idx_pages_parent_id (parent_id),
    CONSTRAINT fk_pages_parent
        FOREIGN KEY (parent_id) REFERENCES pages(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_permissions (
    page_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (page_id, permission_id),
    CONSTRAINT fk_page_permissions_page
        FOREIGN KEY (page_id) REFERENCES pages(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_page_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, description) VALUES
('Administrator', 'Pełny dostęp do systemu'),
('User', 'Podstawowy użytkownik');

INSERT INTO permissions (name, description) VALUES
('dashboard.view', 'Podgląd dashboardu'),
('users.view', 'Podgląd użytkowników'),
('users.manage', 'Zarządzanie użytkownikami'),
('roles.view', 'Podgląd ról'),
('roles.manage', 'Zarządzanie rolami'),
('permissions.view', 'Podgląd uprawnień'),
('permissions.manage', 'Zarządzanie uprawnieniami'),
('pages.view', 'Podgląd stron'),
('pages.manage', 'Zarządzanie dostępem do stron');

INSERT INTO pages (slug, title, parent_id, is_public, menu_visible, sort_order) VALUES
('home', 'Start', NULL, 1, 1, 10),
('login', 'Logowanie', NULL, 1, 0, 20),
('dashboard', 'Dashboard', NULL, 0, 1, 20),
('settings', 'Ustawienia', NULL, 0, 1, 50),
('access_management', 'Zarządzaj dostępem', NULL, 0, 1, 10),
('users', 'Użytkownicy', NULL, 0, 1, 10),
('roles', 'Role', NULL, 0, 1, 20),
('permissions', 'Uprawnienia', NULL, 0, 1, 30),
('forbidden', 'Brak dostępu', NULL, 1, 0, 999);

UPDATE pages child
JOIN pages parent ON parent.slug = 'settings'
SET child.parent_id = parent.id
WHERE child.slug = 'access_management';

UPDATE pages child
JOIN pages parent ON parent.slug = 'access_management'
SET child.parent_id = parent.id
WHERE child.slug IN ('users', 'roles', 'permissions');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.name = 'Administrator';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.name = 'User'
  AND p.name IN ('dashboard.view');

INSERT INTO page_permissions (page_id, permission_id)
SELECT pg.id, pm.id
FROM pages pg
JOIN permissions pm
WHERE
    (pg.slug = 'dashboard' AND pm.name = 'dashboard.view') OR
    (pg.slug = 'users' AND pm.name IN ('users.view', 'users.manage')) OR
    (pg.slug = 'roles' AND pm.name IN ('roles.view', 'roles.manage')) OR
    (pg.slug = 'permissions' AND pm.name IN ('permissions.view', 'permissions.manage'));

SET FOREIGN_KEY_CHECKS = 1;