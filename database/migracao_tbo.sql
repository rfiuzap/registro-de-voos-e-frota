-- TBO (revisão geral do motor): valor estimado (US$) e horas até a revisão. Aplicar uma única vez em bancos criados antes desta versão.

ALTER TABLE `aeronaves`
  ADD COLUMN `tbo_valor` decimal(14,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `tbo_horas` int(11) NOT NULL DEFAULT 0;
