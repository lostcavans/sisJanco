<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];

// Buscar empresas com estatísticas de processos
$sql = "SELECT e.*,
               (SELECT COUNT(*) FROM gestao_processo_empresas pe WHERE pe.empresa_id = e.id) as total_processos,
               (SELECT COUNT(*) FROM gestao_processo_empresas pe 
                INNER JOIN gestao_processos p ON pe.processo_id = p.id 
                WHERE pe.empresa_id = e.id AND p.status = 'concluido') as processos_concluidos,
               (SELECT COUNT(*) FROM gestao_processo_empresas pe 
                INNER JOIN gestao_processos p ON pe.processo_id = p.id 
                WHERE pe.empresa_id = e.id AND p.status = 'pendente') as processos_pendentes,
               (SELECT COUNT(*) FROM gestao_processo_empresas pe 
                INNER JOIN gestao_processos p ON pe.processo_id = p.id 
                WHERE pe.empresa_id = e.id AND p.status = 'atrasado') as processos_atrasados
        FROM empresas e
        WHERE 1=1";

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
    if ($empresa['total_processos'] > 0) {
        $empresa['progresso_processos'] = round(($empresa['processos_concluidos'] / $empresa['total_processos']) * 100);
    } else {
        $empresa['progresso_processos'] = 0;
    }
}
unset($empresa);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Empresas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="gestao-processos/gestao-styles.css">
</head>
<body>
    <nav class="navbar">
        <a href="dashboard-gestao.php" class="navbar-brand">
            <img src="uploads/logo-images/ANTONIO LOGO 2.png" alt="Descrição da imagem" style="width: 75px; height: 50px;">
            Gestão de Processos
        </a>
        <ul class="navbar-nav">
            <li><a href="dashboard-gestao.php" class="nav-link">Dashboard</a></li>
            <?php if (in_array($nivel_usuario, ['admin', 'analista'])): ?>
                <li><a href="processos-gestao.php" class="nav-link">Processos</a></li>
            <?php endif; ?>
            <li><a href="gestao-empresas.php" class="nav-link active">Empresas</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Gestão de Empresas</h1>
            <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                <a href="tipos-documentacao.php" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> Gerenciar Tipos
                </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-error fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?>
            </div>
        <?php endif; ?>

        <!-- Barra de Pesquisa -->
        <div class="form-section fade-in">
            <form method="GET" action="" class="search-form">
                <div class="form-grid" style="grid-template-columns: 1fr auto auto;">
                    <div class="form-group">
                        <input type="text" 
                               name="pesquisa" 
                               class="search-input" 
                               placeholder="Pesquisar por nome da empresa ou CNPJ..."
                               value="<?php echo htmlspecialchars($filtro_nome); ?>">
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Pesquisar
                    </button>
                    <?php if (!empty($filtro_nome)): ?>
                        <a href="gestao-empresas.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Lista de Empresas -->
        <div class="empresas-grid">
            <?php if (count($empresas) > 0): ?>
                <?php foreach ($empresas as $empresa): ?>
                    <div class="empresa-card fade-in">
                        <div class="empresa-header">
                            <div>
                                <div class="empresa-nome"><?php echo htmlspecialchars($empresa['razao_social']); ?></div>
                                <div class="empresa-cnpj">CNPJ: <?php echo htmlspecialchars($empresa['cnpj']); ?></div>
                            </div>
                            <div style="text-align: right;">
                                <?php if (isset($empresa['regime_tributario']) && !empty($empresa['regime_tributario'])): ?>
                                    <span class="empresa-badge"><?php echo htmlspecialchars($empresa['regime_tributario']); ?></span>
                                <?php endif; ?>
                                
                                <?php if (isset($empresa['atividade']) && !empty($empresa['atividade'])): ?>
                                    <span class="empresa-badge"><?php echo htmlspecialchars($empresa['atividade']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="empresa-content">
                            <!-- Estatísticas de Processos -->
                            <div class="empresa-stats">
                                <div class="stat-item">
                                    <div class="stat-number stat-total"><?php echo $empresa['total_processos']; ?></div>
                                    <div class="stat-label">Total</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number stat-concluido"><?php echo $empresa['processos_concluidos']; ?></div>
                                    <div class="stat-label">Concluídos</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number stat-pendente"><?php echo $empresa['processos_pendentes']; ?></div>
                                    <div class="stat-label">Pendentes</div>
                                </div>
                            </div>

                            <!-- Barra de Progresso -->
                            <?php if ($empresa['total_processos'] > 0): ?>
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $empresa['progresso_processos']; ?>%;"></div>
                                </div>
                                <div class="progress-text">
                                    <?php echo $empresa['progresso_processos']; ?>% completo • 
                                    <?php echo $empresa['total_processos']; ?> processo(s)
                                </div>
                            </div>
                            <?php else: ?>
                            <div style="text-align: center; color: var(--gray); padding: 1rem; flex: 1; display: flex; align-items: center; justify-content: center;">
                                <div>
                                    <i class="fas fa-tasks"></i>
                                    <p>Nenhum processo associado</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Ações - SEMPRE no final do card -->
                        <div class="empresa-actions">
                            <a href="empresa-processos.php?id=<?php echo $empresa['id']; ?>" class="btn btn-small">
                                <i class="fas fa-tasks"></i> Processos
                            </a>
                            
                            <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                                <a href="gerenciar-processos.php?empresa_id=<?php echo $empresa['id']; ?>" class="btn btn-small btn-success">
                                    <i class="fas fa-cog"></i> Gerenciar
                                </a>
                            <?php endif; ?>
                            
                            <a href="documentacoes-empresa.php?id=<?php echo $empresa['id']; ?>" class="btn btn-small btn-secondary">
                                <i class="fas fa-file-alt"></i> Docs
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6b7280;">
                    <i class="fas fa-building" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p><?php echo empty($filtro_nome) ? 'Nenhuma empresa cadastrada' : 'Nenhuma empresa encontrada'; ?></p>
                    <?php if (!empty($filtro_nome)): ?>
                        <a href="gestao-empresas.php" class="btn" style="margin-top: 1rem;">
                            <i class="fas fa-arrow-left"></i> Ver Todas as Empresas
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="gestao-scripts.js"></script>
</body>
</html>