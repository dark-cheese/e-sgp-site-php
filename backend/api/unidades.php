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
        $query = "SELECT u.id, u.nome, u.endereco, u.secretariaId, u.responsavelId,
            s.nome AS secretaria,
            r.nome AS responsavel,
            (SELECT COUNT(*) FROM departamento d WHERE d.unidadeId = u.id) AS departamentos,
            (SELECT COUNT(*) FROM item i JOIN departamento d ON i.departamentoId = d.id WHERE d.unidadeId = u.id) AS itens
        FROM unidade u
        LEFT JOIN secretaria s ON u.secretariaId = s.id
        LEFT JOIN responsavel r ON u.responsavelId = r.id
        ORDER BY u.nome";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar unidades: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nome = trim($input['nome'] ?? '');
    $secretariaId = isset($input['secretariaId']) ? (int)$input['secretariaId'] : 0;
    $endereco = trim($input['endereco'] ?? '');
    $responsavelId = isset($input['responsavelId']) && $input['responsavelId'] !== '' ? (int)$input['responsavelId'] : null;

    if ($nome === '') {
        echo json_encode(['success' => false, 'message' => 'O nome da unidade é obrigatório.']);
        exit;
    }

    if ($secretariaId <= 0) {
        echo json_encode(['success' => false, 'message' => 'A secretaria é obrigatória.']);
        exit;
    }

    try {
        $query = 'INSERT INTO unidade (nome, secretariaId, endereco, responsavelId) VALUES (:nome, :secretariaId, :endereco, :responsavelId)';
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':secretariaId', $secretariaId, PDO::PARAM_INT);
        $stmt->bindValue(':endereco', $endereco ?: null, PDO::PARAM_STR);
        if ($responsavelId !== null) {
            $stmt->bindValue(':responsavelId', $responsavelId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':responsavelId', null, PDO::PARAM_NULL);
        }
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Unidade cadastrada com sucesso.', 'id' => $conn->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar unidade: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
