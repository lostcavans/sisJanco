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

// Buscar estatísticas gerais
if (temPermissaoGestao('analista')) {
    // Analistas veem tudo
    $sql_total_processos = "SELECT COUNT(*) as total FROM gestao_processos WHERE ativo = 1";
    $sql_processos_status = "SELECT status, COUNT(*) as total FROM gestao_processos WHERE ativo = 1 GROUP BY status";
    $sql_empresas = "SELECT COUNT(*) as total FROM empresas WHERE ativo = 1";
    $sql_usuarios = "SELECT COUNT(*) as total FROM gestao_usuarios WHERE ativo = 1";
    $sql_documentos = "SELECT COUNT(*) as total FROM gestao_documentacoes_empresa";
    $sql_nfe = "SELECT COUNT(*) as total FROM nfe WHERE status = 'processada'";
} else {
    // Auxiliares veem apenas seus processos
    $sql_total_processos = "SELECT COUNT(*) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ?";
    $sql_processos_status = "SELECT status, COUNT(*) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ? GROUP BY status";
    $sql_empresas = "SELECT COUNT(DISTINCT empresa_id) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ?";
    $sql_usuarios = "SELECT COUNT(*) as total FROM gestao_usuarios WHERE ativo = 1 AND id = ?";
    $sql_documentos = "SELECT COUNT(*) as total FROM gestao_documentacoes_empresa WHERE usuario_recebimento_id = ?";
    $sql_nfe = "SELECT COUNT(*) as total FROM nfe WHERE usuario_id = ? AND status = 'processada'";
}

// Executar consultas
$total_processos = executarConsulta($sql_total_processos, $usuario_id);
$processos_status = executarConsultaArray($sql_processos_status, $usuario_id);
$total_empresas = executarConsulta($sql_empresas, $usuario_id);
$total_usuarios = executarConsulta($sql_usuarios, $usuario_id);
$total_documentos = executarConsulta($sql_documentos, $usuario_id);
$total_nfe = executarConsulta($sql_nfe, $usuario_id);

// Buscar processos por categoria
if (temPermissaoGestao('analista')) {
    $sql_categorias = "SELECT c.nome, c.cor, COUNT(p.id) as total 
                      FROM gestao_categorias_processo c 
                      LEFT JOIN gestao_processos p ON c.id = p.categoria_id AND p.ativo = 1 
                      WHERE c.ativo = 1 
                      GROUP BY c.id, c.nome, c.cor 
                      ORDER BY total DESC";
} else {
    $sql_categorias = "SELECT c.nome, c.cor, COUNT(p.id) as total 
                      FROM gestao_categorias_processo c 
                      LEFT JOIN gestao_processos p ON c.id = p.categoria_id AND p.ativo = 1 AND p.responsavel_id = ?
                      WHERE c.ativo = 1 
                      GROUP BY c.id, c.nome, c.cor 
                      ORDER BY total DESC";
}
$categorias = executarConsultaArray($sql_categorias, $usuario_id);

// Buscar processos por prioridade
if (temPermissaoGestao('analista')) {
    $sql_prioridades = "SELECT prioridade, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 
                       GROUP BY prioridade 
                       ORDER BY FIELD(prioridade, 'urgente', 'alta', 'media', 'baixa')";
} else {
    $sql_prioridades = "SELECT prioridade, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND responsavel_id = ? 
                       GROUP BY prioridade 
                       ORDER BY FIELD(prioridade, 'urgente', 'alta', 'media', 'baida')";
}
$prioridades = executarConsultaArray($sql_prioridades, $usuario_id);

// Buscar processos recorrentes
if (temPermissaoGestao('analista')) {
    $sql_recorrentes = "SELECT recorrente, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND recorrente != 'nao' 
                       GROUP BY recorrente 
                       ORDER BY total DESC";
} else {
    $sql_recorrentes = "SELECT recorrente, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND recorrente != 'nao' AND responsavel_id = ? 
                       GROUP BY recorrente 
                       ORDER BY total DESC";
}
$recorrentes = executarConsultaArray($sql_recorrentes, $usuario_id);

