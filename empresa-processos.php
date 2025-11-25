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

// Buscar processos da empresa - QUERY CORRIGIDA
$sql_processos = "SELECT 
    p.id,
    p.codigo,
    p.titulo,
    p.descricao,
    p.status,
    p.prioridade,
    p.categoria_id,
    p.responsavel_id,
    p.data_inicio,
    p.recorrente,
    pe.data_prevista as empresa_data_prevista,
    pe.observacoes as empresa_observacoes,
    c.nome as categoria_nome,
    resp.nome_completo as responsavel_nome
FROM gestao_processos p
INNER JOIN gestao_processo_empresas pe ON p.id = pe.processo_id
LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id
LEFT JOIN gestao_usuarios resp ON p.responsavel_id = resp.id
WHERE pe.empresa_id = ?
ORDER BY p.titulo";

$stmt_processos = $conexao->prepare($sql_processos);
$stmt_processos->bind_param("i", $empresa_id);
$stmt_processos->execute();
$processos = $stmt_processos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_processos->close(); // Fechar apenas uma vez

// Calcular estatísticas CORRETAMENTE
$total_processos = count($processos);
$processos_concluidos = 0;
$processos_pendentes = 0;
$processos_andamento = 0;

foreach ($processos as $processo) {
    // Para cada processo, buscar informações de checklist
    $processo_id = $processo['id'];
    
    $sql_checklist_stats = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END) as concluidos
        FROM gestao_processo_checklist 
        WHERE processo_id = ? AND empresa_id = ?";
    
    $stmt_stats = $conexao->prepare($sql_checklist_stats);
    $stmt_stats->bind_param("ii", $processo_id, $empresa_id);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
    $stmt_stats->close();
    
    $total_checklists = $stats['total'] ?? 0;
    $checklists_concluidos = $stats['concluidos'] ?? 0;
    
    // Determinar status baseado no progresso dos checklists
    if ($total_checklists > 0) {
        $progresso = ($checklists_concluidos / $total_checklists) * 100;
        
        if ($progresso == 100) {
            $processos_concluidos++;
        } elseif ($progresso > 0 && $progresso < 100) {
            $processos_andamento++;
        } else {
            $processos_pendentes++;
        }
    } else {
        // Se não há checklists, considerar como pendente
        $processos_pendentes++;
    }
}

$progresso = $total_processos > 0 ? round(($processos_concluidos / $total_processos) * 100) : 0;

// DEBUG: Verificar estatísticas
error_log("=== ESTATÍSTICAS CALCULADAS ===");
error_log("Total: $total_processos");
error_log("Concluídos: $processos_concluidos");
error_log("Andamento: $processos_andamento");
error_log("Pendentes: $processos_pendentes");
error_log("===============================");

// Buscar checklists para cada processo - COM NOME DO USUÁRIO
$processos_com_checklists = [];
foreach ($processos as $processo) {
    $processo_id = $processo['id'];
    
    // Query CORRIGIDA - SEM COMENTÁRIOS DENTRO DA STRING SQL
    $sql_checklist = "SELECT 
        c.id,
        c.processo_id,
        c.empresa_id,
        c.titulo,
        c.descricao,
        c.concluido,
        c.data_conclusao,
        c.usuario_conclusao_id,
        c.observacao,
        c.ordem,
        c.created_at,
        c.updated_at,
        c.imagem_nome,
        c.imagem_tipo,
        c.imagem_tamanho,
        u.nome_completo as usuario_conclusao_nome
    FROM gestao_processo_checklist c
    LEFT JOIN gestao_usuarios u ON c.usuario_conclusao_id = u.id
    WHERE c.processo_id = $processo_id AND c.empresa_id = $empresa_id
    ORDER BY c.ordem, c.id";
    
    $result_checklist = $conexao->query($sql_checklist);
    
    if ($result_checklist) {
        $checklists = [];
        while ($row = $result_checklist->fetch_assoc()) {
            $checklists[] = $row;
        }
        
        error_log("Processo $processo_id - Checklists com imagem: " . count($checklists));
    } else {
        error_log("ERRO SQL Checklist: " . $conexao->error);
        $checklists = [];
    }
    
    $processos_com_checklists[] = [
        'processo' => $processo,
        'checklists' => $checklists
    ];
}

