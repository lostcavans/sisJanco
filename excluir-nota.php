<?php
session_start();

// Função para verificar autenticação
function verificarAutenticacao() {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        return false;
    }
    
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_username'])) {
        return false;
    }
    
    if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > 1800)) {
        return false;
    }
    
    $_SESSION['ultimo_acesso'] = time();
    
    return true;
}

// Verifica autenticação
if (!verificarAutenticacao()) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

include("config.php");

$logado = $_SESSION['usuario_username'];
$usuario_id = $_SESSION['usuario_id'];

// Verificar se o tipo e ID da nota foram passados
if (!isset($_GET['tipo']) || !isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: importar-notas-fiscais.php?erro=nota_nao_encontrada");
    exit;
}

$tipo_nota = $_GET['tipo'];
$nota_id = $_GET['id'];

// Determinar tabelas baseado no tipo
if ($tipo_nota == 'nfe') {
    $tabela_nota = 'nfe';
    $tabela_itens = 'nfe_itens';
    $campo_id = 'nfe_id';
} else {
    $tabela_nota = 'nfce';
    $tabela_itens = 'nfce_itens';
    $campo_id = 'nfce_id';
}

// Verificar se a nota existe e pertence ao usuário
$sql_check = "SELECT id FROM $tabela_nota WHERE id = ? AND usuario_id = ?";
$stmt = mysqli_prepare($conexao, $sql_check);
mysqli_stmt_bind_param($stmt, "ii", $nota_id, $usuario_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) === 0) {
    mysqli_stmt_close($stmt);
    header("Location: importar-notas-fiscais.php?erro=nota_nao_encontrada");
    exit;
}

mysqli_stmt_close($stmt);

// Excluir a nota fiscal (com transação para garantir integridade)
mysqli_begin_transaction($conexao);

try {
    // Primeiro excluir os itens
    $sql_delete_itens = "DELETE FROM $tabela_itens WHERE $campo_id = ?";
    $stmt = mysqli_prepare($conexao, $sql_delete_itens);
    mysqli_stmt_bind_param($stmt, "i", $nota_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erro ao excluir itens da nota");
    }
    mysqli_stmt_close($stmt);
    
    // Depois excluir a nota
    $sql_delete_nota = "DELETE FROM $tabela_nota WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $sql_delete_nota);
    mysqli_stmt_bind_param($stmt, "i", $nota_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erro ao excluir nota fiscal");
    }
    mysqli_stmt_close($stmt);
    
    // Commit da transação
    mysqli_commit($conexao);
    
    header("Location: importar-notas-fiscais.php?sucesso=nota_excluida");
    exit;
    
} catch (Exception $e) {
    // Rollback em caso de erro
    mysqli_rollback($conexao);
    header("Location: importar-notas-fiscais.php?erro=erro_exclusao");
    exit;
}
?>