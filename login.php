<?php
require "auth.php";
require "auth_layout.php";

if (usuarioLogado()) {
    header("Location: " . destinoAposLogin());
    exit;
}

$erro = "";
$email = "";
$voltar = destinoAposLogin();
$mensagem = match ($_GET["msg"] ?? "") {
    "senha_redefinida" => "Senha redefinida com sucesso. Entre com a nova senha.",
    "saiu" => "Você saiu do sistema.",
    "login_necessario" => "Faça login para salvar suas atividades.",
    default => "",
};

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    if (ambienteDemo() && $email === DEMO_LOGIN) {
        $email = DEMO_EMAIL;
    }
    $senha = $_POST["senha"] ?? "";
    $ip = ipCliente();

    $stmt = $conn->prepare("SELECT (SELECT COUNT(*) FROM login_tentativas WHERE ip = ? AND criado_em > NOW() - INTERVAL 15 MINUTE), (SELECT COUNT(*) FROM login_tentativas WHERE email = ? AND criado_em > NOW() - INTERVAL 15 MINUTE)");
    $stmt->bind_param("ss", $ip, $email);
    $stmt->execute();
    $stmt->bind_result($falhasIp, $falhasEmail);
    $stmt->fetch();
    $stmt->close();

    if (!csrfValido()) {
        $erro = "Sessão expirada. Tente novamente.";
    } elseif (!ambienteDemo() && $email === DEMO_EMAIL) {
        $erro = "O usuário de demonstração só está disponível no ambiente demo.";
    } elseif ($falhasIp >= 20 || $falhasEmail >= 5) {
        $erro = "Muitas tentativas. Aguarde 15 minutos e tente novamente.";
    } else {
        $stmt = $conn->prepare("SELECT id, nome, senha_hash FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuario && password_verify($senha, $usuario["senha_hash"])) {
            if (password_needs_rehash($usuario["senha_hash"], PASSWORD_DEFAULT)) {
                $novoHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
                $stmt->bind_param("si", $novoHash, $usuario["id"]);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $conn->prepare("DELETE FROM login_tentativas WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
            iniciarSessaoUsuario($usuario);
            header("Location: " . $voltar);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO login_tentativas (ip, email) VALUES (?, ?)");
        $stmt->bind_param("ss", $ip, $email);
        $stmt->execute();
        $stmt->close();
        $erro = "E-mail ou senha incorretos.";
    }
}

layoutAuthInicio("Entrar", "Acesse seus voos, aeronaves e planejamentos");
?>
            <?php if ($mensagem): ?><p class="sucesso"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
            <?php if ($erro): ?><p class="erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <?php if (ambienteDemo()): ?><p class="sucesso"><strong>Acesso Demo</strong><br>Usuário: demo<br>Senha: demo123</p><?php endif; ?>
            <form method="post" class="auth-form" novalidate>
                <?= campoCsrf() ?>
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltar) ?>">
                <label><?= ambienteDemo() ? "E-mail ou usuário" : "E-mail" ?>
                    <input type="<?= ambienteDemo() ? "text" : "email" ?>" name="email" value="<?= htmlspecialchars($email) ?>" autocomplete="email" required autofocus>
                </label>
                <label>
                    <span class="auth-label-linha">Senha <?php if (!ambienteDemo()): ?><a href="esqueci_senha.php">Esqueci minha senha</a><?php endif; ?></span>
                    <input type="password" name="senha" autocomplete="current-password" required>
                </label>
                <button type="submit">Entrar</button>
            </form>
            <?php if (!ambienteDemo()): ?><p class="auth-alternativa">Ainda não tem conta? <a href="cadastro.php?voltar=<?= urlencode($voltar) ?>">Criar conta</a></p><?php endif; ?>
            <p class="auth-alternativa"><a href="<?= htmlspecialchars($voltar) ?>">Continuar sem login</a></p>
<?php layoutAuthFim(); ?>
