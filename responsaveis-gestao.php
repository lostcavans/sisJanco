<?php
session_start();
include("config-gestao.php");

// Verificar autenticação
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

// Verificar permissão
redirecionarSeNaoAutorizadoGestao('admin');

$empresa_id = $_SESSION['empresa_id_gestao'];

// Buscar responsáveis
$sql = "SELECT u.*, COUNT(p.id) as total_processos 
        FROM gestao_usuarios u 
        LEFT JOIN gestao_processos p ON u.id = p.responsavel_id AND p.empresa_id = ? 
        WHERE u.ativo = 1 
        GROUP BY u.id 
        ORDER BY u.nome_completo";
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$responsaveis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Processar formulário de adição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_responsavel'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = trim($_POST['email']);
    $nome_completo = trim($_POST['nome_completo']);
    $nivel_acesso = $_POST['nivel_acesso'];
    $departamento = trim($_POST['departamento']);
    $cargo = trim($_POST['cargo']);
    
    $sql_insert = "INSERT INTO gestao_usuarios 
                  (username, password, email, nome_completo, nivel_acesso, departamento, cargo) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conexao->prepare($sql_insert);
    $stmt->bind_param("sssssss", $username, $password, $email, $nome_completo, $nivel_acesso, $departamento, $cargo);
    
    if ($stmt->execute()) {
        $usuario_id = $stmt->insert_id;
        
        // Associar usuário à empresa
        $sql_associar = "INSERT INTO gestao_user_empresa (user_id, empresa_id, principal) VALUES (?, ?, 1)";
        $stmt_assoc = $conexao->prepare($sql_associar);
        $stmt_assoc->bind_param("ii", $usuario_id, $empresa_id);
        $stmt_assoc->execute();
        $stmt_assoc->close();
        
        // Registrar log
        registrarLogGestao('CRIAR_USUARIO', 'Usuário ' . $username . ' criado');
        
        $_SESSION['sucesso'] = 'Responsável adicionado com sucesso!';
        header("Location: responsaveis-gestao.php");
        exit;
    } else {
        $_SESSION['erro'] = 'Erro ao adicionar responsável: ' . $stmt->error;
    }
    $stmt->close();
}

// Processar edição de responsável
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_responsavel'])) {
    $usuario_id = $_POST['usuario_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $nome_completo = trim($_POST['nome_completo']);
    $nivel_acesso = $_POST['nivel_acesso'];
    $departamento = trim($_POST['departamento']);
    $cargo = trim($_POST['cargo']);
    
    // Verificar se foi informada uma nova senha
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql_update = "UPDATE gestao_usuarios SET username = ?, password = ?, email = ?, nome_completo = ?, nivel_acesso = ?, departamento = ?, cargo = ? WHERE id = ?";
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("sssssssi", $username, $password, $email, $nome_completo, $nivel_acesso, $departamento, $cargo, $usuario_id);
    } else {
        $sql_update = "UPDATE gestao_usuarios SET username = ?, email = ?, nome_completo = ?, nivel_acesso = ?, departamento = ?, cargo = ? WHERE id = ?";
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("ssssssi", $username, $email, $nome_completo, $nivel_acesso, $departamento, $cargo, $usuario_id);
    }
    
    if ($stmt->execute()) {
        // Registrar log
        registrarLogGestao('EDITAR_USUARIO', 'Usuário ' . $username . ' atualizado');
        
        $_SESSION['sucesso'] = 'Responsável atualizado com sucesso!';
        header("Location: responsaveis-gestao.php");
        exit;
    } else {
        $_SESSION['erro'] = 'Erro ao atualizar responsável: ' . $stmt->error;
    }
    $stmt->close();
}

// Processar exclusão de responsável
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_responsavel'])) {
    $usuario_id = $_POST['usuario_id'];
    
    // Verificar se o usuário tem processos associados
    $sql_check = "SELECT COUNT(*) as total FROM gestao_processos WHERE responsavel_id = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("i", $usuario_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if ($result['total'] > 0) {
        $_SESSION['erro'] = 'Não é possível excluir o responsável pois existem processos associados a ele.';
        header("Location: responsaveis-gestao.php");
        exit;
    }
    
    // Fazer exclusão lógica (desativar usuário)
    $sql_delete = "UPDATE gestao_usuarios SET ativo = 0 WHERE id = ?";
    $stmt = $conexao->prepare($sql_delete);
    $stmt->bind_param("i", $usuario_id);
    
    if ($stmt->execute()) {
        // Registrar log
        registrarLogGestao('EXCLUIR_USUARIO', 'Usuário ID ' . $usuario_id . ' excluído');
        
        $_SESSION['sucesso'] = 'Responsável excluído com sucesso!';
        header("Location: responsaveis-gestao.php");
        exit;
    } else {
        $_SESSION['erro'] = 'Erro ao excluir responsável: ' . $stmt->error;
    }
    $stmt->close();
}

