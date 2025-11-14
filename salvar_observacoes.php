<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    $_SESSION['erro'] = 'Não autenticado';
    header("Location: login-gestao.php");
    exit;
}

// Apenas auxiliares, admin e analistas podem adicionar observações
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];
if (!in_array($nivel_usuario, ['auxiliar', 'admin', 'analista'])) {
    $_SESSION['erro'] = 'Sem permissão';
    header("Location: gestao-empresas.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checklist_id'])) {
    $checklist_id = intval($_POST['checklist_id']);
    $observacao = trim($_POST['observacao'] ?? '');
    
    $sql = "UPDATE gestao_processo_checklist 
            SET observacao = ?, updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("si", $observacao, $checklist_id);
    
    if ($stmt->execute()) {
        $_SESSION['sucesso'] = 'Observações salvas com sucesso!';
    } else {
        $_SESSION['erro'] = 'Erro ao salvar observações: ' . $stmt->error;
    }
    
    $stmt->close();
    
    // Redirecionar de volta para a página anterior
    $referer = $_SERVER['HTTP_REFERER'] ?? 'gestao-empresas.php';
    header("Location: " . $referer);
    exit;
} else {
    $_SESSION['erro'] = 'Requisição inválida';
    header("Location: gestao-empresas.php");
    exit;
}
?>