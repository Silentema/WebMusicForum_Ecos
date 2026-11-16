<?php require __DIR__ . '/PHP/solo_admin.php'; ?>
<?php
//Estadísticas del administrador: todo sale de las VIEWS y FUNCTIONS de la base de datos.
//Views usadas: v_feed_publico, v_publicaciones_pendientes, v_comentarios_detalle,
//v_usuarios_por_pais (usa fn_edad), v_ranking_usuarios, v_seguidores_por_usuario,
//v_hashtags_populares, v_publicaciones_populares (usan fn_total_likes / fn_total_seguidores)

$errores = [];

function filas($conexion, $sql) {
    global $errores;
    try {
        return $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $errores[] = $e->getMessage();
        return [];
    }
}

function numero($conexion, $sql) {
    $f = filas($conexion, $sql);
    return $f ? (int)array_values($f[0])[0] : 0;
}

function h($t) {
    return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
}

// Tarjetas de resumen
$totalUsuarios    = numero($conexion, "SELECT COALESCE(SUM(total_usuarios), 0) FROM v_usuarios_por_pais");
$totalAprobadas   = numero($conexion, "SELECT COUNT(*) FROM v_feed_publico");
$totalPendientes  = numero($conexion, "SELECT COUNT(*) FROM v_publicaciones_pendientes");
$totalComentarios = numero($conexion, "SELECT COUNT(*) FROM v_comentarios_detalle");

