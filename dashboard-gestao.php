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

// Verificar se há filtro aplicado
$filtro_status = $_GET['status'] ?? 'todos';

// Construir WHERE clause baseada no filtro
$where_conditions = ["p.ativo = 1"];
$where_params = [];
$where_types = "";

if ($filtro_status !== 'todos') {
    $where_conditions[] = "p.status = ?";
    $where_params[] = $filtro_status;
    $where_types .= "s";
}

if (!temPermissaoGestao('analista')) {
    $where_conditions[] = "p.responsavel_id = ?";
    $where_params[] = $usuario_id;
    $where_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Buscar processos para o dashboard - QUERY COMPATÍVEL COM EMPRESA-PROCESSOS
$sql = "SELECT p.*, u.nome_completo as responsavel_nome, c.nome as categoria_nome,
               (SELECT COUNT(DISTINCT empresa_id) FROM gestao_processo_checklist WHERE processo_id = p.id) as total_empresas_checklist,
               (SELECT COUNT(DISTINCT empresa_id) FROM gestao_processo_checklist WHERE processo_id = p.id AND concluido = 1) as empresas_concluidas_checklist,
               -- Calcular status real baseado no progresso dos checklists (MESMA LÓGICA DO EMPRESA-PROCESSOS)
               CASE 
                   WHEN (SELECT COUNT(*) FROM gestao_processo_checklist WHERE processo_id = p.id) = 0 THEN 'pendente'
                   WHEN (SELECT COUNT(*) FROM gestao_processo_checklist WHERE processo_id = p.id AND concluido = 1) = 
                        (SELECT COUNT(*) FROM gestao_processo_checklist WHERE processo_id = p.id) THEN 'concluido'
                   WHEN (SELECT COUNT(*) FROM gestao_processo_checklist WHERE processo_id = p.id AND concluido = 1) > 0 THEN 'em_andamento'
                   ELSE 'pendente'
               END as status_real
        FROM gestao_processos p 
        LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
        LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id 
        WHERE $where_clause
        ORDER BY p.created_at DESC 
        LIMIT 10";

$stmt = $conexao->prepare($sql);
if (!empty($where_params)) {
    $stmt->bind_param($where_types, ...$where_params);
}
$stmt->execute();
$processos_dashboard = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular progresso e status usando funções padronizadas
foreach ($processos_dashboard as &$processo) {
    $processo['status_real'] = calcularStatusProcesso($processo['id']);
    $processo['progresso'] = calcularProgressoProcesso($processo['id']);
    
    // Buscar e armazenar estatísticas de checklist para uso no HTML
    $sql_checklist_stats = "SELECT 
        COUNT(*) as total_checklists,
        SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END) as checklists_concluidos
        FROM gestao_processo_checklist 
        WHERE processo_id = ?";
    
    $stmt_stats = $conexao->prepare($sql_checklist_stats);
    $stmt_stats->bind_param("i", $processo['id']);
    $stmt_stats->execute();
    $checklist_stats = $stmt_stats->get_result()->fetch_assoc();
    $stmt_stats->close();
    
    $processo['total_checklists'] = $checklist_stats['total_checklists'] ?? 0;
    $processo['checklists_concluidos'] = $checklist_stats['checklists_concluidos'] ?? 0;
}
unset($processo);

// Calcular progresso e status usando funções padronizadas
foreach ($processos_dashboard as &$processo) {
    $processo['status_real'] = calcularStatusProcesso($processo['id']);
    $processo['progresso'] = calcularProgressoProcesso($processo['id']);
}
unset($processo);

