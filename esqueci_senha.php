<?php
require "auth.php";
require "auth_layout.php";

if (ambienteDemo()) {
    header("Location: login.php");
    exit;
}

$enviado = false;
$erro = "";
$linkDesenvolvimento = "";

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));

    if (!csrfValido()) {
        $erro = "Sessão expirada. Tente novamente.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Informe um e-mail válido.";
    } else {
        $stmt = $conn->prepare("SELECT u.id, u.nome, (SELECT COUNT(*) FROM senha_resets r WHERE r.usuario_id = u.id AND r.criado_em > NOW() - INTERVAL 1 HOUR) FROM usuarios u WHERE u.email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($usuarioId, $usuarioNome, $pedidosRecentes);
        $encontrado = $stmt->fetch();
        $stmt->close();

        if ($encontrado && $pedidosRecentes < 3) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash("sha256", $token);
            $stmt = $conn->prepare("INSERT INTO senha_resets (usuario_id, token_hash, expira_em) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)");
            $stmt->bind_param("is", $usuarioId, $tokenHash);
            $stmt->execute();
            $stmt->close();

            $link = urlBaseSistema() . "/redefinir_senha.php?token=" . $token;
            $nomeSeguro = htmlspecialchars($usuarioNome);
            $linkSeguro = htmlspecialchars($link);
            $html = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#0f172a">
    <h2 style="color:#1d4ed8">Redefinição de senha</h2>
    <p>Olá, {$nomeSeguro}.</p>
    <p>Recebemos um pedido para redefinir a senha da sua conta no Registro de Voos &amp; Frota.</p>
    <p><a href="{$linkSeguro}" style="display:inline-block;padding:12px 22px;background:#1d4ed8;color:#fff;border-radius:8px;text-decoration:none;font-weight:bold">Criar nova senha</a></p>
    <p style="font-size:13px;color:#64748b">O link expira em 1 hora. Se você não fez este pedido, ignore este e-mail: sua senha continua a mesma.</p>
</div>
HTML;
            $emailEnviado = enviarEmail($email, "Redefinição de senha - Registro de Voos", $html);
            // Em ambiente local o mail() normalmente falha; exibe o link apenas fora de produção.
            if (!$emailEnviado && (empty($config) || !empty($config["modo_desenvolvimento"]))) {
                $linkDesenvolvimento = $link;
            }
        }
        $enviado = true;
    }
}

layoutAuthInicio("Esqueci minha senha", "Enviaremos um link para você criar uma nova senha");
?>
            <?php if ($erro): ?><p class="erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <?php if ($enviado): ?>
                <p class="sucesso">Se o e-mail estiver cadastrado, você receberá em instantes um link para redefinir a senha. Verifique também a caixa de spam.</p>
                <?php if ($linkDesenvolvimento): ?>
                    <p class="aviso-info">Ambiente local: o e-mail não pôde ser enviado. <a href="<?= htmlspecialchars($linkDesenvolvimento) ?>">Abrir link de redefinição</a></p>
                <?php endif; ?>
            <?php else: ?>
                <form method="post" class="auth-form" novalidate>
                    <?= campoCsrf() ?>
                    <label>E-mail cadastrado
                        <input type="email" name="email" autocomplete="email" required autofocus>
                    </label>
                    <button type="submit">Enviar link</button>
                </form>
            <?php endif; ?>
            <p class="auth-alternativa"><a href="login.php">Voltar para o login</a></p>
<?php layoutAuthFim(); ?>
