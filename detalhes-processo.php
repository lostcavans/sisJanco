<?php
session_start();
include("config-gestao.php");

// Verificar autenticação
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];

// Verificar se o ID do processo foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = 'Processo não encontrado.';
    header("Location: dashboard-gestao.php");
    exit;
}

$processo_id = $_GET['id'];

// Buscar dados do processo
$sql = "SELECT p.*, 
               u.nome_completo as responsavel_nome, 
               u.nivel_acesso as responsavel_nivel,
               c.nome as categoria_nome,
               uc.nome_completo as criador_nome
        FROM gestao_processos p 
        LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
        LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id 
        LEFT JOIN gestao_usuarios uc ON p.criador_id = uc.id 
        WHERE p.id = ? AND p.ativo = 1";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$processo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$processo) {
    $_SESSION['erro'] = 'Processo não encontrado.';
    header("Location: dashboard-gestao.php");
    exit;
}

// Buscar empresas associadas ao processo
$sql_empresas = "SELECT e.* 
                 FROM gestao_processo_empresas pe 
                 LEFT JOIN empresas e ON pe.empresa_id = e.id 
                 WHERE pe.processo_id = ? 
                 ORDER BY e.razao_social";
$stmt = $conexao->prepare($sql_empresas);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$empresas_associadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Buscar histórico do processo - CORREÇÃO: garantir que sempre retorne array
$sql_historico = "SELECT h.*, u.nome_completo 
                  FROM gestao_historicos_processo h 
                  JOIN gestao_usuarios u ON h.usuario_id = u.id 
                  WHERE h.processo_id = ? 
                  ORDER BY h.created_at DESC";
$stmt = $conexao->prepare($sql_historico);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$result_historico = $stmt->get_result();
$historico = $result_historico->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Buscar processos relacionados (mesmo código em outras empresas)
$sql_relacionados = "SELECT p.*, 
                            (SELECT COUNT(*) FROM gestao_processo_empresas pe WHERE pe.processo_id = p.id) as total_empresas
                     FROM gestao_processos p 
                     WHERE p.codigo = ? AND p.id != ? AND p.ativo = 1
                     ORDER BY p.created_at DESC";
$stmt = $conexao->prepare($sql_relacionados);
$stmt->bind_param("si", $processo['codigo'], $processo_id);
$stmt->execute();
$processos_relacionados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Processar conclusão do processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['concluir_processo'])) {
    $sql_update = "UPDATE gestao_processos SET status = 'concluido' WHERE id = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("i", $processo_id);
    
    if ($stmt->execute()) {
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                         VALUES (?, ?, 'conclusao', 'Processo concluído')";
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $processo_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        // Registrar log
        registrarLogGestao('CONCLUIR_PROCESSO', 'Processo ' . $processo['codigo'] . ' concluído');
        
        $_SESSION['sucesso'] = 'Processo concluído com sucesso!';
        header("Location: detalhes-processo.php?id=" . $processo_id);
        exit;
    } else {
        $_SESSION['erro'] = 'Erro ao concluir processo: ' . $stmt->error;
    }
    $stmt->close();
}

