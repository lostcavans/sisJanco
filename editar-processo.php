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
    $_SESSION['erro'] = 'Você não tem permissão para editar processos.';
    header("Location: processos-gestao.php");
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

/// Buscar checklist do processo da NOVA tabela
$sql_checklist = "SELECT * FROM gestao_processo_predefinido_checklist WHERE processo_id = ? ORDER BY ordem, id";
$stmt = $conexao->prepare($sql_checklist);
$stmt->bind_param("i", $processo_id);
$stmt->execute();
$checklist_itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

// Processar formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_processo'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao'] ?? '');
    $categoria_id = intval($_POST['categoria_id']);
    $responsavel_id = intval($_POST['responsavel_id']);
    $prioridade = $_POST['prioridade'];
    $recorrente = $_POST['recorrente'];
    $status = $_POST['status'];
    
    // Iniciar transação
    $conexao->begin_transaction();
    
    try {
        // Atualizar dados do processo
        $sql_update = "UPDATE gestao_processos 
                      SET titulo = ?, descricao = ?, categoria_id = ?, 
                          responsavel_id = ?, prioridade = ?, recorrente = ?, status = ?,
                          updated_at = NOW()
                      WHERE id = ?";
        
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("ssiisssi", $titulo, $descricao, $categoria_id, $responsavel_id, 
                         $prioridade, $recorrente, $status, $processo_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar processo: ' . $stmt->error);
        }
        $stmt->close();
        
        // Processar checklist existente
        if (isset($_POST['checklist_id']) && is_array($_POST['checklist_id'])) {
            foreach ($_POST['checklist_id'] as $index => $checklist_id) {
                $titulo_item = trim($_POST['checklist_titulo'][$index] ?? '');
                $descricao_item = trim($_POST['checklist_descricao'][$index] ?? '');
                $ordem = $index + 1;
                
                if (!empty($checklist_id) && !empty($titulo_item)) {
                    // Atualizar item existente na NOVA tabela
                    $sql_update_checklist = "UPDATE gestao_processo_predefinido_checklist 
                                            SET titulo = ?, descricao = ?, ordem = ?, updated_at = NOW()
                                            WHERE id = ? AND processo_id = ?";
                    
                    $stmt_checklist = $conexao->prepare($sql_update_checklist);
                    $stmt_checklist->bind_param("ssiii", $titulo_item, $descricao_item, $ordem, $checklist_id, $processo_id);
                    
                    if (!$stmt_checklist->execute()) {
                        throw new Exception('Erro ao atualizar checklist: ' . $stmt_checklist->error);
                    }
                    $stmt_checklist->close();
                }
            }
        }
        
        // Processar NOVOS itens do checklist
        if (isset($_POST['novo_checklist_titulo']) && is_array($_POST['novo_checklist_titulo'])) {
            foreach ($_POST['novo_checklist_titulo'] as $index => $titulo_item) {
                $descricao_item = trim($_POST['novo_checklist_descricao'][$index] ?? '');
                $titulo_item = trim($titulo_item);
                $ordem = count($checklist_itens) + $index + 1;
                
                if (!empty($titulo_item)) {
                    // Inserir na NOVA tabela
                    $sql_insert_checklist = "INSERT INTO gestao_processo_predefinido_checklist 
                                            (processo_id, titulo, descricao, ordem, created_at, updated_at)
                                            VALUES (?, ?, ?, ?, NOW(), NOW())";
                    
                    $stmt_checklist = $conexao->prepare($sql_insert_checklist);
                    $stmt_checklist->bind_param("issi", $processo_id, $titulo_item, $descricao_item, $ordem);
                    
                    if (!$stmt_checklist->execute()) {
                        throw new Exception('Erro ao inserir novo item no checklist: ' . $stmt_checklist->error);
                    }
                    $stmt_checklist->close();
                }
            }
        }
        
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                         VALUES (?, ?, 'atualizacao', 'Processo pré-definido atualizado')";
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $processo_id, $usuario_id);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        // Commit da transação
        $conexao->commit();
        
        $_SESSION['sucesso'] = 'Processo atualizado com sucesso!';
        header("Location: processos-gestao.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        $conexao->rollback();
        $_SESSION['erro'] = $e->getMessage();
        header("Location: editar-processo.php?id=" . $processo_id);
        exit;
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
            <h1 class="page-title">Editar Processo</h1>
            <a href="processos-gestao.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
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

        <form method="POST" action="">
            <input type="hidden" name="editar_processo" value="1">
            
            <!-- Informações do Processo -->
            <div class="form-section fade-in">
                <h2><i class="fas fa-info-circle"></i> Informações do Processo</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="titulo" class="required">Título do Processo</label>
                        <input type="text" id="titulo" name="titulo" required 
                               value="<?php echo htmlspecialchars($processo['titulo']); ?>"
                               placeholder="Ex: Envio de DCTF Mensal">
                    </div>
                    
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" id="codigo" value="<?php echo htmlspecialchars($processo['codigo']); ?>" readonly
                               style="background-color: #f8f9fa;">
                        <small class="field-help">Código não pode ser alterado</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição do Processo</label>
                    <textarea id="descricao" name="descricao" 
                              placeholder="Descreva detalhadamente este processo..."
                              rows="4"><?php echo htmlspecialchars($processo['descricao']); ?></textarea>
                </div>
            </div>

            <!-- Configurações -->
            <div class="form-section fade-in">
                <h2><i class="fas fa-cog"></i> Configurações</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="categoria_id" class="required">Departamento</label>
                        <select id="categoria_id" name="categoria_id" required>
                            <option value="">Selecione o departamento...</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo $categoria['id'] == $processo['categoria_id'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $responsavel['id']; ?>" 
                                    <?php echo $responsavel['id'] == $processo['responsavel_id'] ? 'selected' : ''; ?>>
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
                            <option value="baixa" <?php echo $processo['prioridade'] == 'baixa' ? 'selected' : ''; ?>>🟢 Baixa</option>
                            <option value="media" <?php echo $processo['prioridade'] == 'media' ? 'selected' : ''; ?>>🟡 Média</option>
                            <option value="alta" <?php echo $processo['prioridade'] == 'alta' ? 'selected' : ''; ?>>🔴 Alta</option>
                            <option value="urgente" <?php echo $processo['prioridade'] == 'urgente' ? 'selected' : ''; ?>>⚡ Urgente</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="recorrente">Tipo de Processo</label>
                        <select id="recorrente" name="recorrente">
                            <option value="unico" <?php echo $processo['recorrente'] == 'unico' ? 'selected' : ''; ?>>📄 Processo Único</option>
                            <option value="semanal" <?php echo $processo['recorrente'] == 'semanal' ? 'selected' : ''; ?>>📅 Processo Semanal</option>
                            <option value="mensal" <?php echo $processo['recorrente'] == 'mensal' ? 'selected' : ''; ?>>🗓️ Processo Mensal</option>
                            <option value="trimestral" <?php echo $processo['recorrente'] == 'trimestral' ? 'selected' : ''; ?>>📊 Processo Trimestral</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">Status do Processo</label>
                    <select id="status" name="status">
                        <option value="ativo" <?php echo $processo['status'] == 'ativo' ? 'selected' : ''; ?>>✅ Ativo</option>
                        <option value="inativo" <?php echo $processo['status'] == 'inativo' ? 'selected' : ''; ?>>⏸️ Inativo</option>
                        <option value="pausado" <?php echo $processo['status'] == 'pausado' ? 'selected' : ''; ?>>⏸️ Pausado</option>
                    </select>
                </div>
            </div>

            <!-- Checklist Editável -->
            <div class="form-section fade-in">
                <h2><i class="fas fa-list-check"></i> Checklist do Processo</h2>
                
                <div id="checklist-container">
                    <?php if (count($checklist_itens) > 0): ?>
                        <?php foreach ($checklist_itens as $item): ?>
                            <div class="checklist-template-item">
                                <input type="hidden" name="checklist_id[]" value="<?php echo $item['id']; ?>">
                                <div class="checklist-header">
                                    <input type="text" name="checklist_titulo[]" 
                                        value="<?php echo htmlspecialchars($item['titulo']); ?>"
                                        placeholder="Título da etapa" class="checklist-titulo-input" required>
                                    <button type="button" class="btn-remove-checklist" onclick="removerChecklistItem(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <textarea name="checklist_descricao[]" 
                                        placeholder="Descrição detalhada desta etapa..."
                                        class="checklist-descricao-input"><?php echo htmlspecialchars($item['descricao']); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Item padrão quando não há checklist -->
                        <div class="checklist-template-item">
                            <input type="hidden" name="checklist_id[]" value="">
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
                    <?php endif; ?>
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="adicionarChecklistItem()">
                    <i class="fas fa-plus"></i> Adicionar Etapa
                </button>
            </div>

            <div class="form-actions">
                <a href="processos-gestao.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Atualizar Processo
                </button>
            </div>
        </form>
    </main>

    <script src="gestao-scripts.js"></script>
    <script>
        // Função para adicionar novo item de checklist
        function adicionarChecklistItem() {
            const container = document.getElementById('checklist-container');
            const newIndex = container.querySelectorAll('.checklist-template-item').length;
            
            const newItem = document.createElement('div');
            newItem.className = 'checklist-template-item';
            newItem.innerHTML = `
                <input type="hidden" name="novo_checklist_id[]" value="">
                <div class="checklist-header">
                    <input type="text" name="novo_checklist_titulo[]" 
                        placeholder="Título da etapa" class="checklist-titulo-input" required>
                    <button type="button" class="btn-remove-checklist" onclick="removerChecklistItem(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <textarea name="novo_checklist_descricao[]" 
                        placeholder="Descrição detalhada desta etapa..."
                        class="checklist-descricao-input"></textarea>
            `;
            
            container.appendChild(newItem);
        }

        // Função para remover item de checklist
        function removerChecklistItem(button) {
            const item = button.closest('.checklist-template-item');
            const container = document.getElementById('checklist-container');
            const items = container.querySelectorAll('.checklist-template-item');
            
            if (items.length > 1) {
                item.remove();
            } else {
                alert('O processo deve ter pelo menos uma etapa no checklist.');
            }
        }

        // Validação do formulário
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                const tituloInput = document.getElementById('titulo');
                const categoriaSelect = document.getElementById('categoria_id');
                const responsavelSelect = document.getElementById('responsavel_id');
                
                let isValid = true;
                
                // Validar campos obrigatórios
                if (!tituloInput.value.trim()) {
                    alert('O título do processo é obrigatório.');
                    tituloInput.focus();
                    isValid = false;
                } else if (!categoriaSelect.value) {
                    alert('Selecione um departamento.');
                    categoriaSelect.focus();
                    isValid = false;
                } else if (!responsavelSelect.value) {
                    alert('Selecione um responsável.');
                    responsavelSelect.focus();
                    isValid = false;
                }
                
                // Validar checklist
                const checklistTitulos = document.querySelectorAll('.checklist-titulo-input');
                let checklistValido = true;
                
                checklistTitulos.forEach(input => {
                    if (!input.value.trim()) {
                        checklistValido = false;
                    }
                });
                
                if (!checklistValido) {
                    alert('Todos os itens do checklist devem ter um título.');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>