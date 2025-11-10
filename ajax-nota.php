<?php
session_start();
include("config.php");

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
    exit;
}

$nota_id = $_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

$query = "
    SELECT n.*, e.razao_social as emitente 
    FROM nfe n 
    LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj 
    WHERE n.id = ? AND n.usuario_id = ?
";

if ($stmt = $conexao->prepare($query)) {
    $stmt->bind_param("ii", $nota_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $nota = $result->fetch_assoc();
    $stmt->close();
    
    if ($nota) {
        echo json_encode(['success' => true, 'nota' => $nota]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nota não encontrada']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Erro na consulta: ' . $conexao->error]);
}
?>