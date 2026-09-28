<?php
require "auth.php";
require "auth_layout.php";

if (ambienteDemo()) {
    header("Location: login.php");
    exit;
}

$token = $_GET["token"] ?? ($_POST["token"] ?? "");
$token = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? $token : "";
$erro = "";
$reset = null;

if ($token !== "") {
    $tokenHash = hash("sha256", $token);
    $stmt = $conn->prepare("SELECT id, usuario_id FROM senha_resets WHERE token_hash = ? AND usado_em IS NULL AND expira_em > NOW()");
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($reset && ($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $senha = $_POST["senha"] ?? "";
    $confirmacao = $_POST["confirmacao"] ?? "";

    if (!csrfValido()) {
        $erro = "Sessão expirada. Tente novamente.";
    } elseif (strlen($senha) < 8) {
        $erro = "A senha deve ter pelo menos 8 caracteres.";
    } elseif ($senha !== $confirmacao) {
        $erro = "As senhas não conferem.";
    } else {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $usuarioId = (int) $reset["usuario_id"];
        $stmt = $conn->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $usuarioId);
        $stmt->execute();
        $stmt->close();
        // Invalida este e qualquer outro link pendente do mesmo usuário.
        $stmt = $conn->prepare("UPDATE senha_resets SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $stmt->close();
        header("Location: login.php?msg=senha_redefinida");
        exit;
    }
}

layoutAuthInicio("Nova senha", "Escolha uma nova senha para sua conta");
?>
            <?php if (!$reset): ?>
                <p class="erro" role="alert">Link inválido ou expirado. Solicite um novo.</p>
                <p class="auth-alternativa"><a href="esqueci_senha.php">Solicitar novo link</a></p>
            <?php else: ?>
                <?php if ($erro): ?><p class="erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
                <form method="post" class="auth-form" novalidate>
                    <?= campoCsrf() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <label>Nova senha
                        <input type="password" name="senha" minlength="8" autocomplete="new-password" required autofocus>
                        <small class="auth-dica">Mínimo de 8 caracteres</small>
                    </label>
                    <label>Confirmar nova senha
                        <input type="password" name="confirmacao" minlength="8" autocomplete="new-password" required>
                    </label>
                    <button type="submit">Salvar nova senha</button>
                </form>
            <?php endif; ?>
<?php layoutAuthFim(); ?>
