<?php
// config-gestao.php - Configuração específica para o Sistema de Gestão

// Remover session_start() daqui e deixar apenas nos arquivos que precisam
// session_start(); // REMOVER ESTA LINHA

// Usar a mesma conexão do sistema contábil existente
$host = 'mysql.jancoassessoriacontabil.com.br';
$usuario = 'jancoasses_add1';
$senha = 'g283116';
$banco = 'jancoassessori';

// Estabelecer conexão
$conexao = new mysqli($host, $usuario, $senha, $banco);

// Verificar conexão
if ($conexao->connect_error) {
    die("Erro de conexão: " . $conexao->connect_error);
}

// Definir charset para utf8
$conexao->set_charset("utf8");

// Configurações do sistema de gestão
define('SISTEMA_NOME', 'Sistema de Gestão de Processos');
define('SISTEMA_VERSAO', '1.0.0');
define('TEMPO_SESSAO', 1800); // 30 minutos em segundos

// Funções de autenticação e autorização específicas para gestão
function verificarAutenticacaoGestao() {
    if (!isset($_SESSION['logado_gestao']) || $_SESSION['logado_gestao'] !== true) {
        return false;
    }
    
    if (!isset($_SESSION['usuario_id_gestao']) || !isset($_SESSION['usuario_username_gestao'])) {
        return false;
    }
    
    if (isset($_SESSION['ultimo_acesso_gestao']) && (time() - $_SESSION['ultimo_acesso_gestao'] > TEMPO_SESSAO)) {
        return false;
    }
    
    $_SESSION['ultimo_acesso_gestao'] = time();
    return true;
}


function redirecionarSeNaoAutorizadoGestao($nivel_requerido) {
    if (!temPermissaoGestao($nivel_requerido)) {
        $_SESSION['erro_gestao'] = 'Você não tem permissão para acessar esta funcionalidade.';
        header('Location: dashboard-gestao.php');
        exit;
    }
}

// Função para registrar logs no sistema de gestão
function registrarLogGestao($acao, $descricao) {
    global $conexao;
    
    $usuario_id = $_SESSION['usuario_id_gestao'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconhecido';
    
    $sql = "INSERT INTO gestao_logs_sistema (usuario_id, acao, descricao, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("issss", $usuario_id, $acao, $descricao, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

// Função para obter configurações do sistema
function obterConfiguracao($chave) {
    global $conexao;
    
    $sql = "SELECT valor, tipo FROM gestao_configuracoes_sistema WHERE chave = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $chave);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $config = $result->fetch_assoc();
        
        // Converter para o tipo correto
        switch ($config['tipo']) {
            case 'integer':
                return (int)$config['valor'];
            case 'boolean':
                return (bool)$config['valor'];
            case 'json':
                return json_decode($config['valor'], true);
            default:
                return $config['valor'];
        }
    }
    
    return null;

    
}

function temPermissaoGestao($nivel_requerido) {
    if (!isset($_SESSION['usuario_nivel_gestao'])) {
        return false;
    }
    
    $niveis = ['auxiliar' => 1, 'analista' => 2, 'admin' => 3];
    $nivel_usuario = $_SESSION['usuario_nivel_gestao'];

    // Adicionado para garantir que 'admin' sempre tenha acesso total.
    if ($nivel_usuario === 'admin') {
        return true;
    }
    
    return ($niveis[$nivel_usuario] ?? 0) >= ($niveis[$nivel_requerido] ?? 0);
}


function calcularStatusProcesso($processo_id) {
    global $conexao;
    
    $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END) as concluidos
        FROM gestao_processo_checklist 
        WHERE processo_id = ?";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i", $processo_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total = $result['total'];
    $concluidos = $result['concluidos'];
    
    if ($total == 0) return 'pendente';
    if ($concluidos == $total) return 'concluido';
    if ($concluidos > 0) return 'em_andamento';
    return 'pendente';
}
?>