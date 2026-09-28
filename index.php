<?php
if (!ob_start("ob_gzhandler")) ob_start();
require "auth.php";
require_once "aerodromos_dados.php";
$usuarioId = usuarioAtualId();
$joinAeronave = sqlJoinAeronaveDoVoo($usuarioId);

$mensagem = "";
$erro = "";
$vooEditar = null;

if (isset($_GET["editar"])) {
    $idEditar = (int) $_GET["editar"];
    $stmt = $conn->prepare("SELECT * FROM voos WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $idEditar, $usuarioId);
    $stmt->execute();
    $vooEditar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Dados vindos do botão "Registrar voo" do planejamento
$vooForm = $vooEditar ?? [];
if (!$vooEditar && isset($_GET["origem"])) {
    $tempoPlano = preg_match('/^\d{1,2}:\d{2}$/', $_GET["tempo_voo"] ?? "") ? $_GET["tempo_voo"] : "";
    $vooForm = [
        "aeronave" => trim($_GET["aeronave"] ?? ""),
        "partida" => strtoupper(substr(trim($_GET["origem"] ?? ""), 0, 4)),
        "destino" => strtoupper(substr(trim($_GET["destino"] ?? ""), 0, 4)),
        "distancia_nm" => (float) ($_GET["distancia"] ?? 0) ?: "",
        "tanque_decolagem" => (float) ($_GET["tanque_decolagem"] ?? 0),
        "tanque_pouso" => (float) ($_GET["tanque_pouso"] ?? 0),
        "tempo_voo" => $tempoPlano !== "" ? str_pad($tempoPlano, 5, "0", STR_PAD_LEFT) : "",
        "altitude" => (int) ($_GET["altitude"] ?? 0) ?: "",
    ];
}

if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $acao = $_POST["acao"] ?? "salvar";
    $id = (int) ($_POST["id"] ?? 0);

    if ($acao === "excluir" && $id > 0) {
        $stmt = $conn->prepare("DELETE FROM voos WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $id, $usuarioId);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    }

    $aeronave = trim($_POST["aeronave"] ?? "");
    $partida = strtoupper(substr(trim($_POST["partida"] ?? ""), 0, 4));
    $destino = strtoupper(substr(trim($_POST["destino"] ?? ""), 0, 4));
    $distancia = (float) ($_POST["distancia_nm"] ?? 0);
    $tanqueDecolagem = (float) ($_POST["tanque_decolagem"] ?? 0);
    $tanquePouso = (float) ($_POST["tanque_pouso"] ?? 0);
    $tempo = $_POST["tempo_voo"] ?? "";
    $altitude = (int) ($_POST["altitude"] ?? 0);
    $observacoes = trim($_POST["observacoes"] ?? "");

    $partesTempo = explode(":", $tempo);
    $horas = ((int) ($partesTempo[0] ?? 0)) + (((int) ($partesTempo[1] ?? 0)) / 60);
    $consumoReal = $tanqueDecolagem - $tanquePouso;
    $consumoGph = $horas > 0 ? $consumoReal / $horas : 0;
    $velocidade = $horas > 0 ? $distancia / $horas : 0;

    $stmtAeronave = $conn->prepare("SELECT CONCAT(fabricante, ' ', modelo) FROM aeronaves WHERE (CONCAT(fabricante, ' ', modelo) = ? OR modelo = ?) AND (usuario_id = ? OR visibilidade = 'publica') ORDER BY usuario_id = ? DESC LIMIT 1");
    $stmtAeronave->bind_param("ssii", $aeronave, $aeronave, $usuarioId, $usuarioId);
    $stmtAeronave->execute();
    $stmtAeronave->bind_result($nomeCompletoAero);
    if ($stmtAeronave->fetch()) {
        $aeronave = $nomeCompletoAero;
        $aeronaveExiste = 1;
    } else {
        $aeronaveExiste = 0;
    }
    $stmtAeronave->close();

    if (!$aeronaveExiste || $partida === "" || $destino === "" || $distancia <= 0 || $tanqueDecolagem < 0 || $tanquePouso < 0 || $tanquePouso > $tanqueDecolagem || $horas <= 0 || $altitude < 0) {
        $erro = "Selecione uma aeronave cadastrada e preencha os campos corretamente.";
    } else {
        if ($acao === "editar" && $id > 0) {
            $stmt = $conn->prepare("UPDATE voos SET aeronave = ?, partida = ?, destino = ?, distancia_nm = ?, tanque_decolagem = ?, tanque_pouso = ?, consumo_real = ?, consumo_gph = ?, tempo_voo = ?, altitude = ?, velocidade = ?, observacoes = ? WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("sssdddddsidsii", $aeronave, $partida, $destino, $distancia, $tanqueDecolagem, $tanquePouso, $consumoReal, $consumoGph, $tempo, $altitude, $velocidade, $observacoes, $id, $usuarioId);
        } else {
            $stmt = $conn->prepare("INSERT INTO voos (aeronave, partida, destino, distancia_nm, tanque_decolagem, tanque_pouso, consumo_real, consumo_gph, tempo_voo, altitude, velocidade, observacoes, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdddddsidsi", $aeronave, $partida, $destino, $distancia, $tanqueDecolagem, $tanquePouso, $consumoReal, $consumoGph, $tempo, $altitude, $velocidade, $observacoes, $usuarioId);
        }
        $stmt->execute();
        $mensagem = $acao === "editar" ? "Voo atualizado com sucesso." : "Voo salvo com sucesso.";
        $stmt->close();
        $vooEditar = null;
    }
}

$ordenacoesVoos = [
    "data" => "criado_em",
    "aeronave" => "aeronave",
    "rota" => "CONCAT(partida, destino)",
    "distancia" => "distancia_nm",
    "tempo" => "tempo_voo",
    "consumo" => "consumo_real",
    "gph" => "consumo_gph",
    "velocidade" => "velocidade"
];
$ordenarVoosPor = $_GET["ordenar"] ?? "data";
$ordenarVoosPor = array_key_exists($ordenarVoosPor, $ordenacoesVoos) ? $ordenarVoosPor : "data";
$direcaoPadrao = isset($_GET["ordenar"]) ? "ASC" : "DESC";
$direcaoVoos = strtoupper($_GET["direcao"] ?? $direcaoPadrao) === "DESC" ? "DESC" : "ASC";
$filtroPartida = strtoupper(substr(trim($_GET["partida"] ?? ""), 0, 4));
$filtroDestino = strtoupper(substr(trim($_GET["destino"] ?? ""), 0, 4));
$filtroAeronave = trim($_GET["filtro_aeronave"] ?? "");
$valorJetaInformado = array_key_exists("valor_jeta", $_GET) ? trim($_GET["valor_jeta"]) : "5.96";
$valorAvgasInformado = array_key_exists("valor_avgas", $_GET) ? trim($_GET["valor_avgas"]) : "10.70";
$valorJeta = $valorJetaInformado !== "" && is_numeric(str_replace(",", ".", $valorJetaInformado)) ? max(0, (float) str_replace(",", ".", $valorJetaInformado)) : null;
$valorAvgas = $valorAvgasInformado !== "" && is_numeric(str_replace(",", ".", $valorAvgasInformado)) ? max(0, (float) str_replace(",", ".", $valorAvgasInformado)) : null;
$litrosPorGalao = 3.785411784;
$ordenacoesVoos["custo"] = "consumo_real * CASE aeronaves.tipo_combustivel WHEN 'JetA' THEN " . (float) ($valorJeta ?? 0) * $litrosPorGalao . " WHEN 'Avgas' THEN " . (float) ($valorAvgas ?? 0) * $litrosPorGalao . " ELSE NULL END";
$ordenarVoosPor = array_key_exists($ordenarVoosPor, $ordenacoesVoos) ? $ordenarVoosPor : "data";
$condicoesVoos = [];
$parametrosVoos = [];
$tiposVoos = "";
$filtroUsuario = "voos.usuario_id = {$usuarioId}";
if ($filtroPartida !== "") {
    $condicoesVoos[] = "partida = ?";
    $parametrosVoos[] = $filtroPartida;
    $tiposVoos .= "s";
}
if ($filtroDestino !== "") {
    $condicoesVoos[] = "destino = ?";
    $parametrosVoos[] = $filtroDestino;
    $tiposVoos .= "s";
}
if ($filtroAeronave !== "") {
    $condicoesVoos[] = "voos.aeronave = ?";
    $parametrosVoos[] = $filtroAeronave;
    $tiposVoos .= "s";
}
$sqlVoos = "SELECT voos.*, aeronaves.tipo_combustivel, aeronaves.consumo_gph AS consumo_gph_cadastrado FROM voos {$joinAeronave} WHERE " . implode(" AND ", [$filtroUsuario, ...$condicoesVoos]) . " ORDER BY {$ordenacoesVoos[$ordenarVoosPor]} $direcaoVoos";
$stmtVoos = $conn->prepare($sqlVoos);
if ($parametrosVoos) {
    $stmtVoos->bind_param($tiposVoos, ...$parametrosVoos);
}
$stmtVoos->execute();
$voos = $stmtVoos->get_result();
$totalVoosRegistrados = $voos->num_rows;

// Base de aeródromos por OACI para nomes e cidades
$aerodromosPorOaci = carregarAerodromosPorOaci();

function custoVooRota(?array $voo, ?float $valorJeta, ?float $valorAvgas, float $litrosPorGalao): ?float
{
    if (!$voo) {
        return null;
    }
    $precoPorLitro = match ($voo["tipo_combustivel"] ?? null) {
        "JetA" => $valorJeta,
        "Avgas" => $valorAvgas,
        default => null,
    };
    return $precoPorLitro !== null ? (float) $voo["consumo_real"] * $precoPorLitro * $litrosPorGalao : null;
}

// Exportação de voos filtrados para CSV
if (isset($_GET["exportar"]) && $_GET["exportar"] === "csv") {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=registro_voos_" . date("Ymd_His") . ".csv");
    echo "\xEF\xBB\xBF"; // BOM UTF-8 para Excel
    $saida = fopen("php://output", "w");
    fputcsv($saida, [
        "Aeronave",
        "Origem",
        "Destino",
        "Distância (NM)",
        "Tempo de Voo",
        "Consumo Real (gal)",
        "Consumo GPH",
        "Velocidade (kt)",
        "Custo Combustível (R$)",
        "Data"
    ], ";");

    while ($vooExport = $voos->fetch_assoc()) {
        $custo = custoVooRota($vooExport, $valorJeta, $valorAvgas, $litrosPorGalao);
        fputcsv($saida, [
            $vooExport["aeronave"],
            $vooExport["partida"],
            $vooExport["destino"],
            number_format($vooExport["distancia_nm"], 0, ",", "."),
            substr($vooExport["tempo_voo"], 0, 5),
            number_format($vooExport["consumo_real"], 0, ",", "."),
            number_format($vooExport["consumo_gph"], 0, ",", "."),
            number_format($vooExport["velocidade"], 0, ",", "."),
            $custo !== null ? number_format($custo, 2, ",", ".") : "-",
            date("d/m/Y H:i", strtotime($vooExport["criado_em"]))
        ], ";");
    }
    fclose($saida);
    exit;
}

// Estatísticas para os cards de KPI (refletem os filtros quando aplicados)
$filtrosAtivos = !empty($condicoesVoos);
$sqlStats = "SELECT COUNT(*) as total_voos, COALESCE(SUM(distancia_nm), 0) as total_distancia, COALESCE(SUM(consumo_real), 0) as total_consumo, COALESCE(SUM(TIME_TO_SEC(tempo_voo)), 0) as total_segundos FROM voos WHERE " . implode(" AND ", [$filtroUsuario, ...$condicoesVoos]);
$stmtStats = $conn->prepare($sqlStats);
if ($parametrosVoos) {
    $stmtStats->bind_param($tiposVoos, ...$parametrosVoos);
}
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc() ?: ["total_voos" => 0, "total_distancia" => 0, "total_consumo" => 0, "total_segundos" => 0];
$stmtStats->close();

$kpiTotalVoos = (int) ($stats["total_voos"] ?? 0);
$kpiTotalDistancia = (float) ($stats["total_distancia"] ?? 0);
$kpiTotalSegundos = (int) ($stats["total_segundos"] ?? 0);
$kpiTotalHoras = $kpiTotalSegundos > 0 ? $kpiTotalSegundos / 3600 : 0;
$kpiHorasFormatadas = sprintf('%dh %02dm', floor($kpiTotalHoras), floor(($kpiTotalSegundos % 3600) / 60));
$kpiConsumoMedioGph = $kpiTotalHoras > 0 ? ((float) $stats["total_consumo"] / $kpiTotalHoras) : 0;

$partidasDisponiveis = $conn->query("SELECT DISTINCT partida FROM voos WHERE {$filtroUsuario} AND partida <> '' ORDER BY partida");
$destinosDisponiveis = $conn->query("SELECT DISTINCT destino FROM voos WHERE {$filtroUsuario} AND destino <> '' ORDER BY destino");
$aeronavesFiltro = $conn->query("SELECT DISTINCT aeronave FROM voos WHERE {$filtroUsuario} AND aeronave <> '' ORDER BY aeronave");
$aeronavesCadastradas = $conn->query("SELECT fabricante, modelo, capacidade_tanque FROM aeronaves WHERE " . sqlAeronavesVisiveis($usuarioId) . " ORDER BY fabricante, modelo");
$tanquesPorAeronave = [];
while ($aeroTanque = $aeronavesCadastradas->fetch_assoc()) {
    $tanquesPorAeronave[$aeroTanque["fabricante"] . " " . $aeroTanque["modelo"]] = (float) $aeroTanque["capacidade_tanque"];
    $tanquesPorAeronave[$aeroTanque["modelo"]] = (float) $aeroTanque["capacidade_tanque"];
}
$aeronavesCadastradas->data_seek(0);

function tempoEmMinutos(string $tempoVoo): int
{
    [$horas, $minutos] = array_map("intval", explode(":", $tempoVoo));
    return $horas * 60 + $minutos;
}

$rotasMaisVoadas = $conn->query("SELECT partida, destino, COUNT(*) AS quantidade FROM voos WHERE {$filtroUsuario} GROUP BY partida, destino ORDER BY quantidade DESC, partida, destino LIMIT 20");
$rankingRotas = [];
while ($rota = $rotasMaisVoadas->fetch_assoc()) {
    $sqlExtremo = "SELECT voos.aeronave, voos.tempo_voo, voos.consumo_real, aeronaves.tipo_combustivel FROM voos {$joinAeronave} WHERE {$filtroUsuario} AND voos.partida = ? AND voos.destino = ? ORDER BY voos.tempo_voo %s LIMIT 1";

    $stmtRapido = $conn->prepare(sprintf($sqlExtremo, "ASC"));
    $stmtRapido->bind_param("ss", $rota["partida"], $rota["destino"]);
    $stmtRapido->execute();
    $rota["mais_rapido"] = $stmtRapido->get_result()->fetch_assoc();
    $stmtRapido->close();

    $stmtLento = $conn->prepare(sprintf($sqlExtremo, "DESC"));
    $stmtLento->bind_param("ss", $rota["partida"], $rota["destino"]);
    $stmtLento->execute();
    $rota["mais_lento"] = $stmtLento->get_result()->fetch_assoc();
    $stmtLento->close();

    $rankingRotas[] = $rota;
}

function linkOrdenarVoos(string $coluna, string $ordenarAtual, string $direcaoAtual): string
{
    $novaDirecao = ($ordenarAtual === $coluna && $direcaoAtual === "ASC") ? "DESC" : "ASC";
    $params = $_GET;
    $params["ordenar"] = $coluna;
    $params["direcao"] = $novaDirecao;
    return "?" . http_build_query($params);
}

function setaOrdenacaoVoos(string $coluna, string $ordenarAtual, string $direcaoAtual): string
{
    if ($ordenarAtual !== $coluna) {
        return "";
    }
    return $direcaoAtual === "ASC" ? " &#9650;" : " &#9660;";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Voos | Operações de Frota</title>
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
                <h1>Registro de Voos</h1>
                <p>Diário operacional e gestão de performance da frota</p>
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
        <a href="index.php" class="ativo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            <span>Registro de<br>voos</span>
        </a>
        <a href="aeronaves.php">
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

    <!-- Resumo Operacional (KPI Cards) -->
    <section class="kpi-grid" aria-label="Métricas da frota">
        <div class="kpi-card">
            <div class="kpi-icon primary" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
            </div>
            <div class="kpi-info">
                <span class="kpi-rotulo">Total de Voos</span>
                <span class="kpi-valor"><?= number_format($kpiTotalVoos, 0, ",", ".") ?></span>
                <span class="kpi-sub">Missões registradas</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon teal" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div class="kpi-info">
                <span class="kpi-rotulo">Horas Voadas</span>
                <span class="kpi-valor"><?= $kpiHorasFormatadas ?></span>
                <span class="kpi-sub"><?= number_format($kpiTotalHoras, 1, ",", ".") ?> horas totais</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon accent" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
            </div>
            <div class="kpi-info">
                <span class="kpi-rotulo">Distância Total</span>
                <span class="kpi-valor"><?= number_format($kpiTotalDistancia, 0, ",", ".") ?> <small style="font-size: 14px; font-weight: 600;">NM</small></span>
                <span class="kpi-sub">Milhas náuticas percorridas</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon warning" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <div class="kpi-info">
                <span class="kpi-rotulo">Consumo Médio</span>
                <span class="kpi-valor"><?= number_format($kpiConsumoMedioGph, 1, ",", ".") ?> <small style="font-size: 14px; font-weight: 600;">GPH</small></span>
                <span class="kpi-sub"><?= number_format((float)($stats["total_consumo"] ?? 0), 0, ",", ".") ?> galões totais</span>
            </div>
        </div>
    </section>

    <!-- Alertas do Sistema -->
    <?php if ($mensagem): ?>
        <div class="sucesso" role="alert">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro" role="alert">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <!-- Datalists para Autocomplete de Aeródromos e Aeronaves -->
    <datalist id="listaAeronaves">
        <?php 
        $aeronavesCadastradas->data_seek(0);
        while ($aero = $aeronavesCadastradas->fetch_assoc()): 
        ?>
            <option value="<?= htmlspecialchars($aero["modelo"]) ?>"></option>
        <?php endwhile; ?>
    </datalist>

    <datalist id="listaAerodromos">
        <?php 
        $aerodromosLista = carregarTodosAerodromos();
        foreach ($aerodromosLista as $aeroItem) {
            $oaciItem = $aeroItem["CódigoOACI"];
            if ($oaciItem === "") continue;
            $nomeItem = $aeroItem["Nome"];
            $muniItem = $aeroItem["Município"];
            $ufItem = $aeroItem["UF"];
            $descItem = $nomeItem . ($muniItem !== "" ? " - {$muniItem}" : "") . ($ufItem !== "" ? "/{$ufItem}" : "");
            echo '<option value="' . htmlspecialchars($oaciItem, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($oaciItem . " - " . $descItem, ENT_QUOTES, 'UTF-8') . "</option>\n";
        }
        ?>
    </datalist>

    <!-- Formulário de Registro / Edição -->
    <form method="post" aria-labelledby="formTitulo" id="formVoo">
        <?= campoCsrf() ?>
        <div class="form-cabecalho">
            <h2 id="formTitulo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <?= $vooEditar ? "Editar Voo #" . (int) $vooEditar["id"] : "Registrar Novo Voo" ?>
            </h2>

            <div class="preview-rota-container" id="previewRotaContainer">
                <div class="preview-rota-ponto">
                    <span id="previewPartida" class="preview-codigo"><?= htmlspecialchars($vooForm["partida"] ?? "ORIGEM") ?></span>
                    <small id="previewPartidaNome" class="preview-nome"><?= htmlspecialchars($aerodromosPorOaci[$vooForm["partida"] ?? ""]["Nome"] ?? "") ?></small>
                </div>
                <span class="seta-rota">➔</span>
                <div class="preview-rota-ponto">
                    <span id="previewDestino" class="preview-codigo"><?= htmlspecialchars($vooForm["destino"] ?? "DESTINO") ?></span>
                    <small id="previewDestinoNome" class="preview-nome"><?= htmlspecialchars($aerodromosPorOaci[$vooForm["destino"] ?? ""]["Nome"] ?? "") ?></small>
                </div>
            </div>
        </div>

        <input type="hidden" name="acao" value="<?= $vooEditar ? "editar" : "salvar" ?>">
        <?php if ($vooEditar): ?>
            <input type="hidden" name="id" value="<?= (int) $vooEditar["id"] ?>">
        <?php endif; ?>

        <div class="campos">
            <label>
                <span>Aeronave (Modelo)</span>
                <input id="campoAeronave" name="aeronave" list="listaAeronaves" placeholder="Ex: SR22 ou Baron 58" value="<?= htmlspecialchars($vooForm["aeronave"] ?? "") ?>" required autocomplete="off">
            </label>

            <label>
                <div class="label-com-unidade">
                    <span>Origem (ICAO)</span>
                    <span class="unidade-badge">4 letras</span>
                </div>
                <input id="campoPartida" name="partida" list="listaAerodromos" maxlength="4" placeholder="Ex: SBMT" value="<?= htmlspecialchars($vooForm["partida"] ?? "") ?>" style="text-transform: uppercase" oninput="this.value = this.value.toUpperCase()" required autocomplete="off">
            </label>

            <label>
                <div class="label-com-unidade">
                    <span>Destino (ICAO)</span>
                    <span class="unidade-badge">4 letras</span>
                </div>
                <input id="campoDestino" name="destino" list="listaAerodromos" maxlength="4" placeholder="Ex: SBRJ" value="<?= htmlspecialchars($vooForm["destino"] ?? "") ?>" style="text-transform: uppercase" oninput="this.value = this.value.toUpperCase()" required autocomplete="off">
            </label>

            <label>
                <div class="label-com-unidade">
                    <span>Distância</span>
                    <span class="unidade-badge">NM</span>
                </div>
                <input id="campoDistancia" type="number" name="distancia_nm" placeholder="Auto ou manual" value="<?= htmlspecialchars($vooForm["distancia_nm"] ?? "") ?>" min="0.01" step="0.01" required>
            </label>

            <label>
                <div class="label-com-unidade">
                    <span>Tanque na decolagem</span>
                    <span class="unidade-badge">galões</span>
                </div>
                <input id="campoTanqueDecolagem" type="number" name="tanque_decolagem" placeholder="Ex: 80" value="<?= htmlspecialchars($vooForm["tanque_decolagem"] ?? "") ?>" min="0" step="0.01" required>
            </label>

            <label>
                <div class="label-com-unidade">
                    <span>Tanque no pouso</span>
                    <span class="unidade-badge">galões</span>
                </div>
                <input id="campoTanquePouso" type="number" name="tanque_pouso" placeholder="Ex: 45" value="<?= htmlspecialchars($vooForm["tanque_pouso"] ?? "") ?>" min="0" step="0.01" required>
            </label>

            <label>
                <div class="label-com-unidade">
                    <span>Tempo de voo</span>
                    <span class="unidade-badge">HH:MM</span>
                </div>
                <input id="campoTempoVoo" type="time" name="tempo_voo" value="<?= htmlspecialchars(substr($vooForm["tempo_voo"] ?? "", 0, 5)) ?>" step="60" required>
            </label>

            <label>
                <div class="label-com-unidade">
                    <span>Altitude de Cruzeiro</span>
                    <span class="unidade-badge">pés (ft)</span>
                </div>
                <input type="number" name="altitude" placeholder="Ex: 8500" value="<?= htmlspecialchars($vooForm["altitude"] ?? "") ?>" min="0" required>
            </label>
        </div>

        <!-- Painel de Cálculo em Tempo Real e Validação -->
        <div id="painelCalculoVoo" class="painel-calculo-voo">
            <div class="item-calculo-voo">
                <span class="rotulo-calculo">Consumo Real:</span>
                <strong id="previewConsumoReal" class="valor-calculo">-</strong> <small class="unidade-calculo">gal</small>
            </div>
            <div class="item-calculo-voo">
                <span class="rotulo-calculo">GPH:</span>
                <strong id="previewConsumoGph" class="valor-calculo">-</strong> <small class="unidade-calculo">GPH</small>
            </div>
            <div class="item-calculo-voo">
                <span class="rotulo-calculo">Velocidade Média:</span>
                <strong id="previewVelocidade" class="valor-calculo">-</strong> <small class="unidade-calculo">kt</small>
            </div>
            <div id="alertaTanqueInvalido" class="alerta-tanque oculto">
                ⚠️ O tanque no pouso não pode ser maior que o tanque na decolagem!
            </div>
        </div>

        <!-- Aviso Marte SBMT -->
        <div id="avisoOrigemSBMT" class="aviso-info oculto">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div>
                <strong>Atenção operacional — Saídas de Marte (SBMT):</strong>
                <p>Perus - Morumgaba :: adicionar 10 NM &bull; Abril - Itapevi :: adicionar 3 NM</p>
            </div>
        </div>

        <label style="margin-top: 16px;">
            <span>Observações de voo</span>
            <textarea name="observacoes" rows="2" placeholder="Condições meteorológicas, rotas específicas ou anotações operacionais..."><?= htmlspecialchars($vooEditar["observacoes"] ?? "") ?></textarea>
        </label>

        <div class="acoes-form-rodape">
            <button type="submit" id="btnSalvarVoo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                <?= $vooEditar ? "Atualizar Voo" : "Salvar Voo" ?>
            </button>
            <?php if ($vooEditar): ?>
                <a href="index.php" class="btn-link-cancelar">Cancelar edição</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Seção de Voos Registrados -->
    <section class="tabela voos">
        <div class="titulo-tabela">
            <h2>
                Voos Registrados 
                <span class="contador-voos"><?= (int) $totalVoosRegistrados ?></span>
            </h2>

            <div class="barra-acoes-tabela">
                <form class="valores-combustivel" method="get">
                    <input type="hidden" name="partida" value="<?= htmlspecialchars($filtroPartida) ?>">
                    <input type="hidden" name="destino" value="<?= htmlspecialchars($filtroDestino) ?>">
                    <input type="hidden" name="filtro_aeronave" value="<?= htmlspecialchars($filtroAeronave) ?>">
                    <input type="hidden" name="ordenar" value="<?= htmlspecialchars($ordenarVoosPor) ?>">
                    <input type="hidden" name="direcao" value="<?= htmlspecialchars($direcaoVoos) ?>">
                    
                    <label class="campo-valor-combustivel">
                        <span class="nome-combustivel">JetA</span>
                        <span class="entrada-combustivel">
                            <input type="text" name="valor_jeta" value="<?= htmlspecialchars($valorJeta !== null ? number_format($valorJeta, 2, ".", "") : "") ?>" inputmode="decimal" data-decimal aria-label="Valor JetA (R$/litro)">
                            <small>R$/L</small>
                        </span>
                    </label>

                    <label class="campo-valor-combustivel">
                        <span class="nome-combustivel">Avgas</span>
                        <span class="entrada-combustivel">
                            <input type="text" name="valor_avgas" value="<?= htmlspecialchars($valorAvgas !== null ? number_format($valorAvgas, 2, ".", "") : "") ?>" inputmode="decimal" data-decimal aria-label="Valor Avgas (R$/litro)">
                            <small>R$/L</small>
                        </span>
                    </label>

                    <button type="submit" class="btn-secundario">Calcular</button>
                </form>
            </div>
        </div>

        <div class="botoes-toggle-paineis">
            <button type="button" class="botao-filtro" id="botaoFiltro" aria-expanded="false" aria-controls="painelFiltros">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filtros
            </button>
            <button type="button" class="botao-filtro" id="botaoMaisVoados" aria-expanded="false" aria-controls="painelMaisVoados">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20V10M18 20V4"></path></svg>
                + Voados
            </button>
            <?php 
                $paramsExportar = $_GET;
                $paramsExportar["exportar"] = "csv";
                $linkExportar = "?" . http_build_query($paramsExportar);
            ?>
            <a href="<?= htmlspecialchars($linkExportar) ?>" class="botao-filtro botao-exportar" title="Exportar voos listados para CSV (Excel)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Exportar CSV
            </a>
        </div>

        <!-- Painel Rotas Mais Voadas -->
        <div class="tabela-rotas oculto" id="painelMaisVoados">
            <h3>Rotas mais frequentes na frota</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rota</th>
                        <th>Qtd</th>
                        <th>Voo mais lento</th>
                        <th>Voo mais rápido</th>
                        <th>Diferença</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankingRotas as $rota): 
                        $rapido = $rota["mais_rapido"]; 
                        $lento = $rota["mais_lento"]; 
                        $custoRapido = custoVooRota($rapido, $valorJeta, $valorAvgas, $litrosPorGalao); 
                        $custoLento = custoVooRota($lento, $valorJeta, $valorAvgas, $litrosPorGalao); 
                        $minutosRapido = $rapido ? tempoEmMinutos($rapido["tempo_voo"]) : null; 
                        $minutosLento = $lento ? tempoEmMinutos($lento["tempo_voo"]) : null; 
                        $percentualTempo = $minutosLento && $minutosRapido !== null ? (1 - $minutosRapido / $minutosLento) * 100 : null; 
                        $percentualCusto = $custoLento && $custoRapido !== null ? (1 - $custoRapido / $custoLento) * 100 : null; 
                        $nomePartidaRota = $aerodromosPorOaci[$rota["partida"]]["Nome"] ?? "";
                        $nomeDestinoRota = $aerodromosPorOaci[$rota["destino"]]["Nome"] ?? "";
                    ?>
                        <tr>
                            <td>
                                <div class="rota-coluna-wrapper">
                                    <span class="badge-rota">
                                        <?= htmlspecialchars($rota["partida"]) ?> 
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg> 
                                        <?= htmlspecialchars($rota["destino"]) ?>
                                    </span>
                                    <?php if ($nomePartidaRota || $nomeDestinoRota): ?>
                                        <span class="sub-rota-nomes"><?= htmlspecialchars($nomePartidaRota ?: $rota["partida"]) ?> ➔ <?= htmlspecialchars($nomeDestinoRota ?: $rota["destino"]) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong><?= (int) $rota["quantidade"] ?></strong></td>
                            <td><?php if ($lento): ?><?= htmlspecialchars(substr($lento["tempo_voo"], 0, 5)) ?> &bull; <?= htmlspecialchars($lento["aeronave"]) ?> &bull; <?= $custoLento !== null ? "R$ " . number_format($custoLento, 0, ",", ".") : "-" ?><?php else: ?>-<?php endif; ?></td>
                            <td><?php if ($rapido): ?><?= htmlspecialchars(substr($rapido["tempo_voo"], 0, 5)) ?> &bull; <?= htmlspecialchars($rapido["aeronave"]) ?> &bull; <?= $custoRapido !== null ? "R$ " . number_format($custoRapido, 0, ",", ".") : "-" ?><?php else: ?>-<?php endif; ?></td>
                            <td><?= $percentualTempo !== null ? number_format(abs($percentualTempo), 0, ",", ".") . "% mais " . ($percentualTempo >= 0 ? "rápido" : "lento") : "-" ?> | <?= $percentualCusto !== null ? number_format(abs($percentualCusto), 0, ",", ".") . "% mais " . ($percentualCusto >= 0 ? "barato" : "caro") : "-" ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Painel Filtros -->
        <form class="filtros-voos oculto" id="painelFiltros" method="get">
            <label>
                <span>Aeronave</span>
                <select name="filtro_aeronave">
                    <option value="">Todas</option>
                    <?php while ($aeronaveFiltro = $aeronavesFiltro->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($aeronaveFiltro["aeronave"]) ?>" <?= $filtroAeronave === $aeronaveFiltro["aeronave"] ? "selected" : "" ?>><?= htmlspecialchars($aeronaveFiltro["aeronave"]) ?></option>
                    <?php endwhile; ?>
                </select>
            </label>

            <label>
                <span>Origem</span>
                <select name="partida">
                    <option value="">Todas</option>
                    <?php while ($partidaDisponivel = $partidasDisponiveis->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($partidaDisponivel["partida"]) ?>" <?= $filtroPartida === $partidaDisponivel["partida"] ? "selected" : "" ?>><?= htmlspecialchars($partidaDisponivel["partida"]) ?></option>
                    <?php endwhile; ?>
                </select>
            </label>

            <label>
                <span>Destino</span>
                <select name="destino">
                    <option value="">Todas</option>
                    <?php while ($destinoDisponivel = $destinosDisponiveis->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($destinoDisponivel["destino"]) ?>" <?= $filtroDestino === $destinoDisponivel["destino"] ? "selected" : "" ?>><?= htmlspecialchars($destinoDisponivel["destino"]) ?></option>
                    <?php endwhile; ?>
                </select>
            </label>

            <label>
                <span>Ordem</span>
                <select name="ordenar">
                    <option value="data" <?= $ordenarVoosPor === "data" ? "selected" : "" ?>>Data</option>
                    <option value="aeronave" <?= $ordenarVoosPor === "aeronave" ? "selected" : "" ?>>Aeronave</option>
                    <option value="rota" <?= $ordenarVoosPor === "rota" ? "selected" : "" ?>>Rota</option>
                    <option value="distancia" <?= $ordenarVoosPor === "distancia" ? "selected" : "" ?>>Distância</option>
                    <option value="tempo" <?= $ordenarVoosPor === "tempo" ? "selected" : "" ?>>Tempo</option>
                    <option value="consumo" <?= $ordenarVoosPor === "consumo" ? "selected" : "" ?>>Consumo real</option>
                    <option value="gph" <?= $ordenarVoosPor === "gph" ? "selected" : "" ?>>GPH</option>
                    <option value="velocidade" <?= $ordenarVoosPor === "velocidade" ? "selected" : "" ?>>Velocidade</option>
                    <option value="custo" <?= $ordenarVoosPor === "custo" ? "selected" : "" ?>>Custo de Combustível</option>
                </select>
            </label>

            <label>
                <span>Direção</span>
                <select name="direcao">
                    <option value="ASC" <?= $direcaoVoos === "ASC" ? "selected" : "" ?>>Crescente</option>
                    <option value="DESC" <?= $direcaoVoos === "DESC" ? "selected" : "" ?>>Decrescente</option>
                </select>
            </label>

            <input type="hidden" name="valor_jeta" value="<?= htmlspecialchars($valorJetaInformado) ?>">
            <input type="hidden" name="valor_avgas" value="<?= htmlspecialchars($valorAvgasInformado) ?>">
            <button type="submit">Filtrar</button>
            <a class="limpar-filtros" href="index.php">Limpar</a>
        </form>

        <!-- Tabela Principal de Voos -->
        <div class="tabela-container">
            <table>
                <thead>
                    <tr>
                        <th><a href="<?= linkOrdenarVoos("aeronave", $ordenarVoosPor, $direcaoVoos) ?>">Aeronave<?= setaOrdenacaoVoos("aeronave", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th><a href="<?= linkOrdenarVoos("rota", $ordenarVoosPor, $direcaoVoos) ?>">Rota<?= setaOrdenacaoVoos("rota", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th><a href="<?= linkOrdenarVoos("distancia", $ordenarVoosPor, $direcaoVoos) ?>">Distância<?= setaOrdenacaoVoos("distancia", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th><a href="<?= linkOrdenarVoos("tempo", $ordenarVoosPor, $direcaoVoos) ?>">Tempo<?= setaOrdenacaoVoos("tempo", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th><a href="<?= linkOrdenarVoos("consumo", $ordenarVoosPor, $direcaoVoos) ?>">Consumo Real<?= setaOrdenacaoVoos("consumo", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th><a href="<?= linkOrdenarVoos("gph", $ordenarVoosPor, $direcaoVoos) ?>">GPH<?= setaOrdenacaoVoos("gph", $ordenarVoosPor, $direcaoVoos) ?></a><small class="subtitulo-gph">(Real / Esperado)</small></th>
                        <th><a href="<?= linkOrdenarVoos("velocidade", $ordenarVoosPor, $direcaoVoos) ?>">Velocidade<?= setaOrdenacaoVoos("velocidade", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th><a href="<?= linkOrdenarVoos("custo", $ordenarVoosPor, $direcaoVoos) ?>">Custo Combustível<?= setaOrdenacaoVoos("custo", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th><a href="<?= linkOrdenarVoos("data", $ordenarVoosPor, $direcaoVoos) ?>">Data<?= setaOrdenacaoVoos("data", $ordenarVoosPor, $direcaoVoos) ?></a></th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($voos->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 32px; color: var(--muted);">Nenhum voo encontrado com os critérios selecionados.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($voo = $voos->fetch_assoc()): 
                            $nomePartida = $aerodromosPorOaci[$voo["partida"]]["Nome"] ?? "";
                            $cidadePartida = $aerodromosPorOaci[$voo["partida"]]["Município"] ?? "";
                            $nomeDestino = $aerodromosPorOaci[$voo["destino"]]["Nome"] ?? "";
                            $cidadeDestino = $aerodromosPorOaci[$voo["destino"]]["Município"] ?? "";
                            $tooltipRota = ($nomePartida ? "{$voo['partida']} ({$nomePartida} - {$cidadePartida})" : $voo['partida'])
                                . " ➔ "
                                . ($nomeDestino ? "{$voo['destino']} ({$nomeDestino} - {$cidadeDestino})" : $voo['destino']);
                        ?>
                            <tr>
                                <td><span class="badge-aeronave"><?= htmlspecialchars($voo["aeronave"]) ?></span></td>
                                <td>
                                    <div class="rota-coluna-wrapper" title="<?= htmlspecialchars($tooltipRota) ?>">
                                        <span class="badge-rota">
                                            <?= htmlspecialchars($voo["partida"]) ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                            <?= htmlspecialchars($voo["destino"]) ?>
                                        </span>
                                        <?php if ($nomePartida || $nomeDestino): ?>
                                            <span class="sub-rota-nomes">
                                                <?= htmlspecialchars($nomePartida ?: $voo["partida"]) ?> ➔ <?= htmlspecialchars($nomeDestino ?: $voo["destino"]) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong><?= number_format($voo["distancia_nm"], 0, ",", ".") ?></strong> <small class="unidade-badge">NM</small></td>
                                <td><?= htmlspecialchars(substr($voo["tempo_voo"], 0, 5)) ?></td>
                                <?php 
                                    $classeConsumo = (float) $voo["consumo_gph"] > (float) $voo["consumo_gph_cadastrado"] 
                                        ? "consumo-maior" 
                                        : ((float) $voo["consumo_gph"] < (float) $voo["consumo_gph_cadastrado"] ? "consumo-menor" : ""); 
                                ?>
                                <td><strong><?= number_format($voo["consumo_real"], 0, ",", ".") ?></strong> <small class="unidade-badge">gal</small></td>
                                <td class="celula-gph">
                                    <span class="<?= $classeConsumo ?>"><?= number_format($voo["consumo_gph"], 0, ",", ".") ?></span>
                                    <small class="gph-cadastrado">(<?= number_format($voo["consumo_gph_cadastrado"], 0, ",", ".") ?>)</small>
                                </td>
                                <td><strong><?= number_format($voo["velocidade"], 0, ",", ".") ?></strong> <small class="unidade-badge">kt</small></td>
                                <?php $custoCombustivel = custoVooRota($voo, $valorJeta, $valorAvgas, $litrosPorGalao); ?>
                                <td class="custo-destaque"><?= $custoCombustivel !== null ? "R$ " . number_format($custoCombustivel, 0, ",", ".") : "-" ?></td>
                                <td class="data-registro"><?= htmlspecialchars(date("d/m/Y H:i", strtotime($voo["criado_em"]))) ?></td>
                                <td class="acoes">
                                    <a class="btn-acao-editar" href="?editar=<?= (int) $voo["id"] ?>" title="Editar voo" aria-label="Editar voo">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>
                                    <form method="post" style="display: inline; padding: 0; border: 0; background: transparent; box-shadow: none;">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $voo["id"] ?>">
                                        <button class="btn-acao-excluir" type="submit" title="Excluir voo" aria-label="Excluir voo" onclick="return confirm('Tem certeza que deseja excluir este voo?')">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <script>
        const campoPartida = document.getElementById("campoPartida");
        const campoDestino = document.getElementById("campoDestino");
        const campoDistancia = document.getElementById("campoDistancia");
        const avisoOrigemSBMT = document.getElementById("avisoOrigemSBMT");
        const previewPartida = document.getElementById("previewPartida");
        const previewPartidaNome = document.getElementById("previewPartidaNome");
        const previewDestino = document.getElementById("previewDestino");
        const previewDestinoNome = document.getElementById("previewDestinoNome");

        const cacheAerodromos = {};

        function buscarAerodromo(codigo, elementoPreviewNome, callback) {
            const cod = codigo.trim().toUpperCase();
            if (cod.length !== 4) {
                if (elementoPreviewNome) elementoPreviewNome.textContent = "";
                return;
            }

            if (cacheAerodromos[cod]) {
                aplicarDadosAerodromo(cacheAerodromos[cod], elementoPreviewNome);
                if (callback) callback(cacheAerodromos[cod]);
                return;
            }

            fetch(`calcular_distancia.php?oaci=${cod}`)
                .then(r => r.json())
                .then(data => {
                    if (data.encontrado && data.aerodromo) {
                        cacheAerodromos[cod] = data.aerodromo;
                        aplicarDadosAerodromo(data.aerodromo, elementoPreviewNome);
                        if (callback) callback(data.aerodromo);
                    } else {
                        if (elementoPreviewNome) elementoPreviewNome.textContent = "";
                    }
                })
                .catch(() => {});
        }

        function aplicarDadosAerodromo(aerodromo, elementoPreviewNome) {
            if (elementoPreviewNome) {
                elementoPreviewNome.textContent = aerodromo.nome || "";
            }
        }

        function atualizarOrigem() {
            const partidaVal = campoPartida.value.trim().toUpperCase();
            avisoOrigemSBMT.classList.toggle("oculto", partidaVal !== "SBMT");
            if (previewPartida) previewPartida.textContent = partidaVal || "ORIGEM";
            buscarAerodromo(partidaVal, previewPartidaNome);
        }

        function atualizarDestino() {
            const destinoVal = campoDestino.value.trim().toUpperCase();
            if (previewDestino) previewDestino.textContent = destinoVal || "DESTINO";
            buscarAerodromo(destinoVal, previewDestinoNome);
        }

        function calcularDistanciaAutomatica() {
            const origem = campoPartida.value.trim().toUpperCase();
            const destino = campoDestino.value.trim().toUpperCase();
            if (origem.length < 4 || destino.length < 4) return;
            fetch(`calcular_distancia.php?origem=${origem}&destino=${destino}`)
                .then(resposta => resposta.json())
                .then(dados => {
                    if (dados.distancia_nm !== undefined) campoDistancia.value = dados.distancia_nm;
                    if (dados.origem) {
                        cacheAerodromos[origem] = dados.origem;
                        aplicarDadosAerodromo(dados.origem, previewPartidaNome);
                    }
                    if (dados.destino) {
                        cacheAerodromos[destino] = dados.destino;
                        aplicarDadosAerodromo(dados.destino, previewDestinoNome);
                    }
                })
                .catch(() => {});
        }

        campoPartida.addEventListener("input", atualizarOrigem);
        campoDestino.addEventListener("input", atualizarDestino);
        campoPartida.addEventListener("change", calcularDistanciaAutomatica);
        campoDestino.addEventListener("change", calcularDistanciaAutomatica);

        atualizarOrigem();
        atualizarDestino();

        document.querySelectorAll("[data-decimal]").forEach(campo => {
            campo.addEventListener("blur", () => {
                const valor = Number(campo.value.replace(",", "."));
                if (!Number.isNaN(valor)) campo.value = valor.toFixed(2);
            });
        });

        // Validação de combustível e cálculo em tempo real no formulário
        const campoTanqueDecolagem = document.getElementById("campoTanqueDecolagem");
        const campoTanquePouso = document.getElementById("campoTanquePouso");
        const campoTempoVoo = document.getElementById("campoTempoVoo");
        const previewConsumoReal = document.getElementById("previewConsumoReal");
        const previewConsumoGph = document.getElementById("previewConsumoGph");
        const previewVelocidade = document.getElementById("previewVelocidade");
        const alertaTanqueInvalido = document.getElementById("alertaTanqueInvalido");
        const btnSalvarVoo = document.getElementById("btnSalvarVoo");

        function atualizarCalculosEValidacaoVoo() {
            if (!campoTanqueDecolagem || !campoTanquePouso) return;
            const strDec = campoTanqueDecolagem.value.trim();
            const strPou = campoTanquePouso.value.trim();
            const tanqueDec = parseFloat(strDec);
            const tanquePou = parseFloat(strPou);
            const dist = parseFloat(campoDistancia ? campoDistancia.value : 0) || 0;
            const tempoStr = campoTempoVoo ? campoTempoVoo.value : "";

            // Enquanto os campos não forem preenchidos, oculta a frase de alerta
            const ambosPreenchidos = strDec !== "" && strPou !== "" && !isNaN(tanqueDec) && !isNaN(tanquePou);
            const temErroCombustivel = ambosPreenchidos && (tanquePou > tanqueDec);

            if (temErroCombustivel) {
                if (alertaTanqueInvalido) alertaTanqueInvalido.classList.remove("oculto");
                campoTanquePouso.classList.add("campo-invalido");
                if (btnSalvarVoo) btnSalvarVoo.disabled = true;
            } else {
                if (alertaTanqueInvalido) alertaTanqueInvalido.classList.add("oculto");
                campoTanquePouso.classList.remove("campo-invalido");
                if (btnSalvarVoo) btnSalvarVoo.disabled = false;
            }

            // Consumo Real e métricas estimadas
            if (ambosPreenchidos && !temErroCombustivel) {
                const consumo = Math.max(0, tanqueDec - tanquePou);
                if (previewConsumoReal) {
                    previewConsumoReal.textContent = consumo.toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                }

                // Horas e GPH / Velocidade
                let horas = 0;
                if (tempoStr && tempoStr.includes(":")) {
                    const [h, m] = tempoStr.split(":").map(Number);
                    horas = (h || 0) + ((m || 0) / 60);
                }

                if (previewConsumoGph) {
                    if (horas > 0 && consumo > 0) {
                        previewConsumoGph.textContent = (consumo / horas).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                    } else {
                        previewConsumoGph.textContent = "-";
                    }
                }

                if (previewVelocidade) {
                    if (horas > 0 && dist > 0) {
                        previewVelocidade.textContent = Math.round(dist / horas).toLocaleString("pt-BR");
                    } else {
                        previewVelocidade.textContent = "-";
                    }
                }
            } else {
                if (previewConsumoReal) previewConsumoReal.textContent = "-";
                if (previewConsumoGph) previewConsumoGph.textContent = "-";
                if (previewVelocidade) previewVelocidade.textContent = "-";
            }
        }

        if (campoTanqueDecolagem) campoTanqueDecolagem.addEventListener("input", atualizarCalculosEValidacaoVoo);
        if (campoTanquePouso) campoTanquePouso.addEventListener("input", atualizarCalculosEValidacaoVoo);
        if (campoTempoVoo) campoTempoVoo.addEventListener("input", atualizarCalculosEValidacaoVoo);
        if (campoDistancia) campoDistancia.addEventListener("input", atualizarCalculosEValidacaoVoo);

        // Preenchimento automático do tanque na decolagem ao selecionar a aeronave
        const tanquesPorAeronave = <?= json_encode($tanquesPorAeronave) ?>;
        const campoAeronave = document.getElementById("campoAeronave");
        if (campoAeronave && campoTanqueDecolagem) {
            function preencherTanqueAeronave() {
                const nome = campoAeronave.value.trim();
                if (tanquesPorAeronave[nome] !== undefined && tanquesPorAeronave[nome] > 0) {
                    campoTanqueDecolagem.value = tanquesPorAeronave[nome];
                    atualizarCalculosEValidacaoVoo();
                }
            }
            campoAeronave.addEventListener("input", preencherTanqueAeronave);
            campoAeronave.addEventListener("change", preencherTanqueAeronave);
        }

        atualizarCalculosEValidacaoVoo();

        const botaoFiltro = document.getElementById("botaoFiltro");
        const painelFiltros = document.getElementById("painelFiltros");
        if (botaoFiltro && painelFiltros) {
            botaoFiltro.addEventListener("click", function () {
                const oculto = painelFiltros.classList.toggle("oculto");
                botaoFiltro.setAttribute("aria-expanded", String(!oculto));
            });
        }

        const botaoMaisVoados = document.getElementById("botaoMaisVoados");
        const painelMaisVoados = document.getElementById("painelMaisVoados");
        if (botaoMaisVoados && painelMaisVoados) {
            botaoMaisVoados.addEventListener("click", function () {
                const oculto = painelMaisVoados.classList.toggle("oculto");
                botaoMaisVoados.setAttribute("aria-expanded", String(!oculto));
            });
        }
    </script>
    <?php include "rodape.php"; ?>
</body>
</html>
<?php $conn->close(); ?>