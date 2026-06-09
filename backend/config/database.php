<?php
// backend/config/database.php

class Database {
    private $host     = "200.131.251.11";
    private $port     = 3341;
    private $db_name  = "2026ProjetoInv";
    private $username = "2026Iventario";
    private $password = "Inventa@2026";
    public  $conn;

    // Obtém a conexão com o banco de dados
    public function getConnection() {
        $this->conn = null;

        try {

            $dsn = "mysql:host={$this->host};
            port={$this->port};
            dbname={$this->db_name}; 
            charset=utf8;connect_timeout=5"; //Define tempo limite de 5 segundos para conexão

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
                // PDO::ATTR_PERSISTENT => true // Habilita conexões persistentes para melhor desempenho
            ]);
            $this->conn->exec("set names utf8"); // Garante que a conexão use UTF-8
        } catch (PDOException $e) {
            error_log("Erro de conexão: " . $e->getMessage());
        }

        return $this->conn; // Retorna a conexão (pode ser null se falhar)
    }
}
?>
