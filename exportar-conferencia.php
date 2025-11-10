<?php
session_start();

// Verificar autenticação
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

// Verificar se existem resultados na sessão
if (!isset($_SESSION['resultados_comparacao']) || empty($_SESSION['resultados_comparacao'])) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

$resultados = $_SESSION['resultados_comparacao'];
$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');

// Nome do mês para o arquivo
$nomes_meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$nome_mes = $nomes_meses[(int)$mes] ?? '';

// Configurar headers para download do arquivo
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="conferencia_fiscal_' . $nome_mes . '_' . $ano . '.csv"');

// Abrir output stream
$output = fopen('php://output', 'w');

// Adicionar BOM para UTF-8 (para Excel)
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Escrever cabeçalho
fputcsv($output, [
    'Número NF',
    'Status',
    'Valor no Relatório (R$)',
    'Valor no Sistema (R$)',
    'Diferença (R$)',
    'Emitente',
    'Data Emissão'
], ';');

// Escrever dados
foreach ($resultados as $numero => $resultado) {
    // Determinar status
    if ($resultado['status'] === 'encontrada') {
        $status = 'Conferida';
    } elseif ($resultado['status'] === 'valor_diferente') {
        $status = 'Divergente';
    } elseif ($resultado['status'] === 'nao_encontrada_banco') {
        $status = 'Não no Banco';
    } else {
        $status = 'Não no Relatório';
    }
    
    // Calcular diferença se aplicável
    $diferenca = '';
    if ($resultado['status'] === 'valor_diferente') {
        $diferenca = number_format(abs($resultado['valor_relatorio'] - $resultado['dados']['valor']), 2, ',', '.');
    }
    
    // Formatar valores
    $valor_relatorio = isset($resultado['valor_relatorio']) ? number_format($resultado['valor_relatorio'], 2, ',', '.') : '';
    $valor_sistema = isset($resultado['dados']['valor']) ? number_format($resultado['dados']['valor'], 2, ',', '.') : '';
    
    // Data de emissão
    $data_emissao = isset($resultado['dados']['data_emissao']) ? date('d/m/Y', strtotime($resultado['dados']['data_emissao'])) : '';
    
    // Emitente - AJUSTAR PARA O NOME CORRETO DA COLUNA
    $emitente = isset($resultado['dados']['emitente_nome']) ? $resultado['dados']['emitente_nome'] : '';
    
    fputcsv($output, [
        $numero,
        $status,
        $valor_relatorio,
        $valor_sistema,
        $diferenca,
        $emitente,
        $data_emissao
    ], ';');
}

fclose($output);
exit;