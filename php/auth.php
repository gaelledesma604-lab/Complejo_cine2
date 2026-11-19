<?php
// php/auth.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

$entrada = json_decode(file_get_contents('php://input'), true) ?? [];
$accion  = $entrada['accion'] ?? ($_GET['accion'] ?? '');

try {
    $pdo = obtenerConexion();

    if ($accion === 'registro') {
        $nombre = trim($entrada['nombre'] ?? '');
        $email  = trim($entrada['email'] ?? '');
        $pass   = $entrada['pass'] ?? '';

        if (strlen($nombre) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO Usuarios (nombre, email, contrasenna) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $email, $pass]);

        $_SESSION['usuario'] = [
            'id'     => (int) $pdo->lastInsertId(),
            'nombre' => $nombre,
            'email'  => $email
        ];
        echo json_encode(['ok' => true, 'usuario' => $_SESSION['usuario']], JSON_UNESCAPED_UNICODE);
    }
    elseif ($accion === 'login') {
        $email = trim($entrada['email'] ?? '');
        $pass  = $entrada['pass'] ?? '';

        $stmt = $pdo->prepare("SELECT ID_Usuario, nombre, email, contrasenna FROM Usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || $user['contrasenna'] !== $pass) {
            echo json_encode(['ok' => false, 'error' => 'Correo o contraseña incorrectos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $_SESSION['usuario'] = [
            'id'     => (int) $user['ID_Usuario'],
            'nombre' => $user['nombre'],
            'email'  => $user['email']
        ];
        echo json_encode(['ok' => true, 'usuario' => $_SESSION['usuario']], JSON_UNESCAPED_UNICODE);
    }
    elseif ($accion === 'estado') {
        echo json_encode(['ok' => true, 'usuario' => $_SESSION['usuario'] ?? null], JSON_UNESCAPED_UNICODE);
    }
    elseif ($accion === 'salir') {
        session_destroy();
        echo json_encode(['ok' => true]);
    }
} catch (PDOException $e) {
    $msg = str_contains($e->getMessage(), 'UNIQUE')
        ? 'Ese correo ya tiene una cuenta.'
        : 'Error en la base de datos: ' . $e->getMessage();
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}