// Buscar top responsáveis
if (temPermissaoGestao('analista')) {
    $sql_responsaveis = "SELECT u.nome_completo, COUNT(p.id) as total 
                        FROM gestao_usuarios u 
                        LEFT JOIN gestao_processos p ON u.id = p.responsavel_id AND p.ativo = 1 
                        WHERE u.ativo = 1 
                        GROUP BY u.id, u.nome_completo 
                        ORDER BY total DESC 
                        LIMIT 5";
} else {
    $sql_responsaveis = "SELECT u.nome_completo, COUNT(p.id) as total 
                        FROM gestao_usuarios u 
                        LEFT JOIN gestao_processos p ON u.id = p.responsavel_id AND p.ativo = 1 AND p.responsavel_id = ?
                        WHERE u.ativo = 1 AND u.id = ?
                        GROUP BY u.id, u.nome_completo 
                        ORDER BY total DESC 
                        LIMIT 5";
}
$top_responsaveis = executarConsultaArray($sql_responsaveis, $usuario_id);

// Buscar estatísticas de documentos
if (temPermissaoGestao('analista')) {
    $sql_docs_status = "SELECT status, COUNT(*) as total 
                       FROM gestao_documentacoes_empresa 
                       GROUP BY status";
} else {
    $sql_docs_status = "SELECT status, COUNT(*) as total 
                       FROM gestao_documentacoes_empresa 
                       WHERE usuario_recebimento_id = ? 
                       GROUP BY status";
}
$documentos_status = executarConsultaArray($sql_docs_status, $usuario_id);

// Buscar estatísticas de NFes
if (temPermissaoGestao('analista')) {
    $sql_nfe_mes = "SELECT COUNT(*) as total, competencia_mes, competencia_ano 
                   FROM nfe 
                   GROUP BY competencia_ano, competencia_mes 
                   ORDER BY competencia_ano DESC, competencia_mes DESC 
                   LIMIT 6";
} else {
    $sql_nfe_mes = "SELECT COUNT(*) as total, competencia_mes, competencia_ano 
                   FROM nfe 
                   WHERE usuario_id = ? 
                   GROUP BY competencia_ano, competencia_mes 
                   ORDER BY competencia_ano DESC, competencia_mes DESC 
                   LIMIT 6";
}
$nfe_por_mes = executarConsultaArray($sql_nfe_mes, $usuario_id);

// Buscar processos próximos do vencimento
if (temPermissaoGestao('analista')) {
    $sql_vencimentos = "SELECT p.titulo, p.data_prevista, u.nome_completo as responsavel, 
                       DATEDIFF(p.data_prevista, CURDATE()) as dias_restantes 
                       FROM gestao_processos p 
                       LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
                       WHERE p.ativo = 1 AND p.status NOT IN ('concluido', 'cancelado') 
                       AND p.data_prevista IS NOT NULL 
                       AND p.data_prevista >= CURDATE() 
                       ORDER BY p.data_prevista ASC 
                       LIMIT 5";
} else {
    $sql_vencimentos = "SELECT p.titulo, p.data_prevista, u.nome_completo as responsavel, 
                       DATEDIFF(p.data_prevista, CURDATE()) as dias_restantes 
                       FROM gestao_processos p 
                       LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
                       WHERE p.ativo = 1 AND p.status NOT IN ('concluido', 'cancelado') 
                       AND p.data_prevista IS NOT NULL 
                       AND p.data_prevista >= CURDATE() 
                       AND p.responsavel_id = ? 
                       ORDER BY p.data_prevista ASC 
                       LIMIT 5";
}
$proximos_vencimentos = executarConsultaArray($sql_vencimentos, $usuario_id);

