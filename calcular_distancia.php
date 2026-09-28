<?php
require "auth.php";
usuarioAtualId(true);
session_write_close();
require "aerodromos_dados.php";

header("Content-Type: application/json; charset=utf-8");

$origemOaci = strtoupper(trim($_GET["origem"] ?? ""));
$destinoOaci = strtoupper(trim($_GET["destino"] ?? ""));
$oaciConsulta = strtoupper(trim($_GET["oaci"] ?? ""));

$aerodromosPorOaci = carregarAerodromosPorOaci();

// Consulta individual de um único aeródromo
if ($oaciConsulta !== "") {
    $aerodromo = $aerodromosPorOaci[$oaciConsulta] ?? null;
    if (!$aerodromo) {
        echo json_encode(["encontrado" => false, "mensagem" => "Aeródromo não encontrado."]);
        exit;
    }
    $altitudeM = is_numeric($aerodromo["Altitude"] ?? null) ? (float) $aerodromo["Altitude"] : 0;
    $altitudeFt = round($altitudeM * 3.28084);
    echo json_encode([
        "encontrado" => true,
        "aerodromo" => [
            "oaci" => $oaciConsulta,
            "nome" => $aerodromo["Nome"] ?? "",
            "municipio" => $aerodromo["Município"] ?? "",
            "uf" => $aerodromo["UF"] ?? "",
            "tipo" => $aerodromo["Tipo"] ?? "",
            "altitude_m" => $altitudeM,
            "altitude_ft" => $altitudeFt,
            "lat" => (float) str_replace(",", ".", $aerodromo["LatGeoPoint"] ?? "0"),
            "lon" => (float) str_replace(",", ".", $aerodromo["LonGeoPoint"] ?? "0")
        ]
    ]);
    exit;
}

if ($origemOaci === "" && $destinoOaci === "") {
    echo json_encode(["erro" => "Informe os códigos OACI de origem e/ou destino."]);
    exit;
}

$aerodromoOrigem = $origemOaci !== "" ? ($aerodromosPorOaci[$origemOaci] ?? null) : null;
$aerodromoDestino = $destinoOaci !== "" ? ($aerodromosPorOaci[$destinoOaci] ?? null) : null;

$altOrigemM = is_numeric($aerodromoOrigem["Altitude"] ?? null) ? (float) $aerodromoOrigem["Altitude"] : 0;
$altDestinoM = is_numeric($aerodromoDestino["Altitude"] ?? null) ? (float) $aerodromoDestino["Altitude"] : 0;

$resposta = [
    "origem" => $aerodromoOrigem ? [
        "oaci" => $origemOaci,
        "nome" => $aerodromoOrigem["Nome"] ?? "",
        "municipio" => $aerodromoOrigem["Município"] ?? "",
        "uf" => $aerodromoOrigem["UF"] ?? "",
        "altitude_m" => $altOrigemM,
        "altitude_ft" => round($altOrigemM * 3.28084),
        "lat" => (float) str_replace(",", ".", $aerodromoOrigem["LatGeoPoint"] ?? "0"),
        "lon" => (float) str_replace(",", ".", $aerodromoOrigem["LonGeoPoint"] ?? "0")
    ] : null,
    "destino" => $aerodromoDestino ? [
        "oaci" => $destinoOaci,
        "nome" => $aerodromoDestino["Nome"] ?? "",
        "municipio" => $aerodromoDestino["Município"] ?? "",
        "uf" => $aerodromoDestino["UF"] ?? "",
        "altitude_m" => $altDestinoM,
        "altitude_ft" => round($altDestinoM * 3.28084),
        "lat" => (float) str_replace(",", ".", $aerodromoDestino["LatGeoPoint"] ?? "0"),
        "lon" => (float) str_replace(",", ".", $aerodromoDestino["LonGeoPoint"] ?? "0")
    ] : null,
];

if ($aerodromoOrigem && $aerodromoDestino) {
    $lat1 = (float) str_replace(",", ".", $aerodromoOrigem["LatGeoPoint"] ?? "0");
    $lon1 = (float) str_replace(",", ".", $aerodromoOrigem["LonGeoPoint"] ?? "0");
    $lat2 = (float) str_replace(",", ".", $aerodromoDestino["LatGeoPoint"] ?? "0");
    $lon2 = (float) str_replace(",", ".", $aerodromoDestino["LonGeoPoint"] ?? "0");

    $distancia = distanciaNM($lat1, $lon1, $lat2, $lon2);
    $rumo = rumoEBussola($lat1, $lon1, $lat2, $lon2);

    $resposta["distancia_nm"] = round($distancia);
    $resposta["rumo_graus"] = $rumo["rumo_graus"];
    $resposta["rumo_formatado"] = $rumo["rumo_formatado"];
    $resposta["direcao_sigla"] = $rumo["direcao_sigla"];
    $resposta["direcao_nome"] = $rumo["direcao_nome"];
}

echo json_encode($resposta);
