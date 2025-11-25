<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = 'Processo não encontrado.';
    header("Location: dashboard-gestao.php");
    exit;
}

$processo_id = $_GET['id'];

// Buscar dados do processo
$sql = "SELECT p.*, u.nome_completo as responsavel_nome, c.nome as categoria_nome
        FROM gestao_processos p 
        LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
        LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id 
        WHERE p.id = ? AND p.ativo = 1";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$processo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$processo) {
    $_SESSION['erro'] = 'Processo não encontrado.';
    header("Location: dashboard-gestao.php");
    exit;
}

// Verificar se o usuário tem permissão para acessar este checklist
if (!temPermissaoGestao('analista') && $processo['responsavel_id'] != $usuario_id) {
    $_SESSION['erro'] = 'Você não tem permissão para acessar este checklist.';
    header("Location: dashboard-gestao.php");
    exit;
}

// Buscar empresas e seus checklists (COMPATÍVEL COM EMPRESA-PROCESSOS)
$sql_checklist = "SELECT 
    e.*, 
    pc.id as checklist_id,
    pc.concluido, 
    pc.data_conclusao, 
    pc.observacao,
    pc.imagem_nome,
    uc.nome_completo as usuario_conclusao_nome,
    -- Calcular progresso por empresa
    (SELECT COUNT(*) FROM gestao_processo_checklist pc2 WHERE pc2.empresa_id = e.id AND pc2.processo_id = ?) as total_checklists_empresa,
    (SELECT COUNT(*) FROM gestao_processo_checklist pc2 WHERE pc2.empresa_id = e.id AND pc2.processo_id = ? AND pc2.concluido = 1) as checklists_concluidos_empresa
FROM gestao_processo_empresas pe 
LEFT JOIN empresas e ON pe.empresa_id = e.id 
LEFT JOIN gestao_processo_checklist pc ON pc.processo_id = pe.processo_id AND pc.empresa_id = pe.empresa_id
LEFT JOIN gestao_usuarios uc ON pc.usuario_conclusao_id = uc.id
WHERE pe.processo_id = ? 
GROUP BY e.id
ORDER BY e.razao_social";

$stmt = $conexao->prepare($sql_checklist);
$stmt->bind_param("iii", $processo_id, $processo_id, $processo_id);
$stmt->execute();
$checklist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular progresso geral (COMPATÍVEL COM EMPRESA-PROCESSOS)
$sql_progresso_geral = "SELECT 
    COUNT(*) as total_checklists,
    SUM(CASE WHEN concluido = 1 THEN 1 ELSE 0 END) as checklists_concluidos
    FROM gestao_processo_checklist 
    WHERE processo_id = ?";

$stmt_progresso = $conexao->prepare($sql_progresso_geral);
$stmt_progresso->bind_param("i", $processo_id);
$stmt_progresso->execute();
$progresso_geral = $stmt_progresso->get_result()->fetch_assoc();
$stmt_progresso->close();

$total_checklists = $progresso_geral['total_checklists'] ?? 0;
$checklists_concluidos = $progresso_geral['checklists_concluidos'] ?? 0;
$progresso = $total_checklists > 0 ? round(($checklists_concluidos / $total_checklists) * 100) : 0;



// Calcular progresso geral usando função padronizada
$progresso = calcularProgressoProcesso($processo_id);
$status_processo = calcularStatusProcesso($processo_id);

