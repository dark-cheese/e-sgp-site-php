-- Execute esta migracao em bancos criados antes da inclusao do responsavel no inventario.
ALTER TABLE inventario
    ADD COLUMN responsavelId INTEGER NULL AFTER unidadeId,
    ADD CONSTRAINT fk_inventario_responsavel
        FOREIGN KEY (responsavelId)
        REFERENCES responsavel(id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL;
