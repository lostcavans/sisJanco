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

// CORREÇÃO: Aceitar tanto 'id' quanto 'empresa_id' como parâmetro
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $empresa_id = intval($_GET['id']);
} elseif (isset($_GET['empresa_id']) && !empty($_GET['empresa_id'])) {
    $empresa_id = intval($_GET['empresa_id']);
} else {
    $_SESSION['erro'] = 'Empresa não especificada.';
    header("Location: gestao-empresas.php");
    exit;
}

if ($empresa_id <= 0) {
    $_SESSION['erro'] = 'ID da empresa inválido.';
    header("Location: gestao-empresas.php");
    exit;
}

// Buscar dados da empresa
try {
    $sql_empresa = "SELECT * FROM empresas WHERE id = ?";
    $stmt = $conexao->prepare($sql_empresa);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta da empresa: ' . $conexao->error);
    }
    
    $stmt->bind_param("i", $empresa_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar consulta da empresa: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $empresa = $result->fetch_assoc();
    $stmt->close();
    
    if (!$empresa) {
        $_SESSION['erro'] = 'Empresa não encontrada no sistema.';
        header("Location: gestao-empresas.php");
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['erro'] = 'Erro ao carregar dados da empresa: ' . $e->getMessage();
    header("Location: gestao-empresas.php");
    exit;
}

// Buscar tipos de documentação disponíveis
$sql_tipos = "SELECT * FROM gestao_tipos_documentacao WHERE ativo = 1 ORDER BY nome";
$tipos_documentacao = $conexao->query($sql_tipos)->fetch_all(MYSQLI_ASSOC);

// Buscar documentações existentes da empresa
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

// Processar adição de nova documentação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_documentacao'])) {
    $tipo_documentacao_id = $_POST['tipo_documentacao_id'];
    $competencia = $_POST['competencia'];
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validar dados
    if (empty($tipo_documentacao_id) || empty($competencia)) {
        $_SESSION['erro'] = 'Tipo de documentação e competência são obrigatórios.';
        header("Location: gerenciar-documentacoes.php?id=" . $empresa_id);
        exit;
    }
    
    // Verificar se já existe documentação do mesmo tipo para a mesma competência
    $sql_check = "SELECT id FROM gestao_documentacoes_empresa 
                  WHERE empresa_id = ? AND tipo_documentacao_id = ? AND competencia = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("iis", $empresa_id, $tipo_documentacao_id, $competencia);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        $_SESSION['erro'] = 'Já existe uma documentação deste tipo para esta competência.';
        header("Location: gerenciar-documentacoes.php?id=" . $empresa_id);
        exit;
    }
    $stmt_check->close();
    
    // Inserir nova documentação
    $sql_insert = "INSERT INTO gestao_documentacoes_empresa 
                  (empresa_id, tipo_documentacao_id, competencia, observacoes, status, created_at, updated_at)
                  VALUES (?, ?, ?, ?, 'pendente', NOW(), NOW())";
    
    $stmt = $conexao->prepare($sql_insert);
    $stmt->bind_param("iiss", $empresa_id, $tipo_documentacao_id, $competencia, $observacoes);
    
    if ($stmt->execute()) {
        $documentacao_id = $conexao->insert_id;
        
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_documentacao 
                         (documentacao_id, usuario_id, acao, descricao)
                         VALUES (?, ?, 'criacao', 'Documentação criada')";
        
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $documentacao_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        $_SESSION['sucesso'] = 'Documentação adicionada com sucesso!';
    } else {
        $_SESSION['erro'] = 'Erro ao adicionar documentação: ' . $stmt->error;
    }
    
    $stmt->close();
    header("Location: gerenciar-documentacoes.php?id=" . $empresa_id);
    exit;
}

