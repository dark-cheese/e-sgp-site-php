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
// FUNÇÕES AUXILIARES
// ============================================================

// Verifica se a coluna 'responsavelId' existe na tabela 'inventario' (para compatibilidade)
function colunaExiste($conn, $tabela, $coluna) {
    $query = "SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :tabela
            AND COLUMN_NAME = :coluna";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':tabela', $tabela, PDO::PARAM_STR);
    $stmt->bindValue(':coluna', $coluna, PDO::PARAM_STR);
    $stmt->execute();
    return (int)$stmt->fetchColumn() > 0;
}

// Adiciona a coluna responsavelId se não existir (evita erro em versões antigas)
function garantirResponsavelNoInventario($conn) {
    if (colunaExiste($conn, 'inventario', 'responsavelId')) {
        return true;
    }
    try {
        $conn->exec("ALTER TABLE inventario ADD COLUMN responsavelId INTEGER NULL AFTER unidadeId");
        return true;
    } catch (PDOException $e) {
        error_log('Nao foi possivel adicionar responsavelId em inventario: ' . $e->getMessage());
        return false;
    }
}

// Busca itens de uma unidade dentro de um período (para pré-visualização e vinculação automática)
function buscarItensDoInventario($conn, $dataInicio, $dataFim, $unidadeId) {
    /*
     * LÓGICA DO SELECT:
     * - Busca itens de uma unidade específica, com data de aquisição entre dataInicio e dataFim
     * - JOIN com departamento e unidade para obter os nomes
     * - LEFT JOIN com responsável para obter nome do responsável
     * - LEFT JOIN com baixa (última) para saber se o item foi baixado e seu status
     * - Ordena por número de patrimônio
     */
    $query = "SELECT i.id, i.numeroPatrimonio, i.descricao, i.estado, i.dataAquisicao, i.valor,
        d.nome AS departamento,
        u.nome AS unidade,
        r.nome AS responsavel,
        b.tipo AS baixaTipo,
        CASE WHEN b.id IS NULL THEN 1 ELSE 0 END AS ativo,
        CASE WHEN b.id IS NULL THEN 'ATIVO' ELSE b.tipo END AS statusPatrimonio
    FROM item i
    JOIN departamento d ON i.departamentoId = d.id
    JOIN unidade u ON d.unidadeId = u.id
    LEFT JOIN responsavel r ON i.responsavelId = r.id
    LEFT JOIN baixa b ON b.itemId = i.id
        AND b.id = (SELECT MAX(b2.id) FROM baixa b2 WHERE b2.itemId = i.id)
    WHERE d.unidadeId = :unidadeId
        AND i.dataAquisicao IS NOT NULL
        AND i.dataAquisicao BETWEEN :dataInicio AND :dataFim
    ORDER BY i.numeroPatrimonio";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':unidadeId', $unidadeId, PDO::PARAM_INT);
    $stmt->bindValue(':dataInicio', $dataInicio, PDO::PARAM_STR);
    $stmt->bindValue(':dataFim', $dataFim, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function somarValorItens($itens) {
    return array_reduce($itens, function ($total, $item) {
        return $total + (float)($item['valor'] ?? 0);
    }, 0);
}

function buscarInventarioPorId($conn, $inventarioId, $temResponsavelInventario) {
    // Monta a consulta com ou sem responsavelId, dependendo se a coluna existe
    $responsavelSelect = $temResponsavelInventario
        ? "i.responsavelId, r.nome AS responsavel,"
        : "NULL AS responsavelId, NULL AS responsavel,";
    $responsavelJoin = $temResponsavelInventario
        ? "LEFT JOIN responsavel r ON i.responsavelId = r.id"
        : "";

    /*
     * LÓGICA DO SELECT:
     * - Busca um inventário específico pelo ID
     * - Inclui contagem de itens vinculados e soma do valor total
     * - Se a coluna responsavelId existir, busca também o responsável
     */
    $query = "SELECT i.id, i.ano, i.nome, i.dataInicio, i.dataFim, i.status, i.unidadeId,
        $responsavelSelect
        u.nome AS unidade,
        (SELECT COUNT(*) FROM inventario_item ii WHERE ii.inventarioId = i.id) AS itensVinculados,
        (SELECT COALESCE(SUM(item.valor), 0)
            FROM inventario_item ii
            JOIN item ON item.id = ii.itemId
            WHERE ii.inventarioId = i.id) AS valorTotal
    FROM inventario i
    LEFT JOIN unidade u ON i.unidadeId = u.id
    $responsavelJoin
    WHERE i.id = :inventarioId
    LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':inventarioId', $inventarioId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function buscarItensVinculadosAoInventario($conn, $inventarioId) {
    /*
     * LÓGICA DO SELECT:
     * - Busca todos os itens vinculados a um inventário (via tabela inventario_item)
     * - Inclui dados do item: número patrimônio, descrição, etc.
     * - Também inclui informações do departamento, unidade, responsável e status de baixa
     */
    $query = "SELECT ii.id AS inventarioItemId, ii.localizado, ii.estado AS estadoInventario, ii.observacao,
        i.id, i.numeroPatrimonio, i.descricao, i.marca, i.modelo, i.numeroSerie, i.estado,
        i.dataAquisicao, i.valor, i.notaFiscal,
        d.nome AS departamento,
        u.nome AS unidade,
        r.nome AS responsavel,
        b.tipo AS baixaTipo,
        CASE WHEN b.id IS NULL THEN 1 ELSE 0 END AS ativo,
        CASE WHEN b.id IS NULL THEN 'ATIVO' ELSE b.tipo END AS statusPatrimonio
    FROM inventario_item ii
    JOIN item i ON ii.itemId = i.id
    LEFT JOIN departamento d ON i.departamentoId = d.id
    LEFT JOIN unidade u ON d.unidadeId = u.id
    LEFT JOIN responsavel r ON i.responsavelId = r.id
    LEFT JOIN baixa b ON b.itemId = i.id
        AND b.id = (SELECT MAX(b2.id) FROM baixa b2 WHERE b2.itemId = i.id)
    WHERE ii.inventarioId = :inventarioId
    ORDER BY i.numeroPatrimonio";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':inventarioId', $inventarioId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================================
// GET – LISTAR INVENTÁRIOS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $temResponsavelInventario = garantirResponsavelNoInventario($conn);
        $inventarioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $unidadeId = isset($_GET['unidadeId']) ? (int)$_GET['unidadeId'] : 0;
        $dataInicio = trim($_GET['dataInicio'] ?? '');
        $dataFim = trim($_GET['dataFim'] ?? '');

        // Caso 1: Buscar inventário específico pelo ID
        if ($inventarioId > 0) {
            $inventario = buscarInventarioPorId($conn, $inventarioId, $temResponsavelInventario);
            if (!$inventario) {
                echo json_encode(['success' => false, 'message' => 'Inventario nao encontrado.']);
                exit;
            }
            $itens = buscarItensVinculadosAoInventario($conn, $inventarioId);
            $inventario['itensVinculados'] = count($itens);
            $inventario['valorTotal'] = somarValorItens($itens);

            echo json_encode([
                'success' => true,
                'data' => [
                    'inventario' => $inventario,
                    'itens' => $itens,
                    'totalItens' => count($itens),
                    'valorTotal' => somarValorItens($itens)
                ]
            ]);
            exit;
        }

        // Caso 2: Buscar itens para pré-visualização (com data e unidade)
        if (isset($_GET['itens'])) {
            if ($dataInicio === '' || $dataFim === '' || $unidadeId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Data inicial, data final e unidade são obrigatórias.']);
                exit;
            }
            if ($dataInicio > $dataFim) {
                echo json_encode(['success' => false, 'message' => 'A data inicial não pode ser maior que a data final.']);
                exit;
            }
            $itens = buscarItensDoInventario($conn, $dataInicio, $dataFim, $unidadeId);
            echo json_encode([
                'success' => true,
                'data' => $itens,
                'totalItens' => count($itens),
                'valorTotal' => somarValorItens($itens)
            ]);
            exit;
        }

        // Caso 3: Listar todos os inventários
        $responsavelSelect = $temResponsavelInventario
            ? "i.responsavelId, r.nome AS responsavel,"
            : "NULL AS responsavelId, NULL AS responsavel,";
        $responsavelJoin = $temResponsavelInventario
            ? "LEFT JOIN responsavel r ON i.responsavelId = r.id"
            : "";

        /*
         * LÓGICA DO SELECT:
         * - Lista todos os inventários com contagem de itens e valor total
         * - Inclui responsável se a coluna existir
         * - Ordena por ano (mais recente) e nome
         */
        $query = "SELECT i.id, i.ano, i.nome, i.dataInicio, i.dataFim, i.status, i.unidadeId,
            $responsavelSelect
            u.nome AS unidade,
            (SELECT COUNT(*) FROM inventario_item ii WHERE ii.inventarioId = i.id) AS itensVinculados,
            (SELECT COALESCE(SUM(item.valor), 0)
                FROM inventario_item ii
                JOIN item ON item.id = ii.itemId
                WHERE ii.inventarioId = i.id) AS valorTotal
        FROM inventario i
        LEFT JOIN unidade u ON i.unidadeId = u.id
        $responsavelJoin
        ORDER BY i.ano DESC, i.nome";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar inventarios: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST – CADASTRAR INVENTÁRIO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nome = trim($input['nome'] ?? '');
    $ano = isset($input['ano']) ? (int)$input['ano'] : 0;
    $unidadeId = isset($input['unidadeId']) ? (int)$input['unidadeId'] : 0;
    $responsavelId = isset($input['responsavelId']) ? (int)$input['responsavelId'] : 0;
    $dataInicio = trim($input['dataInicio'] ?? '') ?: null;
    $status = trim($input['status'] ?? '');
    $dataFim = trim($input['dataFim'] ?? '') ?: null;

    // Validações...
    if ($nome === '' || $ano <= 0 || $unidadeId <= 0 || $responsavelId <= 0 || $dataInicio === null || $dataFim === null || $status === '') {
        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
        exit;
    }
    if ($dataInicio > $dataFim) {
        echo json_encode(['success' => false, 'message' => 'A data inicial não pode ser maior que a data final.']);
        exit;
    }
    $status = strtoupper($status);
    if (!in_array($status, ['ABERTO', 'CONCLUIDO', 'SUSPENSO'])) {
        echo json_encode(['success' => false, 'message' => 'Status inválido.']);
        exit;
    }

    try {
        $temResponsavelInventario = garantirResponsavelNoInventario($conn);
        $conn->beginTransaction();

        /*
         * LÓGICA DO INSERT (inventário):
         * - Insere o inventário com os dados fornecidos
         * - Se a coluna responsavelId existe, insere também
         * - O ID é gerado automaticamente
         */
        if ($temResponsavelInventario) {
            $query = 'INSERT INTO inventario (ano, nome, dataInicio, dataFim, status, unidadeId, responsavelId)
                VALUES (:ano, :nome, :dataInicio, :dataFim, :status, :unidadeId, :responsavelId)';
        } else {
            $query = 'INSERT INTO inventario (ano, nome, dataInicio, dataFim, status, unidadeId)
                VALUES (:ano, :nome, :dataInicio, :dataFim, :status, :unidadeId)';
        }
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':ano', $ano, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':dataInicio', $dataInicio, PDO::PARAM_STR);
        $stmt->bindValue(':dataFim', $dataFim, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':unidadeId', $unidadeId, PDO::PARAM_INT);
        if ($temResponsavelInventario) {
            $stmt->bindValue(':responsavelId', $responsavelId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $id = (int) $conn->lastInsertId();

        // Busca os itens da unidade no período e vincula automaticamente
        $itens = buscarItensDoInventario($conn, $dataInicio, $dataFim, $unidadeId);

        /*
         * LÓGICA DO INSERT (inventario_item):
         * - Para cada item encontrado no período, insere um registro na tabela inventario_item
         * - Define localizado = TRUE (padrão), estado = estado atual do item, observação informativa
         * - Isso cria o vínculo entre o inventário e os itens
         */
        $itemInventarioStmt = $conn->prepare("INSERT INTO inventario_item (inventarioId, itemId, localizado, estado, observacao)
            VALUES (:inventarioId, :itemId, TRUE, :estado, :observacao)");

        foreach ($itens as $item) {
            $itemInventarioStmt->bindValue(':inventarioId', $id, PDO::PARAM_INT);
            $itemInventarioStmt->bindValue(':itemId', (int)$item['id'], PDO::PARAM_INT);
            $itemInventarioStmt->bindValue(':estado', $item['estado'], PDO::PARAM_STR);
            $itemInventarioStmt->bindValue(':observacao', 'Vinculado automaticamente pelo inventario do periodo ' . $dataInicio . ' a ' . $dataFim . '.', PDO::PARAM_STR);
            $itemInventarioStmt->execute();
        }

        registrarHistorico(
            $conn,
            'CRIAR',
            'inventario',
            $id,
            'Cadastrou o inventario "' . $nome . '" do periodo ' . $dataInicio . ' a ' . $dataFim . ' com ' . count($itens) . ' itens vinculados.',
            obterUsuarioIdDaRequisicao($input)
        );

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Inventario cadastrado com sucesso.',
            'id' => $id,
            'itensVinculados' => count($itens),
            'valorTotal' => somarValorItens($itens)
        ]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar inventario: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);