// Calcular estatísticas
$total_processos = count($processos_com_checklists);
$processos_concluidos = 0;
$processos_pendentes = 0;
$processos_andamento = 0;

foreach ($processos_com_checklists as $dados) {
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

// DEBUG: Verificar o que está sendo carregado
error_log("=== DEBUG EMPRESA-PROCESSOS ===");
error_log("Empresa ID: " . $empresa_id);
error_log("Total de processos: " . count($processos_com_checklists));

foreach ($processos_com_checklists as $index => $dados) {
    $processo = $dados['processo'];
    $checklists = $dados['checklists'];
    error_log("Processo {$processo['id']} - {$processo['titulo']}: " . count($checklists) . " checklists");
    
    foreach ($checklists as $checklist) {
        error_log("  Checklist: {$checklist['titulo']} (ID: {$checklist['id']})");
    }
}
error_log("=== FIM DEBUG ===");


// Verificar se há filtro aplicado
$filtro_status = $_GET['status'] ?? 'todos';

// Aplicar filtro aos processos se necessário
if ($filtro_status !== 'todos') {
    $processos_filtrados = [];
    
    foreach ($processos as $processo) {
        // Para cada processo, buscar informações de checklist para determinar status
        $processo_id = $processo['id'];
        
        $sql_checklist_stats = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END) as concluidos
            FROM gestao_processo_checklist 
            WHERE processo_id = ? AND empresa_id = ?";
        
        $stmt_filter = $conexao->prepare($sql_checklist_stats);
        $stmt_filter->bind_param("ii", $processo_id, $empresa_id);
        $stmt_filter->execute();
        $stats = $stmt_filter->get_result()->fetch_assoc();
        $stmt_filter->close();
        
        $total_checklists = $stats['total'] ?? 0;
        $checklists_concluidos = $stats['concluidos'] ?? 0;
        
        // Determinar status baseado no progresso
        $status_processo = 'pendente'; // padrão
        
        if ($total_checklists > 0) {
            $progresso_individual = ($checklists_concluidos / $total_checklists) * 100;
            
            if ($progresso_individual == 100) {
                $status_processo = 'concluido';
            } elseif ($progresso_individual > 0 && $progresso_individual < 100) {
                $status_processo = 'em_andamento';
            }
        }
        
        // Aplicar filtro
        if ($status_processo === $filtro_status) {
            $processos_filtrados[] = $processo;
        }
    }
    
    $processos = $processos_filtrados;
    
    // Recalcular estatísticas baseadas no filtro
    $total_processos = count($processos);
    $processos_concluidos = 0;
    $processos_pendentes = 0;
    $processos_andamento = 0;

    foreach ($processos as $processo) {
        $processo_id = $processo['id'];
        
        $sql_checklist_stats = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END) as concluidos
            FROM gestao_processo_checklist 
            WHERE processo_id = ? AND empresa_id = ?";
        
        $stmt_recalc = $conexao->prepare($sql_checklist_stats);
        $stmt_recalc->bind_param("ii", $processo_id, $empresa_id);
        $stmt_recalc->execute();
        $stats = $stmt_recalc->get_result()->fetch_assoc();
        $stmt_recalc->close();
        
        $total_checklists = $stats['total'] ?? 0;
        $checklists_concluidos = $stats['concluidos'] ?? 0;
        
        if ($total_checklists > 0) {
            $progresso = ($checklists_concluidos / $total_checklists) * 100;
            
            if ($progresso == 100) {
                $processos_concluidos++;
            } elseif ($progresso > 0 && $progresso < 100) {
                $processos_andamento++;
            } else {
                $processos_pendentes++;
            }
        } else {
            $processos_pendentes++;
        }
    }

    

    $progresso = $total_processos > 0 ? round(($processos_concluidos / $total_processos) * 100) : 0;
}

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
    <title>Processos - <?php echo htmlspecialchars($empresa['razao_social']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="gestao-processos/gestao-styles.css">
    <style>
        /* ADICIONE ISSO NO SEU gestao-styles.css */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    overflow: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from {opacity: 0; transform: translateY(-50px);}
    to {opacity: 1; transform: translateY(0);}
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    color: #333;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-btn:hover {
    background-color: #e9ecef;
    color: #495057;
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

        <!-- Estatísticas COM FILTROS -->
        <div class="stats-grid fade-in">
            <div class="stat-card total filter-card <?php echo $filtro_classes['todos']; ?>" onclick="aplicarFiltro('todos')">
                <div class="stat-number"><?php echo $total_processos; ?></div>
                <div class="stat-label">Total de Processos</div>
                <i class="fas fa-tasks" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card concluido filter-card <?php echo $filtro_classes['concluido']; ?>" onclick="aplicarFiltro('concluido')">
                <div class="stat-number"><?php echo $processos_concluidos; ?></div>
                <div class="stat-label">Concluídos</div>
                <i class="fas fa-check-circle" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card pendente filter-card <?php echo $filtro_classes['em_andamento']; ?>" onclick="aplicarFiltro('em_andamento')">
                <div class="stat-number"><?php echo $processos_andamento; ?></div>
                <div class="stat-label">Em Andamento</div>
                <i class="fas fa-sync-alt" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
            
            <div class="stat-card atrasado filter-card <?php echo $filtro_classes['pendente']; ?>" onclick="aplicarFiltro('pendente')">
                <div class="stat-number"><?php echo $processos_pendentes; ?></div>
                <div class="stat-label">Pendentes</div>
                <i class="fas fa-clock" style="font-size: 2rem; margin-top: 1rem; opacity: 0.7;"></i>
            </div>
        </div>

        <!-- Indicador de Filtro Ativo -->
        <?php if ($filtro_status !== 'todos'): ?>
        <div class="alert alert-info fade-in" style="margin-bottom: 1.5rem;">
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
            - Mostrando <?php echo count($processos_com_checklists); ?> processo(s)
            <a href="empresa-processos.php?id=<?php echo $empresa_id; ?>" class="btn btn-small" style="margin-left: 1rem;">
                <i class="fas fa-times"></i> Limpar Filtro
            </a>
        </div>
        <?php endif; ?>

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
        <?php if (count($processos_com_checklists) > 0): ?>
            <?php foreach ($processos_com_checklists as $dados): ?>
                <?php $processo = $dados['processo']; ?>
                <div class="processo-group fade-in">
                    <div class="group-header">
                        <div class="group-title">
                            <i class="fas fa-tasks"></i>
                            <?php echo htmlspecialchars($processo['titulo']); ?>
                            <small style="color: var(--gray); margin-left: 10px;">
                                <?php echo htmlspecialchars($processo['codigo']); ?>
                            </small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button type="button" class="btn btn-small" 
                                            onclick="abrirModalEditarProcessoEmpresa(<?php echo $empresa_id; ?>, <?php echo $processo['id']; ?>, 
                                                    '<?php echo htmlspecialchars($processo['titulo']); ?>',
                                                    '<?php echo $processo['empresa_data_prevista'] ?? ''; ?>',
                                                    `<?php echo addslashes($processo['empresa_observacoes'] ?? ''); ?>`)"
                                            title="Editar informações para esta empresa">
                                        <i class="fas fa-building"></i> Config. Empresa
                                    </button>
                                    
                                    <button type="button" class="btn btn-small btn-primary" 
                                            onclick="abrirModalGerenciarChecklist(<?php echo $empresa_id; ?>, <?php echo $processo['id']; ?>, 
                                                    '<?php echo htmlspecialchars($processo['titulo']); ?>')"
                                            title="Gerenciar checklist para esta empresa">
                                        <i class="fas fa-list-check"></i> Gerenciar Checklist
                                    </button>
                                </div>
                            <?php endif; ?>
                            <div class="status-badge status-<?php echo $processo['status']; ?>">
                                <?php if ($processo['status'] === 'pendente'): ?>
                                    <i class="fas fa-clock"></i>
                                <?php elseif ($processo['status'] === 'em_andamento'): ?>
                                    <i class="fas fa-sync-alt"></i>
                                <?php elseif ($processo['status'] === 'concluido'): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-triangle"></i>
                                <?php endif; ?>
                                <?php echo ucfirst($processo['status']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="checklist-container">
                        <!-- Informações do Processo -->
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 1rem;">
                            <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                                <div>
                                    <strong><i class="fas fa-layer-group"></i> Departamento:</strong>
                                    <?php echo htmlspecialchars($processo['categoria_nome']); ?>
                                </div>
                                <div>
                                    <strong><i class="fas fa-user"></i> Responsável:</strong>
                                    <?php echo htmlspecialchars($processo['responsavel_nome']); ?>
                                </div>
                                <div>
                                    <strong><i class="fas fa-flag"></i> Prioridade:</strong>
                                    <span class="priority-badge priority-<?php echo $processo['prioridade']; ?>">
                                        <?php echo ucfirst($processo['prioridade']); ?>
                                    </span>
                                </div>
                                <?php if ($processo['empresa_data_prevista']): ?>
                                <div>
                                    <strong><i class="fas fa-calendar"></i> Data Prevista:</strong>
                                    <?php echo date('d/m/Y', strtotime($processo['empresa_data_prevista'])); ?>
                                </div>
                                <?php if ($dados['tem_personalizado'] ?? false): ?>
                                    <span class="badge" style="background: #ff6b6b; color: white; margin-left: 10px;">
                                        <i class="fas fa-star"></i> Personalizado
                                    </span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Observações específicas da empresa -->
                            <?php if ($processo['empresa_observacoes']): ?>
                            <div style="margin-top: 1rem; padding: 0.75rem; background: white; border-radius: 4px; border-left: 4px solid var(--primary);">
                                <strong><i class="fas fa-sticky-note"></i> Observações para esta empresa:</strong>
                                <div style="margin-top: 0.5rem; color: var(--dark);">
                                    <?php echo nl2br(htmlspecialchars($processo['empresa_observacoes'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Checklist -->
                        <?php if (count($dados['checklists']) > 0): ?>
                            <?php foreach ($dados['checklists'] as $item): ?>
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


                                        <!-- Imagem do checklist -->
                                        <?php if (!empty($item['imagem_nome'])): ?>
                                            <div class="checklist-imagem" style="margin-top: 0.5rem;">
                                                <img src="uploads/checklist-images/<?php echo htmlspecialchars($item['imagem_nome']); ?>" 
                                                    alt="Imagem do checklist" 
                                                    style="max-width: 200px; max-height: 150px; border-radius: 4px; border: 1px solid #ddd; cursor: pointer;"
                                                    onclick="abrirModalImagem('<?php echo htmlspecialchars($item['imagem_nome']); ?>')">
                                                <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                                                    <i class="fas fa-image"></i> Clique para ampliar
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Informações de conclusão - VERSÃO CORRIGIDA -->
                                        <?php if ($item['concluido'] && $item['data_conclusao']): ?>
                                            <div class="checklist-info">
                                                <small>
                                                    <i class="fas fa-check-circle"></i>
                                                    Concluído em <?php echo date('d/m/Y H:i', strtotime($item['data_conclusao'])); ?>
                                                    <?php if (!empty($item['usuario_conclusao_nome'])): ?>
                                                        por <?php echo htmlspecialchars($item['usuario_conclusao_nome']); ?>
                                                    <?php elseif (!empty($item['usuario_conclusao_id'])): ?>
                                                        por Usuário ID: <?php echo $item['usuario_conclusao_id']; ?>
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
                                    
                                    <!-- Botão para editar item do checklist -->
                                    <?php if (temPermissaoGestao('auxiliar') || temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                                        <button type="button" class="btn btn-small" 
                                                onclick="abrirModalEditarChecklist(<?php echo $item['id']; ?>, 
                                                        '<?php echo htmlspecialchars($item['titulo']); ?>',
                                                        `<?php echo addslashes($item['descricao'] ?? ''); ?>`,
                                                        `<?php echo addslashes($item['observacao'] ?? ''); ?>`,
                                                        <?php echo $item['concluido'] ? '1' : '0'; ?>,
                                                        '<?php echo $item['data_conclusao'] ?? ''; ?>',
                                                        <?php echo $empresa_id; ?>,
                                                        '<?php echo $item['imagem_nome'] ?? ''; ?>')"  ← AQUI ESTÁ PASSANDO A IMAGEM
                                                title="Editar item do checklist">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--gray);">
                                <i class="fas fa-list-check" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>Nenhum checklist definido para este processo</p>
                                <?php if (temPermissaoGestao('admin') || temPermissaoGestao('analista')): ?>
                                    <button type="button" class="btn btn-small" 
                                            onclick="abrirModalGerenciarChecklist(<?php echo $empresa_id; ?>, <?php echo $processo['id']; ?>, 
                                                    '<?php echo htmlspecialchars($processo['titulo']); ?>')">
                                        <i class="fas fa-plus"></i> Criar Checklist
                                    </button>
                                <?php endif; ?>
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

    <!-- Modal para Editar Processo da Empresa -->
    <div id="modalEditarProcessoEmpresa" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Editar Processo para esta Empresa</h2>
                <button class="close-btn" onclick="fecharModal('modalEditarProcessoEmpresa')">&times;</button>
            </div>
            
            <form method="POST" action="atualizar_processo_empresa.php">
                <input type="hidden" id="editar_empresa_id" name="empresa_id">
                <input type="hidden" id="editar_processo_id" name="processo_id">
                
                <div class="form-group">
                    <label for="editar_processo_titulo">Processo:</label>
                    <div id="editar_processo_titulo" style="padding: 0.5rem; background: #f8f9fa; border-radius: 4px; font-weight: 600;"></div>
                </div>
                
                <div class="form-group">
                    <label for="editar_empresa_data_prevista">Data Prevista para esta Empresa</label>
                    <input type="date" id="editar_empresa_data_prevista" name="data_prevista">
                </div>
                
                <div class="form-group">
                    <label for="editar_empresa_observacoes">Observações para esta Empresa</label>
                    <textarea id="editar_empresa_observacoes" name="observacoes" 
                            placeholder="Observações específicas para esta empresa..."
                            rows="4"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEditarProcessoEmpresa')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Gerenciar Checklist -->
    <div id="modalGerenciarChecklist" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title">Gerenciar Checklist</h2>
                <button class="close-btn" onclick="fecharModal('modalGerenciarChecklist')">&times;</button>
            </div>
            
            <form method="POST" action="salvar_checklist_empresa.php">
                <input type="hidden" id="gerenciar_empresa_id" name="empresa_id">
                <input type="hidden" id="gerenciar_processo_id" name="processo_id">
                
                <div class="form-group">
                    <label>Processo:</label>
                    <div id="gerenciar_processo_titulo" style="padding: 0.5rem; background: #f8f9fa; border-radius: 4px; font-weight: 600;"></div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-list-check"></i> Itens do Checklist</h3>
                    <small style="color: var(--gray); margin-bottom: 1rem; display: block;">
                        Estes itens são específicos para esta empresa. Você pode adicionar, editar ou remover itens.
                    </small>
                    
                    <div id="checklist-container">
                        <!-- Itens serão adicionados dinamicamente aqui -->
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="adicionarItemChecklist()">
                        <i class="fas fa-plus"></i> Adicionar Item
                    </button>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalGerenciarChecklist')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Checklist
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Item do Checklist COM IMAGEM -->
    <div id="modalEditarChecklist" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">Editar Item do Checklist</h2>
                <button class="close-btn" onclick="fecharModal('modalEditarChecklist')">&times;</button>
            </div>
            
            <form method="POST" action="atualizar_item_checklist.php" enctype="multipart/form-data">
                <input type="hidden" id="editar_checklist_id" name="checklist_id">
                <input type="hidden" id="editar_empresa_id_checklist" name="empresa_id">
                <input type="hidden" id="editar_imagem_atual" name="imagem_atual">
                <input type="hidden" id="editar_remover_imagem" name="remover_imagem" value="0">
                
                <div class="form-group">
                    <label for="editar_checklist_titulo" class="required">Título do Item</label>
                    <input type="text" id="editar_checklist_titulo" name="titulo" required 
                        placeholder="Digite o título do item...">
                </div>
                
                <div class="form-group">
                    <label for="editar_checklist_descricao">Descrição</label>
                    <textarea id="editar_checklist_descricao" name="descricao" 
                            placeholder="Descreva este item do checklist..."
                            rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editar_checklist_observacao">Observações</label>
                    <textarea id="editar_checklist_observacao" name="observacao" 
                            placeholder="Observações sobre este item..."
                            rows="3"></textarea>
                    <small style="color: var(--gray);">Estas observações serão visíveis para todos os usuários.</small>
                </div>

                <!-- SEÇÃO DE IMAGEM MELHORADA -->
                <div class="form-section">
                    <h3><i class="fas fa-image"></i> Imagem do Item</h3>
                    
                    <div class="form-group">
                        <label for="editar_checklist_imagem">Upload de Imagem</label>
                        <input type="file" id="editar_checklist_imagem" name="imagem" 
                            accept="image/*" class="form-input">
                        <small style="color: var(--gray);">
                            Formatos: JPG, PNG, GIF, WEBP (Máx: 5MB)
                        </small>
                    </div>
                    
                    <!-- Preview da imagem atual MELHORADO -->
                    <div id="preview_imagem_container" style="display: none; margin-top: 1rem;">
                        <label>Imagem Atual do Item:</label>
                        <div style="border: 2px dashed #ddd; padding: 1rem; border-radius: 8px; text-align: center; background: #f8f9fa;">
                            <img id="preview_imagem" src="" alt="Imagem atual do item" 
                                style="max-width: 100%; max-height: 200px; border-radius: 4px; border: 1px solid #ccc;">
                            <div id="info_imagem" style="margin-top: 0.5rem; font-size: 0.9rem; color: #666;">
                                <i class="fas fa-info-circle"></i> 
                                <span id="nome_imagem"></span>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <button type="button" class="btn btn-danger btn-small" onclick="removerImagem()">
                                    <i class="fas fa-trash"></i> Remover Imagem
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editar_checklist_status">Status</label>
                        <select id="editar_checklist_status" name="concluido">
                            <option value="0">Pendente</option>
                            <option value="1">Concluído</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_checklist_data_conclusao">Data de Conclusão</label>
                        <input type="datetime-local" id="editar_checklist_data_conclusao" name="data_conclusao">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalEditarChecklist')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Template para item do checklist -->
    <template id="checklist-item-template">
        <div class="checklist-template-item" style="border: 1px solid #ddd; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">
            <div style="display: flex; gap: 1rem; align-items: start;">
                <div style="flex: 1;">
                    <div class="form-group">
                        <label class="required">Título do Item</label>
                        <input type="text" name="checklist_titulo[]" required 
                            placeholder="Digite o título do item..." class="form-input"
                            value="">
                    </div>
                    
                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="checklist_descricao[]" 
                                placeholder="Descreva este item do checklist..."
                                rows="2" class="form-input"></textarea>
                    </div>
                </div>
                
                <button type="button" class="btn btn-danger" onclick="removerItemChecklist(this)" 
                        style="align-self: start;" title="Remover item">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </template>

    <!-- Modal para Visualizar Imagem em Tela Cheia -->
    <div id="modalImagem" class="modal">
        <div class="modal-content" style="max-width: 90%; max-height: 90%; background: transparent; border: none;">
            <div class="modal-header" style="background: transparent; border: none; justify-content: flex-end;">
                <button class="close-btn" onclick="fecharModal('modalImagem')" style="background: rgba(0,0,0,0.5); color: white;">&times;</button>
            </div>
            <div style="text-align: center;">
                <img id="imagemGrande" src="" alt="Imagem em tela cheia" style="max-width: 100%; max-height: 80vh; border-radius: 8px;">
            </div>
        </div>
    </div>

    <script src="gestao-scripts.js"></script>
<script>
// ===== CONFIGURAÇÃO INICIAL =====
window.usuarioId = <?php echo $usuario_id; ?>;
window.checklistCounter = 0;

// ===== VERIFICAÇÃO DE CARREGAMENTO =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== INICIALIZAÇÃO DE MODAIS ===');
    
    // Verificar se modais existem
    const modais = ['modalEditarProcessoEmpresa', 'modalGerenciarChecklist', 'modalEditarChecklist', 'modalImagem'];
    modais.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            console.log('✓ Modal carregado:', modalId);
            modal.style.display = 'none';
        } else {
            console.error('✗ Modal não encontrado:', modalId);
        }
    });
    
    // Configurar event listeners para fechar modais
    configurarEventListenersModais();
});

