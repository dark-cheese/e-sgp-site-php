<?php
// backend/config/database.php
// Configuração da conexão com o banco de dados

class Database {
    private $host = "200.131.251.11";
    private $port = 3341;
    private $db_name = "2026ProjetoInv";
    private $username = "2026Iventario";
    private $password = "Inventa@2026";
    public $conn; //<-- Variável que vai guardar a conexão para ser acessada fora da classe.

    public function getConnection() { //<--  Declara um método (função dentro da classe) que vai retornar a conexão com o banco.
        $this->conn = null; //<-- Começa com a variável de conexão vazia. $this-> significa "desta classe"
        
        try { //<-- enta executar o código dentro dele. Se der erro, pula para o catch
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8;connect_timeout=5";
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ]
            );
            $this->conn->exec("set names utf8"); //<-- Garante que acentos e caracteres especiais sejam salvos corretamente no banco.
        } catch(PDOException $exception) {
            error_log("Erro de conexão: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}
?>