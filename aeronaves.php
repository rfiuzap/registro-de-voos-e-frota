<?php
if (!ob_start("ob_gzhandler")) ob_start();
require "auth.php";
$usuarioId = usuarioAtualId();

$mensagem = "";
$erro = "";
$aeronaveEditar = null;
$listasAeronaves = ["minhas", "outros", "ocultas"];
$listaAtual = in_array($_GET["lista"] ?? "", $listasAeronaves, true) ? $_GET["lista"] : "minhas";
if (!$usuarioId) {
    $listaAtual = "outros";
}

if (isset($_GET["editar"])) {
    $idEditar = (int) $_GET["editar"];
    $stmt = $conn->prepare("SELECT * FROM aeronaves WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $idEditar, $usuarioId);
    $stmt->execute();
    $aeronaveEditar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $acao = $_POST["acao"] ?? "salvar";
    $id = (int) ($_POST["id"] ?? 0);

    if (in_array($acao, ["ocultar", "mostrar"], true) && $id > 0) {
        if ($acao === "ocultar") {
            $stmt = $conn->prepare("INSERT IGNORE INTO aeronaves_ocultas (usuario_id, aeronave_id) SELECT ?, id FROM aeronaves WHERE id = ? AND visibilidade = 'publica' AND (usuario_id IS NULL OR usuario_id <> ?)");
            $stmt->bind_param("iii", $usuarioId, $id, $usuarioId);
        } else {
            $stmt = $conn->prepare("DELETE FROM aeronaves_ocultas WHERE usuario_id = ? AND aeronave_id = ?");
            $stmt->bind_param("ii", $usuarioId, $id);
        }
        $stmt->execute();
        $stmt->close();
        header("Location: aeronaves.php?lista=" . ($acao === "ocultar" ? "outros" : "ocultas"));
        exit;
    }

    if (in_array($acao, ["editar", "excluir"], true) && (!$aeronaveEditar || (int) $aeronaveEditar["id"] !== $id)) {
        http_response_code(403);
        exit("Você só pode alterar aeronaves cadastradas por você.");
    }

    if ($acao === "excluir" && $id > 0) {
        $fotoExcluir = $aeronaveEditar["foto"] ?? null;
        $stmt = $conn->prepare("DELETE FROM aeronaves WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $id, $usuarioId);
        $stmt->execute();
        $stmt->close();
        if ($fotoExcluir && is_file(__DIR__ . "/" . $fotoExcluir)) {
            unlink(__DIR__ . "/" . $fotoExcluir);
        }
        header("Location: aeronaves.php");
        exit;
    }

    $fabricante = trim($_POST["fabricante"] ?? "");
    $modelo = trim($_POST["modelo"] ?? "");
    $ano = (int) ($_POST["ano"] ?? 0);
    $velocidadeCruzeiro = (float) ($_POST["velocidade_cruzeiro"] ?? 0);
    $velocidadeSubida = (float) str_replace(".", "", $_POST["velocidade_subida"] ?? "0");
    $razaoSubida = (float) str_replace(".", "", $_POST["razao_subida"] ?? "0");
    $tetoOperacional = (int) str_replace(".", "", $_POST["teto_operacional"] ?? "0");
    $altitudeCruzeiroIdeal = (int) str_replace(".", "", $_POST["altitude_cruzeiro_ideal"] ?? "0");
    $autonomia = trim($_POST["autonomia"] ?? "");
    $capacidadeTanque = (float) str_replace(".", "", $_POST["capacidade_tanque"] ?? "0");
    $pesoVazio = (float) str_replace(".", "", $_POST["peso_vazio"] ?? "0");
    $pesoMaximoDecolagem = (float) str_replace(".", "", $_POST["peso_maximo_decolagem"] ?? "0");
    $cargaUtil = (float) str_replace(".", "", $_POST["carga_util"] ?? "0");
    $tipoCombustivel = $_POST["tipo_combustivel"] ?? "";
    $potencia = (float) ($_POST["potencia"] ?? 0);
    $motor = trim($_POST["motor"] ?? "");
    $assentos = (int) str_replace(".", "", $_POST["assentos"] ?? "0");
    $pressurizado = $_POST["pressurizado"] ?? "";
    $consumoGph = (float) ($_POST["consumo_gph_aeronave"] ?? 0);
    $valor = (float) str_replace(".", "", $_POST["valor"] ?? 0);
    $visibilidade = ($_POST["visibilidade"] ?? "") === "privada" ? "privada" : "publica";
    $foto = $aeronaveEditar["foto"] ?? null;
    $autonomiaValida = preg_match("/^([0-9]{2}):([0-5][0-9])$/", $autonomia);

    if (isset($_FILES["foto"]) && $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $tiposPermitidos = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
        $tipoFoto = mime_content_type($_FILES["foto"]["tmp_name"]);
        if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK || $_FILES["foto"]["size"] > 5 * 1024 * 1024 || !isset($tiposPermitidos[$tipoFoto])) {
            $erro = "A foto deve ser JPG, PNG ou WEBP e ter no máximo 5 MB.";
        } else {
            $nomeFoto = bin2hex(random_bytes(12)) . "." . $tiposPermitidos[$tipoFoto];
            $caminhoFoto = "uploads/" . $nomeFoto;
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], __DIR__ . "/" . $caminhoFoto)) {
                if ($foto && is_file(__DIR__ . "/" . $foto)) {
                    unlink(__DIR__ . "/" . $foto);
                }
                $foto = $caminhoFoto;
            } else {
                $erro = "Não foi possível salvar a foto.";
            }
        }
    }

    if ($erro === "" && ($fabricante === "" || $modelo === "" || $ano < 1 || $ano > 9999 || $velocidadeCruzeiro < 0 || $velocidadeSubida < 0 || $razaoSubida < 0 || $tetoOperacional < 0 || $altitudeCruzeiroIdeal < 0 || !$autonomiaValida || $capacidadeTanque < 0 || $pesoVazio < 0 || $pesoMaximoDecolagem < 0 || $cargaUtil < 0 || !in_array($tipoCombustivel, ["Avgas", "JetA"], true) || $potencia < 0 || $motor === "" || $assentos < 0 || !in_array($pressurizado, ["Sim", "Não"], true) || $consumoGph < 0 || $valor < 0)) {
        $erro = "Preencha corretamente os dados da aeronave.";
    } elseif ($acao === "editar" && $id > 0) {
        $nomeAeronaveAnterior = trim(($aeronaveEditar["fabricante"] ?? "") . " " . ($aeronaveEditar["modelo"] ?? ""));
        $nomeAeronaveAtual = $fabricante . " " . $modelo;
        $stmt = $conn->prepare("UPDATE aeronaves SET fabricante=?, modelo=?, ano=?, velocidade_cruzeiro=?, velocidade_subida=?, razao_subida=?, teto_operacional=?, altitude_cruzeiro_ideal=?, autonomia=?, capacidade_tanque=?, peso_vazio=?, peso_maximo_decolagem=?, carga_util=?, tipo_combustivel=?, potencia=?, motor=?, assentos=?, pressurizado=?, consumo_gph=?, valor=?, foto=?, visibilidade=? WHERE id=? AND usuario_id=?");
        $stmt->bind_param("ssidddiisddddsdsisddssii", $fabricante, $modelo, $ano, $velocidadeCruzeiro, $velocidadeSubida, $razaoSubida, $tetoOperacional, $altitudeCruzeiroIdeal, $autonomia, $capacidadeTanque, $pesoVazio, $pesoMaximoDecolagem, $cargaUtil, $tipoCombustivel, $potencia, $motor, $assentos, $pressurizado, $consumoGph, $valor, $foto, $visibilidade, $id, $usuarioId);
        $stmt->execute();
        $stmt->close();
        if ($nomeAeronaveAnterior !== "" && $nomeAeronaveAnterior !== $nomeAeronaveAtual) {
            // Aeronave pública pode estar nos voos de outros usuários; privada só nos do dono.
            if ($aeronaveEditar["visibilidade"] === "publica") {
                $stmt = $conn->prepare("UPDATE voos SET aeronave = ? WHERE aeronave = ?");
                $stmt->bind_param("ss", $nomeAeronaveAtual, $nomeAeronaveAnterior);
            } else {
                $stmt = $conn->prepare("UPDATE voos SET aeronave = ? WHERE aeronave = ? AND usuario_id = ?");
                $stmt->bind_param("ssi", $nomeAeronaveAtual, $nomeAeronaveAnterior, $usuarioId);
            }
            $stmt->execute();
            $stmt->close();
        }
        header("Location: aeronaves.php");
        exit;
    } else {
        $stmt = $conn->prepare("INSERT INTO aeronaves (fabricante, modelo, ano, velocidade_cruzeiro, velocidade_subida, razao_subida, teto_operacional, altitude_cruzeiro_ideal, autonomia, capacidade_tanque, peso_vazio, peso_maximo_decolagem, carga_util, tipo_combustivel, potencia, motor, assentos, pressurizado, consumo_gph, valor, foto, visibilidade, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssidddiisddddsdsisddssi", $fabricante, $modelo, $ano, $velocidadeCruzeiro, $velocidadeSubida, $razaoSubida, $tetoOperacional, $altitudeCruzeiroIdeal, $autonomia, $capacidadeTanque, $pesoVazio, $pesoMaximoDecolagem, $cargaUtil, $tipoCombustivel, $potencia, $motor, $assentos, $pressurizado, $consumoGph, $valor, $foto, $visibilidade, $usuarioId);
        $stmt->execute();
        $stmt->close();
        $mensagem = "Aeronave cadastrada com sucesso.";
    }
}

