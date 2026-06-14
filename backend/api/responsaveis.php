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
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao conectar com o banco de dados.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT r.id, r.nome, r.cargo, u.email AS email FROM responsavel r LEFT JOIN usuario u ON r.usuarioId = u.id ORDER BY r.nome";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $responsaveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $responsaveis
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar responsáveis: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $nome = trim($input['nome'] ?? '');
    $cargo = trim($input['cargo'] ?? '');
    $email = trim($input['email'] ?? '');

    if ($nome === '' || $cargo === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Nome e cargo são obrigatórios para cadastrar o responsável.'
        ]);
        exit;
    }

    $usuarioId = null;
    if ($email !== '') {
        try {
            $userQuery = "SELECT id FROM usuario WHERE email = :email LIMIT 1";
            $userStmt = $conn->prepare($userQuery);
            $userStmt->bindValue(':email', $email, PDO::PARAM_STR);
            $userStmt->execute();
            $usuario = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($usuario) {
                $usuarioId = (int)$usuario['id'];
            }
        } catch (PDOException $e) {
            // Ignorar erro de usuário vinculado; vamos cadastrar apenas o responsavel.
        }
    }

    try {
        if ($usuarioId !== null) {
            $query = "INSERT INTO responsavel (nome, cargo, usuarioId) VALUES (:nome, :cargo, :usuarioId)";
        } else {
            $query = "INSERT INTO responsavel (nome, cargo) VALUES (:nome, :cargo)";
        }
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
        $stmt->bindValue(':cargo', $cargo, PDO::PARAM_STR);
        if ($usuarioId !== null) {
            $stmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $id = (int) $conn->lastInsertId();
        registrarHistorico(
            $conn,
            'CRIAR',
            'responsavel',
            $id,
            'Cadastrou o responsavel "' . $nome . '".',
            obterUsuarioIdDaRequisicao($input)
        );

        echo json_encode([
            'success' => true,
            'message' => 'Responsável cadastrado com sucesso.',
            'id' => $id
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao cadastrar responsável: ' . $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Método não permitido.'
]);
?>
