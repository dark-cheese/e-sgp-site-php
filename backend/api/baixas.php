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
// GET – LISTAR BAIXAS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        /*
         * LÓGICA DO SELECT:
         * - Busca todos os registros de baixa
         * - LEFT JOIN com item para obter número de patrimônio, descrição e valor
         * - LEFT JOIN com departamento, unidade e responsável para mostrar onde o item estava
         * - Ordena pela data da baixa (mais recente primeiro)
         * - Usado para listar todas as baixas na página de baixas
         */
        $query = "SELECT b.id, b.itemId, b.tipo, b.dataBaixa, b.justificativa, b.documento,
            i.numeroPatrimonio AS itemNumero,
            i.descricao AS itemDescricao,
            i.valor AS itemValor,
            d.nome AS departamento,
            u.nome AS unidade,
            r.nome AS responsavel
        FROM baixa b
        LEFT JOIN item i ON b.itemId = i.id
        LEFT JOIN departamento d ON i.departamentoId = d.id
        LEFT JOIN unidade u ON d.unidadeId = u.id
        LEFT JOIN responsavel r ON i.responsavelId = r.id
        ORDER BY b.dataBaixa DESC";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar baixas: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST – REGISTRAR BAIXA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $itemId = isset($input['itemId']) ? (int)$input['itemId'] : 0;
    $tipo = strtoupper(trim($input['tipo'] ?? ''));
    $dataBaixa = trim($input['dataBaixa'] ?? '');
    $justificativa = trim($input['justificativa'] ?? '');
    $documento = trim($input['documento'] ?? '') ?: null;

    // ========== VALIDAÇÕES ==========
    if ($itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'O item é obrigatório.']);
        exit;
    }

    if (!in_array($tipo, ['DOACAO', 'INUTILIZACAO', 'PERDIDO', 'CADASTRAMENTO_INDEVIDO'])) {
        echo json_encode(['success' => false, 'message' => 'Tipo de baixa inválido.']);
        exit;
    }

    if ($dataBaixa === '') {
        echo json_encode(['success' => false, 'message' => 'A data da baixa é obrigatória.']);
        exit;
    }

    if ($justificativa === '') {
        echo json_encode(['success' => false, 'message' => 'A justificativa é obrigatória.']);
        exit;
    }

    try {
        // Verifica se o item existe
        $itemStmt = $conn->prepare("SELECT id, numeroPatrimonio, descricao FROM item WHERE id = :itemId LIMIT 1");
        $itemStmt->bindValue(':itemId', $itemId, PDO::PARAM_INT);
        $itemStmt->execute();
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item não encontrado.']);
            exit;
        }

        // Verifica se o item já foi baixado (consulta a última baixa)
        $baixaExistenteStmt = $conn->prepare("SELECT id, tipo FROM baixa WHERE itemId = :itemId ORDER BY id DESC LIMIT 1");
        $baixaExistenteStmt->bindValue(':itemId', $itemId, PDO::PARAM_INT);
        $baixaExistenteStmt->execute();
        $baixaExistente = $baixaExistenteStmt->fetch(PDO::FETCH_ASSOC);

        if ($baixaExistente) {
            echo json_encode(['success' => false, 'message' => 'Este item já está desativado pela baixa ' . $baixaExistente['tipo'] . '.']);
            exit;
        }

        /*
         * LÓGICA DO INSERT:
         * - Insere o registro de baixa com itemId, tipo, data, justificativa e documento (opcional)
         * - Não exclui o item, apenas registra a baixa para manter o histórico
         * - O status "ativo" do item é determinado pela ausência de baixa (via LEFT JOIN na consulta de itens)
         */
        $conn->beginTransaction();

        $query = 'INSERT INTO baixa (itemId, tipo, dataBaixa, justificativa, documento)
            VALUES (:itemId, :tipo, :dataBaixa, :justificativa, :documento)';
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':itemId', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':dataBaixa', $dataBaixa, PDO::PARAM_STR);
        $stmt->bindValue(':justificativa', $justificativa, PDO::PARAM_STR);
        $stmt->bindValue(':documento', $documento, PDO::PARAM_STR);
        $stmt->execute();
        $id = (int)$conn->lastInsertId();

        // Registra no histórico
        registrarHistorico(
            $conn,
            'CRIAR',
            'baixa',
            $id,
            'Registrou baixa do patrimônio ' . $item['numeroPatrimonio'] . ' com tipo "' . $tipo . '".',
            obterUsuarioIdDaRequisicao($input)
        );

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Baixa registrada com sucesso. O item foi desativado como ' . $tipo . '.',
            'id' => $id
        ]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Erro ao registrar baixa: ' . $e->getMessage()]);
    }
    exit;
}

// Método não permitido
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);