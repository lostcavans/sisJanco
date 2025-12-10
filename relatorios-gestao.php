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

// Buscar todas as empresas do usuário
if ($nivel_usuario == 'admin' || $nivel_usuario == 'analista') {
    // Analistas veem tudo
    $sql_total_processos = "SELECT COUNT(*) as total FROM gestao_processos WHERE ativo = 1";
    $sql_processos_status = "SELECT status, COUNT(*) as total FROM gestao_processos WHERE ativo = 1 GROUP BY status";
    $sql_empresas = "SELECT COUNT(*) as total FROM empresas";
    $sql_usuarios = "SELECT COUNT(*) as total FROM gestao_usuarios WHERE ativo = 1";
    $sql_documentos = "SELECT COUNT(*) as total FROM gestao_documentacoes_empresa";
    
    // CONSULTAS SIMPLIFICADAS: Usando diretamente as colunas da tabela nfe
    // Total de notas por tipo (todas as empresas)
    $sql_nfe_entrada = "SELECT COUNT(*) as total FROM nfe WHERE tipo_operacao = 'entrada'";
    $sql_nfe_saida = "SELECT COUNT(*) as total FROM nfe WHERE tipo_operacao = 'saida'";
    $sql_nfce = "SELECT COUNT(*) as total FROM nfce";
    
    // Total geral de notas
    $sql_total_notas = "SELECT 
        (SELECT COUNT(*) FROM nfe) +
        (SELECT COUNT(*) FROM nfce) as total";
    
    // Consulta para gráfico de notas por mês diferenciado
    $sql_notas_mes = "SELECT 
        'NFe Entrada' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfe 
    WHERE tipo_operacao = 'entrada'
    GROUP BY competencia_ano, competencia_mes 
    UNION ALL
    SELECT 
        'NFe Saída' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfe 
    WHERE tipo_operacao = 'saida'
    GROUP BY competencia_ano, competencia_mes 
    UNION ALL
    SELECT 
        'NFCe' as tipo, 
        COUNT(*) as total, 
        competencia_mes, 
        competencia_ano 
    FROM nfce 
    GROUP BY competencia_ano, competencia_mes 
    ORDER BY competencia_ano DESC, competencia_mes DESC 
    LIMIT 18";
    
    // Consulta para valor total das notas por tipo (todas as empresas)
    $sql_valor_notas = "SELECT 
        'NFe Entrada' as tipo,
        COALESCE(SUM(valor_total), 0) as valor_total,
        COUNT(*) as quantidade
    FROM nfe 
    WHERE tipo_operacao = 'entrada'
    UNION ALL
    SELECT 
        'NFe Saída' as tipo,
        COALESCE(SUM(valor_total), 0) as valor_total,
        COUNT(*) as quantidade
    FROM nfe 
    WHERE tipo_operacao = 'saida'
    UNION ALL
    SELECT 
        'NFCe' as tipo,
        COALESCE(SUM(valor_total), 0) as valor_total,
        COUNT(*) as quantidade
    FROM nfce";
    
    // Notas por Empresa e Tipo - SIMPLIFICADO
    $sql_notas_empresa = "SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        'NFe Entrada' as tipo,
        COALESCE(COUNT(n.id), 0) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total
    FROM empresas e
    LEFT JOIN nfe n ON e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada'
    GROUP BY e.id, e.razao_social
    UNION ALL
    SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        'NFe Saída' as tipo,
        COALESCE(COUNT(n.id), 0) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total
    FROM empresas e
    LEFT JOIN nfe n ON e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida'
    GROUP BY e.id, e.razao_social
    UNION ALL
    SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        'NFCe' as tipo,
        COALESCE(COUNT(nc.id), 0) as quantidade,
        COALESCE(SUM(nc.valor_total), 0) as valor_total
    FROM empresas e
    LEFT JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    GROUP BY e.id, e.razao_social
    ORDER BY empresa, tipo";
    
    // Notas por Competência (mês/ano) e Tipo - SIMPLIFICADO
    $sql_notas_competencia = "SELECT 
        CONCAT(
            CASE n.competencia_mes 
                WHEN 1 THEN 'Jan'
                WHEN 2 THEN 'Fev'
                WHEN 3 THEN 'Mar'
                WHEN 4 THEN 'Abr'
                WHEN 5 THEN 'Mai'
                WHEN 6 THEN 'Jun'
                WHEN 7 THEN 'Jul'
                WHEN 8 THEN 'Ago'
                WHEN 9 THEN 'Set'
                WHEN 10 THEN 'Out'
                WHEN 11 THEN 'Nov'
                WHEN 12 THEN 'Dez'
            END,
            '/',
            n.competencia_ano
        ) as competencia_formatada,
        e.razao_social as empresa,
        'NFe Entrada' as tipo,
        COUNT(*) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total,
        n.competencia_mes,
        n.competencia_ano
    FROM empresas e
    LEFT JOIN nfe n ON e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada'
    WHERE n.id IS NOT NULL
    GROUP BY e.id, e.razao_social, n.competencia_ano, n.competencia_mes
    UNION ALL
    SELECT 
        CONCAT(
            CASE n.competencia_mes 
                WHEN 1 THEN 'Jan'
                WHEN 2 THEN 'Fev'
                WHEN 3 THEN 'Mar'
                WHEN 4 THEN 'Abr'
                WHEN 5 THEN 'Mai'
                WHEN 6 THEN 'Jun'
                WHEN 7 THEN 'Jul'
                WHEN 8 THEN 'Ago'
                WHEN 9 THEN 'Set'
                WHEN 10 THEN 'Out'
                WHEN 11 THEN 'Nov'
                WHEN 12 THEN 'Dez'
            END,
            '/',
            n.competencia_ano
        ) as competencia_formatada,
        e.razao_social as empresa,
        'NFe Saída' as tipo,
        COUNT(*) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total,
        n.competencia_mes,
        n.competencia_ano
    FROM empresas e
    LEFT JOIN nfe n ON e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida'
    WHERE n.id IS NOT NULL
    GROUP BY e.id, e.razao_social, n.competencia_ano, n.competencia_mes
    UNION ALL
    SELECT 
        CONCAT(
            CASE nc.competencia_mes 
                WHEN 1 THEN 'Jan'
                WHEN 2 THEN 'Fev'
                WHEN 3 THEN 'Mar'
                WHEN 4 THEN 'Abr'
                WHEN 5 THEN 'Mai'
                WHEN 6 THEN 'Jun'
                WHEN 7 THEN 'Jul'
                WHEN 8 THEN 'Ago'
                WHEN 9 THEN 'Set'
                WHEN 10 THEN 'Out'
                WHEN 11 THEN 'Nov'
                WHEN 12 THEN 'Dez'
            END,
            '/',
            nc.competencia_ano
        ) as competencia_formatada,
        e.razao_social as empresa,
        'NFCe' as tipo,
        COUNT(*) as quantidade,
        COALESCE(SUM(nc.valor_total), 0) as valor_total,
        nc.competencia_mes,
        nc.competencia_ano
    FROM empresas e
    LEFT JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    WHERE nc.id IS NOT NULL
    GROUP BY e.id, e.razao_social, nc.competencia_ano, nc.competencia_mes
    ORDER BY competencia_ano DESC, competencia_mes DESC, empresa, tipo
    LIMIT 36";
    
    // Resumo por empresa - SIMPLIFICADO
    $sql_resumo_empresa = "SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        e.cnpj,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'entrada' AND n.destinatario_cnpj = e.cnpj THEN 1 ELSE 0 END), 0) as total_nfe_entrada,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'saida' AND n.emitente_cnpj = e.cnpj THEN 1 ELSE 0 END), 0) as total_nfe_saida,
        COALESCE(COUNT(nc.id), 0) as total_nfce,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'entrada' AND n.destinatario_cnpj = e.cnpj THEN n.valor_total ELSE 0 END), 0) as valor_nfe_entrada,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'saida' AND n.emitente_cnpj = e.cnpj THEN n.valor_total ELSE 0 END), 0) as valor_nfe_saida,
        COALESCE(SUM(nc.valor_total), 0) as valor_nfce
    FROM empresas e
    LEFT JOIN nfe n ON (e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada') 
                    OR (e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida')
    LEFT JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    GROUP BY e.id, e.razao_social, e.cnpj
    ORDER BY empresa";
    
} else {
    // Auxiliares veem apenas seus processos e empresas - SIMPLIFICADO
    $sql_total_processos = "SELECT COUNT(*) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ?";
    $sql_processos_status = "SELECT status, COUNT(*) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ? GROUP BY status";
    $sql_empresas = "SELECT COUNT(DISTINCT empresa_id) as total FROM gestao_processos WHERE ativo = 1 AND responsavel_id = ?";
    $sql_usuarios = "SELECT COUNT(*) as total FROM gestao_usuarios WHERE ativo = 1 AND id = ?";
    $sql_documentos = "SELECT COUNT(*) as total FROM gestao_documentacoes_empresa WHERE usuario_recebimento_id = ?";
    
    // CONSULTAS PARA AUXILIARES - SIMPLIFICADAS
    // Total de notas por tipo (apenas empresas do usuário)
    $sql_nfe_entrada = "SELECT COUNT(*) as total 
                       FROM nfe n
                       INNER JOIN empresas e ON n.destinatario_cnpj = e.cnpj
                       INNER JOIN user_empresa ue ON e.id = ue.empresa_id
                       WHERE ue.user_id = ? AND n.tipo_operacao = 'entrada'";
    
    $sql_nfe_saida = "SELECT COUNT(*) as total 
                     FROM nfe n
                     INNER JOIN empresas e ON n.emitente_cnpj = e.cnpj
                     INNER JOIN user_empresa ue ON e.id = ue.empresa_id
                     WHERE ue.user_id = ? AND n.tipo_operacao = 'saida'";
    
    $sql_nfce = "SELECT COUNT(*) as total 
                FROM nfce nc
                INNER JOIN empresas e ON nc.emitente_cnpj = e.cnpj
                INNER JOIN user_empresa ue ON e.id = ue.empresa_id
                WHERE ue.user_id = ?";
    
    // Total geral de notas (para auxiliares) - SIMPLIFICADO
    $sql_total_notas = "SELECT 
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'entrada' THEN 1 ELSE 0 END), 0) as entradas,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'saida' THEN 1 ELSE 0 END), 0) as saidas,
        COALESCE(COUNT(nc.id), 0) as nfces
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    LEFT JOIN nfe n ON (e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada')
                    OR (e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida')
    LEFT JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    WHERE ue.user_id = ?";
    
    // Consulta para gráfico de notas por mês diferenciado (para auxiliares)
    $sql_notas_mes = "SELECT 
        'NFe Entrada' as tipo, 
        COUNT(*) as total, 
        n.competencia_mes, 
        n.competencia_ano 
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfe n ON e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada'
    WHERE ue.user_id = ?
    GROUP BY n.competencia_ano, n.competencia_mes 
    UNION ALL
    SELECT 
        'NFe Saída' as tipo, 
        COUNT(*) as total, 
        n.competencia_mes, 
        n.competencia_ano 
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfe n ON e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida'
    WHERE ue.user_id = ?
    GROUP BY n.competencia_ano, n.competencia_mes 
    UNION ALL
    SELECT 
        'NFCe' as tipo, 
        COUNT(*) as total, 
        nc.competencia_mes, 
        nc.competencia_ano 
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    WHERE ue.user_id = ?
    GROUP BY nc.competencia_ano, nc.competencia_mes 
    ORDER BY competencia_ano DESC, competencia_mes DESC 
    LIMIT 18";
    
    // Consulta para valor total das notas (para auxiliares)
    $sql_valor_notas = "SELECT 
        'NFe Entrada' as tipo,
        COALESCE(SUM(n.valor_total), 0) as valor_total,
        COUNT(*) as quantidade
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfe n ON e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada'
    WHERE ue.user_id = ?
    UNION ALL
    SELECT 
        'NFe Saída' as tipo,
        COALESCE(SUM(n.valor_total), 0) as valor_total,
        COUNT(*) as quantidade
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfe n ON e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida'
    WHERE ue.user_id = ?
    UNION ALL
    SELECT 
        'NFCe' as tipo,
        COALESCE(SUM(nc.valor_total), 0) as valor_total,
        COUNT(*) as quantidade
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    WHERE ue.user_id = ?";
    
    // Notas por Empresa e Tipo (para auxiliares)
    $sql_notas_empresa = "SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        'NFe Entrada' as tipo,
        COUNT(n.id) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    LEFT JOIN nfe n ON e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada'
    WHERE ue.user_id = ?
    GROUP BY e.id, e.razao_social
    UNION ALL
    SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        'NFe Saída' as tipo,
        COUNT(n.id) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    LEFT JOIN nfe n ON e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida'
    WHERE ue.user_id = ?
    GROUP BY e.id, e.razao_social
    UNION ALL
    SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        'NFCe' as tipo,
        COUNT(nc.id) as quantidade,
        COALESCE(SUM(nc.valor_total), 0) as valor_total
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    LEFT JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    WHERE ue.user_id = ?
    GROUP BY e.id, e.razao_social
    ORDER BY empresa, tipo";
    
    // Notas por Competência (mês/ano) e Tipo (para auxiliares)
    $sql_notas_competencia = "SELECT 
        CONCAT(
            CASE n.competencia_mes 
                WHEN 1 THEN 'Jan'
                WHEN 2 THEN 'Fev'
                WHEN 3 THEN 'Mar'
                WHEN 4 THEN 'Abr'
                WHEN 5 THEN 'Mai'
                WHEN 6 THEN 'Jun'
                WHEN 7 THEN 'Jul'
                WHEN 8 THEN 'Ago'
                WHEN 9 THEN 'Set'
                WHEN 10 THEN 'Out'
                WHEN 11 THEN 'Nov'
                WHEN 12 THEN 'Dez'
            END,
            '/',
            n.competencia_ano
        ) as competencia_formatada,
        e.razao_social as empresa,
        'NFe Entrada' as tipo,
        COUNT(*) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total,
        n.competencia_mes,
        n.competencia_ano
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfe n ON e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada'
    WHERE ue.user_id = ?
    GROUP BY e.id, e.razao_social, n.competencia_ano, n.competencia_mes
    UNION ALL
    SELECT 
        CONCAT(
            CASE n.competencia_mes 
                WHEN 1 THEN 'Jan'
                WHEN 2 THEN 'Fev'
                WHEN 3 THEN 'Mar'
                WHEN 4 THEN 'Abr'
                WHEN 5 THEN 'Mai'
                WHEN 6 THEN 'Jun'
                WHEN 7 THEN 'Jul'
                WHEN 8 THEN 'Ago'
                WHEN 9 THEN 'Set'
                WHEN 10 THEN 'Out'
                WHEN 11 THEN 'Nov'
                WHEN 12 THEN 'Dez'
            END,
            '/',
            n.competencia_ano
        ) as competencia_formatada,
        e.razao_social as empresa,
        'NFe Saída' as tipo,
        COUNT(*) as quantidade,
        COALESCE(SUM(n.valor_total), 0) as valor_total,
        n.competencia_mes,
        n.competencia_ano
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfe n ON e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida'
    WHERE ue.user_id = ?
    GROUP BY e.id, e.razao_social, n.competencia_ano, n.competencia_mes
    UNION ALL
    SELECT 
        CONCAT(
            CASE nc.competencia_mes 
                WHEN 1 THEN 'Jan'
                WHEN 2 THEN 'Fev'
                WHEN 3 THEN 'Mar'
                WHEN 4 THEN 'Abr'
                WHEN 5 THEN 'Mai'
                WHEN 6 THEN 'Jun'
                WHEN 7 THEN 'Jul'
                WHEN 8 THEN 'Ago'
                WHEN 9 THEN 'Set'
                WHEN 10 THEN 'Out'
                WHEN 11 THEN 'Nov'
                WHEN 12 THEN 'Dez'
            END,
            '/',
            nc.competencia_ano
        ) as competencia_formatada,
        e.razao_social as empresa,
        'NFCe' as tipo,
        COUNT(*) as quantidade,
        COALESCE(SUM(nc.valor_total), 0) as valor_total,
        nc.competencia_mes,
        nc.competencia_ano
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    INNER JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    WHERE ue.user_id = ?
    GROUP BY e.id, e.razao_social, nc.competencia_ano, nc.competencia_mes
    ORDER BY competencia_ano DESC, competencia_mes DESC, empresa, tipo
    LIMIT 36";
    
    // Resumo por empresa (para auxiliares)
    $sql_resumo_empresa = "SELECT 
        e.id as empresa_id,
        e.razao_social as empresa,
        e.cnpj,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'entrada' THEN 1 ELSE 0 END), 0) as total_nfe_entrada,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'saida' THEN 1 ELSE 0 END), 0) as total_nfe_saida,
        COALESCE(COUNT(nc.id), 0) as total_nfce,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'entrada' THEN n.valor_total ELSE 0 END), 0) as valor_nfe_entrada,
        COALESCE(SUM(CASE WHEN n.tipo_operacao = 'saida' THEN n.valor_total ELSE 0 END), 0) as valor_nfe_saida,
        COALESCE(SUM(nc.valor_total), 0) as valor_nfce
    FROM user_empresa ue
    INNER JOIN empresas e ON ue.empresa_id = e.id
    LEFT JOIN nfe n ON (e.cnpj = n.destinatario_cnpj AND n.tipo_operacao = 'entrada')
                    OR (e.cnpj = n.emitente_cnpj AND n.tipo_operacao = 'saida')
    LEFT JOIN nfce nc ON e.cnpj = nc.emitente_cnpj
    WHERE ue.user_id = ?
    GROUP BY e.id, e.razao_social, e.cnpj
    ORDER BY empresa";
}

