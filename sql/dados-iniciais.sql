-- =============================================
-- Dados Iniciais para Teste
-- =============================================

-- Usuário admin padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha, nivel) VALUES 
('Administrador', 'admin@prefeitura.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Secretarias
INSERT INTO secretarias (nome, responsavel) VALUES 
('Educação', 'João Silva'),
('Saúde', 'Maria Santos'),
('Obras', 'Pedro Oliveira');

-- Unidades
INSERT INTO unidades (nome, secretaria_id, endereco, responsavel) VALUES 
('E.M. Pedro Álvares', 1, 'Rua das Flores, 123 - Centro', 'Diretora Maria'),
('E.M. Santos Dumont', 1, 'Av. Principal, 456 - Jardim', 'Diretor João'),
('Posto Central', 2, 'Rua da Saúde, 789 - Centro', 'Dr. Carlos');

-- Locais
INSERT INTO locais (nome, unidade_id) VALUES 
('Sala 1 - 1º Ano', 1),
('Sala 2 - 2º Ano', 1),
('Sala de Informática', 1),
('Biblioteca', 1),
('Sala 1', 2),
('Recepção', 3);
