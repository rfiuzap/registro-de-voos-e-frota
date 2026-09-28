<?php $usuarioMenu = usuarioLogado(); ?>
<?php if ($usuarioMenu): ?>
<div class="usuario-menu">
    <span class="usuario-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($usuarioMenu["nome"], 0, 1))) ?></span>
    <span class="usuario-nome"><?= htmlspecialchars($usuarioMenu["nome"]) ?></span>
    <form method="post" action="sair.php" class="form-sair">
        <?= campoCsrf() ?>
        <button type="submit" class="btn-sair" title="Sair do sistema">Sair</button>
    </form>
</div>
<?php else: ?>
<div class="usuario-menu">
    <a href="<?= htmlspecialchars(urlLogin()) ?>" class="btn-entrar-topo">Entrar</a>
    <?php if (!ambienteDemo()): ?><a href="cadastro.php?voltar=<?= urlencode(basename($_SERVER["SCRIPT_NAME"] ?? "index.php")) ?>" class="btn-criar-conta-topo">Criar conta</a><?php endif; ?>
</div>
<?php endif; ?>