// Processar atualização do checklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_checklist'])) {
    $conexao->begin_transaction();
    
    try {
        foreach ($_POST['empresas'] as $empresa_id => $dados) {
            $concluido = isset($dados['concluido']) ? 1 : 0;
            $observacao = trim($dados['observacao'] ?? '');
            
            if ($concluido) {
                // Verificar se já existe registro
                $sql_check = "SELECT id FROM gestao_processo_checklist WHERE processo_id = ? AND empresa_id = ?";
                $stmt_check = $conexao->prepare($sql_check);
                $stmt_check->bind_param("ii", $processo_id, $empresa_id);
                $stmt_check->execute();
                $exists = $stmt_check->get_result()->num_rows > 0;
                $stmt_check->close();
                
                if ($exists) {
                    $sql_update = "UPDATE gestao_processo_checklist 
                                  SET concluido = ?, observacao = ?, usuario_conclusao_id = ?, 
                                      data_conclusao = NOW(), updated_at = NOW()
                                  WHERE processo_id = ? AND empresa_id = ?";
                    $stmt = $conexao->prepare($sql_update);
                    $stmt->bind_param("isiii", $concluido, $observacao, $usuario_id, $processo_id, $empresa_id);
                } else {
                    $sql_insert = "INSERT INTO gestao_processo_checklist 
                                  (processo_id, empresa_id, concluido, observacao, usuario_conclusao_id, data_conclusao)
                                  VALUES (?, ?, ?, ?, ?, NOW())";
                    $stmt = $conexao->prepare($sql_insert);
                    $stmt->bind_param("iiisi", $processo_id, $empresa_id, $concluido, $observacao, $usuario_id);
                }
            } else {
                // Marcar como não concluído
                $sql_update = "UPDATE gestao_processo_checklist 
                              SET concluido = 0, observacao = ?, usuario_conclusao_id = NULL, data_conclusao = NULL
                              WHERE processo_id = ? AND empresa_id = ?";
                $stmt = $conexao->prepare($sql_update);
                $stmt->bind_param("sii", $observacao, $processo_id, $empresa_id);
            }
            
            $stmt->execute();
            $stmt->close();
        }
        
        // Atualizar progresso no processo
        $novo_progresso = $total_empresas > 0 ? round(($empresas_concluidas / $total_empresas) * 100) : 0;
        $sql_progresso = "UPDATE gestao_processos SET progresso = ? WHERE id = ?";
        $stmt_progresso = $conexao->prepare($sql_progresso);
        $stmt_progresso->bind_param("ii", $novo_progresso, $processo_id);
        $stmt_progresso->execute();
        $stmt_progresso->close();
        
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                         VALUES (?, ?, 'checklist', 'Checklist atualizado - Progresso: $novo_progresso%')";
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $processo_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        $conexao->commit();
        
        $_SESSION['sucesso'] = 'Checklist atualizado com sucesso!';
        header("Location: checklist-processo.php?id=" . $processo_id);
        exit;
        
    } catch (Exception $e) {
        $conexao->rollback();
        $_SESSION['erro'] = 'Erro ao atualizar checklist: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist do Processo - Gestão de Processos</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .btn {
            padding: 12px 24px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background-color: var(--gray);
        }

        .btn-secondary:hover {
            background-color: var(--gray-light);
        }

        .btn-success {
            background-color: #10b981;
        }

        .btn-success:hover {
            background-color: #0da271;
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .progress-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 20px;
            height: 20px;
            margin: 1rem 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #10b981, #34d399);
            height: 100%;
            border-radius: 20px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .progress-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
        }
        
        .checklist-item {
            display: flex;
            align-items: flex-start;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
        }
        
        .checklist-item.concluido {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        
        .checklist-checkbox {
            margin-right: 1rem;
            margin-top: 0.25rem;
        }
        
        .checklist-content {
            flex: 1;
        }
        
        .empresa-info {
            margin-bottom: 0.5rem;
        }
        
        .observacao-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-family: inherit;
        }
        
        .conclusao-info {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 2rem;
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

        .empresa-badge {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(114, 9, 183, 0.1);
            color: var(--secondary);
            border-radius: 12px;
            font-size: 0.8rem;
            margin: 2px;
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .checklist-item {
                flex-direction: column;
            }
            
            .checklist-checkbox {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
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
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Checklist: <?php echo htmlspecialchars($processo['titulo']); ?></h1>
            <a href="dashboard-gestao.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
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

        <!-- Barra de Progresso -->
        <div class="progress-container">
            <div class="progress-text">
                Progresso do Processo: <?php echo $progresso; ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progresso; ?>%;">
                    <?php echo $progresso; ?>%
                </div>
            </div>
            <div style="color: #6b7280;">
                <?php echo $checklists_concluidos; ?> de <?php echo $total_checklists; ?> checklists concluídos
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.5rem; font-weight: 600; color: var(--dark);">Checklist por Empresa</h2>
                <div style="color: #6b7280; font-size: 0.9rem;">
                    Responsável: <?php echo htmlspecialchars($processo['responsavel_nome']); ?>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="atualizar_checklist" value="1">
                
                <div class="checklist-list">
                    <?php foreach ($checklist as $item): 
                        $progresso_empresa = 0;
                        if ($item['total_checklists_empresa'] > 0) {
                            $progresso_empresa = round(($item['checklists_concluidos_empresa'] / $item['total_checklists_empresa']) * 100);
                        }
                    ?>
                        <div class="checklist-item <?php echo $progresso_empresa == 100 ? 'concluido' : ''; ?>">
                            <div class="checklist-checkbox">
                                <input type="checkbox" 
                                    name="empresas[<?php echo $item['id']; ?>][concluido]" 
                                    value="1" 
                                    id="empresa_<?php echo $item['id']; ?>"
                                    <?php echo $progresso_empresa == 100 ? 'checked' : ''; ?>
                                    class="empresa-checkbox">
                            </div>
                            <div class="checklist-content">
                                <div class="empresa-info">
                                    <label for="empresa_<?php echo $item['id']; ?>" style="cursor: pointer; font-weight: 600; font-size: 1.1rem;">
                                        <?php echo htmlspecialchars($item['razao_social']); ?>
                                    </label>
                                    <div style="display: flex; gap: 10px; margin-top: 5px; flex-wrap: wrap; align-items: center;">
                                        <span class="empresa-badge">CNPJ: <?php echo htmlspecialchars($item['cnpj']); ?></span>
                                        <span class="empresa-badge"><?php echo htmlspecialchars($item['regime_tributario']); ?></span>
                                        <span class="empresa-badge">Progresso: <?php echo $progresso_empresa; ?>%</span>
                                    </div>
                                    
                                    <!-- Mini barra de progresso para a empresa -->
                                    <?php if ($item['total_checklists_empresa'] > 0): ?>
                                    <div style="margin-top: 0.5rem;">
                                        <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #6b7280; margin-bottom: 0.25rem;">
                                            <span>Checklists: <?php echo $item['checklists_concluidos_empresa']; ?>/<?php echo $item['total_checklists_empresa']; ?></span>
                                            <span><?php echo $progresso_empresa; ?>%</span>
                                        </div>
                                        <div style="background: #e9ecef; border-radius: 10px; height: 6px; overflow: hidden;">
                                            <div style="background: linear-gradient(90deg, #10b981, #34d399); height: 100%; border-radius: 10px; width: <?php echo $progresso_empresa; ?>%;"></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Link para ver detalhes do checklist da empresa -->
                                <div style="margin-top: 0.5rem;">
                                    <a href="empresa-processos.php?id=<?php echo $item['id']; ?>" class="btn btn-small" style="font-size: 0.8rem; padding: 4px 8px;">
                                        <i class="fas fa-external-link-alt"></i> Ver Checklist Completo
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <a href="dashboard-gestao.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Checklist
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Atualizar visualização quando checkbox for alterado
            const checkboxes = document.querySelectorAll('.empresa-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const item = this.closest('.checklist-item');
                    if (this.checked) {
                        item.classList.add('concluido');
                    } else {
                        item.classList.remove('concluido');
                    }
                });
            });

            // Animar barra de progresso
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                const width = progressFill.style.width;
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = width;
                }, 100);
            }
        });
    </script>
</body>
</html>