// Processar reabertura do processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reabrir_processo'])) {
    $sql_update = "UPDATE gestao_processos SET status = 'em_andamento' WHERE id = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("i", $processo_id);
    
    if ($stmt->execute()) {
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                         VALUES (?, ?, 'atualizacao', 'Processo reaberto')";
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $processo_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        // Registrar log
        registrarLogGestao('REABRIR_PROCESSO', 'Processo ' . $processo['codigo'] . ' reaberto');
        
        $_SESSION['sucesso'] = 'Processo reaberto com sucesso!';
        header("Location: detalhes-processo.php?id=" . $processo_id);
        exit;
    } else {
        $_SESSION['erro'] = 'Erro ao reabrir processo: ' . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Processo - Gestão de Processos</title>
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
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
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
        }

        .navbar {
            background: var(--white);
            box-shadow: var(--shadow);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            font-size: 1.2rem;
        }

        .navbar-nav {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-link {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .btn {
            padding: 12px 24px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background-color: var(--gray);
        }

        .btn-secondary:hover {
            background-color: var(--gray-light);
        }

        .btn-success {
            background-color: #10b981;
        }

        .btn-success:hover {
            background-color: #0da271;
        }

        .btn-warning {
            background-color: #f59e0b;
        }

        .btn-warning:hover {
            background-color: #e6900a;
        }

        .btn-info {
            background-color: #3b82f6;
        }

        .btn-info:hover {
            background-color: #2563eb;
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .info-value {
            font-size: 1.1rem;
            color: var(--dark);
            font-weight: 600;
        }

        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-rascunho { background: rgba(107, 114, 128, 0.1); color: var(--gray); }
        .status-pendente { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .status-em_andamento { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .status-concluido { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-cancelado { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .status-pausado { background: rgba(156, 163, 175, 0.1); color: #9ca3af; }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .priority-baixa { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .priority-media { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .priority-alta { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .priority-urgente { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .user-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        .empresa-badge {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(114, 9, 183, 0.1);
            color: var(--secondary);
            border-radius: 12px;
            font-size: 0.8rem;
            margin: 2px;
        }

        .description {
            background: var(--light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
        }

        .history-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .history-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .history-icon.green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .history-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .history-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .history-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

        .history-content {
            flex: 1;
        }

        .history-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .history-description {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .history-time {
            font-size: 0.8rem;
            color: var(--gray);
            text-align: right;
            min-width: 120px;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .alert {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        .empresa-info {
            background: var(--light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
        }

        .empresa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .processos-relacionados {
            margin-top: 1rem;
        }

        .processo-relacionado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            margin-bottom: 0.5rem;
            transition: var(--transition);
        }

        .processo-relacionado:hover {
            background: var(--light);
            border-color: var(--primary);
        }

        .recorrente-badge {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 8px;
        }

        @media (max-width: 768px) {
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .history-item {
                flex-direction: column;
            }
            
            .history-time {
                text-align: left;
                margin-top: 0.5rem;
            }
            
            .empresa-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard-gestao.php" class="navbar-brand">
            <img src="uploads/logo-images/ANTONIO LOGO 2.png" alt="Descrição da imagem" style="width: 75px; height: 50px;">
            Gestão de Processos
        </a>
        <ul class="navbar-nav">
            <li><a href="dashboard-gestao.php" class="nav-link">Dashboard</a></li>
            <li><a href="processos-gestao.php" class="nav-link">Processos</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Detalhes do Processo</h1>
            <a href="dashboard-gestao.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?>
            </div>
        <?php endif; ?>

        <!-- Card de Informações do Processo -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?php echo htmlspecialchars($processo['titulo']); ?></h2>
                <div>
                    <span class="status-badge status-<?php echo $processo['status']; ?>">
                        <?php 
                        $status_labels = [
                            'rascunho' => 'Rascunho',
                            'pendente' => 'Pendente',
                            'em_andamento' => 'Em Andamento',
                            'concluido' => 'Concluído',
                            'cancelado' => 'Cancelado',
                            'pausado' => 'Pausado'
                        ];
                        echo $status_labels[$processo['status']] ?? $processo['status'];
                        ?>
                    </span>
                    <?php if ($processo['recorrente'] != 'nao'): ?>
                        <span class="recorrente-badge">
                            <i class="fas fa-sync-alt"></i> <?php echo ucfirst($processo['recorrente']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Código</span>
                    <span class="info-value"><?php echo htmlspecialchars($processo['codigo']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Categoria</span>
                    <span class="info-value"><?php echo htmlspecialchars($processo['categoria_nome'] ?? '-'); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Responsável</span>
                    <span class="info-value">
                        <?php echo htmlspecialchars($processo['responsavel_nome']); ?>
                        <span class="user-badge"><?php echo $processo['responsavel_nivel']; ?></span>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Prioridade</span>
                    <span class="info-value">
                        <span class="priority-badge priority-<?php echo $processo['prioridade']; ?>">
                            <?php echo ucfirst($processo['prioridade']); ?>
                        </span>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Data Prevista</span>
                    <span class="info-value">
                        <?php echo $processo['data_prevista'] ? date('d/m/Y', strtotime($processo['data_prevista'])) : '-'; ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Criado por</span>
                    <span class="info-value"><?php echo htmlspecialchars($processo['criador_nome']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Data de Criação</span>
                    <span class="info-value">
                        <?php echo date('d/m/Y H:i', strtotime($processo['created_at'])); ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Última Atualização</span>
                    <span class="info-value">
                        <?php echo date('d/m/Y H:i', strtotime($processo['updated_at'])); ?>
                    </span>
                </div>
            </div>

            <!-- Informações das Empresas -->
            <div class="empresa-info">
                <div class="info-label" style="font-size: 1.1rem; margin-bottom: 1rem;">
                    <i class="fas fa-building"></i> Empresas Associadas (<?php echo count($empresas_associadas); ?>)
                </div>
                <div class="empresa-grid">
                    <?php foreach ($empresas_associadas as $empresa): ?>
                        <div class="info-item">
                            <span class="info-label">Razão Social</span>
                            <span class="info-value"><?php echo htmlspecialchars($empresa['razao_social']); ?></span>
                            <div style="margin-top: 0.5rem;">
                                <span class="empresa-badge">CNPJ: <?php echo htmlspecialchars($empresa['cnpj']); ?></span>
                                <span class="empresa-badge"><?php echo htmlspecialchars($empresa['regime_tributario']); ?></span>
                                <span class="empresa-badge"><?php echo htmlspecialchars($empresa['atividade']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($processo['descricao']): ?>
                <div class="info-item">
                    <span class="info-label">Descrição</span>
                    <div class="description">
                        <?php echo nl2br(htmlspecialchars($processo['descricao'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <?php if (temPermissaoGestao('analista')): ?>
                    <a href="editar-processo.php?id=<?php echo $processo['id']; ?>" class="btn">
                        <i class="fas fa-edit"></i> Editar Processo
                    </a>
                <?php endif; ?>
                
                <?php if ($processo['status'] != 'concluido' && $processo['status'] != 'cancelado'): ?>
                    <form method="POST" action="" style="display: inline;">
                        <button type="submit" name="concluir_processo" class="btn btn-success" 
                                onclick="return confirm('Tem certeza que deseja concluir este processo?')">
                            <i class="fas fa-check"></i> Concluir Processo
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($processo['status'] == 'concluido'): ?>
                    <form method="POST" action="" style="display: inline;">
                        <button type="submit" name="reabrir_processo" class="btn btn-warning" 
                                onclick="return confirm('Tem certeza que deseja reabrir este processo?')">
                            <i class="fas fa-redo"></i> Reabrir Processo
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (count($processos_relacionados) > 0): ?>
                    <a href="#processos-relacionados" class="btn btn-info">
                        <img src="uploads/logo-images/ANTONIO LOGO 2.png" alt="Descrição da imagem" style="width: 75px; height: 50px;"> Ver Processos Relacionados
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Processos Relacionados -->
        <?php if (count($processos_relacionados) > 0): ?>
        <div class="card" id="processos-relacionados">
            <div class="card-header">
                <h2 class="card-title">Processos Relacionados</h2>
                <span class="info-label"><?php echo count($processos_relacionados); ?> processo(s) com o mesmo código</span>
            </div>

            <div class="processos-relacionados">
                <?php foreach ($processos_relacionados as $relacionado): ?>
                    <div class="processo-relacionado">
                        <div>
                            <strong>Processo #<?php echo htmlspecialchars($relacionado['id']); ?></strong>
                            <div style="display: flex; gap: 10px; margin-top: 5px; flex-wrap: wrap;">
                                <span class="status-badge status-<?php echo $relacionado['status']; ?>" style="font-size: 0.8rem;">
                                    <?php echo $status_labels[$relacionado['status']] ?? $relacionado['status']; ?>
                                </span>
                                <span class="priority-badge priority-<?php echo $relacionado['prioridade']; ?>" style="font-size: 0.7rem;">
                                    <?php echo ucfirst($relacionado['prioridade']); ?>
                                </span>
                                <span class="empresa-badge"><?php echo $relacionado['total_empresas']; ?> empresa(s)</span>
                                <?php if ($relacionado['recorrente'] != 'nao'): ?>
                                    <span class="recorrente-badge">
                                        <i class="fas fa-sync-alt"></i> <?php echo ucfirst($relacionado['recorrente']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <a href="detalhes-processo.php?id=<?php echo $relacionado['id']; ?>" class="btn" style="padding: 8px 16px; font-size: 0.9rem;">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card de Histórico -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Histórico do Processo</h2>
            </div>

            <div class="history-list">
                <?php if (is_array($historico) && count($historico) > 0): ?>
                    <?php foreach ($historico as $item): ?>
                        <div class="history-item">
                            <div class="history-icon 
                                <?php 
                                switch($item['acao']) {
                                    case 'criacao': echo 'green'; break;
                                    case 'atualizacao': echo 'blue'; break;
                                    case 'conclusao': echo 'purple'; break;
                                    default: echo 'orange';
                                }
                                ?>">
                                <i class="fas 
                                    <?php 
                                    switch($item['acao']) {
                                        case 'criacao': echo 'fa-plus'; break;
                                        case 'atualizacao': echo 'fa-edit'; break;
                                        case 'conclusao': echo 'fa-check'; break;
                                        default: echo 'fa-info-circle';
                                    }
                                    ?>"></i>
                            </div>
                            <div class="history-content">
                                <div class="history-title"><?php echo htmlspecialchars($item['nome_completo']); ?></div>
                                <div class="history-description">
                                    <?php 
                                    $acoes = [
                                        'criacao' => 'criou o processo',
                                        'atualizacao' => 'atualizou o processo',
                                        'conclusao' => 'concluiu o processo'
                                    ];
                                    echo $acoes[$item['acao']] ?? $item['acao'];
                                    ?>
                                    <?php if ($item['descricao']): ?>
                                        - <?php echo htmlspecialchars($item['descricao']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="history-time">
                                <?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--gray);">
                        <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>Nenhum histórico encontrado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll suave para processos relacionados
            const links = document.querySelectorAll('a[href^="#"]');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>