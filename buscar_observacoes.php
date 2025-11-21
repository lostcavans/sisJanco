<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!isset($_GET['checklist_id']) || empty($_GET['checklist_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID do checklist não especificado']);
    exit;
}

$checklist_id = intval($_GET['checklist_id']);

// Buscar observações do checklist
$sql = "SELECT observacao FROM gestao_processo_checklist WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $checklist_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $dados = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'observacao' => $dados['observacao']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Checklist não encontrado'
    ]);
}

$stmt->close();
?>