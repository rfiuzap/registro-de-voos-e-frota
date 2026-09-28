<?php
require_once __DIR__ . "/conexao.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    $conexaoSegura = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
    session_name("registro_voos_sessao");
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $conexaoSegura,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    ini_set("session.use_strict_mode", "1");
    session_start();
}

function usuarioLogado(): ?array
{
    return isset($_SESSION["usuario_id"]) ? ["id" => (int) $_SESSION["usuario_id"], "nome" => (string) $_SESSION["usuario_nome"]] : null;
}

function iniciarSessaoUsuario(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION["usuario_id"] = (int) $usuario["id"];
    $_SESSION["usuario_nome"] = $usuario["nome"];
    unset($_SESSION["csrf"]);
}

function tokenCsrf(): string
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . tokenCsrf() . '">';
}

function csrfValido(): bool
{
    $enviado = $_POST["csrf"] ?? ($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "");
    return is_string($enviado) && $enviado !== "" && hash_equals(tokenCsrf(), $enviado);
}

function urlLogin(string $voltar = ""): string
{
    $voltar = $voltar !== "" ? $voltar : basename($_SERVER["SCRIPT_NAME"] ?? "index.php");
    return "login.php?" . http_build_query(["msg" => "login_necessario", "voltar" => $voltar]);
}

// Aceita só nomes de páginas locais para evitar redirecionamento aberto.
function destinoAposLogin(): string
{
    $voltar = $_GET["voltar"] ?? ($_POST["voltar"] ?? "");
    return is_string($voltar) && preg_match('/^[a-z_]+\.php$/', $voltar) && is_file(__DIR__ . "/" . $voltar) ? $voltar : "planejamento.php";
}

// Visitantes navegam livremente (id 0); qualquer POST exige login e token CSRF válido.
function usuarioAtualId(bool $respostaJson = false): int
{
    $usuario = usuarioLogado();
    if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
        return $usuario["id"] ?? 0;
    }
    if (!$usuario) {
        if ($respostaJson) {
            http_response_code(401);
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["sucesso" => false, "erro" => "Faça login para salvar."]);
            exit;
        }
        header("Location: " . urlLogin());
        exit;
    }
    if (!csrfValido()) {
        http_response_code(403);
        if ($respostaJson) {
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["sucesso" => false, "erro" => "Requisição inválida. Recarregue a página."]);
        } else {
            echo "Requisição inválida. Recarregue a página e tente novamente.";
        }
        exit;
    }
    return $usuario["id"];
}

// Aeronaves próprias + públicas de outros usuários que não foram ocultadas.
function sqlAeronavesVisiveis(int $usuarioId, string $alias = "aeronaves"): string
{
    return "({$alias}.usuario_id = {$usuarioId} OR ({$alias}.visibilidade = 'publica' AND {$alias}.id NOT IN (SELECT aeronave_id FROM aeronaves_ocultas WHERE usuario_id = {$usuarioId})))";
}

// Voos guardam o nome da aeronave; escolhe uma única aeronave acessível com esse nome, priorizando a do próprio usuário.
function sqlJoinAeronaveDoVoo(int $usuarioId): string
{
    return "LEFT JOIN aeronaves ON aeronaves.id = (SELECT a2.id FROM aeronaves a2 WHERE CONCAT(a2.fabricante, ' ', a2.modelo) = voos.aeronave AND (a2.usuario_id = {$usuarioId} OR a2.visibilidade = 'publica') ORDER BY a2.usuario_id = {$usuarioId} DESC, a2.id LIMIT 1)";
}

function ipCliente(): string
{
    return substr($_SERVER["REMOTE_ADDR"] ?? "0.0.0.0", 0, 45);
}

// URL pública vem da configuração para não confiar no cabeçalho Host em links enviados por e-mail.
function urlBaseSistema(): string
{
    global $config;
    if (!empty($config["url_base"])) {
        return rtrim($config["url_base"], "/");
    }
    $protocolo = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    return $protocolo . "://" . ($_SERVER["HTTP_HOST"] ?? "localhost") . rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/"), "/\\");
}

function enviarEmail(string $para, string $assunto, string $html): bool
{
    global $config;
    $remetente = $config["email_remetente"] ?? "nao-responda@localhost";
    $nomeRemetente = $config["nome_remetente"] ?? "Registro de Voos";
    $cabecalhos = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: =?UTF-8?B?" . base64_encode($nomeRemetente) . "?= <" . $remetente . ">",
        "Reply-To: " . $remetente,
    ];
    return mail($para, "=?UTF-8?B?" . base64_encode($assunto) . "?=", $html, implode("\r\n", $cabecalhos), "-f" . $remetente);
}
