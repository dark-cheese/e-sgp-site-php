<?php
function obterUsuarioIdDaRequisicao($input = []) {
    if (is_array($input) && isset($input['usuarioId']) && (int) $input['usuarioId'] > 0) {
        return (int) $input['usuarioId'];
    }

    if (isset($_SERVER['HTTP_X_USUARIO_ID']) && (int) $_SERVER['HTTP_X_USUARIO_ID'] > 0) {
        return (int) $_SERVER['HTTP_X_USUARIO_ID'];
    }

    return 1;
}

function garantirDataHoraHistorico($conn) {
    static $verificado = false;
    if ($verificado) {
        return;
    }

    $verificado = true;

    try {
        $query = "SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'historico'
                AND COLUMN_NAME = 'dataRegistro'
            LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $tipo = strtolower((string) $stmt->fetchColumn());

        if ($tipo === 'date') {
            $conn->exec("ALTER TABLE historico MODIFY dataRegistro DATETIME NOT NULL");
        }
    } catch (PDOException $e) {
        error_log('Nao foi possivel ajustar dataRegistro do historico: ' . $e->getMessage());
    }
}

function usuarioHistoricoValido($conn, $usuarioId) {
    try {
        $stmt = $conn->prepare("SELECT id FROM usuario WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $usuarioId;
        }
    } catch (PDOException $e) {
        error_log('Nao foi possivel validar usuario do historico: ' . $e->getMessage());
    }

    return 1;
}

function registrarHistorico($conn, $acao, $tabelaAlvo, $registroId, $descricao, $usuarioId = null) {
    try {
        garantirDataHoraHistorico($conn);

        $usuarioId = usuarioHistoricoValido($conn, $usuarioId ?: obterUsuarioIdDaRequisicao());

        $query = "INSERT INTO historico
            (usuarioId, acao, tabelaAlvo, registroId, descricao, dataRegistro)
            VALUES
            (:usuarioId, :acao, :tabelaAlvo, :registroId, :descricao, NOW())";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':acao', $acao, PDO::PARAM_STR);
        $stmt->bindValue(':tabelaAlvo', $tabelaAlvo, PDO::PARAM_STR);
        if ($registroId !== null) {
            $stmt->bindValue(':registroId', (int) $registroId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':registroId', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
        $stmt->execute();

        return true;
    } catch (PDOException $e) {
        error_log('Erro ao registrar historico: ' . $e->getMessage());
        return false;
    }
}
?>
