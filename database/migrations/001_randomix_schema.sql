-- ============================================================
-- RANDOMIX
-- Migracion 001: esquema relacional definitivo
-- Motor: MariaDB 10.4.32
-- Almacenamiento: InnoDB
-- Charset: utf8mb4
-- Collation: utf8mb4_unicode_ci
--
-- ATENCION:
-- - Respaldar la base de datos antes de ejecutar.
-- - DROP TABLE elimina las cinco tablas selection_* existentes.
-- - admin_users NO se elimina ni se modifica.
-- - No ejecutar hasta revisar el esquema y sus dependencias.
-- ============================================================

USE randomix;

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- PASO 1: reiniciar el esquema completo de Randomix.
-- Esta migracion elimina las 15 tablas del nuevo esquema y admin_users.
-- Ejecutar unicamente despues de confirmar que existe un respaldo.
-- ------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS selection_student_sources;
DROP TABLE IF EXISTS selection_run_sources;
DROP TABLE IF EXISTS selection_run_files;
DROP TABLE IF EXISTS selection_headers;
DROP TABLE IF EXISTS selection_students;
DROP TABLE IF EXISTS selection_runs;
DROP TABLE IF EXISTS student_dataset_rows;
DROP TABLE IF EXISTS student_dataset_headers;
DROP TABLE IF EXISTS student_datasets;
DROP TABLE IF EXISTS student_dataset_series;
DROP TABLE IF EXISTS comparison_run_files;
DROP TABLE IF EXISTS comparison_runs;
DROP TABLE IF EXISTS excel_merge_sources;
DROP TABLE IF EXISTS excel_merge_runs;
DROP TABLE IF EXISTS file_artifacts;
DROP TABLE IF EXISTS admin_users;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- PASO 2: tabla de administradores.
-- Se conserva el usuario inicial mediante tests/create_admin.php.
-- ------------------------------------------------------------

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

-- ------------------------------------------------------------
-- PASO 3: catálogo general de archivos.
-- ------------------------------------------------------------