// Funções auxiliares para executar consultas
function executarConsulta($sql, $usuario_id = null) {
    global $conexao;
    $stmt = $conexao->prepare($sql);
    if ($usuario_id && strpos($sql, '?') !== false) {
        $stmt->bind_param("i", $usuario_id);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['total'] ?? 0;
}

function executarConsultaArray($sql, $usuario_id = null) {
    global $conexao;
    $stmt = $conexao->prepare($sql);
    if ($usuario_id && strpos($sql, '?') !== false) {
        $stmt->bind_param("i", $usuario_id);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

// Preparar dados para gráficos
$dados_status = [];
$status_labels = [
    'rascunho' => 'Rascunho',
    'pendente' => 'Pendente', 
    'em_andamento' => 'Em Andamento',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
    'pausado' => 'Pausado'
];

foreach ($processos_status as $status) {
    $dados_status[] = [
        'status' => $status_labels[$status['status']] ?? $status['status'],
        'total' => $status['total']
    ];
}

// Calcular totais para cards
$processos_concluidos = 0;
$processos_andamento = 0;
$processos_pendentes = 0;

foreach ($processos_status as $status) {
    switch ($status['status']) {
        case 'concluido':
            $processos_concluidos = $status['total'];
            break;
        case 'em_andamento':
            $processos_andamento = $status['total'];
            break;
        case 'pendente':
            $processos_pendentes = $status['total'];
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Gestão de Processos</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--gray);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.processos { border-left-color: #4361ee; }
        .stat-card.empresas { border-left-color: #7209b7; }
        .stat-card.usuarios { border-left-color: #2ec4b6; }
        .stat-card.documentos { border-left-color: #ff9f1c; }
        .stat-card.nfe { border-left-color: #e63946; }
        .stat-card.concluidos { border-left-color: #10b981; }
        .stat-card.andamento { border-left-color: #3b82f6; }
        .stat-card.pendentes { border-left-color: #f59e0b; }

        .stat-info h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .stat-info .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }

        .stat-card.processos .stat-icon { color: #4361ee; }
        .stat-card.empresas .stat-icon { color: #7209b7; }
        .stat-card.usuarios .stat-icon { color: #2ec4b6; }
        .stat-card.documentos .stat-icon { color: #ff9f1c; }
        .stat-card.nfe .stat-icon { color: #e63946; }
        .stat-card.concluidos .stat-icon { color: #10b981; }
        .stat-card.andamento .stat-icon { color: #3b82f6; }
        .stat-card.pendentes .stat-icon { color: #f59e0b; }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        .info-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .info-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            background: #f8f9fa;
        }

        .info-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-body {
            padding: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            flex: 1;
            color: var(--dark);
        }

        .info-value {
            font-weight: 600;
            color: var(--primary);
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .badge-info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

        .grid-2-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .grid-2-col {
                grid-template-columns: 1fr;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard-gestao.php" class="navbar-brand">
            <i class="fas fa-project-diagram"></i>
            Gestão de Processos
        </a>
        <ul class="navbar-nav">
            <li><a href="dashboard-gestao.php" class="nav-link">Dashboard</a></li>
            <li><a href="processos-gestao.php" class="nav-link">Processos</a></li>
            <li><a href="gestao-empresas.php" class="nav-link">Empresas</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link active">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Relatórios e Estatísticas</h1>
            <p class="page-subtitle">
                <i class="fas fa-chart-bar"></i>
                Visão completa do sistema - 
                <?php echo temPermissaoGestao('analista') ? 'Todos os dados' : 'Seus dados'; ?>
            </p>
        </div>

        <!-- Cards de Estatísticas Principais -->
        <div class="stats-grid">
            <div class="stat-card processos">
                <div class="stat-info">
                    <h3>Total de Processos</h3>
                    <div class="number"><?php echo $total_processos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            
            <div class="stat-card empresas">
                <div class="stat-info">
                    <h3>Empresas</h3>
                    <div class="number"><?php echo $total_empresas; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            
            <div class="stat-card usuarios">
                <div class="stat-info">
                    <h3>Usuários</h3>
                    <div class="number"><?php echo $total_usuarios; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card documentos">
                <div class="stat-info">
                    <h3>Documentos</h3>
                    <div class="number"><?php echo $total_documentos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>

            <div class="stat-card nfe">
                <div class="stat-info">
                    <h3>NFes Processadas</h3>
                    <div class="number"><?php echo $total_nfe; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>

            <div class="stat-card concluidos">
                <div class="stat-info">
                    <h3>Processos Concluídos</h3>
                    <div class="number"><?php echo $processos_concluidos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <div class="stat-card andamento">
                <div class="stat-info">
                    <h3>Em Andamento</h3>
                    <div class="number"><?php echo $processos_andamento; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
            </div>

            <div class="stat-card pendentes">
                <div class="stat-info">
                    <h3>Processos Pendentes</h3>
                    <div class="number"><?php echo $processos_pendentes; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>

        <!-- Gráficos e Estatísticas Detalhadas -->
        <div class="grid-2-col">
            <!-- Gráfico de Status -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Status dos Processos
                </h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Categorias -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-layer-group"></i>
                    Processos por Categoria
                </h3>
                <div class="chart-container">
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid-2-col">
            <!-- Top Responsáveis -->
            <div class="info-card">
                <div class="info-header">
                    <h3 class="info-title">
                        <i class="fas fa-user-tie"></i>
                        Top Responsáveis
                    </h3>
                </div>
                <div class="info-body">
                    <?php if (count($top_responsaveis) > 0): ?>
                        <?php foreach ($top_responsaveis as $responsavel): ?>
                            <div class="info-item">
                                <span class="info-label"><?php echo htmlspecialchars($responsavel['nome_completo']); ?></span>
                                <span class="info-value"><?php echo $responsavel['total']; ?> processos</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Nenhum responsável encontrado</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Próximos Vencimentos -->
            <div class="info-card">
                <div class="info-header">
                    <h3 class="info-title">
                        <i class="fas fa-calendar-day"></i>
                        Próximos Vencimentos
                    </h3>
                </div>
                <div class="info-body">
                    <?php if (count($proximos_vencimentos) > 0): ?>
                        <?php foreach ($proximos_vencimentos as $vencimento): ?>
                            <div class="info-item">
                                <div>
                                    <div class="info-label"><?php echo htmlspecialchars($vencimento['titulo']); ?></div>
                                    <small style="color: var(--gray);">
                                        <?php echo date('d/m/Y', strtotime($vencimento['data_prevista'])); ?> - 
                                        <?php echo htmlspecialchars($vencimento['responsavel']); ?>
                                    </small>
                                </div>
                                <span class="badge <?php echo $vencimento['dias_restantes'] <= 3 ? 'badge-danger' : ($vencimento['dias_restantes'] <= 7 ? 'badge-warning' : 'badge-info'); ?>">
                                    <?php echo $vencimento['dias_restantes']; ?> dias
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>Nenhum vencimento próximo</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid-2-col">
            <!-- Processos por Prioridade -->
            <div class="info-card">
                <div class="info-header">
                    <h3 class="info-title">
                        <i class="fas fa-flag"></i>
                        Processos por Prioridade
                    </h3>
                </div>
                <div class="info-body">
                    <?php if (count($prioridades) > 0): ?>
                        <?php foreach ($prioridades as $prioridade): ?>
                            <div class="info-item">
                                <span class="info-label" style="text-transform: capitalize;">
                                    <?php echo htmlspecialchars($prioridade['prioridade']); ?>
                                </span>
                                <span class="info-value"><?php echo $prioridade['total']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-flag"></i>
                            <p>Nenhum processo encontrado</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Processos Recorrentes -->
            <div class="info-card">
                <div class="info-header">
                    <h3 class="info-title">
                        <i class="fas fa-sync-alt"></i>
                        Processos Recorrentes
                    </h3>
                </div>
                <div class="info-body">
                    <?php if (count($recorrentes) > 0): ?>
                        <?php foreach ($recorrentes as $recorrente): ?>
                            <div class="info-item">
                                <span class="info-label" style="text-transform: capitalize;">
                                    <?php echo htmlspecialchars($recorrente['recorrente']); ?>
                                </span>
                                <span class="info-value"><?php echo $recorrente['total']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-sync-alt"></i>
                            <p>Nenhum processo recorrente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Estatísticas de Documentos -->
        <div class="info-card">
            <div class="info-header">
                <h3 class="info-title">
                    <i class="fas fa-file-contract"></i>
                    Status de Documentações
                </h3>
            </div>
            <div class="info-body">
                <?php if (count($documentos_status) > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <?php foreach ($documentos_status as $doc_status): ?>
                            <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                                    <?php echo $doc_status['total']; ?>
                                </div>
                                <div style="color: var(--gray); text-transform: capitalize;">
                                    <?php echo htmlspecialchars($doc_status['status']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>Nenhuma documentação encontrada</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dados dos gráficos
            const statusData = <?php echo json_encode($dados_status); ?>;
            const categoriasData = <?php echo json_encode($categorias); ?>;
            const prioridadesData = <?php echo json_encode($prioridades); ?>;

            // Gráfico de Status
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(item => item.status),
                    datasets: [{
                        data: statusData.map(item => item.total),
                        backgroundColor: [
                            '#6B7280', // Rascunho
                            '#F59E0B', // Pendente
                            '#3B82F6', // Em Andamento
                            '#10B981', // Concluído
                            '#EF4444', // Cancelado
                            '#9CA3AF'  // Pausado
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Gráfico de Categorias
            const categoriasCtx = document.getElementById('categoriasChart').getContext('2d');
            const categoriasChart = new Chart(categoriasCtx, {
                type: 'bar',
                data: {
                    labels: categoriasData.map(item => item.nome),
                    datasets: [{
                        label: 'Processos por Categoria',
                        data: categoriasData.map(item => item.total),
                        backgroundColor: categoriasData.map(item => item.cor || '#4361ee'),
                        borderColor: categoriasData.map(item => item.cor || '#4361ee'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>