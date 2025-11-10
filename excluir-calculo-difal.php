<?php
session_start();
include("config.php");

// Verificar autenticação
function verificarAutenticacao() {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        return false;
    }
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_username'])) {
        return false;
    }
    return true;
}

if (!verificarAutenticacao()) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $calculo_id = $_POST['calculo_id'] ?? 0;
    $competencia = $_POST['competencia'] ?? date('Y-m');
    
    if ($calculo_id) {
        // Verificar se o cálculo pertence ao usuário
        $query_verificar = "SELECT id FROM calculos_difal WHERE id = ? AND usuario_id = ?";
        
        if ($stmt = $conexao->prepare($query_verificar)) {
            $stmt->bind_param("ii", $calculo_id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Excluir o cálculo
                $query_excluir = "DELETE FROM calculos_difal WHERE id = ?";
                
                if ($stmt_excluir = $conexao->prepare($query_excluir)) {
                    $stmt_excluir->bind_param("i", $calculo_id);
                    
                    if ($stmt_excluir->execute()) {
                        $_SESSION['msg'] = "Cálculo excluído com sucesso!";
                    } else {
                        $_SESSION['error'] = "Erro ao excluir cálculo: " . $stmt_excluir->error;
                    }
                    $stmt_excluir->close();
                }
            } else {
                $_SESSION['error'] = "Cálculo não encontrado ou você não tem permissão para excluí-lo.";
            }
            $stmt->close();
        }
    } else {
        $_SESSION['error'] = "ID do cálculo não especificado.";
    }
    
    // Redirecionar de volta para a página do DIFAL
    header("Location: difal.php?competencia=" . urlencode($competencia));
    exit;
} else {
    header("Location: difal.php");
    exit;
}
?>