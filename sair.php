<?php
require "auth.php";

// Logout só via POST com CSRF para não ser disparado por links externos.
if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST" && csrfValido()) {
    $_SESSION = [];
    $parametros = session_get_cookie_params();
    setcookie(session_name(), "", time() - 3600, $parametros["path"], $parametros["domain"], $parametros["secure"], $parametros["httponly"]);
    session_destroy();
    header("Location: login.php?msg=saiu");
    exit;
}
header("Location: planejamento.php");
