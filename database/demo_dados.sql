-- Dados da demo: usuario demo/demo123 + voos e planejamentos ficticios.
-- Aplicado apos producao.sql pelo reset automatico (demo.php).
SET NAMES utf8mb4;
INSERT INTO usuarios (id, nome, email, senha_hash, criado_em) VALUES (1, 'Piloto Demo', 'demo@demo.com', '$2y$10$PAC46X.6CXMHhnZyuBtlq.PZkc8Xhtmr5Esb43yl4rkpwmKXeSh76', NOW() - INTERVAL 180 DAY);
INSERT INTO voos (aeronave, partida, destino, distancia_nm, tanque_decolagem, tanque_pouso, consumo_real, consumo_gph, tempo_voo, altitude, velocidade, observacoes, criado_em, usuario_id) VALUES
('CESSNA Caravan 208', 'SBKP', 'SBSR', 180.00, 212.48, 145.93, 66.55, 48.00, '01:23:00', 10000, 129.84, 'Tempo bom, CAVOK.', NOW() - INTERVAL 166 DAY - INTERVAL 611 MINUTE, 1),
('Beechcraft Bonanza G36', 'SBJD', 'SBMT', 25.00, 40.70, 34.79, 5.91, 14.73, '00:24:00', 8500, 62.33, '', NOW() - INTERVAL 156 DAY - INTERVAL 33 MINUTE, 1),
('CESSNA Caravan 208', 'SBVT', 'SBRJ', 225.00, 219.12, 136.80, 82.32, 53.50, '01:32:00', 10500, 146.23, 'Vento de proa na rota.', NOW() - INTERVAL 152 DAY - INTERVAL 827 MINUTE, 1),
('Beechcraft Bonanza G36', 'SBMT', 'SBCT', 180.00, 69.56, 49.95, 19.61, 15.03, '01:18:00', 8000, 138.00, '', NOW() - INTERVAL 142 DAY - INTERVAL 119 MINUTE, 1),
('Cessna 172P Skyhawk', 'SBMT', 'SBJD', 25.00, 36.55, 32.49, 4.06, 8.57, '00:28:00', 7500, 52.74, 'Vento de proa na rota.', NOW() - INTERVAL 132 DAY - INTERVAL 55 MINUTE, 1),
('CESSNA Caravan 208', 'SBMT', 'SBFL', 270.00, 292.16, 200.02, 92.14, 49.00, '01:53:00', 10000, 143.58, 'Desvio por formação no través.', NOW() - INTERVAL 122 DAY - INTERVAL 76 MINUTE, 1),
('Pilatus PC12 NGX', 'SBVT', 'SBRJ', 225.00, 393.96, 324.69, 69.27, 63.36, '01:06:00', 25500, 205.79, 'Treino de pousos.', NOW() - INTERVAL 114 DAY - INTERVAL 50 MINUTE, 1),
('Daher Tbm960', 'SBMT', 'SBBH', 270.00, 209.52, 143.10, 66.42, 59.28, '01:07:00', 27000, 240.98, 'Vento de proa na rota.', NOW() - INTERVAL 107 DAY - INTERVAL 23 MINUTE, 1),
('CESSNA Caravan 208', 'SBRJ', 'SBMT', 195.00, 298.80, 231.59, 67.21, 47.50, '01:25:00', 10500, 137.82, '', NOW() - INTERVAL 96 DAY - INTERVAL 23 MINUTE, 1),
('Piper M500', 'SBMT', 'SBNF', 290.00, 142.80, 89.65, 53.15, 37.00, '01:26:00', 24000, 201.87, 'Voo de instrução.', NOW() - INTERVAL 87 DAY - INTERVAL 208 MINUTE, 1),
('Beechcraft Bonanza G36', 'SBMT', 'SBFL', 270.00, 50.83, 22.59, 28.24, 14.73, '01:55:00', 9000, 140.81, 'Desvio por formação no través.', NOW() - INTERVAL 78 DAY - INTERVAL 897 MINUTE, 1),
('CESSNA Caravan 208', 'SBMT', 'SBUL', 300.00, 312.08, 211.62, 100.46, 51.50, '01:57:00', 11000, 153.79, 'Desvio por formação no través.', NOW() - INTERVAL 69 DAY - INTERVAL 408 MINUTE, 1),
('Pilatus PC12 NGX', 'SBKP', 'SBMT', 45.00, 301.50, 274.42, 27.08, 64.68, '00:25:00', 27000, 107.48, 'Tempo bom, CAVOK.', NOW() - INTERVAL 62 DAY - INTERVAL 123 MINUTE, 1),
('CESSNA Caravan 208', 'SBMT', 'SBNF', 290.00, 295.48, 199.42, 96.06, 48.00, '02:00:00', 10000, 144.91, 'Vento de proa na rota.', NOW() - INTERVAL 53 DAY - INTERVAL 893 MINUTE, 1),
('Piper M500', 'SBMT', 'SBRJ', 195.00, 161.50, 123.56, 37.94, 37.37, '01:01:00', 24000, 192.06, 'Vento de proa na rota.', NOW() - INTERVAL 44 DAY - INTERVAL 538 MINUTE, 1),
('Cessna 172P Skyhawk', 'SBRJ', 'SBMT', 195.00, 27.11, 12.05, 15.06, 7.68, '01:58:00', 6000, 99.46, '', NOW() - INTERVAL 33 DAY - INTERVAL 768 MINUTE, 1),
('CESSNA Caravan 208', 'SBJD', 'SBMT', 25.00, 262.28, 242.69, 19.59, 50.01, '00:24:00', 9500, 63.82, 'Vento de proa na rota.', NOW() - INTERVAL 22 DAY - INTERVAL 149 MINUTE, 1),
('Pilatus PC12 NGX', 'SBSR', 'SBKP', 180.00, 225.12, 163.48, 61.64, 66.66, '00:55:00', 25500, 194.67, 'Vento de proa na rota.', NOW() - INTERVAL 13 DAY - INTERVAL 404 MINUTE, 1);
INSERT INTO planejamentos (nome, modelo_aeronave, origem, destino, distancia, altitude_cruzeiro, tanque_decolagem, velocidade_subida, razao_subida, velocidade_cruzeiro, razao_descida, consumo_gph, tempo_estimado, consumo_estimado, criado_em, usuario_id) VALUES
('SBMT ➔ SBRJ (Bonanza G36)', 'Beechcraft Bonanza G36', 'SBMT', 'SBRJ', 195.00, 8500, 74.00, 105.00, 1050.00, 176.00, 500.00, 15.50, '01:18', 20.27, NOW() - INTERVAL 40 DAY, 1),
('SBMT ➔ SBNF (Tbm960)', 'Daher Tbm960', 'SBMT', 'SBNF', 290.00, 28000, 291.00, 145.00, 2000.00, 330.00, 500.00, 57.00, '01:05', 61.49, NOW() - INTERVAL 31 DAY, 1),
('SBKP ➔ SBBH (Caravan 208)', 'CESSNA Caravan 208', 'SBKP', 'SBBH', 250.00, 10000, 332.00, 115.00, 1234.00, 180.00, 500.00, 50.00, '01:35', 79.44, NOW() - INTERVAL 22 DAY, 1),
('SBMT ➔ SBKP (172P Skyhawk)', 'Cessna 172P Skyhawk', 'SBMT', 'SBKP', 45.00, 6500, 43.00, 75.00, 700.00, 120.00, 500.00, 8.00, '00:35', 4.60, NOW() - INTERVAL 13 DAY, 1);
