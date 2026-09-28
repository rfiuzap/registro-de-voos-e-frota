<?php
// Copie para config.php no servidor de produção e preencha com os dados reais.
return [
    "host" => "localhost",
    "user" => "usuario_producao",
    "pass" => "senha_forte_aqui",
    "database" => "registro_voos_prod",

    // Ambiente: "producao" ou "demo" (demo = usuario demo/demo123, sem cadastro, dados restaurados a cada 2h).
    "ambiente" => "producao",

    // Endereço público do sistema, sem barra no final (usado nos links de redefinição de senha).
    "url_base" => "https://seudominio.com.br/Registro-de-Voos-e-Frota",

    // Conta de e-mail criada no cPanel do mesmo domínio.
    "email_remetente" => "nao-responda@seudominio.com.br",
    "nome_remetente" => "Registro de Voos",
];
