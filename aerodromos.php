<?php
if (!ob_start("ob_gzhandler")) ob_start();
require "auth.php";
usuarioAtualId();
require "aerodromos_dados.php";

$aerodromos = carregarTodosAerodromos();

$busca = trim($_GET["busca"] ?? "");
$uf = trim($_GET["uf"] ?? "");
$tipoFiltro = trim($_GET["tipo"] ?? "");

$ufsDisponiveis = array_unique(array_map(fn($item) => $item["UF"] ?? "", $aerodromos));
sort($ufsDisponiveis);

$resultados = [];
if ($busca !== "" || $uf !== "" || $tipoFiltro !== "") {
    $buscaMaiuscula = mb_strtoupper($busca);
    foreach ($aerodromos as $aerodromo) {
        if ($uf !== "" && ($aerodromo["UF"] ?? "") !== $uf) {
            continue;
        }
        if ($tipoFiltro !== "" && $aerodromo["Tipo"] !== $tipoFiltro) {
            continue;
        }
        if ($buscaMaiuscula !== "") {
            $alvo = mb_strtoupper(($aerodromo["CódigoOACI"] ?? "") . " " . ($aerodromo["Nome"] ?? "") . " " . ($aerodromo["Município"] ?? ""));
            if (!str_contains($alvo, $buscaMaiuscula)) {
                continue;
            }
        }
        $resultados[] = $aerodromo;
    }
}

$aerodromosPorOaci = carregarAerodromosPorOaci();

$origemOaci = strtoupper(trim($_GET["origem"] ?? ""));
$destinoOaci = strtoupper(trim($_GET["destino"] ?? ""));
$distanciaCalculada = null;
$erroDistancia = "";
if ($origemOaci !== "" && $destinoOaci !== "") {
    $aerodromoOrigem = $aerodromosPorOaci[$origemOaci] ?? null;
    $aerodromoDestino = $aerodromosPorOaci[$destinoOaci] ?? null;
    if (!$aerodromoOrigem || !$aerodromoDestino) {
        $erroDistancia = "Código OACI não encontrado na base de dados.";
    } else {
        $distanciaCalculada = distanciaNM(
            (float) str_replace(",", ".", $aerodromoOrigem["LatGeoPoint"]),
            (float) str_replace(",", ".", $aerodromoOrigem["LonGeoPoint"]),
            (float) str_replace(",", ".", $aerodromoDestino["LatGeoPoint"]),
            (float) str_replace(",", ".", $aerodromoDestino["LonGeoPoint"])
        );
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Aeródromos | Operações de Frota</title>
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
                <h1>Consulta de Aeródromos</h1>
                <p>Base de dados nacional de aeródromos públicos e privados</p>
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
        <a href="aerodromos.php" class="ativo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
            <span>Consulta de<br>aeródromos</span>
        </a>
        <a href="calculadora_voo.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            <span>Calculadora<br>de horas</span>
        </a>
    </nav>
    <form method="get">
        <div class="campos">
            <label>Buscar (código, nome ou município)<input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Ex.: SBRB, Plácido de Castro, Rio Branco"></label>
            <label>UF<select name="uf"><option value="">Todas</option><?php foreach ($ufsDisponiveis as $ufDisponivel): if ($ufDisponivel === "") continue; ?><option value="<?= htmlspecialchars($ufDisponivel) ?>" <?= $uf === $ufDisponivel ? "selected" : "" ?>><?= htmlspecialchars($ufDisponivel) ?></option><?php endforeach; ?></select></label>
            <label>Tipo<select name="tipo"><option value="">Todos</option><option value="Público" <?= $tipoFiltro === "Público" ? "selected" : "" ?>>Público</option><option value="Privado" <?= $tipoFiltro === "Privado" ? "selected" : "" ?>>Privado</option></select></label>
        </div>
        <button type="submit">Consultar Banco de Dados de Aeródromos</button>
    </form>
    <form method="get">
        <div class="campos">
            <label>Origem (código OACI)<input type="text" name="origem" maxlength="4" value="<?= htmlspecialchars($origemOaci) ?>" style="text-transform: uppercase" oninput="this.value = this.value.toUpperCase()" placeholder="Ex.: SBRB"></label>
            <label>Destino (código OACI)<input type="text" name="destino" maxlength="4" value="<?= htmlspecialchars($destinoOaci) ?>" style="text-transform: uppercase" oninput="this.value = this.value.toUpperCase()" placeholder="Ex.: SBMT"></label>
        </div>
        <button type="submit">Calcular distância</button>
        <?php if ($erroDistancia !== ""): ?><p class="erro"><?= htmlspecialchars($erroDistancia) ?></p><?php endif; ?>
        <?php if ($distanciaCalculada !== null): ?><p class="sucesso">Distância entre <?= htmlspecialchars($origemOaci) ?> e <?= htmlspecialchars($destinoOaci) ?>: <?= number_format($distanciaCalculada, 0, ",", ".") ?> NM</p><?php endif; ?>
    </form>
    <?php if ($busca !== "" || $uf !== "" || $tipoFiltro !== ""): ?>
    <section class="tabela aerodromos">
        <div class="titulo-tabela">
            <h2>Resultados <span class="contador-voos"><?= count($resultados) ?></span></h2>
        </div>
        <div class="tabela-container">
            <table>
                <thead>
                    <tr><th>Código OACI</th><th>Nome</th><th>Município</th><th>UF</th><th>Latitude</th><th>Longitude</th><th>Altitude</th><th>Pista</th><th>Tipo</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $aerodromo): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($aerodromo["CódigoOACI"] ?? "-") ?></strong></td>
                            <td><?= htmlspecialchars($aerodromo["Nome"] ?? "-") ?></td>
                            <td><?= htmlspecialchars($aerodromo["Município"] ?? "-") ?></td>
                            <td><span class="badge-rota" style="padding: 2px 6px; font-size: 11px;"><?= htmlspecialchars($aerodromo["UF"] ?? "-") ?></span></td>
                            <td><?= htmlspecialchars($aerodromo["Latitude"] ?? "-") ?></td>
                            <td><?= htmlspecialchars($aerodromo["Longitude"] ?? "-") ?></td>
                            <?php $altitudeMetros = is_numeric($aerodromo["Altitude"] ?? null) ? (float) $aerodromo["Altitude"] : null; ?>
                            <td><?= $altitudeMetros !== null ? number_format($altitudeMetros, 0, ",", ".") . "m / " . number_format($altitudeMetros * 3.28084, 0, ",", ".") . " ft" : "-" ?></td>
                            <td><?= htmlspecialchars(trim(($aerodromo["Designação1"] ?? "") . " " . ($aerodromo["Superfície1"] ?? "")) ?: "-") ?></td>
                            <td><?= htmlspecialchars($aerodromo["Tipo"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$resultados): ?><tr><td colspan="9" style="text-align: center; padding: 32px; color: var(--muted);">Nenhum aeródromo encontrado.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
    <?php include "rodape.php"; ?>
</body>
</html>
