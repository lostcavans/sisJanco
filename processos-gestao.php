<?php
session_start();
include("config-gestao.php");

// Verificar autenticação
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];
$empresa_id = $_SESSION['empresa_id_gestao'];
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];

// Função para processar processos recorrentes (COM CONTROLE DE EXECUÇÃO)
function processarProcessosRecorrentes($conexao, $empresa_id) {
    $hoje = date('Y-m-d');
    
    // VERIFICAR SE JÁ FOI EXECUTADO HOJE (evitar dupla execução)
    $chave_execucao = "processos_recorrentes_" . $empresa_id . "_" . $hoje;
    if (isset($_SESSION[$chave_execucao])) {
        return; // Já executou hoje
    }
    
    // Buscar processos recorrentes que precisam ser executados
    $sql = "SELECT * FROM gestao_processos 
            WHERE recorrente != 'nao' 
            AND ativo = 1 
            AND (proxima_execucao IS NULL OR proxima_execucao <= ?)";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $processos_recorrentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($processos_recorrentes as $processo) {
        criarNovaInstanciaProcesso($conexao, $processo);
        atualizarProximaExecucao($conexao, $processo);
    }
    
    // MARCAR COMO EXECUTADO HOJE
    $_SESSION[$chave_execucao] = true;
}

function criarNovaInstanciaProcesso($conexao, $processo_original) {
    // VERIFICAR SE JÁ EXISTE INSTÂNCIA PARA HOJE (evitar duplicação)
    $hoje = date('Y-m-d');
    $sql_check = "SELECT id FROM gestao_processos 
                  WHERE processo_original_id = ? 
                  AND DATE(data_inicio_recorrente) = ? 
                  AND ativo = 1";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("is", $processo_original['id'], $hoje);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $stmt_check->close();
        return; // Já existe instância para hoje
    }
    $stmt_check->close();
    
    // Gerar código único para a nova instância
    $sql_count = "SELECT COUNT(*) as total FROM gestao_processos";
    $stmt = $conexao->prepare($sql_count);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'];
    $codigo = 'PRC-' . str_pad($total + 1, 4, '0', STR_PAD_LEFT);
    $stmt->close();
    
    // Calcular datas baseadas na recorrencia
    $data_prevista = calcularDataPrevistaRecorrente($processo_original['recorrente'], $hoje);
    
    // Inserir nova instância do processo
    $sql_insert = "INSERT INTO gestao_processos 
                  (codigo, titulo, descricao, categoria_id, responsavel_id, 
                   criador_id, prioridade, data_prevista, status, recorrente, 
                   processo_original_id, data_inicio_recorrente) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 'nao', ?, ?)";
    
    $stmt = $conexao->prepare($sql_insert);
    $stmt->bind_param("sssiiisssiss", 
        $codigo, 
        $processo_original['titulo'], 
        $processo_original['descricao'], 
        $processo_original['categoria_id'], 
        $processo_original['responsavel_id'], 
        $processo_original['criador_id'], 
        $processo_original['prioridade'], 
        $data_prevista,
        $processo_original['id'],
        $hoje
    );
    
    if ($stmt->execute()) {
        $novo_processo_id = $stmt->insert_id;
        
        // Copiar empresas associadas do processo original
        $sql_empresas_originais = "SELECT empresa_id FROM gestao_processo_empresas WHERE processo_id = ?";
        $stmt_empresas = $conexao->prepare($sql_empresas_originais);
        $stmt_empresas->bind_param("i", $processo_original['id']);
        $stmt_empresas->execute();
        $empresas_originais = $stmt_empresas->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_empresas->close();
        
        // Associar empresas ao novo processo
        foreach ($empresas_originais as $empresa) {
            $sql_associar = "INSERT INTO gestao_processo_empresas (processo_id, empresa_id) VALUES (?, ?)";
            $stmt_assoc = $conexao->prepare($sql_associar);
            $stmt_assoc->bind_param("ii", $novo_processo_id, $empresa['empresa_id']);
            $stmt_assoc->execute();
            $stmt_assoc->close();
        }
        
        // Registrar histórico
        $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                         VALUES (?, ?, 'criacao', 'Processo recorrente criado - " . $processo_original['recorrente'] . "')";
        $stmt_hist = $conexao->prepare($sql_historico);
        $stmt_hist->bind_param("ii", $novo_processo_id, $processo_original['criador_id']);
        $stmt_hist->execute();
        $stmt_hist->close();
        
        registrarLogGestao('CRIAR_PROCESSO_RECORRENTE', 'Processo recorrente ' . $codigo . ' criado');
    }
    $stmt->close();
}

