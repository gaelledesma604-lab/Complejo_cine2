<?php
/* ==========================================================
   usuarios.php: reemplaza a js/usuarios.js.
   Antes la "sesión" vivía en el navegador (localStorage) y era solo
   una demostración. Ahora los usuarios se guardan en la tabla Usuarios
   y la sesión la maneja PHP en el servidor. Las contraseñas se guardan
   con password_hash() y se comprueban con password_verify(): nunca
   se guarda la contraseña en texto.
   ========================================================== */

/* Devuelve los datos de quien tiene la sesión abierta, o null si nadie la tiene. */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/* Abre la sesión de una persona. Se cambia el identificador de sesión al iniciar
   para evitar que alguien reutilice uno anterior (fijación de sesión). */
function abrir_sesion_usuario(int $id, string $nombre, string $rol): void
{
    session_regenerate_id(true);
    $_SESSION['usuario'] = ['id' => $id, 'nombre' => $nombre, 'rol' => $rol];
}

/* A dónde ir después de iniciar sesión o crear la cuenta. Si la persona venía de una página que pide
   sesión (por ejemplo, comprar), esa página se guardó en $_SESSION['volver']. Solo se acepta una dirección
   interna con forma "pages/algo.php?clave=valor": así nadie puede mandar a otra web con un enlace armado. */
function destino_tras_login(): string
{
    $volver = (string)($_SESSION['volver'] ?? '');
    unset($_SESSION['volver']);
    return preg_match('#^pages/[a-z_]+\.php(\?[A-Za-z0-9=&_]*)?$#', $volver) ? $volver : 'index.php';
}

/* Token secreto que viaja dentro de cada formulario. Sirve para comprobar que el formulario
   lo envió esta misma persona desde nuestra página y no otra web (protección CSRF). */
function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* Cantidad de caracteres (cuenta bien las letras con tilde). */
function largo_texto(string $texto): int
{
    return (int)preg_match_all('/./us', $texto);
}

/* Dibuja los botones de la cabecera según haya o no una sesión abierta. */
function pintar_sesion(): string
{
    $usuario = usuario_actual();
    if ($usuario) {
        $salir = e(BASE . 'pages/logout.php');
        return '<span class="saludo">Hola, ' . e($usuario['nombre']) . '</span>'
             . '<a class="btn-sec" href="' . e(BASE . 'pages/mis_entradas.php') . '">Mis entradas</a>'
             . '<form method="post" action="' . $salir . '"><button type="submit" class="btn-sec">Cerrar sesión</button></form>';
    }
    return '<a class="btn-sec" href="' . e(BASE . 'pages/login.php') . '">Iniciar sesión</a>'
         . '<a class="btn-prim" href="' . e(BASE . 'pages/registro.php') . '">Crear cuenta</a>';
}

/* INICIO DE SESIÓN: valida los datos, busca el correo en la base y compara la contraseña
   con el hash guardado. Si todo sale bien redirige al inicio; si no, devuelve el mensaje de error. */
function procesar_login(string $correo, string $password): string
{
    if (!csrf_valido()) return 'La sesión del formulario venció. Inténtalo de nuevo.';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) return 'Escribe un correo válido.';
    if ($password === '') return 'Escribe tu contraseña.';

    $consulta = conectar()->prepare(
        'SELECT u.ID_Usuario, u.nombre_usuario, u.password_hash, u.estado_usuario, r.nombre_rol
         FROM Usuarios u JOIN Roles r ON r.ID_Rol = u.ID_Rol
         WHERE u.correo_usuario = :correo'
    );
    $consulta->execute([':correo' => $correo]);
    $fila = $consulta->fetch();

    // Un solo mensaje para "correo inexistente" y "contraseña incorrecta":
    // así nadie puede averiguar qué correos tienen cuenta.
    if (!$fila || (int)$fila['estado_usuario'] !== 1 || !password_verify($password, $fila['password_hash'])) {
        return 'Correo o contraseña incorrectos.';
    }

    abrir_sesion_usuario((int)$fila['ID_Usuario'], $fila['nombre_usuario'], $fila['nombre_rol']);
    header('Location: ' . BASE . destino_tras_login());
    exit;
}

/* ¿Ya existe alguien con ese valor en esa columna? ($columna viene siempre de nuestro código, nunca del formulario) */
function usuario_existe(string $columna, string $valor): bool
{
    $consulta = conectar()->prepare("SELECT 1 FROM Usuarios WHERE $columna = :valor");
    $consulta->execute([':valor' => $valor]);
    return (bool)$consulta->fetchColumn();
}

