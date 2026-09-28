<?php

function carregarAerodromos(string $arquivo, string $tipo): array
{
    $conteudo = file_get_contents($arquivo);
    $conteudo = preg_replace("/^\xEF\xBB\xBF/", "", $conteudo); // remove BOM que quebra o json_decode
    $dados = json_decode($conteudo, true) ?: [];
    $filtrados = [];
    foreach ($dados as $item) {
        $filtrados[] = [
            "CódigoOACI" => strtoupper(trim($item["CódigoOACI"] ?? "")),
            "Nome" => trim($item["Nome"] ?? ""),
            "Município" => trim($item["Município"] ?? ""),
            "UF" => trim($item["UF"] ?? ""),
            "LatGeoPoint" => trim($item["LatGeoPoint"] ?? ""),
            "LonGeoPoint" => trim($item["LonGeoPoint"] ?? ""),
            "Latitude" => trim($item["Latitude"] ?? ""),
            "Longitude" => trim($item["Longitude"] ?? ""),
            "Altitude" => trim((string) ($item["Altitude"] ?? "")),
            "Designação1" => trim($item["Designação1"] ?? ""),
            "Superfície1" => trim($item["Superfície1"] ?? ""),
            "Tipo" => $tipo
        ];
    }
    return $filtrados;
}

function carregarDadosAerodromos(): array
{
    static $dadosMemoria = null;
    if ($dadosMemoria !== null) {
        return $dadosMemoria;
    }

    $arquivoCache = __DIR__ . "/aerodromos_cache.php";
    $jsonPub = __DIR__ . "/AerodromosPublicos.json";
    $jsonPriv = __DIR__ . "/AerodromosPrivados.json";

    $tempoCache = is_file($arquivoCache) ? filemtime($arquivoCache) : 0;
    $tempoPub = is_file($jsonPub) ? filemtime($jsonPub) : 0;
    $tempoPriv = is_file($jsonPriv) ? filemtime($jsonPriv) : 0;

    if ($tempoCache > 0 && $tempoCache >= max($tempoPub, $tempoPriv)) {
        $cacheCarregado = require $arquivoCache;
        if (isset($cacheCarregado["lista"]) && isset($cacheCarregado["por_oaci"])) {
            $dadosMemoria = $cacheCarregado;
        } else {
            $porOaci = [];
            foreach ($cacheCarregado as $aerodromo) {
                $codigo = $aerodromo["CódigoOACI"] ?? "";
                if ($codigo !== "") {
                    $porOaci[$codigo] = $aerodromo;
                }
            }
            $dadosMemoria = [
                "lista" => $cacheCarregado,
                "por_oaci" => $porOaci
            ];
        }
        return $dadosMemoria;
    }

    $aerodromos = array_merge(
        carregarAerodromos($jsonPub, "Público"),
        carregarAerodromos($jsonPriv, "Privado")
    );
    $aerodromosPorOaci = [];
    foreach ($aerodromos as $aerodromo) {
        $codigo = $aerodromo["CódigoOACI"];
        if ($codigo !== "") {
            $aerodromosPorOaci[$codigo] = $aerodromo;
        }
    }

    $codigoCache = "<?php\nreturn " . var_export($aerodromos, true) . ";\n";
    @file_put_contents($arquivoCache, $codigoCache, LOCK_EX);

    $dadosMemoria = [
        "lista" => $aerodromos,
        "por_oaci" => $aerodromosPorOaci
    ];
    return $dadosMemoria;
}

function carregarAerodromosPorOaci(): array
{
    return carregarDadosAerodromos()["por_oaci"];
}

function carregarTodosAerodromos(): array
{
    return carregarDadosAerodromos()["lista"];
}

function distanciaNM(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $raioNM = 3440.065;
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLon = deg2rad($lon2 - $lon1);
    $a = sin($deltaLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $raioNM * $c;
}

function rumoEBussola(float $lat1, float $lon1, float $lat2, float $lon2): array
{
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLon = deg2rad($lon2 - $lon1);

    $y = sin($deltaLon) * cos($lat2Rad);
    $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($deltaLon);

    $graus = fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);

    $pontos = [
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

    $sigla = "N";
    $nome = "Norte";
    foreach ($pontos as [$s, $n, $min, $max]) {
        if ($graus >= $min && $graus < $max) {
            $sigla = $s;
            $nome = $n;
            break;
        }
    }

    return [
        "rumo_graus" => (int) round($graus),
        "rumo_formatado" => sprintf("%03d°", round($graus)),
        "direcao_sigla" => $sigla,
        "direcao_nome" => $nome
    ];
}