$colunasOrdenacao = [
    "modelo" => "modelo",
    "ano" => "ano",
    "velocidade" => "velocidade_cruzeiro",
    "teto" => "teto_operacional",
    "altitude_ideal" => "altitude_cruzeiro_ideal",
    "consumo" => "consumo_gph",
    "autonomia" => "autonomia",
    "autonomia_nm" => "(CASE WHEN consumo_gph > 0 THEN (capacidade_tanque / consumo_gph) * velocidade_cruzeiro ELSE 0 END)",
    "carga_util" => "carga_util",
    "assentos" => "assentos",
    "valor" => "valor"
];
$ordenarPor = $_GET["ordenar"] ?? "modelo";
$ordenarPor = array_key_exists($ordenarPor, $colunasOrdenacao) ? $ordenarPor : "modelo";
$direcao = strtoupper($_GET["direcao"] ?? "ASC") === "DESC" ? "DESC" : "ASC";
$sqlOcultasUsuario = "SELECT aeronave_id FROM aeronaves_ocultas WHERE usuario_id = {$usuarioId}";
$sqlDeOutros = "visibilidade = 'publica' AND (usuario_id IS NULL OR usuario_id <> {$usuarioId})";
$filtrosLista = [
    "minhas" => "usuario_id = {$usuarioId}",
    "outros" => "{$sqlDeOutros} AND id NOT IN ({$sqlOcultasUsuario})",
    "ocultas" => "{$sqlDeOutros} AND id IN ({$sqlOcultasUsuario})",
];
$contagemListas = $conn->query("SELECT SUM({$filtrosLista["minhas"]}), SUM({$filtrosLista["outros"]}), SUM({$filtrosLista["ocultas"]}) FROM aeronaves")->fetch_row();
$contagemListas = array_combine($listasAeronaves, array_map("intval", $contagemListas));
$aeronaves = $conn->query("SELECT * FROM aeronaves WHERE {$filtrosLista[$listaAtual]} ORDER BY {$colunasOrdenacao[$ordenarPor]} $direcao");
$quantidadeAeronaves = $aeronaves->num_rows;