// Buscar estatísticas para o dashboard (COMPATÍVEL COM EMPRESA-PROCESSOS)
if (temPermissaoGestao('analista')) {
    $sql_estatisticas = "SELECT 
        COUNT(*) as total_processos,
        SUM(CASE 
            WHEN (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) = 
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) AND
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) > 0 THEN 1 
            ELSE 0 
        END) as processos_concluidos,
        SUM(CASE 
            WHEN (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) > 0 AND
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) < 
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) THEN 1 
            ELSE 0 
        END) as processos_andamento,
        SUM(CASE 
            WHEN (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) = 0 OR
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) = 0 THEN 1 
            ELSE 0 
        END) as processos_pendentes
        FROM gestao_processos p WHERE ativo = 1";
} else {
    $sql_estatisticas = "SELECT 
        COUNT(*) as total_processos,
        SUM(CASE 
            WHEN (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) = 
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) AND
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) > 0 THEN 1 
            ELSE 0 
        END) as processos_concluidos,
        SUM(CASE 
            WHEN (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) > 0 AND
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) < 
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) THEN 1 
            ELSE 0 
        END) as processos_andamento,
        SUM(CASE 
            WHEN (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id) = 0 OR
                 (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) = 0 THEN 1 
            ELSE 0 
        END) as processos_pendentes
        FROM gestao_processos p WHERE ativo = 1 AND responsavel_id = ?";
}

$stmt_estatisticas = $conexao->prepare($sql_estatisticas);
if (temPermissaoGestao('analista')) {
    $stmt_estatisticas->execute();
} else {
    $stmt_estatisticas->bind_param("i", $usuario_id);
    $stmt_estatisticas->execute();
}
$estatisticas = $stmt_estatisticas->get_result()->fetch_assoc();
$stmt_estatisticas->close();

