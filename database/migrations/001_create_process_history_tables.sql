
-- ============================================================
-- Randomix
-- Migración: usuarios, catálogo de archivos e historial
-- Compatible con MariaDB 10.4.32
--
-- IMPORTANTE:
-- 1. Seleccionar previamente la base de datos randomix.
-- 2. Crea admin_users con la estructura original del proyecto.
-- 3. No elimina tablas ni modifica datos existentes.
-- 4. No crea claves foráneas.
-- 5. El usuario Tics se crea por separado mediante
--    tests/create_admin.php.
-- 6. Ejecutar únicamente sobre una base de datos vacía
--    o después de comprobar que las tablas no existen.
-- ============================================================


-- ============================================================
-- 1. TABLA DE ADMINISTRADORES
-- Estructura original compatible con AuthService.php.
-- ============================================================

CREATE TABLE admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    last_login_at TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 2. CATÁLOGO GENERAL DE ARCHIVOS
-- Registra archivos originales y Excel generados.
-- relative_path se guarda relativo a storage/records/.
-- ============================================================

CREATE TABLE file_artifacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,

    file_extension VARCHAR(10) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,

    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_file_artifacts_stored_name (stored_name),
    UNIQUE KEY uq_file_artifacts_relative_path (relative_path),

    INDEX idx_file_artifacts_created_at (created_at),
    INDEX idx_file_artifacts_sha256 (sha256)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. HISTORIAL DE COMPARACIONES
-- Solo registra comparaciones completadas correctamente.
-- ============================================================

CREATE TABLE comparison_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    file_a_id INT UNSIGNED NOT NULL,
    file_b_id INT UNSIGNED NOT NULL,
    result_file_id INT UNSIGNED NOT NULL,

    sheet_a VARCHAR(255) NOT NULL,
    sheet_b VARCHAR(255) NOT NULL,

    total_original INT UNSIGNED NOT NULL,
    coincidences_removed INT UNSIGNED NOT NULL,
    remaining INT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    INDEX idx_comparison_runs_executed_at (executed_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 4. HISTORIAL DE SELECCIONES ALEATORIAS
-- Conserva referencias al archivo original y a los tres Excel:
-- seleccionados, no seleccionados y suplentes.
-- ============================================================

CREATE TABLE selection_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    source_file_id INT UNSIGNED NOT NULL,
    selected_file_id INT UNSIGNED NOT NULL,
    not_selected_file_id INT UNSIGNED NOT NULL,
    alternates_file_id INT UNSIGNED NOT NULL,

    sheet_name VARCHAR(255) NOT NULL,

    requested_beneficiaries INT UNSIGNED NOT NULL,
    requested_alternates INT UNSIGNED NOT NULL,

    total_records INT UNSIGNED NOT NULL,
    beneficiaries_count INT UNSIGNED NOT NULL,
    alternates_count INT UNSIGNED NOT NULL,
    not_selected_count INT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    INDEX idx_selection_runs_executed_at (executed_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 5. HISTORIAL DE UNIFICACIONES DE EXCEL
-- Registra cantidades procesadas y el Excel unificado.
-- ============================================================

CREATE TABLE excel_merge_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    file_a_id INT UNSIGNED NOT NULL,
    file_b_id INT UNSIGNED NOT NULL,
    result_file_id INT UNSIGNED NOT NULL,

    sheet_a VARCHAR(255) NOT NULL,
    sheet_b VARCHAR(255) NOT NULL,

    total_a INT UNSIGNED NOT NULL,
    total_b INT UNSIGNED NOT NULL,

    omitted_empty_rows INT UNSIGNED NOT NULL,
    omitted_without_document INT UNSIGNED NOT NULL,
    duplicates_removed INT UNSIGNED NOT NULL,
    total_merged INT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    INDEX idx_excel_merge_runs_executed_at (executed_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Randomix
-- Agregar created_at a las tablas de historial
-- Compatible con MariaDB 10.4.32
-- ============================================================

ALTER TABLE comparison_runs
    ADD COLUMN created_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE selection_runs
    ADD COLUMN created_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE excel_merge_runs
    ADD COLUMN created_at DATETIME NOT NULL
    DEFAULT CURRENT_TIMESTAMP;

-- 1. Añadir el rol con valor predeterminado de usuario normal.
ALTER TABLE admin_users
ADD COLUMN rol TINYINT UNSIGNED NOT NULL DEFAULT 0
AFTER username;

-- 2. Asignar el rol de administrador exclusivamente a Tics.
UPDATE admin_users
SET rol = 1
WHERE username = 'Tics';

-- 3. Comprobar los roles asignados.
SELECT id, username, rol
FROM admin_users
ORDER BY id;


-- ============================================================
-- Randomix
-- Crear claves foráneas hacia file_artifacts
-- Compatible con MariaDB 10.4.32
-- ============================================================

-- 1. Relaciones de comparación de Excel.
ALTER TABLE comparison_runs
    ADD CONSTRAINT fk_comparison_runs_file_a
        FOREIGN KEY (file_a_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_comparison_runs_file_b
        FOREIGN KEY (file_b_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_comparison_runs_result_file
        FOREIGN KEY (result_file_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;

-- 2. Relaciones de unión de Excel.
ALTER TABLE excel_merge_runs
    ADD CONSTRAINT fk_excel_merge_runs_file_a
        FOREIGN KEY (file_a_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_excel_merge_runs_file_b
        FOREIGN KEY (file_b_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_excel_merge_runs_result_file
        FOREIGN KEY (result_file_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;

-- 3. Relaciones de selección aleatoria.
ALTER TABLE selection_runs
    ADD CONSTRAINT fk_selection_runs_source_file
        FOREIGN KEY (source_file_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_selection_runs_selected_file
        FOREIGN KEY (selected_file_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_selection_runs_not_selected_file
        FOREIGN KEY (not_selected_file_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    ADD CONSTRAINT fk_selection_runs_alternates_file
        FOREIGN KEY (alternates_file_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT;
