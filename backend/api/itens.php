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
        $query = "SELECT i.id, i.numeroPatrimonio, i.descricao, i.marca, i.modelo, i.numeroSerie, i.estado,
            i.valor, i.notaFiscal, i.dataAquisicao, i.observacoes, i.departamentoId, i.localizacaoId, i.responsavelId, i.tipoMaterialId,
            d.nome AS departamento,
            u.nome AS unidade,
            r.nome AS responsavel,
            tm.nome AS tipoMaterial
        FROM item i
        LEFT JOIN departamento d ON i.departamentoId = d.id
        LEFT JOIN unidade u ON d.unidadeId = u.id
        LEFT JOIN responsavel r ON i.responsavelId = r.id
        LEFT JOIN tipo_material tm ON i.tipoMaterialId = tm.id
        ORDER BY i.numeroPatrimonio";

        $stmt = $conn->prepare($query);
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

    try {
        $numeroPatrimonio = 'PATR' . date('YmdHis') . rand(100, 999);
        // Linha 84 - corrigir INSERT removendo campo 'foto' e ajustando ordem
        $query = "INSERT INTO item (numeroPatrimonio, descricao, marca, modelo, numeroSerie, estado, dataAquisicao, valor, notaFiscal, observacoes, departamentoId, localizacaoId, tipoMaterialId, responsavelId) VALUES (:numeroPatrimonio, :descricao, :marca, :modelo, :numeroSerie, :estado, :dataAquisicao, :valor, :notaFiscal, :observacoes, :departamentoId, :localizacaoId, :tipoMaterialId, :responsavelId)";

        // Remover a linha que define :foto (linha 99)
        // Remover: $stmt->bindValue(':foto', null, PDO::PARAM_NULL);
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
        $stmt->bindValue(':foto', null, PDO::PARAM_NULL);
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

        echo json_encode(['success' => true, 'message' => 'Item cadastrado com sucesso.', 'id' => $conn->lastInsertId(), 'numeroPatrimonio' => $numeroPatrimonio]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar item: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
