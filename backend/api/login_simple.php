<?php
// backend/api/login_simple.php
// Caminho: C:/xampp/htdocs/sql/e-sgp-site/backend/api/login_simple.php

header("Content-Type: application/json"); //<-- Diz ao navegador que a resposta será no formato //
header("Access-Control-Allow-Origin: *"); //<-- Permite que qualquer site (domínio) chame esta API
header("Access-Control-Allow-Methods: POST");  //<-- Permite apenas o método POST 
header("Access-Control-Allow-Headers: Content-Type"); //<-- Permite que o cliente envie o cabeçalho Content-Type

// Pega os dados enviados pelo frontend
$data = json_decode(file_get_contents("php://input"));
//file_get_contents("php://input") → lê os dados brutos enviados no corpo da requisição
//json_decode(...) → converte o JSON recebido ex:"email":"x","senha":"y" em um objeto PHP

if(isset($data->email) && isset($data->senha)) { //<-- isset()verifica se a variável existe
    
    $email = $data->email; //<-- Copia os valores do JSON para variáveis locais.
    $senha = $data->senha;
    
    // Credenciais fixas para teste
    if($email === 'admin@prefeitura.br' && $senha === 'admin123') {
        
        // Iniciar sessão
        session_start();
        $_SESSION['usuario_id'] = 1;
        $_SESSION['usuario_nome'] = 'Administrador';
        $_SESSION['usuario_email'] = 'admin@prefeitura.br'; //<-- Guarda informações do usuário na sessão (serão mantidas enquanto ele não fechar o navegador).
        $_SESSION['usuario_nivel'] = 'admin';
        
        echo json_encode([    		//<-- envia" a resposta para o navegador, e o [json_encode] é converte um array PHP em texto JSON
            "success" => true, //<-- success corresponde a true,
            "message" => "Login realizado com sucesso!",
            "usuario" => [
                "id" => 1,
                "nome" => "Administrador",
                "email" => "admin@prefeitura.br",
                "nivel" => "admin"
            ]
        ]);
    } else {
        echo json_encode([ //<-- envia" a resposta para o navegador, e o [json_encode] é converte um array PHP em texto JSON
            "success" => false,
            "message" => "E-mail ou senha incorretos!"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "E-mail e senha são obrigatórios!"
    ]);
}
?>