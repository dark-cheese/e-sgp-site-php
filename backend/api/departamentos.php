<?php
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
// GET – LISTAR DEPARTAMENTOS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $unidadeId = isset($_GET['unidadeId']) ? (int)$_GET['unidadeId'] : 0;

        /*
         * LÓGICA DA CONSULTA:
         * - Busca departamentos com JOINs para obter nome da unidade, secretaria e responsável
         * - Subconsulta: conta quantos itens cada departamento possui
         * - Se veio unidadeId, filtra por ele
         * - Ordena por nome do departamento
         */
        $query = "SELECT d.id, d.nome, d.unidadeId, d.responsavelId,
            u.nome AS unidade,
            u.secretariaId,
            s.nome AS secretaria,
            r.nome AS responsavel,
            (SELECT COUNT(*) FROM item i WHERE i.departamentoId = d.id) AS itens
        FROM departamento d
        LEFT JOIN unidade u ON d.unidadeId = u.id
        LEFT JOIN secretaria s ON u.secretariaId = s.id
        LEFT JOIN responsavel r ON d.responsavelId = r.id";

        if ($unidadeId > 0) {
            $query .= " WHERE d.unidadeId = :unidadeId";
        }

        $query .= " ORDER BY d.nome";

        $stmt = $conn->prepare($query);
        if ($unidadeId > 0) {
            $stmt->bindValue(':unidadeId', $unidadeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar departamentos: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST – CADASTRAR DEPARTAMENTO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nome = trim($input['nome'] ?? '');
    $unidadeId = isset($input['unidadeId']) ? (int)$input['unidadeId'] : 0;
    $responsavelId = isset($input['responsavelId']) && $input['responsavelId'] !== '' ? (int)$input['responsavelId'] : null;

    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'O nome do departamento é obrigatório.']);
        exit;
    }

    if ($unidadeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'A unidade é obrigatória.']);
        exit;
    }

    try {
        /*
         * LÓGICA DO INSERT:
         * - Insere novo departamento com nome, unidadeId e responsavelId (opcional)
         * - Registra no histórico
         */
        $query = 'INSERT INTO departamento (nome, unidadeId, responsavelId) VALUES (:nome, :unidadeId, :responsavelId)';
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':unidadeId', $unidadeId, PDO::PARAM_INT);
        $stmt->bindValue(':responsavelId', $responsavelId ?? null, PDO::PARAM_INT);
        $stmt->execute();
        $id = (int) $conn->lastInsertId();

        registrarHistorico(
            $conn,
            'CRIAR',
            'departamento',
            $id,
            'Cadastrou o departamento "' . $nome . '".',
            obterUsuarioIdDaRequisicao($input)
        );

        echo json_encode(['success' => true, 'message' => 'Departamento cadastrado com sucesso.', 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar departamento: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);