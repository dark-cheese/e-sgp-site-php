<?php
// Caminho correto para o arquivo de configuração
require_once '../config/database.php'; //Ta importando o caminho do banco de dados [require] = esse arquivo é obrigatório,once: só carrega uma vez mesmo se chamar de novo

// Instancia a classe Database
$database = new Database(); //Aqui está sendo criado um objeto da classe Database
$conn = $database->getConnection(); //está chamando o método getConnection conn: é connection

// Verifica a conexão
if ($conn) {
    echo "Conexão com o banco de dados estabelecida com SUCESSO!";
} else {
    echo "Falha ao conectar ao banco de dados.";
}
?>