// ===== FUNÇÕES BÁSICAS DE MODAL =====
function configurarEventListenersModais() {
    // Fechar modal clicando fora
    window.onclick = function(event) {
        const modais = document.querySelectorAll('.modal');
        modais.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
                console.log('Modal fechado clicando fora');
            }
        });
    }

    // Fechar modal com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modais = document.querySelectorAll('.modal');
            modais.forEach(modal => {
                if (modal.style.display === 'block') {
                    modal.style.display = 'none';
                    console.log('Modal fechado com ESC');
                }
            });
        }
    });
}

// Função universal para abrir modais
function abrirModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        console.log('Modal aberto:', modalId);
    } else {
        console.error('Modal não encontrado:', modalId);
    }
}

// Função universal para fechar modais
function fecharModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        console.log('Modal fechado:', modalId);
    }
}

// ===== MODAL: EDITAR PROCESSO EMPRESA =====
function abrirModalEditarProcessoEmpresa(empresaId, processoId, titulo, dataPrevista, observacoes) {
    console.log('Abrindo modal editar processo empresa:', {empresaId, processoId});
    
    // Preencher dados
    document.getElementById('editar_empresa_id').value = empresaId;
    document.getElementById('editar_processo_id').value = processoId;
    document.getElementById('editar_processo_titulo').textContent = titulo;
    document.getElementById('editar_empresa_data_prevista').value = dataPrevista;
    document.getElementById('editar_empresa_observacoes').value = observacoes;
    
    // Abrir modal
    abrirModal('modalEditarProcessoEmpresa');
}

