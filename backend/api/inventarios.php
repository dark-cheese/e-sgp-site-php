<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Falha ao conectar com o banco de dados.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT i.id, i.ano, i.nome, i.dataInicio, i.dataFim, i.status, i.unidadeId,
            u.nome AS unidade
        FROM inventario i
        LEFT JOIN unidade u ON i.unidadeId = u.id
        ORDER BY i.ano DESC, i.nome";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar inventários: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nome = trim($input['nome'] ?? '');
    $ano = isset($input['ano']) ? (int)$input['ano'] : 0;
    $unidadeId = isset($input['unidadeId']) ? (int)$input['unidadeId'] : 0;
    $dataInicio = trim($input['dataInicio'] ?? '') ?: null;
    $status = trim($input['status'] ?? '');
    $dataFim = trim($input['dataFim'] ?? '') ?: null;

    if ($nome === '' || $ano <= 0 || $unidadeId <= 0 || $status === '') {
        echo json_encode(['success' => false, 'message' => 'Nome, ano, unidade e status são obrigatórios.']);
        exit;
    }

    $status = strtoupper($status);
    if (!in_array($status, ['ABERTO', 'CONCLUIDO', 'SUSPENSO'])) {
        echo json_encode(['success' => false, 'message' => 'Status inválido.']);
        exit;
    }

    try {
        $query = 'INSERT INTO inventario (ano, nome, dataInicio, dataFim, status, unidadeId) VALUES (:ano, :nome, :dataInicio, :dataFim, :status, :unidadeId)';
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':ano', $ano, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':dataInicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindValue(':dataFim', $dataFim, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':unidadeId', $unidadeId, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Inventário cadastrado com sucesso.', 'id' => $conn->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar inventário: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
