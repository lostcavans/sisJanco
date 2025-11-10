<?php
session_start();
include("config.php");

// Verificar se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar se é uma requisição de comparação com PDF
if (isset($_POST['comparar_pdf']) && $_POST['comparar_pdf'] === 'true') {
    try {
        // Validar competência
        $competencia_mes = $_POST['competencia_mes'] ?? null;
        $competencia_ano = $_POST['competencia_ano'] ?? null;
        
        if (!$competencia_mes || !$competencia_ano) {
            throw new Exception('Competência não informada.');
        }
        
        // Validar dados das notas
        $dados_notas_json = $_POST['dados_notas'] ?? null;
        if (!$dados_notas_json) {
            throw new Exception('Dados das notas não recebidos.');
        }
        
        $dados_notas = json_decode($dados_notas_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erro ao decodificar dados das notas: ' . json_last_error_msg());
        }
        
        // Comparar dados
        $resultados_comparacao = [];
        $valores_diferentes = [];
        
        // Notas encontradas no PDF
        $notas_pdf = array_column($dados_notas, 'numero');
        
        // Buscar notas no banco de dados para o período
        $data_inicio = $competencia_ano . '-' . str_pad($competencia_mes, 2, '0', STR_PAD_LEFT) . '-01';
        $data_fim = date('Y-m-t', strtotime($data_inicio));
        
        // Consulta para NF-e - AJUSTAR COM OS NOMES CORRETOS DAS COLUNAS
        $sql_nfe = "SELECT numero, valor_total as valor, emitente_nome, data_emissao 
                    FROM nfe 
                    WHERE usuario_id = ? AND data_emissao BETWEEN ? AND ?";
        $stmt_nfe = mysqli_prepare($conexao, $sql_nfe);
        if (!$stmt_nfe) {
            throw new Exception('Erro ao preparar consulta NF-e: ' . mysqli_error($conexao));
        }
        mysqli_stmt_bind_param($stmt_nfe, "iss", $usuario_id, $data_inicio, $data_fim);
        if (!mysqli_stmt_execute($stmt_nfe)) {
            throw new Exception('Erro ao executar consulta NF-e: ' . mysqli_stmt_error($stmt_nfe));
        }
        $result_nfe = mysqli_stmt_get_result($stmt_nfe);
        
        // Consulta para NFC-e - AJUSTAR COM OS NOMES CORRETOS DAS COLUNAS
        $sql_nfce = "SELECT numero, valor_total as valor, emitente_nome, data_emissao 
                     FROM nfce 
                     WHERE usuario_id = ? AND data_emissao BETWEEN ? AND ?";
        $stmt_nfce = mysqli_prepare($conexao, $sql_nfce);
        if (!$stmt_nfce) {
            throw new Exception('Erro ao preparar consulta NFC-e: ' . mysqli_error($conexao));
        }
        mysqli_stmt_bind_param($stmt_nfce, "iss", $usuario_id, $data_inicio, $data_fim);
        if (!mysqli_stmt_execute($stmt_nfce)) {
            throw new Exception('Erro ao executar consulta NFC-e: ' . mysqli_stmt_error($stmt_nfce));
        }
        $result_nfce = mysqli_stmt_get_result($stmt_nfce);
        
        // Combinar resultados
        $notas_banco = [];
        while ($row = mysqli_fetch_assoc($result_nfe)) {
            $notas_banco[$row['numero']] = $row;
        }
        while ($row = mysqli_fetch_assoc($result_nfce)) {
            $notas_banco[$row['numero']] = $row;
        }
        
        // Fechar statements
        mysqli_stmt_close($stmt_nfe);
        mysqli_stmt_close($stmt_nfce);
        
        // Comparar notas do PDF com o banco
        foreach ($dados_notas as $nota_pdf) {
            $numero = $nota_pdf['numero'];
            
            if (isset($notas_banco[$numero])) {
                $nota_banco = $notas_banco[$numero];
                $diferenca = abs($nota_pdf['valor'] - $nota_banco['valor']);
                
                if ($diferenca > 0.01) { // Considera diferença se for maior que 1 centavo
                    $resultados_comparacao[$numero] = [
                        'status' => 'valor_diferente',
                        'valor_relatorio' => $nota_pdf['valor'],
                        'dados' => $nota_banco
                    ];
                    $valores_diferentes[$numero] = $resultados_comparacao[$numero];
                } else {
                    $resultados_comparacao[$numero] = [
                        'status' => 'encontrada',
                        'valor_relatorio' => $nota_pdf['valor'],
                        'dados' => $nota_banco
                    ];
                }
            } else {
                // Nota encontrada no PDF mas não no banco (Não no Banco)
                $resultados_comparacao[$numero] = [
                    'status' => 'nao_encontrada_banco',
                    'valor_relatorio' => $nota_pdf['valor'],
                    'dados' => []
                ];
            }
        }
        
        // Verificar notas que estão no banco mas não no PDF (Não no Relatório)
        foreach ($notas_banco as $numero => $nota_banco) {
            if (!in_array($numero, $notas_pdf)) {
                $resultados_comparacao[$numero] = [
                    'status' => 'nao_encontrada_relatorio',
                    'valor_relatorio' => null,
                    'dados' => $nota_banco
                ];
            }
        }
        
        // Salvar resultados na sessão
        $_SESSION['resultados_comparacao'] = $resultados_comparacao;
        $_SESSION['valores_diferentes'] = $valores_diferentes;
        $_SESSION['total_notas_processadas'] = count($dados_notas);
        
        echo json_encode([
            'success' => true,
            'total' => count($dados_notas),
            'conferidas' => count(array_filter($resultados_comparacao, function($r) { return $r['status'] === 'encontrada'; })),
            'divergentes' => count($valores_diferentes),
            'nao_banco' => count(array_filter($resultados_comparacao, function($r) { return $r['status'] === 'nao_encontrada_banco'; })),
            'nao_relatorio' => count(array_filter($resultados_comparacao, function($r) { return $r['status'] === 'nao_encontrada_relatorio'; }))
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Se não for uma requisição de comparação com PDF, retornar erro
echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);
exit;