function calcularDataPrevistaRecorrente($tipo_recorrencia, $data_inicio) {
    switch ($tipo_recorrencia) {
        case 'semanal':
            return date('Y-m-d', strtotime($data_inicio . ' +6 days'));
        case 'mensal':
            return date('Y-m-d', strtotime($data_inicio . ' +1 month'));
        case 'trimestral':
            return date('Y-m-d', strtotime($data_inicio . ' +3 months'));
        default:
            return date('Y-m-d', strtotime($data_inicio . ' +1 week'));
    }
}

function atualizarProximaExecucao($conexao, $processo) {
    $hoje = date('Y-m-d');
    
    switch ($processo['recorrente']) {
        case 'semanal':
            $proxima_execucao = date('Y-m-d', strtotime('next monday'));
            break;
        case 'mensal':
            $proxima_execucao = date('Y-m-d', strtotime('first day of next month'));
            break;
        case 'trimestral':
            $proxima_execucao = date('Y-m-d', strtotime('+3 months', strtotime($hoje)));
            break;
        default:
            $proxima_execucao = $hoje;
    }
    
    $sql_update = "UPDATE gestao_processos SET proxima_execucao = ? WHERE id = ?";
    $stmt = $conexao->prepare($sql_update);
    $stmt->bind_param("si", $proxima_execucao, $processo['id']);
    $stmt->execute();
    $stmt->close();
}

// Executar processamento de processos recorrentes (APENAS UMA VEZ)
processarProcessosRecorrentes($conexao, $empresa_id);

// Buscar responsáveis para o select
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

// Buscar TODAS las empresas disponibles - CORREGIDO
$sql_empresas = "SELECT id, razao_social, nome_fantasia, cnpj, regime_tributario, atividade 
                 FROM gestao_empresas 
                 WHERE ativo = 1 
                 ORDER BY razao_social";
$empresas = $conexao->query($sql_empresas)->fetch_all(MYSQLI_ASSOC);

// Buscar processos com informações de progresso
$sql = "SELECT p.*, u.nome_completo as responsavel_nome, c.nome as categoria_nome,
               (SELECT COUNT(*) FROM gestao_processo_empresas pe WHERE pe.processo_id = p.id) as total_empresas,
               (SELECT COUNT(*) FROM gestao_processo_checklist pc WHERE pc.processo_id = p.id AND pc.concluido = 1) as empresas_concluidas,
               (SELECT GROUP_CONCAT(e.razao_social SEPARATOR ', ') 
                FROM gestao_processo_empresas pe 
                LEFT JOIN gestao_empresas e ON pe.empresa_id = e.id 
                WHERE pe.processo_id = p.id) as empresas_nomes,
               CASE 
                   WHEN p.recorrente != 'nao' THEN CONCAT(p.titulo, ' (', p.recorrente, ')')
                   ELSE p.titulo 
               END as titulo_exibicao
        FROM gestao_processos p 
        LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
        LEFT JOIN gestao_categorias_processo c ON p.categoria_id = c.id 
        WHERE p.ativo = 1 
        ORDER BY p.recorrente != 'nao' DESC, p.created_at DESC";

$stmt = $conexao->prepare($sql);
$stmt->execute();
$processos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular progresso para cada processo
foreach ($processos as &$processo) {
    if ($processo['total_empresas'] > 0) {
        $processo['progresso'] = round(($processo['empresas_concluidas'] / $processo['total_empresas']) * 100);
    } else {
        $processo['progresso'] = 0;
    }
}
unset($processo); // Liberar a referência