// ===== MODAL: GERENCIAR CHECKLIST =====
function abrirModalGerenciarChecklist(empresaId, processoId, titulo) {
    console.log('Abrindo modal gerenciar checklist:', {empresaId, processoId});
    
    // Preencher dados básicos
    document.getElementById('gerenciar_empresa_id').value = empresaId;
    document.getElementById('gerenciar_processo_id').value = processoId;
    document.getElementById('gerenciar_processo_titulo').textContent = titulo;
    
    // Limpar e carregar checklists
    const container = document.getElementById('checklist-container');
    container.innerHTML = '';
    window.checklistCounter = 0;
    
    // Carregar checklists existentes
    carregarChecklistsExistente(empresaId, processoId);
    
    // Abrir modal
    abrirModal('modalGerenciarChecklist');
}

// ===== MODAL: EDITAR CHECKLIST =====
function abrirModalEditarChecklist(checklistId, titulo, descricao, observacao, concluido, dataConclusao, empresaId, imagemNome = null) {
    console.log('Abrindo modal editar checklist:', checklistId);
    
    // Preencher dados básicos
    document.getElementById('editar_checklist_id').value = checklistId;
    document.getElementById('editar_empresa_id_checklist').value = empresaId;
    document.getElementById('editar_checklist_titulo').value = titulo;
    document.getElementById('editar_checklist_descricao').value = descricao;
    document.getElementById('editar_checklist_observacao').value = observacao;
    document.getElementById('editar_checklist_status').value = concluido;
    document.getElementById('editar_remover_imagem').value = '0';
    
    // Configurar imagem
    if (imagemNome && imagemNome !== '') {
        document.getElementById('editar_imagem_atual').value = imagemNome;
        document.getElementById('preview_imagem').src = 'uploads/checklist-images/' + imagemNome;
        document.getElementById('preview_imagem_container').style.display = 'block';
        atualizarInfoImagem(imagemNome);
    } else {
        document.getElementById('editar_imagem_atual').value = '';
        document.getElementById('preview_imagem_container').style.display = 'none';
    }
    
    // Configurar data
    if (dataConclusao) {
        const data = new Date(dataConclusao);
        const formattedDate = data.toISOString().slice(0, 16);
        document.getElementById('editar_checklist_data_conclusao').value = formattedDate;
    } else {
        document.getElementById('editar_checklist_data_conclusao').value = '';
    }
    
    // Abrir modal
    abrirModal('modalEditarChecklist');
}

