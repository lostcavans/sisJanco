<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    $_SESSION['erro'] = 'Não autenticado';
    header("Location: login-gestao.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erro'] = 'Método não permitido';
    header("Location: gestao-empresas.php");
    exit;
}

if (!isset($_POST['checklist_id']) || empty($_POST['checklist_id'])) {
    $_SESSION['erro'] = 'ID do checklist não especificado';
    header("Location: gestao-empresas.php");
    exit;
}

$checklist_id = intval($_POST['checklist_id']);
$observacao = trim($_POST['observacao'] ?? '');

// Atualizar observações do checklist
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

// Redirecionar de volta para a página da empresa
// Você pode querer ajustar isso para voltar para a página específica
if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: gestao-empresas.php');
}
exit;
?>