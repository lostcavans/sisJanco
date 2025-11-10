<?php
session_start();
include("config-gestao.php");

// Verificar autenticação
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];

// Verificar se o ID do processo foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['erro'] = 'Processo não encontrado.';
    header("Location: processos-gestao.php");
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
    header("Location: processos-gestao.php");
    exit;
}

// Buscar empresas associadas ao processo
$sql_empresas_processo = "SELECT empresa_id FROM gestao_processo_empresas WHERE processo_id = ?";
$stmt = $conexao->prepare($sql_empresas_processo);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$empresas_associadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$empresas_selecionadas_ids = array_column($empresas_associadas, 'empresa_id');

// Verificar permissão
if (!temPermissaoGestao('analista')) {
    $_SESSION['erro'] = 'Você não tem permissão para editar processos.';
    header("Location: processos-gestao.php");
    exit;
}

// Buscar responsáveis
$sql_responsaveis = "SELECT id, nome_completo, nivel_acesso 
                     FROM gestao_usuarios 
                     WHERE ativo = 1 
                     ORDER BY nome_completo";
$stmt = $conexao->prepare($sql_responsaveis);
$stmt->execute();
$responsaveis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Buscar categorias
$sql_categorias = "SELECT id, nome FROM gestao_categorias_processo WHERE ativo = 1 ORDER BY nome";
$categorias = $conexao->query($sql_categorias)->fetch_all(MYSQLI_ASSOC);

// Buscar TODAS as empresas disponíveis
$sql_empresas = "SELECT id, razao_social, cnpj, regime_tributario, atividade 
                 FROM empresas 
                 ORDER BY razao_social";
