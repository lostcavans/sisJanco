<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];

// Verificar se o ID foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = 'Empresa não especificada.';
    header("Location: gestao-empresas.php");
    exit;
}

$empresa_id = intval($_GET['id']);

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

// Copiar checklist do processo PRÉ-DEFINIDO para a empresa USANDO INSERT IGNORE
$sql_checklist = "INSERT IGNORE INTO gestao_processo_checklist (processo_id, empresa_id, titulo, descricao, created_at, updated_at)
                  SELECT ?, ?, titulo, descricao, NOW(), NOW()
                  FROM gestao_processo_predefinido_checklist 
                  WHERE processo_id = ?";

$stmt_checklist = $conexao->prepare($sql_checklist);
$stmt_checklist->bind_param("iii", $processo_id, $empresa_id, $processo_id);

if (!$stmt_checklist->execute()) {
    // Apenas log o erro, mas não interrompa o processo principal
    error_log("Aviso: Erro ao copiar checklist para processo {$processo_id}: " . $stmt_checklist->error);
}
$stmt_checklist->close();

// Buscar processos da empresa com checklists
// Buscar processos da empresa com checklists
$sql_processos = "SELECT 
    p.id,
    p.codigo,
    p.titulo,
    p.descricao,
    p.status,
    p.prioridade,
    p.data_prevista,
    pc.id as checklist_id,
    pc.titulo as checklist_titulo,
    pc.descricao as checklist_descricao,
    pc.concluido,
    pc.data_conclusao,
    pc.observacao,
    pc.usuario_conclusao_id,
    u.nome_completo as usuario_conclusao_nome,
    c.nome as categoria_nome,
    resp.nome_completo as responsavel_nome
FROM gestao_processos p
INNER JOIN gestao_processo_empresas pe ON p.id = pe.processo_id
LEFT JOIN gestao_processo_checklist pc ON (p.id = pc.processo_id AND pc.empresa_id = ?)
LEFT JOIN gestao_usuarios u ON pc.usuario_conclusao_id = u.id
LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id
LEFT JOIN gestao_usuarios resp ON p.responsavel_id = resp.id
WHERE pe.empresa_id = ?
ORDER BY p.titulo, pc.id";

$stmt = $conexao->prepare($sql_processos);
$stmt->bind_param("ii", $empresa_id, $empresa_id);
$stmt->execute();
$processos_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Agrupar por processo
$processos_agrupados = [];
foreach ($processos_data as $item) {
    $processo_id = $item['id'];
    
    if (!isset($processos_agrupados[$processo_id])) {
        $processos_agrupados[$processo_id] = [
            'processo' => [
                'id' => $item['id'],
                'codigo' => $item['codigo'],
                'titulo' => $item['titulo'],
                'descricao' => $item['descricao'],
                'status' => $item['status'],
                'prioridade' => $item['prioridade'],
                'categoria_nome' => $item['categoria_nome'],
                'responsavel_nome' => $item['responsavel_nome'],
                'data_prevista' => $item['data_prevista']
            ],
            'checklist' => []
        ];
    }
    
    if ($item['checklist_id']) {
        $processos_agrupados[$processo_id]['checklist'][] = [
            'id' => $item['checklist_id'],
            'titulo' => $item['checklist_titulo'],
            'descricao' => $item['checklist_descricao'],
            'concluido' => $item['concluido'],
            'data_conclusao' => $item['data_conclusao'],
            'observacao' => $item['observacao'],
            'usuario_conclusao_nome' => $item['usuario_conclusao_nome']
        ];
    }
}

// Calcular estatísticas
$total_processos = count($processos_agrupados);
$processos_concluidos = 0;
$processos_pendentes = 0;
$processos_andamento = 0;

foreach ($processos_agrupados as $dados) {
    switch ($dados['processo']['status']) {
        case 'concluido':
            $processos_concluidos++;
            break;
        case 'em_andamento':
            $processos_andamento++;
            break;
        default:
            $processos_pendentes++;
    }
}

