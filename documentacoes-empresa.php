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

// Verificar se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = 'Empresa não especificada.';
    header("Location: documentacoes-empresas.php");
    exit;
}

$empresa_id = intval($_GET['id']);

if ($empresa_id <= 0) {
    $_SESSION['erro'] = 'ID da empresa inválido.';
    header("Location: documentacoes-empresas.php");
    exit;
}

// CORREÇÃO: Buscar dados da empresa na tabela CORRETA (empresas, não gestao_empresas)
$sql_empresa = "SELECT * FROM empresas WHERE id = ?";
$stmt = $conexao->prepare($sql_empresa);

if (!$stmt) {
    $_SESSION['erro'] = 'Erro no servidor ao buscar empresa.';
    header("Location: documentacoes-empresas.php");
    exit;
}

$stmt->bind_param("i", $empresa_id);

if (!$stmt->execute()) {
    $_SESSION['erro'] = 'Erro ao buscar dados da empresa.';
    header("Location: documentacoes-empresas.php");
    exit;
}

$result = $stmt->get_result();
$empresa = $result->fetch_assoc();
$stmt->close();

if (!$empresa) {
    $_SESSION['erro'] = 'Empresa não encontrada no sistema.';
    header("Location: documentacoes-empresas.php");
    exit;
}

// Buscar documentações da empresa
$sql_documentacoes = "SELECT de.*, td.nome as tipo_nome, td.recorrencia, td.prazo_dias,
                             u.nome_completo as usuario_recebimento_nome
                      FROM gestao_documentacoes_empresa de
                      LEFT JOIN gestao_tipos_documentacao td ON de.tipo_documentacao_id = td.id
                      LEFT JOIN gestao_usuarios u ON de.usuario_recebimento_id = u.id
                      WHERE de.empresa_id = ?
                      ORDER BY td.nome, de.competencia DESC";

try {
    $stmt = $conexao->prepare($sql_documentacoes);
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $documentacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $documentacoes = [];
}

// Calcular estatísticas
$total_documentos = count($documentacoes);
$documentos_recebidos = 0;
$documentos_pendentes = 0;
$documentos_atrasados = 0;

foreach ($documentacoes as $doc) {
    switch ($doc['status']) {
        case 'recebido':
            $documentos_recebidos++;
            break;
        case 'pendente':
            $documentos_pendentes++;
            break;
        case 'atrasado':
            $documentos_atrasados++;
            break;
    }
}

$progresso = $total_documentos > 0 ? round(($documentos_recebidos / $total_documentos) * 100) : 0;

// Agrupar documentações por tipo
$documentacoes_agrupadas = [];
foreach ($documentacoes as $doc) {
    if (!isset($documentacoes_agrupadas[$doc['tipo_nome']])) {
        $documentacoes_agrupadas[$doc['tipo_nome']] = [];
    }
    $documentacoes_agrupadas[$doc['tipo_nome']][] = $doc;
}

