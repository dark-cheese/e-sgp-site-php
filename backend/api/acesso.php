<?php
// ============================================================
// 1. CONFIGURAÇÕES INICIAIS
// ============================================================

// Define que a resposta será em JSON
header("Content-Type: application/json");
// Permite que qualquer site (ou frontend) acesse esta API
header("Access-Control-Allow-Origin: *");
// Métodos permitidos: GET e OPTIONS (usado para pré-verificação)
header("Access-Control-Allow-Methods: GET, OPTIONS");
// Cabeçalhos que o frontend pode enviar
header("Access-Control-Allow-Headers: Content-Type, X-Usuario-Id");

// Se for uma requisição OPTIONS (pré-voo do CORS), responde com sucesso e para
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inclui os arquivos de conexão com o banco e funções de auditoria
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auditoria.php';

// Cria uma conexão com o banco de dados
$database = new Database();
$conn = $database->getConnection();

// Se não conseguiu conectar, avisa e encerra
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Falha ao conectar com o banco de dados.']);
    exit;
}

// Só aceita requisições do tipo GET. Se não for, retorna erro 405 (Método não permitido)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido.']);
    exit;
}

// ============================================================
// 2. FUNÇÕES AUXILIARES
// ============================================================

/**
 * Função para executar uma consulta SQL com parâmetros e retornar todos os resultados
 * @param object $conn   Conexão com o banco
 * @param string $query  SQL com placeholders (ex: :id)
 * @param array  $params Parâmetros para bind (ex: [':id' => 1])
 * @return array         Lista de registros encontrados
 */
