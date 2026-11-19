<?php
/* CERRAR SESIÓN: solo actúa si llega por POST (el botón "Cerrar sesión" de la cabecera es un formulario).
   Quita al usuario de la sesión pero conserva la ubicación y el cine elegidos. */
require __DIR__ . '/../php/main.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['usuario']);
    session_regenerate_id(true);
}
header('Location: ../index.php');
exit;
