<?php
/**
 * Reglas de administrador de ECOS.
 *
 * Una cuenta es administradora si en la tabla `usuario` tiene id_rol = 2
 * (rol "Administrador" de la tabla `rol`). Para hacer administrador a alguien
 * o quitarle el permiso, solo se cambia su id_rol en la base de datos.
 */

const ROL_ADMINISTRADOR = 2;

// ¿Esta fila de la tabla usuario es una cuenta administradora?
function esCuentaAdministrador($usuario) {
    return is_array($usuario) && (int)($usuario['id_rol'] ?? 0) === ROL_ADMINISTRADOR;
}

// ¿La sesión actual entró como administrador y la cuenta lo sigue siendo?
// (Se revisa la base de datos en cada petición: si le quitas el rol, pierde el acceso al instante.)
function sesionEsAdministrador($conexion) {
    if (empty($_SESSION['id_usuario']) || empty($_SESSION['es_admin'])) {
        return false;
    }
    $stmt = $conexion->prepare('SELECT id_rol FROM usuario WHERE id_usuario = :id');
    $stmt->execute([':id' => (int)$_SESSION['id_usuario']]);
    return esCuentaAdministrador($stmt->fetch(PDO::FETCH_ASSOC));
}
