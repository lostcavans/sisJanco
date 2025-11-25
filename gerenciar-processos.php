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
$sql_processos_associados = "SELECT p.*, pe.id as associacao_id, pe.data_prevista, pe.observacoes, 
                             c.nome as categoria_nome
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
    
    if (empty($processo_id)) {
        $_SESSION['erro'] = 'Selecione um processo para associar.';
        header("Location: gerenciar-processos.php?empresa_id=" . $empresa_id);
        exit;
    }
    
    // VERIFICAÇÃO EXTRA SEGURA - sem transação primeiro
    $sql_check = "SELECT COUNT(*) as total FROM gestao_processo_empresas WHERE processo_id = ? AND empresa_id = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("ii", $processo_id, $empresa_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if ($result_check['total'] > 0) {
        $_SESSION['erro'] = 'Este processo já está associado à empresa. Atualize a página.';
        header("Location: gerenciar-processos.php?empresa_id=" . $empresa_id);
        exit;
    }
    
    // AGORA fazemos as inserções SEM transação complexa
    $error = null;
    
    // 1. Inserir associação principal
    $sql_associar = "INSERT INTO gestao_processo_empresas (processo_id, empresa_id, data_associacao) 
                     VALUES (?, ?, NOW())";
    $stmt = $conexao->prepare($sql_associar);
    $stmt->bind_param("ii", $processo_id, $empresa_id);
    
    if ($stmt->execute()) {
    $stmt->close();
    
    // COPIAR checklist predefinido de forma mais robusta
    $sql_copiar_checklist = "INSERT INTO gestao_processo_checklist 
                            (processo_id, empresa_id, titulo, descricao, ordem, created_at, updated_at)
                            SELECT ?, ?, titulo, descricao, ordem, NOW(), NOW()
                            FROM gestao_processo_predefinido_checklist 
                            WHERE processo_id = ? 
                            ORDER BY ordem";
    
    $stmt_copiar = $conexao->prepare($sql_copiar_checklist);
    $stmt_copiar->bind_param("iii", $processo_id, $empresa_id, $processo_id);
    
    if ($stmt_copiar->execute()) {
        $itens_copiados = $stmt_copiar->affected_rows;
        error_log("Checklist copiado com sucesso: $itens_copiados itens");
        
        // Se não há itens no checklist predefinido, criar um item padrão
        if ($itens_copiados === 0) {
            $sql_item_padrao = "INSERT INTO gestao_processo_checklist 
                               (processo_id, empresa_id, titulo, descricao, ordem, created_at, updated_at)
                               VALUES (?, ?, 'Item do Checklist', 'Descrição do item', 1, NOW(), NOW())";
            $stmt_padrao = $conexao->prepare($sql_item_padrao);
            $stmt_padrao->bind_param("ii", $processo_id, $empresa_id);
            $stmt_padrao->execute();
            $stmt_padrao->close();
        }
    } else {
        error_log("Erro ao copiar checklist: " . $stmt_copiar->error);
    }
    $stmt_copiar->close();
    
    $_SESSION['sucesso'] = 'Processo associado à empresa com sucesso!';
} else {
        $error = 'Erro ao associar processo: ' . $stmt->error;
        $stmt->close();
    }
    
    if ($error) {
        $_SESSION['erro'] = $error;
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

// Processar edição de processo associado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_processo'])) {
    $associacao_id = intval($_POST['associacao_id']);
    $data_prevista = $_POST['data_prevista'] ? $_POST['data_prevista'] : null;
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Buscar dados atuais da associação
    $sql_check = "SELECT * FROM gestao_processo_empresas WHERE id = ? AND empresa_id = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("ii", $associacao_id, $empresa_id);
    $stmt_check->execute();
    $associacao = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if (!$associacao) {
        $_SESSION['erro'] = 'Associação não encontrada.';
        header("Location: gerenciar-processos.php?empresa_id=" . $empresa_id);
        exit;
    }
    
    // Atualizar dados específicos da empresa para este processo
    $sql_update = "UPDATE gestao_processo_empresas 
                   SET data_prevista = ?, observacoes = ?, updated_at = NOW() 
                   WHERE id = ? AND empresa_id = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("ssii", $data_prevista, $observacoes, $associacao_id, $empresa_id);
    
    if ($stmt->execute()) {
        $_SESSION['sucesso'] = 'Processo atualizado com sucesso!';
    } else {
        $_SESSION['erro'] = 'Erro ao atualizar processo: ' . $stmt->error;
    }
    $stmt->close();
    
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
            <img src="uploads/logo-images/ANTONIO LOGO 2.png" alt="Descrição da imagem" style="width: 75px; height: 50px;">
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
                            <?php if ($processo['data_prevista'] || $processo['observacoes']): ?>
                            <div style="margin-top: 0.5rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                                <?php if ($processo['data_prevista']): ?>
                                    <div style="font-size: 0.9rem;">
                                        <strong><i class="fas fa-calendar"></i> Data Prevista:</strong> 
                                        <?php echo date('d/m/Y', strtotime($processo['data_prevista'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($processo['observacoes']): ?>
                                    <div style="font-size: 0.9rem; margin-top: 0.25rem;">
                                        <strong><i class="fas fa-sticky-note"></i> Observações:</strong> 
                                        <?php echo htmlspecialchars($processo['observacoes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
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
                                <button type="button" class="action-btn edit-btn" 
                                        onclick="abrirModalEditar(<?php echo $processo['associacao_id']; ?>, 
                                                                '<?php echo htmlspecialchars($processo['titulo']); ?>',
                                                                '<?php echo htmlspecialchars($processo['codigo']); ?>',
                                                                '<?php echo htmlspecialchars($processo['descricao']); ?>',
                                                                '<?php echo htmlspecialchars($processo['categoria_nome']); ?>',
                                                                '<?php echo $processo['prioridade']; ?>',
                                                                '<?php echo $processo['recorrente']; ?>',
                                                                '<?php echo $processo['data_prevista'] ?? ''; ?>',
                                                                '<?php echo htmlspecialchars($processo['observacoes'] ?? ''); ?>')"
                                        title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
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

        <!-- Modal para Editar Processo -->
        <div id="modalEditarProcesso" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Editar Processo para <?php echo htmlspecialchars($empresa['razao_social']); ?></h2>
                    <button class="close-btn" onclick="fecharModal('modalEditarProcesso')">&times;</button>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" id="editar_associacao_id" name="associacao_id">
                    
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Informações do Processo (Pré-definido)</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editar_titulo">Título do Processo</label>
                                <input type="text" id="editar_titulo" readonly 
                                       style="background-color: #f8f9fa; cursor: not-allowed;">
                            </div>
                            
                            <div class="form-group">
                                <label for="editar_codigo">Código</label>
                                <input type="text" id="editar_codigo" readonly
                                       style="background-color: #f8f9fa; cursor: not-allowed;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="editar_descricao">Descrição do Processo</label>
                            <textarea id="editar_descricao" readonly
                                      style="background-color: #f8f9fa; cursor: not-allowed;"
                                      rows="4"></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-cog"></i> Configurações (Pré-definidas)</h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="editar_categoria">Departamento</label>
                                <input type="text" id="editar_categoria" readonly
                                       style="background-color: #f8f9fa; cursor: not-allowed;">
                            </div>
                            
                            <div class="form-group">
                                <label for="editar_prioridade">Prioridade</label>
                                <input type="text" id="editar_prioridade" readonly
                                       style="background-color: #f8f9fa; cursor: not-allowed;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="editar_recorrente">Tipo de Processo</label>
                            <input type="text" id="editar_recorrente" readonly
                                   style="background-color: #f8f9fa; cursor: not-allowed;">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-building"></i> Personalização para esta Empresa</h3>
                        
                        <div class="form-group">
                            <label for="editar_data_prevista">Data Prevista</label>
                            <input type="date" id="editar_data_prevista" name="data_prevista"
                                   min="<?php echo date('Y-m-d'); ?>">
                            <small style="color: var(--gray);">Data específica para esta empresa</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="editar_observacoes">Observações para esta Empresa</label>
                            <textarea id="editar_observacoes" name="observacoes" 
                                    placeholder="Observações específicas para <?php echo htmlspecialchars($empresa['razao_social']); ?>..."
                                    rows="4"></textarea>
                            <small style="color: var(--gray);">Observações específicas para esta empresa</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEditarProcesso')">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" name="editar_processo" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Personalização
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Função para abrir modal de edição
        function abrirModalEditar(associacaoId, titulo, codigo, descricao, categoria, prioridade, recorrente, dataPrevista, observacoes) {
            // Preencher os dados no modal
            document.getElementById('editar_associacao_id').value = associacaoId;
            
            // Dados pré-definidos (somente leitura)
            document.getElementById('editar_titulo').value = titulo;
            document.getElementById('editar_codigo').value = codigo;
            document.getElementById('editar_descricao').value = descricao;
            document.getElementById('editar_categoria').value = categoria;
            document.getElementById('editar_prioridade').value = prioridade.charAt(0).toUpperCase() + prioridade.slice(1);
            document.getElementById('editar_recorrente').value = recorrente.charAt(0).toUpperCase() + recorrente.slice(1);
            
            // Dados personalizáveis (editáveis)
            document.getElementById('editar_data_prevista').value = dataPrevista;
            document.getElementById('editar_observacoes').value = observacoes;
            
            // Abrir o modal
            document.getElementById('modalEditarProcesso').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Função para fechar modal
        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }

        // Fechar modal com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModal('modalEditarProcesso');
            }
        });

        // Formatação das informações para exibição
        function formatarPrioridade(prioridade) {
            const formatos = {
                'baixa': '🟢 Baixa',
                'media': '🟡 Média', 
                'alta': '🔴 Alta',
                'urgente': '⚡ Urgente'
            };
            return formatos[prioridade] || prioridade;
        }

        function formatarRecorrente(recorrente) {
            const formatos = {
                'unico': '📄 Processo Único',
                'semanal': '📅 Processo Semanal',
                'mensal': '🗓️ Processo Mensal',
                'trimestral': '📊 Processo Trimestral'
            };
            return formatos[recorrente] || recorrente;
        }
    </script>
</body>
</html>