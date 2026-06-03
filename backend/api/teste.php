<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

if($conn) {
    echo "Conexão com o banco de dados FUNCIONOU! ✅";
} else {
    echo "Falha na conexão ❌";
}
?>