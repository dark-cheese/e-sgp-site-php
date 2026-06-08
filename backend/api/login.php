<?php
// backend/api/login.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->senha)) {
    echo json_encode([
        "success" => false,
        "message" => "E-mail e senha são obrigatórios!"
    ]);
    exit();
}

$email = trim($data->email);
$senha = trim($data->senha);

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode([
        "success" => false,
        "message" => "Erro ao conectar com o banco de dados. Tente novamente."
    ]);
    exit();
}

$stmt = $db->prepare("SELECT id, nome, email, senha FROM usuario WHERE email = :email LIMIT 1");
$stmt->bindParam(':email', $email);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo json_encode(["success" => false, "message" => "E-mail ou senha incorretos!"]);
    exit();
}

$senhaValida = password_verify($senha, $usuario['senha']) || $senha === $usuario['senha'];

if (!$senhaValida) {
    echo json_encode(["success" => false, "message" => "E-mail ou senha incorretos!"]);
    exit();
}

echo json_encode([
    "success" => true,
    "message" => "Login realizado com sucesso!",
    "usuario" => [
        "id"    => (int) $usuario['id'],
        "nome"  => $usuario['nome'],
        "email" => $usuario['email']
    ]
]);
?>
