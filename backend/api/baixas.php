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
        $query = "SELECT b.id, b.itemId, b.tipo, b.dataBaixa, b.justificativa, b.documento,
            i.numeroPatrimonio AS itemNumero, i.descricao AS itemDescricao
        FROM baixa b
        LEFT JOIN item i ON b.itemId = i.id
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $itemId = isset($input['itemId']) ? (int)$input['itemId'] : 0;
    $tipo = trim($input['tipo'] ?? '');
    $dataBaixa = trim($input['dataBaixa'] ?? '');
    $justificativa = trim($input['justificativa'] ?? '');
    $documento = trim($input['documento'] ?? '') ?: null;

    if ($itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'O item é obrigatório.']);
        exit;
    }

    if ($tipo === '') {
        echo json_encode(['success' => false, 'message' => 'O tipo de baixa é obrigatório.']);
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
        $query = 'INSERT INTO baixa (itemId, tipo, dataBaixa, justificativa, documento) VALUES (:itemId, :tipo, :dataBaixa, :justificativa, :documento)';
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':itemId', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':dataBaixa', $dataBaixa, PDO::PARAM_STR);
        $stmt->bindValue(':justificativa', $justificativa, PDO::PARAM_STR);
        $stmt->bindValue(':documento', $documento, PDO::PARAM_STR);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Baixa registrada com sucesso.', 'id' => $conn->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao registrar baixa: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
