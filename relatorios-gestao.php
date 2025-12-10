<?php
session_start();
include("config-gestao.php");

// Verificar autenticação
if (!verificarAutenticacaoGestao()) {
    header("Location: login-gestao.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id_gestao'];
$nivel_usuario = $_SESSION['usuario_nivel_gestao'];

// Buscar estatísticas gerais
if (temPermissaoGestao('analista')) {
    // Analistas veem tudo
    $sql_total_processos = "SELECT COUNT(*) as total FROM gestao_processos WHERE ativo = 1";
    $sql_processos_status = "SELECT status, COUNT(*) as total FROM gestao_processos WHERE ativo = 1 GROUP BY status";
    $sql_empresas = "SELECT COUNT(*) as total FROM empresas WHERE ativo = 1";
    $sql_usuarios = "SELECT COUNT(*) as total FROM gestao_usuarios WHERE ativo = 1";
    $sql_documentos = "SELECT COUNT(*) as total FROM gestao_documentacoes_empresa";
    
    // Novas consultas para diferenciar notas
    $sql_nfe_entrada = "SELECT COUNT(*) as total FROM nfe WHERE status = 'processada' AND tipo_operacao = 'entrada'";
    $sql_nfe_saida = "SELECT COUNT(*) as total FROM nfe WHERE status = 'processada' AND tipo_operacao = 'saida'";
    $sql_nfce = "SELECT COUNT(*) as total FROM nfce WHERE status = 'processada'";
    
    // Total geral de notas
    $sql_total_notas = "SELECT 
        (SELECT COUNT(*) FROM nfe WHERE status = 'processada') +
        (SELECT COUNT(*) FROM nfce WHERE status = 'processada') as total";
    
    // Consulta para gráfico de notas por mês diferenciado
    $sql_notas_mes = "SELECT 
        'NFe Entrada' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfe 
    WHERE tipo_operacao = 'entrada' AND status = 'processada'
    GROUP BY competencia_ano, competencia_mes 
    UNION ALL
    SELECT 
        'NFe Saída' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfe 
    WHERE tipo_operacao = 'saida' AND status = 'processada'
    GROUP BY competencia_ano, competencia_mes 
    UNION ALL
    SELECT 
        'NFCe' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfce 
    WHERE status = 'processada'
    GROUP BY competencia_ano, competencia_mes 
    ORDER BY competencia_ano DESC, competencia_mes DESC 
    LIMIT 18";
    
    // Consulta para valor total das notas
    $sql_valor_notas = "SELECT 
        'NFe Entrada' as tipo,
        SUM(valor_total) as valor_total,
        COUNT(*) as quantidade
    FROM nfe 
    WHERE tipo_operacao = 'entrada' AND status = 'processada'
    UNION ALL
    SELECT 
        'NFe Saída' as tipo,
        SUM(valor_total) as valor_total,
        COUNT(*) as quantidade
    FROM nfe 
    WHERE tipo_operacao = 'saida' AND status = 'processada'
    UNION ALL
    SELECT 
        'NFCe' as tipo,
        SUM(valor_total) as valor_total,
        COUNT(*) as quantidade
    FROM nfce 
    WHERE status = 'processada'";
    
} else {
    // Auxiliares veem apenas seus processos
    $sql_total_processos = "SELECT COUNT(*) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ?";
    $sql_processos_status = "SELECT status, COUNT(*) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ? GROUP BY status";
    $sql_empresas = "SELECT COUNT(DISTINCT empresa_id) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ?";
    $sql_usuarios = "SELECT COUNT(*) as total FROM gestao_usuarios WHERE ativo = 1 AND id = ?";
    $sql_documentos = "SELECT COUNT(*) as total FROM gestao_documentacoes_empresa WHERE usuario_recebimento_id = ?";
    
    // Novas consultas para diferenciar notas (para auxiliares)
    $sql_nfe_entrada = "SELECT COUNT(*) as total FROM nfe WHERE usuario_id = ? AND status = 'processada' AND tipo_operacao = 'entrada'";
    $sql_nfe_saida = "SELECT COUNT(*) as total FROM nfe WHERE usuario_id = ? AND status = 'processada' AND tipo_operacao = 'saida'";
    $sql_nfce = "SELECT COUNT(*) as total FROM nfce WHERE usuario_id = ? AND status = 'processada'";
    
    // Total geral de notas (para auxiliares)
    $sql_total_notas = "SELECT 
        (SELECT COUNT(*) FROM nfe WHERE usuario_id = ? AND status = 'processada') +
        (SELECT COUNT(*) FROM nfce WHERE usuario_id = ? AND status = 'processada') as total";
    
    // Consulta para gráfico de notas por mês diferenciado (para auxiliares)
    $sql_notas_mes = "SELECT 
        'NFe Entrada' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfe 
    WHERE usuario_id = ? AND tipo_operacao = 'entrada' AND status = 'processada'
    GROUP BY competencia_ano, competencia_mes 
    UNION ALL
    SELECT 
        'NFe Saída' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfe 
    WHERE usuario_id = ? AND tipo_operacao = 'saida' AND status = 'processada'
    GROUP BY competencia_ano, competencia_mes 
    UNION ALL
    SELECT 
        'NFCe' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfce 
    WHERE usuario_id = ? AND status = 'processada'
    GROUP BY competencia_ano, competencia_mes 
    ORDER BY competencia_ano DESC, competencia_mes DESC 
    LIMIT 18";
    
    // Consulta para valor total das notas (para auxiliares)
    $sql_valor_notas = "SELECT 
        'NFe Entrada' as tipo,
        SUM(valor_total) as valor_total,
        COUNT(*) as quantidade
    FROM nfe 
    WHERE usuario_id = ? AND tipo_operacao = 'entrada' AND status = 'processada'
    UNION ALL
    SELECT 
        'NFe Saída' as tipo,
        SUM(valor_total) as valor_total,
        COUNT(*) as quantidade
    FROM nfe 
    WHERE usuario_id = ? AND tipo_operacao = 'saida' AND status = 'processada'
    UNION ALL
    SELECT 
        'NFCe' as tipo,
        SUM(valor_total) as valor_total,
        COUNT(*) as quantidade
    FROM nfce 
    WHERE usuario_id = ? AND status = 'processada'";
}

// Executar consultas
$total_processos = executarConsulta($sql_total_processos, $usuario_id);
$processos_status = executarConsultaArray($sql_processos_status, $usuario_id);
$total_empresas = executarConsulta($sql_empresas, $usuario_id);
$total_usuarios = executarConsulta($sql_usuarios, $usuario_id);
$total_documentos = executarConsulta($sql_documentos, $usuario_id);

// Executar novas consultas de notas
$total_nfe_entrada = executarConsulta($sql_nfe_entrada, $usuario_id);
$total_nfe_saida = executarConsulta($sql_nfe_saida, $usuario_id);
$total_nfce = executarConsulta($sql_nfce, $usuario_id);
$total_notas = executarConsulta($sql_total_notas, $usuario_id);

// Executar consulta de notas por mês
if (temPermissaoGestao('analista')) {
    $notas_por_mes = executarConsultaArray($sql_notas_mes);
    $valor_notas = executarConsultaArray($sql_valor_notas);
} else {
    $notas_por_mes = executarConsultaArray($sql_notas_mes, $usuario_id);
    $valor_notas = executarConsultaArray($sql_valor_notas, $usuario_id);
}

// Buscar processos por categoria
if (temPermissaoGestao('analista')) {
    $sql_categorias = "SELECT c.nome, c.cor, COUNT(p.id) as total 
                      FROM gestao_categorias_processo c 
                      LEFT JOIN gestao_processos p ON c.id = p.categoria_id AND p.ativo = 1 
                      WHERE c.ativo = 1 
                      GROUP BY c.id, c.nome, c.cor 
                      ORDER BY total DESC";
} else {
    $sql_categorias = "SELECT c.nome, c.cor, COUNT(p.id) as total 
                      FROM gestao_categorias_processo c 
                      LEFT JOIN gestao_processos p ON c.id = p.categoria_id AND p.ativo = 1 AND p.responsavel_id = ?
                      WHERE c.ativo = 1 
                      GROUP BY c.id, c.nome, c.cor 
                      ORDER BY total DESC";
}
$categorias = executarConsultaArray($sql_categorias, $usuario_id);

// Buscar processos por prioridade
if (temPermissaoGestao('analista')) {
    $sql_prioridades = "SELECT prioridade, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 
                       GROUP BY prioridade 
                       ORDER BY FIELD(prioridade, 'urgente', 'alta', 'media', 'baixa')";
} else {
    $sql_prioridades = "SELECT prioridade, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND responsavel_id = ? 
                       GROUP BY prioridade 
                       ORDER BY FIELD(prioridade, 'urgente', 'alta', 'media', 'baida')";
}
$prioridades = executarConsultaArray($sql_prioridades, $usuario_id);

// Buscar processos recorrentes
if (temPermissaoGestao('analista')) {
    $sql_recorrentes = "SELECT recorrente, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND recorrente != 'nao' 
                       GROUP BY recorrente 
                       ORDER BY total DESC";
} else {
    $sql_recorrentes = "SELECT recorrente, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND recorrente != 'nao' AND responsavel_id = ? 
                       GROUP BY recorrente 
                       ORDER BY total DESC";
}
$recorrentes = executarConsultaArray($sql_recorrentes, $usuario_id);

// Buscar top responsáveis
if (temPermissaoGestao('analista')) {
    $sql_responsaveis = "SELECT u.nome_completo, COUNT(p.id) as total 
                        FROM gestao_usuarios u 
                        LEFT JOIN gestao_processos p ON u.id = p.responsavel_id AND p.ativo = 1 
                        WHERE u.ativo = 1 
                        GROUP BY u.id, u.nome_completo 
                        ORDER BY total DESC 
                        LIMIT 5";
} else {
    $sql_responsaveis = "SELECT u.nome_completo, COUNT(p.id) as total 
                        FROM gestao_usuarios u 
                        LEFT JOIN gestao_processos p ON u.id = p.responsavel_id AND p.ativo = 1 AND p.responsavel_id = ?
                        WHERE u.ativo = 1 AND u.id = ?
                        GROUP BY u.id, u.nome_completo 
                        ORDER BY total DESC 
                        LIMIT 5";
}
$top_responsaveis = executarConsultaArray($sql_responsaveis, $usuario_id);

// Buscar estatísticas de documentos
if (temPermissaoGestao('analista')) {
    $sql_docs_status = "SELECT status, COUNT(*) as total 
                       FROM gestao_documentacoes_empresa 
                       GROUP BY status";
} else {
    $sql_docs_status = "SELECT status, COUNT(*) as total 
                       FROM gestao_documentacoes_empresa 
                       WHERE usuario_recebimento_id = ? 
                       GROUP BY status";
}
$documentos_status = executarConsultaArray($sql_docs_status, $usuario_id);

// Buscar processos próximos do vencimento
if (temPermissaoGestao('analista')) {
    $sql_vencimentos = "SELECT p.titulo, p.data_prevista, u.nome_completo as responsavel, 
                       DATEDIFF(p.data_prevista, CURDATE()) as dias_restantes 
                       FROM gestao_processos p 
                       LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
                       WHERE p.ativo = 1 AND p.status NOT IN ('concluido', 'cancelado') 
                       AND p.data_prevista IS NOT NULL 
                       AND p.data_prevista >= CURDATE() 
                       ORDER BY p.data_prevista ASC 
                       LIMIT 5";
} else {
    $sql_vencimentos = "SELECT p.titulo, p.data_prevista, u.nome_completo as responsavel, 
                       DATEDIFF(p.data_prevista, CURDATE()) as dias_restantes 
                       FROM gestao_processos p 
                       LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
                       WHERE p.ativo = 1 AND p.status NOT IN ('concluido', 'cancelado') 
                       AND p.data_prevista IS NOT NULL 
                       AND p.data_prevista >= CURDATE() 
                       AND p.responsavel_id = ? 
                       ORDER BY p.data_prevista ASC 
                       LIMIT 5";
}
$proximos_vencimentos = executarConsultaArray($sql_vencimentos, $usuario_id);

// Funções auxiliares para executar consultas
function executarConsulta($sql, $usuario_id = null) {
    global $conexao;
    $stmt = $conexao->prepare($sql);
    if ($usuario_id && strpos($sql, '?') !== false) {
        if (substr_count($sql, '?') > 1) {
            // Para consultas com múltiplos parâmetros
            if (temPermissaoGestao('analista')) {
                $stmt->bind_param("i", $usuario_id);
            } else {
                // Para auxiliares, usar o mesmo usuário para todos os parâmetros
                $param_types = str_repeat('i', substr_count($sql, '?'));
                $params = array_fill(0, substr_count($sql, '?'), $usuario_id);
                $stmt->bind_param($param_types, ...$params);
            }
        } else {
            $stmt->bind_param("i", $usuario_id);
        }
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['total'] ?? 0;
}

function executarConsultaArray($sql, $usuario_id = null) {
    global $conexao;
    $stmt = $conexao->prepare($sql);
    if ($usuario_id && strpos($sql, '?') !== false) {
        if (substr_count($sql, '?') > 1) {
            // Para consultas com múltiplos parâmetros
            if (temPermissaoGestao('analista')) {
                $stmt->bind_param("i", $usuario_id);
            } else {
                // Para auxiliares, usar o mesmo usuário para todos os parâmetros
                $param_types = str_repeat('i', substr_count($sql, '?'));
                $params = array_fill(0, substr_count($sql, '?'), $usuario_id);
                $stmt->bind_param($param_types, ...$params);
            }
        } else {
            $stmt->bind_param("i", $usuario_id);
        }
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

// Preparar dados para gráficos
$dados_status = [];
$status_labels = [
    'rascunho' => 'Rascunho',
    'pendente' => 'Pendente', 
    'em_andamento' => 'Em Andamento',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
    'pausado' => 'Pausado'
];

foreach ($processos_status as $status) {
    $dados_status[] = [
        'status' => $status_labels[$status['status']] ?? $status['status'],
        'total' => $status['total']
    ];
}

// Preparar dados para gráfico de notas
$dados_notas_tipo = [
    ['tipo' => 'NFe Entrada', 'total' => $total_nfe_entrada, 'cor' => '#10B981'],
    ['tipo' => 'NFe Saída', 'total' => $total_nfe_saida, 'cor' => '#3B82F6'],
    ['tipo' => 'NFCe', 'total' => $total_nfce, 'cor' => '#8B5CF6']
];

// Preparar dados para gráfico de notas por mês
$meses_formatados = [];
$dados_notas_mes = [];

foreach ($notas_por_mes as $item) {
    $mes_ano = $item['competencia_mes'] . '/' . $item['competencia_ano'];
    if (!in_array($mes_ano, $meses_formatados)) {
        $meses_formatados[] = $mes_ano;
    }
    
    $dados_notas_mes[$item['tipo']][$mes_ano] = $item['total'];
}

// Calcular totais para cards
$processos_concluidos = 0;
$processos_andamento = 0;
$processos_pendentes = 0;

foreach ($processos_status as $status) {
    switch ($status['status']) {
        case 'concluido':
            $processos_concluidos = $status['total'];
            break;
        case 'em_andamento':
            $processos_andamento = $status['total'];
            break;
        case 'pendente':
            $processos_pendentes = $status['total'];
            break;
    }
}

// Preparar dados de valores das notas
$valores_formatados = [];
foreach ($valor_notas as $item) {
    if ($item['valor_total']) {
        $valores_formatados[] = [
            'tipo' => $item['tipo'],
            'valor_total' => number_format($item['valor_total'], 2, ',', '.'),
            'quantidade' => $item['quantidade']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Gestão de Processos</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--gray);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.processos { border-left-color: #4361ee; }
        .stat-card.empresas { border-left-color: #7209b7; }
        .stat-card.usuarios { border-left-color: #2ec4b6; }
        .stat-card.documentos { border-left-color: #ff9f1c; }
        .stat-card.nfe-entrada { border-left-color: #10B981; }
        .stat-card.nfe-saida { border-left-color: #3B82F6; }
        .stat-card.nfce { border-left-color: #8B5CF6; }
        .stat-card.total-notas { border-left-color: #E63946; }
        .stat-card.concluidos { border-left-color: #10b981; }
        .stat-card.andamento { border-left-color: #3b82f6; }
        .stat-card.pendentes { border-left-color: #f59e0b; }

        .stat-info h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .stat-info .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }

        .stat-card.processos .stat-icon { color: #4361ee; }
        .stat-card.empresas .stat-icon { color: #7209b7; }
        .stat-card.usuarios .stat-icon { color: #2ec4b6; }
        .stat-card.documentos .stat-icon { color: #ff9f1c; }
        .stat-card.nfe-entrada .stat-icon { color: #10B981; }
        .stat-card.nfe-saida .stat-icon { color: #3B82F6; }
        .stat-card.nfce .stat-icon { color: #8B5CF6; }
        .stat-card.total-notas .stat-icon { color: #E63946; }
        .stat-card.concluidos .stat-icon { color: #10b981; }
        .stat-card.andamento .stat-icon { color: #3b82f6; }
        .stat-card.pendentes .stat-icon { color: #f59e0b; }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        .info-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .info-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            background: #f8f9fa;
        }

        .info-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-body {
            padding: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            flex: 1;
            color: var(--dark);
        }

        .info-value {
            font-weight: 600;
            color: var(--primary);
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .badge-info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .badge-purple { background: rgba(139, 92, 246, 0.1); color: #8B5CF6; }

        .grid-2-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .grid-3-col {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .notas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .nota-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
        }

        .nota-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .nota-item h4 {
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-size: 1rem;
        }

        .nota-item .quantidade {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .nota-item.nfe-entrada .quantidade { color: #10B981; }
        .nota-item.nfe-saida .quantidade { color: #3B82F6; }
        .nota-item.nfce .quantidade { color: #8B5CF6; }

        .valor-nota {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .desc-valor {
            font-size: 0.85rem;
            color: var(--gray);
        }

        @media (max-width: 1024px) {
            .grid-3-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .grid-2-col,
            .grid-3-col {
                grid-template-columns: 1fr;
            }
            
            .navbar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            <li><a href="gestao-empresas.php" class="nav-link">Empresas</a></li>
            <li><a href="responsaveis-gestao.php" class="nav-link">Responsáveis</a></li>
            <li><a href="relatorios-gestao.php" class="nav-link active">Relatórios</a></li>
            <li><a href="logout-gestao.php" class="nav-link">Sair</a></li>
        </ul>
    </nav>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">Relatórios e Estatísticas</h1>
            <p class="page-subtitle">
                <i class="fas fa-chart-bar"></i>
                Visão completa do sistema - 
                <?php echo temPermissaoGestao('analista') ? 'Todos os dados' : 'Seus dados'; ?>
            </p>
        </div>

        <!-- Cards de Estatísticas Principais -->
        <div class="stats-grid">
            <div class="stat-card processos">
                <div class="stat-info">
                    <h3>Total de Processos</h3>
                    <div class="number"><?php echo $total_processos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            
            <div class="stat-card empresas">
                <div class="stat-info">
                    <h3>Empresas</h3>
                    <div class="number"><?php echo $total_empresas; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            
            <div class="stat-card usuarios">
                <div class="stat-info">
                    <h3>Usuários</h3>
                    <div class="number"><?php echo $total_usuarios; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card documentos">
                <div class="stat-info">
                    <h3>Documentos</h3>
                    <div class="number"><?php echo $total_documentos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>

            <!-- Novos cards para notas diferenciadas -->
            <div class="stat-card nfe-entrada">
                <div class="stat-info">
                    <h3>NFes Entrada</h3>
                    <div class="number"><?php echo $total_nfe_entrada; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
            </div>

            <div class="stat-card nfe-saida">
                <div class="stat-info">
                    <h3>NFes Saída</h3>
                    <div class="number"><?php echo $total_nfe_saida; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
            </div>

            <div class="stat-card nfce">
                <div class="stat-info">
                    <h3>NFCes</h3>
                    <div class="number"><?php echo $total_nfce; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>

            <div class="stat-card total-notas">
                <div class="stat-info">
                    <h3>Total de Notas</h3>
                    <div class="number"><?php echo $total_notas; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>

            <div class="stat-card concluidos">
                <div class="stat-info">
                    <h3>Processos Concluídos</h3>
                    <div class="number"><?php echo $processos_concluidos; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <div class="stat-card andamento">
                <div class="stat-info">
                    <h3>Em Andamento</h3>
                    <div class="number"><?php echo $processos_andamento; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
            </div>

            <div class="stat-card pendentes">
                <div class="stat-info">
                    <h3>Processos Pendentes</h3>
                    <div class="number"><?php echo $processos_pendentes; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>

        <!-- Gráficos e Estatísticas Detalhadas -->
        <div class="grid-2-col">
            <!-- Gráfico de Status -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Status dos Processos
                </h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Categorias -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-layer-group"></i>
                    Processos por Categoria
                </h3>
                <div class="chart-container">
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Novo gráfico para notas diferenciadas -->
        <div class="grid-2-col">
            <!-- Gráfico de Notas por Tipo -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Distribuição de Notas
                </h3>
                <div class="chart-container">
                    <canvas id="notasChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Notas por Mês -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line"></i>
                    Notas por Mês
                </h3>
                <div class="chart-container">
                    <canvas id="notasMesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Valores das Notas -->
        <?php if (!empty($valores_formatados)): ?>
        <div class="info-card">
            <div class="info-header">
                <h3 class="info-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Valor Total das Notas
                </h3>
            </div>
            <div class="info-body">
                <div class="notas-grid">
                    <?php foreach ($valores_formatados as $valor): ?>
                        <div class="nota-item <?php echo strtolower(str_replace(' ', '-', $valor['tipo'])); ?>">
                            <h4><?php echo $valor['tipo']; ?></h4>
                            <div class="valor-nota">R$ <?php echo $valor['valor_total']; ?></div>
                            <div class="desc-valor"><?php echo $valor['quantidade']; ?> notas</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid-3-col">
            <!-- Top Responsáveis -->
            <div class="info-card">
                <div class="info-header">
                    <h3 class="info-title">
                        <i class="fas fa-user-tie"></i>
                        Top Responsáveis
                    </h3>
                </div>
                <div class="info-body">
                    <?php if (count($top_responsaveis) > 0): ?>
                        <?php foreach ($top_responsaveis as $responsavel): ?>
                            <div class="info-item">
                                <span class="info-label"><?php echo htmlspecialchars($responsavel['nome_completo']); ?></span>
                                <span class="info-value"><?php echo $responsavel['total']; ?> processos</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>Nenhum responsável encontrado</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Próximos Vencimentos -->
            <div class="info-card">
                <div class="info-header">
                    <h3 class="info-title">
                        <i class="fas fa-calendar-day"></i>
                        Próximos Vencimentos
                    </h3>
                </div>
                <div class="info-body">
                    <?php if (count($proximos_vencimentos) > 0): ?>
                        <?php foreach ($proximos_vencimentos as $vencimento): ?>
                            <div class="info-item">
                                <div>
                                    <div class="info-label"><?php echo htmlspecialchars($vencimento['titulo']); ?></div>
                                    <small style="color: var(--gray);">
                                        <?php echo date('d/m/Y', strtotime($vencimento['data_prevista'])); ?> - 
                                        <?php echo htmlspecialchars($vencimento['responsavel']); ?>
                                    </small>
                                </div>
                                <span class="badge <?php echo $vencimento['dias_restantes'] <= 3 ? 'badge-danger' : ($vencimento['dias_restantes'] <= 7 ? 'badge-warning' : 'badge-info'); ?>">
                                    <?php echo $vencimento['dias_restantes']; ?> dias
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>Nenhum vencimento próximo</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Distribuição de Notas -->
            <div class="info-card">
                <div class="info-header">
                    <h3 class="info-title">
                        <i class="fas fa-chart-pie"></i>
                        Distribuição de Notas
                    </h3>
                </div>
                <div class="info-body">
                    <?php if (count($dados_notas_tipo) > 0): ?>
                        <?php foreach ($dados_notas_tipo as $nota): ?>
                            <?php if ($nota['total'] > 0): ?>
                            <div class="info-item">
                                <span class="info-label">
                                    <span style="display: inline-block; width: 12px; height: 12px; background: <?php echo $nota['cor']; ?>; border-radius: 50%; margin-right: 8px;"></span>
                                    <?php echo $nota['tipo']; ?>
                                </span>
                                <span class="info-value"><?php echo $nota['total']; ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <p>Nenhuma nota encontrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Estatísticas de Documentos -->
        <div class="info-card">
            <div class="info-header">
                <h3 class="info-title">
                    <i class="fas fa-file-contract"></i>
                    Status de Documentações
                </h3>
            </div>
            <div class="info-body">
                <?php if (count($documentos_status) > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <?php foreach ($documentos_status as $doc_status): ?>
                            <div style="text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                                    <?php echo $doc_status['total']; ?>
                                </div>
                                <div style="color: var(--gray); text-transform: capitalize;">
                                    <?php echo htmlspecialchars($doc_status['status']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>Nenhuma documentação encontrada</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Dados dos gráficos
            const statusData = <?php echo json_encode($dados_status); ?>;
            const categoriasData = <?php echo json_encode($categorias); ?>;
            const notasData = <?php echo json_encode($dados_notas_tipo); ?>;
            const notasMesData = <?php echo json_encode($dados_notas_mes); ?>;
            const meses = <?php echo json_encode($meses_formatados); ?>;

            // Gráfico de Status
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(item => item.status),
                    datasets: [{
                        data: statusData.map(item => item.total),
                        backgroundColor: [
                            '#6B7280', // Rascunho
                            '#F59E0B', // Pendente
                            '#3B82F6', // Em Andamento
                            '#10B981', // Concluído
                            '#EF4444', // Cancelado
                            '#9CA3AF'  // Pausado
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Gráfico de Categorias
            const categoriasCtx = document.getElementById('categoriasChart').getContext('2d');
            const categoriasChart = new Chart(categoriasCtx, {
                type: 'bar',
                data: {
                    labels: categoriasData.map(item => item.nome),
                    datasets: [{
                        label: 'Processos por Categoria',
                        data: categoriasData.map(item => item.total),
                        backgroundColor: categoriasData.map(item => item.cor || '#4361ee'),
                        borderColor: categoriasData.map(item => item.cor || '#4361ee'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Gráfico de Notas por Tipo
            const notasCtx = document.getElementById('notasChart').getContext('2d');
            const notasChart = new Chart(notasCtx, {
                type: 'pie',
                data: {
                    labels: notasData.filter(item => item.total > 0).map(item => item.tipo),
                    datasets: [{
                        data: notasData.filter(item => item.total > 0).map(item => item.total),
                        backgroundColor: notasData.filter(item => item.total > 0).map(item => item.cor),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Gráfico de Notas por Mês
            const notasMesCtx = document.getElementById('notasMesChart').getContext('2d');
            
            // Preparar datasets
            const tiposNotas = Object.keys(notasMesData);
            const datasets = tiposNotas.map(tipo => {
                const cores = {
                    'NFe Entrada': '#10B981',
                    'NFe Saída': '#3B82F6',
                    'NFCe': '#8B5CF6'
                };
                
                return {
                    label: tipo,
                    data: meses.map(mes => notasMesData[tipo]?.[mes] || 0),
                    backgroundColor: cores[tipo] || '#6B7280',
                    borderColor: cores[tipo] || '#6B7280',
                    borderWidth: 2,
                    fill: false
                };
            });

            const notasMesChart = new Chart(notasMesCtx, {
                type: 'line',
                data: {
                    labels: meses,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantidade de Notas'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Mês/Ano'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y} notas`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>