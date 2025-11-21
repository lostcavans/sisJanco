<?php
session_start();
include("config-gestao.php");

if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

if (!temPermissaoGestao('admin') && !temPermissaoGestao('analista') && !temPermissaoGestao('auxiliar')) {
    $_SESSION['erro'] = 'Você não tem permissão para editar checklists.';
    header("Location: gestao-empresas.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checklist_id = intval($_POST['checklist_id']);
    $empresa_id = intval($_POST['empresa_id']);
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $concluido = intval($_POST['concluido']);
    $data_conclusao = !empty($_POST['data_conclusao']) ? $_POST['data_conclusao'] : null;
    $imagem_atual = $_POST['imagem_atual'] ?? '';
    
    error_log("=== ATUALIZAR CHECKLIST INICIADO ===");
    error_log("Checklist ID: $checklist_id, Empresa ID: $empresa_id");
    error_log("Imagem atual: $imagem_atual");
    
    // DEBUG do upload
    if (isset($_FILES['imagem'])) {
        error_log("Arquivo recebido: " . $_FILES['imagem']['name']);
        error_log("Tamanho: " . $_FILES['imagem']['size']);
        error_log("Erro: " . $_FILES['imagem']['error']);
        error_log("Tmp: " . $_FILES['imagem']['tmp_name']);
    }
    
    // Buscar dados atuais para validar
    $sql_check = "SELECT * FROM gestao_processo_checklist WHERE id = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("i", $checklist_id);
    $stmt_check->execute();
    $checklist = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if (!$checklist) {
        $_SESSION['erro'] = 'Item do checklist não encontrado.';
        header("Location: empresa-processos.php?id=" . $empresa_id);
        exit;
    }
    
    // Processar upload de imagem - VERSÃO ROBUSTA
    $imagem_nome = $imagem_atual;
    $imagem_tipo = null;
    $imagem_tamanho = null;
    
    // Verificar se pasta de uploads existe e tem permissão
    $pasta_uploads = 'uploads/checklist-images';
    if (!is_dir($pasta_uploads)) {
        if (!mkdir($pasta_uploads, 0755, true)) {
            error_log("ERRO: Não foi possível criar a pasta $pasta_uploads");
            $_SESSION['erro'] = 'Erro ao criar pasta de uploads.';
            header("Location: empresa-processos.php?id=" . $empresa_id);
            exit;
        }
        error_log("Pasta criada: $pasta_uploads");
    }
    
    // Verificar permissão de escrita
    if (!is_writable($pasta_uploads)) {
        error_log("ERRO: Pasta $pasta_uploads não tem permissão de escrita");
        $_SESSION['erro'] = 'Pasta de uploads não tem permissão de escrita.';
        header("Location: empresa-processos.php?id=" . $empresa_id);
        exit;
    }
    
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $arquivo = $_FILES['imagem'];
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        error_log("Processando upload: {$arquivo['name']}, Extensão: $extensao");
        
        // Validar tipo de arquivo
        if (in_array($extensao, $extensoes_permitidas)) {
            // Validar tamanho (5MB máximo)
            if ($arquivo['size'] <= 5 * 1024 * 1024) {
                // Gerar nome único para o arquivo
                $imagem_nome = 'checklist_' . $checklist_id . '_' . time() . '.' . $extensao;
                $caminho_destino = $pasta_uploads . '/' . $imagem_nome;
                
                error_log("Tentando mover para: $caminho_destino");
                
                // Mover arquivo
                if (move_uploaded_file($arquivo['tmp_name'], $caminho_destino)) {
                    $imagem_tipo = $arquivo['type'];
                    $imagem_tamanho = $arquivo['size'];
                    error_log("✅ Upload bem-sucedido: $imagem_nome");
                    
                    // Remover imagem antiga se existir
                    if (!empty($imagem_atual) && $imagem_atual !== $imagem_nome) {
                        $caminho_antigo = $pasta_uploads . '/' . $imagem_atual;
                        if (file_exists($caminho_antigo)) {
                            if (unlink($caminho_antigo)) {
                                error_log("🗑️ Imagem antiga removida: $imagem_atual");
                            } else {
                                error_log("⚠️ Não foi possível remover imagem antiga: $imagem_atual");
                            }
                        }
                    }
                } else {
                    error_log("❌ Falha ao mover arquivo uploadado");
                    $_SESSION['erro'] = 'Erro ao salvar a imagem no servidor.';
                    header("Location: empresa-processos.php?id=" . $empresa_id);
                    exit;
                }
            } else {
                $_SESSION['erro'] = 'A imagem deve ter no máximo 5MB.';
                header("Location: empresa-processos.php?id=" . $empresa_id);
                exit;
            }
        } else {
            $_SESSION['erro'] = 'Formato de imagem não permitido. Use JPG, PNG ou GIF.';
            header("Location: empresa-processos.php?id=" . $empresa_id);
            exit;
        }
    } elseif (isset($_FILES['imagem'])) {
        error_log("Erro no upload: " . $_FILES['imagem']['error']);
        
        // Se o usuário tentou enviar mas deu erro (não é erro "nenhum arquivo")
        if ($_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
            switch ($_FILES['imagem']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $_SESSION['erro'] = 'Arquivo muito grande. Máximo 5MB.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $_SESSION['erro'] = 'Upload parcialmente feito.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $_SESSION['erro'] = 'Pasta temporária não encontrada.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $_SESSION['erro'] = 'Não foi possível escrever no disco.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $_SESSION['erro'] = 'Extensão não permitida.';
                    break;
                default:
                    $_SESSION['erro'] = 'Erro desconhecido no upload.';
            }
            header("Location: empresa-processos.php?id=" . $empresa_id);
            exit;
        }
    }
    
    // Se checkbox "remover imagem" foi marcado
    if (isset($_POST['remover_imagem']) && $_POST['remover_imagem'] == '1') {
        if (!empty($imagem_atual)) {
            $caminho_antigo = $pasta_uploads . '/' . $imagem_atual;
            if (file_exists($caminho_antigo)) {
                unlink($caminho_antigo);
                error_log("🗑️ Imagem removida por solicitação: $imagem_atual");
            }
        }
        $imagem_nome = null;
        $imagem_tipo = null;
        $imagem_tamanho = null;
    }
    
    error_log("Imagem final a ser salva: " . ($imagem_nome ?: 'NULL'));
    
    // Atualizar no banco de dados
    if ($imagem_nome === null) {
        $sql_update = "UPDATE gestao_processo_checklist 
                       SET titulo = ?, descricao = ?, observacao = ?, concluido = ?, 
                           data_conclusao = ?, usuario_conclusao_id = ?, imagem_nome = NULL, 
                           imagem_tipo = NULL, imagem_tamanho = NULL, updated_at = NOW()
                       WHERE id = ?";
        
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("sssisii", $titulo, $descricao, $observacao, $concluido, 
                         $data_conclusao, $usuario_id, $checklist_id);
    } else {
        $sql_update = "UPDATE gestao_processo_checklist 
                       SET titulo = ?, descricao = ?, observacao = ?, concluido = ?, 
                           data_conclusao = ?, usuario_conclusao_id = ?, imagem_nome = ?, 
                           imagem_tipo = ?, imagem_tamanho = ?, updated_at = NOW()
                       WHERE id = ?";
        
        $usuario_id = $concluido ? $_SESSION['usuario_id_gestao'] : null;
        
        $stmt = $conexao->prepare($sql_update);
        $stmt->bind_param("sssissssii", $titulo, $descricao, $observacao, $concluido, 
                         $data_conclusao, $usuario_id, $imagem_nome, $imagem_tipo, 
                         $imagem_tamanho, $checklist_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['sucesso'] = 'Item do checklist atualizado com sucesso!';
        error_log("✅ Checklist atualizado no banco");
    } else {
        $_SESSION['erro'] = 'Erro ao atualizar item: ' . $stmt->error;
        error_log("❌ Erro no UPDATE: " . $stmt->error);
    }
    $stmt->close();
    
    header("Location: empresa-processos.php?id=" . $empresa_id);
    exit;
    
} else {
    $_SESSION['erro'] = 'Método não permitido.';
    header("Location: gestao-empresas.php");
    exit;
}
?>