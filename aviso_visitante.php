<?php if (ambienteDemo()): ?>
<div class="aviso-visitante" role="status"><span><strong>Ambiente de demonstração</strong> · Usuário: demo · Senha: demo123 · Os dados voltam ao original a cada 2 horas.</span></div>
<?php endif; ?>
<?php if (!usuarioLogado()): ?>
<div class="aviso-visitante" role="status">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <span>Você está navegando como visitante. <strong>É necessário fazer login para salvar as atividades feitas</strong> (voos, planejamentos e aeronaves).</span>
    <a href="<?= htmlspecialchars(urlLogin()) ?>" class="aviso-visitante-link">Entrar</a>
</div>
<?php endif; ?>
