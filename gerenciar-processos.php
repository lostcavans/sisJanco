<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

// Apenas Admin e Analistas podem gerenciar processos
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];
if (!in_array($nivel_usuario, ['admin', 'analista'])) {
    $_SESSION['erro'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header("Location: gestao-empresas.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];

// Verificar empresa
if (isset($_GET['empresa_id']) && !empty($_GET['empresa_id'])) {
    $empresa_id = intval($_GET['empresa_id']);
} else {
    $_SESSION['erro'] = 'Empresa não especificada.';
    header("Location: gestao-empresas.php");
    exit;
}

// Buscar dados da empresa
$sql_empresa = "SELECT * FROM empresas WHERE id = ?";
$stmt = $conexao->prepare($sql_empresa);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empresa) {
    $_SESSION['erro'] = 'Empresa não encontrada.';
    header("Location: gestao-empresas.php");
    exit;
}

// Buscar processos pré-definidos disponíveis
$sql_processos = "SELECT p.*, c.nome as categoria_nome
                  FROM gestao_processos p
                  LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id
                  WHERE p.ativo = 1 
                  AND p.id NOT IN (
                      SELECT processo_id FROM gestao_processo_empresas WHERE empresa_id = ?
                  )
                  ORDER BY p.titulo";
$stmt = $conexao->prepare($sql_processos);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$processos_disponiveis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Buscar processos já associados à empresa
$sql_processos_associados = "SELECT p.*, pe.id as associacao_id, c.nome as categoria_nome
                             FROM gestao_processos p
                             INNER JOIN gestao_processo_empresas pe ON p.id = pe.processo_id
                             LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id
                             WHERE pe.empresa_id = ?
                             ORDER BY p.titulo";
$stmt = $conexao->prepare($sql_processos_associados);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$processos_associados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Processar associação de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['associar_processo'])) {
    $processo_id = intval($_POST['processo_id']);
    $data_prevista = $_POST['data_prevista'];
    
    if (empty($processo_id)) {
        $_SESSION['erro'] = 'Selecione um processo para associar.';
        header("Location: gerenciar-processos.php?empresa_id=" . $empresa_id);
        exit;
    }
    
    // Iniciar transação para garantir consistência
    $conexao->begin_transaction();
    
    try {
        // Verificar se já está associado (dupla verificação)
        $sql_check = "SELECT id FROM gestao_processo_empresas WHERE processo_id = ? AND empresa_id = ?";
        $stmt_check = $conexao->prepare($sql_check);
        $stmt_check->bind_param("ii", $processo_id, $empresa_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception('Este processo já está associado à empresa.');
        }
        $stmt_check->close();
        
        // Associar processo à empresa
        $sql_associar = "INSERT INTO gestao_processo_empresas (processo_id, empresa_id, data_associacao) 
                         VALUES (?, ?, NOW())";
        $stmt = $conexao->prepare($sql_associar);
        $stmt->bind_param("ii", $processo_id, $empresa_id);
        
        if (!$stmt->execute()) {
            // Se for erro de duplicidade, mesmo após a verificação
            if ($stmt->errno == 1062) { // Código de erro para duplicate entry
                throw new Exception('Este processo já está associado à empresa.');
            } else {
                throw new Exception('Erro ao associar processo: ' . $stmt->error);
            }
        }
        $stmt->close();
        
        // Copiar checklist do processo PRÉ-DEFINIDO para a empresa
        $sql_checklist = "INSERT INTO gestao_processo_checklist (processo_id, empresa_id, titulo, descricao, created_at, updated_at)
                        SELECT ?, ?, titulo, descricao, NOW(), NOW()
                        FROM gestao_processo_predefinido_checklist 
                        WHERE processo_id = ?";
        
        $stmt_checklist = $conexao->prepare($sql_checklist);
        $stmt_checklist->bind_param("iii", $processo_id, $empresa_id, $processo_id);
        
        if (!$stmt_checklist->execute()) {
            throw new Exception('Erro ao copiar checklist: ' . $stmt_checklist->error);
        }
        $stmt_checklist->close();
        
        $conexao->commit();
        $_SESSION['sucesso'] = 'Processo associado à empresa com sucesso!';
        
    } catch (Exception $e) {
        $conexao->rollback();
        $_SESSION['erro'] = $e->getMessage();
    }
    
    header("Location: gerenciar-processos.php?empresa_id=" . $empresa_id);
    exit;
}

