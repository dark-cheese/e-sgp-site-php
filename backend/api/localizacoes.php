<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Usuario-Id");

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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido.']);
    exit;
}

try {
    $departamentoId = isset($_GET['departamentoId']) ? (int) $_GET['departamentoId'] : 0;

    /*
     * LÓGICA DO SELECT:
     * - Busca todas as localizações
     * - Se veio departamentoId, filtra por ele (WHERE departamentoId = :departamentoId)
     * - Ordena por nome
     * - Usado para preencher o select de localização no formulário de cadastro de itens
     */
    $query = "SELECT id, departamentoId, nome, descricao FROM localizacao";

    if ($departamentoId > 0) {
        $query .= " WHERE departamentoId = :departamentoId";
    }

    $query .= " ORDER BY nome";

    $stmt = $conn->prepare($query);
    if ($departamentoId > 0) {
        $stmt->bindValue(':departamentoId', $departamentoId, PDO::PARAM_INT);
    }
    $stmt->execute();

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar localizacoes: ' . $e->getMessage()]);
}