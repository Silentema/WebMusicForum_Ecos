-- =====================================================================
-- Limpieza de la base de datos para InfinityFree
-- ---------------------------------------------------------------------
-- InfinityFree (hosting gratuito) no permite ejecutar funciones
-- almacenadas ni triggers. El código PHP ya NO usa vistas, funciones
-- ni triggers: todo se calcula con consultas directas a las tablas.
--
-- Este script quita esos objetos si llegaron a crearse. NO toca tablas
-- ni datos (usuarios, publicaciones, comentarios, etc. quedan igual).
--
-- Cómo usarlo: phpMyAdmin de InfinityFree -> tu base -> pestaña SQL ->
-- pegar todo -> Continuar.
-- =====================================================================

-- Vistas (antes las usaban el perfil, los comentarios y las estadísticas)
DROP VIEW IF EXISTS v_publicaciones_populares;
DROP VIEW IF EXISTS v_feed_publico;
DROP VIEW IF EXISTS v_comentarios_detalle;
DROP VIEW IF EXISTS v_hashtags_populares;
DROP VIEW IF EXISTS v_publicaciones_pendientes;
DROP VIEW IF EXISTS v_ranking_usuarios;
DROP VIEW IF EXISTS v_seguidores_por_usuario;
DROP VIEW IF EXISTS v_usuarios_por_pais;

-- Triggers (su lógica ahora está en api_publicaciones.php).
-- Si aquí sale "Acceso denegado", es que nunca se crearon: puedes ignorarlo.
DROP TRIGGER IF EXISTS trg_publicacion_bi;
DROP TRIGGER IF EXISTS trg_reaccion_bi;
DROP TRIGGER IF EXISTS trg_relacion_bi;

-- Funciones: no hace falta borrarlas. InfinityFree no dejó crearlas
-- (error #1044 al importar) y el código ya no las llama.
