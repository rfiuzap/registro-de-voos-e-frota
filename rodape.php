<?php
// Versão derivada da última modificação de arquivos do projeto: muda sozinha a cada alteração.
$ultimaAlteracao = 0;
foreach (glob(__DIR__ . "/*.{php,css,js}", GLOB_BRACE) as $arquivoProjeto) {
    $ultimaAlteracao = max($ultimaAlteracao, filemtime($arquivoProjeto));
}
$versaoSistema = "v" . date("Y.m.d.Hi", $ultimaAlteracao);
?>
<footer class="rodape">
    <span>&copy; <?= date("Y") ?> Renato Fiuza</span>
    <span class="rodape-versao" title="Atualizado em <?= date("d/m/Y H:i", $ultimaAlteracao) ?>"><?= $versaoSistema ?></span>
</footer>
