<?php
if (!ob_start("ob_gzhandler")) ob_start();
require "auth.php";
$usuarioId = usuarioAtualId();

$aeronaves = [];
$resultado = $conn->query("SELECT id, fabricante, modelo, velocidade_cruzeiro, velocidade_subida, razao_subida, altitude_cruzeiro_ideal, consumo_gph, tipo_combustivel FROM aeronaves WHERE " . sqlAeronavesVisiveis($usuarioId) . " ORDER BY fabricante, modelo");
while ($aeronave = $resultado->fetch_assoc()) {
    $aeronaves[$aeronave["id"]] = [
        "id" => (int) $aeronave["id"],
        "nome" => $aeronave["fabricante"] . " - " . $aeronave["modelo"],
        "modelo" => $aeronave["modelo"],
        "velocidadeCruzeiro" => (float) $aeronave["velocidade_cruzeiro"],
        "velocidadeSubida" => (float) $aeronave["velocidade_subida"],
        "razaoSubida" => (float) $aeronave["razao_subida"],
        "altitudeCruzeiro" => (float) $aeronave["altitude_cruzeiro_ideal"],
        "consumoGph" => (float) $aeronave["consumo_gph"],
        "tipoCombustivel" => (string) $aeronave["tipo_combustivel"]
    ];
}
$nomesMeses = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
$totalComparativo = 6;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Horas | Operações de Frota</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body>
    <!-- Topo da Aplicação -->
    <header class="topo">
        <div class="marca">
            <div class="logo-aplicacao" role="img" aria-label="Logo Registro de Voos"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg></div>
            <div>
                <h1>Calculadora de Horas</h1>
                <p>Projeção mensal e anual de horas, consumo e custo a partir das suas rotas</p>
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
        <a href="calculadora_voo.php" class="ativo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Calculadora<br>de horas</span>
        </a>
    </nav>

    <!-- Cadastro de rotas -->
    <section class="painel-grafico calc-painel">
        <h2>Adicionar rota</h2>
        <p class="subtitulo">Informe a rota, a frequência e os meses em que ela se repete. A distância é preenchida automaticamente pelo código OACI.</p>

        <div class="calc-linha">
            <label>
                <span class="label-com-unidade"><span>Origem</span><span class="unidade-badge">OACI</span></span>
                <input type="text" id="calcOrigem" maxlength="4" autocomplete="off" placeholder="Ex.: SBMT" style="text-transform: uppercase">
            </label>
            <label>
                <span class="label-com-unidade"><span>Destino</span><span class="unidade-badge">OACI</span></span>
                <input type="text" id="calcDestino" maxlength="4" autocomplete="off" placeholder="Ex.: SBBR" style="text-transform: uppercase">
            </label>
            <label>
                <span class="label-com-unidade"><span>Distância</span><span class="unidade-badge">NM</span></span>
                <input type="number" id="calcDistancia" min="1" step="1" placeholder="Automática ou manual">
            </label>
            <label>
                <span class="label-com-unidade"><span>Vezes por mês</span></span>
                <input type="number" id="calcQuantidadeMes" min="1" step="1" value="1">
            </label>
        </div>

        <div class="calc-linha calc-linha-opcoes">
            <fieldset class="calc-grupo">
                <legend>Trajeto</legend>
                <label class="calc-check"><input type="checkbox" id="calcIdaVolta" checked><span>Ida e volta</span></label>
            </fieldset>
            <fieldset class="calc-grupo">
                <legend>Frequência</legend>
                <div class="calc-pills">
                    <label class="calc-pill"><input type="radio" name="calcFrequencia" value="todos" checked><span>Todo mês</span></label>
                    <label class="calc-pill"><input type="radio" name="calcFrequencia" value="especificos"><span>Meses específicos</span></label>
                </div>
            </fieldset>
        </div>

        <fieldset class="calc-grupo oculto" id="calcMesesGrupo">
            <legend>Meses</legend>
            <div class="calc-pills calc-pills-meses">
                <?php foreach ($nomesMeses as $indice => $nomeMes): ?>
                <label class="calc-pill"><input type="checkbox" class="calc-mes" value="<?= $indice + 1 ?>"><span><?= $nomeMes ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="calc-acoes">
            <button type="button" id="calcBotaoAdicionar" class="btn-primario">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Adicionar rota
            </button>
            <span id="calcMensagem" class="calc-mensagem" role="status" aria-live="polite"></span>
        </div>
    </section>

    <!-- Projeção -->
    <section class="painel-grafico calc-painel secao">
        <div class="calc-cabecalho">
            <h2>Projeção de voos</h2>
            <div class="calc-controles">
                <fieldset class="calc-grupo calc-grupo-inline">
                    <legend>Margem de distância</legend>
                    <div class="calc-pills">
                        <?php foreach ([0, 5, 10, 15, 20] as $margem): ?>
                        <label class="calc-pill calc-pill-margem"><input type="radio" name="calcMargem" value="<?= $margem ?>"<?= $margem === 0 ? " checked" : "" ?>><span><?= $margem ?>%</span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label class="calc-simular">
                    <span>Simular com</span>
                    <select id="calcAeronave">
                        <option value="">Selecione uma aeronave...</option>
                        <?php foreach ($aeronaves as $aeronave): ?>
                        <option value="<?= $aeronave["id"] ?>"><?= htmlspecialchars($aeronave["nome"]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div>
                <div class="kpi-info"><span class="kpi-rotulo">Horas por ano</span><strong class="kpi-valor" id="calcTotalHoras">0h 00m</strong></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon accent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg></div>
                <div class="kpi-info"><span class="kpi-rotulo">Distância por ano</span><strong class="kpi-valor" id="calcTotalNm">0 NM</strong></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                <div class="kpi-info"><span class="kpi-rotulo">Voos por ano</span><strong class="kpi-valor" id="calcTotalVoos">0</strong></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg></div>
                <div class="kpi-info"><span class="kpi-rotulo">Média por voo</span><strong class="kpi-valor" id="calcMediaNm">0 NM</strong></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon warning"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2.7c3 3.6 6 6.6 6 10.8a6 6 0 0 1-12 0c0-4.2 3-7.2 6-10.8z"></path></svg></div>
                <div class="kpi-info"><span class="kpi-rotulo">Consumo por ano</span><strong class="kpi-valor" id="calcTotalGaloes">0 gal</strong></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg></div>
                <div class="kpi-info"><span class="kpi-rotulo">Combustível por ano</span><strong class="kpi-valor" id="calcTotalCusto">R$ 0</strong></div>
            </div>
        </div>

        <h3 class="calc-subtitulo">Tempo para atingir horas de voo</h3>
        <div class="kpi-grid">
            <?php foreach ([500, 1000, 1500, 2000] as $meta): ?>
            <div class="kpi-card">
                <div class="kpi-info">
                    <span class="kpi-rotulo">Marca de <?= number_format($meta, 0, ",", ".") ?> h</span>
                    <strong class="kpi-valor" id="calcTempo<?= $meta ?>">--</strong>
                    <span class="kpi-sub">Combustível: <span id="calcCusto<?= $meta ?>">--</span></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="tabela-container">
            <table id="calcTabelaRotas">
                <thead>
                    <tr>
                        <th>Rota</th>
                        <th>Dist. (NM)</th>
                        <th>Dist. total (NM)</th>
                        <th>Tempo</th>
                        <th>Voos/mês • ano</th>
                        <th>Meses</th>
                        <th>Consumo (gal)</th>
                        <th>Custo</th>
                        <th><span class="sr-only">Ação</span></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <p id="calcVazio" class="vazio">Nenhuma rota adicionada ainda.</p>

        <div class="calc-acoes calc-acoes-fim">
            <button type="button" id="calcBotaoExportar" class="btn-secundario">Exportar dados (.json)</button>
            <button type="button" id="calcBotaoImportar" class="btn-secundario">Importar dados (.json)</button>
            <button type="button" id="calcBotaoLimpar" class="btn-secundario">Limpar rotas</button>
            <input type="file" id="calcArquivoImportar" accept=".json,application/json" class="oculto">
        </div>
    </section>

    <!-- Mapa -->
    <section class="painel-grafico calc-painel secao">
        <h2>Mapa de rotas simuladas</h2>
        <div id="calcMapa" class="calc-mapa" role="img" aria-label="Mapa com as rotas adicionadas"></div>
    </section>

    <!-- Evolução anual -->
    <section class="painel-grafico calc-painel secao">
        <div class="calc-cabecalho">
            <h2>Evolução anual</h2>
            <fieldset class="calc-grupo calc-grupo-inline">
                <legend>Indicador</legend>
                <div class="calc-pills">
                    <label class="calc-pill"><input type="radio" name="calcFiltroGrafico" value="horas" checked><span>Horas</span></label>
                    <label class="calc-pill"><input type="radio" name="calcFiltroGrafico" value="voos"><span>Qtd. de voos</span></label>
                    <label class="calc-pill"><input type="radio" name="calcFiltroGrafico" value="nm"><span>Distância</span></label>
                </div>
            </fieldset>
        </div>
        <div class="calc-grafico"><canvas id="calcGraficoMeses"></canvas></div>
    </section>

    <!-- Comparativo -->
    <section class="painel-grafico calc-painel secao">
        <h2>Comparativo de aeronaves</h2>
        <p class="subtitulo">Escolha até <?= $totalComparativo ?> aeronaves para ver quanto tempo cada uma leva para acumular as horas, com as mesmas rotas.</p>
        <div class="calc-comparativo-selects">
            <?php for ($i = 1; $i <= $totalComparativo; $i++): ?>
            <select id="calcComp<?= $i ?>" class="calc-comp-select" aria-label="Aeronave <?= $i ?> do comparativo">
                <option value="">Aeronave <?= $i ?>...</option>
                <?php foreach ($aeronaves as $aeronave): ?>
                <option value="<?= $aeronave["id"] ?>"><?= htmlspecialchars($aeronave["nome"]) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endfor; ?>
        </div>
        <div id="calcPillsComparativo" class="calc-legenda"></div>
        <div class="calc-grafico calc-grafico-alto"><canvas id="calcGraficoComparativo"></canvas></div>

        <h3 class="calc-subtitulo calc-subtitulo-tabela">Projeção por aeronave</h3>
        <div id="calcTabelaComparativoContainer" class="tabela-container oculto">
            <table id="calcTabelaComparativo" class="calc-tabela-comparativo">
                <thead></thead>
                <tbody></tbody>
            </table>
        </div>
        <p id="calcComparativoVazio" class="vazio">Adicione rotas e escolha aeronaves acima para comparar os indicadores.</p>
    </section>

    <?php include "rodape.php"; ?>

    <script>
        const AERONAVES = <?= json_encode($aeronaves, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const TOTAL_COMPARATIVO = <?= $totalComparativo ?>;
        const CHAVE_ESTADO = "calculadoraVoosEstado";
        const METAS_HORAS = [500, 1000, 1500, 2000];
        const PRECO_COMBUSTIVEL = { jeta: 5.96, avgas: 10.70, litrosPorGalao: 3.785411784 };
        const CORES_COMPARATIVO = ["#1d4ed8", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#0d9488"];
        const MESES_ABREV = <?= json_encode($nomesMeses) ?>;
        const TODOS_OS_MESES = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

        let rotas = [];
        let graficoMeses = null;
        let graficoComparativo = null;
        let mapa = null;
        let camadaRotas = null;
        let sequenciaMapa = 0;
        const cacheAerodromos = {};
        const dadosGrafico = { horas: new Array(12).fill(0), voos: new Array(12).fill(0), nm: new Array(12).fill(0) };

        const $ = id => document.getElementById(id);
        const formatarNumero = (valor, casas = 0) => Number(valor).toLocaleString("pt-BR", { minimumFractionDigits: casas, maximumFractionDigits: casas });
        const formatarMoeda = valor => "R$ " + formatarNumero(valor);
        const escapeHtml = texto => String(texto).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

        function formatarTempo(horasDecimais) {
            const totalMinutos = Math.round(horasDecimais * 60);
            return Math.floor(totalMinutos / 60) + "h " + String(totalMinutos % 60).padStart(2, "0") + "m";
        }

        function formatarPrazo(anosDecimais) {
            const totalMeses = Math.round(anosDecimais * 12);
            const anos = Math.floor(totalMeses / 12);
            const meses = totalMeses % 12;
            if (totalMeses === 0) return "< 1 mês";
            const partes = [];
            if (anos > 0) partes.push(anos + (anos > 1 ? " anos" : " ano"));
            if (meses > 0) partes.push(meses + (meses > 1 ? " meses" : " mês"));
            return partes.join(" e ");
        }

        function mostrarMensagem(texto, erro = true) {
            const mensagem = $("calcMensagem");
            mensagem.textContent = texto;
            mensagem.classList.toggle("calc-mensagem-erro", erro && texto !== "");
        }

        function margemAtual() {
            const marcado = document.querySelector('input[name="calcMargem"]:checked');
            return 1 + ((parseFloat(marcado ? marcado.value : 0) || 0) / 100);
        }

        function frequenciaAtual() {
            const marcado = document.querySelector('input[name="calcFrequencia"]:checked');
            return marcado ? marcado.value : "todos";
        }

        // ---------- Estado (localStorage) ----------
        function coletarEstado() {
            const comp = {};
            for (let i = 1; i <= TOTAL_COMPARATIVO; i++) comp["ac" + i] = $("calcComp" + i).value;
            return {
                rotas: rotas.map(({ origem, destino, distancia, altOrigem, altDestino, meses, qtdMes, idaVolta }) => ({ origem, destino, distancia, altOrigem, altDestino, meses, qtdMes, idaVolta })),
                form: {
                    aeronave_global: $("calcAeronave").value,
                    origem: $("calcOrigem").value,
                    destino: $("calcDestino").value,
                    distancia: $("calcDistancia").value,
                    ida_volta: $("calcIdaVolta").checked,
                    frequencia: frequenciaAtual(),
                    qtd_mes: $("calcQuantidadeMes").value,
                    meses: [...document.querySelectorAll(".calc-mes:checked")].map(item => item.value),
                    margem: String((margemAtual() - 1) * 100)
                },
                comp
            };
        }

        function salvarEstado() {
            try { localStorage.setItem(CHAVE_ESTADO, JSON.stringify(coletarEstado())); } catch (erro) { /* armazenamento indisponível */ }
        }

        function rotaValida(rota) {
            return rota && /^[A-Z]{4}$/.test(String(rota.origem)) && /^[A-Z]{4}$/.test(String(rota.destino))
                && Number(rota.distancia) > 0 && Array.isArray(rota.meses) && rota.meses.length > 0 && Number(rota.qtdMes) > 0;
        }

        // Dados antigos guardavam ida e volta como duas rotas seguidas; junta de volta em uma só.
        function unirIdaEVolta(lista) {
            const unidas = [];
            lista.forEach(rota => {
                const anterior = unidas[unidas.length - 1];
                const ehVolta = anterior && !anterior.idaVolta && !rota.idaVolta
                    && anterior.origem === rota.destino && anterior.destino === rota.origem
                    && anterior.distancia === rota.distancia && anterior.qtdMes === rota.qtdMes
                    && anterior.meses.slice().sort().join() === rota.meses.slice().sort().join();
                if (ehVolta) anterior.idaVolta = true;
                else unidas.push(rota);
            });
            return unidas;
        }

        function aplicarEstado(estado) {
            rotas = unirIdaEVolta((Array.isArray(estado.rotas) ? estado.rotas : []).filter(rotaValida).map(rota => ({
                origem: String(rota.origem), destino: String(rota.destino),
                distancia: Number(rota.distancia), altOrigem: Number(rota.altOrigem) || 0, altDestino: Number(rota.altDestino) || 0,
                meses: rota.meses.map(Number).filter(mes => mes >= 1 && mes <= 12), qtdMes: Math.max(1, parseInt(rota.qtdMes, 10) || 1),
                idaVolta: !!rota.idaVolta
            })));
            const form = estado.form || {};
            if (form.aeronave_global && AERONAVES[form.aeronave_global]) $("calcAeronave").value = form.aeronave_global;
            $("calcOrigem").value = form.origem || "";
            $("calcDestino").value = form.destino || "";
            $("calcDistancia").value = form.distancia || "";
            if (form.ida_volta !== undefined) $("calcIdaVolta").checked = !!form.ida_volta;
            const frequencia = document.querySelector(`input[name="calcFrequencia"][value="${form.frequencia === "especificos" ? "especificos" : "todos"}"]`);
            if (frequencia) frequencia.checked = true;
            if (form.qtd_mes) $("calcQuantidadeMes").value = form.qtd_mes;
            const mesesMarcados = Array.isArray(form.meses) ? form.meses.map(String) : [];
            document.querySelectorAll(".calc-mes").forEach(item => { item.checked = mesesMarcados.includes(item.value); });
            const margem = document.querySelector(`input[name="calcMargem"][value="${form.margem}"]`);
            if (margem) margem.checked = true;
            const comp = estado.comp || {};
            for (let i = 1; i <= TOTAL_COMPARATIVO; i++) {
                if (comp["ac" + i] && AERONAVES[comp["ac" + i]]) $("calcComp" + i).value = comp["ac" + i];
            }
            alternarMeses();
        }

        function carregarEstado() {
            let salvo = null;
            try { salvo = localStorage.getItem(CHAVE_ESTADO); } catch (erro) { /* armazenamento indisponível */ }
            if (!salvo) return;
            try {
                aplicarEstado(JSON.parse(salvo));
            } catch (erro) {
                console.error("Estado salvo inválido", erro);
            }
        }

        function exportarDados() {
            const estado = coletarEstado();
            if (!estado.rotas.length) return mostrarMensagem("Nenhuma rota para exportar.");
            const link = document.createElement("a");
            link.href = URL.createObjectURL(new Blob([JSON.stringify(estado)], { type: "application/json" }));
            link.download = "voos_export.json";
            link.click();
            URL.revokeObjectURL(link.href);
        }

        function importarDados(evento) {
            const arquivo = evento.target.files[0];
            if (!arquivo) return;
            const leitor = new FileReader();
            leitor.onload = () => {
                try {
                    const dados = JSON.parse(leitor.result);
                    if (!dados || !Array.isArray(dados.rotas)) throw new Error("formato");
                    aplicarEstado(dados);
                    atualizarTudo();
                    mostrarMensagem(rotas.length + " rota(s) importada(s).", false);
                } catch (erro) {
                    mostrarMensagem("Arquivo inválido. Use um .json exportado por esta calculadora.");
                }
                evento.target.value = "";
            };
            leitor.readAsText(arquivo);
        }

        // ---------- Aeródromos ----------
        async function obterAerodromo(oaci) {
            if (!/^[A-Z]{4}$/.test(oaci)) return null;
            if (cacheAerodromos[oaci]) return cacheAerodromos[oaci];
            try {
                const resposta = await fetch("calcular_distancia.php?oaci=" + encodeURIComponent(oaci));
                const dados = await resposta.json();
                if (dados.encontrado && dados.aerodromo) return (cacheAerodromos[oaci] = dados.aerodromo);
            } catch (erro) {
                console.error("Erro ao obter aeródromo", erro);
            }
            return null;
        }

        async function preencherDistancia() {
            const origem = $("calcOrigem").value.trim().toUpperCase();
            const destino = $("calcDestino").value.trim().toUpperCase();
            if (!/^[A-Z]{4}$/.test(origem) || !/^[A-Z]{4}$/.test(destino)) return;
            try {
                const resposta = await fetch("calcular_distancia.php?origem=" + encodeURIComponent(origem) + "&destino=" + encodeURIComponent(destino));
                const dados = await resposta.json();
                if (dados.origem) cacheAerodromos[origem] = dados.origem;
                if (dados.destino) cacheAerodromos[destino] = dados.destino;
                if (dados.distancia_nm) {
                    $("calcDistancia").value = dados.distancia_nm;
                    mostrarMensagem("");
                    salvarEstado();
                } else {
                    mostrarMensagem("Aeródromo não encontrado: informe a distância manualmente.");
                }
            } catch (erro) {
                console.error("Erro ao buscar distância", erro);
            }
        }

        // ---------- Cálculo ----------
        // Subida (razão de subida) + cruzeiro + descida (5x a velocidade de cruzeiro em ft/min); consumo 120% na subida e 80% na descida.
        function calcularRota(distancia, aeronave, altOrigem, altDestino) {
            const vazio = { tempoHoras: 0, galoesVoo: 0, custoVoo: 0 };
            if (!aeronave || !(distancia > 0) || !(aeronave.velocidadeCruzeiro > 0)) return vazio;

            const velCruzeiro = aeronave.velocidadeCruzeiro;
            const consumoGph = aeronave.consumoGph;
            const velSubida = aeronave.velocidadeSubida > 0 ? aeronave.velocidadeSubida : velCruzeiro * 0.85;
            const razaoSubida = aeronave.razaoSubida > 0 ? aeronave.razaoSubida : 700;
            const razaoDescida = velCruzeiro * 5;
            const altCruzeiro = aeronave.altitudeCruzeiro > 0 ? aeronave.altitudeCruzeiro : 8500;

            let distSubida = (Math.max(0, altCruzeiro - altOrigem) / razaoSubida / 60) * velSubida;
            let distDescida = (Math.max(0, altCruzeiro - altDestino) / razaoDescida / 60) * velCruzeiro;
            let distCruzeiro = distancia - distSubida - distDescida;
            let tempoCruzeiro = 0;

            if (distCruzeiro < 0) {
                // Rota curta: não alcança a altitude de cruzeiro; limita a altitude atingida.
                const fatorSubida = velSubida / (60 * razaoSubida);
                const fatorDescida = velCruzeiro / (60 * razaoDescida);
                const menorAlt = Math.min(altOrigem, altDestino);
                const ganhoMaximo = Math.max(0, (distancia + altOrigem * fatorSubida + altDestino * fatorDescida) / (fatorSubida + fatorDescida) - menorAlt);
                const altAtingida = Math.round(menorAlt + ganhoMaximo);
                distSubida = (Math.max(0, altAtingida - altOrigem) / razaoSubida / 60) * velSubida;
                distDescida = Math.max(0, distancia - distSubida);
            } else {
                tempoCruzeiro = (distCruzeiro / velCruzeiro) * 60;
            }

            const tempoSubida = (distSubida / velSubida) * 60;
            const tempoDescida = (distDescida / velCruzeiro) * 60;
            const galoes = (tempoSubida / 60) * consumoGph * 1.2 + (tempoCruzeiro / 60) * consumoGph + (tempoDescida / 60) * consumoGph * 0.8;

            const combustivel = aeronave.tipoCombustivel.toUpperCase();
            let precoLitro = velCruzeiro > 250 ? PRECO_COMBUSTIVEL.jeta : PRECO_COMBUSTIVEL.avgas;
            if (combustivel.includes("JET")) precoLitro = PRECO_COMBUSTIVEL.jeta;
            else if (combustivel.includes("AVGAS") || combustivel.includes("GASOLINA")) precoLitro = PRECO_COMBUSTIVEL.avgas;

            return {
                tempoHoras: (tempoSubida + tempoCruzeiro + tempoDescida) / 60,
                galoesVoo: galoes,
                custoVoo: galoes * PRECO_COMBUSTIVEL.litrosPorGalao * precoLitro
            };
        }

        // ---------- Rotas ----------
        function alternarMeses() {
            $("calcMesesGrupo").classList.toggle("oculto", frequenciaAtual() !== "especificos");
        }

        async function adicionarRota() {
            const origem = $("calcOrigem").value.trim().toUpperCase();
            const destino = $("calcDestino").value.trim().toUpperCase();
            const distancia = parseFloat($("calcDistancia").value) || 0;
            const qtdMes = parseInt($("calcQuantidadeMes").value, 10) || 0;

            if (!/^[A-Z]{4}$/.test(origem) || !/^[A-Z]{4}$/.test(destino)) return mostrarMensagem("Informe origem e destino com 4 letras (código OACI).");
            if (distancia <= 0) return mostrarMensagem("Informe a distância ou aguarde o preenchimento automático.");
            if (qtdMes < 1) return mostrarMensagem("Informe quantas vezes por mês a rota é voada.");

            const meses = frequenciaAtual() === "todos" ? [...TODOS_OS_MESES] : [...document.querySelectorAll(".calc-mes:checked")].map(item => parseInt(item.value, 10));
            if (!meses.length) return mostrarMensagem("Selecione pelo menos um mês.");

            const [aeroOrigem, aeroDestino] = await Promise.all([obterAerodromo(origem), obterAerodromo(destino)]);
            const altOrigem = aeroOrigem ? aeroOrigem.altitude_ft : 0;
            const altDestino = aeroDestino ? aeroDestino.altitude_ft : 0;

            rotas.push({ origem, destino, distancia, altOrigem, altDestino, meses, qtdMes, idaVolta: $("calcIdaVolta").checked });
            mostrarMensagem("");
            atualizarTudo();
        }

        function removerRota(indice) {
            rotas.splice(indice, 1);
            atualizarTudo();
        }

        // Soma ida (e volta, se houver) de uma ocorrência da rota; a volta troca as altitudes de origem e destino.
        function calcularOcorrencia(rota, aeronave, margem) {
            const trechos = [calcularRota(rota.distancia * margem, aeronave, rota.altOrigem, rota.altDestino)];
            if (rota.idaVolta) trechos.push(calcularRota(rota.distancia * margem, aeronave, rota.altDestino, rota.altOrigem));
            return {
                trechos: trechos.length,
                tempoHoras: trechos.reduce((soma, t) => soma + t.tempoHoras, 0),
                galoesVoo: trechos.reduce((soma, t) => soma + t.galoesVoo, 0),
                custoVoo: trechos.reduce((soma, t) => soma + t.custoVoo, 0)
            };
        }

        function nomeCidade(oaci) {
            const aerodromo = cacheAerodromos[oaci];
            return aerodromo && aerodromo.municipio ? aerodromo.municipio : "";
        }

        async function completarAerodromos() {
            const faltando = [...new Set(rotas.flatMap(rota => [rota.origem, rota.destino]))].filter(oaci => !cacheAerodromos[oaci]);
            if (!faltando.length) return;
            await Promise.all(faltando.map(obterAerodromo));
            atualizarProjecao();
        }

        function atualizarTudo() {
            atualizarProjecao();
            completarAerodromos();
            atualizarComparativo();
            atualizarMapa();
            salvarEstado();
        }

        // ---------- Projeção ----------
        function atualizarProjecao() {
            const corpo = document.querySelector("#calcTabelaRotas tbody");
            corpo.innerHTML = "";
            const aeronave = AERONAVES[$("calcAeronave").value] || null;
            const margem = margemAtual();
            let totalNm = 0, totalHoras = 0, totalCusto = 0, totalVoos = 0, totalGaloes = 0;
            Object.values(dadosGrafico).forEach(lista => lista.fill(0));

            rotas.forEach((rota, indice) => {
                const calculo = calcularOcorrencia(rota, aeronave, margem);
                const ocorrenciasAno = rota.meses.length * rota.qtdMes;
                const voosAno = ocorrenciasAno * calculo.trechos;
                const cidadeOrigem = nomeCidade(rota.origem);
                const cidadeDestino = nomeCidade(rota.destino);
                const linha = document.createElement("tr");
                linha.innerHTML = `
                    <td>
                        <span class="badge-rota">${escapeHtml(rota.origem)} ${rota.idaVolta ? "&harr;" : "&rarr;"} ${escapeHtml(rota.destino)}</span>
                        ${cidadeOrigem && cidadeDestino ? `<span class="calc-cidades">${escapeHtml(cidadeOrigem)} - ${escapeHtml(cidadeDestino)}</span>` : ""}
                    </td>
                    <td>${formatarNumero(rota.distancia)}</td>
                    <td>${formatarNumero(rota.distancia * calculo.trechos)}</td>
                    <td>${aeronave ? formatarTempo(calculo.tempoHoras) : "--"}</td>
                    <td>${rota.qtdMes * calculo.trechos} &bull; ${voosAno}</td>
                    <td>${rota.meses.length === 12 ? "Todos" : rota.meses.slice().sort((a, b) => a - b).map(mes => MESES_ABREV[mes - 1]).join(", ")}</td>
                    <td>${aeronave ? formatarNumero(calculo.galoesVoo, 1) : "--"}</td>
                    <td>${aeronave ? formatarMoeda(calculo.custoVoo) : "--"}</td>
                    <td><button type="button" class="btn-remover-rota" aria-label="Remover rota ${escapeHtml(rota.origem)} para ${escapeHtml(rota.destino)}">&times;</button></td>`;
                linha.querySelector(".btn-remover-rota").addEventListener("click", () => removerRota(indice));
                corpo.appendChild(linha);

                totalNm += rota.distancia * margem * voosAno;
                totalHoras += calculo.tempoHoras * ocorrenciasAno;
                totalCusto += calculo.custoVoo * ocorrenciasAno;
                totalGaloes += calculo.galoesVoo * ocorrenciasAno;
                totalVoos += voosAno;
                rota.meses.forEach(mes => {
                    dadosGrafico.horas[mes - 1] += calculo.tempoHoras * rota.qtdMes;
                    dadosGrafico.voos[mes - 1] += rota.qtdMes * calculo.trechos;
                    dadosGrafico.nm[mes - 1] += rota.distancia * margem * rota.qtdMes * calculo.trechos;
                });
            });

            $("calcVazio").classList.toggle("oculto", rotas.length > 0);
            $("calcTotalHoras").textContent = formatarTempo(totalHoras);
            $("calcTotalNm").textContent = formatarNumero(totalNm) + " NM";
            $("calcTotalVoos").textContent = formatarNumero(totalVoos);
            $("calcMediaNm").textContent = formatarNumero(totalVoos > 0 ? totalNm / totalVoos : 0) + " NM";
            $("calcTotalGaloes").textContent = formatarNumero(totalGaloes) + " gal";
            $("calcTotalCusto").textContent = formatarMoeda(totalCusto);

            METAS_HORAS.forEach(meta => {
                $("calcTempo" + meta).textContent = totalHoras > 0 ? formatarPrazo(meta / totalHoras) : "--";
                $("calcCusto" + meta).textContent = totalHoras > 0 ? formatarMoeda((totalCusto / totalHoras) * meta) : "--";
            });

            if (!graficoMeses) inicializarGrafico();
            atualizarGrafico();
        }

        function inicializarGrafico() {
            graficoMeses = new Chart($("calcGraficoMeses"), {
                type: "bar",
                data: { labels: MESES_ABREV, datasets: [{ label: "", data: dadosGrafico.horas, backgroundColor: "#1d4ed8", borderRadius: 6 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "top" } }, scales: { y: { beginAtZero: true } } }
            });
        }

        function atualizarGrafico() {
            const marcado = document.querySelector('input[name="calcFiltroGrafico"]:checked');
            const filtro = marcado ? marcado.value : "horas";
            const rotulos = { horas: "Horas de voo projetadas", voos: "Quantidade de voos projetada", nm: "Distância projetada (NM)" };
            graficoMeses.data.datasets[0].data = dadosGrafico[filtro];
            graficoMeses.data.datasets[0].label = rotulos[filtro];
            graficoMeses.update();
        }

        // ---------- Mapa ----------
        function inicializarMapa() {
            const camadaMapa = L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png", {
                maxZoom: 19, subdomains: "abcd",
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
            });
            const camadaSatelite = L.tileLayer("https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", {
                maxZoom: 18, attribution: "Tiles &copy; Esri &mdash; Source: Esri, USDA, USGS, GeoEye, IGN, and the GIS User Community"
            });
            mapa = L.map("calcMapa", { center: [-15.7801, -47.9292], zoom: 4, layers: [camadaSatelite] });
            L.control.layers({ "Mapa cartográfico": camadaMapa, "Satélite": camadaSatelite }, null, { position: "topright" }).addTo(mapa);
            L.control.scale({ imperial: true, metric: true, position: "bottomleft" }).addTo(mapa);
            camadaRotas = L.layerGroup().addTo(mapa);
        }

        async function atualizarMapa() {
            const sequencia = ++sequenciaMapa;
            const desenhadas = new Set();
            const trechos = [];
            for (const rota of rotas) {
                const chave = [rota.origem, rota.destino].sort().join("-");
                if (desenhadas.has(chave)) continue;
                desenhadas.add(chave);
                const [origem, destino] = await Promise.all([obterAerodromo(rota.origem), obterAerodromo(rota.destino)]);
                if (origem && destino && origem.lat && destino.lat) trechos.push({ rota, origem, destino });
            }
            if (sequencia !== sequenciaMapa) return; // chamada mais recente assumiu

            camadaRotas.clearLayers();
            const limites = [];
            const opcaoRotulo = { permanent: true, direction: "top", offset: [0, -5], className: "calc-mapa-rotulo" };
            trechos.forEach(({ rota, origem, destino }) => {
                const p1 = [origem.lat, origem.lon];
                const p2 = [destino.lat, destino.lon];
                limites.push(p1, p2);
                L.polyline([p1, p2], { color: "#38bdf8", weight: 3, opacity: 0.95 }).addTo(camadaRotas);
                L.polyline([p1, p2], { color: "#ffffff", weight: 2, opacity: 0.9, dashArray: "8, 14" }).addTo(camadaRotas);
                L.circleMarker(p1, { radius: 6, color: "#ffffff", weight: 2, fillColor: "#1d4ed8", fillOpacity: 1 }).bindTooltip(rota.origem, opcaoRotulo).addTo(camadaRotas);
                L.circleMarker(p2, { radius: 6, color: "#ffffff", weight: 2, fillColor: "#10b981", fillOpacity: 1 }).bindTooltip(rota.destino, opcaoRotulo).addTo(camadaRotas);
            });
            if (limites.length) mapa.fitBounds(L.latLngBounds(limites), { padding: [30, 30] });
        }

        // ---------- Comparativo ----------
        function atualizarTabelaComparativo(avioes) {
            $("calcTabelaComparativoContainer").classList.toggle("oculto", !avioes.length);
            $("calcComparativoVazio").classList.toggle("oculto", avioes.length > 0);
            const cabecalho = document.querySelector("#calcTabelaComparativo thead");
            const corpo = document.querySelector("#calcTabelaComparativo tbody");
            cabecalho.innerHTML = "";
            corpo.innerHTML = "";
            if (!avioes.length) return;

            // [rótulo, texto da célula, valor comparável]. Menor é melhor; todos iguais = sem destaque.
            // Nas marcas de horas o valor é horasAno: quem acumula mais horas por ano chega antes à marca, o que é pior (desgaste), então fica vermelho.
            const emBadge = (texto, classe) => classe ? `<span class="calc-badge ${classe}">${texto}</span>` : texto;
            const linhas = [
                ["Horas por ano", (aviao, c) => emBadge(formatarTempo(aviao.horasAno), c), aviao => aviao.horasAno],
                ["Distância por ano", (aviao, c) => emBadge(formatarNumero(aviao.nmAno) + " NM", c), aviao => aviao.nmAno],
                ["Velocidade média", (aviao, c) => emBadge(formatarNumero(aviao.nmAno / aviao.horasAno) + " kt", c), aviao => -aviao.nmAno / aviao.horasAno],
                ["Voos por ano", (aviao, c) => emBadge(formatarNumero(aviao.voosAno), c), aviao => aviao.voosAno],
                ["Média por voo", (aviao, c) => emBadge(formatarNumero(aviao.nmAno / aviao.voosAno) + " NM", c), aviao => aviao.nmAno / aviao.voosAno],
                ["Consumo por ano", (aviao, c) => emBadge(formatarNumero(aviao.galoesAno) + " gal", c), aviao => aviao.galoesAno],
                ["Combustível por ano", (aviao, c) => emBadge(formatarMoeda(aviao.custoAno), c), aviao => aviao.custoAno],
                ["Combustível por hora", (aviao, c) => emBadge(formatarMoeda(aviao.custoAno / aviao.horasAno), c), aviao => aviao.custoAno / aviao.horasAno],
                ...METAS_HORAS.map(meta => ["Marca de " + formatarNumero(meta) + " h", (aviao, c) =>
                    `<strong>${emBadge(formatarPrazo(meta / aviao.horasAno), c)}</strong><span class="calc-sub-celula">${formatarMoeda((aviao.custoAno / aviao.horasAno) * meta)}</span>`,
                    aviao => aviao.horasAno])
            ];

            // Classe de destaque por aeronave: menor valor = melhor (verde), maior = pior (vermelho).
            const classificar = valores => {
                const menor = Math.min(...valores);
                const maior = Math.max(...valores);
                if (maior - menor <= Math.abs(maior) * 1e-6) return valores.map(() => "");
                return valores.map(valor => valor - menor <= Math.abs(menor) * 1e-6 ? "calc-badge-melhor" : maior - valor <= Math.abs(maior) * 1e-6 ? "calc-badge-pior" : "");
            };

            const tr = document.createElement("tr");
            tr.innerHTML = "<th>Indicador</th>" + avioes.map(aviao =>
                `<th><span class="calc-cor" style="background:${aviao.cor}"></span>${escapeHtml(aviao.nome)}</th>`).join("");
            cabecalho.appendChild(tr);
            linhas.forEach(([rotulo, texto, valor]) => {
                const classes = avioes.length > 1 ? classificar(avioes.map(valor)) : avioes.map(() => "");
                const linha = document.createElement("tr");
                linha.innerHTML = `<th scope="row">${rotulo}</th>` + avioes.map((aviao, i) => `<td>${texto(aviao, classes[i])}</td>`).join("");
                corpo.appendChild(linha);
            });
        }

        function atualizarComparativo() {
            const margem = margemAtual();
            const legenda = $("calcPillsComparativo");
            legenda.innerHTML = "";
            const avioes = [];

            if (rotas.length) {
                for (let i = 1; i <= TOTAL_COMPARATIVO; i++) {
                    const aeronave = AERONAVES[$("calcComp" + i).value];
                    if (!aeronave) continue;
                    let horasAno = 0, custoAno = 0, galoesAno = 0, nmAno = 0, voosAno = 0;
                    rotas.forEach(rota => {
                        const calculo = calcularOcorrencia(rota, aeronave, margem);
                        const ocorrenciasAno = rota.meses.length * rota.qtdMes;
                        horasAno += calculo.tempoHoras * ocorrenciasAno;
                        custoAno += calculo.custoVoo * ocorrenciasAno;
                        galoesAno += calculo.galoesVoo * ocorrenciasAno;
                        nmAno += rota.distancia * margem * calculo.trechos * ocorrenciasAno;
                        voosAno += calculo.trechos * ocorrenciasAno;
                    });
                    if (horasAno > 0) avioes.push({ nome: aeronave.modelo, horasAno, custoAno, galoesAno, nmAno, voosAno, cor: CORES_COMPARATIVO[i - 1] });
                }
            }

            atualizarTabelaComparativo(avioes);
            if (!avioes.length) {
                if (graficoComparativo) { graficoComparativo.destroy(); graficoComparativo = null; }
                return;
            }

            let maxAnos = 0;
            avioes.forEach(aviao => {
                const anos = 2000 / aviao.horasAno;
                maxAnos = Math.max(maxAnos, anos);
                const pill = document.createElement("span");
                pill.className = "calc-legenda-item";
                pill.style.backgroundColor = aviao.cor;
                pill.textContent = `${aviao.nome}: ${formatarMoeda(aviao.custoAno / aviao.horasAno)}/h • ${formatarPrazo(anos)} até 2.000 h`;
                legenda.appendChild(pill);
            });
            maxAnos = Math.min(30, Math.max(1, Math.ceil(maxAnos)));

            const rotulos = Array.from({ length: maxAnos }, (_, i) => "Ano " + (i + 1));
            const conjuntos = avioes.map(aviao => ({
                label: aviao.nome,
                data: rotulos.map((_, i) => Math.min(2000, aviao.horasAno * (i + 1))),
                borderColor: aviao.cor, backgroundColor: aviao.cor, tension: 0.1, fill: false, borderWidth: 2
            }));
            METAS_HORAS.forEach(meta => conjuntos.push({
                label: "Marca de " + formatarNumero(meta) + " h", data: new Array(maxAnos).fill(meta),
                borderColor: "rgba(239, 68, 68, 0.5)", borderDash: [4, 4], pointRadius: 0, fill: false, borderWidth: 1
            }));

            if (graficoComparativo) {
                graficoComparativo.data.labels = rotulos;
                graficoComparativo.data.datasets = conjuntos;
                graficoComparativo.avioes = avioes;
                graficoComparativo.maxAnos = maxAnos;
                graficoComparativo.update();
                return;
            }

            const linhasVerticais = {
                id: "linhasVerticais",
                afterDraw: grafico => {
                    if (!grafico.avioes) return;
                    const { ctx, scales: { x: escalaX, y: escalaY } } = grafico;
                    grafico.avioes.forEach(aviao => METAS_HORAS.forEach(meta => {
                        const anos = meta / aviao.horasAno;
                        if (anos > grafico.maxAnos) return;
                        const posicaoX = escalaX.getPixelForValue(anos - 1);
                        ctx.save();
                        ctx.beginPath();
                        ctx.setLineDash([4, 4]);
                        ctx.moveTo(posicaoX, escalaY.getPixelForValue(meta));
                        ctx.lineTo(posicaoX, escalaY.bottom);
                        ctx.strokeStyle = aviao.cor;
                        ctx.lineWidth = 1.5;
                        ctx.stroke();
                        ctx.restore();
                    }));
                }
            };
            graficoComparativo = new Chart($("calcGraficoComparativo"), {
                type: "line",
                data: { labels: rotulos, datasets: conjuntos },
                plugins: [linhasVerticais],
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: "top" } },
                    scales: { y: { beginAtZero: true, max: 2050, ticks: { stepSize: 250 } } }
                }
            });
            graficoComparativo.avioes = avioes;
            graficoComparativo.maxAnos = maxAnos;
        }

        // ---------- Inicialização ----------
        document.addEventListener("DOMContentLoaded", () => {
            inicializarMapa();
            carregarEstado();
            atualizarTudo();

            $("calcBotaoAdicionar").addEventListener("click", adicionarRota);
            $("calcOrigem").addEventListener("blur", preencherDistancia);
            $("calcDestino").addEventListener("blur", preencherDistancia);
            $("calcBotaoExportar").addEventListener("click", exportarDados);
            $("calcBotaoImportar").addEventListener("click", () => $("calcArquivoImportar").click());
            $("calcArquivoImportar").addEventListener("change", importarDados);
            $("calcBotaoLimpar").addEventListener("click", () => {
                if (rotas.length && confirm("Remover todas as rotas?")) { rotas = []; atualizarTudo(); }
            });
            document.querySelectorAll('input[name="calcFrequencia"]').forEach(item => item.addEventListener("change", () => { alternarMeses(); salvarEstado(); }));
            document.querySelectorAll('input[name="calcMargem"], #calcAeronave').forEach(item => item.addEventListener("change", atualizarTudo));
            document.querySelectorAll(".calc-comp-select").forEach(item => item.addEventListener("change", () => { atualizarComparativo(); salvarEstado(); }));
            document.querySelectorAll('input[name="calcFiltroGrafico"]').forEach(item => item.addEventListener("change", atualizarGrafico));
            document.querySelectorAll("#calcOrigem, #calcDestino, #calcDistancia, #calcQuantidadeMes, #calcIdaVolta, .calc-mes").forEach(item => item.addEventListener("change", salvarEstado));
        });
    </script>
</body>
</html>
