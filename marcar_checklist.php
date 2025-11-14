<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Apenas auxiliares, admin e analistas podem marcar checklist
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];
if (!in_array($nivel_usuario, ['auxiliar', 'admin', 'analista'])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$checklist_id = intval($input['checklist_id']);
$empresa_id = intval($input['empresa_id']);
$concluido = $input['concluido'];
$usuario_id = intval($input['usuario_id']);

try {
    if ($concluido) {
        // Marcar como concluído
        $sql = "UPDATE gestao_processo_checklist 
                SET concluido = 1, 
                    data_conclusao = NOW(),
                    usuario_conclusao_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND empresa_id = ?";
        
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("iii", $usuario_id, $checklist_id, $empresa_id);
    } else {
        // Desmarcar conclusão
        $sql = "UPDATE gestao_processo_checklist 
                SET concluido = 0, 
                    data_conclusao = NULL,
                    usuario_conclusao_id = NULL,
                    observacao = NULL,
                    updated_at = NOW()
                WHERE id = ? AND empresa_id = ?";
        
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ii", $checklist_id, $empresa_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao executar query: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>