// ===== MODAL: VISUALIZAR IMAGEM =====
function abrirModalImagem(imagemNome) {
    console.log('Abrindo modal imagem:', imagemNome);
    document.getElementById('imagemGrande').src = 'uploads/checklist-images/' + imagemNome;
    abrirModal('modalImagem');
}

// ===== FUNÇÕES AUXILIARES =====
function carregarChecklistsExistente(empresaId, processoId) {
    console.log('Carregando checklists para:', {empresaId, processoId});
    
    // Simular carregamento por enquanto
    adicionarItemChecklist('Item de exemplo', 'Descrição exemplo');
}

function adicionarItemChecklist(titulo = '', descricao = '') {
    const template = document.getElementById('checklist-item-template');
    const clone = template.content.cloneNode(true);
    const container = document.getElementById('checklist-container');
    
    const newItem = clone.querySelector('.checklist-template-item');
    const tituloInput = newItem.querySelector('input[name="checklist_titulo[]"]');
    const descricaoTextarea = newItem.querySelector('textarea[name="checklist_descricao[]"]');
    
    tituloInput.value = titulo;
    descricaoTextarea.value = descricao;
    
    container.appendChild(newItem);
    window.checklistCounter++;
}

function removerItemChecklist(button) {
    const items = document.querySelectorAll('.checklist-template-item');
    if (items.length > 1) {
        button.closest('.checklist-template-item').remove();
        window.checklistCounter--;
    } else {
        alert('É necessário ter pelo menos um item no checklist.');
    }
}

