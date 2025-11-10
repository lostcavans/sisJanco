<?php
session_start();
include("config.php");

// Definir cabeçalho JSON
header('Content-Type: application/json');

// Verificar autenticação
function verificarAutenticacao() {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        return false;
    }
    
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_username'])) {
        return false;
    }
    
    if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > 1800)) {
        return false;
    }
    
    $_SESSION['ultimo_acesso'] = time();
    
    return true;
}

// Verificar autenticação
if (!verificarAutenticacao()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter os dados JSON do corpo da requisição
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }
    
    $dados_contabeis = $data['dados_contabeis'] ?? [];
    $usuario_id = $data['usuario_id'] ?? 0;
    
    if (empty($dados_contabeis)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum dado recebido.']);
        exit;
    }
    
    if ($usuario_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de usuário inválido.']);
        exit;
    }
    
    // Verificar se o usuário tem permissão
    if ($usuario_id != $_SESSION['usuario_id']) {
        echo json_encode(['success' => false, 'message' => 'Permissão negada.']);
        exit;
    }
    
    // Iniciar transação
    mysqli_begin_transaction($conexao);
    
    try {
        $registrosContabeisInseridos = 0;
        $erros = [];
        
        // Inserir dados contábeis
        if (!empty($dados_contabeis)) {
            foreach ($dados_contabeis as $index => $dado) {
                // Converter data para formato MySQL
                $pagamento_mysql = null;
                
                if (!empty($dado['pagamento'])) {
                    $date = DateTime::createFromFormat('d/m/Y', $dado['pagamento']);
                    if ($date) {
                        $pagamento_mysql = $date->format('Y-m-d');
                    }
                }
                
                // ... mantém apenas os campos que estão sendo usados
                $sql = "INSERT INTO tabela_contabil (
                    usuario_id, pagamento, cod_conta_debito, conta_credito, vr_liquido, 
                    cod_historico, complemento_historico, inicia_lote, data_importacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = mysqli_prepare($conexao, $sql);
                if (!$stmt) {
                    throw new Exception("Erro ao preparar statement: " . mysqli_error($conexao));
                }
                
                mysqli_stmt_bind_param(
                    $stmt, 
                    "isssdsss", 
                    $usuario_id, 
                    $pagamento_mysql, 
                    $dado['cod_conta_debito'], 
                    $dado['conta_credito'], 
                    $dado['vr_liquido'],
                    $dado['cod_historico'], 
                    $dado['complemento_historico'], 
                    $dado['inicia_lote']
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $registrosContabeisInseridos++;
                } else {
                    $erros[] = "Erro contábil na linha $index: " . mysqli_error($conexao);
                }
                
                mysqli_stmt_close($stmt);
            }
        }
        
        if (!empty($erros)) {
            throw new Exception("Erros durante a inserção: " . implode("; ", $erros));
        }
        
        // Commit da transação
        mysqli_commit($conexao);
        
        echo json_encode([
            'success' => true, 
            'message' => "Dados salvos com sucesso! $registrosContabeisInseridos registros contábeis inseridos."
        ]);
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        mysqli_rollback($conexao);
        
        echo json_encode([
            'success' => false, 
            'message' => "Erro ao salvar dados: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
}
?>