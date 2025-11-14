<?php
session_start();
include("config-gestao.php");

// Verificar autenticação
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$empresa_id = $_SESSION['empresa_id_gestao'];

// Buscar estatísticas para os cards
$sql_total = "SELECT COUNT(*) as total FROM gestao_processos WHERE empresa_id = ? AND ativo = 1";
$stmt = $conexao->prepare($sql_total);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$total_processos = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$sql_ativos = "SELECT COUNT(*) as total FROM gestao_processos 
               WHERE empresa_id = ? AND status IN ('em_andamento', 'pendente') AND ativo = 1";
$stmt = $conexao->prepare($sql_ativos);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$processos_ativos = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$sql_responsaveis = "SELECT COUNT(DISTINCT responsavel_id) as total FROM gestao_processos 
                     WHERE empresa_id = ? AND ativo = 1";
$stmt = $conexao->prepare($sql_responsaveis);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$total_responsaveis = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$sql_pendentes = "SELECT COUNT(*) as total FROM gestao_processos 
                  WHERE empresa_id = ? AND status = 'pendente' AND ativo = 1";
$stmt = $conexao->prepare($sql_pendentes);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$processos_pendentes = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Buscar dados para gráficos
$sql_status = "SELECT status, COUNT(*) as total 
               FROM gestao_processos 
               WHERE empresa_id = ? AND ativo = 1 
               GROUP BY status";
$stmt = $conexao->prepare($sql_status);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$dados_status = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sql_responsaveis_detalhes = "SELECT u.nome_completo, COUNT(p.id) as total 
                              FROM gestao_processos p 
                              JOIN gestao_usuarios u ON p.responsavel_id = u.id 
                              WHERE p.empresa_id = ? AND p.ativo = 1 
                              GROUP BY u.nome_completo 
                              ORDER BY total DESC LIMIT 5";
$stmt = $conexao->prepare($sql_responsaveis_detalhes);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$dados_responsaveis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Buscar atividade recente
$sql_atividade = "SELECT h.*, u.nome_completo, p.titulo as processo_titulo 
                  FROM gestao_historicos_processo h 
                  JOIN gestao_usuarios u ON h.usuario_id = u.id 
                  JOIN gestao_processos p ON h.processo_id = p.id 
                  WHERE p.empresa_id = ? 
                  ORDER BY h.created_at DESC 
                  LIMIT 5";
$stmt = $conexao->prepare($sql_atividade);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$atividades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
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
            margin-bottom: 2rem;
            color: var(--white);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.green { border-left-color: #10b981; }
        .stat-card.purple { border-left-color: #8b5cf6; }
        .stat-card.yellow { border-left-color: #f59e0b; }

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

        .stat-card.blue .stat-icon { color: #3b82f6; }
        .stat-card.green .stat-icon { color: #10b981; }
        .stat-card.purple .stat-icon { color: #8b5cf6; }
        .stat-card.yellow .stat-icon { color: #f59e0b; }

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
        }

        .chart-container {
            height: 250px;
            position: relative;
        }

        .activity-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .activity-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .activity-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
        }

        .activity-icon.green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .activity-icon.blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .activity-icon.purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .activity-icon.orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

        .activity-content {
            flex: 1;
        }

        .activity-title-small {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .activity-description {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .page-title {
                font-size: 2rem;
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
            <li><a href="gestao-empresas.php" class="nav-link">Documentações</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Relatórios e Estatísticas</h1>
            <p class="page-subtitle">Acompanhe métricas e desempenho dos processos</p>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-info">
                    <h3>Total de Processos</h3>
                    <div class="number"><?php echo $total_processos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
            </div>
            
            <div class="stat-card green">
                <div class="stat-info">
                    <h3>Processos Ativos</h3>
                    <div class="number"><?php echo $processos_ativos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            
            <div class="stat-card purple">
                <div class="stat-info">
                    <h3>Total de Responsáveis</h3>
                    <div class="number"><?php echo $total_responsaveis; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card yellow">
                <div class="stat-info">
                    <h3>Processos Pendentes</h3>
                    <div class="number"><?php echo $processos_pendentes; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title">Status dos Processos</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3 class="chart-title">Processos por Responsável</h3>
                <div class="chart-container">
                    <canvas id="responsibleChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Atividade Recente -->
        <div class="activity-card">
            <div class="activity-header">
                <h3 class="activity-title">Atividade Recente</h3>
            </div>
            <div class="activity-body">
                <?php if (count($atividades) > 0): ?>
                    <?php foreach ($atividades as $atividade): ?>
                        <div class="activity-item">
                            <div class="activity-icon 
                                <?php 
                                switch($atividade['acao']) {
                                    case 'criacao': echo 'green'; break;
                                    case 'atualizacao': echo 'blue'; break;
                                    case 'conclusao': echo 'purple'; break;
                                    default: echo 'orange';
                                }
                                ?>">
                                <i class="fas 
                                    <?php 
                                    switch($atividade['acao']) {
                                        case 'criacao': echo 'fa-plus'; break;
                                        case 'atualizacao': echo 'fa-edit'; break;
                                        case 'conclusao': echo 'fa-check'; break;
                                        default: echo 'fa-info-circle';
                                    }
                                    ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title-small"><?php echo htmlspecialchars($atividade['processo_titulo']); ?></div>
                                <div class="activity-description">
                                    <?php echo htmlspecialchars($atividade['nome_completo']); ?> - 
                                    <?php 
                                    $acoes = [
                                        'criacao' => 'criou o processo',
                                        'atualizacao' => 'atualizou o processo',
                                        'conclusao' => 'concluiu o processo'
                                    ];
                                    echo $acoes[$atividade['acao']] ?? $atividade['acao'];
                                    ?>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php echo date('d/m/Y H:i', strtotime($atividade['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-item" style="justify-content: center; padding: 2rem;">
                        <i class="fas fa-inbox" style="font-size: 2rem; color: var(--gray); margin-right: 1rem;"></i>
                        <div style="color: var(--gray);">Nenhuma atividade recente</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dados dos gráficos
            const statusData = <?php echo json_encode($dados_status); ?>;
            const responsaveisData = <?php echo json_encode($dados_responsaveis); ?>;

            // Gráfico de Status
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(item => {
                        const labels = {
                            'rascunho': 'Rascunho',
                            'pendente': 'Pendente',
                            'em_andamento': 'Em Andamento',
                            'concluido': 'Concluído',
                            'cancelado': 'Cancelado',
                            'pausado': 'Pausado'
                        };
                        return labels[item.status] || item.status;
                    }),
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

            // Gráfico de Responsáveis
            const responsibleCtx = document.getElementById('responsibleChart').getContext('2d');
            const responsibleChart = new Chart(responsibleCtx, {
                type: 'bar',
                data: {
                    labels: responsaveisData.map(item => item.nome_completo),
                    datasets: [{
                        label: 'Processos por Responsável',
                        data: responsaveisData.map(item => item.total),
                        backgroundColor: '#3B82F6',
                        borderColor: '#2563EB',
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