<?php
session_start();
include("config-gestao.php");

// Verificar autenticação E permissão
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

// VERIFICAÇÃO DE PERMISSÃO - Apenas Admin e Analistas
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];
if (!in_array($nivel_usuario, ['admin', 'analista'])) {
    $_SESSION['erro'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header("Location: dashboard-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];

// Buscar responsáveis
$sql_responsaveis = "SELECT id, nome_completo FROM gestao_usuarios WHERE ativo = 1 ORDER BY nome_completo";
$responsaveis = $conexao->query($sql_responsaveis)->fetch_all(MYSQLI_ASSOC);

// Buscar categorias/departamentos
$sql_categorias = "SELECT id, nome FROM gestao_categorias_processo WHERE ativo = 1 ORDER BY nome";
$categorias = $conexao->query($sql_categorias)->fetch_all(MYSQLI_ASSOC);

// Buscar processos pré-definidos
$sql_processos = "SELECT p.*, c.nome as categoria_nome, u.nome_completo as responsavel_nome,
                         (SELECT COUNT(*) FROM gestao_processo_predefinido_checklist pc WHERE pc.processo_id = p.id) as total_checklist
                  FROM gestao_processos p
                  LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id
                  LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id
                  WHERE p.ativo = 1 
                  ORDER BY p.created_at DESC";
$processos = $conexao->query($sql_processos)->fetch_all(MYSQLI_ASSOC);

// Processar formulário de adição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_processo'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria_id = intval($_POST['categoria_id']);
    $responsavel_id = intval($_POST['responsavel_id']);
    $prioridade = $_POST['prioridade'];
    $recorrente = $_POST['recorrente'];
    
    // Gerar código automático
    $sql_count = "SELECT COUNT(*) as total FROM gestao_processos WHERE codigo LIKE 'PRC-%'";
    $result = $conexao->query($sql_count);
    $total = $result->fetch_assoc()['total'];
    $codigo = 'PRC-' . str_pad($total + 1, 4, '0', STR_PAD_LEFT);
    
    // Inserir processo
    $sql_insert = "INSERT INTO gestao_processos 
                (codigo, titulo, descricao, categoria_id, responsavel_id, 
                criador_id, prioridade, recorrente, status, ativo, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ativo', 1, NOW(), NOW())";

    $stmt = $conexao->prepare($sql_insert);
    // CORREÇÃO: String de tipos com 8 caracteres para 8 variáveis
    $stmt->bind_param("sssiisss", $codigo, $titulo, $descricao, $categoria_id, 
                    $responsavel_id, $usuario_id, $prioridade, $recorrente);
    
    if ($stmt->execute()) {
    $processo_id = $stmt->insert_id;
    
    // Salvar checklist do processo pré-definido na NOVA tabela
    if (isset($_POST['checklist_titulo']) && is_array($_POST['checklist_titulo'])) {
        foreach ($_POST['checklist_titulo'] as $index => $titulo_item) {
            $descricao_item = $_POST['checklist_descricao'][$index] ?? '';
            
            if (!empty(trim($titulo_item))) {
                // USAR A NOVA TABELA para processos pré-definidos
                $sql_checklist = "INSERT INTO gestao_processo_predefinido_checklist 
                                 (processo_id, titulo, descricao, ordem, created_at, updated_at)
                                 VALUES (?, ?, ?, ?, NOW(), NOW())";
                
                $stmt_checklist = $conexao->prepare($sql_checklist);
                $ordem = $index + 1;
                $stmt_checklist->bind_param("issi", $processo_id, $titulo_item, $descricao_item, $ordem);
                $stmt_checklist->execute();
                $stmt_checklist->close();
            }
        }
    }
    
    $_SESSION['sucesso'] = 'Processo pré-definido criado com sucesso!';
} else {
        $_SESSION['erro'] = 'Erro ao criar processo: ' . $stmt->error;
    }
    
    $stmt->close();
    header("Location: processos-gestao.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processos Pré-definidos - Gestão</title>
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
            <li><a href="processos-gestao.php" class="nav-link active">Processos</a></li>
            <li><a href="gestao-empresas.php" class="nav-link">Empresas</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Processos Pré-definidos</h1>
            <button class="btn" onclick="abrirModal('addProcessModal')">
                <i class="fas fa-plus"></i> Novo Processo
            </button>
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

        <div class="form-section fade-in">
            <h2><i class="fas fa-list"></i> Processos Cadastrados</h2>
            
            <?php if (count($processos) > 0): ?>
                <div class="processo-list">
                    <?php foreach ($processos as $processo): ?>
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
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($processo['responsavel_nome']); ?>
                                    </span>
                                    <span class="empresa-badge">
                                        <i class="fas fa-flag"></i>
                                        <?php echo ucfirst($processo['prioridade']); ?>
                                    </span>
                                    <span class="empresa-badge">
                                        <i class="fas fa-sync-alt"></i>
                                        <?php echo ucfirst($processo['recorrente']); ?>
                                    </span>
                                    <span class="empresa-badge">
                                        <i class="fas fa-list-check"></i>
                                        <?php echo $processo['total_checklist']; ?> etapas
                                    </span>
                                </div>
                            </div>
                            
                            <div class="actions">
                                <a href="editar-processo.php?id=<?php echo $processo['id']; ?>" class="action-btn edit-btn" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="excluir-processo.php?id=<?php echo $processo['id']; ?>" class="action-btn delete-btn" title="Excluir"
                                    onclick="return confirm('Tem certeza que deseja excluir este processo?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h3>Nenhum processo cadastrado</h3>
                    <p>Clique no botão "Novo Processo" para criar o primeiro processo pré-definido.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Adicionar Processo -->
    <div id="addProcessModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Criar Processo Pré-definido</h2>
                <button class="close-btn" onclick="fecharModal('addProcessModal')">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="salvar_processo" value="1">
                
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Informações do Processo</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="titulo" class="required">Título do Processo</label>
                            <input type="text" id="titulo" name="titulo" required 
                                   placeholder="Ex: Envio de DCTF Mensal">
                        </div>
                        
                        <div class="form-group">
                            <label for="codigo">Código</label>
                            <input type="text" id="codigo" name="codigo" readonly
                                   style="background-color: #f8f9fa;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao">Descrição do Processo</label>
                        <textarea id="descricao" name="descricao" 
                                  placeholder="Descreva detalhadamente este processo..."
                                  rows="4"></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-cog"></i> Configurações</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="categoria_id" class="required">Departamento</label>
                            <select id="categoria_id" name="categoria_id" required>
                                <option value="">Selecione o departamento...</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>">
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="responsavel_id" class="required">Responsável</label>
                            <select id="responsavel_id" name="responsavel_id" required>
                                <option value="">Selecione o responsável...</option>
                                <?php foreach ($responsaveis as $responsavel): ?>
                                    <option value="<?php echo $responsavel['id']; ?>">
                                        <?php echo htmlspecialchars($responsavel['nome_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prioridade">Prioridade</label>
                            <select id="prioridade" name="prioridade">
                                <option value="baixa">🟢 Baixa</option>
                                <option value="media" selected>🟡 Média</option>
                                <option value="alta">🔴 Alta</option>
                                <option value="urgente">⚡ Urgente</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="recorrente">Tipo de Processo</label>
                            <select id="recorrente" name="recorrente">
                                <option value="unico">📄 Processo Único</option>
                                <option value="semanal">📅 Processo Semanal</option>
                                <option value="mensal">🗓️ Processo Mensal</option>
                                <option value="trimestral">📊 Processo Trimestral</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Checklist Editável -->
                <div class="form-section">
                    <h3><i class="fas fa-list-check"></i> Checklist do Processo</h3>
                    
                    <div id="checklist-container">
                        <div class="checklist-template-item">
                            <div class="checklist-header">
                                <input type="text" name="checklist_titulo[]" 
                                       placeholder="Título da etapa" class="checklist-titulo-input" required>
                                <button type="button" class="btn-remove-checklist" onclick="removerChecklistItem(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <textarea name="checklist_descricao[]" 
                                      placeholder="Descrição detalhada desta etapa..."
                                      class="checklist-descricao-input"></textarea>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="adicionarChecklistItem()">
                        <i class="fas fa-plus"></i> Adicionar Etapa
                    </button>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('addProcessModal')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Processo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="gestao-scripts.js"></script>
    <script>
        // Definir variável global para o usuário
        window.usuarioId = <?php echo $usuario_id; ?>;
        
        // Inicializar código automático
        document.addEventListener('DOMContentLoaded', function() {
            gerarCodigoAutomatico();
        });

        // Funções do Modal
    function abrirModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function fecharModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Fechar modal clicando fora
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }

    // Funções do Checklist
    function adicionarChecklistItem() {
        const container = document.getElementById('checklist-container');
        const newItem = document.createElement('div');
        newItem.className = 'checklist-template-item';
        newItem.innerHTML = `
            <div class="checklist-header">
                <input type="text" name="checklist_titulo[]" 
                       placeholder="Título da etapa" class="checklist-titulo-input" required>
                <button type="button" class="btn-remove-checklist" onclick="removerChecklistItem(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <textarea name="checklist_descricao[]" 
                      placeholder="Descrição detalhada desta etapa..."
                      class="checklist-descricao-input"></textarea>
        `;
        container.appendChild(newItem);
    }

    function removerChecklistItem(button) {
        const item = button.closest('.checklist-template-item');
        // Não remover se for o único item
        const items = document.querySelectorAll('.checklist-template-item');
        if (items.length > 1) {
            item.remove();
        } else {
            alert('É necessário ter pelo menos uma etapa no checklist.');
        }
    }

    // Gerar código automático
    function gerarCodigoAutomatico() {
        const codigoInput = document.getElementById('codigo');
        if (codigoInput) {
            // O código é gerado no PHP, apenas exibimos "Automático"
            codigoInput.value = 'Automático (PRC-XXXX)';
        }
    }

    // Debug: Verificar se o botão está funcionando
    document.addEventListener('DOMContentLoaded', function() {
        const btnNovoProcesso = document.querySelector('button[onclick*="abrirModal"]');
        if (btnNovoProcesso) {
            btnNovoProcesso.addEventListener('click', function(e) {
                console.log('Botão Novo Processo clicado');
            });
        }
        
        // Testar função do modal
        console.log('Função abrirModal disponível:', typeof abrirModal);
        
        // Inicializar
        gerarCodigoAutomatico();
    });
    </script>
</body>
</html>