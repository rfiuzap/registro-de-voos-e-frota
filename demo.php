<?php
// Ambiente de demonstração: conta demo/demo123 e dados restaurados a cada 2 horas.
// Ativado com "ambiente" => "demo" no config.php da instalação.

const DEMO_LOGIN = "demo";
const DEMO_EMAIL = "demo@demo.com";
const DEMO_SENHA = "demo123";
const DEMO_INTERVALO = 2 * 60 * 60;

function ambienteDemo(): bool
{
    global $config;
    return ($config["ambiente"] ?? "local") === "demo";
}

// Recria o banco a partir de database/producao.sql + database/demo_dados.sql quando o prazo vence.
function demoRestaurarSeVencido(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS demo_controle (id TINYINT UNSIGNED PRIMARY KEY, reiniciado_em INT UNSIGNED NOT NULL) ENGINE=InnoDB");
    $ultimo = (int) ($conn->query("SELECT reiniciado_em FROM demo_controle WHERE id = 1")->fetch_row()[0] ?? 0);
    if (time() - $ultimo < DEMO_INTERVALO) {
        return;
    }
    if ((int) $conn->query("SELECT GET_LOCK('registro_voos_demo_reset', 0)")->fetch_row()[0] !== 1) {
        return;
    }

    try {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("DROP TABLE IF EXISTS voos, planejamentos, aeronaves_ocultas, aeronaves, senha_resets, login_tentativas, usuarios");
        foreach (["producao.sql", "demo_dados.sql"] as $arquivo) {
            $sql = file_get_contents(__DIR__ . "/database/" . $arquivo);
            if ($sql === false || !$conn->multi_query($sql)) {
                throw new RuntimeException("Falha ao aplicar " . $arquivo . ": " . $conn->error);
            }
            do {
                if ($resultado = $conn->store_result()) {
                    $resultado->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            if ($conn->errno) {
                throw new RuntimeException("Falha ao aplicar " . $arquivo . ": " . $conn->error);
            }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->query("INSERT INTO demo_controle (id, reiniciado_em) VALUES (1, " . time() . ") ON DUPLICATE KEY UPDATE reiniciado_em = VALUES(reiniciado_em)");
        demoRemoverFotosDeVisitantes();
    } finally {
        $conn->query("SELECT RELEASE_LOCK('registro_voos_demo_reset')");
    }
}

// Apaga fotos enviadas por visitantes, mantendo só as referenciadas nos dados originais e as logos.
function demoRemoverFotosDeVisitantes(): void
{
    $base = (string) file_get_contents(__DIR__ . "/database/producao.sql") . (string) file_get_contents(__DIR__ . "/database/demo_dados.sql");
    foreach (glob(__DIR__ . "/uploads/*") ?: [] as $arquivo) {
        $nome = basename($arquivo);
        if (is_file($arquivo) && !str_contains($base, "uploads/" . $nome) && !preg_match('/^(logo|favi)/', $nome) && $nome !== ".htaccess") {
            @unlink($arquivo);
        }
    }
}