// Buscar dados de um responsável específico para edição
$responsavel_edicao = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $usuario_id = $_GET['editar'];
    $sql_edicao = "SELECT * FROM gestao_usuarios WHERE id = ? AND ativo = 1";
    $stmt_edicao = $conexao->prepare($sql_edicao);
    $stmt_edicao->bind_param("i", $usuario_id);
    $stmt_edicao->execute();
    $responsavel_edicao = $stmt_edicao->get_result()->fetch_assoc();
    $stmt_edicao->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsáveis - Gestão de Processos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Seu CSS existente permanece igual */
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
            max-width: 1200px;
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
            overflow: hidden;
        }

        .card-header {
            background: var(--light);
            padding: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
        }

        .card-body {
            padding: 0;
        }

        .responsible-item {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            align-items: center;
        }

        .responsible-item:last-child {
            border-bottom: none;
        }

        .nivel-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .nivel-admin { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .nivel-analista { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .nivel-auxiliar { background: rgba(16, 185, 129, 0.1); color: #10b981; }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 6px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            background: transparent;
        }

        .edit-btn {
            color: var(--primary);
        }

        .delete-btn {
            color: #ef4444;
        }

        .action-btn:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            transform: translateY(-20px);
            transition: var(--transition);
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
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

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
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

        .password-note {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .card-header, .responsible-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
            <li><a href="responsaveis-gestao.php" class="nav-link active">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Responsáveis</h1>
            <button id="addResponsibleBtn" class="btn">
                <i class="fas fa-plus"></i> Novo Responsável
            </button>
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
            <div class="card-header">
                <div>Nome</div>
                <div>Email</div>
                <div>Departamento</div>
                <div>Cargo</div>
                <div>Nível</div>
                <div>Ações</div>
            </div>
            <div class="card-body">
                <?php if (count($responsaveis) > 0): ?>
                    <?php foreach ($responsaveis as $responsavel): ?>
                        <div class="responsible-item">
                            <div>
                                <strong><?php echo htmlspecialchars($responsavel['nome_completo']); ?></strong>
                                <br><small>@<?php echo htmlspecialchars($responsavel['username']); ?></small>
                            </div>
                            <div><?php echo htmlspecialchars($responsavel['email']); ?></div>
                            <div><?php echo htmlspecialchars($responsavel['departamento'] ?? '-'); ?></div>
                            <div><?php echo htmlspecialchars($responsavel['cargo'] ?? '-'); ?></div>
                            <div>
                                <span class="nivel-badge nivel-<?php echo $responsavel['nivel_acesso']; ?>">
                                    <?php echo ucfirst($responsavel['nivel_acesso']); ?>
                                </span>
                                <br>
                                <small><?php echo $responsavel['total_processos']; ?> processos</small>
                            </div>
                            <div class="actions">
                                <button class="action-btn edit-btn" title="Editar" onclick="editarResponsavel(<?php echo $responsavel['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete-btn" title="Excluir" onclick="excluirResponsavel(<?php echo $responsavel['id']; ?>, '<?php echo htmlspecialchars($responsavel['nome_completo']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="responsible-item" style="display: block; text-align: center; padding: 3rem;">
                        <i class="fas fa-users" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                        <p style="color: var(--gray);">Nenhum responsável cadastrado</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Adicionar Responsável -->
    <div id="addResponsibleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Adicionar Novo Responsável</h2>
                <button class="close-btn">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="adicionar_responsavel" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome_completo">Nome Completo *</label>
                        <input type="text" id="nome_completo" name="nome_completo" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Senha *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="nivel_acesso">Nível de Acesso *</label>
                        <select id="nivel_acesso" name="nivel_acesso" required>
                            <option value="auxiliar">Auxiliar</option>
                            <option value="analista">Analista</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="departamento">Departamento</label>
                        <input type="text" id="departamento" name="departamento">
                    </div>
                    <div class="form-group">
                        <label for="cargo">Cargo</label>
                        <input type="text" id="cargo" name="cargo">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelAddResponsible">Cancelar</button>
                    <button type="submit" class="btn">Salvar Responsável</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Responsável -->
    <div id="editResponsibleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Editar Responsável</h2>
                <button class="close-btn">&times;</button>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="editar_responsavel" value="1">
                <input type="hidden" name="usuario_id" id="edit_usuario_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_nome_completo">Nome Completo *</label>
                        <input type="text" id="edit_nome_completo" name="nome_completo" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_username">Username *</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">Nova Senha</label>
                        <input type="password" id="edit_password" name="password">
                        <div class="password-note">Deixe em branco para manter a senha atual</div>
                    </div>
                    <div class="form-group">
                        <label for="edit_nivel_acesso">Nível de Acesso *</label>
                        <select id="edit_nivel_acesso" name="nivel_acesso" required>
                            <option value="auxiliar">Auxiliar</option>
                            <option value="analista">Analista</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_departamento">Departamento</label>
                        <input type="text" id="edit_departamento" name="departamento">
                    </div>
                    <div class="form-group">
                        <label for="edit_cargo">Cargo</label>
                        <input type="text" id="edit_cargo" name="cargo">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelEditResponsible">Cancelar</button>
                    <button type="submit" class="btn">Atualizar Responsável</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmar Exclusão -->
    <div id="deleteResponsibleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirmar Exclusão</h2>
                <button class="close-btn">&times;</button>
            </div>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="excluir_responsavel" value="1">
                <input type="hidden" name="usuario_id" id="delete_usuario_id">
                <div class="form-group">
                    <p>Tem certeza que deseja excluir o responsável <strong id="delete_nome"></strong>?</p>
                    <p class="password-note">Esta ação não pode ser desfeita. O responsável será desativado do sistema.</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelDeleteResponsible">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir Responsável</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modais
            const addModal = document.getElementById('addResponsibleModal');
            const editModal = document.getElementById('editResponsibleModal');
            const deleteModal = document.getElementById('deleteResponsibleModal');
            
            // Botões abrir modal
            const openAddBtn = document.getElementById('addResponsibleBtn');
            
            // Botões fechar modal
            const closeAddBtn = addModal.querySelector('.close-btn');
            const closeEditBtn = editModal.querySelector('.close-btn');
            const closeDeleteBtn = deleteModal.querySelector('.close-btn');
            
            // Botões cancelar
            const cancelAddBtn = document.getElementById('cancelAddResponsible');
            const cancelEditBtn = document.getElementById('cancelEditResponsible');
            const cancelDeleteBtn = document.getElementById('cancelDeleteResponsible');

            // Funções para abrir modais
            function openAddModal() {
                addModal.classList.add('show');
            }

            function openEditModal() {
                editModal.classList.add('show');
            }

            function openDeleteModal() {
                deleteModal.classList.add('show');
            }

            // Funções para fechar modais
            function closeAddModal() {
                addModal.classList.remove('show');
            }

            function closeEditModal() {
                editModal.classList.remove('show');
            }

            function closeDeleteModal() {
                deleteModal.classList.remove('show');
            }

            // Event listeners para abrir modais
            openAddBtn.addEventListener('click', openAddModal);

            // Event listeners para fechar modais
            closeAddBtn.addEventListener('click', closeAddModal);
            closeEditBtn.addEventListener('click', closeEditModal);
            closeDeleteBtn.addEventListener('click', closeDeleteModal);

            // Event listeners para cancelar
            cancelAddBtn.addEventListener('click', closeAddModal);
            cancelEditBtn.addEventListener('click', closeEditModal);
            cancelDeleteBtn.addEventListener('click', closeDeleteModal);

            // Fechar modal ao clicar fora
            addModal.addEventListener('click', function(e) {
                if (e.target === addModal) closeAddModal();
            });
            editModal.addEventListener('click', function(e) {
                if (e.target === editModal) closeEditModal();
            });
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) closeDeleteModal();
            });

            // Verificar se deve abrir modal de edição via URL
            <?php if (isset($_GET['editar']) && $responsavel_edicao): ?>
                preencherFormularioEdicao(<?php echo json_encode($responsavel_edicao); ?>);
                openEditModal();
            <?php endif; ?>
        });

        function editarResponsavel(id) {
            window.location.href = 'responsaveis-gestao.php?editar=' + id;
        }

        function preencherFormularioEdicao(responsavel) {
            document.getElementById('edit_usuario_id').value = responsavel.id;
            document.getElementById('edit_nome_completo').value = responsavel.nome_completo;
            document.getElementById('edit_username').value = responsavel.username;
            document.getElementById('edit_email').value = responsavel.email;
            document.getElementById('edit_nivel_acesso').value = responsavel.nivel_acesso;
            document.getElementById('edit_departamento').value = responsavel.departamento || '';
            document.getElementById('edit_cargo').value = responsavel.cargo || '';
        }

        function excluirResponsavel(id, nome) {
            document.getElementById('delete_usuario_id').value = id;
            document.getElementById('delete_nome').textContent = nome;
            
            const deleteModal = document.getElementById('deleteResponsibleModal');
            deleteModal.classList.add('show');
        }
    </script>
</body>
</html>