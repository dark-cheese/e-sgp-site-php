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
require_once __DIR__ . '/../includes/auditoria.php';

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

function buscarTodos($conn, $query, $params = []) {
    $stmt = $conn->prepare($query);
    foreach ($params as $nome => $valor) {
        $stmt->bindValue($nome, $valor, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function idsDaLista($lista, $campo = 'id') {
    return array_values(array_unique(array_map(function ($item) use ($campo) {
        return (int) $item[$campo];
    }, $lista)));
}

try {
    $usuarioId = obterUsuarioIdDaRequisicao($_GET);

    $usuarioStmt = $conn->prepare("SELECT u.id, u.nome, u.email, tu.nome AS tipoUsuario
        FROM usuario u
        LEFT JOIN tipo_usuario tu ON u.tipoUsuarioId = tu.id
        WHERE u.id = :usuarioId
        LIMIT 1");
    $usuarioStmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $usuarioStmt->execute();
    $usuario = $usuarioStmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario nao encontrado.']);
        exit;
    }

    $tipoUsuario = strtolower($usuario['tipoUsuario'] ?? '');
    $admin = in_array($tipoUsuario, ['admin', 'gestor']);
    $responsavel = null;

    if (!$admin) {
        $responsavelStmt = $conn->prepare("SELECT id, nome, cargo FROM responsavel WHERE usuarioId = :usuarioId LIMIT 1");
        $responsavelStmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
        $responsavelStmt->execute();
        $responsavel = $responsavelStmt->fetch(PDO::FETCH_ASSOC);

        if (!$responsavel) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'nivel' => 'sem_acesso',
                    'usuario' => $usuario,
                    'responsavel' => null,
                    'secretarias' => [],
                    'unidades' => [],
                    'departamentos' => [],
                    'responsaveis' => []
                ]
            ]);
            exit;
        }
    }

    if ($admin) {
        $secretarias = buscarTodos($conn, "SELECT id, nome FROM secretaria ORDER BY nome");
        $unidades = buscarTodos($conn, "SELECT id, nome, secretariaId FROM unidade ORDER BY nome");
        $departamentos = buscarTodos($conn, "SELECT d.id, d.nome, d.unidadeId, u.secretariaId
            FROM departamento d
            JOIN unidade u ON d.unidadeId = u.id
            ORDER BY d.nome");
        $responsaveis = buscarTodos($conn, "SELECT id, nome, cargo FROM responsavel ORDER BY nome");
        $nivel = 'admin';
    } else {
        $responsavelId = (int) $responsavel['id'];

        $departamentos = buscarTodos($conn, "SELECT d.id, d.nome, d.unidadeId, u.secretariaId
            FROM departamento d
            JOIN unidade u ON d.unidadeId = u.id
            WHERE d.responsavelId = :responsavelId
            ORDER BY d.nome", [':responsavelId' => $responsavelId]);

        if ($departamentos) {
            $nivel = 'departamento';
            $unidadeIds = idsDaLista($departamentos, 'unidadeId');
            $secretariaIds = idsDaLista($departamentos, 'secretariaId');
        } else {
            $unidades = buscarTodos($conn, "SELECT id, nome, secretariaId
                FROM unidade
                WHERE responsavelId = :responsavelId
                ORDER BY nome", [':responsavelId' => $responsavelId]);

            if ($unidades) {
                $nivel = 'unidade';
                $unidadeIds = idsDaLista($unidades);
                $secretariaIds = idsDaLista($unidades, 'secretariaId');
                $placeholders = implode(',', array_fill(0, count($unidadeIds), '?'));
                $stmt = $conn->prepare("SELECT d.id, d.nome, d.unidadeId, u.secretariaId
                    FROM departamento d
                    JOIN unidade u ON d.unidadeId = u.id
                    WHERE d.unidadeId IN ($placeholders)
                    ORDER BY d.nome");
                foreach ($unidadeIds as $index => $id) {
                    $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
                }
                $stmt->execute();
                $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $secretarias = buscarTodos($conn, "SELECT id, nome
                    FROM secretaria
                    WHERE responsavelId = :responsavelId
                    ORDER BY nome", [':responsavelId' => $responsavelId]);

                if (!$secretarias) {
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'nivel' => 'sem_acesso',
                            'usuario' => $usuario,
                            'responsavel' => $responsavel,
                            'secretarias' => [],
                            'unidades' => [],
                            'departamentos' => [],
                            'responsaveis' => []
                        ]
                    ]);
                    exit;
                }

                $nivel = 'secretaria';
                $secretariaIds = idsDaLista($secretarias);
                $placeholders = implode(',', array_fill(0, count($secretariaIds), '?'));
                $stmt = $conn->prepare("SELECT id, nome, secretariaId
                    FROM unidade
                    WHERE secretariaId IN ($placeholders)
                    ORDER BY nome");
                foreach ($secretariaIds as $index => $id) {
                    $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
                }
                $stmt->execute();
                $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $unidadeIds = idsDaLista($unidades);
                $departamentos = [];
                if ($unidadeIds) {
                    $placeholdersUnidades = implode(',', array_fill(0, count($unidadeIds), '?'));
                    $stmt = $conn->prepare("SELECT d.id, d.nome, d.unidadeId, u.secretariaId
                        FROM departamento d
                        JOIN unidade u ON d.unidadeId = u.id
                        WHERE d.unidadeId IN ($placeholdersUnidades)
                        ORDER BY d.nome");
                    foreach ($unidadeIds as $index => $id) {
                        $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
                    }
                    $stmt->execute();
                    $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }

        if (!isset($secretarias)) {
            $placeholdersSecretarias = implode(',', array_fill(0, count($secretariaIds), '?'));
            $stmt = $conn->prepare("SELECT id, nome FROM secretaria WHERE id IN ($placeholdersSecretarias) ORDER BY nome");
            foreach ($secretariaIds as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $secretarias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (!isset($unidades)) {
            $placeholdersUnidades = implode(',', array_fill(0, count($unidadeIds), '?'));
            $stmt = $conn->prepare("SELECT id, nome, secretariaId FROM unidade WHERE id IN ($placeholdersUnidades) ORDER BY nome");
            foreach ($unidadeIds as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $responsaveis = [$responsavel];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'nivel' => $nivel,
            'usuario' => $usuario,
            'responsavel' => $responsavel,
            'secretarias' => $secretarias,
            'unidades' => $unidades,
            'departamentos' => $departamentos,
            'responsaveis' => $responsaveis
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar acesso: ' . $e->getMessage()]);
}
?>
