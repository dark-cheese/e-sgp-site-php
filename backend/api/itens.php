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
// FUNÇÕES AUXILIARES (validação de permissão)
// ============================================================

// Verifica se o usuário pode cadastrar item no departamento informado
function usuarioPodeCadastrarItemNoDepartamento($conn, $usuarioId, $departamentoId) {
    // Se for admin ou gestor, tem acesso a tudo
    $usuarioStmt = $conn->prepare("SELECT tu.nome AS tipoUsuario
        FROM usuario u
        LEFT JOIN tipo_usuario tu ON u.tipoUsuarioId = tu.id
        WHERE u.id = :usuarioId
        LIMIT 1");
    $usuarioStmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $usuarioStmt->execute();
    $usuario = $usuarioStmt->fetch(PDO::FETCH_ASSOC);
    $tipoUsuario = strtolower($usuario['tipoUsuario'] ?? '');

    if (in_array($tipoUsuario, ['admin', 'gestor'])) {
        return true;
    }

    // Para usuários comuns, verifica se ele é responsável pelo departamento, unidade ou secretaria
    $responsavelStmt = $conn->prepare("SELECT id FROM responsavel WHERE usuarioId = :usuarioId LIMIT 1");
    $responsavelStmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $responsavelStmt->execute();
    $responsavel = $responsavelStmt->fetch(PDO::FETCH_ASSOC);

    if (!$responsavel) {
        return false;
    }

    $responsavelId = (int) $responsavel['id'];
    $acessoStmt = $conn->prepare("SELECT d.id
        FROM departamento d
        JOIN unidade u ON d.unidadeId = u.id
        JOIN secretaria s ON u.secretariaId = s.id
        WHERE d.id = :departamentoId
            AND (
                d.responsavelId = :responsavelId
                OR u.responsavelId = :responsavelId
                OR s.responsavelId = :responsavelId
            )
        LIMIT 1");
    $acessoStmt->bindValue(':departamentoId', $departamentoId, PDO::PARAM_INT);
    $acessoStmt->bindValue(':responsavelId', $responsavelId, PDO::PARAM_INT);
    $acessoStmt->execute();

    return (bool) $acessoStmt->fetch(PDO::FETCH_ASSOC);
}

// Verifica se a localização pertence ao departamento informado
function localizacaoPertenceAoDepartamento($conn, $localizacaoId, $departamentoId) {
    if ($localizacaoId === null) {
        return true;
    }
    $stmt = $conn->prepare("SELECT id FROM localizacao WHERE id = :localizacaoId AND departamentoId = :departamentoId LIMIT 1");
    $stmt->bindValue(':localizacaoId', $localizacaoId, PDO::PARAM_INT);
    $stmt->bindValue(':departamentoId', $departamentoId, PDO::PARAM_INT);
    $stmt->execute();
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

// Gera um número de patrimônio único (com tentativas para evitar duplicação)
function gerarNumeroPatrimonio($conn) {
    for ($tentativa = 0; $tentativa < 10; $tentativa++) {
        $numeroPatrimonio = 'PATR' . date('ymdHis') . rand(10, 99);
        $stmt = $conn->prepare("SELECT id FROM item WHERE numeroPatrimonio = :numeroPatrimonio LIMIT 1");
        $stmt->bindValue(':numeroPatrimonio', $numeroPatrimonio, PDO::PARAM_STR);
        $stmt->execute();
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $numeroPatrimonio;
        }
    }
    return 'PATR' . substr(str_replace('.', '', uniqid('', true)), 0, 16);
}

// ============================================================
// GET – LISTAR ITENS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $departamentoId = isset($_GET['departamentoId']) ? (int)$_GET['departamentoId'] : 0;
        $somenteAtivos = isset($_GET['ativos']) && (int)$_GET['ativos'] === 1;

        /*
         * LÓGICA DA CONSULTA:
         * - Busca todos os itens com JOINs para obter:
         *   - departamento, unidade, secretaria (via chaves estrangeiras)
         *   - responsável, tipo de material
         * - LEFT JOIN com baixa (usando subconsulta para pegar a ÚLTIMA baixa de cada item)
         * - Calcula se o item está ativo (se NÃO tem baixa) e o status (ATIVO ou tipo da baixa)
         * - Filtra por departamentoId se veio na URL
         * - Opcionalmente filtra apenas ativos (b.id IS NULL)
         * - Ordena por número de patrimônio
         */
        $query = "SELECT i.id, i.numeroPatrimonio, i.descricao, i.marca, i.modelo, i.numeroSerie, i.estado,
            i.valor, i.notaFiscal, i.dataAquisicao, i.observacoes, i.departamentoId, i.localizacaoId, i.responsavelId, i.tipoMaterialId,
            d.nome AS departamento,
            d.unidadeId,
            u.nome AS unidade,
            u.secretariaId,
            r.nome AS responsavel,
            tm.nome AS tipoMaterial,
            b.id AS baixaId,
            b.tipo AS baixaTipo,
            b.dataBaixa,
            CASE WHEN b.id IS NULL THEN 1 ELSE 0 END AS ativo,
            CASE WHEN b.id IS NULL THEN 'ATIVO' ELSE b.tipo END AS statusPatrimonio
        FROM item i
        LEFT JOIN departamento d ON i.departamentoId = d.id
        LEFT JOIN unidade u ON d.unidadeId = u.id
        LEFT JOIN responsavel r ON i.responsavelId = r.id
        LEFT JOIN tipo_material tm ON i.tipoMaterialId = tm.id
        LEFT JOIN baixa b ON b.itemId = i.id
            AND b.id = (SELECT MAX(b2.id) FROM baixa b2 WHERE b2.itemId = i.id)";

        $where = [];

        if ($departamentoId > 0) {
            $where[] = "i.departamentoId = :departamentoId";
        }

        if ($somenteAtivos) {
            $where[] = "b.id IS NULL";
        }

        if ($where) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " ORDER BY i.numeroPatrimonio";

        $stmt = $conn->prepare($query);
        if ($departamentoId > 0) {
            $stmt->bindValue(':departamentoId', $departamentoId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar itens: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST – CADASTRAR ITEM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $descricao = trim($input['descricao'] ?? '');
    $tipoMaterialId = isset($input['tipoMaterialId']) ? (int)$input['tipoMaterialId'] : 0;
    $estado = trim($input['estado'] ?? '');
    $departamentoId = isset($input['departamentoId']) ? (int)$input['departamentoId'] : 0;
    $localizacaoId = isset($input['localizacaoId']) && $input['localizacaoId'] !== '' ? (int)$input['localizacaoId'] : null;
    $responsavelId = isset($input['responsavelId']) && $input['responsavelId'] !== '' ? (int)$input['responsavelId'] : null;
    $marca = trim($input['marca'] ?? '');
    $modelo = trim($input['modelo'] ?? '');
    $numeroSerie = trim($input['numeroSerie'] ?? '');
    $dataAquisicao = trim($input['dataAquisicao'] ?? '') ?: null;
    $valor = isset($input['valor']) && $input['valor'] !== '' ? $input['valor'] : null;
    $notaFiscal = trim($input['notaFiscal'] ?? '');
    $observacoes = trim($input['observacoes'] ?? '');

    // Validações...
    if ($descricao === '') {
        echo json_encode(['success' => false, 'message' => 'A descrição do item é obrigatória.']);
        exit;
    }
    if ($tipoMaterialId <= 0) {
        echo json_encode(['success' => false, 'message' => 'O tipo de material é obrigatório.']);
        exit;
    }
    if ($estado === '') {
        echo json_encode(['success' => false, 'message' => 'O estado do item é obrigatório.']);
        exit;
    }
    if ($departamentoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'O departamento é obrigatório.']);
        exit;
    }

    // Verifica permissão do usuário para cadastrar neste departamento
    $usuarioId = obterUsuarioIdDaRequisicao($input);
    if (!usuarioPodeCadastrarItemNoDepartamento($conn, $usuarioId, $departamentoId)) {
        echo json_encode(['success' => false, 'message' => 'Você não tem acesso para cadastrar patrimônio neste departamento.']);
        exit;
    }

    // Verifica se a localização pertence ao departamento
    if (!localizacaoPertenceAoDepartamento($conn, $localizacaoId, $departamentoId)) {
        echo json_encode(['success' => false, 'message' => 'A localização selecionada não pertence ao departamento informado.']);
        exit;
    }

    try {
        // Gera número de patrimônio único
        $numeroPatrimonio = gerarNumeroPatrimonio($conn);

        /*
         * LÓGICA DO INSERT:
         * - Insere o item com todos os dados fornecidos
         * - O número de patrimônio é gerado automaticamente (PATR + timestamp + rand)
         * - Os campos opcionais (marca, modelo, etc.) podem ser nulos
         * - Registra no histórico
         */
        $query = "INSERT INTO item (numeroPatrimonio, descricao, marca, modelo, numeroSerie, estado, dataAquisicao, valor, notaFiscal, observacoes, departamentoId, localizacaoId, tipoMaterialId, responsavelId)
            VALUES (:numeroPatrimonio, :descricao, :marca, :modelo, :numeroSerie, :estado, :dataAquisicao, :valor, :notaFiscal, :observacoes, :departamentoId, :localizacaoId, :tipoMaterialId, :responsavelId)";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':numeroPatrimonio', $numeroPatrimonio, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
        $stmt->bindValue(':marca', $marca ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':modelo', $modelo ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':numeroSerie', $numeroSerie ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':dataAquisicao', $dataAquisicao, PDO::PARAM_STR);
        $stmt->bindValue(':valor', $valor !== null ? $valor : null, PDO::PARAM_STR);
        $stmt->bindValue(':notaFiscal', $notaFiscal ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':observacoes', $observacoes ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':departamentoId', $departamentoId, PDO::PARAM_INT);
        $stmt->bindValue(':localizacaoId', $localizacaoId ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':tipoMaterialId', $tipoMaterialId, PDO::PARAM_INT);
        $stmt->bindValue(':responsavelId', $responsavelId ?? null, PDO::PARAM_INT);
        $stmt->execute();
        $id = (int) $conn->lastInsertId();

        registrarHistorico(
            $conn,
            'CRIAR',
            'item',
            $id,
            'Cadastrou o item "' . $descricao . '" com patrimonio ' . $numeroPatrimonio . '.',
            $usuarioId
        );

        echo json_encode(['success' => true, 'message' => 'Item cadastrado com sucesso.', 'id' => $id, 'numeroPatrimonio' => $numeroPatrimonio]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar item: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);