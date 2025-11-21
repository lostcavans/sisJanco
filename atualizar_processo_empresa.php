<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

// Apenas admin e analistas podem editar
if (!temPermissaoGestao('admin') && !temPermissaoGestao('analista')) {
    $_SESSION['erro'] = 'Você não tem permissão para editar processos.';
    header("Location: gestao-empresas.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id = intval($_POST['empresa_id']);
    $processo_id = intval($_POST['processo_id']);
    $data_prevista = $_POST['data_prevista'] ? "'" . $conexao->real_escape_string($_POST['data_prevista']) . "'" : 'NULL';
    $observacoes = $conexao->real_escape_string(trim($_POST['observacoes'] ?? ''));
    
    // Validar se a associação existe
    $sql_check = "SELECT * FROM gestao_processo_empresas 
                  WHERE empresa_id = $empresa_id AND processo_id = $processo_id";
    $result_check = $conexao->query($sql_check);
    $associacao = $result_check->fetch_assoc();
    
    if (!$associacao) {
        $_SESSION['erro'] = 'Processo não encontrado para esta empresa.';
        header("Location: empresa-processos.php?id=" . $empresa_id);
        exit;
    }
    
    // Atualizar dados específicos da empresa
    $sql_update = "UPDATE gestao_processo_empresas 
                   SET data_prevista = $data_prevista, 
                       observacoes = '$observacoes', 
                       updated_at = NOW()
                   WHERE empresa_id = $empresa_id AND processo_id = $processo_id";
    
    if ($conexao->query($sql_update)) {
        $_SESSION['sucesso'] = 'Processo atualizado com sucesso!';
        
        // Registrar no histórico
        $usuario_id = $_SESSION['usuario_id_gestao'];
        $sql_historico = "INSERT INTO gestao_historicos_processo 
                         (processo_id, usuario_id, acao, descricao, created_at) 
                         VALUES ($processo_id, $usuario_id, 'configuracao', 'Configurações da empresa atualizadas', NOW())";
        $conexao->query($sql_historico);
        
    } else {
        $_SESSION['erro'] = 'Erro ao atualizar processo: ' . $conexao->error;
    }
    
    header("Location: empresa-processos.php?id=" . $empresa_id);
    exit;
    
} else {
    $_SESSION['erro'] = 'Método não permitido.';
    header("Location: gestao-empresas.php");
    exit;
}
?>