// Tablas
$ranking = filas($conexion, "
    SELECT usuario, avatar, publicaciones, likes_recibidos, seguidores
    FROM v_ranking_usuarios
    ORDER BY likes_recibidos DESC, seguidores DESC, publicaciones DESC
    LIMIT 10");

$populares = filas($conexion, "
    SELECT id_publicacion, titulo, autor, total_likes, total_comentarios, puntaje
    FROM v_publicaciones_populares
    ORDER BY puntaje DESC, fecha_creacion DESC
    LIMIT 10");

$hashtags = filas($conexion, "
    SELECT hashtag, veces_usado
    FROM v_hashtags_populares
    WHERE veces_usado > 0
    ORDER BY veces_usado DESC, hashtag ASC
    LIMIT 10");
$maxHashtag = 1;
foreach ($hashtags as $t) {
    $maxHashtag = max($maxHashtag, (int)$t['veces_usado']);
}

$paises = filas($conexion, "
    SELECT pais, total_usuarios, edad_promedio
    FROM v_usuarios_por_pais
    ORDER BY total_usuarios DESC, pais ASC");

$seguidores = filas($conexion, "
    SELECT usuario, seguidores, siguiendo
    FROM v_seguidores_por_usuario
    ORDER BY seguidores DESC, siguiendo DESC
    LIMIT 10");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECOS • Estadísticas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/Main.css" />
    <link rel="stylesheet" href="CSS/back-btn.css" />
    <style>
        .est-main { max-width: 1100px; margin: 0 auto; padding: 20px 20px 60px; font-family: 'Montserrat', sans-serif; color: var(--text, #222); }
        .est-main h1 { text-align: center; margin: 10px 0 24px; }
        .est-resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .est-dato { background: rgba(127,127,127,.12); border-radius: 18px; padding: 20px; text-align: center; }
        .est-dato strong { display: block; font-size: 2.2rem; line-height: 1.1; }
        .est-dato span { font-size: .9rem; opacity: .75; }
        .est-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(440px, 1fr)); gap: 22px; }
        .est-card { background: rgba(127,127,127,.08); border-radius: 20px; padding: 20px 22px; overflow-x: auto; }
        .est-card h2 { margin: 0 0 4px; font-size: 1.15rem; }
        .est-fuente { margin: 0 0 14px; font-size: .78rem; opacity: .6; }
        .est-card table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        .est-card th { text-align: left; font-weight: 700; padding: 6px 8px; border-bottom: 2px solid rgba(127,127,127,.3); }
        .est-card td { padding: 8px; border-bottom: 1px solid rgba(127,127,127,.18); }
        .est-card td.num, .est-card th.num { text-align: right; }
        .est-card a { color: inherit; }
        .est-usuario { display: flex; align-items: center; gap: 10px; }
        .est-usuario img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }
        .est-barra { height: 10px; border-radius: 6px; background: #FF5050; min-width: 4px; }
        .est-vacio { opacity: .6; padding: 10px 0; }
        .est-error { background: rgba(210,66,66,.15); border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; }
    </style>
</head>
<body style="padding-top:70px;">
    <div id="header-container"></div>
    <script src="JS/headerLoader.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.toggle('contrast-theme', localStorage.getItem('contrastMode') === 'true');
        });
    </script>

    <button class="back-btn" style="margin-top: 70px;" onclick="history.back()">←</button>

    <main class="est-main">
        <h1>Estadísticas de ECOS</h1>

        <?php if ($errores): ?>
            <div class="est-error">
                Falta algún objeto de la base de datos (view o function). Corre de nuevo el script SQL en phpMyAdmin.<br>
                <small><?= h($errores[0]) ?></small>
            </div>
        <?php endif; ?>

        <section class="est-resumen">
            <div class="est-dato"><strong><?= $totalUsuarios ?></strong><span>Usuarios</span></div>
            <div class="est-dato"><strong><?= $totalAprobadas ?></strong><span>Publicaciones aprobadas</span></div>
            <div class="est-dato"><strong><?= $totalPendientes ?></strong><span>Pendientes de revisión</span></div>
            <div class="est-dato"><strong><?= $totalComentarios ?></strong><span>Comentarios</span></div>
        </section>

        <div class="est-grid">

            <section class="est-card">
                <h2>Ranking de usuarios</h2>
                <p class="est-fuente">Vista v_ranking_usuarios · function fn_total_seguidores</p>
                <?php if ($ranking): ?>
                <table>
                    <tr><th>Usuario</th><th class="num">Posts</th><th class="num">Likes</th><th class="num">Seguidores</th></tr>
                    <?php foreach ($ranking as $r): ?>
                    <tr>
                        <td>
                            <div class="est-usuario">
                                <img src="<?= h(!empty($r['avatar']) ? $r['avatar'] : 'Image/cuenta.png') ?>" alt="" onerror="this.onerror=null;this.src='Image/cuenta.png'">
                                <?= h($r['usuario']) ?>
                            </div>
                        </td>
                        <td class="num"><?= (int)$r['publicaciones'] ?></td>
                        <td class="num"><?= (int)$r['likes_recibidos'] ?></td>
                        <td class="num"><?= (int)$r['seguidores'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?><div class="est-vacio">Sin datos todavía.</div><?php endif; ?>
            </section>

            <section class="est-card">
                <h2>Publicaciones populares</h2>
                <p class="est-fuente">Vista v_publicaciones_populares · puntaje = likes + 2 × comentarios · function fn_total_likes</p>
                <?php if ($populares): ?>
                <table>
                    <tr><th>Título</th><th>Autor</th><th class="num">Likes</th><th class="num">Coment.</th><th class="num">Puntaje</th></tr>
                    <?php foreach ($populares as $p): ?>
                    <tr>
                        <td><a href="PHP/5verPost.php?id=<?= (int)$p['id_publicacion'] ?>"><?= h($p['titulo'] !== null && $p['titulo'] !== '' ? $p['titulo'] : 'Sin título') ?></a></td>
                        <td><?= h($p['autor']) ?></td>
                        <td class="num"><?= (int)$p['total_likes'] ?></td>
                        <td class="num"><?= (int)$p['total_comentarios'] ?></td>
                        <td class="num"><strong><?= (int)$p['puntaje'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?><div class="est-vacio">Aún no hay publicaciones con likes o comentarios.</div><?php endif; ?>
            </section>

            <section class="est-card">
                <h2>Hashtags más usados</h2>
                <p class="est-fuente">Vista v_hashtags_populares (solo publicaciones aprobadas)</p>
                <?php if ($hashtags): ?>
                <table>
                    <tr><th>Hashtag</th><th style="width:45%">Uso</th><th class="num">Veces</th></tr>
                    <?php foreach ($hashtags as $t): ?>
                    <tr>
                        <td>#<?= h($t['hashtag']) ?></td>
                        <td><div class="est-barra" style="width: <?= max(4, round(100 * (int)$t['veces_usado'] / $maxHashtag)) ?>%"></div></td>
                        <td class="num"><?= (int)$t['veces_usado'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?><div class="est-vacio">Aún no hay hashtags en publicaciones aprobadas.</div><?php endif; ?>
            </section>

            <section class="est-card">
                <h2>Usuarios por país</h2>
                <p class="est-fuente">Vista v_usuarios_por_pais · function fn_edad</p>
                <?php if ($paises): ?>
                <table>
                    <tr><th>País</th><th class="num">Usuarios</th><th class="num">Edad promedio</th></tr>
                    <?php foreach ($paises as $p): ?>
                    <tr>
                        <td><?= h($p['pais']) ?></td>
                        <td class="num"><?= (int)$p['total_usuarios'] ?></td>
                        <td class="num"><?= $p['edad_promedio'] !== null ? h($p['edad_promedio']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?><div class="est-vacio">Sin datos todavía.</div><?php endif; ?>
            </section>

            <section class="est-card">
                <h2>Seguidores</h2>
                <p class="est-fuente">Vista v_seguidores_por_usuario</p>
                <?php if ($seguidores): ?>
                <table>
                    <tr><th>Usuario</th><th class="num">Seguidores</th><th class="num">Siguiendo</th></tr>
                    <?php foreach ($seguidores as $s): ?>
                    <tr>
                        <td><?= h($s['usuario']) ?></td>
                        <td class="num"><?= (int)$s['seguidores'] ?></td>
                        <td class="num"><?= (int)$s['siguiendo'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?><div class="est-vacio">Sin datos todavía.</div><?php endif; ?>
            </section>

        </div>
    </main>
</body>
</html>