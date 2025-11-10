<?php
session_start();
include("config-gestao.php");

// Verificar autenticação
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];

// Verificar se o ID do processo foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = 'Processo não encontrado.';
    header("Location: processos-gestao.php");
    exit;
}

$processo_id = $_GET['id'];

// CORREÇÃO: Buscar dados do processo SEM filtrar por empresa_id específico
// Permitir que usuários com permissão vejam processos de qualquer empresa
$sql = "SELECT p.*, e.razao_social as empresa_nome 
        FROM gestao_processos p 
        LEFT JOIN empresas e ON p.empresa_id = e.id 
        WHERE p.id = ? AND p.ativo = 1";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$processo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$processo) {
    $_SESSION['erro'] = 'Processo não encontrado.';
    header("Location: processos-gestao.php");
    exit;
}

// Verificar permissão - apenas analistas podem excluir
if (!temPermissaoGestao('analista')) {
    $_SESSION['erro'] = 'Você não tem permissão para excluir processos.';
    header("Location: processos-gestao.php");
    exit;
}

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_exclusao'])) {
    // CORREÇÃO: Excluir sem verificar empresa_id específico
    $sql_update = "UPDATE gestao_processos SET ativo = 0 WHERE id = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("i", $processo_id);
    
    if ($stmt->execute()) {
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                         VALUES (?, ?, 'exclusao', 'Processo excluído')";
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $processo_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        // Registrar log
        registrarLogGestao('EXCLUIR_PROCESSO', 'Processo ' . $processo['codigo'] . ' excluído');
        
        $_SESSION['sucesso'] = 'Processo excluído com sucesso!';
    } else {
        $_SESSION['erro'] = 'Erro ao excluir processo: ' . $stmt->error;
    }
    $stmt->close();
    
    // Redirecionar após processar a exclusão
    header("Location: processos-gestao.php");
    exit;
}

// Se chegou até aqui sem ser POST, mostrar página de confirmação
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excluir Processo - Gestão de Processos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #adb5bd;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            max-width: 500px;
            width: 90%;
            margin: 0 auto;
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            text-align: center;
        }

        .warning-icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 1.5rem;
        }

        .card h1 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .card p {
            color: var(--gray);
            margin-bottom: 2rem;
        }

        .process-info {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            text-align: left;
        }

        .process-info strong {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .process-info code {
            background: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            margin: 0 0.5rem;
        }

        .btn-danger {
            background-color: #ef4444;
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-secondary {
            background-color: var(--gray);
            color: var(--white);
        }

        .btn-secondary:hover {
            background-color: var(--gray-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h1>Confirmar Exclusão</h1>
            
            <p>Tem certeza que deseja excluir este processo? Esta ação não pode ser desfeita.</p>
            
            <div class="process-info">
                <strong>Processo:</strong>
                <code><?php echo htmlspecialchars($processo['codigo']); ?></code> - 
                <?php echo htmlspecialchars($processo['titulo']); ?>
                
                <?php if (!empty($processo['empresa_nome'])): ?>
                <br><strong>Empresa:</strong> <?php echo htmlspecialchars($processo['empresa_nome']); ?>
                <?php endif; ?>
                
                <?php if (!empty($processo['descricao'])): ?>
                <br><strong>Descrição:</strong> <?php echo substr(htmlspecialchars($processo['descricao']), 0, 100); ?>...
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <button type="submit" name="confirmar_exclusao" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Sim, Excluir Processo
                </button>
                
                <a href="processos-gestao.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </form>
        </div>
    </div>
</body>
</html>