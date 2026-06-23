<?php
// ============================================================
// FUNÇÃO: obterUsuarioIdDaRequisicao
// ============================================================
// Tenta descobrir qual usuário está fazendo a requisição.
// Prioridade:
//   1. Parâmetro GET/POST 'usuarioId'
//   2. Cabeçalho HTTP 'X-Usuario-Id' (enviado pelo frontend)
//   3. Padrão: usuário 1 (caso não encontre nenhum)
// ============================================================
function obterUsuarioIdDaRequisicao($input = []) {
    // Verifica se veio um ID válido nos parâmetros da URL/body
    if (is_array($input) && isset($input['usuarioId']) && (int) $input['usuarioId'] > 0) {
        return (int) $input['usuarioId'];
    }

    // Verifica se veio um ID válido no cabeçalho HTTP
    if (isset($_SERVER['HTTP_X_USUARIO_ID']) && (int) $_SERVER['HTTP_X_USUARIO_ID'] > 0) {
        return (int) $_SERVER['HTTP_X_USUARIO_ID'];
    }

    // Se nada foi enviado, assume o usuário 1 (ex: admin padrão)
    return 1;
}

// ============================================================
// FUNÇÃO: garantirDataHoraHistorico
// ============================================================
// Verifica se a coluna 'dataRegistro' da tabela 'historico' está
// no formato DATETIME (com hora). Se estiver como DATE (só dia),
// converte para DATETIME para guardar também a hora.
// Executa apenas uma vez por requisição (static $verificado).
// ============================================================
function garantirDataHoraHistorico($conn) {
    static $verificado = false;  // Só executa uma vez
    if ($verificado) {
        return;
    }

    $verificado = true;

    try {
        // Consulta o tipo atual da coluna dataRegistro
        $query = "SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'historico'
                AND COLUMN_NAME = 'dataRegistro'
            LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $tipo = strtolower((string) $stmt->fetchColumn());

        // Se for só DATE (sem hora), altera para DATETIME
        if ($tipo === 'date') {
            $conn->exec("ALTER TABLE historico MODIFY dataRegistro DATETIME NOT NULL");
        }
    } catch (PDOException $e) {
        error_log('Nao foi possivel ajustar dataRegistro do historico: ' . $e->getMessage());
    }
}

// ============================================================
// FUNÇÃO: usuarioHistoricoValido
// ============================================================
// Verifica se o ID do usuário existe na tabela 'usuario'.
// Se existir, retorna o próprio ID. Se não, retorna 1 (padrão).
// ============================================================
function usuarioHistoricoValido($conn, $usuarioId) {
    try {
        $stmt = $conn->prepare("SELECT id FROM usuario WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $usuarioId;  // Usuário existe, mantém o ID
        }
    } catch (PDOException $e) {
        error_log('Nao foi possivel validar usuario do historico: ' . $e->getMessage());
    }

    return 1;  // Se não encontrou ou deu erro, usa o padrão 1
}

// ============================================================
// FUNÇÃO: registrarHistorico
// ============================================================
// Insere um registro na tabela 'historico' com os dados da ação.
// Parâmetros:
//   - $conn: conexão com o banco
//   - $acao: tipo de ação (ex: 'INSERT', 'UPDATE', 'DELETE', 'LOGIN')
//   - $tabelaAlvo: nome da tabela afetada (ex: 'unidade', 'departamento')
//   - $registroId: ID do registro afetado (opcional)
//   - $descricao: texto descrevendo o que foi feito
//   - $usuarioId: ID do usuário (se não informado, tenta descobrir)
// Retorna true se conseguiu inserir, false em caso de erro.
// ============================================================
function registrarHistorico($conn, $acao, $tabelaAlvo, $registroId, $descricao, $usuarioId = null) {
    try {
        // Garante que a coluna dataRegistro seja DATETIME
        garantirDataHoraHistorico($conn);

        // Define o usuário: se não veio por parâmetro, tenta descobrir
        $usuarioId = usuarioHistoricoValido($conn, $usuarioId ?: obterUsuarioIdDaRequisicao());

        // SQL de inserção no histórico
        $query = "INSERT INTO historico
            (usuarioId, acao, tabelaAlvo, registroId, descricao, dataRegistro)
            VALUES
            (:usuarioId, :acao, :tabelaAlvo, :registroId, :descricao, NOW())";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':acao', $acao, PDO::PARAM_STR);
        $stmt->bindValue(':tabelaAlvo', $tabelaAlvo, PDO::PARAM_STR);
        // Se o registroId for nulo, insere NULL no banco
        if ($registroId !== null) {
            $stmt->bindValue(':registroId', (int) $registroId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':registroId', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
        $stmt->execute();

        return true;  // Inseriu com sucesso
    } catch (PDOException $e) {
        // Registra o erro no log do servidor, mas não interrompe a execução
        error_log('Erro ao registrar historico: ' . $e->getMessage());
        return false;
    }
}
?>