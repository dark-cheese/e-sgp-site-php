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

function usuarioPodeCadastrarItemNoDepartamento($conn, $usuarioId, $departamentoId) {
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $departamentoId = isset($_GET['departamentoId']) ? (int)$_GET['departamentoId'] : 0;
        $somenteAtivos = isset($_GET['ativos']) && (int)$_GET['ativos'] === 1;
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

    if ($descricao === '') {
        echo json_encode(['success' => false, 'message' => 'A descriÃƒÆ’Ã‚Â§ÃƒÆ’Ã‚Â£o do item ÃƒÆ’Ã‚Â© obrigatÃƒÆ’Ã‚Â³ria.']);
        exit;
    }

    if ($tipoMaterialId <= 0) {
        echo json_encode(['success' => false, 'message' => 'O tipo de material ÃƒÆ’Ã‚Â© obrigatÃƒÆ’Ã‚Â³rio.']);
        exit;
    }

    if ($estado === '') {
        echo json_encode(['success' => false, 'message' => 'O estado do item ÃƒÆ’Ã‚Â© obrigatÃƒÆ’Ã‚Â³rio.']);
        exit;
    }

    if ($departamentoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'O departamento e obrigatorio.']);
        exit;
    }

    $usuarioId = obterUsuarioIdDaRequisicao($input);
    if (!usuarioPodeCadastrarItemNoDepartamento($conn, $usuarioId, $departamentoId)) {
        echo json_encode(['success' => false, 'message' => 'Voce nao tem acesso para cadastrar patrimonio neste departamento.']);
        exit;
    }

    if (!localizacaoPertenceAoDepartamento($conn, $localizacaoId, $departamentoId)) {
        echo json_encode(['success' => false, 'message' => 'A localizacao selecionada nao pertence ao departamento informado.']);
        exit;
    }

    try {
        $numeroPatrimonio = gerarNumeroPatrimonio($conn);
        $query = "INSERT INTO item (numeroPatrimonio, descricao, marca, modelo, numeroSerie, estado, dataAquisicao, valor, notaFiscal, observacoes, departamentoId, localizacaoId, tipoMaterialId, responsavelId) VALUES (:numeroPatrimonio, :descricao, :marca, :modelo, :numeroSerie, :estado, :dataAquisicao, :valor, :notaFiscal, :observacoes, :departamentoId, :localizacaoId, :tipoMaterialId, :responsavelId)";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':numeroPatrimonio', $numeroPatrimonio, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $descricao, PDO::PARAM_STR);
        $stmt->bindValue(':marca', $marca ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':modelo', $modelo ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':numeroSerie', $numeroSerie ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':dataAquisicao', $dataAquisicao, PDO::PARAM_STR);
        if ($valor !== null) {
            $stmt->bindValue(':valor', $valor, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':valor', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':notaFiscal', $notaFiscal ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':observacoes', $observacoes ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':departamentoId', $departamentoId, PDO::PARAM_INT);
        if ($localizacaoId !== null) {
            $stmt->bindValue(':localizacaoId', $localizacaoId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':localizacaoId', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':tipoMaterialId', $tipoMaterialId, PDO::PARAM_INT);
        if ($responsavelId !== null) {
            $stmt->bindValue(':responsavelId', $responsavelId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':responsavelId', null, PDO::PARAM_NULL);
        }
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
echo json_encode(['success' => false, 'message' => 'MÃƒÆ’Ã‚Â©todo nÃƒÆ’Ã‚Â£o permitido.']);