CREATE TABLE file_artifacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    artifact_type VARCHAR(20) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NULL,
    availability_status VARCHAR(20) NOT NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_file_artifacts_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    INDEX idx_file_artifacts_sha256 (sha256),
    INDEX idx_file_artifacts_status (availability_status),
    INDEX idx_file_artifacts_created_at (created_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PASO 4: comparaciones de Excel.
-- ------------------------------------------------------------

CREATE TABLE comparison_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    previous_year SMALLINT UNSIGNED NOT NULL,
    current_year SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    previous_rows_count INT UNSIGNED NOT NULL DEFAULT 0,
    current_rows_count INT UNSIGNED NOT NULL DEFAULT 0,
    result_rows_count INT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_comparison_runs_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,


    INDEX idx_comparison_runs_years (
        previous_year,
        current_year
    ),
    INDEX idx_comparison_runs_status (status),
    INDEX idx_comparison_runs_created_at (created_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comparison_run_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    comparison_run_id BIGINT UNSIGNED NOT NULL,
    file_artifact_id BIGINT UNSIGNED NOT NULL,
    file_role VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_comparison_run_files_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_comparison_run_files_run
        FOREIGN KEY (comparison_run_id)
        REFERENCES comparison_runs (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_comparison_run_files_artifact
        FOREIGN KEY (file_artifact_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    INDEX idx_comparison_run_files_artifact (file_artifact_id),
    INDEX idx_comparison_run_files_role (
        comparison_run_id,
        file_role
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PASO 5: series y versiones de conjuntos de estudiantes.
-- ------------------------------------------------------------

CREATE TABLE student_dataset_series (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_student_dataset_series_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,


    INDEX idx_student_dataset_series_name (name)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_datasets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    series_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    parent_dataset_id BIGINT UNSIGNED NULL,
    source_type VARCHAR(25) NOT NULL,
    source_file_id BIGINT UNSIGNED NULL,
    source_selection_run_id BIGINT UNSIGNED NULL,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_student_datasets_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_student_datasets_series
        FOREIGN KEY (series_id)
        REFERENCES student_dataset_series (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_student_datasets_parent
        FOREIGN KEY (parent_dataset_id)
        REFERENCES student_datasets (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_student_datasets_file
        FOREIGN KEY (source_file_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT uq_student_datasets_series_version
        UNIQUE (series_id, version_number),

    INDEX idx_student_datasets_parent (parent_dataset_id),
    INDEX idx_student_datasets_status (status)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_dataset_headers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_id BIGINT UNSIGNED NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    column_position INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_student_dataset_headers_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_student_dataset_headers_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES student_datasets (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT uq_student_dataset_headers_position
        UNIQUE (dataset_id, column_position)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_dataset_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(30) NULL,
    document_number_normalized VARCHAR(50) NULL,
    student_data LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_student_dataset_rows_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_student_dataset_rows_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES student_datasets (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    INDEX idx_student_dataset_rows_dataset (dataset_id),
    INDEX idx_student_dataset_rows_document (
        document_type,
        document_number_normalized
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PASO 6: sorteos.
-- La FK hacia student_datasets se agrega al final para resolver
-- la dependencia circular con student_datasets.
-- ------------------------------------------------------------

CREATE TABLE selection_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    selection_type VARCHAR(20) NOT NULL,
    dataset_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    generated_at DATETIME NULL,
    sheet_name VARCHAR(255) NULL,
    total_records INT UNSIGNED NOT NULL DEFAULT 0,
    beneficiaries_count INT UNSIGNED NOT NULL DEFAULT 0,
    substitutes_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_selection_runs_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT chk_selection_runs_type
        CHECK (
            selection_type IN ('beneficiaries', 'substitutes')
        ),

    CONSTRAINT chk_selection_runs_substitute_beneficiaries
        CHECK (
            selection_type <> 'substitutes'
            OR beneficiaries_count = 0
        ),

    CONSTRAINT chk_selection_runs_counts
        CHECK (
            beneficiaries_count + substitutes_count
            <= total_records
        ),

    INDEX idx_selection_runs_dataset (dataset_id),
    INDEX idx_selection_runs_type_status (
        selection_type,
        status
    ),
    INDEX idx_selection_runs_generated_at (generated_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE selection_students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_run_id BIGINT UNSIGNED NOT NULL,
    source_dataset_row_id BIGINT UNSIGNED NOT NULL,
    original_position INT UNSIGNED NOT NULL,
    result_type VARCHAR(20) NOT NULL,
    result_position INT UNSIGNED NULL,
    document_type_normalized VARCHAR(30) NULL,
    document_number_normalized VARCHAR(50) NULL,
    student_data LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_selection_students_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_selection_students_run
        FOREIGN KEY (selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_selection_students_dataset_row
        FOREIGN KEY (source_dataset_row_id)
        REFERENCES student_dataset_rows (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT chk_selection_students_result
        CHECK (
            result_type IN (
                'beneficiary',
                'substitute',
                'not_selected'
            )
        ),

    CONSTRAINT chk_selection_students_position
        CHECK (
            (result_type = 'not_selected'
                AND result_position IS NULL)
            OR
            (result_type <> 'not_selected'
                AND result_position IS NOT NULL)
        ),

    CONSTRAINT chk_selection_students_document
        CHECK (
            (document_type_normalized IS NULL
                AND document_number_normalized IS NULL)
            OR
            (document_type_normalized IS NOT NULL
                AND document_number_normalized IS NOT NULL)
        ),

    CONSTRAINT chk_selection_students_json
        CHECK (JSON_VALID(student_data)),

    CONSTRAINT uq_selection_students_original_position
        UNIQUE (selection_run_id, original_position),

    CONSTRAINT uq_selection_students_result_position
        UNIQUE (selection_run_id, result_type, result_position),

    INDEX idx_selection_students_run_result (
        selection_run_id,
        result_type
    ),

    INDEX idx_selection_students_dataset_row (
        source_dataset_row_id
    ),

    INDEX idx_selection_students_document (
        document_type_normalized,
        document_number_normalized
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE selection_headers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_run_id BIGINT UNSIGNED NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    column_position INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_selection_headers_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_selection_headers_run
        FOREIGN KEY (selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT uq_selection_headers_position
        UNIQUE (selection_run_id, column_position)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE selection_run_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_run_id BIGINT UNSIGNED NOT NULL,
    source_selection_run_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_selection_run_sources_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_selection_run_sources_run
        FOREIGN KEY (selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_selection_run_sources_source
        FOREIGN KEY (source_selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT uq_selection_run_sources_pair
        UNIQUE (selection_run_id, source_selection_run_id),

    CONSTRAINT chk_selection_run_sources_not_self
        CHECK (selection_run_id <> source_selection_run_id),

    INDEX idx_selection_run_sources_source (
        source_selection_run_id
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE selection_student_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_student_id BIGINT UNSIGNED NOT NULL,
    source_selection_student_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_selection_student_sources_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_selection_student_sources_current
        FOREIGN KEY (selection_student_id)
        REFERENCES selection_students (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_selection_student_sources_source
        FOREIGN KEY (source_selection_student_id)
        REFERENCES selection_students (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT uq_selection_student_sources_pair
        UNIQUE (
            selection_student_id,
            source_selection_student_id
        ),

    CONSTRAINT chk_selection_student_sources_not_self
        CHECK (
            selection_student_id <> source_selection_student_id
        ),

    INDEX idx_selection_student_sources_source (
        source_selection_student_id
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE selection_run_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_run_id BIGINT UNSIGNED NOT NULL,
    file_artifact_id BIGINT UNSIGNED NOT NULL,
    file_role VARCHAR(25) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_selection_run_files_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_selection_run_files_run
        FOREIGN KEY (selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_selection_run_files_artifact
        FOREIGN KEY (file_artifact_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    INDEX idx_selection_run_files_artifact (file_artifact_id),
    INDEX idx_selection_run_files_role (
        selection_run_id,
        file_role
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PASO 7: unión de Excel.
-- ------------------------------------------------------------

CREATE TABLE excel_merge_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    status VARCHAR(20) NOT NULL,
    input_a_rows INT UNSIGNED NOT NULL DEFAULT 0,
    input_b_rows INT UNSIGNED NOT NULL DEFAULT 0,
    result_rows INT UNSIGNED NOT NULL DEFAULT 0,
    duplicates_removed INT UNSIGNED NOT NULL DEFAULT 0,
    result_file_artifact_id BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_excel_merge_runs_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_excel_merge_runs_result_file
        FOREIGN KEY (result_file_artifact_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    INDEX idx_excel_merge_runs_status (status),
    INDEX idx_excel_merge_runs_created_at (created_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE excel_merge_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    excel_merge_run_id BIGINT UNSIGNED NOT NULL,
    file_artifact_id BIGINT UNSIGNED NOT NULL,
    source_position TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_excel_merge_sources_created_by
        FOREIGN KEY (created_by)
        REFERENCES admin_users (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,



    CONSTRAINT fk_excel_merge_sources_run
        FOREIGN KEY (excel_merge_run_id)
        REFERENCES excel_merge_runs (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT fk_excel_merge_sources_artifact
        FOREIGN KEY (file_artifact_id)
        REFERENCES file_artifacts (id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,

    CONSTRAINT uq_excel_merge_sources_position
        UNIQUE (excel_merge_run_id, source_position),

    CONSTRAINT chk_excel_merge_sources_position
        CHECK (source_position IN (1, 2)),

    INDEX idx_excel_merge_sources_artifact (file_artifact_id)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PASO 8: completar las dos relaciones circulares.
-- ------------------------------------------------------------

ALTER TABLE selection_runs
    ADD CONSTRAINT fk_selection_runs_dataset
    FOREIGN KEY (dataset_id)
    REFERENCES student_datasets (id)
    ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE student_datasets
    ADD CONSTRAINT fk_student_datasets_source_selection
    FOREIGN KEY (source_selection_run_id)
    REFERENCES selection_runs (id)
    ON UPDATE RESTRICT ON DELETE RESTRICT;

-- ============================================================
-- FIN DE LA MIGRACION 001
-- ============================================================