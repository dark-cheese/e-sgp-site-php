<?php
// backend/config/database.php

class Database {
    private $host     = "200.131.251.11";
    private $port     = 3341;
    private $db_name  = "2026ProjetoInv";
    private $username = "2026Iventario";
    private $password = "Inventa@2026";
    public  $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8;connect_timeout=5";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            $this->conn->exec("set names utf8");
        } catch (PDOException $e) {
            error_log("Erro de conexão: " . $e->getMessage());
        }

        return $this->conn;
    }
}
?>