/* REGISTRO: valida, guarda al usuario con rol "usuario" y abre su sesión.
   Devuelve el mensaje de error si algo falla. */
function procesar_registro(string $nombre, string $correo, string $password, string $password2): string
{
    if (!csrf_valido()) return 'La sesión del formulario venció. Inténtalo de nuevo.';
    if (largo_texto($nombre) < 3) return 'El nombre de usuario debe tener al menos 3 caracteres.';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) return 'Escribe un correo válido.';
    if (strlen($password) < 6) return 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2) return 'Las contraseñas no coinciden.';
    if (usuario_existe('correo_usuario', $correo)) return 'Ese correo ya tiene una cuenta.';
    if (usuario_existe('nombre_usuario', $nombre)) return 'Ese nombre de usuario ya está en uso.';

    $pdo = conectar();
    try {
        $consulta = $pdo->prepare(
            "INSERT INTO Usuarios (nombre_usuario, correo_usuario, password_hash, ID_Rol)
             VALUES (:nombre, :correo, :hash, (SELECT ID_Rol FROM Roles WHERE nombre_rol = 'usuario'))"
        );
        $consulta->execute([
            ':nombre' => $nombre,
            ':correo' => $correo,
            ':hash'   => password_hash($password, PASSWORD_DEFAULT), // se guarda el hash, no la contraseña
        ]);
    } catch (PDOException $error) {
        return 'No se pudo crear la cuenta. Inténtalo de nuevo.';
    }

    abrir_sesion_usuario((int)$pdo->lastInsertId(), $nombre, 'usuario');
    header('Location: ' . BASE . destino_tras_login());
    exit;
}

/* Formulario de inicio de sesión (antes era una ventana <dialog>; ahora es una página). */
function formulario_login(string $error, string $correo): string
{
    $error = e($error);
    $correo = e($correo);
    $csrf = e(token_csrf());
    $registro = e(BASE . 'pages/registro.php');
    $cancelar = e(BASE . 'index.php');

    return <<<HTML
        <section class="modal" aria-labelledby="t-login">
          <h2 id="t-login">Iniciar sesión</h2>
          <form method="post" action="login.php" novalidate>
            <input type="hidden" name="csrf" value="{$csrf}">
            <div class="campo"><label for="login-email">Correo electrónico</label>
              <input id="login-email" name="email" type="email" value="{$correo}" autocomplete="email"></div>
            <div class="campo"><label for="login-pass">Contraseña</label>
              <input id="login-pass" name="password" type="password" autocomplete="current-password"></div>
            <p class="error" role="alert">{$error}</p>
            <div class="fila-botones">
              <a class="btn-sec" href="{$cancelar}">Cancelar</a>
              <button type="submit" class="btn-prim">Iniciar sesión</button>
            </div>
          </form>
          <p class="cambiar">¿No tienes cuenta? <a href="{$registro}">Crear cuenta</a></p>
        </section>
        HTML;
}

/* Formulario de registro. */
function formulario_registro(string $error, string $nombre, string $correo): string
{
    $error = e($error);
    $nombre = e($nombre);
    $correo = e($correo);
    $csrf = e(token_csrf());
    $login = e(BASE . 'pages/login.php');
    $cancelar = e(BASE . 'index.php');

    return <<<HTML
        <section class="modal" aria-labelledby="t-reg">
          <h2 id="t-reg">Crear cuenta</h2>
          <form method="post" action="registro.php" novalidate>
            <input type="hidden" name="csrf" value="{$csrf}">
            <div class="campo"><label for="reg-nombre">Nombre de usuario</label>
              <input id="reg-nombre" name="nombre" type="text" value="{$nombre}" autocomplete="username"></div>
            <div class="campo"><label for="reg-email">Correo electrónico</label>
              <input id="reg-email" name="email" type="email" value="{$correo}" autocomplete="email"></div>
            <div class="campo"><label for="reg-pass">Contraseña</label>
              <input id="reg-pass" name="password" type="password" autocomplete="new-password"></div>
            <div class="campo"><label for="reg-pass2">Confirmar contraseña</label>
              <input id="reg-pass2" name="password2" type="password" autocomplete="new-password"></div>
            <p class="error" role="alert">{$error}</p>
            <div class="fila-botones">
              <a class="btn-sec" href="{$cancelar}">Cancelar</a>
              <button type="submit" class="btn-prim">Crear cuenta</button>
            </div>
          </form>
          <p class="cambiar">¿Ya tienes cuenta? <a href="{$login}">Iniciar sesión</a></p>
        </section>
        HTML;
}