// Processar exclusão de documentação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_documentacao'])) {
    $documentacao_id = $_POST['documentacao_id'];
    
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
        
        // Excluir histórico primeiro
        $sql_delete_hist = "DELETE FROM gestao_historicos_documentacao WHERE documentacao_id = ?";
        $stmt_hist = $conexao->prepare($sql_delete_hist);
        $stmt_hist->bind_param("i", $documentacao_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        // Excluir documentação
        $sql_delete = "DELETE FROM gestao_documentacoes_empresa WHERE id = ? AND empresa_id = ?";
        $stmt = $conexao->prepare($sql_delete);
        $stmt->bind_param("ii", $documentacao_id, $empresa_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao excluir documentação: ' . $stmt->error);
        }
        $stmt->close();
        
        $conexao->commit();
        
        $_SESSION['sucesso'] = 'Documentação excluída com sucesso!';
        
    } catch (Exception $e) {
        $conexao->rollback();
        $_SESSION['erro'] = 'Erro ao excluir documentação: ' . $e->getMessage();
    }
    
    header("Location: gerenciar-documentacoes.php?id=" . $empresa_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Documentações - <?php echo htmlspecialchars($empresa['razao_social']); ?></title>
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
        transition: var(--transition);
    }

    .navbar-brand:hover {
        transform: translateY(-1px);
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
        transition: var(--transition);
        padding: 0.75rem 1.25rem;
        border-radius: var(--border-radius);
        position: relative;
        overflow: hidden;
    }

    .nav-link::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 2px;
        background: var(--primary);
        transition: var(--transition);
        transform: translateX(-50%);
    }

    .nav-link:hover, .nav-link.active {
        color: var(--primary);
        background: var(--primary-light);
    }

    .nav-link:hover::before, .nav-link.active::before {
        width: 80%;
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
        position: relative;
        overflow: hidden;
    }

    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: var(--transition);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .btn:hover::before {
        left: 100%;
    }

    .btn-secondary {
        background: linear-gradient(135deg, var(--gray) 0%, #4b5563 100%);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    }

    .btn-small {
        padding: 8px 16px;
        font-size: 0.8rem;
    }

    /* FORM SECTION - ESTILOS ESPECÍFICOS PARA GERENCIAR */
    .form-section {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .form-section:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-lg);
    }

    .form-section h2 {
        margin-bottom: 1.5rem;
        color: var(--dark);
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 1.5rem;
        align-items: end;
    }

    .form-group {
        margin-bottom: 0;
    }

    label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--dark);
    }

    select, input, textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--gray-light);
        border-radius: var(--border-radius);
        font-size: 1rem;
        font-family: inherit;
        transition: var(--transition);
        background: var(--white);
    }

    select:focus, input:focus, textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        transform: translateY(-1px);
    }

    /* DOCUMENTAÇÃO LIST - ESTILOS COMPARTILHADOS */
    .documentacao-list {
        padding: 0;
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
        display: flex;
        align-items: center;
        gap: 8px;
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

    .observacao-badge {
        background: var(--primary-light);
        color: var(--primary);
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 0.75rem;
        cursor: help;
        border: 1px solid rgba(67, 97, 238, 0.2);
        transition: var(--transition);
    }

    .observacao-badge:hover {
        background: var(--primary);
        color: var(--white);
        transform: scale(1.05);
    }

    .action-buttons {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }

    /* FORM ACTIONS */
    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }

    /* ALERTS - ESTILOS COMPARTILHADOS */
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

    /* EMPTY STATE - ESTILOS COMPARTILHADOS */
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

    /* ANIMAÇÕES - ESTILOS COMPARTILHADOS */
    .fade-in {
        animation: fadeIn 0.6s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* RESPONSIVIDADE - ESTILOS COMPARTILHADOS */
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
        
        .form-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .documentacao-item {
            grid-template-columns: 1fr;
            gap: 1rem;
            text-align: center;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .action-buttons {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
    }

    /* ESTILOS ESPECÍFICOS PARA MOBILE */
    @media (max-width: 480px) {
        .container {
            padding: 0 1rem;
        }
        
        .form-section {
            padding: 1.5rem;
        }
        
        .page-title {
            font-size: 2rem;
        }
        
        .documentacao-item {
            padding: 1rem;
        }
    }

    /* ANIMAÇÃO DE CARREGAMENTO PARA FORMULÁRIOS */
    .loading {
        opacity: 0.7;
        pointer-events: none;
        position: relative;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid var(--primary);
        border-top: 2px solid transparent;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
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
        <div class="page-header fade-in">
            <div>
                <h1 class="page-title">Gerenciar Documentações</h1>
                <div class="page-subtitle">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($empresa['razao_social']); ?> • 
                    CNPJ: <?php echo htmlspecialchars($empresa['cnpj']); ?>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="documentacoes-empresa.php?id=<?php echo $empresa_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
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

        <!-- FORMULÁRIO PARA ADICIONAR NOVA DOCUMENTAÇÃO -->
        <div class="form-section fade-in">
            <h2><i class="fas fa-plus"></i> Adicionar Nova Documentação</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="tipo_documentacao_id">Tipo de Documentação *</label>
                        <select id="tipo_documentacao_id" name="tipo_documentacao_id" required>
                            <option value="">Selecione um tipo...</option>
                            <?php foreach ($tipos_documentacao as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>">
                                    <?php echo htmlspecialchars($tipo['nome']); ?> 
                                    (<?php echo ucfirst($tipo['recorrencia']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="competencia">Competência *</label>
                        <input type="month" id="competencia" name="competencia" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="observacoes">Observações</label>
                        <input type="text" id="observacoes" name="observacoes" 
                               placeholder="Observações opcionais...">
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 1rem;">
                    <button type="submit" name="adicionar_documentacao" class="btn btn-success">
                        <i class="fas fa-plus"></i> Adicionar Documentação
                    </button>
                </div>
            </form>
        </div>

        <!-- LISTA DE DOCUMENTAÇÕES EXISTENTES -->
        <div class="form-section fade-in">
            <h2><i class="fas fa-list"></i> Documentações Existentes</h2>
            
            <?php if (count($documentacoes) > 0): ?>
                <div class="documentacao-list">
                    <?php foreach ($documentacoes as $doc): ?>
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
                                <strong><?php echo htmlspecialchars($doc['tipo_nome']); ?></strong>
                                <div style="font-size: 0.8rem; color: var(--gray); margin-top: 0.25rem;">
                                    <?php echo ucfirst($doc['recorrencia']); ?>
                                </div>
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
                            
                            <div class="action-buttons">
                                <?php if ($doc['observacoes']): ?>
                                    <span class="observacao-badge" title="<?php echo htmlspecialchars($doc['observacoes']); ?>">
                                        <i class="fas fa-sticky-note"></i>
                                    </span>
                                <?php endif; ?>
                                
                                <form method="POST" action="" style="display: inline;" 
                                      onsubmit="return confirm('Tem certeza que deseja excluir esta documentação?');">
                                    <input type="hidden" name="documentacao_id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" name="excluir_documentacao" class="btn btn-danger btn-small">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>Nenhuma documentação cadastrada</h3>
                    <p>Use o formulário acima para adicionar a primeira documentação.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Definir o mês atual como padrão no campo de competência
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            document.getElementById('competencia').value = `${year}-${month}`;
            
            // Animar elementos
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>