// Processar formulário de adição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_processo'])) {
    $codigo = trim($_POST['codigo']);
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $categoria_id = $_POST['categoria_id'];
    $responsavel_id = $_POST['responsavel_id'];
    $prioridade = $_POST['prioridade'];
    $recorrente = $_POST['recorrente'] ?? 'nao';
    $empresas_selecionadas = $_POST['empresas'] ?? [];

    // CORREÇÃO: Tratar datas vazias
    $data_prevista = !empty($_POST['data_prevista']) ? $_POST['data_prevista'] : null;
    $data_inicio_recorrente = !empty($_POST['data_inicio_recorrente']) ? $_POST['data_inicio_recorrente'] : null;

    // Validar se pelo menos uma empresa foi selecionada
    if (empty($empresas_selecionadas)) {
        $_SESSION['erro'] = 'Selecione pelo menos uma empresa para o processo.';
        header("Location: processos-gestao.php");
        exit;
    }

    // CORREÇÃO: Calcular próxima execução se for recorrente
    $proxima_execucao = null;
    if ($recorrente != 'nao' && $data_inicio_recorrente) {
        $proxima_execucao = $data_inicio_recorrente;
    }

    // CORREÇÃO: Gerar código único UMA VEZ
    if (empty($codigo)) {
        $sql_count = "SELECT COUNT(*) as total FROM gestao_processos WHERE codigo LIKE 'PRC-%'";
        $result = $conexao->query($sql_count);
        $total = $result->fetch_assoc()['total'];
        $codigo = 'PRC-' . str_pad($total + 1, 4, '0', STR_PAD_LEFT);
    }

    // CORREÇÃO: Criar APENAS UM processo (SEM empresa_id)
    $sql_insert = "INSERT INTO gestao_processos 
                  (codigo, titulo, descricao, categoria_id, responsavel_id, 
                   criador_id, prioridade, data_prevista, status, recorrente, 
                   data_inicio_recorrente, proxima_execucao) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, ?)";
    
    $stmt = $conexao->prepare($sql_insert);
    
    if ($stmt) {
        $stmt->bind_param("sssiissssss", $codigo, $titulo, $descricao, $categoria_id, 
                         $responsavel_id, $usuario_id, $prioridade, $data_prevista,
                         $recorrente, $data_inicio_recorrente, $proxima_execucao);
        
        if ($stmt->execute()) {
            $processo_id = $stmt->insert_id;
            
            // CORREÇÃO: Associar as empresas selecionadas ao proceso na tabela de relacionamento
            foreach ($empresas_selecionadas as $empresa_id) {
                $sql_empresa = "INSERT INTO gestao_processo_empresas (processo_id, empresa_id) VALUES (?, ?)";
                $stmt_empresa = $conexao->prepare($sql_empresa);
                $stmt_empresa->bind_param("ii", $processo_id, $empresa_id);
                if ($stmt_empresa->execute()) {
                    // Sucesso ao associar empresa
                } else {
                    $_SESSION['erro'] = 'Erro ao associar empresa: ' . $stmt_empresa->error;
                    $stmt_empresa->close();
                    header("Location: processos-gestao.php");
                    exit;
                }
                $stmt_empresa->close();
            }
            
            // Registrar histórico
            $descricao_historico = $recorrente != 'nao' ? 
                "Processo recorrente ($recorrente) criado para " . count($empresas_selecionadas) . " empresa(s)" : 
                "Processo criado para " . count($empresas_selecionadas) . " empresa(s)";
                
            $sql_historico = "INSERT INTO gestao_historicos_processo (processo_id, usuario_id, acao, descricao) 
                             VALUES (?, ?, 'criacao', ?)";
            $stmt_hist = $conexao->prepare($sql_historico);
            $stmt_hist->bind_param("iis", $processo_id, $usuario_id, $descricao_historico);
            $stmt_hist->execute();
            $stmt_hist->close();
            
            registrarLogGestao('CRIAR_PROCESSO', 'Processo ' . $codigo . ' criado para ' . count($empresas_selecionadas) . ' empresa(s)');
            
            $_SESSION['sucesso'] = 'Processo criado com sucesso para ' . count($empresas_selecionadas) . ' empresa(s)!' . 
                                  ($recorrente != 'nao' ? " (Recorrente: $recorrente)" : "");
            header("Location: processos-gestao.php");
            exit;
        } else {
            $_SESSION['erro'] = 'Erro ao criar processo: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['erro'] = 'Erro ao preparar statement: ' . $conexao->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processos - Gestão de Processos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* RESET E VARIÁVEIS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #eef2ff;
            --secondary: #7209b7;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #adb5bd;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }

        /* NAVBAR */
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
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: background-color 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        /* CONTAINER E HEADER */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
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

        /* BOTÕES */
        .btn {
            padding: 12px 24px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 1rem;
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
            background-color: #5a6268;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        /* CARD */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--light);
            padding: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .card-body {
            padding: 0;
        }

        .process-item {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            align-items: center;
        }

        .process-item:last-child {
            border-bottom: none;
        }

        /* BADGES */
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-rascunho { background: #f8f9fa; color: #6c757d; border: 1px solid #6c757d; }
        .status-pendente { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-em_andamento { background: #cce7ff; color: #004085; border: 1px solid #b3d7ff; }
        .status-concluido { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-cancelado { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-pausado { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
        }

        .priority-baixa { background: #d4edda; color: #155724; }
        .priority-media { background: #cce7ff; color: #004085; }
        .priority-alta { background: #fff3cd; color: #856404; }
        .priority-urgente { background: #f8d7da; color: #721c24; }

        .user-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 12px;
            font-size: 0.7rem;
            margin-left: 5px;
        }

        /* BARRA DE PROGRESSO */
        .progress-container {
            width: 100%;
        }

        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .progress-fill {
            background: linear-gradient(90deg, var(--success), #34d399);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-text {
            font-size: 0.7rem;
            color: #666;
            text-align: center;
        }

        /* AÇÕES */
        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 6px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            background: transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .edit-btn { color: var(--primary); }
        .delete-btn { color: #dc3545; }
        .view-btn { color: var(--gray); }
        .checklist-btn { color: #10b981; }

        .action-btn:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        /* MODAL - MEJORADO */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.show {
            display: flex !important;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 900px;
            max-height: 95vh;
            overflow-y: auto;
            z-index: 10001;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from { 
                opacity: 0; 
                transform: translateY(-50px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            background: linear-gradient(135deg, var(--primary-light), #ffffff);
            border-radius: 12px 12px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-header-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-icon {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(0, 0, 0, 0.1);
            color: var(--danger);
        }

        /* FORMULÁRIO - MEJORADO */
        .form-container {
            padding: 0;
        }

        .form-section {
            background: var(--white);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .required::after {
            content: " *";
            color: var(--danger);
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            transform: translateY(-1px);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* SELECT MEJORADO */
        .select-wrapper {
            position: relative;
        }

        .select-arrow {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        select {
            appearance: none;
            padding-right: 40px !important;
        }

        /* CONTADOR DE CARACTERES */
        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 4px;
        }

        /* EMPRESAS - MEJORADO */
        .empresas-section {
            margin-top: 1rem;
        }

        .controles-empresas {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .controles-left, .controles-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-control {
            padding: 8px 16px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-control:hover {
            background: var(--primary);
            color: var(--white);
        }

        .empresa-counter {
            background: var(--primary-light);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* BUSCADOR DE EMPRESAS */
        .empresas-search {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-empresas {
            padding-left: 40px !important;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .empresas-container {
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            background: var(--light);
        }

        .empresa-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .empresa-item:hover {
            background: rgba(67, 97, 238, 0.05);
            transform: translateX(4px);
        }

        .empresa-item.selected {
            background: rgba(67, 97, 238, 0.1);
            border-left: 4px solid var(--primary);
        }

        .empresa-checkbox {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .empresa-info {
            flex: 1;
            cursor: pointer;
        }

        .empresa-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .empresa-nome {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
            font-size: 1rem;
        }

        .nome-fantasia {
            color: var(--gray);
            font-style: italic;
            font-size: 0.9rem;
        }

        .empresa-detalhes {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empresa-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: rgba(114, 9, 183, 0.1);
            color: var(--secondary);
            border-radius: 12px;
            font-size: 0.7rem;
        }

        /* CHECKBOX PERSONALIZADO */
        .checkbox-custom {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-light);
            border-radius: 4px;
            display: inline-block;
            position: relative;
            transition: all 0.3s ease;
        }

        .empresa-checkbox:checked + .empresa-info .checkbox-custom {
            background: var(--primary);
            border-color: var(--primary);
        }

        .empresa-checkbox:checked + .empresa-info .checkbox-custom::after {
            content: "✓";
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            font-weight: bold;
        }

        /* RECURRENCIA */
        .recorrencia-info {
            margin-top: 1rem;
        }

        .info-card {
            background: var(--primary-light);
            border: 1px solid var(--primary);
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .info-card i {
            color: var(--primary);
            margin-top: 2px;
        }

        .info-content ul {
            margin: 0;
            padding-left: 1rem;
        }

        .info-content li {
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        /* RESUMEN */
        .form-summary {
            background: var(--light);
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem;
        }

        .summary-card h4 {
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-weight: 500;
            color: var(--dark);
        }

        .summary-value {
            font-weight: 600;
            color: var(--primary);
        }

        /* ACCIONES */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid var(--gray-light);
            background: var(--light);
            border-radius: 0 0 12px 12px;
        }

        /* ALERTAS */
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

        /* HELPERS */
        .field-help {
            color: var(--gray);
            font-size: 0.8rem;
            margin-top: 4px;
            display: block;
        }

        .field-warning {
            color: var(--warning);
            font-size: 0.8rem;
            margin-top: 4px;
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }

        /* RESPONSIVIDADE */
        @media (max-width: 768px) {
            .card-header, .process-item {
                grid-template-columns: 1fr;
                gap: 0.5rem;
                text-align: center;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                justify-content: center;
            }
            
            .controles-empresas {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .controles-left, .controles-right {
                justify-content: center;
            }
            
            .modal-content {
                margin: 0;
                width: 100%;
                max-height: 100vh;
                border-radius: 0;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .form-section {
                padding: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* SCROLLBAR PERSONALIZADO */
        .empresas-container::-webkit-scrollbar {
            width: 6px;
        }

        .empresas-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .empresas-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .empresas-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
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
            <li><a href="processos-gestao.php" class="nav-link active">Processos</a></li>
            <li><a href="documentacoes-empresas.php" class="nav-link">Documentações</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Processos Cadastrados</h1>
            <button id="addProcessBtn" class="btn">
                <i class="fas fa-plus"></i> Novo Processo
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
                <div>Código</div>
                <div>Processo</div>
                <div>Empresas</div>
                <div>Progresso</div>
                <div>Categoria</div>
                <div>Responsável</div>
                <div>Status</div>
                <div>Ações</div>
            </div>

            <div class="card-body">
                <?php if (count($processos) > 0): ?>
                    <?php foreach ($processos as $processo): ?>
                        <div class="process-item">
                            <div><?php echo htmlspecialchars($processo['codigo']); ?></div>
                            <div>
                                <strong><?php echo htmlspecialchars($processo['titulo_exibicao']); ?></strong>
                                <?php if ($processo['recorrente'] != 'nao'): ?>
                                    <br><small style="color: #4361ee; font-weight: 500;">
                                        <i class="fas fa-sync-alt"></i> Recorrente
                                        <?php if ($processo['proxima_execucao']): ?>
                                            | Próxima: <?php echo date('d/m/Y', strtotime($processo['proxima_execucao'])); ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($processo['descricao']): ?>
                                    <br><small style="color: #666;"><?php echo substr(htmlspecialchars($processo['descricao']), 0, 50); ?>...</small>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong><?php echo $processo['total_empresas']; ?> empresa(s)</strong>
                                <?php if ($processo['empresas_nomes']): ?>
                                    <br><small style="color: #666;">
                                        <?php 
                                        $empresas = explode(', ', $processo['empresas_nomes']);
                                        if (count($empresas) > 2) {
                                            echo htmlspecialchars($empresas[0]) . ', ' . htmlspecialchars($empresas[1]) . '...';
                                        } else {
                                            echo htmlspecialchars($processo['empresas_nomes']);
                                        }
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="progress-container">
                                <div style="font-weight: 600; color: #4361ee; margin-bottom: 5px; font-size: 0.9rem;">
                                    <?php echo $processo['progresso']; ?>%
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $processo['progresso']; ?>%;"></div>
                                </div>
                                <div class="progress-text">
                                    <?php echo $processo['empresas_concluidas']; ?>/<?php echo $processo['total_empresas']; ?> concluídas
                                </div>
                            </div>
                            <div><?php echo htmlspecialchars($processo['categoria_nome'] ?? '-'); ?></div>
                            <div><?php echo htmlspecialchars($processo['responsavel_nome']); ?></div>
                            <div>
                                <span class="status-badge status-<?php echo $processo['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'rascunho' => 'Rascunho',
                                        'pendente' => 'Pendente',
                                        'em_andamento' => 'Em Andamento',
                                        'concluido' => 'Concluído',
                                        'cancelado' => 'Cancelado',
                                        'pausado' => 'Pausado'
                                    ];
                                    echo $status_labels[$processo['status']] ?? $processo['status'];
                                    ?>
                                </span>
                                <br>
                                <small class="priority-badge priority-<?php echo $processo['prioridade']; ?>">
                                    <?php echo ucfirst($processo['prioridade']); ?>
                                </small>
                            </div>
                            <div class="actions">
                                <a href="checklist-processo.php?id=<?php echo $processo['id']; ?>" class="action-btn checklist-btn" title="Checklist">
                                    <i class="fas fa-list-check"></i>
                                </a>
                                <a href="detalhes-processo.php?id=<?php echo $processo['id']; ?>" class="action-btn view-btn" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </a>
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
                <?php else: ?>
                    <div class="process-item" style="display: block; text-align: center; padding: 3rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #666; margin-bottom: 1rem;"></i>
                        <p style="color: #666;">Nenhum processo cadastrado</p>
                        <button id="addProcessBtnEmpty" class="btn" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Criar Primeiro Processo
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal Adicionar Processo - MEJORADO -->
        <div id="addProcessModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-header-content">
                        <i class="fas fa-plus-circle modal-icon"></i>
                        <h2 class="modal-title">Criar Novo Processo</h2>
                    </div>
                    <button class="close-btn" title="Fechar">&times;</button>
                </div>
                
                <form method="POST" action="" id="processForm">
                    <input type="hidden" name="adicionar_processo" value="1">
                    
                    <div class="form-container">
                        <!-- SECCIÓN PRINCIPAL -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Informações Básicas
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="titulo" class="required">Título do Processo</label>
                                    <input type="text" id="titulo" name="titulo" required 
                                           placeholder="Ex: Envio de Declarações Mensais"
                                           maxlength="255">
                                    <div class="char-counter" id="tituloCounter">0/255</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="codigo">Código</label>
                                    <input type="text" id="codigo" name="codigo" 
                                           placeholder="Será gerado automaticamente"
                                           readonly
                                           style="background-color: #f8f9fa;">
                                    <small class="field-help">Código único gerado automaticamente</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="descricao">Descrição</label>
                                <textarea id="descricao" name="descricao" 
                                          placeholder="Descreva detalhadamente o processo, objetivos e observações importantes..."
                                          rows="4"
                                          maxlength="1000"></textarea>
                                <div class="char-counter" id="descricaoCounter">0/1000</div>
                            </div>
                        </div>

                        <!-- SECCIÓN DE CONFIGURACIÓN -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-cog"></i>
                                Configurações
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="categoria_id">Categoria</label>
                                    <div class="select-wrapper">
                                        <select id="categoria_id" name="categoria_id">
                                            <option value="">Selecione uma categoria...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?php echo $categoria['id']; ?>">
                                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fas fa-chevron-down select-arrow"></i>
                                    </div>
                                    <?php if (empty($categorias)): ?>
                                        <small class="field-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            Nenhuma categoria disponível
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="responsavel_id" class="required">Responsável</label>
                                    <div class="select-wrapper">
                                        <select id="responsavel_id" name="responsavel_id" required>
                                            <option value="">Selecione o responsável...</option>
                                            <?php foreach ($responsaveis as $responsavel): ?>
                                                <option value="<?php echo $responsavel['id']; ?>">
                                                    <?php echo htmlspecialchars($responsavel['nome_completo']); ?>
                                                    <span class="user-badge"><?php echo $responsavel['nivel_acesso']; ?></span>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fas fa-chevron-down select-arrow"></i>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="prioridade">Prioridade</label>
                                    <div class="select-wrapper">
                                        <select id="prioridade" name="prioridade">
                                            <option value="baixa">🟢 Baixa</option>
                                            <option value="media" selected>🟡 Média</option>
                                            <option value="alta">🔴 Alta</option>
                                            <option value="urgente">⚡ Urgente</option>
                                        </select>
                                        <i class="fas fa-chevron-down select-arrow"></i>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="data_prevista">Data Prevista</label>
                                    <div class="date-input-wrapper">
                                        <input type="date" id="data_prevista" name="data_prevista"
                                               min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN DE EMPRESAS - MEJORADA -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-building"></i>
                                Empresas Associadas
                                <span class="required-badge">*</span>
                            </h3>
                            
                            <div class="empresas-section">
                                <div class="controles-empresas">
                                    <div class="controles-left">
                                        <button type="button" class="btn-control" onclick="selecionarTodasEmpresas()">
                                            <i class="fas fa-check-double"></i> Selecionar Todas
                                        </button>
                                        <button type="button" class="btn-control" onclick="limparSelecaoEmpresas()">
                                            <i class="fas fa-times"></i> Limpar Seleção
                                        </button>
                                    </div>
                                    <div class="controles-right">
                                        <span id="contador-empresas" class="empresa-counter">
                                            <i class="fas fa-building"></i>
                                            <span id="contador-numero">0</span> empresa(s) selecionada(s)
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="empresas-search">
                                    <input type="text" id="empresaSearch" 
                                           placeholder="Pesquisar empresas por nome ou CNPJ..."
                                           class="search-empresas">
                                    <i class="fas fa-search search-icon"></i>
                                </div>
                                
                                <div class="empresas-container" id="empresasList">
                                    <?php if (count($empresas) > 0): ?>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <div class="empresa-item" data-empresa-id="<?php echo $empresa['id']; ?>"
                                                 data-nome="<?php echo htmlspecialchars(strtolower($empresa['razao_social'])); ?>"
                                                 data-cnpj="<?php echo htmlspecialchars($empresa['cnpj']); ?>">
                                                <input type="checkbox" name="empresas[]" 
                                                       value="<?php echo $empresa['id']; ?>" 
                                                       id="empresa_<?php echo $empresa['id']; ?>" 
                                                       class="empresa-checkbox">
                                                <label for="empresa_<?php echo $empresa['id']; ?>" class="empresa-info">
                                                    <div class="empresa-header">
                                                        <div class="empresa-nome">
                                                            <?php echo htmlspecialchars($empresa['razao_social']); ?>
                                                            <?php if (!empty($empresa['nome_fantasia'])): ?>
                                                                <small class="nome-fantasia">
                                                                    (<?php echo htmlspecialchars($empresa['nome_fantasia']); ?>)
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="empresa-status">
                                                            <span class="checkbox-custom"></span>
                                                        </div>
                                                    </div>
                                                    <div class="empresa-detalhes">
                                                        <span class="empresa-badge">
                                                            <i class="fas fa-id-card"></i>
                                                            <?php echo htmlspecialchars($empresa['cnpj']); ?>
                                                        </span>
                                                        <span class="empresa-badge">
                                                            <i class="fas fa-receipt"></i>
                                                            <?php echo htmlspecialchars($empresa['regime_tributario']); ?>
                                                        </span>
                                                        <span class="empresa-badge">
                                                            <i class="fas fa-industry"></i>
                                                            <?php echo htmlspecialchars($empresa['atividade']); ?>
                                                        </span>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-building" style="font-size: 3rem; color: #6b7280; margin-bottom: 1rem;"></i>
                                            <p style="color: #6b7280; text-align: center;">
                                                Nenhuma empresa cadastrada no sistema.
                                            </p>
                                            <small style="color: #9ca3af; text-align: center;">
                                                É necessário cadastrar empresas antes de criar processos.
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN DE RECORRENCIA - MEJORADA -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-sync-alt"></i>
                                Configuração de Recorrência
                            </h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="recorrente">Tipo de Processo</label>
                                    <div class="select-wrapper">
                                        <select id="recorrente" name="recorrente">
                                            <option value="nao">📄 Processo Único</option>
                                            <option value="semanal">📅 Processo Semanal</option>
                                            <option value="mensal">🗓️ Processo Mensal</option>
                                            <option value="trimestral">📊 Processo Trimestral</option>
                                        </select>
                                        <i class="fas fa-chevron-down select-arrow"></i>
                                    </div>
                                </div>
                                
                                <div class="form-group" id="data_inicio_container" style="display: none;">
                                    <label for="data_inicio_recorrente">Data de Início da Recorrência</label>
                                    <div class="date-input-wrapper">
                                        <input type="date" id="data_inicio_recorrente" name="data_inicio_recorrente"
                                               min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <small class="field-help" id="recorrenciaHelp">
                                        Para processos únicos, este campo será ignorado
                                    </small>
                                </div>
                            </div>
                            
                            <div class="recorrencia-info" id="recorrenciaInfo" style="display: none;">
                                <div class="info-card">
                                    <i class="fas fa-info-circle"></i>
                                    <div class="info-content">
                                        <strong>Como funciona a recorrência:</strong>
                                        <ul>
                                            <li><strong>Semanal:</strong> Processo criado toda segunda-feira</li>
                                            <li><strong>Mensal:</strong> Processo criado no primeiro dia de cada mês</li>
                                            <li><strong>Trimestral:</strong> Processo criado a cada 3 meses</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RESUMEN Y VALIDACIÓN -->
                        <div class="form-summary" id="formSummary">
                            <div class="summary-card">
                                <h4>
                                    <i class="fas fa-clipboard-check"></i>
                                    Resumo do Processo
                                </h4>
                                <div class="summary-content">
                                    <div class="summary-item">
                                        <span class="summary-label">Empresas selecionadas:</span>
                                        <span class="summary-value" id="summaryEmpresas">0</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Tipo:</span>
                                        <span class="summary-value" id="summaryTipo">Único</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Prioridade:</span>
                                        <span class="summary-value" id="summaryPrioridade">Média</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACCIONES DEL FORMULARIO -->
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" id="cancelAddProcess">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitProcess">
                                <i class="fas fa-save"></i> Criar Processo
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        inicializarModal();
        inicializarFormulario();
        inicializarEmpresas();
        inicializarRecorrencia();
    });

    function inicializarModal() {
        const modal = document.getElementById('addProcessModal');
        const openBtns = document.querySelectorAll('#addProcessBtn, #addProcessBtnEmpty');
        const closeBtn = modal.querySelector('.close-btn');
        const cancelBtn = document.getElementById('cancelAddProcess');

        // Abrir modal
        openBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                abrirModal();
            });
        });

        // Cerrar modal
        [closeBtn, cancelBtn].forEach(btn => {
            if (btn) btn.addEventListener('click', cerrarModal);
        });

        // Cerrar al hacer clic fuera
        modal.addEventListener('click', (e) => {
            if (e.target === modal) cerrarModal();
        });

        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                cerrarModal();
            }
        });
    }

    function inicializarFormulario() {
        const form = document.getElementById('processForm');
        const tituloInput = document.getElementById('titulo');
        const descricaoInput = document.getElementById('descricao');
        
        // Contador de caracteres
        if (tituloInput) {
            tituloInput.addEventListener('input', () => {
                document.getElementById('tituloCounter').textContent = 
                    `${tituloInput.value.length}/255`;
            });
        }
        
        if (descricaoInput) {
            descricaoInput.addEventListener('input', () => {
                document.getElementById('descricaoCounter').textContent = 
                    `${descricaoInput.value.length}/1000`;
            });
        }
        
        // Generar código automático
        generarCodigoAutomatico();
        
        // Validación antes de enviar
        form.addEventListener('submit', validarFormulario);
        
        // Actualizar resumen en tiempo real
        actualizarResumen();
    }

    function inicializarEmpresas() {
        const searchInput = document.getElementById('empresaSearch');
        const checkboxes = document.querySelectorAll('.empresa-checkbox');
        
        // Búsqueda en tiempo real
        if (searchInput) {
            searchInput.addEventListener('input', filtrarEmpresas);
        }
        
        // Actualizar contador al cambiar selección
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                atualizarContadorEmpresas();
                actualizarResumen();
            });
        });
        
        // Selección con clic en el item
        document.querySelectorAll('.empresa-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (!e.target.matches('input, label, .empresa-badge')) {
                    const checkbox = item.querySelector('.empresa-checkbox');
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
        });
        
        atualizarContadorEmpresas();
    }

    function inicializarRecorrencia() {
        const selectRecorrente = document.getElementById('recorrente');
        const dataInicioContainer = document.getElementById('data_inicio_container');
        const recorrenciaInfo = document.getElementById('recorrenciaInfo');
        
        if (selectRecorrente) {
            selectRecorrente.addEventListener('change', function() {
                const isRecorrente = this.value !== 'nao';
                dataInicioContainer.style.display = isRecorrente ? 'block' : 'none';
                recorrenciaInfo.style.display = isRecorrente ? 'block' : 'none';
                actualizarResumen();
            });
        }
    }

    // Funciones de utilidad
    function abrirModal() {
        const modal = document.getElementById('addProcessModal');
        modal.style.display = 'flex';
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        document.getElementById('titulo').focus();
    }

    function cerrarModal() {
        const modal = document.getElementById('addProcessModal');
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    function selecionarTodasEmpresas() {
        document.querySelectorAll('.empresa-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
        atualizarContadorEmpresas();
        actualizarResumen();
    }

    function limparSelecaoEmpresas() {
        document.querySelectorAll('.empresa-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        atualizarContadorEmpresas();
        actualizarResumen();
    }

    function filtrarEmpresas() {
        const searchTerm = document.getElementById('empresaSearch').value.toLowerCase();
        const empresas = document.querySelectorAll('.empresa-item');
        
        empresas.forEach(empresa => {
            const nome = empresa.getAttribute('data-nome');
            const cnpj = empresa.getAttribute('data-cnpj');
            const matches = nome.includes(searchTerm) || cnpj.includes(searchTerm);
            empresa.style.display = matches ? 'flex' : 'none';
        });
    }

    function atualizarContadorEmpresas() {
        const selecionadas = document.querySelectorAll('.empresa-checkbox:checked').length;
        const contador = document.getElementById('contador-numero');
        if (contador) contador.textContent = selecionadas;
    }

    function generarCodigoAutomatico() {
        const codigoInput = document.getElementById('codigo');
        if (codigoInput) {
            const timestamp = new Date().getTime().toString().slice(-4);
            codigoInput.value = `PRC-${timestamp}`;
        }
    }

    function actualizarResumen() {
        const empresasSelecionadas = document.querySelectorAll('.empresa-checkbox:checked').length;
        const tipoProcesso = document.getElementById('recorrente').value;
        const prioridade = document.getElementById('prioridade').value;
        
        document.getElementById('summaryEmpresas').textContent = empresasSelecionadas;
        document.getElementById('summaryTipo').textContent = 
            tipoProcesso === 'nao' ? 'Único' : tipoProcesso.charAt(0).toUpperCase() + tipoProcesso.slice(1);
        document.getElementById('summaryPrioridade').textContent = 
            prioridade.charAt(0).toUpperCase() + prioridade.slice(1);
    }

    function validarFormulario(e) {
        const empresasSelecionadas = document.querySelectorAll('.empresa-checkbox:checked').length;
        
        if (empresasSelecionadas === 0) {
            e.preventDefault();
            alert('Por favor, selecione pelo menos uma empresa para o processo.');
            document.querySelector('.empresas-section').scrollIntoView({ 
                behavior: 'smooth',
                block: 'center'
            });
            return false;
        }
        
        return true;
    }
    </script>
</body>
</html>