// Determinar classe ativa para os filtros
$filtro_classes = [
    'todos' => $filtro_status === 'todos' ? 'active' : '',
    'concluido' => $filtro_status === 'concluido' ? 'active' : '',
    'em_andamento' => $filtro_status === 'em_andamento' ? 'active' : '',
    'pendente' => $filtro_status === 'pendente' ? 'active' : ''
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestão de Processos</title>
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
            max-width: 1200px;
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

        /* ESTATÍSTICAS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            border-top: 4px solid var(--primary);
        }

        .stat-card.success { border-top-color: #10b981; }
        .stat-card.warning { border-top-color: #f59e0b; }
        .stat-card.info { border-top-color: #3b82f6; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-weight: 500;
        }

        /* CARDS DE PROCESSOS */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .process-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .process-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .process-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .process-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            flex: 1;
        }
        
        .process-meta {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        /* BARRA DE PROGRESSO - DASHBOARD */
        .process-progress {
            margin: 1rem 0;
        }

        .progress-bar-small {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            width: 100%;
        }

        .progress-fill-small {
            background: linear-gradient(90deg, #10b981, #34d399);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
            min-width: 8px; /* Garante que seja visível mesmo com 0% */
        }

        /* Cores diferentes baseadas no progresso */
        .progress-fill-small.baixa { background: #10b981; }
        .progress-fill-small.media { background: #3b82f6; }
        .progress-fill-small.alta { background: #f59e0b; }
        .progress-fill-small.completo { background: #10b981; }
        
        .process-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* BADGES */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
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
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
        }

        .priority-baixa { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .priority-media { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .priority-alta { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .priority-urgente { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .recorrente-badge {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
        }

        .user-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        /* ALERTAS */
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
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .process-actions {
                flex-direction: column;
            }
        }

        /* Estilos para os filtros */
.filter-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.filter-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

.filter-card.active {
    border-color: var(--primary);
    box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
}

.filter-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
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
            <li><a href="gestao-empresas.php" class="nav-link">Empresas</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <div style="color: #6b7280;">
                <i class="fas fa-user"></i> 
                <?php echo temPermissaoGestao('analista') ? 'Analista' : 'Auxiliar'; ?> - 
                <?php echo $_SESSION['usuario_nome_gestao']; ?>
            </div>
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

        <!-- ESTATÍSTICAS COM FILTROS -->
        <div class="stats-grid">
            <div class="stat-card filter-card <?php echo $filtro_classes['todos']; ?>" onclick="aplicarFiltro('todos')">
                <div class="stat-number"><?php echo $estatisticas['total_processos']; ?></div>
                <div class="stat-label">Total de Obrigações</div>
                <i class="fas fa-tasks" style="font-size: 2rem; color: var(--primary); margin-top: 1rem;"></i>
            </div>
            
            <div class="stat-card success filter-card <?php echo $filtro_classes['concluido']; ?>" onclick="aplicarFiltro('concluido')">
                <div class="stat-number"><?php echo $estatisticas['processos_concluidos']; ?></div>
                <div class="stat-label">Obrigações Concluídas</div>
                <i class="fas fa-check-circle" style="font-size: 2rem; color: #10b981; margin-top: 1rem;"></i>
            </div>
            
            <div class="stat-card warning filter-card <?php echo $filtro_classes['em_andamento']; ?>" onclick="aplicarFiltro('em_andamento')">
                <div class="stat-number"><?php echo $estatisticas['processos_andamento']; ?></div>
                <div class="stat-label">Em Andamento</div>
                <i class="fas fa-spinner" style="font-size: 2rem; color: #f59e0b; margin-top: 1rem;"></i>
            </div>
            
            <div class="stat-card info filter-card <?php echo $filtro_classes['pendente']; ?>" onclick="aplicarFiltro('pendente')">
                <div class="stat-number"><?php echo $estatisticas['processos_pendentes']; ?></div>
                <div class="stat-label">Obrigações Pendentes</div>
                <i class="fas fa-clock" style="font-size: 2rem; color: #3b82f6; margin-top: 1rem;"></i>
            </div>
        </div>

        <!-- Indicador de Filtro Ativo -->
        <?php if ($filtro_status !== 'todos'): ?>
        <div class="alert alert-info" style="margin-bottom: 1.5rem;">
            <i class="fas fa-filter"></i>
            Filtro ativo: 
            <?php 
            $filtros_nomes = [
                'concluido' => 'Concluídos',
                'em_andamento' => 'Em Andamento', 
                'pendente' => 'Pendentes'
            ];
            echo $filtros_nomes[$filtro_status] ?? ucfirst($filtro_status);
            ?>
            <a href="dashboard-gestao.php" class="btn btn-small" style="margin-left: 1rem;">
                <i class="fas fa-times"></i> Limpar Filtro
            </a>
        </div>
        <?php endif; ?>

        <h2 style="margin-bottom: 1.5rem; color: #374151;">
            <i class="fas fa-tasks"></i> 
            <?php echo temPermissaoGestao('analista') ? 'Todas as Obrigações' : 'Minhas Obrigações'; ?>
        </h2>

        <div class="dashboard-cards">
            <?php if (count($processos_dashboard) > 0): ?>
                <?php foreach ($processos_dashboard as $processo): ?>
                    <div class="process-card">
                        <div class="process-card-header">
                            <div style="flex: 1;">
                                <div class="process-title"><?php echo htmlspecialchars($processo['titulo']); ?></div>
                                <div style="font-size: 0.8rem; color: #6b7280;">
                                    Código: <?php echo htmlspecialchars($processo['codigo']); ?>
                                    <?php if ($processo['categoria_nome']): ?>
                                        • <?php echo htmlspecialchars($processo['categoria_nome']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $processo['status_real']; ?>">
                                <?php 
                                $status_labels = [
                                    'rascunho' => 'Rascunho',
                                    'pendente' => 'Pendente',
                                    'em_andamento' => 'Em Andamento',
                                    'concluido' => 'Concluído',
                                    'cancelado' => 'Cancelado',
                                    'pausado' => 'Pausado'
                                ];
                                echo $status_labels[$processo['status_real']] ?? $processo['status_real'];
                                ?>
                            </span>
                        </div>
                        
                        <div class="process-meta">
                            <span class="priority-badge priority-<?php echo $processo['prioridade']; ?>">
                                <?php echo ucfirst($processo['prioridade']); ?>
                            </span>
                            <span style="font-size: 0.8rem; color: #6b7280;">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($processo['responsavel_nome']); ?>
                            </span>
                            <?php if ($processo['recorrente'] != 'nao'): ?>
                                <span class="recorrente-badge">
                                    <i class="fas fa-sync-alt"></i> <?php echo ucfirst($processo['recorrente']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($processo['data_prevista']): ?>
                            <div style="font-size: 0.8rem; color: #6b7280; margin-bottom: 0.5rem;">
                                <i class="fas fa-calendar"></i> 
                                Previsão: <?php echo date('d/m/Y', strtotime($processo['data_prevista'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php 
                        // Buscar estatísticas de checklist para exibir
                        $sql_checklist_stats = "SELECT 
                            COUNT(*) as total_checklists,
                            SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END) as checklists_concluidos
                            FROM gestao_processo_checklist 
                            WHERE processo_id = ?";
                        $stmt_stats = $conexao->prepare($sql_checklist_stats);
                        $stmt_stats->bind_param("i", $processo['id']);
                        $stmt_stats->execute();
                        $checklist_stats = $stmt_stats->get_result()->fetch_assoc();
                        $stmt_stats->close();

                        $total_checklists = $checklist_stats['total_checklists'] ?? 0;
                        $checklists_concluidos = $checklist_stats['checklists_concluidos'] ?? 0;
                        ?>

                        <?php if ($processo['total_checklists'] > 0): ?>
                        <div class="process-progress">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="font-size: 0.8rem; color: #6b7280;">Progresso</span>
                                <span style="font-size: 0.8rem; font-weight: 600; color: #374151;">
                                    <?php echo $processo['progresso']; ?>%
                                </span>
                            </div>
                            <div class="progress-bar-small">
                                <div class="progress-fill-small 
                                    <?php 
                                    if ($processo['progresso'] == 100) echo 'completo';
                                    elseif ($processo['progresso'] >= 70) echo 'alta';
                                    elseif ($processo['progresso'] >= 30) echo 'media';
                                    else echo 'baixa';
                                    ?>"
                                    style="width: <?php echo $processo['progresso']; ?>%;">
                                </div>
                            </div>
                            <div style="font-size: 0.7rem; color: #6b7280; text-align: right; margin-top: 0.25rem;">
                                <?php echo $processo['checklists_concluidos']; ?>/<?php echo $processo['total_checklists']; ?> checklists
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="process-actions">
                            <a href="checklist-processo.php?id=<?php echo $processo['id']; ?>" class="btn btn-small btn-success">
                                <i class="fas fa-list-check"></i> Checklist
                            </a>
                            <a href="detalhes-processo.php?id=<?php echo $processo['id']; ?>" class="btn btn-small btn-secondary">
                                <i class="fas fa-eye"></i> Detalhes
                            </a>
                            <?php if (temPermissaoGestao('analista')): ?>
                                <a href="editar-processo.php?id=<?php echo $processo['id']; ?>" class="btn btn-small btn-warning">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>Nenhuma obrigação encontrada</p>
                    <?php if (temPermissaoGestao('analista')): ?>
                        <a href="processos-gestao.php" class="btn" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Criar Primeira Obrigação
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- LINK PARA VER TODOS OS PROCESSOS -->
        <?php if (count($processos_dashboard) > 0): ?>
        <div style="text-align: center; margin-top: 2rem;">
            <a href="processos-gestao.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> Ver Todas as Obrigações
            </a>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // Debug das barras de progresso
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DEBUG BARRAS DE PROGRESSO ===');
            const progressBars = document.querySelectorAll('.progress-fill-small');
            
            progressBars.forEach((bar, index) => {
                const width = bar.style.width;
                const computedStyle = window.getComputedStyle(bar);
                const backgroundColor = computedStyle.backgroundColor;
                
                console.log(`Barra ${index + 1}:`, {
                    width: width,
                    backgroundColor: backgroundColor,
                    element: bar
                });
                
                // Forçar cor verde se necessário
                if (width === '100%' && !backgroundColor.includes('rgb(16, 185, 129)')) {
                    bar.style.background = '#10b981';
                    console.log(`Corrigindo barra ${index + 1} para verde`);
                }
            });
            console.log('=== FIM DEBUG ===');
        });

        // Função para aplicar filtro
        function aplicarFiltro(status) {
            const url = new URL(window.location.href);
            if (status === 'todos') {
                url.searchParams.delete('status');
            } else {
                url.searchParams.set('status', status);
            }
            window.location.href = url.toString();
        }

        // Adicionar classes de filtro ativo
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const statusFiltro = urlParams.get('status') || 'todos';
            
            // Animar barras de progresso
            const progressBars = document.querySelectorAll('.progress-fill-small');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>