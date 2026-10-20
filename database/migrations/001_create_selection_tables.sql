
-- ============================================================
-- RANDOMIX
-- Migracion 001: persistencia de sorteos
-- Compatible con MariaDB 10.4.32
--
-- Tablas:
--   1. selection_runs
--   2. selection_students
--   3. selection_headers
--   4. selection_run_sources
--   5. selection_student_sources
--
-- IMPORTANTE:
-- - No elimina ni modifica tablas existentes.
-- - No inserta datos de prueba.
-- - No impone unicidad global sobre documentos.
-- - Las validaciones entre tablas corresponden a la aplicacion.
-- - Revisar antes de ejecutar.
-- ============================================================


-- ------------------------------------------------------------
-- TABLA 1: selection_runs
-- Registro general de cada ejecucion.
-- ------------------------------------------------------------

CREATE TABLE selection_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    selection_type VARCHAR(20) NOT NULL,
    generated_at DATETIME NOT NULL,
    sheet_name VARCHAR(255) NULL,
    total_records INT UNSIGNED NOT NULL,
    beneficiaries_count INT UNSIGNED NOT NULL DEFAULT 0,
    substitutes_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_selection_runs
        PRIMARY KEY (id),

    CONSTRAINT chk_selection_runs_type
        CHECK (
            selection_type IN ('beneficiaries', 'substitutes')
        ),

    -- Los sorteos de suplentes no generan beneficiarios.
    CONSTRAINT chk_selection_runs_substitute_beneficiaries
        CHECK (
            selection_type <> 'substitutes'
            OR beneficiaries_count = 0
        ),

    
    -- El total de beneficiarios y suplentes no puede
    -- superar la cantidad de registros participantes.
    CONSTRAINT chk_selection_runs_counts
        CHECK (
            beneficiaries_count + substitutes_count
            <= total_records
        ),


    INDEX idx_selection_runs_type_date (
        selection_type,
        generated_at
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- TABLA 2: selection_students
-- Cada fila representa la participacion de un estudiante
-- en una ejecucion concreta.
-- ------------------------------------------------------------

CREATE TABLE selection_students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_run_id BIGINT UNSIGNED NOT NULL,
    original_position INT UNSIGNED NOT NULL,
    result_type VARCHAR(20) NOT NULL,
    result_position INT UNSIGNED NULL,

    document_type_normalized VARCHAR(30) NULL,
    document_number_normalized VARCHAR(50) NULL,

    student_data LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_selection_students
        PRIMARY KEY (id),

    CONSTRAINT fk_selection_students_run
        FOREIGN KEY (selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    CONSTRAINT chk_selection_students_result
        CHECK (
            result_type IN (
                'beneficiary',
                'substitute',
                'not_selected'
            )
        ),

    -- Los no seleccionados no tienen posicion en un grupo.
    CONSTRAINT chk_selection_students_position
        CHECK (
            (
                result_type = 'not_selected'
                AND result_position IS NULL
            )
            OR
            (
                result_type <> 'not_selected'
                AND result_position IS NOT NULL
            )
        ),

    -- El tipo y el numero normalizados se guardan juntos
    -- o ambos quedan NULL cuando no hay documento valido.
    CONSTRAINT chk_selection_students_document
        CHECK (
            (
                document_type_normalized IS NULL
                AND document_number_normalized IS NULL
            )
            OR
            (
                document_type_normalized IS NOT NULL
                AND document_number_normalized IS NOT NULL
            )
        ),

    -- Valida el JSON almacenado en LONGTEXT.
    CONSTRAINT chk_selection_students_json
        CHECK (JSON_VALID(student_data)),

    -- Una posicion original solo puede aparecer una vez
    -- dentro de la misma ejecucion.
    CONSTRAINT uq_selection_students_original_position
        UNIQUE (selection_run_id, original_position),

    -- Evita repetir una posicion dentro del mismo grupo.
    -- Los result_position NULL de not_selected se permiten.
    CONSTRAINT uq_selection_students_result_position
        UNIQUE (
            selection_run_id,
            result_type,
            result_position
        ),

    INDEX idx_selection_students_run_result (
        selection_run_id,
        result_type
    ),

    -- Indice de consulta, NO una restriccion de unicidad.
    -- Permite buscar documentos repetidos sin prohibirlos.
    INDEX idx_selection_students_document (
        document_type_normalized,
        document_number_normalized
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- TABLA 3: selection_headers
-- Conserva los encabezados y su orden original.
-- ------------------------------------------------------------

CREATE TABLE selection_headers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_run_id BIGINT UNSIGNED NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    column_position INT UNSIGNED NOT NULL,

    CONSTRAINT pk_selection_headers
        PRIMARY KEY (id),

    CONSTRAINT fk_selection_headers_run
        FOREIGN KEY (selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    -- Una sola columna por posicion en cada ejecucion.
    CONSTRAINT uq_selection_headers_position
        UNIQUE (selection_run_id, column_position)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- TABLA 4: selection_run_sources
-- Relaciona un Sorteo de Suplentes con los Sorteos de
-- Beneficiarios elegidos como fuente.
-- ------------------------------------------------------------

CREATE TABLE selection_run_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_run_id BIGINT UNSIGNED NOT NULL,
    source_selection_run_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_selection_run_sources
        PRIMARY KEY (id),

    CONSTRAINT fk_selection_run_sources_run
        FOREIGN KEY (selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    CONSTRAINT fk_selection_run_sources_source
        FOREIGN KEY (source_selection_run_id)
        REFERENCES selection_runs (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    -- Una misma fuente no se agrega dos veces a un destino.
    -- La misma fuente SI puede usarse en otros destinos.
    CONSTRAINT uq_selection_run_sources_pair
        UNIQUE (
            selection_run_id,
            source_selection_run_id
        ),

    CONSTRAINT chk_selection_run_sources_not_self
        CHECK (
            selection_run_id <> source_selection_run_id
        ),

    INDEX idx_selection_run_sources_source (
        source_selection_run_id
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- TABLA 5: selection_student_sources
-- Registra las participaciones anteriores que justifican
-- la inclusion de un candidato en una nueva ejecucion.
-- ------------------------------------------------------------

CREATE TABLE selection_student_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    selection_student_id BIGINT UNSIGNED NOT NULL,
    source_selection_student_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_selection_student_sources
        PRIMARY KEY (id),

    CONSTRAINT fk_selection_student_sources_current
        FOREIGN KEY (selection_student_id)
        REFERENCES selection_students (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    CONSTRAINT fk_selection_student_sources_source
        FOREIGN KEY (source_selection_student_id)
        REFERENCES selection_students (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    -- Una participacion de origen no se registra dos veces
    -- para el mismo candidato de destino.
    CONSTRAINT uq_selection_student_sources_pair
        UNIQUE (
            selection_student_id,
            source_selection_student_id
        ),

    CONSTRAINT chk_selection_student_sources_not_self
        CHECK (
            selection_student_id <>
            source_selection_student_id
        ),

    INDEX idx_selection_student_sources_source (
        source_selection_student_id
    )
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- FIN DE LA MIGRACION 001
-- ============================================================