// Processar marcação como recebido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_recebido'])) {
    $documentacao_id = $_POST['documentacao_id'];
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    $conexao->begin_transaction();
    
    try {
        // Verificar se a documentação existe e pertence à empresa
        $sql_check = "SELECT id FROM gestao_documentacoes_empresa WHERE id = ? AND empresa_id = ?";
        $stmt_check = $conexao->prepare($sql_check);
        $stmt_check->bind_param("ii", $documentacao_id, $empresa_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows === 0) {
            throw new Exception('Documentação não encontrada ou não pertence a esta empresa.');
        }
        $stmt_check->close();
        
        // Atualizar documentação
        $sql_update = "UPDATE gestao_documentacoes_empresa 
                      SET status = 'recebido', 
                          data_recebimento = NOW(),
                          usuario_recebimento_id = ?,
                          observacoes = ?,
                          updated_at = NOW()
                      WHERE id = ? AND empresa_id = ?";
        
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("isii", $usuario_id, $observacoes, $documentacao_id, $empresa_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar documentação: ' . $stmt->error);
        }
        $stmt->close();
        
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_documentacao 
                         (documentacao_id, usuario_id, acao, descricao)
                         VALUES (?, ?, 'recebimento', 'Documentação marcada como recebida')";
        
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $documentacao_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        $conexao->commit();
        
        $_SESSION['sucesso'] = 'Documentação marcada como recebida com sucesso!';
        header("Location: documentacoes-empresa.php?id=" . $empresa_id);
        exit;
        
    } catch (Exception $e) {
        $conexao->rollback();
        $_SESSION['erro'] = 'Erro ao marcar documentação como recebida: ' . $e->getMessage();
        header("Location: documentacoes-empresa.php?id=" . $empresa_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentações - <?php echo htmlspecialchars($empresa['razao_social']); ?></title>
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
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
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
            align-items: flex-start;
            margin-bottom: 2.5rem;
            gap: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1.2;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 1.1rem;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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
            box-shadow: var(--shadow);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--gray) 0%, #4b5563 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 0.8rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--white) 0%, var(--light) 100%);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-card.total::before { background: var(--primary); }
        .stat-card.recebido::before { background: var(--success); }
        .stat-card.pendente::before { background: var(--warning); }
        .stat-card.atrasado::before { background: var(--danger); }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-card.total .stat-number { color: var(--primary); }
        .stat-card.recebido .stat-number { color: var(--success); }
        .stat-card.pendente .stat-number { color: var(--warning); }
        .stat-card.atrasado .stat-number { color: var(--danger); }

        .stat-label {
            color: var(--gray);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-section {
            background: linear-gradient(135deg, var(--white) 0%, var(--light) 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .progress-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .progress-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .progress-bar-container {
            background: var(--gray-light);
            border-radius: 20px;
            height: 12px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--success), #34d399);
            height: 100%;
            border-radius: 20px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .documentacao-group {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: var(--transition);
        }

        .documentacao-group:hover {
            box-shadow: var(--shadow-lg);
        }

        .group-header {
            background: linear-gradient(135deg, var(--primary-light) 0%, #e0e7ff 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .group-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recorrencia-badge {
            background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .documentacao-list {
            padding: 1rem;
        }

        .documentacao-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 1.5rem;
            padding: 1.25rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            margin-bottom: 0.75rem;
            align-items: center;
            transition: var(--transition);
            background: var(--white);
        }

        .documentacao-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
            transform: translateX(5px);
        }

        .competencia {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pendente { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .status-recebido { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #34d399;
        }

        .status-atrasado { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #f87171;
        }

        .recebimento-info {
            font-size: 0.85rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .observacao-badge {
            background: var(--primary-light);
            color: var(--primary);
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            cursor: help;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow-xl);
            position: relative;
            animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            background: var(--gray-light);
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            transition: var(--transition);
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-left-color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-left-color: #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .nav-link {
                text-align: center;
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .documentacao-item {
                grid-template-columns: 1fr;
                gap: 1rem;
                text-align: center;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .modal-content {
                margin: 1rem;
                padding: 1.5rem;
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
        <div class="page-header fade-in">
            <div>
                <h1 class="page-title"><?php echo htmlspecialchars($empresa['razao_social']); ?></h1>
                <div class="page-subtitle">
                    <i class="fas fa-building"></i> CNPJ: <?php echo htmlspecialchars($empresa['cnpj']); ?> • 
                    <i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars($empresa['regime_tributario']); ?>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="documentacoes-empresas.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <?php if (temPermissaoGestao('admin' and 'analista')): ?>
                    <a href="gerenciar-documentacoes.php?empresa_id=<?php echo $empresa_id; ?>" class="btn">
                        <i class="fas fa-edit"></i> Gerenciar
                    </a>
                <?php endif; ?>
            </div>
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

        <!-- ESTATÍSTICAS -->
        <div class="stats-grid fade-in">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $total_documentos; ?></div>
                <div class="stat-label">Total de Documentos</div>
                <i class="fas fa-file-alt" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card recebido">
                <div class="stat-number"><?php echo $documentos_recebidos; ?></div>
                <div class="stat-label">Recebidos</div>
                <i class="fas fa-check-circle" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card pendente">
                <div class="stat-number"><?php echo $documentos_pendentes; ?></div>
                <div class="stat-label">Pendentes</div>
                <i class="fas fa-clock" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card atrasado">
                <div class="stat-number"><?php echo $documentos_atrasados; ?></div>
                <div class="stat-label">Atrasados</div>
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
        </div>

        <!-- BARRA DE PROGRESSO -->
        <?php if ($total_documentos > 0): ?>
        <div class="progress-section fade-in">
            <div class="progress-header">
                <div class="progress-title">Progresso Geral</div>
                <div class="progress-value"><?php echo $progresso; ?>%</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo $progresso; ?>%;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.9rem; color: var(--gray);">
                <span><?php echo $documentos_recebidos; ?> de <?php echo $total_documentos; ?> documentos recebidos</span>
                <span><?php echo (100 - $progresso); ?>% pendente</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- LISTA DE DOCUMENTAÇÕES AGRUPADAS POR TIPO -->
        <?php if (count($documentacoes_agrupadas) > 0): ?>
            <?php foreach ($documentacoes_agrupadas as $tipo_nome => $docs): ?>
                <div class="documentacao-group fade-in">
                    <div class="group-header">
                        <div class="group-title">
                            <i class="fas fa-folder"></i>
                            <?php echo htmlspecialchars($tipo_nome); ?>
                        </div>
                        <div class="recorrencia-badge">
                            <i class="fas fa-sync-alt"></i>
                            <?php echo ucfirst($docs[0]['recorrencia']); ?>
                        </div>
                    </div>
                    
                    <div class="documentacao-list">
                        <?php foreach ($docs as $doc): ?>
                            <div class="documentacao-item">
                                <div class="competencia">
                                    <i class="fas fa-calendar"></i>
                                    <?php 
                                    if ($doc['recorrencia'] === 'mensal') {
                                        echo date('m/Y', strtotime($doc['competencia']));
                                    } else {
                                        echo date('d/m/Y', strtotime($doc['competencia']));
                                    }
                                    ?>
                                </div>
                                
                                <div>
                                    <span class="status-badge status-<?php echo $doc['status']; ?>">
                                        <?php if ($doc['status'] === 'pendente'): ?>
                                            <i class="fas fa-clock"></i>
                                        <?php elseif ($doc['status'] === 'recebido'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            <i class="fas fa-exclamation-triangle"></i>
                                        <?php endif; ?>
                                        <?php echo ucfirst($doc['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="recebimento-info">
                                    <?php if ($doc['status'] === 'recebido' && $doc['data_recebimento']): ?>
                                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                        Recebido em <?php echo date('d/m/Y', strtotime($doc['data_recebimento'])); ?>
                                        <?php if ($doc['usuario_recebimento_nome']): ?>
                                            <br><small>por <?php echo htmlspecialchars($doc['usuario_recebimento_nome']); ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($doc['status'] === 'atrasado'): ?>
                                        <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                                        Documentação atrasada
                                    <?php else: ?>
                                        <i class="fas fa-clock" style="color: var(--warning);"></i>
                                        Aguardando recebimento
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <?php if ($doc['observacoes']): ?>
                                        <span class="observacao-badge" title="<?php echo htmlspecialchars($doc['observacoes']); ?>">
                                            <i class="fas fa-sticky-note"></i> Observações
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($doc['status'] !== 'recebido'): ?>
                                        <button class="btn btn-success btn-small" 
                                                onclick="abrirModalRecebimento(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($tipo_nome); ?> - <?php echo date('m/Y', strtotime($doc['competencia'])); ?>')">
                                            <i class="fas fa-check"></i> Recebido
                                        </button>
                                    <?php else: ?>
                                        <span style="color: var(--success); font-weight: 600;">
                                            <i class="fas fa-check-double"></i> Concluído
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card empty-state fade-in">
                <i class="fas fa-file-alt"></i>
                <h3>Nenhuma documentação encontrada</h3>
                <p>Esta empresa ainda não possui documentações cadastradas.</p>
                <?php if (temPermissaoGestao('admin' and 'analista')): ?>
                    <a href="gerenciar-documentacoes.php?empresa_id=<?php echo $empresa_id; ?>" class="btn" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Cadastrar Primeira Documentação
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- MODAL PARA MARCAR COMO RECEBIDO -->
    <div id="modalRecebimento" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirmar Recebimento</h2>
                <button class="close-btn" onclick="fecharModalRecebimento()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="marcar_recebido" value="1">
                                <input type="hidden" id="documentacao_id" name="documentacao_id">
                
                <div class="form-group">
                    <label for="observacoes">Observações (opcional)</label>
                    <textarea id="observacoes" name="observacoes" class="observacao-input" 
                              placeholder="Adicione observações sobre o recebimento desta documentação..."></textarea>
                </div>
                
                <div id="documentacao-info" style="background: var(--primary-light); padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem;">
                    <strong>Documentação:</strong> <span id="documentacao-descricao"></span>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalRecebimento()">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirmar Recebimento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalRecebimento(documentacaoId, documentacaoDescricao) {
            document.getElementById('documentacao_id').value = documentacaoId;
            document.getElementById('documentacao-descricao').textContent = documentacaoDescricao;
            document.getElementById('modalRecebimento').style.display = 'flex';
            
            // Focar no textarea
            setTimeout(() => {
                document.getElementById('observacoes').focus();
            }, 300);
        }
        
        function fecharModalRecebimento() {
            document.getElementById('modalRecebimento').style.display = 'none';
            document.getElementById('observacoes').value = '';
        }
        
        // Fechar modal ao clicar fora ou pressionar ESC
        document.getElementById('modalRecebimento').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalRecebimento();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharModalRecebimento();
            }
        });

        // Animações de entrada para os elementos
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>