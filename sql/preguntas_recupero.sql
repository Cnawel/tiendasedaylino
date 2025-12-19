-- ============================================================================
-- SEED DATA: Preguntas de Recupero de Contraseña
-- ============================================================================
-- Archivo independiente para instalación/inserción de preguntas por defecto.
-- Ejecutar DESPUÉS de CREATE TABLE Preguntas_Recupero.
-- ON DUPLICATE evita re-insert si ya existe (por orden o texto).

USE if0_40082852_tiendasedaylino;  -- Cambiar por tu DB name

INSERT IGNORE INTO Preguntas_Recupero (texto_pregunta, activa, orden) VALUES
    ('¿Cuál es el nombre de tu primera mascota?', 1, 1),
    ('¿En qué ciudad naciste?', 1, 2),
    ('¿Cuál es el nombre de tu mejor amigo/a de la infancia?', 1, 3),
    ('¿Cuál es el nombre de tu colegio primario?', 1, 4);

-- Verificar inserción
SELECT * FROM Preguntas_Recupero ORDER BY orden ASC;
