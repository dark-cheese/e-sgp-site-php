<?php
// backend/includes/functions.php
// Funções auxiliares

// Função para gerar número de patrimônio único
function gerarNumeroPatrimonio($db, $prefix = '2026') {
    $query = "SELECT COUNT(*) as total FROM itens WHERE numero_patrimonio LIKE :prefix";
    $stmt = $db->prepare($query);
    $like = $prefix . '%';
    $stmt->bindParam(":prefix", $like);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $numero = $result['total'] + 1;
    return $prefix . '.' . str_pad($numero, 3, '0', STR_PAD_LEFT);
}

// Função para formatar valor monetário
function formatarValor($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para validar email exemplo:usuario@email.com
function validaEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Função para converter data do formato dd/mm/yyyy para yyyy-mm-dd
function tratarData($data) {
    if(empty($data)) return null;
    $partes = explode('/', $data);
    if(count($partes) == 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data;
}
?>