// Processar remoção de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_processo'])) {
    $associacao_id = intval($_POST['associacao_id']);
    
    $conexao->begin_transaction();
    
    try {
        // Remover checklist da empresa
        $sql_remove_checklist = "DELETE FROM gestao_processo_checklist 
                                WHERE processo_id IN (
                                    SELECT processo_id FROM gestao_processo_empresas WHERE id = ?
                                ) AND empresa_id = ?";
        $stmt_checklist = $conexao->prepare($sql_remove_checklist);
        $stmt_checklist->bind_param("ii", $associacao_id, $empresa_id);
        $stmt_checklist->execute();
        $stmt_checklist->close();
        
        // Remover associação
        $sql_remove = "DELETE FROM gestao_processo_empresas WHERE id = ? AND empresa_id = ?";
        $stmt = $conexao->prepare($sql_remove);
        $stmt->bind_param("ii", $associacao_id, $empresa_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao remover processo: ' . $stmt->error);
        }
        $stmt->close();
        
        $conexao->commit();
        $_SESSION['sucesso'] = 'Processo removido da empresa com sucesso!';
        
    } catch (Exception $e) {
        $conexao->rollback();
        $_SESSION['erro'] = 'Erro ao remover processo: ' . $e->getMessage();
    }
    
    header("Location: gerenciar-processos.php?empresa_id=" . $empresa_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Processos - <?php echo htmlspecialchars($empresa['razao_social']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="gestao-processos/gestao-styles.css">
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
            <li><a href="gestao-empresas.php" class="nav-link active">Empresas</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header fade-in">
            <div>
                <h1 class="page-title">Gerenciar Processos</h1>
                <div class="page-subtitle">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($empresa['razao_social']); ?> • 
                    CNPJ: <?php echo htmlspecialchars($empresa['cnpj']); ?>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="empresa-processos.php?id=<?php echo $empresa_id; ?>" class="btn btn-secondary">
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

        <!-- Formulário para Associar Novo Processo -->
        <div class="form-section fade-in">
            <h2><i class="fas fa-plus"></i> Associar Novo Processo</h2>
            
            <?php if (count($processos_disponiveis) > 0): ?>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="processo_id" class="required">Processo Pré-definido</label>
                            <select id="processo_id" name="processo_id" required>
                                <option value="">Selecione um processo...</option>
                                <?php foreach ($processos_disponiveis as $processo): ?>
                                    <option value="<?php echo $processo['id']; ?>">
                                        <?php echo htmlspecialchars($processo['titulo']); ?>
                                        (<?php echo htmlspecialchars($processo['categoria_nome']); ?> • 
                                        <?php echo ucfirst($processo['prioridade']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_prevista">Data Prevista</label>
                            <input type="date" id="data_prevista" name="data_prevista"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="associar_processo" class="btn btn-success">
                                <i class="fas fa-link"></i> Associar Processo
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                    <h3>Todos os processos já estão associados</h3>
                    <p>Não há processos pré-definidos disponíveis para associar a esta empresa.</p>
                    <a href="processos-gestao.php" class="btn" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Criar Novo Processo
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Processos Associados -->
        <div class="form-section fade-in">
            <h2><i class="fas fa-list"></i> Processos Associados</h2>
            
            <?php if (count($processos_associados) > 0): ?>
                <div class="processo-list">
                    <?php foreach ($processos_associados as $processo): ?>
                        <div class="checklist-item">
                            <div class="checklist-content">
                                <div class="checklist-titulo">
                                    <?php echo htmlspecialchars($processo['titulo']); ?>
                                    <small style="color: var(--gray); margin-left: 10px;">
                                        <?php echo htmlspecialchars($processo['codigo']); ?>
                                    </small>
                                </div>
                                
                                <div class="checklist-descricao">
                                    <?php echo htmlspecialchars($processo['descricao']); ?>
                                </div>
                                
                                <div style="display: flex; gap: 1rem; margin-top: 0.5rem; flex-wrap: wrap;">
                                    <span class="empresa-badge">
                                        <i class="fas fa-layer-group"></i>
                                        <?php echo htmlspecialchars($processo['categoria_nome']); ?>
                                    </span>
                                    <span class="empresa-badge">
                                        <i class="fas fa-flag"></i>
                                        <?php echo ucfirst($processo['prioridade']); ?>
                                    </span>
                                    <span class="empresa-badge">
                                        <i class="fas fa-sync-alt"></i>
                                        <?php echo ucfirst($processo['recorrente']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="actions">
                                <form method="POST" action="" style="display: inline;"
                                      onsubmit="return confirm('Tem certeza que deseja remover este processo da empresa?');">
                                    <input type="hidden" name="associacao_id" value="<?php echo $processo['associacao_id']; ?>">
                                    <button type="submit" name="remover_processo" class="action-btn delete-btn" title="Remover">
                                        <i class="fas fa-unlink"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>Nenhum processo associado</h3>
                    <p>Use o formulário acima para associar o primeiro processo a esta empresa.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="gestao-scripts.js"></script>
</body>
</html>