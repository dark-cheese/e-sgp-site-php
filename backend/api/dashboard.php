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
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao conectar com o banco de dados.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metodo nao permitido.'
    ]);
    exit;
}

try {
    $totaisQuery = "SELECT
        (SELECT COUNT(*) FROM item) AS totalBens,
        (SELECT COUNT(*) FROM secretaria) AS totalSecretarias,
        (SELECT COUNT(*) FROM unidade) AS totalUnidades,
        (SELECT COUNT(*) FROM responsavel) AS totalResponsaveis";

    $totaisStmt = $conn->prepare($totaisQuery);
    $totaisStmt->execute();
    $totais = $totaisStmt->fetch(PDO::FETCH_ASSOC);

    $secretariasQuery = "SELECT
            s.id,
            s.nome,
            COUNT(i.id) AS itens
        FROM secretaria s
        LEFT JOIN unidade u ON u.secretariaId = s.id
        LEFT JOIN departamento d ON d.unidadeId = u.id
        LEFT JOIN item i ON i.departamentoId = d.id
        GROUP BY s.id, s.nome
        ORDER BY itens DESC, s.nome";

    $secretariasStmt = $conn->prepare($secretariasQuery);
    $secretariasStmt->execute();
    $secretarias = $secretariasStmt->fetchAll(PDO::FETCH_ASSOC);

    $historicos = [];
    try {
        $historicosQuery = "SELECT
                h.id,
                h.acao,
                h.tabelaAlvo,
                h.registroId,
                h.descricao,
                DATE_FORMAT(h.dataRegistro, '%d/%m/%Y %H:%i') AS dataRegistro,
                u.nome AS usuario
            FROM historico h
            LEFT JOIN usuario u ON h.usuarioId = u.id
            ORDER BY h.dataRegistro DESC, h.id DESC
            LIMIT 5";

        $historicosStmt = $conn->prepare($historicosQuery);
        $historicosStmt->execute();
        $historicos = $historicosStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Erro ao carregar historico recente do dashboard: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totais' => [
                'totalBens' => (int) ($totais['totalBens'] ?? 0),
                'totalSecretarias' => (int) ($totais['totalSecretarias'] ?? 0),
                'totalUnidades' => (int) ($totais['totalUnidades'] ?? 0),
                'totalResponsaveis' => (int) ($totais['totalResponsaveis'] ?? 0)
            ],
            'secretarias' => array_map(function ($secretaria) {
                $secretaria['itens'] = (int) ($secretaria['itens'] ?? 0);
                return $secretaria;
            }, $secretarias),
            'historicos' => $historicos
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar dashboard: ' . $e->getMessage()
    ]);
}
?>
