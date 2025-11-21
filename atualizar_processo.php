<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

// Apenas Admin e Analistas podem editar
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];
if (!in_array($nivel_usuario, ['admin', 'analista'])) {
    $_SESSION['erro'] = 'Você não tem permissão para realizar esta ação.';
    header("Location: gestao-empresas.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $processo_id = intval($_POST['processo_id']);
    $empresa_id = intval($_POST['empresa_id']);
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria_id = intval($_POST['categoria_id']);
    $responsavel_id = intval($_POST['responsavel_id']);
    $prioridade = $_POST['prioridade'];
    $status = $_POST['status'];
    $data_inicio = $_POST['data_inicio'] ? $_POST['data_inicio'] : null;
    $data_prevista = $_POST['data_prevista'] ? $_POST['data_prevista'] : null;
    $recorrente = $_POST['recorrente'];
    $observacoes_gerais = trim($_POST['observacoes_gerais'] ?? '');
    
    // Validar dados obrigatórios
    if (empty($titulo) || empty($categoria_id) || empty($responsavel_id)) {
        $_SESSION['erro'] = 'Preencha todos os campos obrigatórios.';
        header("Location: empresa-processos.php?id=" . $empresa_id);
        exit;
    }
    
    // Verificar se o processo existe e pertence à empresa
    $sql_check = "SELECT p.* FROM gestao_processos p
                  INNER JOIN gestao_processo_empresas pe ON p.id = pe.processo_id
                  WHERE p.id = ? AND pe.empresa_id = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("ii", $processo_id, $empresa_id);
    $stmt_check->execute();
    $processo = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if (!$processo) {
        $_SESSION['erro'] = 'Processo não encontrado ou não pertence a esta empresa.';
        header("Location: empresa-processos.php?id=" . $empresa_id);
        exit;
    }
    
    // Atualizar processo
    $sql_update = "UPDATE gestao_processos 
                   SET titulo = ?, descricao = ?, categoria_id = ?, responsavel_id = ?, 
                       prioridade = ?, status = ?, data_inicio = ?, data_prevista = ?, 
                       recorrente = ?, updated_at = NOW()
                   WHERE id = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("ssiisssssi", $titulo, $descricao, $categoria_id, $responsavel_id, 
                     $prioridade, $status, $data_inicio, $data_prevista, $recorrente, $processo_id);
    
    if ($stmt->execute()) {
        $_SESSION['sucesso'] = 'Processo atualizado com sucesso!';
        
        // Registrar no histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo 
                         (processo_id, usuario_id, acao, descricao, created_at) 
                         VALUES (?, ?, 'atualizacao', 'Processo atualizado', NOW())";
        $stmt_historico = $conexao->prepare($sql_historico);
        $stmt_historico->bind_param("ii", $processo_id, $_SESSION['usuario_id_gestao']);
        $stmt_historico->execute();
        $stmt_historico->close();
        
    } else {
        $_SESSION['erro'] = 'Erro ao atualizar processo: ' . $stmt->error;
    }
    $stmt->close();
    
    header("Location: empresa-processos.php?id=" . $empresa_id);
    exit;
} else {
    $_SESSION['erro'] = 'Método não permitido.';
    header("Location: gestao-empresas.php");
    exit;
}
?>