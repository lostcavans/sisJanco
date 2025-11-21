<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!isset($_GET['empresa_id']) || !isset($_GET['processo_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dados insuficientes']);
    exit;
}

$empresa_id = intval($_GET['empresa_id']);
$processo_id = intval($_GET['processo_id']);

// DEBUG
error_log("=== BUSCAR CHECKLIST AJAX ===");
error_log("Empresa: $empresa_id, Processo: $processo_id");

// Buscar checklists específicos da empresa (já copiados/preparados)
$sql_checklist = "SELECT 
    id,
    titulo,
    descricao,
    concluido,
    data_conclusao,
    observacao,
    ordem
FROM gestao_processo_checklist 
WHERE processo_id = $processo_id AND empresa_id = $empresa_id
ORDER BY ordem, id";

error_log("Query executada: " . $sql_checklist);
error_log("Checklists encontrados: " . count($checklists));

if (count($checklists) === 0) {
    // Log adicional para debugging
    $sql_debug = "SHOW TABLES LIKE 'gestao_processo_checklist'";
    $result_debug = $conexao->query($sql_debug);
    error_log("Tabela existe: " . ($result_debug->num_rows > 0 ? 'SIM' : 'NÃO'));
}

$result = $conexao->query($sql_checklist);

if ($result) {
    $checklists = $result->fetch_all(MYSQLI_ASSOC);
    
    // DEBUG
    error_log("Checklists encontrados no AJAX: " . count($checklists));
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'checklists' => $checklists
    ]);
} else {
    error_log("ERRO na query AJAX: " . $conexao->error);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erro na consulta'
    ]);
}
?>