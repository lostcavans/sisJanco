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

// Buscar empresas da tabela EMPRESAS (sistema contábil)
$sql = "SELECT e.*,
               (SELECT COUNT(*) FROM gestao_documentacoes_empresa de WHERE de.empresa_id = e.id) as total_documentos,
               (SELECT COUNT(*) FROM gestao_documentacoes_empresa de WHERE de.empresa_id = e.id AND de.status = 'recebido') as documentos_recebidos,
               (SELECT COUNT(*) FROM gestao_documentacoes_empresa de WHERE de.empresa_id = e.id AND de.status = 'pendente') as documentos_pendentes,
               (SELECT COUNT(*) FROM gestao_documentacoes_empresa de WHERE de.empresa_id = e.id AND de.status = 'atrasado') as documentos_atrasados
        FROM empresas e
        WHERE e.ativo = 1";

// Filtro de pesquisa
$filtro_nome = '';
if (isset($_GET['pesquisa']) && !empty($_GET['pesquisa'])) {
    $filtro_nome = $_GET['pesquisa'];
    $sql .= " AND (e.razao_social LIKE ? OR e.cnpj LIKE ?)";
}

$sql .= " ORDER BY e.razao_social";

$stmt = $conexao->prepare($sql);

if (!empty($filtro_nome)) {
    $termo_pesquisa = "%$filtro_nome%";
    $stmt->bind_param("ss", $termo_pesquisa, $termo_pesquisa);
}

$stmt->execute();
$empresas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular percentuais
foreach ($empresas as &$empresa) {
    if ($empresa['total_documentos'] > 0) {
        $empresa['percentual_recebido'] = round(($empresa['documentos_recebidos'] / $empresa['total_documentos']) * 100);
    } else {
        $empresa['percentual_recebido'] = 0;
    }
}
unset($empresa);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentações - Gestão de Processos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #eef2ff;
            --secondary: #7209b7;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8f9fa;
            --dark: #1f2937;
            --gray: #6b7280;
            --gray-light: #d1d5db;
            --white: #ffffff;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        .navbar {
            background: var(--white);
            box-shadow: var(--shadow-lg);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            font-size: 1.25rem;
        }

        .navbar-nav {
            display: flex;
            gap: 1.5rem;
            list-style: none;
        }

        .nav-link {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: var(--primary-light);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
        }

        .btn {
            padding: 12px 24px;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--gray);
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .search-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 1rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .empresas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .empresa-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .empresa-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .empresa-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .empresa-nome {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .empresa-cnpj {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .empresa-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
            text-align: center;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }

        .stat-recebido { color: var(--success); }
        .stat-pendente { color: var(--warning); }
        .stat-atrasado { color: var(--danger); }

        .progress-container {
            margin: 1rem 0;
        }

        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            background: linear-gradient(90deg, var(--success), #34d399);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-text {
            font-size: 0.8rem;
            color: var(--gray);
            text-align: center;
        }

        .empresa-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.8rem;
        }

        .empresa-badge {
            display: inline-block;
            padding: 4px 8px;
            background: rgba(114, 9, 183, 0.1);
            color: var(--secondary);
            border-radius: 12px;
            font-size: 0.7rem;
            margin: 2px;
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
            
            .empresas-grid {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .empresa-stats {
                grid-template-columns: 1fr;
            }
            
            .empresa-actions {
                flex-direction: column;
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
            <li><a href="documentacoes-empresas.php" class="nav-link active">Documentações</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Documentações das Empresas</h1>
            <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                <a href="tipos-documentacao.php" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> Gerenciar Tipos
                </a>
            <?php endif; ?>
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

        <!-- BARRA DE PESQUISA -->
        <div class="search-container">
            <form method="GET" action="" class="search-form">
                <input type="text" 
                       name="pesquisa" 
                       class="search-input" 
                       placeholder="Pesquisar por nome da empresa ou CNPJ..."
                       value="<?php echo htmlspecialchars($filtro_nome); ?>">
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> Pesquisar
                </button>
                <?php if (!empty($filtro_nome)): ?>
                    <a href="documentacoes-empresas.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- LISTA DE EMPRESAS -->
        <div class="empresas-grid">
            <?php if (count($empresas) > 0): ?>
                <?php foreach ($empresas as $empresa): ?>
                    <div class="empresa-card">
                        <div class="empresa-header">
                            <div>
                                <div class="empresa-nome"><?php echo htmlspecialchars($empresa['razao_social']); ?></div>
                                <div class="empresa-cnpj">CNPJ: <?php echo htmlspecialchars($empresa['cnpj']); ?></div>
                            </div>
                            <div style="text-align: right;">
                                <span class="empresa-badge"><?php echo htmlspecialchars($empresa['regime_tributario']); ?></span>
                                <span class="empresa-badge"><?php echo htmlspecialchars($empresa['atividade']); ?></span>
                            </div>
                        </div>

                        <!-- ESTATÍSTICAS -->
                        <div class="empresa-stats">
                            <div class="stat-item">
                                <div class="stat-number stat-recebido"><?php echo $empresa['documentos_recebidos']; ?></div>
                                <div class="stat-label">Recebidos</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number stat-pendente"><?php echo $empresa['documentos_pendentes']; ?></div>
                                <div class="stat-label">Pendentes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number stat-atrasado"><?php echo $empresa['documentos_atrasados']; ?></div>
                                <div class="stat-label">Atrasados</div>
                            </div>
                        </div>

                        <!-- BARRA DE PROGRESSO -->
                        <?php if ($empresa['total_documentos'] > 0): ?>
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $empresa['percentual_recebido']; ?>%;"></div>
                            </div>
                            <div class="progress-text">
                                <?php echo $empresa['percentual_recebido']; ?>% completo • 
                                <?php echo $empresa['total_documentos']; ?> documento(s)
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; color: var(--gray); padding: 1rem;">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhuma documentação cadastrada</p>
                        </div>
                        <?php endif; ?>

                        <div class="empresa-actions">
                            <a href="documentacoes-empresa.php?id=<?php echo $empresa['id']; ?>" class="btn btn-small">
                                <i class="fas fa-folder-open"></i> Ver Documentações
                            </a>
                            <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                                <a href="gerenciar-documentacoes.php?empresa_id=<?php echo $empresa['id']; ?>" class="btn btn-small btn-success">
                                    <i class="fas fa-edit"></i> Gerenciar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-building" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p><?php echo empty($filtro_nome) ? 'Nenhuma empresa cadastrada' : 'Nenhuma empresa encontrada'; ?></p>
                    <?php if (!empty($filtro_nome)): ?>
                        <a href="documentacoes-empresas.php" class="btn" style="margin-top: 1rem;">
                            <i class="fas fa-arrow-left"></i> Ver Todas as Empresas
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animar barras de progresso
            const progressBars = document.querySelectorAll('.progress-fill');
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