function linkOrdenarAeronaves(string $coluna, string $ordenarAtual, string $direcaoAtual): string
{
    $novaDirecao = ($ordenarAtual === $coluna && $direcaoAtual === "ASC") ? "DESC" : "ASC";
    $params = $_GET;
    $params["ordenar"] = $coluna;
    $params["direcao"] = $novaDirecao;
    return "?" . http_build_query($params);
}

function setaOrdenacaoAeronaves(string $coluna, string $ordenarAtual, string $direcaoAtual): string
{
    if ($ordenarAtual !== $coluna) {
        return ' <span class="seta-ordem inativa">&#8597;</span>';
    }
    return $direcaoAtual === "ASC" ? ' <span class="seta-ordem ativa">&#9650;</span>' : ' <span class="seta-ordem ativa">&#9660;</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Aeronaves | Operações de Frota</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
    <!-- Topo da Aplicação -->
    <header class="topo">
        <div class="marca">
            <div class="logo-aplicacao" role="img" aria-label="Logo Registro de Voos"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg></div>
            <div>
                <h1>Cadastro de Aeronaves</h1>
                <p>Especificações técnicas e desempenho operacional da frota</p>
            </div>
        </div>
        <?php include "usuario_menu.php"; ?>
    </header>
    <?php include "aviso_visitante.php"; ?>

    <!-- Navegação em Tabs -->
    <nav class="navegacao" aria-label="Navegação principal">
        <a href="planejamento.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
            <span>Planejamento<br>de voo</span>
        </a>
        <a href="index.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span>Registro de<br>voos</span>
        </a>
        <a href="aeronaves.php" class="ativo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path></svg>
            <span>Cadastro de<br>aeronaves</span>
        </a>
        <a href="analise.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            <span>Comparar<br>aeronave</span>
        </a>
        <a href="aerodromos.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
            <span>Consulta de<br>aeródromos</span>
        </a>
    </nav>
    <?php if ($mensagem): ?><p class="sucesso"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
    <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>
    <?php
    // Preparar valores para o formulário (edição ou novo cadastro)
    $valFabricante = $aeronaveEditar["fabricante"] ?? "";
    $valModelo = $aeronaveEditar["modelo"] ?? "";
    $valAno = !empty($aeronaveEditar["ano"]) ? (int) $aeronaveEditar["ano"] : "";
    $valValor = !empty($aeronaveEditar["valor"]) ? number_format((int) $aeronaveEditar["valor"], 0, ",", ".") : "";
    $valMotor = $aeronaveEditar["motor"] ?? "";
    $valPotencia = !empty($aeronaveEditar["potencia"]) ? number_format((int) $aeronaveEditar["potencia"], 0, ",", ".") : "";
    $valAssentos = !empty($aeronaveEditar["assentos"]) ? number_format((int) $aeronaveEditar["assentos"], 0, ",", ".") : "";
    $valTipoComb = $aeronaveEditar["tipo_combustivel"] ?? "";
    $valPressurizado = $aeronaveEditar["pressurizado"] ?? "";

    $valVelCruzeiro = !empty($aeronaveEditar["velocidade_cruzeiro"]) ? number_format((int) $aeronaveEditar["velocidade_cruzeiro"], 0, ",", ".") : "";
    $valVelSubida = !empty($aeronaveEditar["velocidade_subida"]) ? number_format((int) $aeronaveEditar["velocidade_subida"], 0, ",", ".") : "";
    $valRazaoSubida = !empty($aeronaveEditar["razao_subida"]) ? number_format((int) $aeronaveEditar["razao_subida"], 0, ",", ".") : "";
    $valAltIdeal = !empty($aeronaveEditar["altitude_cruzeiro_ideal"]) ? number_format((int) $aeronaveEditar["altitude_cruzeiro_ideal"], 0, ",", ".") : "";
    $valTetoOper = !empty($aeronaveEditar["teto_operacional"]) ? number_format((int) $aeronaveEditar["teto_operacional"], 0, ",", ".") : "";

    $valPesoVazio = !empty($aeronaveEditar["peso_vazio"]) ? number_format((int) $aeronaveEditar["peso_vazio"], 0, ",", ".") : "";
    $valMtow = !empty($aeronaveEditar["peso_maximo_decolagem"]) ? number_format((int) $aeronaveEditar["peso_maximo_decolagem"], 0, ",", ".") : "";
    $valCargaUtil = !empty($aeronaveEditar["carga_util"]) ? number_format((int) $aeronaveEditar["carga_util"], 0, ",", ".") : "";
    $valTanque = !empty($aeronaveEditar["capacidade_tanque"]) ? number_format((int) $aeronaveEditar["capacidade_tanque"], 0, ",", ".") : "";
    $valConsumoGph = !empty($aeronaveEditar["consumo_gph"]) ? (int) $aeronaveEditar["consumo_gph"] : "";
    $valAutonomia = !empty($aeronaveEditar["autonomia"]) ? substr($aeronaveEditar["autonomia"], 0, 5) : "";
    $valFoto = $aeronaveEditar["foto"] ?? "";
    ?>

    <?php $mostrarTabelaInicial = !$usuarioId || (!$aeronaveEditar && (isset($_GET["ordenar"]) || isset($_GET["lista"]))); ?>
    <?php if ($usuarioId): ?>
    <form id="secaoCadastroAeronave" class="form-aeronave form-aeronave-moderno<?= $mostrarTabelaInicial ? " oculto" : "" ?>" method="post" enctype="multipart/form-data">
        <?= campoCsrf() ?>
        <input type="hidden" name="acao" value="<?= $aeronaveEditar ? "editar" : "salvar" ?>">
        <?php if ($aeronaveEditar): ?><input type="hidden" name="id" value="<?= (int) $aeronaveEditar["id"] ?>"><?php endif; ?>

        <!-- Cabeçalho do Formulário -->
        <div class="form-cabecalho-aero">
            <div class="form-cabecalho-principal-aero">
                <div class="icone-cabecalho-aero">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path>
                    </svg>
                </div>
                <div>
                    <h2><?= $aeronaveEditar ? "Editar Aeronave" : "Cadastrar Nova Aeronave" ?></h2>
                    <p><?= $aeronaveEditar ? "Atualize as especificações e limites operacionais de <strong>" . htmlspecialchars($valFabricante . " " . $valModelo) . "</strong>" : "Preencha as especificações técnicas, motorização e desempenho operacional da frota" ?></p>
                </div>
            </div>
            <div class="form-cabecalho-acoes-aero">
                <?php if ($aeronaveEditar): ?>
                    <div class="badge-modo-edicao">
                        <span class="ponto-status-edicao"></span>
                        <span>Editando ID #<?= (int) $aeronaveEditar["id"] ?></span>
                        <a href="aeronaves.php" class="btn-novo-cadastro-link" title="Sair do modo de edição e criar nova aeronave">+ Novo Cadastro</a>
                    </div>
                <?php endif; ?>
                <button type="button" class="btn-alternar-secao-aero" onclick="alternarSecaoAeronaves(true)">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    <span>Aeronaves Cadastradas</span>
                    <span class="contador-voos"><?= array_sum($contagemListas) ?></span>
                </button>
            </div>
        </div>

        <!-- Grupos Temáticos em Grid 2x2 -->
        <div class="grid-grupos-aeronave">

            <!-- GRUPO 1: Identificação & Foto -->
            <div class="card-grupo-aero">
                <div class="grupo-titulo-card">
                    <div class="grupo-icone-badge icone-azul">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <div>
                        <h3>1. Identificação Geral & Foto</h3>
                        <span>Fabricante, modelo, ano, valor e imagem</span>
                    </div>
                </div>

                <div class="grupo-campos-grid grid-2-col">
                    <div class="campo-aero-item">
                        <label for="campoFabricante">Fabricante <span class="obrigatorio">*</span></label>
                        <input type="text" id="campoFabricante" name="fabricante" placeholder="Ex: Cirrus, Beechcraft, Piper" value="<?= htmlspecialchars($valFabricante) ?>" required autocomplete="off">
                    </div>
                    <div class="campo-aero-item">
                        <label for="campoModelo">Modelo da Aeronave <span class="obrigatorio">*</span></label>
                        <input type="text" id="campoModelo" name="modelo" placeholder="Ex: SR22 G6 Turbo, Baron G58" value="<?= htmlspecialchars($valModelo) ?>" required autocomplete="off">
                    </div>
                    <div class="campo-aero-item">
                        <label for="campoAno">Ano de Fabricação <span class="obrigatorio">*</span></label>
                        <input type="text" id="campoAno" name="ano" placeholder="Ex: 2022" maxlength="4" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '')" value="<?= htmlspecialchars((string)$valAno) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoValor">Valor de Mercado <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">US$</span>
                        </div>
                        <input type="text" id="campoValor" name="valor" placeholder="Ex: 850.000" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valValor) ?>" required>
                    </div>
                    <div class="campo-aero-item campo-col-span-2">
                        <span class="campo-aero-titulo">Quem pode ver esta aeronave? <span class="obrigatorio">*</span></span>
                        <div class="botao-slide-container" role="radiogroup" aria-label="Visibilidade da aeronave">
                            <label class="slide-opcao">
                                <input type="radio" name="visibilidade" value="publica" <?= ($aeronaveEditar["visibilidade"] ?? "publica") === "publica" ? "checked" : "" ?> required>
                                <span class="slide-opcao-texto">🌐 Pública (todos os usuários)</span>
                            </label>
                            <label class="slide-opcao">
                                <input type="radio" name="visibilidade" value="privada" <?= ($aeronaveEditar["visibilidade"] ?? "") === "privada" ? "checked" : "" ?> required>
                                <span class="slide-opcao-texto">🔒 Privada (só eu)</span>
                            </label>
                            <span class="slide-glider"></span>
                        </div>
                    </div>
                </div>

                <!-- Foto Upload & Preview -->
                <div class="card-upload-foto-bloco">
                    <div class="foto-preview-box">
                        <?php if (!empty($valFoto) && is_file(__DIR__ . "/" . $valFoto)): ?>
                            <img src="<?= htmlspecialchars($valFoto) ?>" alt="Foto da aeronave" class="foto-preview-img" id="imgFotoPreview">
                            <div class="sem-foto-box oculto" id="placeholderSemFoto">
                                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                <span>Sem imagem</span>
                            </div>
                        <?php else: ?>
                            <img src="" alt="Nova foto" class="foto-preview-img oculto" id="imgFotoPreview">
                            <div class="sem-foto-box" id="placeholderSemFoto">
                                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                <span>Sem foto</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="foto-controles-box">
                        <span class="foto-rotulo-texto">Foto da Aeronave</span>
                        <p class="foto-ajuda-texto">Formatos JPG, PNG ou WEBP (até 5 MB)</p>
                        <input type="file" id="inputFotoArquivo" name="foto" accept="image/jpeg,image/png,image/webp" class="input-foto-oculto" onchange="previewImagemSelecionada(this)">
                        <label for="inputFotoArquivo" class="btn-selecionar-foto">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            <span id="btnTxtEscolherFoto"><?= !empty($valFoto) ? "Alterar foto..." : "Escolher foto..." ?></span>
                        </label>
                        <span class="nome-arquivo-selecionado" id="txtNomeArquivoFoto"><?= !empty($valFoto) ? "Foto atual salva no servidor" : "" ?></span>
                    </div>
                </div>
            </div>

            <!-- GRUPO 2: Motorização & Cabine -->
            <div class="card-grupo-aero">
                <div class="grupo-titulo-card">
                    <div class="grupo-icone-badge icone-verde">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </div>
                    <div>
                        <h3>2. Motorização & Cabine</h3>
                        <span>Propulsão, potência, combustível e assentos</span>
                    </div>
                </div>

                <div class="grupo-campos-grid grid-2-col">
                    <div class="campo-aero-item campo-col-span-2">
                        <label for="campoMotor">Modelo do Motor <span class="obrigatorio">*</span></label>
                        <input type="text" id="campoMotor" name="motor" placeholder="Ex: Continental TSIO-550-K, PT6A-135A, Lycoming IO-540" value="<?= htmlspecialchars($valMotor) ?>" required autocomplete="off">
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoPotencia">Potência do Motor <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">hp</span>
                        </div>
                        <input type="text" id="campoPotencia" name="potencia" placeholder="Ex: 315" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valPotencia) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoAssentos">Total de Assentos <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">ocupantes</span>
                        </div>
                        <input type="text" id="campoAssentos" name="assentos" placeholder="Ex: 4 ou 6" maxlength="2" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '')" value="<?= htmlspecialchars($valAssentos) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <span class="campo-aero-titulo">Tipo de Combustível <span class="obrigatorio">*</span></span>
                        <div class="botao-slide-container" role="radiogroup" aria-label="Tipo de Combustível">
                            <label class="slide-opcao">
                                <input type="radio" name="tipo_combustivel" value="Avgas" <?= ($valTipoComb === "Avgas" || empty($valTipoComb)) ? "checked" : "" ?> required>
                                <span class="slide-opcao-texto">⛽ Avgas (100LL)</span>
                            </label>
                            <label class="slide-opcao">
                                <input type="radio" name="tipo_combustivel" value="JetA" <?= $valTipoComb === "JetA" ? "checked" : "" ?> required>
                                <span class="slide-opcao-texto">✈️ Jet-A</span>
                            </label>
                            <span class="slide-glider"></span>
                        </div>
                    </div>
                    <div class="campo-aero-item">
                        <span class="campo-aero-titulo">Cabine Pressurizada? <span class="obrigatorio">*</span></span>
                        <div class="botao-slide-container" role="radiogroup" aria-label="Cabine Pressurizada">
                            <label class="slide-opcao">
                                <input type="radio" name="pressurizado" value="Não" <?= ($valPressurizado === "Não" || empty($valPressurizado)) ? "checked" : "" ?> required>
                                <span class="slide-opcao-texto">❌ Não</span>
                            </label>
                            <label class="slide-opcao">
                                <input type="radio" name="pressurizado" value="Sim" <?= $valPressurizado === "Sim" ? "checked" : "" ?> required>
                                <span class="slide-opcao-texto">✅ Sim</span>
                            </label>
                            <span class="slide-glider"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- GRUPO 3: Performance & Cruzeiro -->
            <div class="card-grupo-aero">
                <div class="grupo-titulo-card">
                    <div class="grupo-icone-badge icone-ciano">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                    </div>
                    <div>
                        <h3>3. Performance & Cruzeiro</h3>
                        <span>Velocidades, razões e altitudes de operação</span>
                    </div>
                </div>

                <div class="grupo-campos-grid grid-2-col">
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoVelCruzeiro">Velocidade de Cruzeiro (TAS) <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">kt</span>
                        </div>
                        <input type="text" id="campoVelCruzeiro" name="velocidade_cruzeiro" placeholder="Ex: 180" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valVelCruzeiro) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoVelSubida">Velocidade na Subida <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">kt</span>
                        </div>
                        <input type="text" id="campoVelSubida" name="velocidade_subida" placeholder="Ex: 120" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valVelSubida) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoRazaoSubida">Razão de Subida (ROC) <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">ft/min</span>
                        </div>
                        <input type="text" id="campoRazaoSubida" name="razao_subida" placeholder="Ex: 1.200" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valRazaoSubida) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoAltIdeal">Altitude Ideal de Cruzeiro <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">ft</span>
                        </div>
                        <input type="text" id="campoAltIdeal" name="altitude_cruzeiro_ideal" placeholder="Ex: 10.000" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valAltIdeal) ?>" required>
                    </div>
                    <div class="campo-aero-item campo-col-span-2">
                        <div class="label-com-unidade">
                            <label for="campoTetoOper">Teto Operacional Máximo <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">ft</span>
                        </div>
                        <input type="text" id="campoTetoOper" name="teto_operacional" placeholder="Ex: 25.000" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valTetoOper) ?>" required>
                    </div>
                </div>
            </div>

            <!-- GRUPO 4: Pesos, Combustível & Autonomia -->
            <div class="card-grupo-aero">
                <div class="grupo-titulo-card">
                    <div class="grupo-icone-badge icone-roxo">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path><line x1="16" y1="8" x2="2" y2="22"></line><line x1="17.5" y1="15" x2="9" y2="15"></line></svg>
                    </div>
                    <div>
                        <h3>4. Pesos, Combustível & Autonomia</h3>
                        <span>Limites de peso, tanque e alcance de voo</span>
                    </div>
                </div>

                <div class="grupo-campos-grid grid-2-col">
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoPesoVazio">Peso Vazio Básico (BEW) <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">lb</span>
                        </div>
                        <input type="text" id="campoPesoVazio" name="peso_vazio" placeholder="Ex: 2.350" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valPesoVazio) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoMtow">Peso Máx. Decolagem (MTOW) <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">lb</span>
                        </div>
                        <input type="text" id="campoMtow" name="peso_maximo_decolagem" placeholder="Ex: 3.600" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valMtow) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoCargaUtil">Carga Útil (Useful Load) <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">lb</span>
                        </div>
                        <div class="input-com-botao-acao">
                            <input type="text" id="campoCargaUtil" name="carga_util" placeholder="Ex: 1.250" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valCargaUtil) ?>" required>
                            <button type="button" class="btn-calc-auto" onclick="calcularCargaUtilAutomatica()" title="Calcular automaticamente: MTOW - Peso Vazio">
                                <span>Auto</span>
                            </button>
                        </div>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoCapacidadeTanque">Capacidade do Tanque <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">galões</span>
                        </div>
                        <input type="text" id="campoCapacidadeTanque" name="capacidade_tanque" placeholder="Ex: 92" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')" value="<?= htmlspecialchars($valTanque) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoConsumoGph">Consumo Médio de Cruzeiro <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">GPH</span>
                        </div>
                        <input type="text" id="campoConsumoGph" name="consumo_gph_aeronave" placeholder="Ex: 18" maxlength="3" inputmode="numeric" oninput="this.value = this.value.replace(/\D/g, '')" value="<?= htmlspecialchars((string)$valConsumoGph) ?>" required>
                    </div>
                    <div class="campo-aero-item">
                        <div class="label-com-unidade">
                            <label for="campoAutonomia">Autonomia Total <span class="obrigatorio">*</span></label>
                            <span class="unidade-badge">hh:mm</span>
                        </div>
                        <div class="input-com-botao-acao">
                            <input type="text" id="campoAutonomia" name="autonomia" placeholder="04:30" maxlength="5" pattern="[0-9]{2}:[0-5][0-9]" value="<?= htmlspecialchars($valAutonomia) ?>" required>
                            <button type="button" class="btn-calc-auto" onclick="calcularAutonomiaAutomatica()" title="Estimar autonomia com base no Tanque / Consumo">
                                <span>Auto</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Rodapé de Ações do Formulário -->
        <div class="acoes-form-aeronave-rodape">
            <div class="acoes-principais-esquerda">
                <button type="submit" class="btn-salvar-aeronave">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <span><?= $aeronaveEditar ? "Atualizar Aeronave" : "Cadastrar Aeronave" ?></span>
                </button>
                <?php if ($aeronaveEditar): ?>
                    <a class="btn-cancelar-edicao-link" href="aeronaves.php">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        <span>Cancelar</span>
                    </a>
                <?php else: ?>
                    <button type="reset" class="btn-cancelar-edicao-link" onclick="limparPreviewFoto()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                        <span>Limpar</span>
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($aeronaveEditar): ?>
                <div class="acoes-perigo-direita">
                    <button class="btn-excluir-aeronave" type="submit" name="acao" value="excluir" title="Excluir aeronave permanentemente" onclick="return confirm('Tem certeza de que deseja excluir permanentemente esta aeronave da frota?')">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        <span>Excluir Aeronave</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
    <section id="secaoTabelaAeronaves" class="tabela aeronaves<?= $mostrarTabelaInicial ? "" : " oculto" ?>">
        <div class="titulo-tabela">
            <h2>
                Aeronaves Cadastradas
                <span class="contador-voos"><?= (int) $quantidadeAeronaves ?></span>
            </h2>
            <?php if ($usuarioId): ?>
            <button type="button" class="btn-alternar-secao-aero" onclick="alternarSecaoAeronaves(false)">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span><?= $aeronaveEditar ? "Voltar para Edição" : "Cadastrar Nova Aeronave" ?></span>
            </button>
            <?php else: ?>
            <a href="<?= htmlspecialchars(urlLogin()) ?>" class="btn-alternar-secao-aero" title="Faça login para cadastrar aeronaves">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>Entrar para cadastrar aeronave</span>
            </a>
            <?php endif; ?>
        </div>

        <?php $rotulosListas = $usuarioId ? ["minhas" => "Minhas aeronaves", "outros" => "De outros usuários", "ocultas" => "Ocultas"] : ["outros" => "Aeronaves públicas"]; ?>
        <nav class="abas-lista-aeronaves" aria-label="Filtrar aeronaves">
            <?php foreach ($rotulosListas as $chaveLista => $rotuloLista): ?>
                <a href="?<?= htmlspecialchars(http_build_query(array_merge(array_diff_key($_GET, ["editar" => 1]), ["lista" => $chaveLista]))) ?>" class="<?= $listaAtual === $chaveLista ? "ativo" : "" ?>"<?= $listaAtual === $chaveLista ? ' aria-current="page"' : "" ?>>
                    <?= $rotuloLista ?> <span class="contador-aba"><?= $contagemListas[$chaveLista] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="tabela-container">
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th><a href="<?= linkOrdenarAeronaves("modelo", $ordenarPor, $direcao) ?>">Modelo<?= setaOrdenacaoAeronaves("modelo", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("velocidade", $ordenarPor, $direcao) ?>">Velocidade<?= setaOrdenacaoAeronaves("velocidade", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("teto", $ordenarPor, $direcao) ?>">Teto Oper.<?= setaOrdenacaoAeronaves("teto", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("altitude_ideal", $ordenarPor, $direcao) ?>">Alt. Ideal<?= setaOrdenacaoAeronaves("altitude_ideal", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("consumo", $ordenarPor, $direcao) ?>">GPH<?= setaOrdenacaoAeronaves("consumo", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("autonomia", $ordenarPor, $direcao) ?>">Autonomia<?= setaOrdenacaoAeronaves("autonomia", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("carga_util", $ordenarPor, $direcao) ?>">Carga Útil<?= setaOrdenacaoAeronaves("carga_util", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("assentos", $ordenarPor, $direcao) ?>">Assentos<?= setaOrdenacaoAeronaves("assentos", $ordenarPor, $direcao) ?></a></th>
                        <th><a href="<?= linkOrdenarAeronaves("valor", $ordenarPor, $direcao) ?>">Valor<?= setaOrdenacaoAeronaves("valor", $ordenarPor, $direcao) ?></a><small class="subtitulo-gph">(US$)</small></th>
                        <?php if ($usuarioId): ?><th><?= $listaAtual === "minhas" ? "Visibilidade" : "Ação" ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($quantidadeAeronaves === 0): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 32px; color: var(--muted);"><?= ["minhas" => "Você ainda não cadastrou aeronaves.", "outros" => "Nenhuma aeronave pública de outros usuários.", "ocultas" => "Nenhuma aeronave oculta."][$listaAtual] ?></td>
                        </tr>
                    <?php else: ?>
                        <?php while ($aeronave = $aeronaves->fetch_assoc()): 
                            $consumoGph = (float) $aeronave["consumo_gph"];
                            $autonomiaCalculada = $consumoGph > 0 ? round(((float) $aeronave["capacidade_tanque"] / $consumoGph) * (float) $aeronave["velocidade_cruzeiro"]) : 0;
                            $cargaUtilLb = (float) $aeronave["carga_util"];
                            $cargaUtilKg = round($cargaUtilLb / 2.20462);
                        ?>
                            <?php if ($listaAtual === "minhas"): ?>
                            <tr class="linha-clicavel" data-href="?editar=<?= (int) $aeronave["id"] ?>" title="Clique na linha para editar <?= htmlspecialchars($aeronave["modelo"]) ?>">
                            <?php else: ?>
                            <tr>
                            <?php endif; ?>
                                <td>
                                    <?php if (!empty($aeronave["foto"])): ?>
                                        <button type="button" class="btn-miniatura-foto abrir-foto" data-dialog="foto-<?= (int) $aeronave["id"] ?>" title="Clique para ampliar">
                                            <img class="miniatura-aeronave-tabela" src="<?= htmlspecialchars($aeronave["foto"]) ?>" alt="Foto de <?= htmlspecialchars($aeronave["modelo"]) ?>">
                                        </button>
                                        <dialog id="foto-<?= (int) $aeronave["id"] ?>">
                                            <img class="foto-ampliada" src="<?= htmlspecialchars($aeronave["foto"]) ?>" alt="Foto ampliada de <?= htmlspecialchars($aeronave["modelo"]) ?>">
                                            <button type="button" class="fechar-dialog">Fechar</button>
                                        </dialog>
                                    <?php else: ?>
                                        <span class="sem-foto-tabela" title="Sem foto">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-aeronave" title="<?= htmlspecialchars($aeronave["fabricante"] ?? "") ?>"><?= htmlspecialchars($aeronave["modelo"]) ?></span>
                                    <small class="sub-ano">(<?= (int) $aeronave["ano"] ?>)</small>
                                </td>
                                <td><strong><?= number_format($aeronave["velocidade_cruzeiro"], 0, ",", ".") ?></strong> <small class="unidade-badge">kt</small></td>
                                <td><?= number_format($aeronave["teto_operacional"], 0, ",", ".") ?> <small class="unidade-badge">ft</small></td>
                                <td><?= number_format($aeronave["altitude_cruzeiro_ideal"], 0, ",", ".") ?> <small class="unidade-badge">ft</small></td>
                                <td><strong><?= number_format($aeronave["consumo_gph"], 0, ",", ".") ?></strong></td>
                                <td>
                                    <div><?= htmlspecialchars(substr($aeronave["autonomia"], 0, 5)) ?></div>
                                    <small class="sub-autonomia-nm"><?= number_format($autonomiaCalculada, 0, ",", ".") ?> NM</small>
                                </td>
                                <td>
                                    <div><?= number_format($cargaUtilLb, 0, ",", ".") ?> <small class="unidade-badge">lb</small></div>
                                    <small class="sub-autonomia-nm"><?= number_format($cargaUtilKg, 0, ",", ".") ?> kg</small>
                                </td>
                                <td><?= number_format($aeronave["assentos"], 0, ",", ".") ?></td>
                                <td><span class="custo-destaque"><?= number_format($aeronave["valor"], 0, ",", ".") ?></span></td>
                                <?php if ($usuarioId): ?>
                                <td>
                                    <?php if ($listaAtual === "minhas"): ?>
                                        <span class="badge-visibilidade <?= $aeronave["visibilidade"] === "privada" ? "privada" : "publica" ?>"><?= $aeronave["visibilidade"] === "privada" ? "🔒 Privada" : "🌐 Pública" ?></span>
                                    <?php else: ?>
                                        <form method="post" class="form-acao-inline">
                                            <?= campoCsrf() ?>
                                            <input type="hidden" name="id" value="<?= (int) $aeronave["id"] ?>">
                                            <?php if ($listaAtual === "outros"): ?>
                                                <button type="submit" name="acao" value="ocultar" class="btn-ocultar-aeronave" title="Ocultar esta aeronave das minhas listas">Ocultar</button>
                                            <?php else: ?>
                                                <button type="submit" name="acao" value="mostrar" class="btn-ocultar-aeronave" title="Voltar a exibir esta aeronave">Mostrar</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <script>
        function alternarSecaoAeronaves(mostrarTabela) {
            document.getElementById("secaoCadastroAeronave").classList.toggle("oculto", mostrarTabela);
            document.getElementById("secaoTabelaAeronaves").classList.toggle("oculto", !mostrarTabela);
        }

        document.querySelectorAll(".abrir-foto").forEach(function (botao) {
            botao.addEventListener("click", function (e) {
                e.stopPropagation();
                document.getElementById(botao.dataset.dialog).showModal();
            });
        });
        document.querySelectorAll(".fechar-dialog").forEach(function (botao) {
            botao.addEventListener("click", function (e) {
                e.stopPropagation();
                botao.closest("dialog").close();
            });
        });
        document.querySelectorAll(".linha-clicavel").forEach(function (linha) {
            linha.addEventListener("click", function (e) {
                if (e.target.closest(".abrir-foto") || e.target.closest("dialog") || e.target.closest("button") || e.target.closest("a")) {
                    return;
                }
                window.location.href = linha.dataset.href;
            });
        });

        // Helpers de Formulário de Aeronaves
        function previewImagemSelecionada(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById("imgFotoPreview");
                    const placeholder = document.getElementById("placeholderSemFoto");
                    const txt = document.getElementById("txtNomeArquivoFoto");
                    const btnTxt = document.getElementById("btnTxtEscolherFoto");
                    if (img) {
                        img.src = e.target.result;
                        img.classList.remove("oculto");
                    }
                    if (placeholder) {
                        placeholder.classList.add("oculto");
                    }
                    if (txt) {
                        const tamanhoMb = (file.size / (1024 * 1024)).toFixed(2);
                        txt.textContent = `${file.name} (${tamanhoMb} MB)`;
                    }
                    if (btnTxt) {
                        btnTxt.textContent = "Alterar foto...";
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function limparPreviewFoto() {
            const img = document.getElementById("imgFotoPreview");
            const placeholder = document.getElementById("placeholderSemFoto");
            const txt = document.getElementById("txtNomeArquivoFoto");
            const btnTxt = document.getElementById("btnTxtEscolherFoto");
            if (img) {
                img.src = "";
                img.classList.add("oculto");
            }
            if (placeholder) {
                placeholder.classList.remove("oculto");
            }
            if (txt) {
                txt.textContent = "";
            }
            if (btnTxt) {
                btnTxt.textContent = "Escolher foto...";
            }
        }

        function calcularCargaUtilAutomatica() {
            const elMtow = document.getElementById("campoMtow");
            const elPesoVazio = document.getElementById("campoPesoVazio");
            const elCargaUtil = document.getElementById("campoCargaUtil");
            if (!elMtow || !elPesoVazio || !elCargaUtil) return;

            const mtow = parseInt(elMtow.value.replace(/\D/g, ""), 10) || 0;
            const vazio = parseInt(elPesoVazio.value.replace(/\D/g, ""), 10) || 0;

            if (mtow > 0 && vazio > 0) {
                const util = Math.max(0, mtow - vazio);
                elCargaUtil.value = util.toLocaleString("pt-BR");
                elCargaUtil.focus();
            } else {
                alert("Por favor, preencha primeiro o Peso Máximo de Decolagem (MTOW) e o Peso Vazio para calcular a Carga Útil.");
            }
        }

        function calcularAutonomiaAutomatica() {
            const elTanque = document.getElementById("campoCapacidadeTanque");
            const elConsumo = document.getElementById("campoConsumoGph");
            const elAutonomia = document.getElementById("campoAutonomia");
            if (!elTanque || !elConsumo || !elAutonomia) return;

            const tanque = parseFloat(elTanque.value.replace(/\D/g, "")) || 0;
            const gph = parseFloat(elConsumo.value.replace(/\D/g, "")) || 0;

            if (tanque > 0 && gph > 0) {
                const totalHoras = tanque / gph;
                const horas = Math.floor(totalHoras);
                const minutos = Math.round((totalHoras - horas) * 60);
                elAutonomia.value = `${String(horas).padStart(2, "0")}:${String(minutos).padStart(2, "0")}`;
                elAutonomia.focus();
            } else {
                alert("Por favor, preencha primeiro a Capacidade do Tanque e o Consumo Médio (GPH) para estimar a Autonomia.");
            }
        }
    </script>
    <?php include "rodape.php"; ?>
</body></html>
<?php $conn->close(); ?>
