<?php
require_once __DIR__ . '/error_popup.php';

//Se reanuda la sesión para poder leer los datos del usuario que inició sesión
session_start();

// Si alguien intenta entrar a ajustes escribiendo la URL sin hacer login, lo regresamos
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../2inicioSesion.html");
    exit();
}

$passwordActualizada = !empty($_SESSION['password_actualizada']);
unset($_SESSION['password_actualizada']);

//Traemos la conexión a la base de datos
require __DIR__ . '/conexion.php';

try {
    //Buscamos los datos reales del usuario usando su ID guardado en la sesión
    $id_usuario = $_SESSION['id_usuario'];
    $sql = "SELECT * FROM usuario WHERE id_usuario = :id";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    
    //Guardamos los datos del usuario en un arreglo ($usuario)
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    mostrarPopupError(
        "Error al cargar los datos: " . $e->getMessage(),
        '3main.html'
    );
}
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ECOS • Ajustes</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../CSS/ajustes.css" />
    <link rel="stylesheet" href="../CSS/back-btn.css" />
  </head>

  <body style="padding-top:70px;">
    
    <div id="header-container"></div>
    <script src="../JS/headerLoader.js"></script>

    <button class="back-btn" onclick="history.back()" style="top: 90px;">←</button>

    <main class="page">
      <div class="container">
        <div class="page-header">
          <div class="header-left">
            <svg class="settings-icon" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.791 60L20.597 50.4C19.9502 50.15 19.3408 49.85 18.7687 49.5C18.1965 49.15 17.6368 48.775 17.0896 48.375L8.20895 52.125L0 37.875L7.68657 32.025C7.63682 31.675 7.61194 31.3375 7.61194 31.0125V28.9875C7.61194 28.6625 7.63682 28.325 7.68657 27.975L0 22.125L8.20895 7.875L17.0896 11.625C17.6368 11.225 18.209 10.85 18.806 10.5C19.403 10.15 20 9.85 20.597 9.6L21.791 0H38.209L39.403 9.6C40.0497 9.85 40.6592 10.15 41.2313 10.5C41.8035 10.85 42.3632 11.225 42.9104 11.625L51.791 7.875L60 22.125L52.3134 27.975C52.3632 28.325 52.3881 28.6625 52.3881 28.9875V31.0125C52.3881 31.3375 52.3383 31.675 52.2388 32.025L59.9254 37.875L51.7164 52.125L42.9104 48.375C42.3632 48.775 41.791 49.15 41.194 49.5C40.597 49.85 40 50.15 39.403 50.4L38.209 60H21.791ZM27.0149 54H32.9104L33.9552 46.05C35.4975 45.65 36.9279 45.0625 38.2463 44.2875C39.5647 43.5125 40.7711 42.575 41.8657 41.475L49.2537 44.55L52.1642 39.45L45.7463 34.575C45.995 33.875 46.1692 33.1375 46.2687 32.3625C46.3682 31.5875 46.4179 30.8 46.4179 30C46.4179 29.2 46.3682 28.4125 46.2687 27.6375C46.1692 26.8625 45.995 26.125 45.7463 25.425L52.1642 20.55L49.2537 15.45L41.8657 18.6C40.7711 17.45 39.5647 16.4875 38.2463 15.7125C36.9279 14.9375 35.4975 14.35 33.9552 13.95L32.9851 6H27.0896L26.0448 13.95C24.5025 14.35 23.0721 14.9375 21.7537 15.7125C20.4353 16.4875 19.2289 17.425 18.1343 18.525L10.7463 15.45L7.83582 20.55L14.2537 25.35C14.005 26.1 13.8308 26.85 13.7313 27.6C13.6318 28.35 13.5821 29.15 13.5821 30C13.5821 30.8 13.6318 31.575 13.7313 32.325C13.8308 33.075 14.005 33.825 14.2537 34.575L7.83582 39.45L10.7463 44.55L18.1343 41.4C19.2289 42.55 20.4353 43.5125 21.7537 44.2875C23.0721 45.0625 24.5025 45.65 26.0448 46.05L27.0149 54ZM30.1493 40.5C33.0348 40.5 35.4975 39.475 37.5373 37.425C39.5771 35.375 40.597 32.9 40.597 30C40.597 27.1 39.5771 24.625 37.5373 22.575C35.4975 20.525 33.0348 19.5 30.1493 19.5C27.2139 19.5 24.7388 20.525 22.7239 22.575C20.709 24.625 19.7015 27.1 19.7015 30C19.7015 32.9 20.709 35.375 22.7239 37.425C24.7388 39.475 27.2139 40.5 30.1493 40.5Z" fill="#777"/></svg>
            <h1 class="page-title">Ajustes</h1>
          </div>
          <div class="search-box">
            <input type="text" class="search-input" placeholder="Una búsqueda" />
            <svg class="search-icon" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M23.0556 25L14.3056 16.25C13.6111 16.8056 12.8125 17.2454 11.9097 17.5694C11.0069 17.8935 10.0463 18.0556 9.02778 18.0556C6.50463 18.0556 4.36921 17.1817 2.62153 15.434C0.873843 13.6863 0 11.5509 0 9.02778C0 6.50463 0.873843 4.36921 2.62153 2.62153C4.36921 0.873843 6.50463 0 9.02778 0C11.5509 0 13.6863 0.873843 15.434 2.62153C17.1817 4.36921 18.0556 6.50463 18.0556 9.02778C18.0556 10.0463 17.8935 11.0069 17.5694 11.9097C17.2454 12.8125 16.8056 13.6111 16.25 14.3056L25 23.0556L23.0556 25ZM9.02778 15.2778C10.7639 15.2778 12.2396 14.6701 13.4549 13.4549C14.6701 12.2396 15.2778 10.7639 15.2778 9.02778C15.2778 7.29167 14.6701 5.81597 13.4549 4.60069C12.2396 3.38542 10.7639 2.77778 9.02778 2.77778C7.29167 2.77778 5.81597 3.38542 4.60069 4.60069C3.38542 5.81597 2.77778 7.29167 2.77778 9.02778C2.77778 10.7639 3.38542 12.2396 4.60069 13.4549C5.81597 14.6701 7.29167 15.2778 9.02778 15.2778Z" fill="#777"/></svg>
          </div>
        </div>

        <div class="settings-grid">
          <div class="settings-left">
            <div class="ajustes-menu-items">
              <button class="ajustes-menu-item active" id="btnPerfil">
                <svg class="ajustes-menu-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.7568 28.3805H25.2432C26.0358 28.3805 26.7097 28.103 27.2647 27.5479C27.8198 26.9932 28.0974 26.3193 28.0974 25.5263V19.2511C28.0974 18.4581 27.8198 17.7842 27.2647 17.2295C26.7097 16.6744 26.0358 16.3968 25.2432 16.3968H24.9395V12.9153C24.9395 11.5321 24.4623 10.363 23.5079 9.4079C22.5539 8.45316 21.386 7.97579 20.0042 7.97579C18.6221 7.97579 17.4526 8.45316 16.4958 9.4079C15.5389 10.363 15.0605 11.5321 15.0605 12.9153V13.4011H17.5711V12.9153C17.5711 12.2377 17.8065 11.6633 18.2774 11.1921C18.7482 10.7212 19.3225 10.4858 20 10.4858C20.6775 10.4858 21.2518 10.7212 21.7226 11.1921C22.1935 11.6633 22.4289 12.2377 22.4289 12.9153V16.3968H14.7568C13.9642 16.3968 13.2904 16.6744 12.7353 17.2295C12.1802 17.7842 11.9026 18.4581 11.9026 19.2511V25.5263C11.9026 26.3193 12.1802 26.9932 12.7353 27.5479C13.2904 28.103 13.9642 28.3805 14.7568 28.3805ZM20.0037 40C17.2374 40 14.6372 39.4751 12.2032 38.4253C9.76912 37.3754 7.65193 35.9507 5.85158 34.1511C4.05123 32.3514 2.62579 30.2351 1.57526 27.8021C0.525087 25.3691 0 22.7696 0 20.0037C0 17.2374 0.524912 14.6372 1.57474 12.2032C2.62456 9.76912 4.0493 7.65193 5.84895 5.85158C7.6486 4.05123 9.76491 2.62579 12.1979 1.57526C14.6309 0.525087 17.2304 0 19.9963 0C22.7626 0 25.3628 0.524911 27.7968 1.57474C30.2309 2.62456 32.3481 4.0493 34.1484 5.84895C35.9488 7.6486 37.3742 9.76491 38.4247 12.1979C39.4749 14.6309 40 17.2304 40 19.9963C40 22.7626 39.4751 25.3628 38.4253 27.7968C37.3754 30.2309 35.9507 32.3481 34.1511 34.1484C32.3514 35.9488 30.2351 37.3742 27.8021 38.4247C25.3691 39.4749 22.7696 40 20.0037 40ZM20 36.8421C24.7018 36.8421 28.6842 35.2105 31.9474 31.9474C35.2105 28.6842 36.8421 24.7018 36.8421 20C36.8421 15.2982 35.2105 11.3158 31.9474 8.05263C28.6842 4.78947 24.7018 3.15789 20 3.15789C15.2982 3.15789 11.3158 4.78947 8.05263 8.05263C4.78947 11.3158 3.15789 15.2982 3.15789 20C3.15789 24.7018 4.78947 28.6842 8.05263 31.9474C11.3158 35.2105 15.2982 36.8421 20 36.8421Z" fill="#686868"/></svg>
                <span class="ajustes-menu-text">Tu cuenta</span>
                <svg class="ajustes-menu-arrow" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
              </button>

              <button class="ajustes-menu-item" id="btnPrivacidad">
                <svg class="ajustes-menu-icon" viewBox="0 0 31 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.71466 40V13.0107C6.88787 12.363 4.59163 10.8961 2.82594 8.60989C1.05992 6.32333 0.117939 3.72878 0 0.82627H3.06728C3.19851 3.47177 4.15634 5.70635 5.94077 7.53001C7.72486 9.35367 9.90043 10.2655 12.4675 10.2655H17.5805C18.4851 10.2655 19.2954 10.417 20.0112 10.72C20.727 11.0227 21.4034 11.4926 22.0405 12.1297L31 21.0893L28.8449 23.245L20.3333 14.7333V40H17.266V27.8468H12.7819V40H9.71466ZM15.024 7.55148C13.9779 7.55148 13.087 7.18385 12.3514 6.44859C11.6158 5.713 11.248 4.82213 11.248 3.776C11.248 2.72986 11.6158 1.839 12.3514 1.1034C13.087 0.367801 13.9779 0 15.024 0C16.0701 0 16.9609 0.367801 17.6965 1.1034C18.4321 1.839 18.7999 2.72986 18.7999 3.776C18.7999 4.82213 18.4321 5.713 17.6965 6.44859C16.9609 7.18385 16.0701 7.55148 15.024 7.55148Z" fill="#686868"/></svg>
                <span class="ajustes-menu-text">Privacidad</span>
                <svg class="ajustes-menu-arrow" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
              </button>

              <button class="ajustes-menu-item" id="btnNotificaciones">
                <svg class="ajustes-menu-icon" viewBox="0 0 34 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15.5053 40C14.4744 40 13.5938 39.632 12.8637 38.8959C12.1332 38.1598 11.768 37.272 11.768 36.2323H19.2426C19.2426 37.272 18.8773 38.1598 18.1469 38.8959C17.4167 39.632 16.5362 40 15.5053 40ZM0 34.1482V31.0224H3.73729V15.4708C3.73729 12.7402 4.5477 10.3127 6.16852 8.1882C7.78934 6.06408 9.89582 4.66542 12.488 3.99224C12.2361 4.56921 12.0385 5.17101 11.8951 5.79765C11.7521 6.4243 11.6462 7.06483 11.5773 7.71926C10.1859 8.43552 9.0482 9.4757 8.16405 10.8398C7.28025 12.2039 6.83835 13.7476 6.83835 15.4708V31.0224H24.1722V23.0822C24.6891 23.1461 25.2059 23.1748 25.7228 23.1682C26.2396 23.1616 26.7564 23.1196 27.2733 23.0421V31.0224H31.0106V34.1482H0ZM23.6952 18.5168L23.274 16.032C22.7413 15.8719 22.241 15.6695 21.773 15.425C21.3051 15.1804 20.8618 14.8591 20.4432 14.461L18.0818 15.2786L16.4914 12.6572L18.3919 11.0143C18.2433 10.429 18.1691 9.84373 18.1691 9.25842C18.1691 8.67347 18.2433 8.08833 18.3919 7.50303L16.4914 5.85966L18.0818 3.23881L20.4432 4.05632C20.8488 3.65825 21.2888 3.33694 21.7632 3.09239C22.2377 2.84785 22.7413 2.64534 23.274 2.48486L23.6952 0H26.7962L27.2175 2.48486C27.7505 2.64534 28.2541 2.84785 28.7282 3.09239C29.2027 3.33694 29.6427 3.65825 30.0482 4.05632L32.4097 3.23881L34 5.85966L32.0996 7.50303C32.2481 8.08833 32.3223 8.67347 32.3223 9.25842C32.3223 9.84373 32.2481 10.429 32.0996 11.0143L34 12.6572L32.4097 15.2786L30.0482 14.461C29.6296 14.8591 29.1863 15.1804 28.7184 15.425C28.2508 15.6695 27.7505 15.8719 27.2175 16.032L26.7962 18.5168H23.6952ZM25.2457 13.5873C26.4358 13.5873 27.449 13.1657 28.2853 12.3227C29.1215 11.4796 29.5396 10.4582 29.5396 9.25842C29.5396 8.05863 29.1215 7.03722 28.2853 6.19417C27.449 5.35112 26.4358 4.92959 25.2457 4.92959C24.0556 4.92959 23.0424 5.35112 22.2062 6.19417C21.3699 7.03722 20.9518 8.05863 20.9518 9.25842C20.9518 10.4582 21.3699 11.4796 22.2062 12.3227C23.0424 13.1657 24.0556 13.5873 25.2457 13.5873Z" fill="#686868"/></svg>
                <span class="ajustes-menu-text">Notificaciones</span>
                <svg class="ajustes-menu-arrow" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
              </button>

              <button class="ajustes-menu-item" id="btnAccesibilidad">
                <svg class="ajustes-menu-icon" viewBox="0 0 34 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.5 6.23875C13.6274 6.23875 12.8844 5.93503 12.2708 5.32759C11.6573 4.71986 11.3505 3.98386 11.3505 3.11959C11.3505 2.25531 11.6573 1.51931 12.2708 0.911585C12.8844 0.303861 13.6274 0 14.5 0C15.3726 0 16.1156 0.303861 16.7292 0.911585C17.3427 1.51931 17.6495 2.25531 17.6495 3.11959C17.6495 3.98386 17.3427 4.71986 16.7292 5.32759C16.1156 5.93503 15.3726 6.23875 14.5 6.23875ZM10.0715 31.6491V10.983C8.33267 10.8422 6.60375 10.6391 4.88479 10.3738C3.16583 10.1083 1.53757 9.78062 0 9.39087L0.6235 6.85633C2.80817 7.41534 5.0723 7.82044 7.4159 8.07164C9.75921 8.32284 12.1206 8.44844 14.5 8.44844C16.8794 8.44844 19.2408 8.32284 21.5841 8.07164C23.9277 7.82044 26.1918 7.41534 28.3765 6.85633L29 9.39087C27.4624 9.78062 25.8342 10.1083 24.1152 10.3738C22.3962 10.6391 20.6673 10.8422 18.9285 10.983V31.6491H16.3701V21.4785H12.6299V31.6491H10.0715ZM7.67647 40C7.25881 40 6.90285 39.8544 6.60859 39.5632C6.31461 39.2717 6.16762 38.9192 6.16762 38.5055C6.16762 38.0918 6.31461 37.7392 6.60859 37.4477C6.90285 37.1565 7.25881 37.0109 7.67647 37.0109C8.09413 37.0109 8.45009 37.1565 8.74435 37.4477C9.03833 37.7392 9.18532 38.0918 9.18532 38.5055C9.18532 38.9192 9.03833 39.2717 8.74435 39.5632C8.45009 39.8544 8.09413 40 7.67647 40ZM14.5 40C14.0823 40 13.7264 39.8544 13.4321 39.5632C13.1381 39.2717 12.9911 38.9192 12.9911 38.5055C12.9911 38.0918 13.1381 37.7392 13.4321 37.4477C13.7264 37.1565 14.0823 37.0109 14.5 37.0109C14.9177 37.0109 15.2736 37.1565 15.5679 37.4477C15.8619 37.7392 16.0089 38.0918 16.0089 38.5055C16.0089 38.9192 15.8619 39.2717 15.5679 39.5632C15.2736 39.8544 14.9177 40 14.5 40ZM21.3235 40C20.9059 40 20.5499 39.8544 20.2556 39.5632C19.9617 39.2717 19.8147 38.9192 19.8147 38.5055C19.8147 38.0918 19.9617 37.7392 20.2556 37.4477C20.5499 37.1565 20.9059 37.0109 21.3235 37.0109C21.7412 37.0109 22.0971 37.1565 22.3914 37.4477C22.6854 37.7392 22.8324 38.0918 22.8324 38.5055C22.8324 38.9192 22.6854 39.2717 22.3914 39.5632C22.0971 39.8544 21.7412 40 21.3235 40Z" fill="#686868"/></svg>
                <span class="ajustes-menu-text">Accesibilidad</span>
                <svg class="ajustes-menu-arrow" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
              </button>
            </div>

            <div class="logout-section">
              <div class="logout-divider left"></div>
              <div class="logout-divider right"></div>
              <button class="btn btn-cancel" id="logout-btn">Cerrar sesión</button>
            </div>
          </div>

          <div class="settings-right">

            <!-- Panel Notificaciones -->
            <div class="panel superpuesto" id="panel-notificaciones">
              <h2 class="panel-title">Notificaciones</h2>
              <div class="panel-option sub" id="btnCuentasNotificacionesActivas">
                <span class="panel-option-text">Cuentas con notificaciones activas</span>
                <svg width="8" height="16" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
              </div>
              <div class="panel-option sub">
                <span class="panel-option-text">Activar notificaciones</span>
                <svg class="toggle-svg toggle-on" width="50" height="50" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M17.75 43.5C13.9304 43.5 10.684 42.1638 8.01062 39.4913C5.33687 36.8188 4 33.5735 4 29.7556C4 25.9377 5.33687 22.6906 8.01062 20.0144C10.684 17.3381 13.9304 16 17.75 16H42.75C46.5696 16 49.816 17.3363 52.4894 20.0088C55.1631 22.6813 56.5 25.9265 56.5 29.7444C56.5 33.5623 55.1631 36.8094 52.4894 39.4856C49.816 42.1619 46.5696 43.5 42.75 43.5H17.75ZM17.75 39.75H42.75C45.5 39.75 47.8542 38.7708 49.8125 36.8125C51.7708 34.8542 52.75 32.5 52.75 29.75C52.75 27 51.7708 24.6458 49.8125 22.6875C47.8542 20.7292 45.5 19.75 42.75 19.75H17.75C15 19.75 12.6458 20.7292 10.6875 22.6875C8.72917 24.6458 7.75 27 7.75 29.75C7.75 32.5 8.72917 34.8542 10.6875 36.8125C12.6458 38.7708 15 39.75 17.75 39.75ZM42.7469 36.625C44.656 36.625 46.2796 35.9569 47.6175 34.6206C48.9558 33.2844 49.625 31.6619 49.625 29.7531C49.625 27.844 48.9569 26.2204 47.6206 24.8825C46.2844 23.5442 44.6619 22.875 42.7531 22.875C40.844 22.875 39.2204 23.5431 37.8825 24.8794C36.5442 26.2156 35.875 27.8381 35.875 29.7469C35.875 31.656 36.5431 33.2796 37.8794 34.6175C39.2156 35.9558 40.8381 36.625 42.7469 36.625Z" fill="#ED3232"/>
                </svg>
              </div>
            </div>

            <!-- Panel Privacidad -->
            <div class="panel superpuesto" id="panel-privacidad">
              <div class="privacidad-container">
                <div class="privacidad-header">
                  <h1>Privacidad</h1>
                </div>
                <div class="privacidad-menu-item" id="btnUsuariosBloqueados">
                  <span>Usuarios bloqueados</span>
                  <svg viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
                </div>
                <div class="privacidad-menu-item" id="btnUsuariosSilenciados">
                  <span>Usuarios silenciados</span>
                  <svg viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
                </div>
              </div>
            </div>

            <!-- Panel Personas Bloqueadas -->
            <div class="panel superpuesto" id="personas-bloqueadas">
              <h1 class="title">Usuarios que bloqueaste</h1>
              <div class="blocked-users-container">
                <div class="blocked-users-list">
                  <div class="user-card">
                    <img src="https://api.builder.io/api/v1/image/assets/TEMP/d1d40f5f0a574ae7e86b9d78c9c3f116edd0cf67?width=108" alt="Alberto" class="user-avatar">
                    <div class="user-info">
                      <div class="user-name">Alberto Aguilera Valadez</div>
                      <div class="user-handle">@albertelguapo</div>
                      <div class="user-bio">Si soy bien guapo y soy el Albert</div>
                    </div>
                    <div class="actions"><button class="block-button">Bloqueado</button></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Panel Personas Silenciadas --> //ME GUSTAN LOS CACHORRITOS
            <div class="panel superpuesto" id="personas-silenciadas">
              <h1 class="title">Usuarios que silenciaste</h1>
              <div class="blocked-users-container">
                <div class="blocked-users-list">
                  <div class="user-card">
                    <img src="../Image/ajoloEric.jpg" alt="Joaquin" class="user-avatar">
                    <div class="user-info">
                      <div class="user-name">Joaquin Ruenes hernandez</div>
                      <div class="user-handle">@joakini3241</div>
                      <div class="user-bio">El chiquis</div>
                    </div>
                    <div class="actions"><button class="block-button">Silenciado</button></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Panel Cuentas Activas -->
            <div class="panel superpuesto" id="cuentas-activas">
              <h1 class="title">Cuentas con notificaciones activas</h1>
              <div class="blocked-users-container">
                <div class="blocked-users-list">
                  <div class="user-card">
                    <img src="../Image/caradel gordo.jpg" alt="Joaquin" class="user-avatar">
                    <div class="user-info">
                      <div class="user-name">Joaquin Ruenes hernandez</div>
                      <div class="user-handle">@joakini3241</div>
                      <div class="user-bio">El chiquis</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Panel Info (Menú Inicial derecho) -->
            <div class="panel superpuesto visible" id="panel-info">
              <h2 class="panel-title">Información de tu cuenta</h2>
              <div class="panel-option sub" id="btnTuInfo">
                <span class="panel-option-text">Tu información</span>
                <svg width="8" height="16" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
              </div>
              <div class="panel-option sub" id="btnCambiarContra">
                <span class="panel-option-text">Cambiar contraseña</span>
                <svg width="8" height="16" viewBox="0 0 8 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 8L0 16L0 0L8 8Z" fill="#2C2C2C"/></svg>
              </div>
            </div>

            <!-- Panel Accesibilidad -->
            <div class="panel superpuesto" id="panel-accesibilidad">
              <h2 class="panel-title">Opciones de accesibilidad</h2>
              <div class="panel-option sub">
                <span class="panel-option-text">Aumentar contraste</span>
                <label class="contrast-switch" style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-left:auto;">
                  <input type="checkbox" id="contrastBtn" style="width:22px;height:22px;accent-color:#FF5050;" aria-label="Activar alto contraste">
                </label>
              </div>
              <div class="panel-option sub"><span class="panel-option-text">Color de fondo</span></div>
              <div class="theme-toggle-group">
                <button class="theme-button light active" id="lightBtn" onclick="selectTheme('light')">
                  <span>Claro</span>
                </button>
                <button class="theme-button dark" id="darkBtn" onclick="selectTheme('dark')">
                  <span>Oscuro</span>
                </button>
              </div>
            </div>

            <!-- Panel Tu Cuenta (Formulario dinámico con datos de la BD) -->
            <div class="panel superpuesto" id="tu-cuenta">
              <div class="privacidad-container">
                <div class="privacidad-header">
                  <h1>Tu información</h1>
                </div>
                <form action="actualizar_perfil.php" method="POST">
                  <div class="ajustes2-form-group">
                    <label for="username" class="form-label">Nombre de usuario</label>
                    <input
                      type="text"
                      id="username"
                      name="nombre"
                      class="form-input small"
                      value="<?php echo htmlspecialchars($usuario['nombre'] ?? ''); ?>"
                    />
                  </div>
                  <div class="ajustes2-form-group">
                    <label for="alias" class="form-label">Alias</label>
                    <input
                      type="text"
                      id="alias"
                      name="alias"
                      class="form-input medium"
                      value="<?php echo htmlspecialchars($usuario['alias'] ?? ''); ?>"
                    />
                  </div>
                  <div class="ajustes2-form-group">
                    <label for="email" class="form-label">Correo</label>
                    <input
                      type="email"
                      id="email"
                      name="email"
                      class="form-input"
                      value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>"
                    />
                  </div>
                  <div class="ajustes2-form-group">
                    <label for="country" class="form-label">País</label>
                    <input
                      type="text"
                      id="country"
                      name="pais"
                      class="form-input"
                      value="<?php echo htmlspecialchars($usuario['pais'] ?? ''); ?>"
                    />
                  </div>
                  <div class="ajustes2-form-group">
                    <label for="gender" class="form-label">Género</label>
                    <div class="select-wrapper">
                      <select id="gender" name="genero" class="form-select">
                      <?php
                          $generoBD = strtolower(trim((string)($usuario['genero'] ?? '')));
                          if ($generoBD === '1') {
                              $generoBD = 'masculino';
                          } elseif ($generoBD === '2') {
                              $generoBD = 'femenino';
                          }
                      ?>
                      <option value="masculino"
                          <?= $generoBD === 'masculino' ? 'selected' : '' ?>>
                          Hombre
                      </option>
                      <option value="femenino"
                          <?= $generoBD === 'femenino' ? 'selected' : '' ?>>
                          Mujer
                      </option>
                      <option value="otro"
                          <?= $generoBD === 'otro' ? 'selected' : '' ?>>
                          Otro
                      </option>
                      <option value="prefiero-no-decir"
                          <?= $generoBD === 'prefiero-no-decir' ? 'selected' : '' ?>>
                          Prefiero no decir
                      </option>
                      </select>
                    </div>
                  </div>
                  <div class="button-group">
                    <button type="submit" class="btn btn-confirm">Confirmar</button>
                    <button type="button" class="btn btn-cancel">Cancelar</button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Panel Olvidé / Cambiar Contraseña -->
            <div class="panel superpuesto" id="forgot-pass">
              <div class="change-password-form-container">
                <h1 class="change-password-page-title">Cambiar contraseña</h1>
                <form action="actualizar_perfil.php" method="POST">
                  <div class="change-password-form-group">
                    <label for="current-password" class="change-password-form-label">Contraseña actual</label>
                    <input type="password" id="current-password" name="current_password" class="change-password-form-input" placeholder="Ingresa tu contraseña" required />
                    <a href="#" class="change-password-forgot-password">¿Olvidó su contraseña?</a>
                  </div>
                  <div class="change-password-form-group">
                    <label for="new-password" class="change-password-form-label">Nueva contraseña</label>
                    <input type="password" id="new-password" name="new_password" class="change-password-form-input" placeholder="Contraseña nueva" required />
                  </div>
                  <div class="change-password-form-group">
                    <label for="confirm-password" class="change-password-form-label">Confirme la nueva contraseña</label>
                    <input type="password" id="confirm-password" name="confirm_password" class="change-password-form-input" placeholder="Confirmación" required />
                  </div>
                  <div class="change-password-button-group">
                    <button type="submit" class="change-password-btn change-password-btn-confirm">Confirmar</button>
                  </div>
                </form>
              </div>
            </div>

          </div>
        </div>
      </div>
    </main>

    <!-- Overlay de Cierre de Sesión -->
    <div id="logout-overlay" style="display:none;position:fixed;z-index:9999;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;">
      <div style="background:var(--bg);padding:2rem 2.5rem;border-radius:18px;box-shadow:0 4px 32px #0008;text-align:center;max-width:90vw;">
        <p style="color:var(--text);font-size:1.2rem;margin-bottom:1.5rem;">¿Seguro que quieres cerrar sesión?</p>
        <button id="logout-confirm" style="background:var(--bg);color:var(--text);border:none;padding:0.7rem 1.5rem;border-radius:8px;font-weight:600;font-size:1rem;margin-right:1rem;cursor:pointer; box-shadow: 0 0px 6px #0008;">Sí, cerrar sesión</button>
        <button id="logout-cancel" style="background:var(--btn);color:white;border:none;padding:0.7rem 1.5rem;border-radius:8px;font-weight:600;font-size:1rem;cursor:pointer;box-shadow: 0 0px 6px #0008;">Cancelar</button>
      </div>
    </div>

    <!-- Scripts de la Interfaz y PANELES -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Navegación de paneles
        const setupPanelToggle = (btnId, panelId) => {
          const btn = document.getElementById(btnId);
          if(btn) {
            btn.addEventListener("click", () => {
              document.querySelectorAll(".panel.superpuesto").forEach(p => p.classList.remove("visible"));
              document.getElementById(panelId).classList.add("visible");
            });
          }
        };

        setupPanelToggle("btnPerfil", "panel-info");
        setupPanelToggle("btnPrivacidad", "panel-privacidad");
        setupPanelToggle("btnNotificaciones", "panel-notificaciones");
        setupPanelToggle("btnAccesibilidad", "panel-accesibilidad");
        setupPanelToggle("btnTuInfo", "tu-cuenta");
        setupPanelToggle("btnCambiarContra", "forgot-pass");
        setupPanelToggle("btnUsuariosBloqueados", "personas-bloqueadas");
        setupPanelToggle("btnUsuariosSilenciados", "personas-silenciadas");
        setupPanelToggle("btnCuentasNotificacionesActivas", "cuentas-activas");

        //Lógica del modal de cierre de sesión
        var btn = document.querySelector('.btn-cancel');
        var overlay = document.getElementById('logout-overlay');
        var confirm = document.getElementById('logout-confirm');
        var cancel = document.getElementById('logout-cancel');
        if(btn && overlay && confirm && cancel) {
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            overlay.style.display = 'flex';
          });
          cancel.addEventListener('click', function() {
            overlay.style.display = 'none';
          });
          confirm.addEventListener('click', function() {
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('rol');
            window.location.href = 'logout.php';
          });
        }
      });

      //Claro-Oscuro
      function selectTheme(theme) {
        const lightBtn = document.getElementById('lightBtn');
        const darkBtn = document.getElementById('darkBtn');
        const body = document.body;
        if (theme === 'light') {
          body.classList.remove('dark-theme');
          body.classList.add('light-theme');
          if(lightBtn) lightBtn.classList.add('active');
          if(darkBtn) darkBtn.classList.remove('active');
          localStorage.setItem('darkMode', 'false');
        } else {
          body.classList.remove('light-theme');
          body.classList.add('dark-theme');
          if(darkBtn) darkBtn.classList.add('active');
          if(lightBtn) lightBtn.classList.remove('active');
          localStorage.setItem('darkMode', 'true');
        }
      }
    </script>
    <?php if ($passwordActualizada): ?>
      <script>alert('Tu contraseña se actualizó correctamente.');</script>
    <?php endif; ?>
  </body>
</html>