<?php
// ============================================================
// CONFIGURAÇÕES INICIAIS
// ============================================================
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Usuario-Id");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auditoria.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Falha ao conectar com o banco de dados.']);
    exit;
}

// ============================================================
// GET – LISTAR SECRETARIAS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        /*
         * LÓGICA DA CONSULTA:
         * - Busca todas as secretarias com o nome do responsável (LEFT JOIN com responsavel)
         * - Conta quantas UNIDADES cada secretaria possui (subconsulta)
         * - Conta quantos ITENS existem dentro de todas as unidades/departamentos da secretaria (subconsulta)
         * - Ordena pelo nome da secretaria
         * - O resultado mostra: ID, nome, descrição, responsável, total de unidades e total de itens
         */
        $query = "SELECT s.id, s.nome, s.descricao, s.responsavelId, r.nome AS responsavel,
            (SELECT COUNT(*) FROM unidade u WHERE u.secretariaId = s.id) AS unidades,
            (SELECT COUNT(*) FROM item i
                JOIN departamento d ON i.departamentoId = d.id
                JOIN unidade u2 ON d.unidadeId = u2.id
                WHERE u2.secretariaId = s.id) AS itens
        FROM secretaria s
        LEFT JOIN responsavel r ON s.responsavelId = r.id
        ORDER BY s.nome";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $secretarias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $secretarias]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar secretarias: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST – CADASTRAR SECRETARIA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $nome = trim($input['nome'] ?? '');
    $descricao = trim($input['descricao'] ?? '');
    $responsavelId = isset($input['responsavelId']) && $input['responsavelId'] !== '' ? (int)$input['responsavelId'] : null;

    // Valida campos obrigatórios
    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'O nome da secretaria é obrigatório.']);
        exit;
    }

    if ($responsavelId === null) {
        echo json_encode(['success' => false, 'message' => 'O responsável é obrigatório.']);
        exit;
    }

    try {
        /*
         * LÓGICA DO INSERT:
         * - Insere um novo registro na tabela secretaria
         * - Salva nome, descrição (pode ser nulo) e ID do responsável
         * - O ID é gerado automaticamente pelo banco (AUTO_INCREMENT)
         * - Depois do INSERT, chama a função registrarHistorico() para registrar a ação
         */
        $query = "INSERT INTO secretaria (nome, descricao, responsavelId) VALUES (:nome, :descricao, :responsavelId)";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':responsavelId', $responsavelId, PDO::PARAM_INT);
        $stmt->execute();
        $id = (int) $conn->lastInsertId();

        registrarHistorico(
            $conn,
            'CRIAR',
            'secretaria',
            $id,
            'Cadastrou a secretaria "' . $nome . '".',
            obterUsuarioIdDaRequisicao($input)
        );

        echo json_encode([
            'success' => true,
            'message' => 'Secretaria cadastrada com sucesso.',
            'id' => $id
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao cadastrar secretaria: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Método não permitido
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);