// Funções auxiliares para executar consultas
function executarConsulta($sql, ...$params) {
    global $conexao;
    $stmt = $conexao->prepare($sql);
    if (!empty($params)) {
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    $stmt->close();
    
    // Para a consulta $sql_total_notas que tem múltiplas colunas
    if (isset($row['entradas']) && isset($row['saidas']) && isset($row['nfces'])) {
        return $row['entradas'] + $row['saidas'] + $row['nfces'];
    }
    
    return $row['total'] ?? 0;
}

function executarConsultaArray($sql, ...$params) {
    global $conexao;
    $stmt = $conexao->prepare($sql);
    if (!empty($params)) {
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $data;
}

// Executar consultas
if ($nivel_usuario == 'admin' || $nivel_usuario == 'analista') {
    $total_processos = executarConsulta($sql_total_processos);
    $processos_status = executarConsultaArray($sql_processos_status);
    $total_empresas = executarConsulta($sql_empresas);
    $total_usuarios = executarConsulta($sql_usuarios);
    $total_documentos = executarConsulta($sql_documentos);
    
    // Executar novas consultas de notas
    $total_nfe_entrada = executarConsulta($sql_nfe_entrada);
    $total_nfe_saida = executarConsulta($sql_nfe_saida);
    $total_nfce = executarConsulta($sql_nfce);
    $total_notas = executarConsulta($sql_total_notas);
    
    // Executar consultas adicionais
    $notas_por_mes = executarConsultaArray($sql_notas_mes);
    $valor_notas = executarConsultaArray($sql_valor_notas);
    $notas_por_empresa = executarConsultaArray($sql_notas_empresa);
    $notas_por_competencia = executarConsultaArray($sql_notas_competencia);
    $resumo_empresa = executarConsultaArray($sql_resumo_empresa);
    
    // Outras consultas
    $sql_categorias = "SELECT c.nome, c.cor, COUNT(p.id) as total 
                      FROM gestao_categorias_processo c 
                      LEFT JOIN gestao_processos p ON c.id = p.categoria_id AND p.ativo = 1 
                      WHERE c.ativo = 1 
                      GROUP BY c.id, c.nome, c.cor 
                      ORDER BY total DESC";
    $categorias = executarConsultaArray($sql_categorias);
    
    $sql_prioridades = "SELECT prioridade, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 
                       GROUP BY prioridade 
                       ORDER BY FIELD(prioridade, 'urgente', 'alta', 'media', 'baixa')";
    $prioridades = executarConsultaArray($sql_prioridades);
    
    $sql_recorrentes = "SELECT recorrente, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND recorrente != 'nao' 
                       GROUP BY recorrente 
                       ORDER BY total DESC";
    $recorrentes = executarConsultaArray($sql_recorrentes);
    
    $sql_responsaveis = "SELECT u.nome_completo, COUNT(p.id) as total 
                        FROM gestao_usuarios u 
                        LEFT JOIN gestao_processos p ON u.id = p.responsavel_id AND p.ativo = 1 
                        WHERE u.ativo = 1 
                        GROUP BY u.id, u.nome_completo 
                        ORDER BY total DESC 
                        LIMIT 5";
    $top_responsaveis = executarConsultaArray($sql_responsaveis);
    
    $sql_docs_status = "SELECT status, COUNT(*) as total 
                       FROM gestao_documentacoes_empresa 
                       GROUP BY status";
    $documentos_status = executarConsultaArray($sql_docs_status);
    
    $sql_vencimentos = "SELECT p.titulo, p.data_prevista, u.nome_completo as responsavel, 
                       DATEDIFF(p.data_prevista, CURDATE()) as dias_restantes 
                       FROM gestao_processos p 
                       LEFT JOIN gestao_usuarios u ON p.responsavel_id = u.id 
                       WHERE p.ativo = 1 AND p.status NOT IN ('concluido', 'cancelado') 
                       AND p.data_prevista IS NOT NULL 
                       AND p.data_prevista >= CURDATE() 
                       ORDER BY p.data_prevista ASC 
                       LIMIT 5";
    $proximos_vencimentos = executarConsultaArray($sql_vencimentos);
} else {
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
    
    // Executar consultas adicionais
    $notas_por_mes = executarConsultaArray($sql_notas_mes, $usuario_id, $usuario_id, $usuario_id);
    $valor_notas = executarConsultaArray($sql_valor_notas, $usuario_id, $usuario_id, $usuario_id);
    $notas_por_empresa = executarConsultaArray($sql_notas_empresa, $usuario_id, $usuario_id, $usuario_id);
    $notas_por_competencia = executarConsultaArray($sql_notas_competencia, $usuario_id, $usuario_id, $usuario_id);
    $resumo_empresa = executarConsultaArray($sql_resumo_empresa, $usuario_id);
    
    // Outras consultas
    $sql_categorias = "SELECT c.nome, c.cor, COUNT(p.id) as total 
                      FROM gestao_categorias_processo c 
                      LEFT JOIN gestao_processos p ON c.id = p.categoria_id AND p.ativo = 1 AND p.responsavel_id = ?
                      WHERE c.ativo = 1 
                      GROUP BY c.id, c.nome, c.cor 
                      ORDER BY total DESC";
    $categorias = executarConsultaArray($sql_categorias, $usuario_id);
    
    $sql_prioridades = "SELECT prioridade, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND responsavel_id = ? 
                       GROUP BY prioridade 
                       ORDER BY FIELD(prioridade, 'urgente', 'alta', 'media', 'baixa')";
    $prioridades = executarConsultaArray($sql_prioridades, $usuario_id);
    
    $sql_recorrentes = "SELECT recorrente, COUNT(*) as total 
                       FROM gestao_processos 
                       WHERE ativo = 1 AND recorrente != 'nao' AND responsavel_id = ? 
                       GROUP BY recorrente 
                       ORDER BY total DESC";
    $recorrentes = executarConsultaArray($sql_recorrentes, $usuario_id);
    
    // CORREÇÃO: Para $sql_responsaveis que tem 2 placeholders
    $top_responsaveis = executarConsultaArray($sql_responsaveis, $usuario_id, $usuario_id);
    
    $sql_docs_status = "SELECT status, COUNT(*) as total 
                       FROM gestao_documentacoes_empresa 
                       WHERE usuario_recebimento_id = ? 
                       GROUP BY status";
    $documentos_status = executarConsultaArray($sql_docs_status, $usuario_id);
    
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
    $proximos_vencimentos = executarConsultaArray($sql_vencimentos, $usuario_id);
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

// Organizar dados por empresa
$dados_empresas = [];
foreach ($notas_por_empresa as $item) {
    $empresa_id = $item['empresa_id'];
    $empresa_nome = $item['empresa'];
    
    if (!isset($dados_empresas[$empresa_id])) {
        $dados_empresas[$empresa_id] = [
            'id' => $empresa_id,
            'nome' => $empresa_nome,
            'tipos' => []
        ];
    }
    
    $dados_empresas[$empresa_id]['tipos'][$item['tipo']] = [
        'quantidade' => $item['quantidade'] ?? 0,
        'valor_total' => $item['valor_total'] ?? 0
    ];
}

// Organizar dados por competência
$dados_competencias = [];
foreach ($notas_por_competencia as $item) {
    $competencia = $item['competencia_formatada'];
    $empresa = $item['empresa'];
    
    if (!isset($dados_competencias[$competencia])) {
        $dados_competencias[$competencia] = [];
    }
    
    if (!isset($dados_competencias[$competencia][$empresa])) {
        $dados_competencias[$competencia][$empresa] = [];
    }
    
    $dados_competencias[$competencia][$empresa][$item['tipo']] = [
        'quantidade' => $item['quantidade'] ?? 0,
        'valor_total' => $item['valor_total'] ?? 0,
        'competencia_mes' => $item['competencia_mes'],
        'competencia_ano' => $item['competencia_ano']
    ];
}

// Ordenar competências por data
uksort($dados_competencias, function($a, $b) {
    $partesA = explode('/', $a);
    $partesB = explode('/', $b);
    
    if (count($partesA) == 2 && count($partesB) == 2) {
        $meses = [
            'Jan' => 1, 'Fev' => 2, 'Mar' => 3, 'Abr' => 4, 'Mai' => 5, 'Jun' => 6,
            'Jul' => 7, 'Ago' => 8, 'Set' => 9, 'Out' => 10, 'Nov' => 11, 'Dez' => 12
        ];
        
        $mesA = $meses[$partesA[0]] ?? 0;
        $anoA = (int)$partesA[1];
        $mesB = $meses[$partesB[0]] ?? 0;
        $anoB = (int)$partesB[1];
        
        if ($anoA != $anoB) {
            return $anoA - $anoB;
        }
        return $mesA - $mesB;
    }
    
    return 0;
});

// Processar dados do resumo por empresa
$resumo_consolidado = [];
if (!empty($resumo_empresa)) {
    foreach ($resumo_empresa as $item) {
        $empresa_id = $item['empresa_id'];
        
        $resumo_consolidado[$empresa_id] = [
            'empresa_id' => $empresa_id,
            'empresa' => $item['empresa'],
            'cnpj' => $item['cnpj'],
            'total_nfe_entrada' => $item['total_nfe_entrada'] ?? 0,
            'total_nfe_saida' => $item['total_nfe_saida'] ?? 0,
            'total_nfce' => $item['total_nfce'] ?? 0,
            'valor_nfe_entrada' => $item['valor_nfe_entrada'] ?? 0,
            'valor_nfe_saida' => $item['valor_nfe_saida'] ?? 0,
            'valor_nfce' => $item['valor_nfce'] ?? 0,
            'total_notas' => ($item['total_nfe_entrada'] ?? 0) + ($item['total_nfe_saida'] ?? 0) + ($item['total_nfce'] ?? 0),
            'valor_total' => ($item['valor_nfe_entrada'] ?? 0) + ($item['valor_nfe_saida'] ?? 0) + ($item['valor_nfce'] ?? 0)
        ];
    }
    
    // Ordenar por valor total
    usort($resumo_consolidado, function($a, $b) {
        return $b['valor_total'] - $a['valor_total'];
    });
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
    if ($item['valor_total'] > 0) {
        $valores_formatados[] = [
            'tipo' => $item['tipo'],
            'valor_total' => number_format($item['valor_total'], 2, ',', '.'),
            'quantidade' => $item['quantidade']
        ];
    }
}

// Se não houver dados de valor, mostrar zeros
if (empty($valores_formatados)) {
    $valores_formatados = [
        ['tipo' => 'NFe Entrada', 'valor_total' => '0,00', 'quantidade' => 0],
        ['tipo' => 'NFe Saída', 'valor_total' => '0,00', 'quantidade' => 0],
        ['tipo' => 'NFCe', 'valor_total' => '0,00', 'quantidade' => 0]
    ];
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
        /* O CSS permanece o mesmo */
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
            max-width: 1600px;
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

        .grid-4-col {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
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

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--gray-light);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }

        .data-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--gray-light);
        }

        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .tipo-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .tipo-indicator.nfe-entrada { background: #10B981; }
        .tipo-indicator.nfe-saida { background: #3B82F6; }
        .tipo-indicator.nfce { background: #8B5CF6; }

        .tab-container {
            margin-bottom: 2rem;
        }

        .tab-buttons {
            display: flex;
            border-bottom: 2px solid var(--gray-light);
            margin-bottom: 1.5rem;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .tab-button:hover {
            color: var(--primary);
        }

        .tab-button.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .valor-negativo {
            color: #EF4444 !important;
        }

        .valor-positivo {
            color: #10B981 !important;
        }

        @media (max-width: 1200px) {
            .grid-4-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 1024px) {
            .grid-3-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .charts-grid,
            .grid-2-col,
            .grid-3-col,
            .grid-4-col {
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
            
            .tab-buttons {
                overflow-x: auto;
                flex-wrap: nowrap;
            }
        }
    </style>
</head>
<body>
    <!-- O HTML permanece o mesmo -->
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

        <!-- Tabs para diferentes visões -->
        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-button active" data-tab="dashboard">Dashboard</button>
                <button class="tab-button" data-tab="empresas">Por Empresa</button>
                <button class="tab-button" data-tab="competencias">Por Competência</button>
                <button class="tab-button" data-tab="analise">Análise Detalhada</button>
            </div>

            <!-- Tab Dashboard -->
            <div id="dashboard-tab" class="tab-content active">
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
            </div>

            <!-- Tab Por Empresa -->
            <div id="empresas-tab" class="tab-content">
                <div class="info-card">
                    <div class="info-header">
                        <h3 class="info-title">
                            <i class="fas fa-building"></i>
                            Notas por Empresa e Tipo
                        </h3>
                    </div>
                    <div class="info-body">
                        <?php if (!empty($dados_empresas)): ?>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Empresa</th>
                                            <th>Tipo</th>
                                            <th>Quantidade</th>
                                            <th>Valor Total</th>
                                            <th>Valor Médio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dados_empresas as $empresa_id => $empresa_data): ?>
                                            <?php 
                                            $tipos = $empresa_data['tipos'] ?? [];
                                            $rowspan = count($tipos);
                                            $first = true;
                                            foreach ($tipos as $tipo => $dados):
                                                if ($dados['quantidade'] > 0):
                                            ?>
                                            <tr>
                                                <?php if ($first): ?>
                                                <td rowspan="<?php echo $rowspan; ?>">
                                                    <strong><?php echo htmlspecialchars($empresa_data['nome']); ?></strong>
                                                </td>
                                                <?php endif; ?>
                                                <td>
                                                    <span class="tipo-indicator <?php echo strtolower(str_replace(' ', '-', $tipo)); ?>"></span>
                                                    <?php echo $tipo; ?>
                                                </td>
                                                <td><?php echo $dados['quantidade']; ?></td>
                                                <td>R$ <?php echo number_format($dados['valor_total'], 2, ',', '.'); ?></td>
                                                <td>
                                                    <?php if ($dados['quantidade'] > 0): ?>
                                                        R$ <?php echo number_format($dados['valor_total'] / $dados['quantidade'], 2, ',', '.'); ?>
                                                    <?php else: ?>
                                                        R$ 0,00
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php 
                                                $first = false;
                                                endif;
                                            endforeach; 
                                            ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-building"></i>
                                <p>Nenhuma nota encontrada por empresa</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Resumo por Empresa -->
                <?php if (!empty($resumo_consolidado)): ?>
                <div class="info-card">
                    <div class="info-header">
                        <h3 class="info-title">
                            <i class="fas fa-chart-bar"></i>
                            Resumo por Empresa
                        </h3>
                    </div>
                    <div class="info-body">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>CNPJ</th>
                                        <th>NFes Entrada</th>
                                        <th>NFes Saída</th>
                                        <th>NFCes</th>
                                        <th>Total Notas</th>
                                        <th>Valor Entrada</th>
                                        <th>Valor Saída</th>
                                        <th>Valor NFCe</th>
                                        <th>Valor Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumo_consolidado as $empresa): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($empresa['empresa']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($empresa['cnpj']); ?></td>
                                            <td><?php echo $empresa['total_nfe_entrada']; ?></td>
                                            <td><?php echo $empresa['total_nfe_saida']; ?></td>
                                            <td><?php echo $empresa['total_nfce']; ?></td>
                                            <td><strong><?php echo $empresa['total_notas']; ?></strong></td>
                                            <td>R$ <?php echo number_format($empresa['valor_nfe_entrada'], 2, ',', '.'); ?></td>
                                            <td>R$ <?php echo number_format($empresa['valor_nfe_saida'], 2, ',', '.'); ?></td>
                                            <td>R$ <?php echo number_format($empresa['valor_nfce'], 2, ',', '.'); ?></td>
                                            <td class="valor-positivo"><strong>R$ <?php echo number_format($empresa['valor_total'], 2, ',', '.'); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab Por Competência -->
            <div id="competencias-tab" class="tab-content">
                <div class="info-card">
                    <div class="info-header">
                        <h3 class="info-title">
                            <i class="fas fa-calendar-alt"></i>
                            Notas por Competência, Empresa e Tipo
                        </h3>
                    </div>
                    <div class="info-body">
                        <?php if (!empty($dados_competencias)): ?>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Competência</th>
                                            <th>Empresa</th>
                                            <th>Tipo</th>
                                            <th>Quantidade</th>
                                            <th>Valor Total</th>
                                            <th>Valor Médio</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dados_competencias as $competencia => $empresas): ?>
                                            <?php 
                                            $competencia_rowspan = 0;
                                            foreach ($empresas as $empresa => $tipos) {
                                                $competencia_rowspan += count($tipos);
                                            }
                                            $first_competencia = true;
                                            ?>
                                            <?php foreach ($empresas as $empresa => $tipos): ?>
                                                <?php 
                                                $empresa_rowspan = count($tipos);
                                                $first_empresa = true;
                                                ?>
                                                <?php foreach ($tipos as $tipo => $dados): ?>
                                                    <?php if ($dados['quantidade'] > 0): ?>
                                                    <tr>
                                                        <?php if ($first_competencia): ?>
                                                        <td rowspan="<?php echo $competencia_rowspan; ?>">
                                                            <strong><?php echo $competencia; ?></strong>
                                                        </td>
                                                        <?php 
                                                            $first_competencia = false;
                                                        endif; ?>
                                                        <?php if ($first_empresa): ?>
                                                        <td rowspan="<?php echo $empresa_rowspan; ?>">
                                                            <?php echo htmlspecialchars($empresa); ?>
                                                        </td>
                                                        <?php 
                                                            $first_empresa = false;
                                                        endif; ?>
                                                        <td>
                                                            <span class="tipo-indicator <?php echo strtolower(str_replace(' ', '-', $tipo)); ?>"></span>
                                                            <?php echo $tipo; ?>
                                                        </td>
                                                        <td><?php echo $dados['quantidade']; ?></td>
                                                        <td>R$ <?php echo number_format($dados['valor_total'], 2, ',', '.'); ?></td>
                                                        <td>
                                                            <?php if ($dados['quantidade'] > 0): ?>
                                                                R$ <?php echo number_format($dados['valor_total'] / $dados['quantidade'], 2, ',', '.'); ?>
                                                            <?php else: ?>
                                                                R$ 0,00
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <p>Nenhuma nota encontrada por competência</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab Análise Detalhada -->
            <div id="analise-tab" class="tab-content">
                <div class="info-card">
                    <div class="info-header">
                        <h3 class="info-title">
                            <i class="fas fa-search"></i>
                            Análise Comparativa
                        </h3>
                    </div>
                    <div class="info-body">
                        <div class="grid-3-col">
                            <!-- Comparação NFe Entrada vs Saída -->
                            <div class="nota-item">
                                <h4>Saldo NFe (Entrada - Saída)</h4>
                                <?php 
                                $saldo_quantidade = $total_nfe_entrada - $total_nfe_saida;
                                $valor_entrada = 0;
                                $valor_saida = 0;
                                
                                foreach ($valor_notas as $item) {
                                    if ($item['tipo'] == 'NFe Entrada') $valor_entrada = $item['valor_total'] ?? 0;
                                    if ($item['tipo'] == 'NFe Saída') $valor_saida = $item['valor_total'] ?? 0;
                                }
                                
                                $saldo_valor = $valor_entrada - $valor_saida;
                                ?>
                                <div class="quantidade <?php echo $saldo_quantidade >= 0 ? 'valor-positivo' : 'valor-negativo'; ?>">
                                    <?php echo $saldo_quantidade; ?>
                                </div>
                                <div class="valor-nota <?php echo $saldo_valor >= 0 ? 'valor-positivo' : 'valor-negativo'; ?>">
                                    R$ <?php echo number_format($saldo_valor, 2, ',', '.'); ?>
                                </div>
                                <div class="desc-valor">
                                    Entrada: <?php echo $total_nfe_entrada; ?> | Saída: <?php echo $total_nfe_saida; ?>
                                </div>
                            </div>

                            <!-- Média por Nota -->
                            <div class="nota-item">
                                <h4>Valor Médio por Nota</h4>
                                <?php 
                                $total_valor = 0;
                                $total_quantidade = 0;
                                foreach ($valor_notas as $item) {
                                    $total_valor += $item['valor_total'] ?? 0;
                                    $total_quantidade += $item['quantidade'] ?? 0;
                                }
                                
                                $media = $total_quantidade > 0 ? $total_valor / $total_quantidade : 0;
                                ?>
                                <div class="quantidade"><?php echo $total_quantidade; ?></div>
                                <div class="valor-nota">R$ <?php echo number_format($media, 2, ',', '.'); ?></div>
                                <div class="desc-valor">
                                    Total: R$ <?php echo number_format($total_valor, 2, ',', '.'); ?>
                                </div>
                            </div>

                            <!-- Distribuição Percentual -->
                            <div class="nota-item">
                                <h4>Distribuição Percentual</h4>
                                <?php 
                                $percent_entrada = $total_quantidade > 0 ? ($total_nfe_entrada / $total_quantidade * 100) : 0;
                                $percent_saida = $total_quantidade > 0 ? ($total_nfe_saida / $total_quantidade * 100) : 0;
                                $percent_nfce = $total_quantidade > 0 ? ($total_nfce / $total_quantidade * 100) : 0;
                                ?>
                                <div class="quantidade">100%</div>
                                <div style="margin: 10px 0;">
                                    <div style="display: flex; height: 20px; border-radius: 10px; overflow: hidden;">
                                        <div style="background: #10B981; width: <?php echo $percent_entrada; ?>%;" title="NFe Entrada"></div>
                                        <div style="background: #3B82F6; width: <?php echo $percent_saida; ?>%;" title="NFe Saída"></div>
                                        <div style="background: #8B5CF6; width: <?php echo $percent_nfce; ?>%;" title="NFCe"></div>
                                    </div>
                                </div>
                                <div class="desc-valor">
                                    Ent: <?php echo round($percent_entrada, 1); ?>% | 
                                    Sai: <?php echo round($percent_saida, 1); ?>% | 
                                    NFCe: <?php echo round($percent_nfce, 1); ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Análise Temporal -->
                <?php if (!empty($dados_competencias)): ?>
                <div class="info-card">
                    <div class="info-header">
                        <h3 class="info-title">
                            <i class="fas fa-chart-area"></i>
                            Evolução Temporal
                        </h3>
                    </div>
                    <div class="info-body">
                        <div class="chart-container">
                            <canvas id="evolucaoTemporalChart"></canvas>
                        </div>
                    </div>
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
            const competenciasData = <?php echo json_encode($dados_competencias); ?>;
            
            // Configurar tabs
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    button.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });

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

            // Gráfico de Evolução Temporal (se existir dados de competência)
            const evolucaoTemporalChart = document.getElementById('evolucaoTemporalChart');
            if (evolucaoTemporalChart) {
                const evolucaoCtx = evolucaoTemporalChart.getContext('2d');
                
                // Preparar dados para o gráfico
                // Vamos agrupar por competência e somar todos os valores
                const competencias = Object.keys(competenciasData).reverse();
                const tipos = ['NFe Entrada', 'NFe Saída', 'NFCe'];
                
                // Inicializar arrays com zeros
                const evolucaoData = {};
                tipos.forEach(tipo => {
                    evolucaoData[tipo] = competencias.map(() => 0);
                });
                
                // Preencher com dados reais
                competencias.forEach((competencia, index) => {
                    const empresas = competenciasData[competencia];
                    if (empresas) {
                        Object.values(empresas).forEach(empresa => {
                            tipos.forEach(tipo => {
                                if (empresa[tipo]) {
                                    evolucaoData[tipo][index] += empresa[tipo].valor_total || 0;
                                }
                            });
                        });
                    }
                });
                
                const evolucaoDatasets = tipos.map(tipo => {
                    const cores = {
                        'NFe Entrada': '#10B981',
                        'NFe Saída': '#3B82F6',
                        'NFCe': '#8B5CF6'
                    };
                    
                    return {
                        label: tipo,
                        data: evolucaoData[tipo],
                        backgroundColor: cores[tipo] || '#6B7280',
                        borderColor: cores[tipo] || '#6B7280',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    };
                });

                new Chart(evolucaoCtx, {
                    type: 'line',
                    data: {
                        labels: competencias,
                        datasets: evolucaoDatasets
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
                                    text: 'Valor Total (R$)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Competência'
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.parsed.y;
                                        return `${context.dataset.label}: R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>