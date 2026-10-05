<?php
if (!ob_start("ob_gzhandler")) ob_start();
require "auth.php";
$usuarioId = usuarioAtualId();

$aeronaves = [];
$resultado = $conn->query("SELECT fabricante, modelo, ano, velocidade_cruzeiro, capacidade_tanque, consumo_gph, valor, tipo_combustivel FROM aeronaves WHERE " . sqlAeronavesVisiveis($usuarioId));
while ($aeronave = $resultado->fetch_assoc()) {
    $consumoGph = (float) $aeronave["consumo_gph"];
    $aeronave["autonomia_nm"] = $consumoGph > 0 ? round(((float) $aeronave["capacidade_tanque"] / $consumoGph) * (float) $aeronave["velocidade_cruzeiro"]) : 0;
    $aeronaves[] = $aeronave;
}

$porPreco = $aeronaves;
usort($porPreco, static function ($a, $b) {
    $comparacaoPreco = (float) $b["valor"] <=> (float) $a["valor"];
    return $comparacaoPreco !== 0 ? $comparacaoPreco : strcasecmp($a["modelo"], $b["modelo"]);
});

$rotulo = static fn($aeronave) => $aeronave["modelo"] . " | " . $aeronave["ano"];
$labelsPreco = array_map($rotulo, $porPreco);
$valoresPreco = array_map(static fn($aeronave) => (float) $aeronave["valor"], $porPreco);
$valoresVelocidadeNoPreco = array_map(static fn($aeronave) => (float) $aeronave["velocidade_cruzeiro"], $porPreco);
$valoresConsumoNoPreco = array_map(static fn($aeronave) => (float) $aeronave["consumo_gph"], $porPreco);
$valoresAutonomiaNoPreco = array_map(static fn($aeronave) => (float) $aeronave["autonomia_nm"], $porPreco);
$coresGrafico = static fn($aeronaves) => array_map(static fn($aeronave) => $aeronave["tipo_combustivel"] === "JetA" ? "#e3b341" : "#087f73", $aeronaves);
$coresPreco = $coresGrafico($porPreco);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise da Frota | Operações de Frota</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>
<body>
    <!-- Topo da Aplicação -->
    <header class="topo">
        <div class="marca">
            <div class="logo-aplicacao" role="img" aria-label="Logo Registro de Voos"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg></div>
            <div>
                <h1>Análise da Frota</h1>
                <p>Comparativos de performance, custos e especificações das aeronaves</p>
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
        <a href="analise.php" class="ativo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            <span>Comparar<br>aeronave</span>
        </a>
        <a href="aerodromos.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
            <span>Consulta de<br>aeródromos</span>
        </a>
        <a href="calculadora_voo.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Calculadora<br>de horas</span>
        </a>
    </nav>
    <section class="painel-grafico">
        <h2>Valor, velocidade e consumo das aeronaves</h2>
        <button type="button" id="botaoLinhaCorte" class="botao-linha-corte" aria-expanded="false" aria-controls="camposReferencia">Linha de corte</button>
        <button type="button" id="botaoFiltroAeronaves" class="botao-linha-corte" aria-expanded="false" aria-controls="filtroAeronaves">Filtro de aeronaves</button>
        <div id="filtroAeronaves" class="filtro-aeronaves oculto">
            <?php foreach ($porPreco as $indice => $aeronave): ?><label><input type="checkbox" class="filtro-aeronave" data-indice="<?= $indice ?>" checked><?= htmlspecialchars($aeronave["modelo"] . " | " . $aeronave["ano"]) ?></label><?php endforeach; ?>
        </div>
        <div id="camposReferencia" class="campos-referencia oculto">
            <label>Valor ($ milhões)<input class="valor-referencia" data-eixo="xPreco" type="number" min="0" step="0.1" placeholder="Ex.: 3,5"></label>
            <label>Velocidade (kt)<input class="valor-referencia" data-eixo="xVelocidade" type="number" min="0" step="1" placeholder="Ex.: 300"></label>
            <label>Consumo (GPH)<input class="valor-referencia" data-eixo="xConsumo" type="number" min="0" step="1" placeholder="Ex.: 50"></label>
            <label>Autonomia (NM)<input class="valor-referencia" data-eixo="xAutonomia" type="number" min="0" step="1" placeholder="Ex.: 1000"></label>
        </div>
        <?php if ($aeronaves): ?><div id="graficoContainer" class="grafico-container"><div class="controles-modelos"><?php foreach ($porPreco as $indice => $aeronave): ?><label class="controle-modelo" data-indice="<?= $indice ?>"><input type="checkbox"><span><?= htmlspecialchars($aeronave["modelo"] . " | " . $aeronave["ano"]) ?></span></label><?php endforeach; ?></div><canvas id="graficoPrecoLista"></canvas></div><?php else: ?><p class="vazio">Cadastre uma aeronave para visualizar o gráfico.</p><?php endif; ?>
    </section>
    <div class="legenda-combustivel"><span><i class="amostra-cor jet-a"></i>JetA</span><span><i class="amostra-cor avgas"></i>Avgas</span></div>

    <?php if ($aeronaves): ?>
    <script>
        Chart.register(ChartDataLabels);
        const valoresReferencia = {};
        const modelosSelecionados = new Set();
        const aeronavesVisiveis = new Set(<?= json_encode(array_keys($porPreco)) ?>);
        const coresReferencia = { xPreco: "#e3b341", xVelocidade: "#738D70", xConsumo: "#d87941", xAutonomia: "#5b8fa8" };
        const destaqueModelos = {
            id: "destaqueModelos",
            beforeDatasetsDraw: grafico => {
                const escala = grafico.scales.y;
                const contexto = grafico.ctx;
                modelosSelecionados.forEach(indice => {
                    if (!aeronavesVisiveis.has(indice)) return;
                    const centro = escala.getPixelForTick([...aeronavesVisiveis].indexOf(indice));
                    const indiceVisivel = [...aeronavesVisiveis].indexOf(indice);
                    const proximoCentro = escala.getPixelForTick(indiceVisivel + 1);
                    const altura = proximoCentro ? proximoCentro - centro : (grafico.chartArea.bottom - grafico.chartArea.top) / grafico.data.labels.length;
                    contexto.save();
                    contexto.fillStyle = "rgba(255, 224, 102, 0.28)";
                    contexto.fillRect(grafico.chartArea.left, centro - Math.abs(altura) / 2, grafico.chartArea.right - grafico.chartArea.left, Math.abs(altura));
                    contexto.restore();
                });
            },
            afterDraw: grafico => {
                const escala = grafico.scales.y;
                document.querySelectorAll(".controle-modelo").forEach(controle => {
                    const indice = Number(controle.dataset.indice);
                    const posicao = [...aeronavesVisiveis].indexOf(indice);
                    controle.style.display = posicao < 0 ? "none" : "flex";
                    if (posicao >= 0) controle.style.top = escala.getPixelForTick(posicao) + "px";
                });
            }
        };
        Chart.register(destaqueModelos);
        const linhaReferencia = {
            id: "linhaReferencia",
            afterDraw: grafico => {
                const contexto = grafico.ctx;
                Object.entries(valoresReferencia).forEach(([eixo, valor]) => {
                    if (valor === null || valor < 0) return;
                    const escala = grafico.scales[eixo];
                    const posicao = escala.getPixelForValue(valor);
                    contexto.save();
                    contexto.beginPath();
                    contexto.moveTo(posicao, grafico.chartArea.top);
                    contexto.lineTo(posicao, grafico.chartArea.bottom);
                    contexto.lineWidth = 8;
                    contexto.strokeStyle = "#000000";
                    contexto.stroke();
                    contexto.beginPath();
                    contexto.moveTo(posicao, grafico.chartArea.top);
                    contexto.lineTo(posicao, grafico.chartArea.bottom);
                    contexto.lineWidth = 6;
                    contexto.strokeStyle = coresReferencia[eixo];
                    contexto.stroke();
                    contexto.restore();
                });
            }
        };
        Chart.register(linhaReferencia);
        const configuracaoGraficoPreco = (labels, valoresPreco, valoresVelocidade, valoresConsumo, valoresAutonomia, coresPreco) => new Chart(document.getElementById("graficoPrecoLista"), {
            type: "bar",
            data: { labels, datasets: [
                { label: "Preço ($ milhões)", data: valoresPreco.map(valor => valor / 1000000), backgroundColor: coresPreco, borderColor: "#ffffff", borderWidth: 4, borderRadius: 5, barThickness: 24, xAxisID: "xPreco" },
                { label: "Velocidade (kt)", data: valoresVelocidade, backgroundColor: "#738D70", borderColor: "#ffffff", borderWidth: 4, borderRadius: 5, barThickness: 24, xAxisID: "xVelocidade" },
                { label: "Consumo (GPH)", data: valoresConsumo, backgroundColor: "#d87941", borderColor: "#ffffff", borderWidth: 4, borderRadius: 5, barThickness: 24, xAxisID: "xConsumo" },
                { label: "Autonomia (NM)", data: valoresAutonomia, backgroundColor: "#5b8fa8", borderColor: "#ffffff", borderWidth: 4, borderRadius: 5, barThickness: 24, xAxisID: "xAutonomia" }
            ] },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { left: 175 } },
                plugins: {
                    legend: { display: true },
                    datalabels: { anchor: "end", align: "start", color: "#ffffff", clamp: true, formatter: (valor, contexto) => contexto.dataset.label.startsWith("Preço") ? "$ " + valor.toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + " milhões" : contexto.dataset.label.startsWith("Velocidade") ? Math.round(valor).toLocaleString("pt-BR") + " kt" : contexto.dataset.label.startsWith("Consumo") ? Math.round(valor).toLocaleString("pt-BR") + " GPH" : Math.round(valor).toLocaleString("pt-BR") + " NM" }
                },
                scales: {
                    y: { ticks: { display: false }, grid: { color: "#000000", lineWidth: 2, drawTicks: false } },
                    xPreco: { beginAtZero: true, display: false, position: "bottom", ticks: { callback: value => "$ " + Number(value).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + " milhões" } },
                    xVelocidade: { beginAtZero: true, display: false, position: "top", grid: { drawOnChartArea: false }, ticks: { callback: value => value + " kt" } },
                    xConsumo: { type: "linear", min: 0, beginAtZero: true, display: false, bounds: "ticks", position: "top", offset: false, grid: { drawOnChartArea: false }, ticks: { callback: value => value + " GPH" } },
                    xAutonomia: { type: "linear", min: 0, beginAtZero: true, display: false, bounds: "ticks", position: "top", offset: false, grid: { drawOnChartArea: false }, ticks: { callback: value => Math.round(value).toLocaleString("pt-BR") + " NM" } }
                }
            }
        });
        const configuracaoGrafico = (id, labels, valores, cores, unidade, divisor = 1) => new Chart(document.getElementById(id), {
            type: "bar",
            data: { labels, datasets: [{ data: valores, backgroundColor: cores, borderRadius: 5, barThickness: 34, categoryPercentage: 0.58, barPercentage: 0.78 }] },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: { anchor: "end", align: "start", color: "#ffffff", clamp: true, formatter: valor => unidade + Number(valor / divisor).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) }
                },
                scales: { x: { beginAtZero: true, ticks: { callback: value => unidade + Number(value / divisor).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) } } }
            }
        });
        const dadosGrafico = {
            labels: <?= json_encode($labelsPreco, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            preco: <?= json_encode($valoresPreco) ?>,
            velocidade: <?= json_encode($valoresVelocidadeNoPreco) ?>,
            consumo: <?= json_encode($valoresConsumoNoPreco) ?>,
            autonomia: <?= json_encode($valoresAutonomiaNoPreco) ?>,
            cores: <?= json_encode($coresPreco) ?>
        };
        const grafico = configuracaoGraficoPreco(dadosGrafico.labels, dadosGrafico.preco, dadosGrafico.velocidade, dadosGrafico.consumo, dadosGrafico.autonomia, dadosGrafico.cores);
        const graficoContainer = document.getElementById("graficoContainer");
        const ajustarAlturaGrafico = () => {
            graficoContainer.style.height = Math.max(320, 180 + 100 * aeronavesVisiveis.size) + "px";
            grafico.resize();
        };
        ajustarAlturaGrafico();
        document.querySelectorAll(".filtro-aeronave").forEach(filtro => {
            filtro.addEventListener("change", () => {
                if (filtro.checked) aeronavesVisiveis.add(Number(filtro.dataset.indice));
                else aeronavesVisiveis.delete(Number(filtro.dataset.indice));
                const indices = [...aeronavesVisiveis];
                grafico.data.labels = indices.map(indice => dadosGrafico.labels[indice]);
                grafico.data.datasets[0].data = indices.map(indice => dadosGrafico.preco[indice] / 1000000);
                grafico.data.datasets[0].backgroundColor = indices.map(indice => dadosGrafico.cores[indice]);
                grafico.data.datasets[1].data = indices.map(indice => dadosGrafico.velocidade[indice]);
                grafico.data.datasets[2].data = indices.map(indice => dadosGrafico.consumo[indice]);
                grafico.data.datasets[3].data = indices.map(indice => dadosGrafico.autonomia[indice]);
                ajustarAlturaGrafico();
                grafico.update("none");
            });
        });
        document.querySelectorAll(".valor-referencia").forEach(campo => {
            campo.addEventListener("input", evento => {
                const valor = evento.target.value.replace(",", ".");
                valoresReferencia[campo.dataset.eixo] = valor === "" ? null : Number(valor);
                Chart.getChart("graficoPrecoLista").update("none");
            });
        });
        document.querySelectorAll(".controle-modelo input").forEach((checkbox, indice) => {
            checkbox.addEventListener("change", () => {
                if (checkbox.checked) modelosSelecionados.add(indice);
                else modelosSelecionados.delete(indice);
                Chart.getChart("graficoPrecoLista").update("none");
            });
        });
    </script>
    <?php endif; ?>
    <script>
        const botaoLinhaCorte = document.getElementById("botaoLinhaCorte");
        const camposReferencia = document.getElementById("camposReferencia");
        botaoLinhaCorte.addEventListener("click", () => {
            const visivel = camposReferencia.classList.toggle("oculto");
            botaoLinhaCorte.setAttribute("aria-expanded", String(!visivel));
        });
        const botaoFiltroAeronaves = document.getElementById("botaoFiltroAeronaves");
        const filtroAeronaves = document.getElementById("filtroAeronaves");
        botaoFiltroAeronaves.addEventListener("click", () => {
            const oculto = filtroAeronaves.classList.toggle("oculto");
            botaoFiltroAeronaves.setAttribute("aria-expanded", String(!oculto));
        });
    </script>
    <?php include "rodape.php"; ?>
</body>
</html>
<?php $conn->close(); ?>
