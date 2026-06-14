-- Dados iniciais compatíveis com sql/banco.sql.
-- Não contém dados demonstrativos de estrutura patrimonial.

INSERT INTO tipo_usuario (nome, descricao) VALUES
('admin', 'Administrador da empresa, acesso total'),
('gestor', 'Gestor da prefeitura, cria usuários comuns e estruturas'),
('usuario', 'Usuário comum, cadastra itens');

INSERT INTO tipo_material (nome, descricao) VALUES
('permanente', 'Bens duráveis a inventariar'),
('consumo', 'Materiais consumíveis não inventariados');

INSERT INTO usuario (nome, email, senha, tipoUsuarioId) VALUES
('Admin', 'admin@gmail.com', 'admin123', 1);