$progresso = $total_processos > 0 ? round(($processos_concluidos / $total_processos) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processos - <?php echo htmlspecialchars($empresa['razao_social']); ?></title>
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
        <div class="page-header fade-in">
            <div>
                <h1 class="page-title"><?php echo htmlspecialchars($empresa['razao_social']); ?></h1>
                <div class="page-subtitle">
                    <i class="fas fa-building"></i> CNPJ: <?php echo htmlspecialchars($empresa['cnpj']); ?> • 
                    <i class="fas fa-chart-pie"></i> <?php echo htmlspecialchars($empresa['regime_tributario']); ?>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="gestao-empresas.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                    <a href="gerenciar-processos.php?empresa_id=<?php echo $empresa_id; ?>" class="btn">
                        <i class="fas fa-cog"></i> Gerenciar Processos
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

        <!-- Estatísticas -->
        <div class="stats-grid fade-in">
            <div class="stat-card total">
                <div class="stat-number"><?php echo $total_processos; ?></div>
                <div class="stat-label">Total de Processos</div>
                <i class="fas fa-tasks" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card concluido">
                <div class="stat-number"><?php echo $processos_concluidos; ?></div>
                <div class="stat-label">Concluídos</div>
                <i class="fas fa-check-circle" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card pendente">
                <div class="stat-number"><?php echo $processos_andamento; ?></div>
                <div class="stat-label">Em Andamento</div>
                <i class="fas fa-sync-alt" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card atrasado">
                <div class="stat-number"><?php echo $processos_pendentes; ?></div>
                <div class="stat-label">Pendentes</div>
                <i class="fas fa-clock" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
        </div>

        <!-- Barra de Progresso -->
        <?php if ($total_processos > 0): ?>
        <div class="progress-section fade-in">
            <div class="progress-header">
                <div class="progress-title">Progresso Geral</div>
                <div class="progress-value"><?php echo $progresso; ?>%</div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo $progresso; ?>%;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.9rem; color: var(--gray);">
                <span><?php echo $processos_concluidos; ?> de <?php echo $total_processos; ?> processos concluídos</span>
                <span><?php echo (100 - $progresso); ?>% pendente</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista de Processos -->
        <?php if (count($processos_agrupados) > 0): ?>
            <?php foreach ($processos_agrupados as $processo_id => $dados): ?>
                <div class="processo-group fade-in">
                    <div class="group-header">
                        <div class="group-title">
                            <i class="fas fa-tasks"></i>
                            <?php echo htmlspecialchars($dados['processo']['titulo']); ?>
                            <small style="color: var(--gray); margin-left: 10px;">
                                <?php echo htmlspecialchars($dados['processo']['codigo']); ?>
                            </small>
                        </div>
                        <div class="status-badge status-<?php echo $dados['processo']['status']; ?>">
                            <?php if ($dados['processo']['status'] === 'pendente'): ?>
                                <i class="fas fa-clock"></i>
                            <?php elseif ($dados['processo']['status'] === 'em_andamento'): ?>
                                <i class="fas fa-sync-alt"></i>
                            <?php elseif ($dados['processo']['status'] === 'concluido'): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle"></i>
                            <?php endif; ?>
                            <?php echo ucfirst($dados['processo']['status']); ?>
                        </div>
                    </div>
                    
                    <div class="checklist-container">
                        <!-- Informações do Processo -->
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 1rem;">
                            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                                <div>
                                    <strong><i class="fas fa-layer-group"></i> Departamento:</strong>
                                    <?php echo htmlspecialchars($dados['processo']['categoria_nome']); ?>
                                </div>
                                <div>
                                    <strong><i class="fas fa-user"></i> Responsável:</strong>
                                    <?php echo htmlspecialchars($dados['processo']['responsavel_nome']); ?>
                                </div>
                                <div>
                                    <strong><i class="fas fa-flag"></i> Prioridade:</strong>
                                    <span class="priority-badge priority-<?php echo $dados['processo']['prioridade']; ?>">
                                        <?php echo ucfirst($dados['processo']['prioridade']); ?>
                                    </span>
                                </div>
                                <?php if ($dados['processo']['data_prevista']): ?>
                                <div>
                                    <strong><i class="fas fa-calendar"></i> Data Prevista:</strong>
                                    <?php echo date('d/m/Y', strtotime($dados['processo']['data_prevista'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Checklist -->
                        <?php if (count($dados['checklist']) > 0): ?>
                            <?php foreach ($dados['checklist'] as $item): ?>
                                <div class="checklist-item <?php echo $item['concluido'] ? 'concluido' : ''; ?>">
                                    <div class="checklist-checkbox">
                                        <?php if (temPermissaoGestao('auxiliar') || temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                                            <input type="checkbox" 
                                                   id="check_<?php echo $item['id']; ?>"
                                                   <?php echo $item['concluido'] ? 'checked' : ''; ?> 
                                                   onchange="marcarChecklist(<?php echo $item['id']; ?>, this.checked, <?php echo $empresa_id; ?>)">
                                        <?php else: ?>
                                            <input type="checkbox" 
                                                   <?php echo $item['concluido'] ? 'checked' : ''; ?> 
                                                   disabled>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="checklist-content">
                                        <label for="check_<?php echo $item['id']; ?>" class="checklist-titulo">
                                            <?php echo htmlspecialchars($item['titulo']); ?>
                                        </label>
                                        
                                        <?php if ($item['descricao']): ?>
                                            <div class="checklist-descricao">
                                                <?php echo htmlspecialchars($item['descricao']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Informações de conclusão -->
                                        <?php if ($item['concluido'] && $item['data_conclusao']): ?>
                                            <div class="checklist-info">
                                                <small>
                                                    <i class="fas fa-check-circle"></i>
                                                    Concluído em <?php echo date('d/m/Y H:i', strtotime($item['data_conclusao'])); ?>
                                                    <?php if ($item['usuario_conclusao_nome']): ?>
                                                        por <?php echo htmlspecialchars($item['usuario_conclusao_nome']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Observações -->
                                        <?php if ($item['observacao']): ?>
                                            <div class="checklist-observacoes">
                                                <i class="fas fa-comment"></i>
                                                <?php echo htmlspecialchars($item['observacao']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Botão para adicionar observações -->
                                    <?php if (temPermissaoGestao('auxiliar') || temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                                        <button type="button" class="btn btn-small" 
                                                onclick="abrirModalObservacoes(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['titulo']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--gray);">
                                <i class="fas fa-list-check" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>Nenhum checklist definido para este processo</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="form-section empty-state fade-in">
                <i class="fas fa-tasks"></i>
                <h3>Nenhum processo encontrado</h3>
                <p>Esta empresa ainda não possui processos associados.</p>
                <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                    <a href="gerenciar-processos.php?empresa_id=<?php echo $empresa_id; ?>" class="btn" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Associar Primeiro Processo
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal para Observações -->
    <div id="modalObservacoes" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Adicionar Observações</h2>
                <button class="close-btn" onclick="fecharModal('modalObservacoes')">&times;</button>
            </div>
            
            <form method="POST" action="salvar_observacoes.php">
                <input type="hidden" id="observacao_checklist_id" name="checklist_id">
                
                <div class="form-group">
                    <label for="observacao_titulo">Etapa:</label>
                    <div id="observacao_titulo" style="padding: 0.5rem; background: #f8f9fa; border-radius: 4px; font-weight: 600;"></div>
                </div>
                
                <div class="form-group">
                    <label for="observacao_texto">Observações:</label>
                    <textarea id="observacao_texto" name="observacao" 
                              placeholder="Digite suas observações sobre esta etapa..."
                              rows="4"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalObservacoes')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Observações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="gestao-scripts.js"></script>
    <script>
        window.usuarioId = <?php echo $usuario_id; ?>;
    </script>
</body>
</html>