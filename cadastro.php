<?php
require "auth.php";
require "auth_layout.php";

// Na demo não há cadastro nem recuperação de senha.
if (ambienteDemo()) {
    header("Location: login.php");
    exit;
}

if (usuarioLogado()) {
    header("Location: " . destinoAposLogin());
    exit;
}

$erro = "";
$nome = "";
$email = "";
$voltar = destinoAposLogin();

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $nome = trim($_POST["nome"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $senha = $_POST["senha"] ?? "";
    $confirmacao = $_POST["confirmacao"] ?? "";

    if (!csrfValido()) {
        $erro = "Sessão expirada. Tente novamente.";
    } elseif ($nome === "" || mb_strlen($nome) > 100) {
        $erro = "Informe seu nome.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $erro = "Informe um e-mail válido.";
    } elseif (strlen($senha) < 8) {
        $erro = "A senha deve ter pelo menos 8 caracteres.";
    } elseif ($senha !== $confirmacao) {
        $erro = "As senhas não conferem.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $jaExiste = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();

        if ($jaExiste) {
            $erro = "Este e-mail já está cadastrado. Use \"Esqueci minha senha\" se não lembrar a senha.";
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nome, $email, $hash);
            $stmt->execute();
            $novoId = $conn->insert_id;
            $stmt->close();

            // O primeiro usuário herda os registros criados antes do controle de usuários.
            $totalUsuarios = (int) $conn->query("SELECT COUNT(*) FROM usuarios")->fetch_row()[0];
            if ($totalUsuarios === 1) {
                foreach (["voos", "planejamentos", "aeronaves"] as $tabela) {
                    $conn->query("UPDATE {$tabela} SET usuario_id = {$novoId} WHERE usuario_id IS NULL");
                }
            }

            iniciarSessaoUsuario(["id" => $novoId, "nome" => $nome]);
            header("Location: " . $voltar);
            exit;
        }
    }
}

layoutAuthInicio("Criar conta", "Cadastre-se para registrar seus voos");
?>
            <?php if ($erro): ?><p class="erro" role="alert"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
            <form method="post" class="auth-form" novalidate>
                <?= campoCsrf() ?>
                <input type="hidden" name="voltar" value="<?= htmlspecialchars($voltar) ?>">
                <label>Nome
                    <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" maxlength="100" autocomplete="name" required autofocus>
                </label>
                <label>E-mail
                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" maxlength="190" autocomplete="email" required>
                </label>
                <label>Senha
                    <input type="password" name="senha" minlength="8" autocomplete="new-password" required>
                    <small class="auth-dica">Mínimo de 8 caracteres</small>
                </label>
                <label>Confirmar senha
                    <input type="password" name="confirmacao" minlength="8" autocomplete="new-password" required>
                </label>
                <button type="submit">Criar conta</button>
            </form>
            <p class="auth-alternativa">Já tem conta? <a href="login.php?voltar=<?= urlencode($voltar) ?>">Entrar</a></p>
<?php layoutAuthFim(); ?>
