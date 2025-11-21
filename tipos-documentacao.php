<?php
session_start();
include("config-gestao.php");

// Verificar autenticação e permissão
if (!verificarAutenticacaoGestao() || !temPermissaoGestao('admin')) {
    $_SESSION['erro'] = 'Acesso não autorizado.';
    header("Location: gestao-empresas.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];

// Buscar tipos de documentação
$sql_tipos = "SELECT * FROM gestao_tipos_documentacao ORDER BY nome";
$tipos = $conexao->query($sql_tipos)->fetch_all(MYSQLI_ASSOC);

// Processar adição de tipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_tipo'])) {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $recorrencia = $_POST['recorrencia'];
    $prazo_dias = intval($_POST['prazo_dias']);

    // Validar dados
    if (empty($nome)) {
        $_SESSION['erro'] = 'O nome do tipo de documentação é obrigatório.';
        header("Location: tipos-documentacao.php");
        exit;
    }

    try {
        // Verificar se já existe tipo com mesmo nome
        $sql_check = "SELECT id FROM gestao_tipos_documentacao WHERE nome = ? AND ativo = 1";
        $stmt_check = $conexao->prepare($sql_check);
        $stmt_check->bind_param("s", $nome);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception('Já existe um tipo de documentação com este nome.');
        }
        $stmt_check->close();

        // Inserir novo tipo
        $sql_insert = "INSERT INTO gestao_tipos_documentacao 
                      (nome, descricao, recorrencia, prazo_dias, ativo)
                      VALUES (?, ?, ?, ?, 1)";
        
        $stmt = $conexao->prepare($sql_insert);
        $stmt->bind_param("sssi", $nome, $descricao, $recorrencia, $prazo_dias);
        
        if ($stmt->execute()) {
            $_SESSION['sucesso'] = 'Tipo de documentação cadastrado com sucesso!';
        } else {
            throw new Exception('Erro ao cadastrar tipo: ' . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['erro'] = $e->getMessage();
    }
    
    header("Location: tipos-documentacao.php");
    exit;
}

// Processar edição de tipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_tipo'])) {
    $tipo_id = $_POST['tipo_id'];
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $recorrencia = $_POST['recorrencia'];
    $prazo_dias = intval($_POST['prazo_dias']);

    if (empty($nome)) {
        $_SESSION['erro'] = 'O nome do tipo de documentação é obrigatório.';
        header("Location: tipos-documentacao.php");
        exit;
    }

    try {
        // Verificar se já existe outro tipo com mesmo nome
        $sql_check = "SELECT id FROM gestao_tipos_documentacao WHERE nome = ? AND id != ? AND ativo = 1";
        $stmt_check = $conexao->prepare($sql_check);
        $stmt_check->bind_param("si", $nome, $tipo_id);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            throw new Exception('Já existe um tipo de documentação com este nome.');
        }
        $stmt_check->close();

        // Atualizar tipo
        $sql_update = "UPDATE gestao_tipos_documentacao 
                      SET nome = ?, descricao = ?, recorrencia = ?, prazo_dias = ?, updated_at = NOW()
                      WHERE id = ?";
        
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("sssii", $nome, $descricao, $recorrencia, $prazo_dias, $tipo_id);
        
        if ($stmt->execute()) {
            $_SESSION['sucesso'] = 'Tipo de documentação atualizado com sucesso!';
        } else {
            throw new Exception('Erro ao atualizar tipo: ' . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['erro'] = $e->getMessage();
    }
    
    header("Location: tipos-documentacao.php");
    exit;
}

// Processar exclusão (inativação) de tipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_tipo'])) {
    $tipo_id = $_POST['tipo_id'];

    try {
        // Verificar se existem documentações usando este tipo
        $sql_check = "SELECT COUNT(*) as total FROM gestao_documentacoes_empresa WHERE tipo_documentacao_id = ?";
        $stmt_check = $conexao->prepare($sql_check);
        $stmt_check->bind_param("i", $tipo_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($result['total'] > 0) {
            throw new Exception('Não é possível excluir este tipo pois existem documentações vinculadas a ele.');
        }

        // Inativar tipo
        $sql_update = "UPDATE gestao_tipos_documentacao SET ativo = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("i", $tipo_id);
        
        if ($stmt->execute()) {
            $_SESSION['sucesso'] = 'Tipo de documentação excluído com sucesso!';
        } else {
            throw new Exception('Erro ao excluir tipo: ' . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['erro'] = $e->getMessage();
    }
    
    header("Location: tipos-documentacao.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Documentação - Gestão de Processos</title>
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

        .btn-warning {
            background-color: #f59e0b;
        }

        .btn-warning:hover {
            background-color: #e6900a;
        }

        .btn-danger {
            background-color: #ef4444;
        }

        .btn-danger:hover {
            background-color: #dc2626;
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
            font-size: 1rem;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .recorrencia-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
        }

        .recorrencia-unica { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
        .recorrencia-mensal { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .recorrencia-trimestral { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .recorrencia-semestral { background: rgba(168, 85, 247, 0.1); color: #a855f7; }
        .recorrencia-anual { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

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

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow-lg);
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
            font-weight: 600;
            color: var(--dark);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .table {
                display: block;
                overflow-x: auto;
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
            <li><a href="gestao-empresas.php" class="nav-link">Empresas</a></li>
            <li><a href="tipos-documentacao.php" class="nav-link active">Tipos de Doc.</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Tipos de Documentação</h1>
            <a href="gestao-empresas.php" class="btn btn-secondary">
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

        <!-- CARD DE ADIÇÃO DE TIPO -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: var(--dark);">
                <i class="fas fa-plus-circle"></i> Adicionar Novo Tipo
            </h2>
            
            <form method="POST" action="">
                <input type="hidden" name="adicionar_tipo" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome">Nome do Tipo *</label>
                        <input type="text" id="nome" name="nome" required 
                               placeholder="Ex: Declaração de ISS, DCTF, SPED Fiscal...">
                    </div>
                    
                    <div class="form-group">
                        <label for="recorrencia">Recorrência *</label>
                        <select id="recorrencia" name="recorrencia" required>
                            <option value="unica">Única</option>
                            <option value="mensal" selected>Mensal</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="semestral">Semestral</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" 
                              placeholder="Descreva o tipo de documentação..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="prazo_dias">Prazo para Recebimento (dias) *</label>
                    <input type="number" id="prazo_dias" name="prazo_dias" value="15" min="1" max="365" required>
                    <small style="color: var(--gray);">Número de dias após o fim do período para recebimento</small>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Limpar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Cadastrar Tipo
                    </button>
                </div>
            </form>
        </div>

        <!-- LISTA DE TIPOS EXISTENTES -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: var(--dark);">
                <i class="fas fa-list"></i> Tipos Cadastrados
            </h2>
            
            <?php if (count($tipos) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th>Recorrência</th>
                                <th>Prazo (dias)</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos as $tipo): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($tipo['nome']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($tipo['descricao'] ?: '-'); ?>
                                    </td>
                                    <td>
                                        <span class="recorrencia-badge recorrencia-<?php echo $tipo['recorrencia']; ?>">
                                            <?php echo ucfirst($tipo['recorrencia']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $tipo['prazo_dias']; ?> dias
                                    </td>
                                    <td>
                                        <span style="color: <?php echo $tipo['ativo'] ? '#10b981' : '#ef4444'; ?>;">
                                            <i class="fas fa-circle" style="font-size: 0.7rem;"></i>
                                            <?php echo $tipo['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-small btn-warning" 
                                                    onclick="abrirModalEdicao(<?php echo htmlspecialchars(json_encode($tipo)); ?>)">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            <?php if ($tipo['ativo']): ?>
                                                <button class="btn btn-small btn-danger" 
                                                        onclick="confirmarExclusao(<?php echo $tipo['id']; ?>, '<?php echo htmlspecialchars($tipo['nome']); ?>')">
                                                    <i class="fas fa-trash"></i> Excluir
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--gray);">
                    <i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>Nenhum tipo de documentação cadastrado</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODAL DE EDIÇÃO -->
    <div id="modalEdicao" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Editar Tipo de Documentação</h2>
                <button class="close-btn" onclick="fecharModalEdicao()">&times;</button>
            </div>
            
            <form method="POST" action="" id="formEdicao">
                <input type="hidden" name="editar_tipo" value="1">
                <input type="hidden" name="tipo_id" id="editar_tipo_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editar_nome">Nome do Tipo *</label>
                        <input type="text" id="editar_nome" name="nome" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar_recorrencia">Recorrência *</label>
                        <select id="editar_recorrencia" name="recorrencia" required>
                            <option value="unica">Única</option>
                            <option value="mensal">Mensal</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="semestral">Semestral</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editar_descricao">Descrição</label>
                    <textarea id="editar_descricao" name="descricao"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="editar_prazo_dias">Prazo para Recebimento (dias) *</label>
                    <input type="number" id="editar_prazo_dias" name="prazo_dias" min="1" max="365" required>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalEdicao()">Cancelar</button>
                    <button type="submit" class="btn btn-success">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <!-- FORMULÁRIO OCULTO PARA EXCLUSÃO -->
    <form method="POST" action="" id="formExclusao" style="display: none;">
        <input type="hidden" name="excluir_tipo" value="1">
        <input type="hidden" name="tipo_id" id="excluir_tipo_id">
    </form>

    <script>
        // MODAL DE EDIÇÃO
        function abrirModalEdicao(tipo) {
            document.getElementById('editar_tipo_id').value = tipo.id;
            document.getElementById('editar_nome').value = tipo.nome;
            document.getElementById('editar_descricao').value = tipo.descricao || '';
            document.getElementById('editar_recorrencia').value = tipo.recorrencia;
            document.getElementById('editar_prazo_dias').value = tipo.prazo_dias;
            
            document.getElementById('modalEdicao').style.display = 'flex';
        }
        
        function fecharModalEdicao() {
            document.getElementById('modalEdicao').style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('modalEdicao').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalEdicao();
            }
        });
        
        // CONFIRMAÇÃO DE EXCLUSÃO
        function confirmarExclusao(tipoId, tipoNome) {
            if (confirm(`Tem certeza que deseja excluir o tipo "${tipoNome}"?\n\nEsta ação não pode ser desfeita.`)) {
                document.getElementById('excluir_tipo_id').value = tipoId;
                document.getElementById('formExclusao').submit();
            }
        }
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharModalEdicao();
            }
        });
    </script>
</body>
</html>