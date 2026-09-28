<?php
function layoutAuthInicio(string $titulo, string $subtitulo): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= htmlspecialchars($titulo) ?> | Registro de Voos</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body class="pagina-auth">
    <main class="auth-container">
        <div class="auth-marca">
            <div class="logo-aplicacao" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg></div>
            <span>Registro de Voos &amp; Frota</span>
        </div>
        <div class="auth-card">
            <h1><?= htmlspecialchars($titulo) ?></h1>
            <p class="auth-subtitulo"><?= htmlspecialchars($subtitulo) ?></p>
    <?php
}

function layoutAuthFim(): void
{
    ?>
        </div>
    </main>
    <?php include __DIR__ . "/rodape.php"; ?>
</body>
</html>
    <?php
}
