<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

if (!temPermissaoGestao('admin') && !temPermissaoGestao('analista')) {
    $_SESSION['erro'] = 'Você não tem permissão para gerenciar checklists.';
    header("Location: gestao-empresas.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id = intval($_POST['empresa_id']);
    $processo_id = intval($_POST['processo_id']);
    
    error_log("=== SALVAMENTO CHECKLIST INICIADO ===");
    error_log("Empresa: $empresa_id, Processo: $processo_id");
    
    // DEBUG: Verificar o que está vindo no POST
    error_log("POST recebido:");
    error_log("checklist_titulo: " . print_r($_POST['checklist_titulo'], true));
    error_log("checklist_descricao: " . print_r($_POST['checklist_descricao'], true));
    
    // Validar associação
    $sql_check = "SELECT * FROM gestao_processo_empresas 
                  WHERE empresa_id = ? AND processo_id = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("ii", $empresa_id, $processo_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if (!$result_check->fetch_assoc()) {
        $_SESSION['erro'] = 'Processo não encontrado para esta empresa.';
        header("Location: empresa-processos.php?id=" . $empresa_id);
        exit;
    }
    $stmt_check->close();
    
    $conexao->begin_transaction();
    
    try {
        // ESTRATÉGIA: DELETE todos + INSERT novos (mais simples)
        
        // 1. Primeiro DELETAR todos os checklists existentes para este processo/empresa
        $sql_delete = "DELETE FROM gestao_processo_checklist 
                      WHERE processo_id = ? AND empresa_id = ?";
        $stmt_delete = $conexao->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $processo_id, $empresa_id);
        
        if (!$stmt_delete->execute()) {
            throw new Exception('Erro ao limpar checklists antigos: ' . $stmt_delete->error);
        }
        $deleted_count = $stmt_delete->affected_rows;
        $stmt_delete->close();
        
        error_log("Checklists antigos removidos: $deleted_count");
        
        // 2. Processar NOVOS itens do formulário
        if (isset($_POST['checklist_titulo']) && is_array($_POST['checklist_titulo'])) {
            $ordem = 1;
            $inserted_count = 0;
            
            foreach ($_POST['checklist_titulo'] as $index => $titulo) {
                $titulo = trim($titulo);
                $descricao = trim($_POST['checklist_descricao'][$index] ?? '');
                
                // Pular itens completamente vazios
                if (empty($titulo) && empty($descricao)) {
                    error_log("Item $ordem pulado (vazio)");
                    continue;
                }
                
                // Se título está vazio mas tem descrição, criar título padrão
                if (empty($titulo)) {
                    $titulo = "Item $ordem";
                }
                
                // PREPARED STATEMENT para inserção segura
                $sql_insert = "INSERT INTO gestao_processo_checklist 
                              (processo_id, empresa_id, titulo, descricao, ordem, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                
                $stmt_insert = $conexao->prepare($sql_insert);
                $stmt_insert->bind_param("iissi", $processo_id, $empresa_id, $titulo, $descricao, $ordem);
                
                if ($stmt_insert->execute()) {
                    $inserted_count++;
                    error_log("Item $ordem inserido: '$titulo'");
                } else {
                    error_log("Erro ao inserir item $ordem: " . $stmt_insert->error);
                    // Continuar mesmo com erro em um item
                }
                $stmt_insert->close();
                
                $ordem++; // SEMPRE incrementar a ordem
            }
            
            error_log("Total de itens inseridos: $inserted_count");
            
            if ($inserted_count === 0) {
                // Se nenhum item foi inserido, criar um item padrão
                $sql_padrao = "INSERT INTO gestao_processo_checklist 
                              (processo_id, empresa_id, titulo, descricao, ordem, created_at, updated_at)
                              VALUES (?, ?, 'Primeiro Item', 'Descrição do primeiro item', 1, NOW(), NOW())";
                $stmt_padrao = $conexao->prepare($sql_padrao);
                $stmt_padrao->bind_param("ii", $processo_id, $empresa_id);
                $stmt_padrao->execute();
                $stmt_padrao->close();
                error_log("Item padrão criado");
            }
            
        } else {
            error_log("Nenhum item no formulário");
        }
        
        $conexao->commit();
        $_SESSION['sucesso'] = 'Checklist salvo com sucesso!';
        error_log("=== SALVAMENTO CONCLUÍDO COM SUCESSO ===");
        
        // Registrar no histórico
        $usuario_id = $_SESSION['usuario_id_gestao'];
        $sql_historico = "INSERT INTO gestao_historicos_processo 
                         (processo_id, usuario_id, acao, descricao, created_at) 
                         VALUES (?, ?, 'checklist', 'Checklist personalizado atualizado', NOW())";
        $stmt_historico = $conexao->prepare($sql_historico);
        $stmt_historico->bind_param("ii", $processo_id, $usuario_id);
        $stmt_historico->execute();
        $stmt_historico->close();
        
    } catch (Exception $e) {
        $conexao->rollback();
        $_SESSION['erro'] = 'Erro ao salvar checklist: ' . $e->getMessage();
        error_log("ERRO NA TRANSAÇÃO: " . $e->getMessage());
    }
    
    header("Location: empresa-processos.php?id=" . $empresa_id);
    exit;
    
} else {
    $_SESSION['erro'] = 'Método não permitido.';
    header("Location: gestao-empresas.php");
    exit;
}
?>