function buscarTodos($conn, $query, $params = []) {
    $stmt = $conn->prepare($query);
    foreach ($params as $nome => $valor) {
        $stmt->bindValue($nome, $valor, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Extrai os IDs (ou outro campo) de uma lista de registros
 * @param array  $lista Lista de arrays associativos
 * @param string $campo Nome do campo que contém o ID (padrão 'id')
 * @return array        Lista de valores únicos e inteiros
 */
function idsDaLista($lista, $campo = 'id') {
    return array_values(array_unique(array_map(function ($item) use ($campo) {
        return (int) $item[$campo];
    }, $lista)));
}

// ============================================================
// 3. OBTÉM O USUÁRIO LOGADO
// ============================================================

try {
    // Pega o ID do usuário enviado pela requisição (via GET ou cabeçalho)
    // A função 'obterUsuarioIdDaRequisicao' vem do arquivo auditoria.php
    $usuarioId = obterUsuarioIdDaRequisicao($_GET);

    // Busca os dados do usuário no banco
    $usuarioStmt = $conn->prepare("SELECT u.id, u.nome, u.email, tu.nome AS tipoUsuario
        FROM usuario u
        LEFT JOIN tipo_usuario tu ON u.tipoUsuarioId = tu.id
        WHERE u.id = :usuarioId
        LIMIT 1");
    $usuarioStmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $usuarioStmt->execute();
    $usuario = $usuarioStmt->fetch(PDO::FETCH_ASSOC);

    // Se o usuário não foi encontrado, retorna erro
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario nao encontrado.']);
        exit;
    }

    // ============================================================
    // 4. IDENTIFICA O NÍVEL DE ACESSO DO USUÁRIO
    // ============================================================

    // Converte o tipo do usuário para minúsculo (ex: 'Admin' vira 'admin')
    $tipoUsuario = strtolower($usuario['tipoUsuario'] ?? '');
    // Se for 'admin' ou 'gestor', tem acesso total
    $admin = in_array($tipoUsuario, ['admin', 'gestor']);
    $responsavel = null; // guardará os dados do responsável, se não for admin

    // Se NÃO for admin, precisa verificar se o usuário é um responsável
    if (!$admin) {
        // Tenta encontrar um responsável vinculado a este usuário
        $responsavelStmt = $conn->prepare("SELECT id, nome, cargo FROM responsavel WHERE usuarioId = :usuarioId LIMIT 1");
        $responsavelStmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
        $responsavelStmt->execute();
        $responsavel = $responsavelStmt->fetch(PDO::FETCH_ASSOC);

        // Se o usuário não tem um responsável vinculado, ele não tem acesso a nenhum dado
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

    // ============================================================
    // 5. CARREGA OS DADOS CONFORME O NÍVEL DE ACESSO
    // ============================================================

    // --- CASO 1: ADMIN / GESTOR (acesso total) ---
    if ($admin) {
        // Busca TODAS as secretarias, unidades, departamentos e responsáveis
        $secretarias = buscarTodos($conn, "SELECT id, nome FROM secretaria ORDER BY nome");
        $unidades = buscarTodos($conn, "SELECT id, nome, secretariaId FROM unidade ORDER BY nome");
        $departamentos = buscarTodos($conn, "SELECT d.id, d.nome, d.unidadeId, u.secretariaId
            FROM departamento d
            JOIN unidade u ON d.unidadeId = u.id
            ORDER BY d.nome");
        $responsaveis = buscarTodos($conn, "SELECT id, nome, cargo FROM responsavel ORDER BY nome");
        $nivel = 'admin';
    } 
    else {
        // --- CASO 2: USUÁRIO COMUM (responsável) ---
        // Descobre quais departamentos estão sob responsabilidade direta do usuário
        $responsavelId = (int) $responsavel['id'];

        $departamentos = buscarTodos($conn, "SELECT d.id, d.nome, d.unidadeId, u.secretariaId
            FROM departamento d
            JOIN unidade u ON d.unidadeId = u.id
            WHERE d.responsavelId = :responsavelId
            ORDER BY d.nome", [':responsavelId' => $responsavelId]);

        // Se o usuário é responsável por pelo menos um departamento
        if ($departamentos) {
            $nivel = 'departamento';
            $unidadeIds = idsDaLista($departamentos, 'unidadeId');
            $secretariaIds = idsDaLista($departamentos, 'secretariaId');
        } 
        else {
            // Se não tem departamento, verifica se é responsável por alguma unidade
            $unidades = buscarTodos($conn, "SELECT id, nome, secretariaId
                FROM unidade
                WHERE responsavelId = :responsavelId
                ORDER BY nome", [':responsavelId' => $responsavelId]);

            if ($unidades) {
                // Responsável por unidade(s)
                $nivel = 'unidade';
                $unidadeIds = idsDaLista($unidades);
                $secretariaIds = idsDaLista($unidades, 'secretariaId');
                // Busca os departamentos dessas unidades
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
            } 
            else {
                // Se não tem unidade, verifica se é responsável por alguma secretaria
                $secretarias = buscarTodos($conn, "SELECT id, nome
                    FROM secretaria
                    WHERE responsavelId = :responsavelId
                    ORDER BY nome", [':responsavelId' => $responsavelId]);

                // Se não tem nem secretaria, então não tem acesso a nada
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

                // Responsável por secretaria(s)
                $nivel = 'secretaria';
                $secretariaIds = idsDaLista($secretarias);
                // Busca as unidades dessas secretarias
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

                // Agora busca os departamentos dessas unidades
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

        // Após determinar o nível, garante que as listas de secretarias, unidades e departamentos
        // estejam completas (preenche o que faltar)
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

        // Para usuários comuns, a lista de responsáveis inclui apenas ele mesmo
        $responsaveis = [$responsavel];
    }

    // ============================================================
    // 6. RETORNA OS DADOS EM JSON
    // ============================================================

    echo json_encode([
        'success' => true,
        'data' => [
            'nivel' => $nivel,              // 'admin', 'secretaria', 'unidade', 'departamento' ou 'sem_acesso'
            'usuario' => $usuario,           // Dados do usuário logado
            'responsavel' => $responsavel,   // Dados do responsável (se não for admin)
            'secretarias' => $secretarias,   // Lista de secretarias que o usuário pode ver
            'unidades' => $unidades,         // Lista de unidades permitidas
            'departamentos' => $departamentos, // Lista de departamentos permitidos
            'responsaveis' => $responsaveis  // Lista de responsáveis (apenas o próprio, para admin todos)
        ]
    ]);

} catch (PDOException $e) {
    // Se ocorrer erro no banco, retorna mensagem de erro
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar acesso: ' . $e->getMessage()]);
}
?>