$todas_empresas = $conexao->query($sql_empresas)->fetch_all(MYSQLI_ASSOC);

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_processo'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $categoria_id = $_POST['categoria_id'];
    $responsavel_id = $_POST['responsavel_id'];
    $prioridade = $_POST['prioridade'];
    $data_prevista = $_POST['data_prevista'];
    $status = $_POST['status'];
    
    // Verificar se empresas foram selecionadas
    $novas_empresas_selecionadas = $_POST['empresas'] ?? [];
    
    if (empty($novas_empresas_selecionadas)) {
        $_SESSION['erro'] = 'Selecione pelo menos uma empresa para o processo.';
        header("Location: editar-processo.php?id=" . $processo_id);
        exit;
    }
    
    // Iniciar transação para garantir consistência
    $conexao->begin_transaction();
    
    try {
        // Atualizar dados do processo
        $sql_update = "UPDATE gestao_processos 
                      SET titulo = ?, descricao = ?, categoria_id = ?, 
                          responsavel_id = ?, prioridade = ?, data_prevista = ?, status = ?
                      WHERE id = ?";
        
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("ssiisssi", $titulo, $descricao, $categoria_id, $responsavel_id, 
                         $prioridade, $data_prevista, $status, $processo_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar processo: ' . $stmt->error);
        }
        $stmt->close();
        
        // Atualizar empresas associadas
        // Remover empresas que não estão mais selecionadas
        $empresas_para_remover = array_diff($empresas_selecionadas_ids, $novas_empresas_selecionadas);
        if (!empty($empresas_para_remover)) {
            $placeholders = str_repeat('?,', count($empresas_para_remover) - 1) . '?';
            $sql_remove = "DELETE FROM gestao_processo_empresas WHERE processo_id = ? AND empresa_id IN ($placeholders)";
            $stmt = $conexao->prepare($sql_remove);
            $types = str_repeat('i', count($empresas_para_remover) + 1);
            $params = array_merge([$processo_id], array_values($empresas_para_remover));
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception('Erro ao remover empresas do processo: ' . $stmt->error);
            }
            $stmt->close();
        }
        
        // Adicionar novas empresas selecionadas
        $empresas_para_adicionar = array_diff($novas_empresas_selecionadas, $empresas_selecionadas_ids);
        if (!empty($empresas_para_adicionar)) {
            $sql_add = "INSERT INTO gestao_processo_empresas (processo_id, empresa_id) VALUES (?, ?)";
            $stmt = $conexao->prepare($sql_add);
            
            foreach ($empresas_para_adicionar as $empresa_id) {
                $stmt->bind_param("ii", $processo_id, $empresa_id);
                if (!$stmt->execute()) {
                    throw new Exception('Erro ao adicionar empresa ao processo: ' . $stmt->error);
                }
            }
            $stmt->close();
        }
        
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                         VALUES (?, ?, 'atualizacao', 'Processo atualizado - " . count($novas_empresas_selecionadas) . " empresa(s)')";
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $processo_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        // Commit da transação
        $conexao->commit();
        
        registrarLogGestao('EDITAR_PROCESSO', 'Processo ' . $processo['codigo'] . ' atualizado para ' . count($novas_empresas_selecionadas) . ' empresa(s)');
        
        $_SESSION['sucesso'] = 'Processo atualizado com sucesso para ' . count($novas_empresas_selecionadas) . ' empresa(s)!';
        header("Location: processos-gestao.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        $conexao->rollback();
        $_SESSION['erro'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Processo - Gestão de Processos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ESTILOS MANTIDOS DO CÓDIGO ORIGINAL */
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

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
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

        .user-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        /* Estilos para a tabela de empresas */
        .empresas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .empresas-table th,
        .empresas-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .empresas-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .empresas-table tr:hover {
            background: #f8f9fa;
        }

        .empresa-selecionada {
            background: rgba(67, 97, 238, 0.05) !important;
        }

        .empresa-checkbox {
            width: 18px;
            height: 18px;
        }

        .controles-empresas {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .controles-empresas {
                flex-direction: column;
                align-items: flex-start;
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
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Editar Processo</h1>
            <a href="processos-gestao.php" class="btn btn-secondary">
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

        <div class="card">
            <form method="POST" action="">
                <input type="hidden" name="editar_processo" value="1">
                
                <div class="form-group">
                    <label for="codigo">Código</label>
                    <input type="text" id="codigo" value="<?php echo htmlspecialchars($processo['codigo']); ?>" readonly style="background: #f8f9fa;">
                    <small style="color: var(--gray);">Código não pode ser alterado</small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="titulo">Título do Processo *</label>
                        <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($processo['titulo']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="categoria_id">Categoria</label>
                        <select id="categoria_id" name="categoria_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo $categoria['id'] == $processo['categoria_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="responsavel_id">Responsável *</label>
                        <select id="responsavel_id" name="responsavel_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($responsaveis as $responsavel): ?>
                                <option value="<?php echo $responsavel['id']; ?>" 
                                    <?php echo $responsavel['id'] == $processo['responsavel_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($responsavel['nome_completo']); ?>
                                    <span class="user-badge"><?php echo $responsavel['nivel_acesso']; ?></span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="prioridade">Prioridade</label>
                        <select id="prioridade" name="prioridade">
                            <option value="baixa" <?php echo $processo['prioridade'] == 'baixa' ? 'selected' : ''; ?>>Baixa</option>
                            <option value="media" <?php echo $processo['prioridade'] == 'media' ? 'selected' : ''; ?>>Média</option>
                            <option value="alta" <?php echo $processo['prioridade'] == 'alta' ? 'selected' : ''; ?>>Alta</option>
                            <option value="urgente" <?php echo $processo['prioridade'] == 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="data_prevista">Data Prevista</label>
                        <input type="date" id="data_prevista" name="data_prevista" value="<?php echo $processo['data_prevista']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="rascunho" <?php echo $processo['status'] == 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                            <option value="pendente" <?php echo $processo['status'] == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="em_andamento" <?php echo $processo['status'] == 'em_andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                            <option value="pausado" <?php echo $processo['status'] == 'pausado' ? 'selected' : ''; ?>>Pausado</option>
                            <option value="concluido" <?php echo $processo['status'] == 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                            <option value="cancelado" <?php echo $processo['status'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" placeholder="Descreva o processo..."><?php echo htmlspecialchars($processo['descricao']); ?></textarea>
                </div>

                <!-- SEÇÃO DE SELEÇÃO DE EMPRESAS -->
                <div class="form-group">
                    <label>Selecionar Empresas *</label>
                    
                    <div class="controles-empresas">
                        <button type="button" class="btn selecionar-todas-btn" style="padding: 8px 16px; font-size: 0.9rem;">
                            <i class="fas fa-check-double"></i> Selecionar Todas
                        </button>
                        <button type="button" class="btn btn-secondary limpar-selecao-btn" style="padding: 8px 16px; font-size: 0.9rem;">
                            <i class="fas fa-times"></i> Limpar Seleção
                        </button>
                        <span id="contador-empresas" style="font-weight: 500; color: var(--primary);">
                            0 empresas selecionadas
                        </span>
                    </div>

                    <div style="border: 1px solid #ddd; border-radius: var(--border-radius); overflow: hidden;">
                        <table class="empresas-table">
                            <thead>
                                <tr>
                                    <th width="50px"></th>
                                    <th>Razão Social</th>
                                    <th>CNPJ</th>
                                    <th>Regime Tributário</th>
                                    <th>Atividade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todas_empresas as $empresa): ?>
                                    <tr class="<?php echo $empresa['id'] == $processo['empresa_id'] ? 'empresa-selecionada' : ''; ?>">
                                        <td>
                                            <input type="checkbox" 
                                                   class="empresa-checkbox" 
                                                   name="empresas[]" 
                                                   value="<?php echo $empresa['id']; ?>" 
                                                   id="empresa_<?php echo $empresa['id']; ?>"
                                                   <?php echo $empresa['id'] == $processo['empresa_id'] ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <label for="empresa_<?php echo $empresa['id']; ?>" style="cursor: pointer; font-weight: 500;">
                                                <?php echo htmlspecialchars($empresa['razao_social']); ?>
                                            </label>
                                        </td>
                                        <td><?php echo htmlspecialchars($empresa['cnpj']); ?></td>
                                        <td>
                                            <span class="user-badge"><?php echo htmlspecialchars($empresa['regime_tributario']); ?></span>
                                        </td>
                                        <td>
                                            <span class="user-badge"><?php echo htmlspecialchars($empresa['atividade']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small style="color: #666; font-size: 0.8rem; margin-top: 0.5rem; display: block;">
                        Selecione uma ou mais empresas para este processo. A empresa atual já está selecionada.
                    </small>
                </div>

                <div class="form-actions">
                    <a href="processos-gestao.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Atualizar Processo(s)
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Definir data mínima para o campo de data
            const dataInput = document.getElementById('data_prevista');
            if (dataInput) {
                const today = new Date().toISOString().split('T')[0];
                dataInput.min = today;
            }

            // Controles para seleção de empresas
            const selecionarTodasBtn = document.querySelector('.selecionar-todas-btn');
            const limparSelecaoBtn = document.querySelector('.limpar-selecao-btn');
            const empresaCheckboxes = document.querySelectorAll('.empresa-checkbox');
            const contadorEmpresas = document.getElementById('contador-empresas');

            // Atualizar contador
            function atualizarContador() {
                const selecionadas = document.querySelectorAll('.empresa-checkbox:checked').length;
                if (contadorEmpresas) {
                    contadorEmpresas.textContent = `${selecionadas} empresa(s) selecionada(s)`;
                }
                
                // Atualizar classes das linhas
                empresaCheckboxes.forEach(checkbox => {
                    const linha = checkbox.closest('tr');
                    if (linha) {
                        if (checkbox.checked) {
                            linha.classList.add('empresa-selecionada');
                        } else {
                            linha.classList.remove('empresa-selecionada');
                        }
                    }
                });
            }
            
            // Selecionar todas as empresas
            if (selecionarTodasBtn) {
                selecionarTodasBtn.addEventListener('click', function() {
                    empresaCheckboxes.forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    atualizarContador();
                });
            }
            
            // Limpar seleção
            if (limparSelecaoBtn) {
                limparSelecaoBtn.addEventListener('click', function() {
                    empresaCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    atualizarContador();
                });
            }
            
            // Atualizar contador quando checkboxes mudarem
            empresaCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', atualizarContador);
            });
            
            // Inicializar contador
            atualizarContador();

            // Validação do formulário
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const empresasSelecionadas = document.querySelectorAll('.empresa-checkbox:checked');
                    if (empresasSelecionadas.length === 0) {
                        e.preventDefault();
                        alert('Selecione pelo menos uma empresa para o processo.');
                        
                        // Destacar a seção de empresas
                        const empresasSection = document.querySelector('.form-group:has(.empresas-table)');
                        if (empresasSection) {
                            empresasSection.style.border = '2px solid #ef4444';
                            empresasSection.style.borderRadius = 'var(--border-radius)';
                            empresasSection.style.padding = '1rem';
                            
                            // Scroll para a seção
                            empresasSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            
                            // Remover o destaque após 3 segundos
                            setTimeout(() => {
                                empresasSection.style.border = '';
                                empresasSection.style.padding = '';
                            }, 3000);
                        }
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>