function atualizarInfoImagem(nomeImagem) {
    const infoImagem = document.getElementById('info_imagem');
    const nomeSpan = document.getElementById('nome_imagem');
    
    if (nomeImagem) {
        nomeSpan.textContent = nomeImagem;
        infoImagem.style.display = 'block';
    } else {
        infoImagem.style.display = 'none';
    }
}

function removerImagem() {
    document.getElementById('editar_checklist_imagem').value = '';
    document.getElementById('editar_imagem_atual').value = '';
    document.getElementById('preview_imagem').src = '';
    document.getElementById('preview_imagem_container').style.display = 'none';
    document.getElementById('editar_remover_imagem').value = '1';
}

function aplicarFiltro(status) {
    const url = new URL(window.location.href);
    if (status === 'todos') {
        url.searchParams.delete('status');
    } else {
        url.searchParams.set('status', status);
    }
    window.location.href = url.toString();
}

// ===== TESTE RÁPIDO =====
// Adicione temporariamente para testar
function testarTodosModais() {
    console.log('=== TESTANDO TODOS OS MODAIS ===');
    
    // Testar modal editar processo
    abrirModalEditarProcessoEmpresa(1, 1, 'Processo Teste', '2024-12-31', 'Observações teste');
    
    // Aguardar e testar próximo modal
    setTimeout(() => {
        fecharModal('modalEditarProcessoEmpresa');
        abrirModalGerenciarChecklist(1, 1, 'Processo Teste');
    }, 2000);
}

// Descomente a linha abaixo para testar automaticamente
// document.addEventListener('DOMContentLoaded', testarTodosModais);
</script>
</body>
</html>