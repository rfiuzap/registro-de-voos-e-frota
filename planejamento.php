<?php
if (!ob_start("ob_gzhandler")) ob_start();
require "auth.php";
require_once "aerodromos_dados.php";
$usuarioId = usuarioAtualId(isset($_GET['api']));

// Manipuladores de API AJAX para Salvar, Listar e Excluir Planos de Voo
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $api = $_GET['api'];

    if ($api === 'salvar_plano') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $nome = trim($input['nome'] ?? '');
        $modelo = trim($input['modelo_aeronave'] ?? '');
        $origem = strtoupper(trim($input['origem'] ?? ''));
        $destino = strtoupper(trim($input['destino'] ?? ''));
        $distancia = floatval($input['distancia'] ?? 0);
        $altitude = intval($input['altitude_cruzeiro'] ?? 0);
        $tanque = floatval($input['tanque_decolagem'] ?? 0);
        $velSubida = floatval($input['velocidade_subida'] ?? 0);
        $razaoSubida = floatval(str_replace('.', '', strval($input['razao_subida'] ?? 0)));
        $velCruzeiro = floatval($input['velocidade_cruzeiro'] ?? 0);
        $razaoDescida = floatval(str_replace('.', '', strval($input['razao_descida'] ?? 0)));
        $consumoGph = floatval($input['consumo_gph'] ?? 0);
        $tempoEst = trim($input['tempo_estimado'] ?? '');
        $consumoEst = floatval($input['consumo_estimado'] ?? 0);

        if (!$nome || !$origem || !$destino) {
            echo json_encode(['sucesso' => false, 'erro' => 'Preencha o nome do planejamento, origem e destino.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO planejamentos (nome, modelo_aeronave, origem, destino, distancia, altitude_cruzeiro, tanque_decolagem, velocidade_subida, razao_subida, velocidade_cruzeiro, razao_descida, consumo_gph, tempo_estimado, consumo_estimado, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssddddddddsdi", $nome, $modelo, $origem, $destino, $distancia, $altitude, $tanque, $velSubida, $razaoSubida, $velCruzeiro, $razaoDescida, $consumoGph, $tempoEst, $consumoEst, $usuarioId);

        if ($stmt->execute()) {
            echo json_encode(['sucesso' => true, 'id' => $conn->insert_id, 'mensagem' => 'Planejamento salvo com sucesso!']);
        } else {
            echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar o planejamento.']);
        }
        exit;
    }

    if ($api === 'listar_planos') {
        $res = $conn->query("SELECT * FROM planejamentos WHERE usuario_id = {$usuarioId} ORDER BY criado_em DESC");
        $planos = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $planos[] = $row;
            }
        }
        echo json_encode(['sucesso' => true, 'planos' => $planos]);
        exit;
    }

    if ($api === 'excluir_plano') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? ($_POST['id'] ?? 0));
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM planejamentos WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param("ii", $id, $usuarioId);
            if ($stmt->execute()) {
                echo json_encode(['sucesso' => true, 'mensagem' => 'Planejamento excluído com sucesso!']);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => 'Erro ao excluir o planejamento.']);
            }
        } else {
            echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
        }
        exit;
    }

    echo json_encode(['sucesso' => false, 'erro' => 'Ação inválida.']);
    exit;
}

$dadosAerodromos = carregarDadosAerodromos();
$todosAerodromos = $dadosAerodromos["lista"];
$aerodromosPorOaci = $dadosAerodromos["por_oaci"];

