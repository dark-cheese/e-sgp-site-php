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
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// ============================================================
// GET – DADOS DO DASHBOARD
// ============================================================
try {
    /*
     * LÓGICA DA CONSULTA (totais):
     * - Usa subconsultas para contar quantos registros existem em cada tabela principal
     * - Isso dá uma visão geral do sistema: total de bens, secretarias, unidades, responsáveis
     */
    $totaisQuery = "SELECT
        (SELECT COUNT(*) FROM item) AS totalBens,
        (SELECT COUNT(*) FROM secretaria) AS totalSecretarias,
        (SELECT COUNT(*) FROM unidade) AS totalUnidades,
        (SELECT COUNT(*) FROM responsavel) AS totalResponsaveis";

    $totaisStmt = $conn->prepare($totaisQuery);
    $totaisStmt->execute();
    $totais = $totaisStmt->fetch(PDO::FETCH_ASSOC);

    /*
     * LÓGICA DA CONSULTA (secretarias por itens):
     * - Lista todas as secretarias com a quantidade de itens que cada uma possui
     * - Faz JOINs para percorrer: secretaria → unidade → departamento → item
     * - Agrupa por secretaria e ordena pela quantidade de itens (decrescente) e nome
     * - Isso cria um ranking das secretarias com mais bens
     */
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

    /*
     * LÓGICA DA CONSULTA (últimos eventos):
     * - Busca os últimos 5 registros do histórico para exibir na página inicial
     * - Inclui o nome do usuário que executou a ação
     * - Ordena do mais recente para o mais antigo
     */
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

    // Monta e retorna a resposta
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