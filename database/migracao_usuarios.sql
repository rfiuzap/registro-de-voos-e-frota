-- Controle de usuários: aplicar uma única vez em bancos criados antes desta versão.

CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `senha_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_senha_resets_token` (`token_hash`),
  KEY `idx_senha_resets_usuario` (`usuario_id`),
  CONSTRAINT `fk_senha_resets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_tentativas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `email` varchar(190) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_tentativas_ip` (`ip`, `criado_em`),
  KEY `idx_login_tentativas_email` (`email`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `aeronaves`
  ADD `usuario_id` int(10) unsigned DEFAULT NULL,
  ADD `visibilidade` enum('publica','privada') NOT NULL DEFAULT 'publica',
  ADD KEY `idx_aeronaves_usuario` (`usuario_id`),
  ADD CONSTRAINT `fk_aeronaves_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

ALTER TABLE `voos`
  ADD `usuario_id` int(10) unsigned DEFAULT NULL,
  ADD KEY `idx_voos_usuario` (`usuario_id`),
  ADD CONSTRAINT `fk_voos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `planejamentos`
  ADD `usuario_id` int(10) unsigned DEFAULT NULL,
  ADD KEY `idx_planejamentos_usuario` (`usuario_id`),
  ADD CONSTRAINT `fk_planejamentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

CREATE TABLE `aeronaves_ocultas` (
  `usuario_id` int(10) unsigned NOT NULL,
  `aeronave_id` int(10) unsigned NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`, `aeronave_id`),
  KEY `idx_aeronaves_ocultas_aeronave` (`aeronave_id`),
  CONSTRAINT `fk_ocultas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ocultas_aeronave` FOREIGN KEY (`aeronave_id`) REFERENCES `aeronaves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