$aeronavesQuery = $conn->query("SELECT id, fabricante, modelo, ano, velocidade_cruzeiro, velocidade_subida, razao_subida, teto_operacional, altitude_cruzeiro_ideal, capacidade_tanque, consumo_gph, tipo_combustivel, peso_vazio, peso_maximo_decolagem, carga_util, assentos, foto FROM aeronaves WHERE " . sqlAeronavesVisiveis($usuarioId) . " ORDER BY fabricante, modelo");
$listaAeronaves = [];
while ($aero = $aeronavesQuery->fetch_assoc()) {
    $listaAeronaves[] = $aero;
}
$valorJeta = 5.96;
$valorAvgas = 10.70;
$litrosPorGalao = 3.785411784;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= tokenCsrf() ?>">
    <title>Planejamento de Voo | Operações de Frota</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body>
    <!-- Topo da Aplicação -->
    <header class="topo">
        <div class="marca">
            <div class="logo-aplicacao" role="img" aria-label="Logo Registro de Voos"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg></div>
            <div>
                <h1>Planejamento de Voo</h1>
                <p>Estimativa de perfil vertical, TOC, TOD, cruzeiro e consumo de combustível</p>
            </div>
        </div>
        <?php include "usuario_menu.php"; ?>
    </header>
    <?php include "aviso_visitante.php"; ?>

    <!-- Navegação em Tabs -->
    <nav class="navegacao" aria-label="Navegação principal">
        <a href="planejamento.php" class="ativo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
            <span>Planejamento<br>de voo</span>
        </a>
        <a href="index.php">
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

    <!-- Datalist de Aeródromos -->
    <datalist id="listaAerodromos">
        <?php
        foreach ($todosAerodromos as $aeroItem) {
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

    <!-- Datalist de Aeronaves (Apenas Modelo) -->
    <datalist id="listaAeronaves">
        <?php foreach ($listaAeronaves as $aero): ?>
            <option value="<?= htmlspecialchars($aero["modelo"]) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <main class="painel-planejamento">
        <!-- Formulário de Parâmetros -->
        <section class="card-planejamento">
            <div class="form-cabecalho">
                <div class="form-cabecalho-principal">
                    <h2>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        Planejamento de Voo
                    </h2>
                    <div class="acoes-planejamento">
                        <button type="button" id="btnVerMapaTopo" class="btn-acao-plano btn-mapa-topo btn-icone-so ativo" onclick="alternarCardMapaRota()" title="Visualizar Rota no Mapa" aria-label="Visualizar Rota no Mapa">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
                        </button>
                        <button type="button" id="btnSalvarPlano" class="btn-acao-plano btn-salvar btn-icone-so" onclick="abrirModalSalvar()" title="Salvar Planejamento" aria-label="Salvar Planejamento">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        </button>
                        <button type="button" id="btnVerPlanosSalvos" class="btn-acao-plano btn-planos-salvos btn-icone-so" onclick="abrirModalPlanosSalvos()" title="Planos Salvos" aria-label="Planos Salvos">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                            <span id="badgeQtdPlanos" class="badge-qtd-planos">0</span>
                        </button>
                        <button type="button" id="btnLimparPlano" class="btn-acao-plano btn-limpar btn-icone-so" onclick="limparTudoENovo()" title="Novo / Limpar formulário" aria-label="Novo / Limpar formulário">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 1. ORIGEM E DESTINO EM DESTAQUE COM ÍCONE DE SETA ENTRE ELES -->
            <div class="rota-destaque-container">
                <!-- Origem -->
                <div class="card-campo-rota card-campo-origem">
                    <label for="campoPartida" class="label-rota-destaque">
                        <div class="label-rota-topo">
                            <span class="tag-ponto-rota tag-origem">ORIGEM</span>
                            <span id="badgeAltOrigem" class="badge-alt-rota">0 ft</span>
                        </div>
                        <span class="sublabel-rota">Aeródromo de Partida (ICAO)</span>
                    </label>
                    <div class="input-rota-wrapper">
                        <svg class="icone-input-rota" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                        <input id="campoPartida" class="input-rota-destaque" list="listaAerodromos" maxlength="4" placeholder="Ex: SBMT" style="text-transform: uppercase" oninput="this.value = this.value.toUpperCase()" required autocomplete="off">
                    </div>
                    <div class="preview-rota-info">
                        <span id="previewPartidaNome" class="preview-aerodromo-nome">Informe o ICAO de origem</span>
                        <span id="previewPartida" style="display:none">ORIGEM</span>
                        <span id="previewPartidaAlt" style="display:none">0 ft</span>
                    </div>
                </div>

                <!-- Ícone de Seta entre Origem e Destino com Rumo / Bússola -->
                <div class="divisor-rota-seta">
                    <div class="circulo-seta-rota" id="indicadorSetaRota" title="Sentido da Rota">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </div>
                    <div id="badgeDirecaoBussola" class="badge-bussola-rota oculto" title="Direção da bússola / Rumo verdadeiro">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                        <span id="txtRumoGraus">--°</span>
                        <span id="txtRumoDirecao">--</span>
                    </div>
                </div>

                <!-- Destino -->
                <div class="card-campo-rota card-campo-destino">
                    <label for="campoDestino" class="label-rota-destaque">
                        <div class="label-rota-topo">
                            <span class="tag-ponto-rota tag-destino">DESTINO</span>
                            <span id="badgeAltDestino" class="badge-alt-rota">0 ft</span>
                        </div>
                        <span class="sublabel-rota">Aeródromo de Destino (ICAO)</span>
                    </label>
                    <div class="input-rota-wrapper">
                        <svg class="icone-input-rota" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <input id="campoDestino" class="input-rota-destaque" list="listaAerodromos" maxlength="4" placeholder="Ex: SBRJ" style="text-transform: uppercase" oninput="this.value = this.value.toUpperCase()" required autocomplete="off">
                    </div>
                    <div class="preview-rota-info">
                        <span id="previewDestinoNome" class="preview-aerodromo-nome">Informe o ICAO de destino</span>
                        <span id="previewDestino" style="display:none">DESTINO</span>
                        <span id="previewDestinoAlt" style="display:none">0 ft</span>
                    </div>
                </div>
            </div>

            <!-- 1.05 CARD DE VISUALIZAÇÃO DA ROTA NO MAPA (TIPO CARD-REGRA-ALTITUDES) -->
            <div id="cardMapaRota" class="card-regra-altitudes card-mapa-rota">
                <div class="regra-alt-header mapa-card-header">
                    <div class="regra-alt-titulo">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
                        <strong>Mapa da Rota de Voo</strong>
                        <span class="badge-mapa-subtitulo">Visualização Geográfica & Satélite</span>
                    </div>
                    <div class="mapa-header-acoes">
                        <button type="button" class="btn-mapa-acao" onclick="centralizarRotaMapa()" title="Centralizar e ajustar enquadramento da rota">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                            <span>Ajustar Rota</span>
                        </button>
                        <button type="button" class="btn-fechar-card-mapa" onclick="fecharCardMapaRota()" title="Recolher mapa" aria-label="Recolher mapa">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                </div>

                <!-- Barra de Status e Métricas da Rota -->
                <div class="card-mapa-bar">
                    <div class="mapa-info-grupo">
                        <span class="mapa-info-badge badge-origem" id="mapaBadgeOrigem">--</span>
                        <svg class="mapa-seta-icone" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        <span class="mapa-info-badge badge-destino" id="mapaBadgeDestino">--</span>
                    </div>
                    <div class="mapa-metrics-grupo">
                        <div class="mapa-metric-pill">
                            <span class="metric-rotulo">DISTÂNCIA</span>
                            <span class="metric-valor" id="mapaTxtDistancia">-- NM</span>
                        </div>
                        <div class="mapa-metric-pill">
                            <span class="metric-rotulo">RUMO</span>
                            <span class="metric-valor" id="mapaTxtRumo">--°</span>
                        </div>
                        <div class="mapa-metric-pill" id="mapaPillTempoWrapper">
                            <span class="metric-rotulo">TEMPO EST.</span>
                            <span class="metric-valor" id="mapaTxtTempo">--:--</span>
                        </div>
                    </div>
                </div>

                <!-- Corpo do Mapa -->
                <div class="card-mapa-corpo">
                    <!-- Aviso quando não há rota traçada -->
                    <div id="avisoSemRotaMapa" class="aviso-sem-rota-mapa oculto">
                        <div class="aviso-sem-rota-conteudo">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <h4>Origem e Destino não definidos</h4>
                            <p>Informe os códigos ICAO de partida e destino nos campos acima para visualizar a rota projetada no mapa.</p>
                        </div>
                    </div>

                    <!-- Container do Leaflet -->
                    <div id="containerMapaRota" class="container-mapa-leaflet-card"></div>
                </div>

                <!-- Rodapé / Legenda -->
                <div class="card-mapa-footer">
                    <div class="mapa-legenda-rapida">
                        <span class="legenda-item"><span class="ponto-legenda azul"></span> Origem</span>
                        <span class="legenda-item"><span class="ponto-legenda verde"></span> Destino</span>
                        <span class="legenda-item"><span class="linha-legenda azul-animada"></span> Rota</span>
                    </div>
                    <button type="button" class="btn-recolher-mapa-link" onclick="fecharCardMapaRota()">
                        <span>Recolher Mapa ▲</span>
                    </button>
                </div>
            </div>

            <!-- 1.1 REGRA SEMICIRCULAR DE ALTITUDES (ABAIXO DE ORIGEM E DESTINO) -->
            <div id="containerRegraAltitudes" class="card-regra-altitudes oculto">
                <div class="regra-alt-header">
                    <div class="regra-alt-titulo">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                        <strong>Regra Semicircular de Níveis de Voo</strong>
                        <span id="badgeRegraRumo" class="badge-regra-rumo">Rumo 000°</span>
                    </div>
                    <div id="badgeRegraTipo" class="badge-regra-tipo impar">ALTITUDES ÍMPARES</div>
                </div>

                <p id="txtRegraExplicacao" class="regra-alt-desc">
                    Para voos com rumo magnético entre 000° e 179° (sentido Leste), a regra semicircular determina o uso de altitudes <strong>ímpares</strong>.
                </p>

                <div class="regra-alt-grupos">
                    <div class="regra-grupo">
                        <span class="regra-grupo-titulo">
                            <span class="dot-regra vfr"></span>
                            <span id="tituloRegraVfr">VFR (Visual • Ímpar + 500 ft)</span>
                        </span>
                        <div id="listaAltitudesVfr" class="pills-altitudes">
                            <!-- Pílulas clicáveis geradas via JS -->
                        </div>
                    </div>

                    <div class="regra-grupo">
                        <span class="regra-grupo-titulo">
                            <span class="dot-regra ifr"></span>
                            <span id="tituloRegraIfr">IFR (Instrumentos • Milhar Ímpar)</span>
                        </span>
                        <div id="listaAltitudesIfr" class="pills-altitudes">
                            <!-- Pílulas clicáveis geradas via JS -->
                        </div>
                    </div>
                </div>

                <div id="avisoConflitoRegraAlt" class="alerta-planejamento atencao oculto" style="margin-top: 10px; margin-bottom: 0;">
                    ⚠️ A altitude informada de <strong id="txtAltDigitadaConflito">0</strong> ft está em desacordo com a regra semicircular para este rumo (<span id="txtRegraEsperadaConflito">ÍMPAR</span>).
                </div>
            </div>

            <!-- 2. CAIXA NA LINHA DE BAIXO: SELEÇÃO DE AERONAVE AO LADO DO COMPARATIVO DE TODAS AS AERONAVES -->
            <div class="caixa-aeronave-e-frota">
                <!-- Coluna: Seleção de Aeronave e Parâmetros Principais -->
                <div class="painel-selecao-aeronave">
                    <div class="cabecalho-secao-aeronave">
                        <h3>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path></svg>
                            Aeronave da Operação
                        </h3>
                    </div>

                    <label class="label-campo-aeronave">
                        <span>Aeronave (Modelo)</span>
                        <input id="campoAeronave" list="listaAeronaves" placeholder="Selecione ou clique na lista ao lado..." required autocomplete="off">
                    </label>

                    <!-- Mini card de especificações da aeronave selecionada -->
                    <div id="cardAeroSelecionadaInfo" class="card-aeronave-mini-info oculto">
                        <div class="aero-mini-header">
                            <strong id="txtAeroMiniNome">Modelo</strong>
                            <span id="txtAeroMiniComb" class="badge-mini-comb">Avgas</span>
                        </div>
                        <div class="aero-mini-specs">
                            <div class="mini-spec-item">
                                <span>Cruzeiro:</span>
                                <strong id="txtAeroMiniVel">0 kt</strong>
                            </div>
                            <div class="mini-spec-item">
                                <span>Consumo:</span>
                                <strong id="txtAeroMiniGph">0 GPH</strong>
                            </div>
                            <div class="mini-spec-item">
                                <span>Tanque:</span>
                                <strong id="txtAeroMiniTanque">0 gal</strong>
                            </div>
                            <div class="mini-spec-item">
                                <span>Teto:</span>
                                <strong id="txtAeroMiniTeto">0 ft</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Parâmetros de Voo -->
                    <div class="campos-rota-secundarios">
                        <!-- Distância Total -->
                        <label>
                            <div class="label-com-unidade">
                                <span>Distância Total</span>
                                <span class="unidade-badge">NM</span>
                            </div>
                            <input id="campoDistancia" type="number" min="1" step="1" placeholder="Auto ou manual" required>
                        </label>

                        <!-- Altitude de Cruzeiro -->
                        <label>
                            <div class="label-com-unidade">
                                <span>Altitude de Cruzeiro</span>
                                <span class="unidade-badge">ft</span>
                            </div>
                            <input id="campoAltitudeCruzeiro" type="number" min="500" step="500" placeholder="Ex: 8500" required>
                        </label>

                    </div>

                    <!-- Parâmetros Operacionais Expansíveis -->
                    <details class="parametros-avancados">
                        <summary>
                            <div class="summary-bloco-texto">
                                <span class="summary-titulo-principal">Parâmetros de Desempenho</span>
                                <span class="summary-subtitulo">(calculados da aeronave)</span>
                            </div>
                        </summary>
                        <div class="campos grid-planejamento-avancado">
                            <label>
                                <div class="label-com-unidade">
                                    <span>Velocidade na Subida</span>
                                    <span class="unidade-badge">kt</span>
                                </div>
                                <input id="campoVelocidadeSubida" type="number" min="30" step="1" placeholder="Ex: 110">
                            </label>

                            <label>
                                <div class="label-com-unidade">
                                    <span>Razão de Subida (ROC)</span>
                                    <span class="unidade-badge">ft/min</span>
                                </div>
                                <input id="campoRazaoSubida" type="text" inputmode="numeric" placeholder="Ex: 800" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')">
                            </label>

                            <label>
                                <div class="label-com-unidade">
                                    <span>Velocidade de Cruzeiro (TAS)</span>
                                    <span class="unidade-badge">kt</span>
                                </div>
                                <input id="campoVelocidade" type="number" min="30" step="1" placeholder="Ex: 150">
                            </label>

                            <label>
                                <div class="label-com-unidade">
                                    <span>Razão de Descida (ROD)</span>
                                    <span class="unidade-badge">ft/min</span>
                                </div>
                                <input id="campoRazaoDescida" type="text" inputmode="numeric" placeholder="Auto (Cruzeiro × 5)" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')">
                            </label>

                            <label>
                                <div class="label-com-unidade">
                                    <span>Consumo Médio</span>
                                    <span class="unidade-badge">GPH</span>
                                </div>
                                <input id="campoConsumoGph" type="number" min="1" step="1" placeholder="Ex: 16">
                            </label>
                        </div>
                    </details>
                </div>

                <!-- Coluna 2: Combustível, Carga & Autonomia (Funcionalidades de autonomia.php) -->
                <div class="painel-selecao-aeronave painel-autonomia-peso">
                    <div class="cabecalho-secao-aeronave" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="16" y1="14" x2="16" y2="18"></line><path d="M16 10h.01"></path><path d="M12 10h.01"></path><path d="M8 10h.01"></path><path d="M12 14h.01"></path><path d="M8 14h.01"></path><path d="M12 18h.01"></path><path d="M8 18h.01"></path></svg>
                            Combustível & Carga
                        </h3>
                        <span id="badgeCombustivelTipo" class="badge-mini-comb">Avgas</span>
                    </div>

                    <!-- Tanque com Barra Slide -->
                    <div class="campo-slider-container">
                        <div class="slider-header-linha">
                            <label for="sliderTanque" class="slider-rotulo">
                                <span>Combustível</span>
                            </label>
                            <div class="slider-valor-badge">
                                <span id="txtTanqueResumo">0 gal (0 L • 0%)</span>
                            </div>
                        </div>
                        <div class="slider-input-wrapper">
                            <input type="range" id="sliderTanque" min="0" max="100" step="1" value="0" class="input-slider-custom slider-combustivel">
                            <input id="campoTanqueDecolagem" type="hidden" value="0">
                        </div>
                        <div class="slider-escala-limites">
                            <span>0 gal</span>
                            <span id="txtTanqueCapacidadeMax">Máx: 0 gal</span>
                        </div>
                    </div>

                    <!-- Alerta de Combustível Insuficiente (Abaixo de Combustível) -->
                    <div id="alertaCombustivel" class="alerta-planejamento perigo oculto alerta-combustivel-coluna">
                        ⚠️ Atenção: Combustível na decolagem insuficiente para completar este voo!
                    </div>

                    <!-- Passageiros com Barra Slide (Embaixo do Tanque) -->
                    <div class="campo-slider-container">
                        <div class="slider-header-linha">
                            <label for="sliderPassageiros" class="slider-rotulo">
                                <span>Ocupantes</span>
                            </label>
                            <div class="slider-valor-badge">
                                <span id="txtPassageirosQtdBadge">1 ocupante (piloto)</span>
                            </div>
                        </div>
                        <div class="slider-input-wrapper">
                            <input type="range" id="sliderPassageiros" min="1" max="4" step="1" value="1" class="input-slider-custom slider-ocupantes">
                            <input id="campoQtdPassageiros" type="hidden" value="1">
                        </div>
                        <div class="slider-escala-limites">
                            <span>1 (piloto)</span>
                            <span id="txtAssentosMax">Máx:  assentos</span>
                        </div>
                        <div class="info-peso-ocupantes">
                            <span>80kg/pessoa + 15kg/bagagem.</span>
                            <strong id="txtPesoTotalOcupantes">95 kg (209 lb)</strong>
                        </div>
                    </div>

                    <!-- Cards de Autonomia (em Horas e Milhas) -->
                    <div class="grid-autonomia-kpis">
                        <div class="card-mini-autonomia">
                            <span class="mini-rotulo">Autonomia Total</span>
                            <strong id="txtAutonomiaTempo" class="mini-valor">00:00</strong>
                            <small id="txtAutonomiaNm" class="mini-sub">0 NM de alcance</small>
                        </div>
                        <div class="card-mini-autonomia">
                            <span class="mini-rotulo">Sobra Pós-Rota</span>
                            <strong id="txtAutonomiaSobraTempo" class="mini-valor">--:--</strong>
                            <small id="txtAutonomiaSobraNm" class="mini-sub">-- NM restante</small>
                        </div>
                    </div>

                    <!-- Dados de Pesos: Peso Vazio, Zero Fuel Weight, Peso Decolagem, Peso Pouso -->
                    <div class="tabela-pesos-operacionais">
                        <div class="linha-peso-item">
                            <span class="rotulo-peso">Peso Vazio (BEW):</span>
                            <strong id="txtPesoVazio" class="valor-peso">-</strong>
                        </div>
                        <div class="linha-peso-item">
                            <span class="rotulo-peso">Zero Fuel Weight (ZFW):</span>
                            <strong id="txtZfw" class="valor-peso">-</strong>
                        </div>
                        <div class="linha-peso-item">
                            <span class="rotulo-peso">Peso do Combustível:</span>
                            <strong id="txtPesoCombustivel" class="valor-peso">-</strong>
                        </div>
                        <div class="linha-peso-item destaque-tow">
                            <span class="rotulo-peso">Peso Decolagem (TOW):</span>
                            <strong id="txtTow" class="valor-peso">-</strong>
                        </div>
                        <div class="linha-peso-item">
                            <span class="rotulo-peso">Peso Pouso Estimado (LW):</span>
                            <strong id="txtLw" class="valor-peso">-</strong>
                        </div>
                        <div id="statusMtow" class="badge-status-mtow ok">
                            <span id="txtStatusMtow">Selecione uma aeronave para calcular</span>
                            <small id="txtStatusMtowSub" class="subtexto-status-mtow"></small>
                        </div>
                    </div>
                </div>

                <!-- Coluna 3: Lista de Todas as Aeronaves com Tempo e Custo da Rota (Linha a Linha Ordenável) -->
                <div class="painel-frota-comparativo">
                    <div class="cabecalho-frota-comparativo">
                        <h3>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            Comparativo da Frota na Rota
                        </h3>
                        <div class="linha-subtitulo-frota">
                            <p id="subtituloFrotaComparativo">Selecione origem e destino para calcular tempo e custo de cada aeronave</p>
                            <span class="badge-frota-qtd"><?= count($listaAeronaves) ?> aeronaves</span>
                        </div>
                    </div>

                    <!-- Tabela Linha a Linha Ordenável -->
                    <div class="tabela-frota-wrapper">
                        <table class="tabela-frota-comparativo">
                            <thead>
                                <tr>
                                    <th class="col-ordenavel" onclick="ordenarTabelaFrota('modelo')" title="Clique para ordenar por Aeronave">
                                        <div class="th-conteudo">
                                            <span>Aeronave</span>
                                            <span class="seta-ord-frota" id="ord_modelo">↕</span>
                                        </div>
                                    </th>
                                    <th class="col-ordenavel" onclick="ordenarTabelaFrota('tempo')" title="Clique para ordenar por Tempo de Voo">
                                        <div class="th-conteudo">
                                            <span>Tempo</span>
                                            <span class="seta-ord-frota" id="ord_tempo">↕</span>
                                        </div>
                                    </th>
                                    <th class="col-ordenavel" onclick="ordenarTabelaFrota('custo')" title="Clique para ordenar por Custo">
                                        <div class="th-conteudo">
                                            <span>Custo</span>
                                            <span class="seta-ord-frota" id="ord_custo">↕</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tabelaFrotaCorpo">
                                <!-- Linhas geradas via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Alertas dinâmicos -->
            <div id="alertaTeto" class="alerta-planejamento perigo oculto">
                ⚠️ A altitude de cruzeiro informada excede o teto operacional da aeronave (<span id="txtTetoMax">0</span> ft)!
            </div>
            <div id="alertaRotaCurta" class="alerta-planejamento atencao oculto">
                ℹ️ Rota curta: a distância total (<span id="txtDistRota">0</span> NM) é insuficiente para nivelar em <span id="txtAltPretendida">0</span> ft. Altitude máxima atingível: <strong id="txtAltMaxAtingivel">0</strong> ft (perfil triangular).
            </div>
        </section>

        <!-- Cards de Resultados / KPIs -->
        <section class="kpi-grid kpi-planejamento">
            <div class="kpi-card">
                <div class="kpi-icon primary" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-rotulo">Tempo Total</span>
                    <span id="kpiTempoTotal" class="kpi-valor">--:--</span>
                    <small id="kpiTempoTotalMin" class="kpi-sub">0 min de voo</small>
                </div>
            </div>


            <div class="kpi-card">
                <div class="kpi-icon warning" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-rotulo">Consumo Total</span>
                    <span id="kpiConsumoTotal" class="kpi-valor">-- gal</span>
                    <small id="kpiConsumoPct" class="kpi-sub">--% do tanque inicial</small>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon accent" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"></path></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-rotulo">Top of Climb (TOC)</span>
                    <span id="kpiTocDist" class="kpi-valor">-- NM</span>
                    <small id="kpiTocTempo" class="kpi-sub">atingido em --:--</small>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon primary" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-rotulo">Cruzeiro Nivelado</span>
                    <span id="kpiCruzeiroDist" class="kpi-valor">-- NM</span>
                    <small id="kpiCruzeiroTempo" class="kpi-sub">voado em --:--</small>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon warning" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>
                </div>
                <div class="kpi-info">
                    <span class="kpi-rotulo">Top of Descent (TOD)</span>
                    <span id="kpiTodDist" class="kpi-valor">-- NM</span>
                    <small id="kpiTodRem" class="kpi-sub">a -- NM do destino</small>
                </div>
            </div>
        </section>

        <!-- Representação Gráfica Dinâmica (Altitude x Distância) -->
        <section class="card-planejamento card-grafico-perfil">
            <div class="titulo-tabela">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17l6-6 4 4 8-8"></path><path d="M14 7h7v7"></path></svg>
                    Perfil Vertical de Voo (Altitude × Distância)
                </h2>
                <div class="legenda-perfil">
                    <span class="item-legenda"><i class="ponto-legenda ponto-origem-perfil"></i>Origem</span>
                    <span class="item-legenda"><i class="ponto-legenda ponto-subida"></i>Subida (TOC)</span>
                    <span class="item-legenda"><i class="ponto-legenda ponto-cruzeiro"></i>Cruzeiro</span>
                    <span class="item-legenda"><i class="ponto-legenda ponto-descida"></i>Descida (TOD)</span>
                    <span class="item-legenda"><i class="ponto-legenda ponto-destino-perfil"></i>Destino</span>
                </div>
            </div>

            <div class="grafico-perfil-wrapper">
                <canvas id="graficoPerfilVoo"></canvas>
            </div>
        </section>

        <!-- Detalhamento das Fases do Voo -->
        <section class="card-planejamento">
            <div class="titulo-tabela">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Detalhamento por Fase de Voo
                </h2>
            </div>

            <div class="tabela-container">
                <table class="tabela-fases">
                    <thead>
                        <tr>
                            <th>Fase</th>
                            <th>Trecho</th>
                            <th>Altitude</th>
                            <th>Distância</th>
                            <th>Tempo</th>
                            <th>Velocidade</th>
                            <th>Taxa V/S</th>
                            <th>Consumo Est.</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaFasesCorpo">
                        <tr>
                            <td><span class="badge-fase subida">Subida (Climb)</span></td>
                            <td>Origem ➔ TOC</td>
                            <td id="faseSubidaAlt">0 ft ➔ 0 ft</td>
                            <td id="faseSubidaDist">0 NM</td>
                            <td id="faseSubidaTempo">--:--</td>
                            <td id="faseSubidaVel">0 kt</td>
                            <td id="faseSubidaTaxa">+0 ft/min</td>
                            <td id="faseSubidaConsumo">0 gal</td>
                        </tr>
                        <tr>
                            <td><span class="badge-fase cruzeiro">Cruzeiro (Cruise)</span></td>
                            <td>TOC ➔ TOD</td>
                            <td id="faseCruzeiroAlt">0 ft (Nivelado)</td>
                            <td id="faseCruzeiroDist">0 NM</td>
                            <td id="faseCruzeiroTempo">--:--</td>
                            <td id="faseCruzeiroVel">0 kt</td>
                            <td id="faseCruzeiroTaxa">0 ft/min</td>
                            <td id="faseCruzeiroConsumo">0 gal</td>
                        </tr>
                        <tr>
                            <td><span class="badge-fase descida">Descida (Descent)</span></td>
                            <td>TOD ➔ Destino</td>
                            <td id="faseDescidaAlt">0 ft ➔ 0 ft</td>
                            <td id="faseDescidaDist">0 NM</td>
                            <td id="faseDescidaTempo">--:--</td>
                            <td id="faseDescidaVel">0 kt</td>
                            <td id="faseDescidaTaxa">-0 ft/min</td>
                            <td id="faseDescidaConsumo">0 gal</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="linha-total-fases">
                            <td colspan="3"><strong>Total da Operação</strong></td>
                            <td id="totalFasesDist"><strong>0 NM</strong></td>
                            <td id="totalFasesTempo"><strong>--:--</strong></td>
                            <td colspan="2">-</td>
                            <td id="totalFasesConsumo"><strong>0 gal</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="acoes-registrar-voo">
            <button type="button" class="btn-primario" onclick="registrarVooDoPlanejamento()">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                <span>Registrar voo</span>
            </button>
        </div>
    </main>

    <!-- Modal Salvar Planejamento -->
    <div id="modalSalvarPlano" class="modal-backdrop oculto" aria-hidden="true">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-titulo-wrapper">
                    <div class="modal-icone primary">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    </div>
                    <div>
                        <h3>Salvar Planejamento de Voo</h3>
                        <p>Dê um nome para identificar este voo na sua lista de planos salvos</p>
                    </div>
                </div>
                <button type="button" class="btn-fechar-modal" onclick="fecharModalSalvar()" aria-label="Fechar">&times;</button>
            </div>
            <div class="modal-corpo">
                <label class="label-modal">
                    <span>Nome do Planejamento *</span>
                    <input type="text" id="inputNomePlano" placeholder="Ex: SBMT ➔ SBRJ (Ponte Aérea)" required maxlength="150" autocomplete="off">
                </label>
                <div class="resumo-salvar-plano">
                    <div class="resumo-item">
                        <span class="resumo-rotulo">Aeronave</span>
                        <span id="resumoSalvarAeronave" class="resumo-valor">-</span>
                    </div>
                    <div class="resumo-item">
                        <span class="resumo-rotulo">Rota</span>
                        <span id="resumoSalvarRota" class="resumo-valor">-</span>
                    </div>
                    <div class="resumo-item">
                        <span class="resumo-rotulo">Distância</span>
                        <span id="resumoSalvarDist" class="resumo-valor">0 NM</span>
                    </div>
                    <div class="resumo-item">
                        <span class="resumo-rotulo">Altitude</span>
                        <span id="resumoSalvarAlt" class="resumo-valor">0 ft</span>
                    </div>
                    <div class="resumo-item">
                        <span class="resumo-rotulo">Tempo Est.</span>
                        <span id="resumoSalvarTempo" class="resumo-valor">--:--</span>
                    </div>
                    <div class="resumo-item">
                        <span class="resumo-rotulo">Consumo Est.</span>
                        <span id="resumoSalvarConsumo" class="resumo-valor">0 gal</span>
                    </div>
                </div>
                <div id="msgAlertaSalvar" class="alerta-planejamento perigo oculto"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secundario" onclick="fecharModalSalvar()">Cancelar</button>
                <button type="button" id="btnConfirmarSalvar" class="btn-primario" onclick="confirmarSalvarPlano()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    <span>Salvar no Banco</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Planos Salvos -->
    <div id="modalPlanosSalvos" class="modal-backdrop oculto" aria-hidden="true">
        <div class="modal-card modal-card-largo">
            <div class="modal-header">
                <div class="modal-titulo-wrapper">
                    <div class="modal-icone accent">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    </div>
                    <div>
                        <h3>Planos de Voo Salvos</h3>
                        <p>Selecione um plano salvo para carregar na tela ou gerenciar</p>
                    </div>
                </div>
                <button type="button" class="btn-fechar-modal" onclick="fecharModalPlanosSalvos()" aria-label="Fechar">&times;</button>
            </div>
            <div class="modal-corpo">
                <div id="listaPlanosSalvosContainer" class="lista-planos-salvos">
                    <!-- Cards carregados via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secundario" onclick="fecharModalPlanosSalvos()">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        // Dados das Aeronaves passados do PHP
        const AERONAVES = <?= json_encode($listaAeronaves, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const PRECO_COMBUSTIVEL = {
            jeta: <?= json_encode($valorJeta) ?>,
            avgas: <?= json_encode($valorAvgas) ?>,
            litrosPorGalao: <?= json_encode($litrosPorGalao) ?>
        };

        // Elementos DOM
        const campoAeronave = document.getElementById("campoAeronave");
        const campoPartida = document.getElementById("campoPartida");
        const campoDestino = document.getElementById("campoDestino");
        const campoDistancia = document.getElementById("campoDistancia");
        const campoAltitudeCruzeiro = document.getElementById("campoAltitudeCruzeiro");
        const campoTanqueDecolagem = document.getElementById("campoTanqueDecolagem");
        const campoVelocidade = document.getElementById("campoVelocidade");
        const campoVelocidadeSubida = document.getElementById("campoVelocidadeSubida");
        const campoRazaoSubida = document.getElementById("campoRazaoSubida");
        const campoRazaoDescida = document.getElementById("campoRazaoDescida");
        const campoConsumoGph = document.getElementById("campoConsumoGph");

        const previewPartida = document.getElementById("previewPartida");
        const previewPartidaNome = document.getElementById("previewPartidaNome");
        const previewPartidaAlt = document.getElementById("previewPartidaAlt");
        const previewDestino = document.getElementById("previewDestino");
        const previewDestinoNome = document.getElementById("previewDestinoNome");
        const previewDestinoAlt = document.getElementById("previewDestinoAlt");
        const badgeAltOrigem = document.getElementById("badgeAltOrigem");
        const badgeAltDestino = document.getElementById("badgeAltDestino");

        // Bússola e Seta da Rota
        const badgeDirecaoBussola = document.getElementById("badgeDirecaoBussola");
        const txtRumoGraus = document.getElementById("txtRumoGraus");
        const txtRumoDirecao = document.getElementById("txtRumoDirecao");
        const indicadorSetaRota = document.getElementById("indicadorSetaRota");

        // Mini Card de Aeronave Selecionada
        const cardAeroInfo = document.getElementById("cardAeroSelecionadaInfo");
        const txtAeroMiniNome = document.getElementById("txtAeroMiniNome");
        const txtAeroMiniComb = document.getElementById("txtAeroMiniComb");
        const txtAeroMiniVel = document.getElementById("txtAeroMiniVel");
        const txtAeroMiniGph = document.getElementById("txtAeroMiniGph");
        const txtAeroMiniTanque = document.getElementById("txtAeroMiniTanque");
        const txtAeroMiniTeto = document.getElementById("txtAeroMiniTeto");

        // Sliders e Controles de Autonomia / Carga / Pesos (Coluna 2)
        const sliderTanque = document.getElementById("sliderTanque");
        const txtTanqueResumo = document.getElementById("txtTanqueResumo");
        const txtTanqueCapacidadeMax = document.getElementById("txtTanqueCapacidadeMax");
        const sliderPassageiros = document.getElementById("sliderPassageiros");
        const campoQtdPassageiros = document.getElementById("campoQtdPassageiros");
        const txtPassageirosQtdBadge = document.getElementById("txtPassageirosQtdBadge");
        const txtAssentosMax = document.getElementById("txtAssentosMax");
        const txtPesoTotalOcupantes = document.getElementById("txtPesoTotalOcupantes");
        const badgeCombustivelTipo = document.getElementById("badgeCombustivelTipo");
        const txtAutonomiaTempo = document.getElementById("txtAutonomiaTempo");
        const txtAutonomiaNm = document.getElementById("txtAutonomiaNm");
        const txtAutonomiaSobraTempo = document.getElementById("txtAutonomiaSobraTempo");
        const txtAutonomiaSobraNm = document.getElementById("txtAutonomiaSobraNm");
        const txtPesoVazio = document.getElementById("txtPesoVazio");
        const txtZfw = document.getElementById("txtZfw");
        const txtPesoCombustivel = document.getElementById("txtPesoCombustivel");
        const txtTow = document.getElementById("txtTow");
        const txtLw = document.getElementById("txtLw");
        const statusMtow = document.getElementById("statusMtow");
        const txtStatusMtow = document.getElementById("txtStatusMtow");
        const txtStatusMtowSub = document.getElementById("txtStatusMtowSub");
        const infoBarraCargaUtil = document.getElementById("infoBarraCargaUtil");
        const fillCombustivelCargaUtil = document.getElementById("fillCombustivelCargaUtil");
        const fillPassageirosCargaUtil = document.getElementById("fillPassageirosCargaUtil");
        const legendaPesoCombustivel = document.getElementById("legendaPesoCombustivel");
        const legendaPesoPassageiros = document.getElementById("legendaPesoPassageiros");
        const legendaPesoSobra = document.getElementById("legendaPesoSobra");

        // Lista de Comparativo da Frota (Tabela Linha a Linha)
        const tabelaFrotaCorpo = document.getElementById("tabelaFrotaCorpo");
        const subtituloFrotaComparativo = document.getElementById("subtituloFrotaComparativo");

        // Regra Semicircular de Níveis de Voo
        const containerRegraAltitudes = document.getElementById("containerRegraAltitudes");
        const badgeRegraRumo = document.getElementById("badgeRegraRumo");
        const badgeRegraTipo = document.getElementById("badgeRegraTipo");
        const txtRegraExplicacao = document.getElementById("txtRegraExplicacao");
        const listaAltitudesVfr = document.getElementById("listaAltitudesVfr");
        const listaAltitudesIfr = document.getElementById("listaAltitudesIfr");
        const tituloRegraVfr = document.getElementById("tituloRegraVfr");
        const tituloRegraIfr = document.getElementById("tituloRegraIfr");
        const avisoConflitoRegraAlt = document.getElementById("avisoConflitoRegraAlt");
        const txtAltDigitadaConflito = document.getElementById("txtAltDigitadaConflito");
        const txtRegraEsperadaConflito = document.getElementById("txtRegraEsperadaConflito");

        // Estado de ordenação da frota e rumo da rota
        let ordenacaoFrota = {
            coluna: "tempo",
            direcao: "asc"
        };
        let rumoAtualRota = null;

        // KPIs
        const kpiTempoTotal = document.getElementById("kpiTempoTotal");
        const kpiTempoTotalMin = document.getElementById("kpiTempoTotalMin");
        const kpiConsumoTotal = document.getElementById("kpiConsumoTotal");
        const kpiConsumoPct = document.getElementById("kpiConsumoPct");
        const kpiTocDist = document.getElementById("kpiTocDist");
        const kpiTocTempo = document.getElementById("kpiTocTempo");
        const kpiTodDist = document.getElementById("kpiTodDist");
        const kpiTodRem = document.getElementById("kpiTodRem");
        const kpiCruzeiroDist = document.getElementById("kpiCruzeiroDist");
        const kpiCruzeiroTempo = document.getElementById("kpiCruzeiroTempo");

        // Alertas
        const alertaTeto = document.getElementById("alertaTeto");
        const txtTetoMax = document.getElementById("txtTetoMax");
        const alertaRotaCurta = document.getElementById("alertaRotaCurta");
        const txtDistRota = document.getElementById("txtDistRota");
        const txtAltPretendida = document.getElementById("txtAltPretendida");
        const txtAltMaxAtingivel = document.getElementById("txtAltMaxAtingivel");
        const alertaCombustivel = document.getElementById("alertaCombustivel");

        // Tabela Fases
        const faseSubidaAlt = document.getElementById("faseSubidaAlt");
        const faseSubidaDist = document.getElementById("faseSubidaDist");
        const faseSubidaTempo = document.getElementById("faseSubidaTempo");
        const faseSubidaVel = document.getElementById("faseSubidaVel");
        const faseSubidaTaxa = document.getElementById("faseSubidaTaxa");
        const faseSubidaConsumo = document.getElementById("faseSubidaConsumo");

        const faseCruzeiroAlt = document.getElementById("faseCruzeiroAlt");
        const faseCruzeiroDist = document.getElementById("faseCruzeiroDist");
        const faseCruzeiroTempo = document.getElementById("faseCruzeiroTempo");
        const faseCruzeiroVel = document.getElementById("faseCruzeiroVel");
        const faseCruzeiroTaxa = document.getElementById("faseCruzeiroTaxa");
        const faseCruzeiroConsumo = document.getElementById("faseCruzeiroConsumo");

        const faseDescidaAlt = document.getElementById("faseDescidaAlt");
        const faseDescidaDist = document.getElementById("faseDescidaDist");
        const faseDescidaTempo = document.getElementById("faseDescidaTempo");
        const faseDescidaVel = document.getElementById("faseDescidaVel");
        const faseDescidaTaxa = document.getElementById("faseDescidaTaxa");
        const faseDescidaConsumo = document.getElementById("faseDescidaConsumo");

        const totalFasesDist = document.getElementById("totalFasesDist");
        const totalFasesTempo = document.getElementById("totalFasesTempo");
        const totalFasesConsumo = document.getElementById("totalFasesConsumo");

        // Cache de aeródromos
        const cacheAerodromos = {};
        let aerodromoOrigemDados = null;
        let aerodromoDestinoDados = null;
        let aeronaveSelecionada = null;

        // Gráfico Chart.js
        let chartPerfil = null;
        let ultimoCalculoPlano = null;

        function registrarVooDoPlanejamento() {
            const origem = campoPartida.value.trim().toUpperCase();
            const destino = campoDestino.value.trim().toUpperCase();
            if (!origem || !destino || !ultimoCalculoPlano) {
                alert("Preencha origem, destino, distância e altitude para gerar o registro do voo.");
                return;
            }

            const c = ultimoCalculoPlano;
            const aeronave = aeronaveSelecionada
                ? `${aeronaveSelecionada.fabricante || ""} ${aeronaveSelecionada.modelo || ""}`.trim()
                : campoAeronave.value.trim();

            const params = new URLSearchParams({
                aeronave,
                origem,
                destino,
                distancia: String(parseFloat(campoDistancia.value) || 0),
                tanque_decolagem: c.tanqueDecolagem.toFixed(1),
                tanque_pouso: Math.max(0, c.tanquePouso).toFixed(1),
                tempo_voo: formatarMinutosParaHhMm(c.tempoTotalMin),
                altitude: String(parseInt(campoAltitudeCruzeiro.value, 10) || 0)
            });
            window.location.href = `index.php?${params.toString()}#formVoo`;
        }

        function escapeHtml(str) {
            if (!str) return "";
            return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        }

        function formatarNumero(num, decimais = 0) {
            const val = typeof num === "number" ? num : (parseFloat(num) || 0);
            return val.toLocaleString("pt-BR", { minimumFractionDigits: decimais, maximumFractionDigits: decimais });
        }

        function formatarMilhar(val) {
            if (val === null || val === undefined || val === "") return "";
            const limpo = String(val).replace(/\D/g, "");
            if (!limpo) return "";
            return parseInt(limpo, 10).toLocaleString("pt-BR");
        }

        function parseMilhar(val) {
            if (val === null || val === undefined || val === "") return 0;
            const limpo = String(val).replace(/\D/g, "");
            return parseInt(limpo, 10) || 0;
        }

        function formatarMinutosParaHhMm(totalMinutos) {
            if (isNaN(totalMinutos) || totalMinutos < 0) return "--:--";
            const h = Math.floor(totalMinutos / 60);
            const m = Math.round(totalMinutos % 60);
            return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
        }

        function atualizarBussolaRota(rumoGraus, rumoFormatado, direcaoSigla, direcaoNome) {
            if (!badgeDirecaoBussola) return;
            if (rumoGraus !== undefined && rumoGraus !== null) {
                badgeDirecaoBussola.classList.remove("oculto");
                txtRumoGraus.textContent = rumoFormatado || `${String(rumoGraus).padStart(3, '0')}°`;
                txtRumoDirecao.textContent = `${direcaoSigla} (${direcaoNome})`;
                if (indicadorSetaRota) {
                    indicadorSetaRota.style.transform = `rotate(${rumoGraus}deg)`;
                }
                atualizarRegraAltitudes(rumoGraus, rumoFormatado, direcaoSigla, direcaoNome);
            } else {
                badgeDirecaoBussola.classList.add("oculto");
                if (indicadorSetaRota) {
                    indicadorSetaRota.style.transform = "none";
                }
                atualizarRegraAltitudes(null);
            }
        }

        function calcularRumoClientSide(lat1, lon1, lat2, lon2) {
            const toRad = deg => (deg * Math.PI) / 180;
            const toDeg = rad => (rad * 180) / Math.PI;

            const lat1Rad = toRad(lat1);
            const lat2Rad = toRad(lat2);
            const deltaLon = toRad(lon2 - lon1);

            const y = Math.sin(deltaLon) * Math.cos(lat2Rad);
            const x = Math.cos(lat1Rad) * Math.sin(lat2Rad) - Math.sin(lat1Rad) * Math.cos(lat2Rad) * Math.cos(deltaLon);

            const graus = ((toDeg(Math.atan2(y, x)) % 360) + 360) % 360;

            const pontos = [
                ["N", "Norte", 348.75, 360.0],
                ["N", "Norte", 0.0, 11.25],
                ["NNE", "Nor-nordeste", 11.25, 33.75],
                ["NE", "Nordeste", 33.75, 56.25],
                ["ENE", "Leste-nordeste", 56.25, 78.75],
                ["L", "Leste", 78.75, 101.25],
                ["ESE", "Leste-sudeste", 101.25, 123.75],
                ["SE", "Sudeste", 123.75, 146.25],
                ["SSE", "Sul-sudeste", 146.25, 168.75],
                ["S", "Sul", 168.75, 191.25],
                ["SSO", "Sul-sudoeste", 191.25, 213.75],
                ["SO", "Sudoeste", 213.75, 236.25],
                ["OSO", "Oeste-sudoeste", 236.25, 258.75],
                ["O", "Oeste", 258.75, 281.25],
                ["ONO", "Oeste-noroeste", 281.25, 303.75],
                ["NO", "Noroeste", 303.75, 326.25],
                ["NNO", "Nor-noroeste", 326.25, 348.75]
            ];

            let sigla = "N";
            let nome = "Norte";
            for (const [s, n, min, max] of pontos) {
                if (graus >= min && graus < max) {
                    sigla = s;
                    nome = n;
                    break;
                }
            }

            const grausInt = Math.round(graus);
            return {
                rumo_graus: grausInt,
                rumo_formatado: `${String(grausInt).padStart(3, "0")}°`,
                direcao_sigla: sigla,
                direcao_nome: nome
            };
        }

        // =========================================================================
        // Ordenação e Renderização da Frota na Rota (Linha a Linha)
        // =========================================================================
        function ordenarTabelaFrota(coluna) {
            if (ordenacaoFrota.coluna === coluna) {
                ordenacaoFrota.direcao = ordenacaoFrota.direcao === "asc" ? "desc" : "asc";
            } else {
                ordenacaoFrota.coluna = coluna;
                ordenacaoFrota.direcao = "asc";
            }
            renderizarComparativoFrota();
        }

        function selecionarAeronaveDaFrota(modelo) {
            campoAeronave.value = modelo;
            atualizarDadosAeronave();
        }

        function renderizarComparativoFrota() {
            if (!tabelaFrotaCorpo) return;

            const distancia = parseFloat(campoDistancia.value) || 0;
            const modeloAtual = campoAeronave.value.trim().toLowerCase();
            const altOrigem = aerodromoOrigemDados ? aerodromoOrigemDados.altitude_ft : 0;
            const altDestino = aerodromoDestinoDados ? aerodromoDestinoDados.altitude_ft : 0;

            if (subtituloFrotaComparativo) {
                if (distancia <= 0) {
                    subtituloFrotaComparativo.textContent = "Preencha a Origem e o Destino para calcular tempo e custo de cada aeronave";
                } else {
                    subtituloFrotaComparativo.innerHTML = `Estimativas para a rota de <strong>${formatarNumero(distancia)} NM</strong>`;
                }
            }

            // 1. Processar dados de cada aeronave
            const itensCalculados = AERONAVES.map(aero => {
                const isAtiva = (aero.modelo.toLowerCase() === modeloAtual || 
                                 (aero.fabricante + " " + aero.modelo).toLowerCase() === modeloAtual);

                let tempoTexto = "--:--";
                let tempoSubTexto = "informe rota";
                let tTotalMin = 999999;
                let totalGal = 0;
                let custoTotal = null;
                let temCalculo = false;

                const velCruzeiro = parseFloat(aero.velocidade_cruzeiro) || 0;
                const consumoGph = parseFloat(aero.consumo_gph) || 0;
                const velSubida = parseFloat(aero.velocidade_subida) > 0 ? parseFloat(aero.velocidade_subida) : (velCruzeiro * 0.85);
                const razaoSubida = parseFloat(aero.razao_subida) > 0 ? parseFloat(aero.razao_subida) : 700;
                const razaoDescida = velCruzeiro * 5;
                const altCruzeiro = parseFloat(aero.altitude_cruzeiro_ideal) > 0 ? parseFloat(aero.altitude_cruzeiro_ideal) : 8500;

                if (distancia > 0 && velCruzeiro > 0) {
                    // Subida
                    const deltaSub = Math.max(0, altCruzeiro - altOrigem);
                    const tSubMin = razaoSubida > 0 ? (deltaSub / razaoSubida) : 0;
                    let dSub = (tSubMin / 60) * velSubida;

                    // Descida
                    const deltaDesc = Math.max(0, altCruzeiro - altDestino);
                    const tDescMin = razaoDescida > 0 ? (deltaDesc / razaoDescida) : 0;
                    let dDesc = (tDescMin / 60) * velCruzeiro;

                    let dCruz = distancia - dSub - dDesc;
                    let tCruzMin = 0;

                    if (dCruz < 0) {
                        // Perfil triangular
                        const fatorSub = velSubida / (60 * razaoSubida);
                        const fatorDesc = velCruzeiro / (60 * razaoDescida);
                        const deltaAltMax = Math.max(0, (distancia + (altOrigem * fatorSub) + (altDestino * fatorDesc)) / (fatorSub + fatorDesc) - Math.min(altOrigem, altDestino));
                        const altReal = Math.round(Math.min(altOrigem, altDestino) + deltaAltMax);

                        const tSubReal = Math.max(0, altReal - altOrigem) / razaoSubida;
                        dSub = (tSubReal / 60) * velSubida;
                        dDesc = Math.max(0, distancia - dSub);
                        dCruz = 0;
                        tCruzMin = 0;
                    } else {
                        tCruzMin = (dCruz / velCruzeiro) * 60;
                    }

                    const tSubFinal = velSubida > 0 ? (dSub / velSubida) * 60 : 0;
                    const tDescFinal = velCruzeiro > 0 ? (dDesc / velCruzeiro) * 60 : 0;
                    tTotalMin = tSubFinal + tCruzMin + tDescFinal;

                    // Consumo
                    const galSub = (tSubFinal / 60) * (consumoGph * 1.2);
                    const galCruz = (tCruzMin / 60) * consumoGph;
                    const galDesc = (tDescFinal / 60) * (consumoGph * 0.8);
                    totalGal = galSub + galCruz + galDesc;

                    // Preço do combustível
                    const tipoComb = (aero.tipo_combustivel || "").toUpperCase();
                    let precoLitro = null;
                    if (tipoComb.includes("JET")) {
                        precoLitro = PRECO_COMBUSTIVEL.jeta;
                    } else if (tipoComb.includes("AVGAS") || tipoComb.includes("GASOLINA")) {
                        precoLitro = PRECO_COMBUSTIVEL.avgas;
                    } else {
                        precoLitro = velCruzeiro > 250 ? PRECO_COMBUSTIVEL.jeta : PRECO_COMBUSTIVEL.avgas;
                    }

                    custoTotal = precoLitro ? (totalGal * PRECO_COMBUSTIVEL.litrosPorGalao * precoLitro) : null;
                    tempoTexto = formatarMinutosParaHhMm(tTotalMin);
                    tempoSubTexto = `${Math.round(tTotalMin)} min`;
                    temCalculo = true;
                }

                return {
                    aero,
                    isAtiva,
                    temCalculo,
                    modeloNome: `${aero.fabricante || ""} ${aero.modelo || ""}`.trim(),
                    tTotalMin,
                    tempoTexto,
                    tempoSubTexto,
                    totalGal,
                    consumoTexto: temCalculo ? `${formatarNumero(totalGal, 1)} gal` : "-",
                    custoTotal: custoTotal !== null ? custoTotal : 99999999,
                    custoTexto: custoTotal !== null ? `R$ ${formatarNumero(Math.round(custoTotal), 0)}` : "-",
                    velCruzeiro
                };
            });

            // 2. Ordenar itens
            const col = ordenacaoFrota.coluna;
            const dir = ordenacaoFrota.direcao === "asc" ? 1 : -1;

            itensCalculados.sort((a, b) => {
                if (col === "modelo") {
                    return dir * a.modeloNome.localeCompare(b.modeloNome);
                }
                if (col === "tempo") {
                    if (a.temCalculo !== b.temCalculo) return a.temCalculo ? -1 : 1;
                    return dir * (a.tTotalMin - b.tTotalMin);
                }
                if (col === "custo") {
                    if (a.temCalculo !== b.temCalculo) return a.temCalculo ? -1 : 1;
                    return dir * (a.custoTotal - b.custoTotal);
                }
                if (col === "consumo") {
                    if (a.temCalculo !== b.temCalculo) return a.temCalculo ? -1 : 1;
                    return dir * (a.totalGal - b.totalGal);
                }
                if (col === "velocidade") {
                    return dir * (a.velCruzeiro - b.velCruzeiro);
                }
                return 0;
            });

            // 3. Atualizar setas indicadoras nos cabeçalhos
            const cols = ["modelo", "tempo", "custo"];
            cols.forEach(c => {
                const el = document.getElementById(`ord_${c}`);
                if (el) {
                    if (c === ordenacaoFrota.coluna) {
                        el.textContent = ordenacaoFrota.direcao === "asc" ? "▲" : "▼";
                        el.classList.add("ativo");
                    } else {
                        el.textContent = "↕";
                        el.classList.remove("ativo");
                    }
                }
            });

            // 4. Renderizar linhas na tabela
            tabelaFrotaCorpo.innerHTML = "";
            itensCalculados.forEach(item => {
                const aero = item.aero;
                const tr = document.createElement("tr");
                tr.className = `linha-frota-item ${item.isAtiva ? "linha-aero-ativa" : ""}`;
                tr.title = `Clique para selecionar ${aero.fabricante} ${aero.modelo}`;
                tr.onclick = () => {
                    campoAeronave.value = aero.modelo;
                    atualizarDadosAeronave();
                };

                tr.innerHTML = `
                    <td class="td-frota-aero">
                        <div class="frota-aero-info-bloco">
                            <span class="frota-aero-fab">${escapeHtml(aero.fabricante || "")}</span>
                            <strong class="frota-aero-mod">${escapeHtml(aero.modelo || "")}</strong>
                        </div>
                    </td>
                    <td class="td-frota-tempo">
                        <strong class="frota-valor-destaque ${item.temCalculo ? 'tempo' : ''}">${item.tempoTexto}</strong>
                        <small class="frota-sub-valor">${item.tempoSubTexto}</small>
                    </td>
                    <td class="td-frota-custo">
                        <strong class="frota-valor-destaque ${item.temCalculo ? 'custo' : ''}">${item.custoTexto}</strong>
                    </td>
                `;
                tabelaFrotaCorpo.appendChild(tr);
            });
        }

        // =========================================================================
        // Motor de Autonomia, Carga e Peso & Balanceamento (de autonomia.php)
        // =========================================================================
        function atualizarAutonomiaEPesos(consumoRotaGal = null) {
            const KG_PARA_LB = 2.20462;
            const LITROS_POR_GALAO = 3.785411784;

            if (!aeronaveSelecionada) {
                if (txtAutonomiaTempo) txtAutonomiaTempo.textContent = "--:--";
                if (txtAutonomiaNm) txtAutonomiaNm.textContent = "0 NM de alcance";
                if (txtAutonomiaSobraTempo) txtAutonomiaSobraTempo.textContent = "--:--";
                if (txtAutonomiaSobraNm) txtAutonomiaSobraNm.textContent = "-- NM restante";
                if (txtPesoVazio) txtPesoVazio.textContent = "-";
                if (txtZfw) txtZfw.textContent = "-";
                if (txtPesoCombustivel) txtPesoCombustivel.textContent = "-";
                if (txtTow) txtTow.textContent = "-";
                if (txtLw) txtLw.textContent = "-";
                if (statusMtow) statusMtow.className = "badge-status-mtow ok";
                if (txtStatusMtow) txtStatusMtow.textContent = "Selecione uma aeronave para calcular";
                if (txtStatusMtowSub) {
                    txtStatusMtowSub.textContent = "";
                    txtStatusMtowSub.style.display = "none";
                }
                if (infoBarraCargaUtil) infoBarraCargaUtil.textContent = "0 kg / 0 kg (0%)";
                if (fillCombustivelCargaUtil) fillCombustivelCargaUtil.style.width = "0%";
                if (fillPassageirosCargaUtil) fillPassageirosCargaUtil.style.width = "0%";
                if (txtTanqueResumo) txtTanqueResumo.textContent = "0 gal (0 L • 0%)";
                if (txtTanqueCapacidadeMax) txtTanqueCapacidadeMax.textContent = "Máx: 0 gal";
                if (txtAssentosMax) txtAssentosMax.textContent = "Máx: 0 assentos";
                return;
            }

            // 1. Limites do Tanque de Combustível
            const capTanque = Math.round(parseFloat(aeronaveSelecionada.capacidade_tanque) || 0);
            if (sliderTanque) sliderTanque.max = capTanque > 0 ? capTanque : 100;
            if (campoTanqueDecolagem) campoTanqueDecolagem.max = capTanque > 0 ? capTanque : 100;
            if (txtTanqueCapacidadeMax) txtTanqueCapacidadeMax.textContent = `Máx: ${formatarNumero(capTanque)} gal`;

            let galTanque = parseFloat(campoTanqueDecolagem ? campoTanqueDecolagem.value : 0);
            if (isNaN(galTanque) || galTanque < 0) galTanque = 0;
            if (capTanque > 0 && galTanque > capTanque) {
                galTanque = capTanque;
                if (campoTanqueDecolagem) campoTanqueDecolagem.value = capTanque;
            }
            if (sliderTanque && parseFloat(sliderTanque.value) !== galTanque) {
                sliderTanque.value = galTanque;
            }

            const litrosTanque = galTanque * LITROS_POR_GALAO;
            const pctTanque = capTanque > 0 ? (galTanque / capTanque) * 100 : 0;
            if (txtTanqueResumo) {
                txtTanqueResumo.textContent = `${formatarNumero(galTanque, 0)} gal (${formatarNumero(litrosTanque, 0)} L • ${Math.round(pctTanque)}%)`;
            }

            // 2. Limites e Ocupantes (Mínimo 1 piloto)
            const maxAssentos = Math.max(1, parseInt(aeronaveSelecionada.assentos) || 4);
            if (sliderPassageiros) {
                sliderPassageiros.min = 1;
                sliderPassageiros.max = maxAssentos;
            }
            if (campoQtdPassageiros) {
                campoQtdPassageiros.min = 1;
                campoQtdPassageiros.max = maxAssentos;
            }
            if (txtAssentosMax) txtAssentosMax.textContent = `Máx: ${maxAssentos} assentos`;

            let qtdOcupantes = parseInt(campoQtdPassageiros ? campoQtdPassageiros.value : (sliderPassageiros ? sliderPassageiros.value : 1));
            if (isNaN(qtdOcupantes) || qtdOcupantes < 1) qtdOcupantes = 1;
            if (qtdOcupantes > maxAssentos) {
                qtdOcupantes = maxAssentos;
                if (campoQtdPassageiros) campoQtdPassageiros.value = maxAssentos;
            }
            if (sliderPassageiros && parseInt(sliderPassageiros.value) !== qtdOcupantes) {
                sliderPassageiros.value = qtdOcupantes;
            }
            if (campoQtdPassageiros && parseInt(campoQtdPassageiros.value) !== qtdOcupantes) {
                campoQtdPassageiros.value = qtdOcupantes;
            }
            if (txtPassageirosQtdBadge) {
                txtPassageirosQtdBadge.textContent = qtdOcupantes === 1 ? '1 ocupante (piloto)' : `${qtdOcupantes} ocupantes`;
            }

            // Peso médio padrão aeronáutico com bagagem: 95 kg (80 kg pax + 15 kg bagagem)
            const pesoMedioKg = 95;
            const pesoOcupantesKg = qtdOcupantes * pesoMedioKg;
            const pesoOcupantesLb = Math.round(pesoOcupantesKg * KG_PARA_LB);
            if (txtPesoTotalOcupantes) {
                txtPesoTotalOcupantes.textContent = `${formatarNumero(pesoOcupantesKg, 0)} kg (${formatarNumero(pesoOcupantesLb, 0)} lb)`;
            }

            // 3. Tipo e Densidade de Combustível
            const tipoComb = (aeronaveSelecionada.tipo_combustivel || "Avgas").toUpperCase();
            const isJet = tipoComb.includes("JET");
            const densidade = isJet ? 6.7 : 6.0; // lb/gal
            if (badgeCombustivelTipo) {
                badgeCombustivelTipo.textContent = isJet ? "Jet-A" : "Avgas";
                badgeCombustivelTipo.className = `badge-mini-comb ${isJet ? 'jeta' : 'avgas'}`;
            }

            const pesoCombustivelLb = galTanque * densidade;
            const pesoCombustivelKg = pesoCombustivelLb / KG_PARA_LB;

            // 4. Pesos (Vazio, ZFW, TOW, MTOW, LW)
            const pesoVazioLb = parseFloat(aeronaveSelecionada.peso_vazio) || 0;
            const pesoVazioKg = pesoVazioLb / KG_PARA_LB;

            const zfwLb = pesoVazioLb + pesoOcupantesLb;
            const zfwKg = zfwLb / KG_PARA_LB;

            const towLb = zfwLb + pesoCombustivelLb;
            const towKg = towLb / KG_PARA_LB;

            const mtowLb = parseFloat(aeronaveSelecionada.peso_maximo_decolagem) || (pesoVazioLb + (parseFloat(aeronaveSelecionada.carga_util) || 0));
            const mtowKg = mtowLb / KG_PARA_LB;

            const cargaUtilTotalLb = parseFloat(aeronaveSelecionada.carga_util) || Math.max(0, mtowLb - pesoVazioLb);
            const cargaUtilTotalKg = cargaUtilTotalLb / KG_PARA_LB;

            // Consumo da rota para estimar peso de pouso
            let consumoVooGal = consumoRotaGal;
            if (consumoVooGal === null) {
                consumoVooGal = parseFloat(kpiConsumoTotal ? kpiConsumoTotal.textContent : 0) || 0;
            }
            const pesoCombGastoLb = consumoVooGal * densidade;
            const lwLb = Math.max(pesoVazioLb + pesoOcupantesLb, towLb - pesoCombGastoLb);
            const lwKg = lwLb / KG_PARA_LB;

            if (txtPesoVazio) txtPesoVazio.textContent = `${formatarNumero(Math.round(pesoVazioKg), 0)} kg (${formatarNumero(Math.round(pesoVazioLb), 0)} lb)`;
            if (txtZfw) txtZfw.textContent = `${formatarNumero(Math.round(zfwKg), 0)} kg (${formatarNumero(Math.round(zfwLb), 0)} lb)`;
            if (txtPesoCombustivel) txtPesoCombustivel.textContent = `${formatarNumero(Math.round(pesoCombustivelKg), 0)} kg (${formatarNumero(Math.round(pesoCombustivelLb), 0)} lb)`;
            if (txtTow) txtTow.textContent = `${formatarNumero(Math.round(towKg), 0)} kg (${formatarNumero(Math.round(towLb), 0)} lb)`;
            if (txtLw) txtLw.textContent = `${formatarNumero(Math.round(lwKg), 0)} kg (${formatarNumero(Math.round(lwLb), 0)} lb)`;

            // Alerta de MTOW / Sobrepeso
            if (mtowLb > 0) {
                const pctTow = Math.round((towLb / mtowLb) * 100);

                if (towLb > mtowLb + 2) {
                    const excessoLb = towLb - mtowLb;
                    const excessoKg = excessoLb / KG_PARA_LB;
                    const pctExcesso = Math.round((excessoLb / mtowLb) * 100);
                    if (statusMtow) statusMtow.className = "badge-status-mtow perigo";
                    if (txtStatusMtow) txtStatusMtow.textContent = `⚠️ SOBREPESO: +${formatarNumero(Math.round(excessoKg), 0)} kg acima do MTOW (${formatarNumero(Math.round(mtowKg), 0)} kg máx)`;
                    if (txtStatusMtowSub) {
                        txtStatusMtowSub.style.display = "block";
                        txtStatusMtowSub.textContent = `${pctTow}% do total (+${pctExcesso}% acima do limite)`;
                    }
                } else {
                    const margemLb = Math.max(0, mtowLb - towLb);
                    const margemKg = margemLb / KG_PARA_LB;
                    const pctMargem = Math.max(0, Math.round((margemLb / mtowLb) * 100));
                    if (statusMtow) statusMtow.className = "badge-status-mtow ok";
                    if (txtStatusMtow) txtStatusMtow.textContent = `✅ Margem: ${formatarNumero(Math.round(margemKg), 0)} kg disponíveis (MTOW: ${formatarNumero(Math.round(mtowKg), 0)} kg)`;
                    if (txtStatusMtowSub) {
                        txtStatusMtowSub.style.display = "block";
                        txtStatusMtowSub.textContent = `${pctTow}% do total (MTOW) • ${pctMargem}% de margem livre`;
                    }
                }
            } else {
                if (txtStatusMtowSub) {
                    txtStatusMtowSub.textContent = "";
                    txtStatusMtowSub.style.display = "none";
                }
            }

            // 5. Barra de Carga Útil
            const pesoUtilizadoLb = pesoCombustivelLb + pesoOcupantesLb;
            const pesoUtilizadoKg = pesoUtilizadoLb / KG_PARA_LB;
            const pctComb = cargaUtilTotalLb > 0 ? Math.min(100, (pesoCombustivelLb / cargaUtilTotalLb) * 100) : 0;
            const pctPax = cargaUtilTotalLb > 0 ? Math.min(100 - pctComb, (pesoOcupantesLb / cargaUtilTotalLb) * 100) : 0;
            const pctTotal = cargaUtilTotalLb > 0 ? (pesoUtilizadoLb / cargaUtilTotalLb) * 100 : 0;

            if (fillCombustivelCargaUtil) fillCombustivelCargaUtil.style.width = pctComb + "%";
            if (fillPassageirosCargaUtil) fillPassageirosCargaUtil.style.width = pctPax + "%";

            if (infoBarraCargaUtil) {
                infoBarraCargaUtil.textContent = `${formatarNumero(Math.round(pesoUtilizadoKg), 0)} kg / ${formatarNumero(Math.round(cargaUtilTotalKg), 0)} kg (${Math.round(pctTotal)}%)`;
            }
            if (legendaPesoCombustivel) {
                legendaPesoCombustivel.textContent = `${formatarNumero(Math.round(pesoCombustivelKg), 0)} kg (${formatarNumero(Math.round(pesoCombustivelLb), 0)} lb)`;
            }
            if (legendaPesoPassageiros) {
                legendaPesoPassageiros.textContent = `${formatarNumero(Math.round(pesoOcupantesKg), 0)} kg (${formatarNumero(Math.round(pesoOcupantesLb), 0)} lb)`;
            }
            const sobraTotalLb = Math.max(0, cargaUtilTotalLb - pesoUtilizadoLb);
            const sobraTotalKg = sobraTotalLb / KG_PARA_LB;
            if (legendaPesoSobra) {
                legendaPesoSobra.textContent = `${formatarNumero(Math.round(sobraTotalKg), 0)} kg (${formatarNumero(Math.round(sobraTotalLb), 0)} lb)`;
            }

            // 6. Autonomia em Horas e Milhas
            const consumoGph = parseFloat(campoConsumoGph ? campoConsumoGph.value : 0) || parseFloat(aeronaveSelecionada.consumo_gph) || 12;
            const velCruzeiro = parseFloat(campoVelocidade ? campoVelocidade.value : 0) || parseFloat(aeronaveSelecionada.velocidade_cruzeiro) || 140;

            const horasAutonomia = consumoGph > 0 ? (galTanque / consumoGph) : 0;
            const minutosAutonomia = Math.round(horasAutonomia * 60);
            const distanciaAutonomiaNm = Math.round(horasAutonomia * velCruzeiro);

            if (txtAutonomiaTempo) txtAutonomiaTempo.textContent = formatarMinutosParaHhMm(minutosAutonomia);
            if (txtAutonomiaNm) txtAutonomiaNm.textContent = `${formatarNumero(distanciaAutonomiaNm, 0)} NM de alcance`;

            // Sobra pós-rota
            if (consumoVooGal > 0 && galTanque >= consumoVooGal) {
                const galSobra = galTanque - consumoVooGal;
                const horasSobra = consumoGph > 0 ? (galSobra / consumoGph) : 0;
                const minSobra = Math.round(horasSobra * 60);
                const nmSobra = Math.round(horasSobra * velCruzeiro);
                if (txtAutonomiaSobraTempo) txtAutonomiaSobraTempo.textContent = formatarMinutosParaHhMm(minSobra);
                if (txtAutonomiaSobraNm) txtAutonomiaSobraNm.textContent = `${formatarNumero(nmSobra, 0)} NM restante`;
            } else if (consumoVooGal > 0 && galTanque < consumoVooGal) {
                if (txtAutonomiaSobraTempo) txtAutonomiaSobraTempo.textContent = "INSUFICIENTE";
                if (txtAutonomiaSobraNm) txtAutonomiaSobraNm.textContent = "Combustível insuficiente";
            } else {
                if (txtAutonomiaSobraTempo) txtAutonomiaSobraTempo.textContent = "--:--";
                if (txtAutonomiaSobraNm) txtAutonomiaSobraNm.textContent = "-- NM restante";
            }
        }

        // =========================================================================
        // Regra Semicircular de Níveis de Voo (Altitudes Par e Ímpar por Rumo)
        // =========================================================================
        function atualizarRegraAltitudes(rumoGraus, rumoFormatado, direcaoSigla, direcaoNome) {
            if (!containerRegraAltitudes) return;
            if (rumoGraus === undefined || rumoGraus === null) {
                containerRegraAltitudes.classList.add("oculto");
                rumoAtualRota = null;
                return;
            }

            rumoAtualRota = rumoGraus;
            containerRegraAltitudes.classList.remove("oculto");

            // Rumo 000° a 179° = Ímpar (Leste) | Rumo 180° a 359° = Par (Oeste)
            const ehImpar = (rumoGraus >= 0 && rumoGraus <= 179);

            if (badgeRegraRumo) {
                badgeRegraRumo.textContent = `Rumo ${rumoFormatado || (String(rumoGraus).padStart(3, '0') + '°')} (${direcaoSigla || ''})`;
            }

            if (ehImpar) {
                if (badgeRegraTipo) {
                    badgeRegraTipo.textContent = "ALTITUDES ÍMPARES";
                    badgeRegraTipo.className = "badge-regra-tipo impar";
                }
                if (txtRegraExplicacao) {
                    txtRegraExplicacao.innerHTML = `Para voos com rumo entre <strong>000° e 179°</strong> (sentido Leste / Eastbound), a <strong>Regra Semicircular</strong> (ICA 100-12 / ICAO) determina o uso de altitudes <strong>ÍMPARES</strong>.`;
                }
                if (tituloRegraVfr) tituloRegraVfr.textContent = "VFR (Ímpar + 500 ft)";
                if (tituloRegraIfr) tituloRegraIfr.textContent = "IFR (Milhar Ímpar)";
            } else {
                if (badgeRegraTipo) {
                    badgeRegraTipo.textContent = "ALTITUDES PARES";
                    badgeRegraTipo.className = "badge-regra-tipo par";
                }
                if (txtRegraExplicacao) {
                    txtRegraExplicacao.innerHTML = `Para voos com rumo entre <strong>180° e 359°</strong> (sentido Oeste / Westbound), a <strong>Regra Semicircular</strong> (ICA 100-12 / ICAO) determina o uso de altitudes <strong>PARES</strong>.`;
                }
                if (tituloRegraVfr) tituloRegraVfr.textContent = "VFR (Par + 500 ft)";
                if (tituloRegraIfr) tituloRegraIfr.textContent = "IFR (Milhar Par)";
            }

            // VFR: Ímpar + 500 ft ou Par + 500 ft
            const altitudesVfr = ehImpar ? 
                [3500, 5500, 7500, 9500, 11500, 13500, 15500] : 
                [4500, 6500, 8500, 10500, 12500, 14500];

            // IFR: Milhares Ímpares ou Milhares Pares
            const altitudesIfr = ehImpar ?
                [3000, 5000, 7000, 9000, 11000, 13000, 15000, 17000, 19000, 21000, 23000, 25000, 27000, 29000] :
                [4000, 6000, 8000, 10000, 12000, 14000, 16000, 18000, 20000, 22000, 24000, 26000, 28000, 30000];

            renderizarPillsAltitudes(listaAltitudesVfr, altitudesVfr, "VFR");
            renderizarPillsAltitudes(listaAltitudesIfr, altitudesIfr, "IFR");

            verificarConflitoAltitudeRegra();
        }

        function renderizarPillsAltitudes(container, altitudes, regraTipo) {
            if (!container) return;
            container.innerHTML = "";
            const altAtual = parseInt(campoAltitudeCruzeiro.value) || 0;
            const tetoAero = (aeronaveSelecionada && aeronaveSelecionada.teto_operacional > 0) ? aeronaveSelecionada.teto_operacional : 999999;

            altitudes.forEach(alt => {
                const btn = document.createElement("button");
                btn.type = "button";
                const ehAtivo = (alt === altAtual);
                const acimaTeto = (alt > tetoAero);

                btn.className = `pill-alt ${ehAtivo ? 'ativo' : ''} ${acimaTeto ? 'acima-teto' : ''}`;
                const flNum = Math.round(alt / 100);
                const flStr = flNum >= 100 ? `FL${flNum}` : `${formatarNumero(alt)} ft`;

                btn.title = acimaTeto 
                    ? `${formatarNumero(alt)} ft - Excede o teto operacional da aeronave (${formatarNumero(tetoAero)} ft)` 
                    : `Clique para definir altitude de cruzeiro em ${formatarNumero(alt)} ft (${flStr})`;

                btn.innerHTML = `
                    <span class="pill-fl">${flStr}</span>
                    ${acimaTeto ? '<span class="pill-aviso-teto" title="Acima do teto operacional">⚠️</span>' : ''}
                `;

                btn.onclick = () => {
                    campoAltitudeCruzeiro.value = alt;
                    calcularPlanejamento();
                    verificarConflitoAltitudeRegra();
                    salvarRascunhoLocal();

                    // Atualiza classe ativa nas pílulas
                    document.querySelectorAll(".pill-alt").forEach(p => p.classList.remove("ativo"));
                    btn.classList.add("ativo");
                };

                container.appendChild(btn);
            });
        }

        function verificarConflitoAltitudeRegra() {
            if (!avisoConflitoRegraAlt) return;
            if (rumoAtualRota === null || rumoAtualRota === undefined) {
                avisoConflitoRegraAlt.classList.add("oculto");
                return;
            }

            const altDigitada = parseInt(campoAltitudeCruzeiro.value) || 0;
            if (altDigitada <= 0) {
                avisoConflitoRegraAlt.classList.add("oculto");
                return;
            }

            const ehImpar = (rumoAtualRota >= 0 && rumoAtualRota <= 179);
            const milhar = Math.floor(altDigitada / 1000);
            const milharEhImpar = (milhar % 2 !== 0);

            let emConflito = false;
            if (ehImpar && !milharEhImpar) {
                emConflito = true;
            } else if (!ehImpar && milharEhImpar) {
                emConflito = true;
            }

            if (emConflito) {
                avisoConflitoRegraAlt.classList.remove("oculto");
                if (txtAltDigitadaConflito) txtAltDigitadaConflito.textContent = formatarNumero(altDigitada);
                if (txtRegraEsperadaConflito) {
                    const sugestaoVfr = ehImpar ? ((milhar > 0 ? (milhar % 2 === 0 ? milhar - 1 : milhar) : 3) * 1000 + 500) : ((milhar > 0 ? (milhar % 2 !== 0 ? milhar + 1 : milhar) : 4) * 1000 + 500);
                    const sugestaoIfr = ehImpar ? ((milhar > 0 ? (milhar % 2 === 0 ? milhar - 1 : milhar) : 3) * 1000) : ((milhar > 0 ? (milhar % 2 !== 0 ? milhar + 1 : milhar) : 4) * 1000);
                    txtRegraEsperadaConflito.textContent = ehImpar 
                        ? `ÍMPAR (ex: ${formatarNumero(sugestaoVfr)} ft VFR ou ${formatarNumero(sugestaoIfr)} ft IFR)` 
                        : `PAR (ex: ${formatarNumero(sugestaoVfr)} ft VFR ou ${formatarNumero(sugestaoIfr)} ft IFR)`;
                }
            } else {
                avisoConflitoRegraAlt.classList.add("oculto");
            }

            // Atualizar destaque das pílulas
            document.querySelectorAll(".pill-alt").forEach(btn => {
                const fl = btn.querySelector(".pill-fl");
                if (fl) {
                    const texto = fl.textContent.trim();
                    const altPill = parseInt(texto.replace(/\D/g, '')) * (texto.startsWith('FL') ? 100 : 1);
                    if (altPill === altDigitada) {
                        btn.classList.add("ativo");
                    } else {
                        btn.classList.remove("ativo");
                    }
                }
            });
        }

        // Busca e preenchimento de aeronave
        function atualizarDadosAeronave() {
            const nomeDigitado = campoAeronave.value.trim().toLowerCase();
            aeronaveSelecionada = AERONAVES.find(a => {
                const mod = a.modelo.toLowerCase();
                const nomeCompleto = (a.fabricante + " " + a.modelo).toLowerCase();
                return mod === nomeDigitado || nomeCompleto === nomeDigitado;
            });

            if (aeronaveSelecionada) {
                if (cardAeroInfo) {
                    cardAeroInfo.classList.remove("oculto");
                    if (txtAeroMiniNome) txtAeroMiniNome.textContent = `${aeronaveSelecionada.fabricante} ${aeronaveSelecionada.modelo}`;
                    if (txtAeroMiniComb) txtAeroMiniComb.textContent = aeronaveSelecionada.tipo_combustivel || "Avgas";
                    if (txtAeroMiniVel) txtAeroMiniVel.textContent = `${Math.round(parseFloat(aeronaveSelecionada.velocidade_cruzeiro) || 0)} kt`;
                    if (txtAeroMiniGph) txtAeroMiniGph.textContent = `${Math.round(parseFloat(aeronaveSelecionada.consumo_gph) || 0)} GPH`;
                    if (txtAeroMiniTanque) txtAeroMiniTanque.textContent = `${formatarNumero(Math.round(parseFloat(aeronaveSelecionada.capacidade_tanque) || 0))} gal`;
                    if (txtAeroMiniTeto) txtAeroMiniTeto.textContent = `${formatarNumero(parseFloat(aeronaveSelecionada.teto_operacional) || 0)} ft`;
                }

                if (aeronaveSelecionada.capacidade_tanque > 0) {
                    const cap = Math.round(aeronaveSelecionada.capacidade_tanque);
                    if (sliderTanque) {
                        sliderTanque.max = cap;
                        sliderTanque.value = cap;
                    }
                    if (campoTanqueDecolagem) {
                        campoTanqueDecolagem.max = cap;
                        campoTanqueDecolagem.value = cap;
                    }
                }
                const maxAssentos = Math.max(1, parseInt(aeronaveSelecionada.assentos) || 4);
                if (sliderPassageiros) {
                    sliderPassageiros.min = 1;
                    sliderPassageiros.max = maxAssentos;
                    if (parseInt(sliderPassageiros.value) > maxAssentos || parseInt(sliderPassageiros.value) < 1) {
                        sliderPassageiros.value = 1;
                    }
                }
                if (campoQtdPassageiros) {
                    campoQtdPassageiros.min = 1;
                    campoQtdPassageiros.max = maxAssentos;
                    if (parseInt(campoQtdPassageiros.value) > maxAssentos || parseInt(campoQtdPassageiros.value) < 1) {
                        campoQtdPassageiros.value = 1;
                    }
                }
                if (aeronaveSelecionada.altitude_cruzeiro_ideal > 0) {
                    campoAltitudeCruzeiro.value = Math.round(aeronaveSelecionada.altitude_cruzeiro_ideal);
                }
                if (aeronaveSelecionada.velocidade_cruzeiro > 0) {
                    campoVelocidade.value = Math.round(aeronaveSelecionada.velocidade_cruzeiro);
                    campoRazaoDescida.value = formatarMilhar(Math.round(aeronaveSelecionada.velocidade_cruzeiro * 5));
                }
                if (aeronaveSelecionada.velocidade_subida > 0) {
                    campoVelocidadeSubida.value = Math.round(aeronaveSelecionada.velocidade_subida);
                } else if (aeronaveSelecionada.velocidade_cruzeiro > 0) {
                    campoVelocidadeSubida.value = Math.round(aeronaveSelecionada.velocidade_cruzeiro * 0.85);
                }
                if (aeronaveSelecionada.razao_subida > 0) {
                    campoRazaoSubida.value = formatarMilhar(Math.round(aeronaveSelecionada.razao_subida));
                }
                if (aeronaveSelecionada.consumo_gph > 0) {
                    campoConsumoGph.value = Math.round(parseFloat(aeronaveSelecionada.consumo_gph));
                }
            } else {
                if (cardAeroInfo) cardAeroInfo.classList.add("oculto");
            }
            calcularPlanejamento();
            atualizarAutonomiaEPesos();
            renderizarComparativoFrota();
            if (rumoAtualRota !== null) {
                atualizarRegraAltitudes(rumoAtualRota);
            }
        }

        // Busca de aeródromo e distância via API
        function atualizarAerodromosEDistancia() {
            const origem = campoPartida.value.trim().toUpperCase();
            const destino = campoDestino.value.trim().toUpperCase();

            if (previewPartida) previewPartida.textContent = origem || "ORIGEM";
            if (previewDestino) previewDestino.textContent = destino || "DESTINO";

            if (origem.length === 4) {
                buscarAerodromoIndividual(origem, dados => {
                    aerodromoOrigemDados = dados;
                    if (previewPartidaNome) previewPartidaNome.textContent = dados ? (dados.nome + " - " + dados.municipio + "/" + dados.uf) : "Aeródromo não encontrado";
                    if (previewPartidaAlt) previewPartidaAlt.textContent = dados ? `${formatarNumero(dados.altitude_ft)} ft` : "0 ft";
                    if (badgeAltOrigem) badgeAltOrigem.textContent = dados ? `${formatarNumero(dados.altitude_ft)} ft` : "0 ft";
                    
                    if (aerodromoDestinoDados && dados && dados.lat && aerodromoDestinoDados.lat) {
                        const rumo = calcularRumoClientSide(dados.lat, dados.lon, aerodromoDestinoDados.lat, aerodromoDestinoDados.lon);
                        atualizarBussolaRota(rumo.rumo_graus, rumo.rumo_formatado, rumo.direcao_sigla, rumo.direcao_nome);
                    }
                    calcularPlanejamento();
                    renderizarComparativoFrota();
                    if (modalMapaRota && !modalMapaRota.classList.contains("oculto")) {
                        desenharRotaNoMapa();
                        centralizarRotaMapa();
                    }
                });
            } else {
                aerodromoOrigemDados = null;
                if (previewPartidaNome) previewPartidaNome.textContent = "Informe o ICAO de origem";
                if (previewPartidaAlt) previewPartidaAlt.textContent = "0 ft";
                if (badgeAltOrigem) badgeAltOrigem.textContent = "0 ft";
                atualizarBussolaRota(null);
                renderizarComparativoFrota();
                if (modalMapaRota && !modalMapaRota.classList.contains("oculto")) {
                    desenharRotaNoMapa();
                }
            }

            if (destino.length === 4) {
                buscarAerodromoIndividual(destino, dados => {
                    aerodromoDestinoDados = dados;
                    if (previewDestinoNome) previewDestinoNome.textContent = dados ? (dados.nome + " - " + dados.municipio + "/" + dados.uf) : "Aeródromo não encontrado";
                    if (previewDestinoAlt) previewDestinoAlt.textContent = dados ? `${formatarNumero(dados.altitude_ft)} ft` : "0 ft";
                    if (badgeAltDestino) badgeAltDestino.textContent = dados ? `${formatarNumero(dados.altitude_ft)} ft` : "0 ft";
                    
                    if (aerodromoOrigemDados && dados && aerodromoOrigemDados.lat && dados.lat) {
                        const rumo = calcularRumoClientSide(aerodromoOrigemDados.lat, aerodromoOrigemDados.lon, dados.lat, dados.lon);
                        atualizarBussolaRota(rumo.rumo_graus, rumo.rumo_formatado, rumo.direcao_sigla, rumo.direcao_nome);
                    }
                    calcularPlanejamento();
                    renderizarComparativoFrota();
                    if (modalMapaRota && !modalMapaRota.classList.contains("oculto")) {
                        desenharRotaNoMapa();
                        centralizarRotaMapa();
                    }
                });
            } else {
                aerodromoDestinoDados = null;
                if (previewDestinoNome) previewDestinoNome.textContent = "Informe o ICAO de destino";
                if (previewDestinoAlt) previewDestinoAlt.textContent = "0 ft";
                if (badgeAltDestino) badgeAltDestino.textContent = "0 ft";
                atualizarBussolaRota(null);
                renderizarComparativoFrota();
                if (modalMapaRota && !modalMapaRota.classList.contains("oculto")) {
                    desenharRotaNoMapa();
                }
            }

            if (origem.length === 4 && destino.length === 4) {
                fetch(`calcular_distancia.php?origem=${origem}&destino=${destino}`)
                    .then(r => r.json())
                    .then(dados => {
                        if (dados.distancia_nm) {
                            campoDistancia.value = dados.distancia_nm;
                        }
                        if (dados.rumo_graus !== undefined) {
                            atualizarBussolaRota(dados.rumo_graus, dados.rumo_formatado, dados.direcao_sigla, dados.direcao_nome);
                        }
                        calcularPlanejamento();
                        renderizarComparativoFrota();
                        if (modalMapaRota && !modalMapaRota.classList.contains("oculto")) {
                            desenharRotaNoMapa();
                            centralizarRotaMapa();
                        }
                    })
                    .catch(() => {});
            }
        }

        function buscarAerodromoIndividual(oaci, callback) {
            if (cacheAerodromos[oaci]) {
                callback(cacheAerodromos[oaci]);
                return;
            }
            fetch(`calcular_distancia.php?oaci=${oaci}`)
                .then(r => r.json())
                .then(d => {
                    if (d.encontrado && d.aerodromo) {
                        cacheAerodromos[oaci] = d.aerodromo;
                        callback(d.aerodromo);
                    } else {
                        callback(null);
                    }
                })
                .catch(() => callback(null));
        }

        // =========================================================================
        // Motor de Cálculo de Planejamento de Voo
        // =========================================================================
        function calcularPlanejamento() {
            const distanciaTotal = parseFloat(campoDistancia.value) || 0;
            const altitudeCruzeiro = parseFloat(campoAltitudeCruzeiro.value) || 0;
            const tanqueDecolagem = parseFloat(campoTanqueDecolagem.value) || 0;
            const velocidadeCruzeiro = Math.round(parseFloat(campoVelocidade.value) || 120);
            const razaoSubida = parseMilhar(campoRazaoSubida.value) || 700;
            let razaoDescida = parseMilhar(campoRazaoDescida.value);
            if (isNaN(razaoDescida) || razaoDescida <= 0) {
                razaoDescida = Math.round(velocidadeCruzeiro * 5);
                campoRazaoDescida.value = formatarMilhar(razaoDescida);
            }
            const consumoGph = Math.round(parseFloat(campoConsumoGph.value) || 10);

            const altOrigem = aerodromoOrigemDados ? aerodromoOrigemDados.altitude_ft : 0;
            const altDestino = aerodromoDestinoDados ? aerodromoDestinoDados.altitude_ft : 0;

            // Verificação de Teto Operacional
            if (aeronaveSelecionada && aeronaveSelecionada.teto_operacional > 0 && altitudeCruzeiro > aeronaveSelecionada.teto_operacional) {
                txtTetoMax.textContent = formatarNumero(aeronaveSelecionada.teto_operacional);
                alertaTeto.classList.remove("oculto");
            } else {
                alertaTeto.classList.add("oculto");
            }

            if (distanciaTotal <= 0 || altitudeCruzeiro <= 0) {
                limparResultados();
                return;
            }

            // Subida (Climb)
            const deltaAltSubida = Math.max(0, altitudeCruzeiro - altOrigem);
            const tempoSubidaMin = razaoSubida > 0 ? (deltaAltSubida / razaoSubida) : 0;
            const velSubidaDigitada = parseFloat(campoVelocidadeSubida ? campoVelocidadeSubida.value : 0);
            const velMediaSubida = velSubidaDigitada > 0 ? velSubidaDigitada : Math.max(40, velocidadeCruzeiro * 0.85);
            let distSubida = (tempoSubidaMin / 60) * velMediaSubida;

            // Descida (Descent)
            const deltaAltDescida = Math.max(0, altitudeCruzeiro - altDestino);
            const tempoDescidaMin = razaoDescida > 0 ? (deltaAltDescida / razaoDescida) : 0;
            const velMediaDescida = velocidadeCruzeiro;
            let distDescida = (tempoDescidaMin / 60) * velMediaDescida;

            let altitudeNiveladaReal = altitudeCruzeiro;
            let distCruzeiro = distanciaTotal - distSubida - distDescida;
            let tempoCruzeiroMin = 0;

            // Caso de Rota Curta (perfil triangular)
            if (distCruzeiro < 0) {
                alertaRotaCurta.classList.remove("oculto");
                txtDistRota.textContent = formatarNumero(distanciaTotal);
                txtAltPretendida.textContent = formatarNumero(altitudeCruzeiro);

                // No perfil triangular: distSubida + distDescida = distanciaTotal
                // (deltaAlt / ROC) * (velSubida / 60) + (deltaAlt / ROD) * (velDescida / 60) = distanciaTotal
                const fatorSubida = (velMediaSubida / (60 * razaoSubida));
                const fatorDescida = (velMediaDescida / (60 * razaoDescida));
                const altBaseMedia = (altOrigem * fatorSubida + altDestino * fatorDescida);
                const deltaAltMax = Math.max(0, (distanciaTotal + (altOrigem * fatorSubida) + (altDestino * fatorDescida)) / (fatorSubida + fatorDescida) - Math.min(altOrigem, altDestino));
                
                altitudeNiveladaReal = Math.round(Math.min(altOrigem, altDestino) + deltaAltMax);
                txtAltMaxAtingivel.textContent = formatarNumero(altitudeNiveladaReal);

                // Recalcular com a altitude real atingível
                const dAltSubReal = Math.max(0, altitudeNiveladaReal - altOrigem);
                const tSubReal = dAltSubReal / razaoSubida;
                distSubida = (tSubReal / 60) * velMediaSubida;

                const dAltDescReal = Math.max(0, altitudeNiveladaReal - altDestino);
                const tDescReal = dAltDescReal / razaoDescida;
                distDescida = Math.max(0, distanciaTotal - distSubida);

                distCruzeiro = 0;
                tempoCruzeiroMin = 0;
            } else {
                alertaRotaCurta.classList.add("oculto");
                tempoCruzeiroMin = (distCruzeiro / velocidadeCruzeiro) * 60;
            }

            // Posições TOC e TOD
            const distTOC = Math.min(distanciaTotal, distSubida);
            const distTOD = Math.max(distTOC, distanciaTotal - distDescida);

            const tempoSubidaFinal = (distSubida / velMediaSubida) * 60;
            const tempoDescidaFinal = (distDescida / velMediaDescida) * 60;
            const tempoTotalMin = tempoSubidaFinal + tempoCruzeiroMin + tempoDescidaFinal;

            // Consumo de Combustível (ponderação: 1.2x em subida, 1.0x em cruzeiro, 0.8x em descida)
            const consumoSubida = (tempoSubidaFinal / 60) * (consumoGph * 1.2);
            const consumoCruzeiro = (tempoCruzeiroMin / 60) * consumoGph;
            const consumoDescida = (tempoDescidaFinal / 60) * (consumoGph * 0.8);
            const consumoTotal = consumoSubida + consumoCruzeiro + consumoDescida;
            const tanquePouso = tanqueDecolagem - consumoTotal;
            const tanquePousoPct = tanqueDecolagem > 0 ? (tanquePouso / tanqueDecolagem) * 100 : 0;

            // Alerta de Combustível
            if (tanquePouso < 0) {
                alertaCombustivel.classList.remove("oculto");
            } else {
                alertaCombustivel.classList.add("oculto");
            }

            const consumoPct = tanqueDecolagem > 0 ? (consumoTotal / tanqueDecolagem) * 100 : 0;

            ultimoCalculoPlano = { tempoTotalMin, consumoTotal, tanqueDecolagem, tanquePouso };

            // Atualização dos KPIs
            kpiTempoTotal.textContent = formatarMinutosParaHhMm(tempoTotalMin);
            kpiTempoTotalMin.textContent = `${Math.round(tempoTotalMin)} min de voo`;

            kpiConsumoTotal.textContent = `${formatarNumero(consumoTotal, 1)} gal`;
            kpiConsumoPct.textContent = `${formatarNumero(consumoPct, 0)}% do tanque inicial`;

            kpiTocDist.textContent = `${formatarNumero(Math.round(distTOC), 0)} NM`;
            kpiTocTempo.textContent = `em ${formatarMinutosParaHhMm(tempoSubidaFinal)}`;

            kpiTodDist.textContent = `${formatarNumero(Math.round(distTOD), 0)} NM`;
            kpiTodRem.textContent = `a ${formatarNumero(Math.round(distanciaTotal - distTOD), 0)} NM do destino`;

            kpiCruzeiroDist.textContent = `${formatarNumero(Math.round(distCruzeiro), 0)} NM`;
            kpiCruzeiroTempo.textContent = `em ${formatarMinutosParaHhMm(tempoCruzeiroMin)}`;

            // Atualização da Tabela de Fases
            faseSubidaAlt.textContent = `${formatarNumero(altOrigem)} ft ➔ ${formatarNumero(altitudeNiveladaReal)} ft`;
            faseSubidaDist.textContent = `${formatarNumero(distSubida, 1)} NM`;
            faseSubidaTempo.textContent = formatarMinutosParaHhMm(tempoSubidaFinal);
            faseSubidaVel.textContent = `${formatarNumero(velMediaSubida, 0)} kt`;
            faseSubidaTaxa.textContent = `+${formatarNumero(razaoSubida, 0)} ft/min`;
            faseSubidaConsumo.textContent = `${formatarNumero(consumoSubida, 1)} gal`;

            faseCruzeiroAlt.textContent = `${formatarNumero(altitudeNiveladaReal)} ft (Nivelado)`;
            faseCruzeiroDist.textContent = `${formatarNumero(Math.round(distCruzeiro), 0)} NM`;
            faseCruzeiroTempo.textContent = formatarMinutosParaHhMm(tempoCruzeiroMin);
            faseCruzeiroVel.textContent = `${formatarNumero(velocidadeCruzeiro, 0)} kt`;
            faseCruzeiroTaxa.textContent = "0 ft/min";
            faseCruzeiroConsumo.textContent = `${formatarNumero(consumoCruzeiro, 1)} gal`;

            faseDescidaAlt.textContent = `${formatarNumero(altitudeNiveladaReal)} ft ➔ ${formatarNumero(altDestino)} ft`;
            faseDescidaDist.textContent = `${formatarNumero(distDescida, 1)} NM`;
            faseDescidaTempo.textContent = formatarMinutosParaHhMm(tempoDescidaFinal);
            faseDescidaVel.textContent = `${formatarNumero(velMediaDescida, 0)} kt`;
            faseDescidaTaxa.textContent = `-${formatarNumero(razaoDescida, 0)} ft/min`;
            faseDescidaConsumo.textContent = `${formatarNumero(consumoDescida, 1)} gal`;

            totalFasesDist.innerHTML = `<strong>${formatarNumero(distanciaTotal, 1)} NM</strong>`;
            totalFasesTempo.innerHTML = `<strong>${formatarMinutosParaHhMm(tempoTotalMin)}</strong>`;
            totalFasesConsumo.innerHTML = `<strong>${formatarNumero(consumoTotal, 1)} gal</strong>`;

            // Atualizar Gráfico Dinâmico
            renderizarGraficoPerfil({
                origemOaci: campoPartida.value.trim().toUpperCase() || "Origem",
                destinoOaci: campoDestino.value.trim().toUpperCase() || "Destino",
                distanciaTotal,
                altOrigem,
                altDestino,
                altitudeNiveladaReal,
                distTOC,
                distTOD,
                distCruzeiro,
                tempoSubidaFinal,
                tempoAteTod: tempoSubidaFinal + tempoCruzeiroMin
            });
            atualizarAutonomiaEPesos(consumoTotal);
            renderizarComparativoFrota();
        }

        function limparResultados() {
            ultimoCalculoPlano = null;
            kpiTempoTotal.textContent = "--:--";
            kpiTempoTotalMin.textContent = "0 min de voo";
            kpiConsumoTotal.textContent = "-- gal";
            kpiConsumoPct.textContent = "--% do tanque inicial";
            kpiTocDist.textContent = "-- NM";
            kpiTodDist.textContent = "-- NM";
            kpiCruzeiroDist.textContent = "-- NM";
            if (chartPerfil) {
                chartPerfil.destroy();
                chartPerfil = null;
            }
            atualizarAutonomiaEPesos();
            renderizarComparativoFrota();
        }

        // Plugin para desenhar linhas verticais tracejadas e rótulos/badges no gráfico
        const pluginRotulosPontos = {
            id: "rotulosPontos",
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                if (!meta || !meta.data || meta.data.length === 0) return;

                ctx.save();
                const cores = ["#0284c7", "#ef4444", "#f59e0b", "#10b981"];

                // 1. Linhas tracejadas verticais do ponto até o eixo X
                meta.data.forEach((element, index) => {
                    const ponto = chart.data.datasets[0].data[index];
                    if (!ponto || !ponto.linhaTracejada) return;

                    const posX = element.x;
                    const posY = element.y;
                    const cor = ponto.corLinha || cores[index % cores.length];

                    ctx.save();
                    ctx.beginPath();
                    ctx.setLineDash([4, 4]);
                    ctx.strokeStyle = cor;
                    ctx.lineWidth = 1.5;
                    ctx.moveTo(posX, posY);
                    ctx.lineTo(posX, chart.chartArea.bottom);
                    ctx.stroke();
                    ctx.restore();
                });

                // 2. Badges de tempo na base (encostados no eixo X)
                meta.data.forEach((element, index) => {
                    const ponto = chart.data.datasets[0].data[index];
                    if (!ponto || !ponto.tempoTexto) return;

                    const textoTempo = ponto.tempoTexto;
                    const cor = ponto.corLinha || cores[index % cores.length];

                    ctx.font = "bold 10.5px 'Plus Jakarta Sans', sans-serif";
                    const textWidth = ctx.measureText(textoTempo).width;
                    const badgeWidth = textWidth + 12;
                    const badgeHeight = 18;

                    let posX = element.x;
                    let rectX = posX - (badgeWidth / 2);

                    // Evita corte nas laterais do gráfico
                    rectX = Math.max(chart.chartArea.left + 2, Math.min(chart.chartArea.right - badgeWidth - 2, rectX));

                    // Posiciona logo acima da linha do eixo X
                    const rectY = chart.chartArea.bottom - badgeHeight - 2;

                    // Fundo do badge de tempo
                    ctx.fillStyle = "rgba(255, 255, 255, 0.95)";
                    ctx.strokeStyle = cor;
                    ctx.lineWidth = 1.2;

                    ctx.beginPath();
                    if (ctx.roundRect) {
                        ctx.roundRect(rectX, rectY, badgeWidth, badgeHeight, 3);
                    } else {
                        ctx.rect(rectX, rectY, badgeWidth, badgeHeight);
                    }
                    ctx.fill();
                    ctx.stroke();

                    // Texto do tempo
                    ctx.fillStyle = cor;
                    ctx.textAlign = "center";
                    ctx.textBaseline = "middle";
                    ctx.fillText(textoTempo, rectX + (badgeWidth / 2), rectY + (badgeHeight / 2) + 0.5);
                });

                // 3. Badges principais junto às bolinhas (Origem, TOC, TOD, Destino)
                ctx.font = "bold 11px 'Plus Jakarta Sans', sans-serif";
                meta.data.forEach((element, index) => {
                    const ponto = chart.data.datasets[0].data[index];
                    if (!ponto || !ponto.tag) return;

                    const texto = ponto.tag;
                    const cor = cores[index % cores.length];

                    const textWidth = ctx.measureText(texto).width;
                    const badgeWidth = textWidth + 14;
                    const badgeHeight = 20;

                    let posX = element.x;
                    let posY = element.y;

                    // Ajuste horizontal para não estourar as bordas do canvas
                    let rectX = posX - (badgeWidth / 2);
                    if (index === 0) {
                        rectX = Math.max(chart.chartArea.left + 2, posX - 4);
                    } else if (index === meta.data.length - 1) {
                        rectX = Math.min(chart.chartArea.right - badgeWidth - 2, posX - badgeWidth + 4);
                    }

                    // Posição vertical: acima do ponto (ou abaixo se estiver muito perto do topo)
                    let rectY = posY - badgeHeight - 9;
                    if (rectY < chart.chartArea.top + 2) {
                        rectY = posY + 9;
                    }

                    // Desenhar fundo do badge (pill branca com borda colorida)
                    ctx.fillStyle = "rgba(255, 255, 255, 0.95)";
                    ctx.strokeStyle = cor;
                    ctx.lineWidth = 1.5;

                    ctx.beginPath();
                    if (ctx.roundRect) {
                        ctx.roundRect(rectX, rectY, badgeWidth, badgeHeight, 4);
                    } else {
                        ctx.rect(rectX, rectY, badgeWidth, badgeHeight);
                    }
                    ctx.fill();
                    ctx.stroke();

                    // Desenhar texto
                    ctx.fillStyle = cor;
                    ctx.textAlign = "center";
                    ctx.textBaseline = "middle";
                    ctx.fillText(texto, rectX + (badgeWidth / 2), rectY + (badgeHeight / 2) + 0.5);
                });

                ctx.restore();
            }
        };

        // =========================================================================
        // Renderização do Gráfico do Perfil Vertical de Voo
        // =========================================================================
        function renderizarGraficoPerfil(dados) {
            const ctx = document.getElementById("graficoPerfilVoo").getContext("2d");

            // Gradiente suave no preenchimento
            const gradient = ctx.createLinearGradient(0, 0, 0, 360);
            gradient.addColorStop(0, "rgba(2, 132, 199, 0.38)");
            gradient.addColorStop(1, "rgba(2, 132, 199, 0.02)");

            const pontosDados = [
                { x: 0, y: dados.altOrigem, label: `Origem (${dados.origemOaci})`, tag: dados.origemOaci },
                { 
                    x: dados.distTOC, 
                    y: dados.altitudeNiveladaReal, 
                    label: "TOC (Top of Climb)", 
                    tag: "TOC",
                    linhaTracejada: true,
                    corLinha: "#ef4444",
                    tempoTexto: formatarMinutosParaHhMm(dados.tempoSubidaFinal)
                },
                { 
                    x: dados.distTOD, 
                    y: dados.altitudeNiveladaReal, 
                    label: "TOD (Top of Descent)", 
                    tag: "TOD",
                    linhaTracejada: true,
                    corLinha: "#f59e0b",
                    tempoTexto: formatarMinutosParaHhMm(dados.tempoAteTod)
                },
                { x: dados.distanciaTotal, y: dados.altDestino, label: `Destino (${dados.destinoOaci})`, tag: dados.destinoOaci }
            ];

            const maxAltGrafico = Math.max(dados.altitudeNiveladaReal, dados.altOrigem, dados.altDestino) + 2000;

            if (chartPerfil) {
                chartPerfil.data.datasets[0].data = pontosDados;
                chartPerfil.options.scales.x.max = dados.distanciaTotal;
                chartPerfil.options.scales.y.max = maxAltGrafico;
                chartPerfil.update();
                return;
            }

            chartPerfil = new Chart(ctx, {
                type: "line",
                data: {
                    datasets: [
                        {
                            label: "Perfil Vertical de Voo",
                            data: pontosDados,
                            borderColor: "#0284c7",
                            borderWidth: 3.5,
                            fill: true,
                            backgroundColor: gradient,
                            tension: 0.05,
                            pointBackgroundColor: ["#0284c7", "#ef4444", "#f59e0b", "#10b981"],
                            pointBorderColor: "#ffffff",
                            pointBorderWidth: 2.5,
                            pointRadius: 6,
                            pointHoverRadius: 9
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 400 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: "rgba(15, 23, 42, 0.9)",
                            titleFont: { size: 13, weight: "bold", family: "'Plus Jakarta Sans', sans-serif" },
                            bodyFont: { size: 12, family: "'Plus Jakarta Sans', sans-serif" },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                title: items => items[0].raw.label || "Ponto de Voo",
                                label: item => `Altitude: ${formatarNumero(item.raw.y)} ft | Distância: ${formatarNumero(item.raw.x, 1)} NM`
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: "linear",
                            min: 0,
                            max: dados.distanciaTotal,
                            title: {
                                display: true,
                                text: "Distância Percorrida (NM)",
                                color: "#64748b",
                                font: { size: 12, weight: "bold", family: "'Plus Jakarta Sans', sans-serif" }
                            },
                            grid: { color: "#e2e8f0" },
                            ticks: {
                                color: "#64748b",
                                font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                                callback: val => `${val} NM`
                            }
                        },
                        y: {
                            min: 0,
                            max: maxAltGrafico,
                            title: {
                                display: true,
                                text: "Altitude (Pés / ft)",
                                color: "#64748b",
                                font: { size: 12, weight: "bold", family: "'Plus Jakarta Sans', sans-serif" }
                            },
                            grid: { color: "#e2e8f0" },
                            ticks: {
                                color: "#64748b",
                                font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                                callback: val => `${formatarNumero(val)} ft`
                            }
                        }
                    }
                },
                plugins: [pluginRotulosPontos]
            });
        }

        // =========================================================================
        // Persistência Automática (Draft / Rascunho Local)
        // =========================================================================
        const CHAVE_RASCUNHO = "avioes_planejamento_draft";

        function salvarRascunhoLocal() {
            const draft = {
                aeronave: campoAeronave.value,
                partida: campoPartida.value,
                destino: campoDestino.value,
                distancia: campoDistancia.value,
                altitudeCruzeiro: campoAltitudeCruzeiro.value,
                tanqueDecolagem: campoTanqueDecolagem.value,
                passageiros: campoQtdPassageiros ? campoQtdPassageiros.value : 1,
                velocidadeSubida: campoVelocidadeSubida ? campoVelocidadeSubida.value : "",
                razaoSubida: campoRazaoSubida ? campoRazaoSubida.value : "",
                velocidade: campoVelocidade ? campoVelocidade.value : "",
                razaoDescida: campoRazaoDescida ? campoRazaoDescida.value : "",
                consumoGph: campoConsumoGph ? campoConsumoGph.value : ""
            };
            try {
                localStorage.setItem(CHAVE_RASCUNHO, JSON.stringify(draft));
            } catch (e) {}
        }

        function restaurarRascunhoLocal() {
            try {
                const raw = localStorage.getItem(CHAVE_RASCUNHO);
                if (!raw) return false;
                const d = JSON.parse(raw);
                if (!d) return false;

                let temDados = false;
                if (d.aeronave) { campoAeronave.value = d.aeronave; temDados = true; }
                if (d.partida) { campoPartida.value = d.partida; temDados = true; }
                if (d.destino) { campoDestino.value = d.destino; temDados = true; }
                if (d.distancia) { campoDistancia.value = d.distancia; temDados = true; }
                if (d.altitudeCruzeiro) { campoAltitudeCruzeiro.value = d.altitudeCruzeiro; temDados = true; }
                if (d.tanqueDecolagem) { 
                    campoTanqueDecolagem.value = d.tanqueDecolagem; 
                    if (sliderTanque) sliderTanque.value = d.tanqueDecolagem;
                    temDados = true; 
                }
                if (d.passageiros) {
                    const pass = Math.max(1, parseInt(d.passageiros) || 1);
                    if (campoQtdPassageiros) campoQtdPassageiros.value = pass;
                    if (sliderPassageiros) sliderPassageiros.value = pass;
                }

                if (temDados) {
                    if (campoAeronave.value) {
                        const nomeDigitado = campoAeronave.value.trim().toLowerCase();
                        aeronaveSelecionada = AERONAVES.find(a => {
                            const mod = a.modelo.toLowerCase();
                            const nomeCompleto = (a.fabricante + " " + a.modelo).toLowerCase();
                            return mod === nomeDigitado || nomeCompleto === nomeDigitado;
                        });
                    }

                    // Valores de desempenho restaurados do draft (mesmo se o usuário alterou manualmente)
                    if (d.velocidadeSubida && campoVelocidadeSubida) campoVelocidadeSubida.value = Math.round(parseFloat(d.velocidadeSubida));
                    if (d.razaoSubida && campoRazaoSubida) campoRazaoSubida.value = formatarMilhar(d.razaoSubida);
                    if (d.velocidade && campoVelocidade) campoVelocidade.value = Math.round(parseFloat(d.velocidade));
                    if (d.razaoDescida && campoRazaoDescida) campoRazaoDescida.value = formatarMilhar(d.razaoDescida);
                    if (d.consumoGph && campoConsumoGph) campoConsumoGph.value = Math.round(parseFloat(d.consumoGph));

                    if (campoPartida.value || campoDestino.value) {
                        atualizarAerodromosEDistancia();
                    } else {
                        calcularPlanejamento();
                    }
                    return true;
                }
            } catch (e) {}
            return false;
        }

        function limparTudoENovo() {
            if (!confirm("Deseja realmente limpar todos os campos e iniciar um novo planejamento?")) return;
            try {
                localStorage.removeItem(CHAVE_RASCUNHO);
            } catch (e) {}
            campoAeronave.value = "";
            campoPartida.value = "";
            campoDestino.value = "";
            campoDistancia.value = "";
            campoAltitudeCruzeiro.value = "";
            campoTanqueDecolagem.value = "";
            if (sliderTanque) sliderTanque.value = 0;
            if (campoQtdPassageiros) campoQtdPassageiros.value = 1;
            if (sliderPassageiros) sliderPassageiros.value = 1;
            atualizarAutonomiaEPesos();
            if (campoVelocidadeSubida) campoVelocidadeSubida.value = "";
            if (campoRazaoSubida) campoRazaoSubida.value = "";
            if (campoVelocidade) campoVelocidade.value = "";
            if (campoRazaoDescida) campoRazaoDescida.value = "";
            if (campoConsumoGph) campoConsumoGph.value = "";
            aeronaveSelecionada = null;
            aerodromoOrigemDados = null;
            aerodromoDestinoDados = null;
            if (previewPartida) previewPartida.textContent = "ORIGEM";
            if (previewPartidaNome) previewPartidaNome.textContent = "Informe o ICAO de origem";
            if (previewPartidaAlt) previewPartidaAlt.textContent = "0 ft";
            if (previewDestino) previewDestino.textContent = "DESTINO";
            if (previewDestinoNome) previewDestinoNome.textContent = "Informe o ICAO de destino";
            if (previewDestinoAlt) previewDestinoAlt.textContent = "0 ft";
            if (badgeAltOrigem) badgeAltOrigem.textContent = "0 ft";
            if (badgeAltDestino) badgeAltDestino.textContent = "0 ft";
            if (cardAeroInfo) cardAeroInfo.classList.add("oculto");
            atualizarBussolaRota(null);
            limparResultados();
            renderizarComparativoFrota();
        }

        // =========================================================================
        // Gerenciamento de Planos Salvos (MySQL via API AJAX)
        // =========================================================================
        const modalSalvarPlano = document.getElementById("modalSalvarPlano");
        const inputNomePlano = document.getElementById("inputNomePlano");
        const resumoSalvarAeronave = document.getElementById("resumoSalvarAeronave");
        const resumoSalvarRota = document.getElementById("resumoSalvarRota");
        const resumoSalvarDist = document.getElementById("resumoSalvarDist");
        const resumoSalvarAlt = document.getElementById("resumoSalvarAlt");
        const resumoSalvarTempo = document.getElementById("resumoSalvarTempo");
        const resumoSalvarConsumo = document.getElementById("resumoSalvarConsumo");
        const msgAlertaSalvar = document.getElementById("msgAlertaSalvar");
        const btnConfirmarSalvar = document.getElementById("btnConfirmarSalvar");

        const modalPlanosSalvos = document.getElementById("modalPlanosSalvos");
        const listaPlanosSalvosContainer = document.getElementById("listaPlanosSalvosContainer");
        const badgeQtdPlanos = document.getElementById("badgeQtdPlanos");
        let planosSalvosCache = [];

        function abrirModalSalvar() {
            const aero = campoAeronave.value.trim();
            const orig = campoPartida.value.trim().toUpperCase();
            const dest = campoDestino.value.trim().toUpperCase();

            if (!orig || !dest) {
                alert("Por favor, preencha ao menos a origem e o destino antes de salvar o planejamento.");
                return;
            }

            inputNomePlano.value = `${orig} ➔ ${dest}${aero ? " (" + aero + ")" : ""}`;
            resumoSalvarAeronave.textContent = aero || "Não informada";
            resumoSalvarRota.textContent = `${orig} ➔ ${dest}`;
            resumoSalvarDist.textContent = `${campoDistancia.value || 0} NM`;
            resumoSalvarAlt.textContent = `${formatarNumero(parseFloat(campoAltitudeCruzeiro.value) || 0)} ft`;
            resumoSalvarTempo.textContent = kpiTempoTotal.textContent;
            resumoSalvarConsumo.textContent = kpiConsumoTotal.textContent;

            msgAlertaSalvar.classList.add("oculto");
            msgAlertaSalvar.textContent = "";
            modalSalvarPlano.classList.remove("oculto");
            setTimeout(() => inputNomePlano.focus(), 100);
        }

        function fecharModalSalvar() {
            modalSalvarPlano.classList.add("oculto");
        }

        function confirmarSalvarPlano() {
            const nome = inputNomePlano.value.trim();
            if (!nome) {
                msgAlertaSalvar.textContent = "Por favor, informe um nome para o planejamento.";
                msgAlertaSalvar.classList.remove("oculto");
                return;
            }

            btnConfirmarSalvar.disabled = true;
            btnConfirmarSalvar.innerHTML = `<span>Salvando...</span>`;

            const payload = {
                nome: nome,
                modelo_aeronave: campoAeronave.value.trim(),
                origem: campoPartida.value.trim().toUpperCase(),
                destino: campoDestino.value.trim().toUpperCase(),
                distancia: parseFloat(campoDistancia.value) || 0,
                altitude_cruzeiro: parseInt(campoAltitudeCruzeiro.value) || 0,
                tanque_decolagem: parseFloat(campoTanqueDecolagem.value) || 0,
                velocidade_subida: Math.round(parseFloat(campoVelocidadeSubida ? campoVelocidadeSubida.value : 0) || 0),
                razao_subida: parseMilhar(campoRazaoSubida ? campoRazaoSubida.value : 0),
                velocidade_cruzeiro: Math.round(parseFloat(campoVelocidade ? campoVelocidade.value : 0) || 0),
                razao_descida: parseMilhar(campoRazaoDescida ? campoRazaoDescida.value : 0),
                consumo_gph: Math.round(parseFloat(campoConsumoGph ? campoConsumoGph.value : 0) || 0),
                tempo_estimado: kpiTempoTotal.textContent,
                consumo_estimado: parseFloat(kpiConsumoTotal.textContent) || 0
            };

            fetch("planejamento.php?api=salvar_plano", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(res => {
                btnConfirmarSalvar.disabled = false;
                btnConfirmarSalvar.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg><span>Salvar no Banco</span>`;

                if (res.sucesso) {
                    fecharModalSalvar();
                    atualizarContadorPlanos();
                    alert("Planejamento salvo com sucesso!");
                } else {
                    msgAlertaSalvar.textContent = res.erro || "Erro ao salvar o planejamento.";
                    msgAlertaSalvar.classList.remove("oculto");
                }
            })
            .catch(() => {
                btnConfirmarSalvar.disabled = false;
                btnConfirmarSalvar.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg><span>Salvar no Banco</span>`;
                msgAlertaSalvar.textContent = "Erro de conexão ao salvar.";
                msgAlertaSalvar.classList.remove("oculto");
            });
        }

        function abrirModalPlanosSalvos() {
            modalPlanosSalvos.classList.remove("oculto");
            carregarListaPlanosSalvos();
        }

        function fecharModalPlanosSalvos() {
            modalPlanosSalvos.classList.add("oculto");
        }

        function atualizarContadorPlanos() {
            fetch("planejamento.php?api=listar_planos")
                .then(r => r.json())
                .then(res => {
                    if (res.sucesso && Array.isArray(res.planos)) {
                        planosSalvosCache = res.planos;
                        if (badgeQtdPlanos) badgeQtdPlanos.textContent = res.planos.length;
                    }
                })
                .catch(() => {});
        }

        function escapeHtml(str) {
            if (!str) return "";
            return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        }

        function carregarListaPlanosSalvos() {
            listaPlanosSalvosContainer.innerHTML = `<div style="text-align:center; padding:30px; color:var(--muted)">Carregando planos salvos...</div>`;

            fetch("planejamento.php?api=listar_planos")
                .then(r => r.json())
                .then(res => {
                    if (!res.sucesso || !res.planos || res.planos.length === 0) {
                        listaPlanosSalvosContainer.innerHTML = `
                            <div class="plano-vazio-msg">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                <p><strong>Nenhum plano de voo salvo ainda</strong></p>
                                <p style="font-size:13px">Preencha os parâmetros do voo e clique em <em>Salvar Planejamento</em> para armazenar suas rotas.</p>
                            </div>
                        `;
                        if (badgeQtdPlanos) badgeQtdPlanos.textContent = "0";
                        return;
                    }

                    planosSalvosCache = res.planos;
                    if (badgeQtdPlanos) badgeQtdPlanos.textContent = res.planos.length;

                    listaPlanosSalvosContainer.innerHTML = res.planos.map(p => {
                        const dataFormatada = p.criado_em ? new Date(p.criado_em).toLocaleDateString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" }) : "";
                        return `
                            <div class="card-plano-salvo" id="cardPlano-${p.id}">
                                <div class="plano-info-principal">
                                    <div class="plano-topo-linha">
                                        <span class="plano-nome">${escapeHtml(p.nome)}</span>
                                        <span class="plano-data">${dataFormatada}</span>
                                    </div>
                                    <div class="plano-detalhes-linha">
                                        <span class="badge-plano-rota">${escapeHtml(p.origem)} ➔ ${escapeHtml(p.destino)}</span>
                                        <span class="badge-plano-aero">${escapeHtml(p.modelo_aeronave || "Aeronave não def.")}</span>
                                        <span class="badge-plano-metric">${formatarNumero(p.distancia)} NM</span>
                                        <span class="badge-plano-metric">${formatarNumero(p.altitude_cruzeiro)} ft</span>
                                        ${p.tempo_estimado ? `<span class="badge-plano-metric">⏱ ${escapeHtml(p.tempo_estimado)}</span>` : ""}
                                        ${p.consumo_estimado > 0 ? `<span class="badge-plano-metric">⛽ ${formatarNumero(p.consumo_estimado, 1)} gal</span>` : ""}
                                    </div>
                                </div>
                                <div class="plano-acoes">
                                    <button type="button" class="btn-carregar-plano" onclick="carregarPlanoSalvo(${p.id})">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                                        <span>Carregar</span>
                                    </button>
                                    <button type="button" class="btn-excluir-plano" onclick="excluirPlanoSalvo(${p.id})" title="Excluir este plano">
                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </div>
                        `;
                    }).join("");
                })
                .catch(() => {
                    listaPlanosSalvosContainer.innerHTML = `<div style="text-align:center; padding:30px; color:var(--danger)">Erro ao carregar planos salvos.</div>`;
                });
        }

        function carregarPlanoSalvo(id) {
            const plano = planosSalvosCache.find(p => parseInt(p.id) === parseInt(id));
            if (!plano) return;

            campoAeronave.value = plano.modelo_aeronave || "";
            campoPartida.value = plano.origem || "";
            campoDestino.value = plano.destino || "";
            campoDistancia.value = plano.distancia || "";
            campoAltitudeCruzeiro.value = plano.altitude_cruzeiro || "";
            campoTanqueDecolagem.value = plano.tanque_decolagem || "";
            if (campoVelocidadeSubida) campoVelocidadeSubida.value = plano.velocidade_subida ? Math.round(parseFloat(plano.velocidade_subida)) : "";
            if (campoRazaoSubida) campoRazaoSubida.value = plano.razao_subida ? formatarMilhar(plano.razao_subida) : "";
            if (campoVelocidade) campoVelocidade.value = plano.velocidade_cruzeiro ? Math.round(parseFloat(plano.velocidade_cruzeiro)) : "";
            if (campoRazaoDescida) campoRazaoDescida.value = plano.razao_descida ? formatarMilhar(plano.razao_descida) : "";
            if (campoConsumoGph) campoConsumoGph.value = plano.consumo_gph ? Math.round(parseFloat(plano.consumo_gph)) : "";

            if (campoAeronave.value) {
                const nomeDigitado = campoAeronave.value.trim().toLowerCase();
                aeronaveSelecionada = AERONAVES.find(a => {
                    const mod = a.modelo.toLowerCase();
                    const nomeCompleto = (a.fabricante + " " + a.modelo).toLowerCase();
                    return mod === nomeDigitado || nomeCompleto === nomeDigitado;
                });
            }

            if (campoPartida.value || campoDestino.value) {
                atualizarAerodromosEDistancia();
            } else {
                calcularPlanejamento();
            }

            salvarRascunhoLocal();
            fecharModalPlanosSalvos();
        }

        function excluirPlanoSalvo(id) {
            if (!confirm("Tem certeza que deseja excluir este planejamento salvo?")) return;

            fetch("planejamento.php?api=excluir_plano", {
                method: "POST",
                headers: { "Content-Type": "application/json", "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.sucesso) {
                    carregarListaPlanosSalvos();
                    atualizarContadorPlanos();
                } else {
                    alert(res.erro || "Erro ao excluir plano.");
                }
            })
            .catch(() => alert("Erro ao conectar com o servidor."));
        }

        // =========================================================================
        // Mapa Interativo da Rota (Leaflet.js)
        // =========================================================================
        const cardMapaRota = document.getElementById("cardMapaRota");
        const modalMapaRota = cardMapaRota;
        const containerMapaRota = document.getElementById("containerMapaRota");
        const avisoSemRotaMapa = document.getElementById("avisoSemRotaMapa");
        const mapaBadgeOrigem = document.getElementById("mapaBadgeOrigem");
        const mapaBadgeDestino = document.getElementById("mapaBadgeDestino");
        const mapaTxtDistancia = document.getElementById("mapaTxtDistancia");
        const mapaTxtRumo = document.getElementById("mapaTxtRumo");
        const mapaTxtTempo = document.getElementById("mapaTxtTempo");

        let mapaLeafletInstancia = null;
        let camadaRotaLayer = null;
        let boundsRotaAtual = null;

        function inicializarMapaLeaflet() {
            if (mapaLeafletInstancia) return;

            // Camadas base: CartoDB Voyager para o mapa e Esri Satélite para imagens.
            const camadaVoyager = L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png", {
                maxZoom: 19,
                subdomains: 'abcd',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
            });

            const camadaSatelite = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
                maxZoom: 18,
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
            });

            mapaLeafletInstancia = L.map("containerMapaRota", {
                center: [-15.7801, -47.9292], // Centro do Brasil (Brasília)
                zoom: 4,
                layers: [camadaSatelite]
            });

            const baseMaps = {
                "🗺️ Mapa Cartográfico (Padrão)": camadaVoyager,
                "🛰️ Satélite (Alta Resolução)": camadaSatelite,
            };

            L.control.layers(baseMaps, null, { position: "topright" }).addTo(mapaLeafletInstancia);
            L.control.scale({ imperial: true, metric: true, position: "bottomleft" }).addTo(mapaLeafletInstancia);

            camadaRotaLayer = L.layerGroup().addTo(mapaLeafletInstancia);
        }

        function criarIconeAviao(rumoGraus, modeloAeronave) {
            return L.divIcon({
                className: "custom-div-icon-aviao",
                html: `
                    <div class="marcador-aviao-container" title="Aeronave em Rota: ${escapeHtml(modeloAeronave || 'Aeronave')} (Rumo ${Math.round(rumoGraus)}°)">
                        <div class="marcador-aviao-aura"></div>
                        <div class="marcador-aviao-rotator" style="transform: rotate(${Math.round(rumoGraus)}deg);">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="#d946ef" stroke="#ffffff" stroke-width="0.8">
                                <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                            </svg>
                        </div>
                    </div>
                `,
                iconSize: [36, 36],
                iconAnchor: [18, 18],
                popupAnchor: [0, -18]
            });
        }

        function criarIconeAerodromo(oaci, tipo) {
            const ehOrigem = tipo === "origem";
            const corClass = ehOrigem ? "marcador-origem" : "marcador-destino";
            const iconeEmoji = ehOrigem ? "🛫" : "🛬";
            const labelTexto = ehOrigem ? "ORIGEM" : "DESTINO";

            return L.divIcon({
                className: "custom-div-icon",
                html: `
                    <div class="pin-aerodromo-mapa ${corClass}">
                        <div class="pin-badge">
                            <span class="pin-icone">${iconeEmoji}</span>
                            <span class="pin-oaci">${escapeHtml(oaci)}</span>
                        </div>
                        <span class="pin-tag-tipo">${labelTexto}</span>
                        <div class="pin-ponta"></div>
                    </div>
                `,
                iconSize: [80, 50],
                iconAnchor: [40, 50],
                popupAnchor: [0, -48]
            });
        }

        function desenharRotaNoMapa() {
            inicializarMapaLeaflet();
            if (!camadaRotaLayer) return;

            camadaRotaLayer.clearLayers();
            boundsRotaAtual = null;

            const origemVal = campoPartida.value.trim().toUpperCase();
            const destinoVal = campoDestino.value.trim().toUpperCase();

            const temOrigem = aerodromoOrigemDados && !isNaN(aerodromoOrigemDados.lat) && !isNaN(aerodromoOrigemDados.lon) && (aerodromoOrigemDados.lat !== 0 || aerodromoOrigemDados.lon !== 0);
            const temDestino = aerodromoDestinoDados && !isNaN(aerodromoDestinoDados.lat) && !isNaN(aerodromoDestinoDados.lon) && (aerodromoDestinoDados.lat !== 0 || aerodromoDestinoDados.lon !== 0);

            if (!temOrigem || !temDestino) {
                if (avisoSemRotaMapa) avisoSemRotaMapa.classList.remove("oculto");
                if (mapaBadgeOrigem) mapaBadgeOrigem.textContent = origemVal || "--";
                if (mapaBadgeDestino) mapaBadgeDestino.textContent = destinoVal || "--";
                if (mapaTxtDistancia) mapaTxtDistancia.textContent = "-- NM";
                if (mapaTxtRumo) mapaTxtRumo.textContent = "--°";
                if (mapaTxtTempo) mapaTxtTempo.textContent = "--:--";
                return;
            }

            if (avisoSemRotaMapa) avisoSemRotaMapa.classList.add("oculto");

            const p1 = [parseFloat(aerodromoOrigemDados.lat), parseFloat(aerodromoOrigemDados.lon)];
            const p2 = [parseFloat(aerodromoDestinoDados.lat), parseFloat(aerodromoDestinoDados.lon)];

            // Atualiza status do modal
            const distNm = parseFloat(campoDistancia.value) || 0;
            const distKm = Math.round(distNm * 1.852);
            const rumoStr = txtRumoGraus ? txtRumoGraus.textContent : "--°";
            const rumoDir = txtRumoDirecao ? txtRumoDirecao.textContent : "";
            const tempoEstimado = kpiTempoTotal ? kpiTempoTotal.textContent : "--:--";

            if (mapaBadgeOrigem) mapaBadgeOrigem.textContent = aerodromoOrigemDados.oaci || origemVal;
            if (mapaBadgeDestino) mapaBadgeDestino.textContent = aerodromoDestinoDados.oaci || destinoVal;
            if (mapaTxtDistancia) mapaTxtDistancia.textContent = `${formatarNumero(distNm)} NM (${formatarNumero(distKm)} km)`;
            if (mapaTxtRumo) mapaTxtRumo.textContent = `${rumoStr} ${rumoDir ? '(' + rumoDir + ')' : ''}`;
            if (mapaTxtTempo) mapaTxtTempo.textContent = tempoEstimado !== "--:--" ? tempoEstimado : "Calcular rota";

            // 1. Linha de sombra / contorno para alto contraste
            const linhaSombra = L.polyline([p1, p2], {
                color: "#0f172a",
                weight: 6.5,
                opacity: 0.35,
                lineCap: "round"
            });
            camadaRotaLayer.addLayer(linhaSombra);

            // 1.1 Linha base branca contínua para criar o traço branco nos vãos do pontilhado
            const linhaBaseBranca = L.polyline([p1, p2], {
                className: "linha-rota-fundo-branco",
                color: "#ffffff",
                weight: 4,
                opacity: 1,
                lineCap: "round"
            });
            camadaRotaLayer.addLayer(linhaBaseBranca);

            // 2. Linha principal da rota de voo com animação de fluxo contínuo
            const linhaRota = L.polyline([p1, p2], {
                className: "linha-rota-animada",
                color: "#d946ef",
                weight: 4,
                opacity: 1,
                dashArray: "12, 10",
                lineCap: "round"
            });

            linhaRota.bindPopup(`
                <div class="popup-aerodromo">
                    <div class="popup-topo topo-rota">
                        <span class="popup-tag">ROTA</span>
                        <h4>${escapeHtml(aerodromoOrigemDados.oaci)} ➔ ${escapeHtml(aerodromoDestinoDados.oaci)}</h4>
                    </div>
                    <div class="popup-corpo">
                        <p><strong>Distância Ortodrômica:</strong> ${formatarNumero(distNm)} NM (${formatarNumero(distKm)} km)</p>
                        <p><strong>Rumo da Rota:</strong> ${rumoStr} ${escapeHtml(rumoDir)}</p>
                        <p><strong>Tempo Estimado:</strong> ${escapeHtml(tempoEstimado)}</p>
                    </div>
                </div>
            `);
            camadaRotaLayer.addLayer(linhaRota);

            // 2.1 Ícone de Aeronave no Ponto Médio da Rota
            const latMid = (p1[0] + p2[0]) / 2;
            const lonMid = (p1[1] + p2[1]) / 2;
            const rumoObjAviao = calcularRumoClientSide(p1[0], p1[1], p2[0], p2[1]);
            const anguloAviao = rumoObjAviao ? rumoObjAviao.rumo_graus : 0;
            const modeloAeronave = campoAeronave ? campoAeronave.value.trim() : "";

            const iconeAviao = criarIconeAviao(anguloAviao, modeloAeronave);
            const markerAviao = L.marker([latMid, lonMid], {
                icon: iconeAviao,
                zIndexOffset: 800,
                title: `Aeronave na Rota (Rumo ${Math.round(anguloAviao)}°)`
            });

            markerAviao.bindPopup(`
                <div class="popup-aerodromo">
                    <div class="popup-topo topo-rota">
                        <span class="popup-tag">AERONAVE EM VOO</span>
                        <h4>${escapeHtml(modeloAeronave || "Aeronave Selecionada")}</h4>
                    </div>
                    <div class="popup-corpo">
                        <p><strong>Trajeto:</strong> ${escapeHtml(aerodromoOrigemDados.oaci)} ➔ ${escapeHtml(aerodromoDestinoDados.oaci)}</p>
                        <p><strong>Rumo Projetado:</strong> ${rumoStr} ${escapeHtml(rumoDir)}</p>
                        <p><strong>Distância Total:</strong> ${formatarNumero(distNm)} NM (${formatarNumero(distKm)} km)</p>
                        <p><strong>Tempo Estimado:</strong> ${escapeHtml(tempoEstimado)}</p>
                    </div>
                </div>
            `);
            camadaRotaLayer.addLayer(markerAviao);

            // 3. Marcador Origem
            const iconeOrigem = criarIconeAerodromo(aerodromoOrigemDados.oaci, "origem");
            const markerOrigem = L.marker(p1, { icon: iconeOrigem, title: `Origem: ${aerodromoOrigemDados.oaci}` });
            markerOrigem.bindPopup(`
                <div class="popup-aerodromo">
                    <div class="popup-topo topo-origem">
                        <span class="popup-tag">AERÓDROMO DE PARTIDA (ORIGEM)</span>
                        <h4>${escapeHtml(aerodromoOrigemDados.oaci)} - ${escapeHtml(aerodromoOrigemDados.nome)}</h4>
                    </div>
                    <div class="popup-corpo">
                        <p><strong>Município/UF:</strong> ${escapeHtml(aerodromoOrigemDados.municipio)} / ${escapeHtml(aerodromoOrigemDados.uf)}</p>
                        <p><strong>Elevação de Pista:</strong> ${formatarNumero(aerodromoOrigemDados.altitude_ft)} ft (${formatarNumero(aerodromoOrigemDados.altitude_m, 1)} m)</p>
                        <p><strong>Coordenadas:</strong> ${p1[0].toFixed(5)}°, ${p1[1].toFixed(5)}°</p>
                    </div>
                </div>
            `);
            camadaRotaLayer.addLayer(markerOrigem);

            // 4. Marcador Destino
            const iconeDestino = criarIconeAerodromo(aerodromoDestinoDados.oaci, "destino");
            const markerDestino = L.marker(p2, { icon: iconeDestino, title: `Destino: ${aerodromoDestinoDados.oaci}` });
            markerDestino.bindPopup(`
                <div class="popup-aerodromo">
                    <div class="popup-topo topo-destino">
                        <span class="popup-tag">AERÓDROMO DE DESTINO (CHEGADA)</span>
                        <h4>${escapeHtml(aerodromoDestinoDados.oaci)} - ${escapeHtml(aerodromoDestinoDados.nome)}</h4>
                    </div>
                    <div class="popup-corpo">
                        <p><strong>Município/UF:</strong> ${escapeHtml(aerodromoDestinoDados.municipio)} / ${escapeHtml(aerodromoDestinoDados.uf)}</p>
                        <p><strong>Elevação de Pista:</strong> ${formatarNumero(aerodromoDestinoDados.altitude_ft)} ft (${formatarNumero(aerodromoDestinoDados.altitude_m, 1)} m)</p>
                        <p><strong>Coordenadas:</strong> ${p2[0].toFixed(5)}°, ${p2[1].toFixed(5)}°</p>
                    </div>
                </div>
            `);
            camadaRotaLayer.addLayer(markerDestino);

            // Bounds da rota
            boundsRotaAtual = L.latLngBounds([p1, p2]);
        }

        function centralizarRotaMapa() {
            if (!mapaLeafletInstancia) return;
            mapaLeafletInstancia.invalidateSize();
            if (boundsRotaAtual) {
                mapaLeafletInstancia.fitBounds(boundsRotaAtual, {
                    padding: [60, 60],
                    maxZoom: 12
                });
            } else {
                mapaLeafletInstancia.setView([-15.7801, -47.9292], 4);
            }
        }

        function alternarCardMapaRota() {
            const card = document.getElementById("cardMapaRota");
            if (!card) return;
            if (card.classList.contains("oculto")) {
                abrirCardMapaRota();
            } else {
                fecharCardMapaRota();
            }
        }

        function abrirCardMapaRota() {
            const card = document.getElementById("cardMapaRota");
            if (!card) return;
            card.classList.remove("oculto");

            const btnRota = document.getElementById("btnVerMapaRota");
            if (btnRota) btnRota.classList.add("ativo");
            const btnTopo = document.getElementById("btnVerMapaTopo");
            if (btnTopo) btnTopo.classList.add("ativo");

            desenharRotaNoMapa();

            // Rola suavemente até o card de forma sutil
            card.scrollIntoView({ behavior: "smooth", block: "nearest" });

            // O Leaflet necessita de recalcular dimensões ao exibir container antes oculto
            setTimeout(() => {
                if (mapaLeafletInstancia) {
                    mapaLeafletInstancia.invalidateSize();
                    centralizarRotaMapa();
                }
            }, 180);
        }

        function fecharCardMapaRota() {
            const card = document.getElementById("cardMapaRota");
            if (!card) return;
            card.classList.add("oculto");

            const btnRota = document.getElementById("btnVerMapaRota");
            if (btnRota) btnRota.classList.remove("ativo");
            const btnTopo = document.getElementById("btnVerMapaTopo");
            if (btnTopo) btnTopo.classList.remove("ativo");
        }

        // Aliases para compatibilidade
        function abrirModalMapaRota() {
            alternarCardMapaRota();
        }

        function fecharModalMapaRota() {
            fecharCardMapaRota();
        }

        // Eventos com persistência no rascunho local
        campoAeronave.addEventListener("input", () => {
            atualizarDadosAeronave();
            salvarRascunhoLocal();
        });
        campoAeronave.addEventListener("change", () => {
            atualizarDadosAeronave();
            salvarRascunhoLocal();
        });
        campoPartida.addEventListener("input", () => {
            atualizarAerodromosEDistancia();
            salvarRascunhoLocal();
        });
        campoDestino.addEventListener("input", () => {
            atualizarAerodromosEDistancia();
            salvarRascunhoLocal();
        });

        campoVelocidade.addEventListener("input", () => {
            const velCruzeiro = parseFloat(campoVelocidade.value) || 0;
            if (velCruzeiro > 0) {
                campoRazaoDescida.value = formatarMilhar(Math.round(velCruzeiro * 5));
            }
            calcularPlanejamento();
            salvarRascunhoLocal();
        });

        if (sliderTanque) {
            sliderTanque.addEventListener("input", () => {
                if (campoTanqueDecolagem) campoTanqueDecolagem.value = sliderTanque.value;
                calcularPlanejamento();
                atualizarAutonomiaEPesos();
                salvarRascunhoLocal();
            });
        }

        if (sliderPassageiros) {
            sliderPassageiros.addEventListener("input", () => {
                let v = parseInt(sliderPassageiros.value, 10) || 1;
                if (v < 1) { v = 1; sliderPassageiros.value = 1; }
                if (campoQtdPassageiros) campoQtdPassageiros.value = v;
                atualizarAutonomiaEPesos();
                salvarRascunhoLocal();
            });
        }

        if (campoQtdPassageiros) {
            campoQtdPassageiros.addEventListener("input", () => {
                let v = parseInt(campoQtdPassageiros.value, 10) || 1;
                const max = parseInt(campoQtdPassageiros.max, 10) || 20;
                if (v > max) { v = max; campoQtdPassageiros.value = max; }
                if (v < 1) { v = 1; campoQtdPassageiros.value = 1; }
                if (sliderPassageiros) sliderPassageiros.value = v;
                atualizarAutonomiaEPesos();
                salvarRascunhoLocal();
            });
        }

        [campoDistancia, campoAltitudeCruzeiro, campoTanqueDecolagem, campoVelocidadeSubida, campoRazaoSubida, campoRazaoDescida, campoConsumoGph].forEach(input => {
            input.addEventListener("input", () => {
                if (input === campoAltitudeCruzeiro) {
                    verificarConflitoAltitudeRegra();
                }
                if (input === campoTanqueDecolagem) {
                    let v = parseFloat(campoTanqueDecolagem.value) || 0;
                    const max = parseFloat(campoTanqueDecolagem.max) || 9999;
                    if (v > max) { v = max; campoTanqueDecolagem.value = max; }
                    if (sliderTanque) sliderTanque.value = v;
                    atualizarAutonomiaEPesos();
                }
                if (input === campoRazaoSubida || input === campoRazaoDescida) {
                    input.value = input.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                }
                calcularPlanejamento();
                salvarRascunhoLocal();
            });
        });

        // Fechar modais ao clicar no fundo
        window.addEventListener("click", e => {
            if (e.target === modalSalvarPlano) fecharModalSalvar();
            if (e.target === modalPlanosSalvos) fecharModalPlanosSalvos();
        });

        // Fechar modais e card do mapa ao pressionar ESC
        window.addEventListener("keydown", e => {
            if (e.key === "Escape") {
                fecharModalSalvar();
                fecharModalPlanosSalvos();
                fecharCardMapaRota();
            }
        });

        // Inicialização: restaurar rascunho local se existir, senão valores pré-existentes
        const rascunhoRestaurado = restaurarRascunhoLocal();
        if (!rascunhoRestaurado) {
            if (campoAeronave.value) atualizarDadosAeronave();
            if (campoPartida.value || campoDestino.value) atualizarAerodromosEDistancia();
        }
        renderizarComparativoFrota();
        atualizarContadorPlanos();

        // Mapa aberto por padrão: desenha a rota e ajusta o enquadramento inicial
        desenharRotaNoMapa();
        setTimeout(() => {
            if (mapaLeafletInstancia) {
                mapaLeafletInstancia.invalidateSize();
                centralizarRotaMapa();
            }
        }, 180);
    </script>
    <?php include "rodape.php"; ?>
</body>
</html>
<?php $conn->close(); ?>
