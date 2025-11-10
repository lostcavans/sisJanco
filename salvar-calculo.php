<?php
session_start();
include("config.php");

// Função para verificar autenticação (se não estiver no config.php)
function verificarAutenticacao() {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        return false;
    }
    
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_username'])) {
        return false;
    }
    
    if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > 1800)) {
        return false;
    }
    
    $_SESSION['ultimo_acesso'] = time();
    
    return true;
}

if (!verificarAutenticacao()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$dados = $_POST;

try {
    $conexao->begin_transaction();
    
    // Criar grupo de cálculo
    $query = "INSERT INTO grupos_calculo (usuario_id, descricao, nota_fiscal_id, informacoes_adicionais, competencia) VALUES (?, ?, ?, ?, ?)";
    if ($stmt = $conexao->prepare($query)) {
        $competencia = $dados['competencia'] ?? date('Y-m');
        $stmt->bind_param("isiss", $usuario_id, $dados['descricao'], $dados['nota_id'], $dados['informacoes_adicionais'], $competencia);
        $stmt->execute();
        $grupo_id = $conexao->insert_id;
        $stmt->close();
    }
    
    // Obter valores dos dados
    $valor_produto = floatval($dados['valor_produto'] ?? 0);
    $valor_frete = floatval($dados['valor_frete'] ?? 0);
    $valor_ipi = floatval($dados['valor_ipi'] ?? 0);
    $valor_seguro = floatval($dados['valor_seguro'] ?? 0);
    $valor_icms = floatval($dados['valor_icms'] ?? 0);
    $valor_gnre = floatval($dados['valor_gnre'] ?? 0);
    $aliquota_interestadual = floatval($dados['aliquota_interestadual'] ?? 0);
    $aliquota_interna = floatval($dados['aliquota_interna'] ?? 20.5);
    $mva_original = floatval($dados['mva_original'] ?? 0);
    $mva_cnae = floatval($dados['mva_cnae'] ?? 0);
    $aliquota_reducao = floatval($dados['aliquota_reducao'] ?? 0);
    $diferencial_aliquota = floatval($dados['diferencial_aliquota'] ?? 0);
    $regime_fornecedor = $dados['regime_fornecedor'] ?? '3';
    $tipo_calculo = $dados['tipo_calculo'] ?? 'icms_st';
    $tipo_credito_icms = isset($dados['tipo_credito_icms']) ? 'manual' : 'nota';
    $empresa_regular = $dados['empresa_regular'] ?? 'S';
    
    // Calcular MVA Ajustada (se aplicável)
    $mva_ajustada = 0;
    if ($regime_fornecedor == '3') { // Regime Normal
        $mva_ajustada = ((1 - ($aliquota_interestadual/100)) / (1 - ($aliquota_interna/100)) * (1 + ($mva_original/100))) - 1;
        $mva_ajustada = $mva_ajustada * 100; // Converter para porcentagem
    }

    // Calcular ICMS ST conforme o regime do fornecedor
    $base_calculo = $valor_produto + $valor_frete + $valor_seguro;
    $icms_st = 0;

    if ($regime_fornecedor == '3') { // Regime Normal
        $base_st = $base_calculo + $valor_ipi;
        $icms_st = ($base_st * (1 + ($mva_ajustada/100)) * ($aliquota_interna/100)) - $valor_icms - $valor_gnre;
    } else { // Simples Nacional
        $base_st = $base_calculo + $valor_ipi;
        $icms_st = ($base_st * (1 + ($mva_original/100)) * ($aliquota_interna/100)) - $valor_icms - $valor_gnre;
    }

    // Calcular outros tipos de ICMS
    $icms_tributado_simples_regular = 0;
    $icms_tributado_simples_irregular = 0;
    $icms_tributado_real = 0;
    $icms_uso_consumo = 0;
    $icms_reducao = 0;
    $icms_reducao_sn = 0;
    $icms_reducao_st_sn = 0;

    // ICMS Tributado Simples (empresa regular)
    $icms_tributado_simples_regular = (($base_calculo + $valor_ipi + $valor_frete + $valor_seguro - $valor_icms) / (1 - ($aliquota_interna/100))) * ($diferencial_aliquota/100);

    // ICMS Tributado Simples (empresa irregular)
    $icms_tributado_simples_irregular = (($base_calculo + $valor_ipi + $valor_frete + $valor_seguro - $valor_icms) / (1 - ($aliquota_interna/100))) * (($aliquota_interna - $aliquota_interestadual)/100);

    // ICMS Tributado Real/Presumido
    $icms_tributado_real = (($base_calculo + $valor_ipi + $valor_frete + $valor_seguro - $valor_icms) / (1 - ($aliquota_interna/100))) * (1 + ($mva_cnae/100)) * ($aliquota_interna/100) - $valor_icms;

    // ICMS Uso e Consumo/Ativo Fixo
    $icms_uso_consumo = (($base_calculo + $valor_ipi + $valor_frete + $valor_seguro - $valor_icms) / (1 - ($aliquota_interna/100))) * (($aliquota_interna - $aliquota_interestadual)/100);

    // ICMS Redução
    $icms_reducao = (($base_calculo + $valor_ipi + $valor_frete + $valor_seguro - $valor_icms) / (1 - ($aliquota_interna/100))) * (1 + ($mva_cnae/100)) * ($aliquota_reducao/100) - $valor_icms;
    
    // ICMS Redução SN - FÓRMULA CORRETA
    if ($aliquota_interna > 0) {
        // Base para redução SN (valor total dos produtos + IPI + frete + seguro - ICMS)
        $base_reducao_sn = $valor_produto + $valor_ipi + $valor_frete + $valor_seguro - $valor_icms;
        
        // Fórmula CORRETA do ICMS Redução SN
        $base_ajustada = $base_reducao_sn / (1 - ($aliquota_interna/100));
        $coeficiente_reducao = ($aliquota_reducao/100) / ($aliquota_interna/100);
        $diferencial_aliquotas = ($aliquota_interna - $aliquota_interestadual) / 100;
        
        $icms_reducao_sn = $base_ajustada * $coeficiente_reducao * $diferencial_aliquotas;
        
        // Garantir que o valor não seja negativo
        if ($icms_reducao_sn < 0) {
            $icms_reducao_sn = 0;
        }
    }

    if ($regime_fornecedor == '3' && $aliquota_reducao > 0) {
        // Fórmula: ((Valor Produto + Valor IPI + Valor Frete + Valor Seguro) × (1 + MVA Ajustada) x (aliquota redução) × Alíquota Interna) - Crédito ICMS - GNRE
        $base_st_sn = $valor_produto + $valor_ipi + $valor_frete + $valor_seguro;
        $icms_reducao_st_sn = ($base_st_sn * (1 + ($mva_ajustada/100)) * ($aliquota_reducao/100) * ($aliquota_interna/100)) - $valor_icms - $valor_gnre;
        
        if ($icms_reducao_st_sn < 0) {
            $icms_reducao_st_sn = 0;
        }
    }
    
    // Inserir cálculo completo
    $query = "
        INSERT INTO calculos_fronteira (
            usuario_id, grupo_id, descricao, valor_produto, valor_frete, valor_ipi, 
            valor_seguro, valor_icms, aliquota_interna, aliquota_interestadual,
            regime_fornecedor, tipo_credito_icms, icms_st, mva_original, mva_cnae,
            aliquota_reducao, diferencial_aliquota, valor_gnre, tipo_calculo,
            icms_tributado_simples_regular, icms_tributado_simples_irregular,
            icms_tributado_real, icms_uso_consumo, icms_reducao, mva_ajustada,
            icms_reducao_sn, icms_reducao_st_sn, empresa_regular, competencia
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param(
            "iisddddddssdddddsddddddsss",  // ATUALIZADO: adicione 'd' para o novo campo
            $usuario_id, $grupo_id, $descricao, $valor_produto, $valor_frete, 
            $valor_ipi, $valor_seguro, $valor_icms, $aliquota_interna, 
            $aliquota_interestadual, $regime_fornecedor, $tipo_credito_icms, 
            $icms_st, $mva_original, $mva_cnae, $aliquota_reducao,
            $diferencial_aliquota, $valor_gnre, $tipo_calculo,
            $icms_tributado_simples_regular, $icms_tributado_simples_irregular,
            $icms_tributado_real, $icms_uso_consumo, $icms_reducao, 
            $mva_ajustada, $icms_reducao_sn, $icms_reducao_st_sn, $empresa_regular, $competencia
        );
        $stmt->execute();
        $calculo_id = $conexao->insert_id;
        $stmt->close();
    }
    
    // Salvar relação entre grupo de cálculo e produtos (se houver)
    if (!empty($dados['produtos_ids'])) {
        $produtos_ids = is_array($dados['produtos_ids']) ? $dados['produtos_ids'] : explode(',', $dados['produtos_ids']);
        $query = "INSERT INTO grupo_calculo_produtos (grupo_calculo_id, produto_id) VALUES (?, ?)";
        if ($stmt = $conexao->prepare($query)) {
            foreach ($produtos_ids as $produto_id) {
                $stmt->bind_param("ii", $grupo_id, $produto_id);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
    
    $conexao->commit();
    
    header("Location: calculo-fronteira.php?action=visualizar&id=" . $calculo_id . "&competencia=" . $competencia);
    exit;
    
} catch (Exception $e) {
    $conexao->rollback();
    header("Location: fronteira-fiscal.php?competencia=" . ($dados['competencia'] ?? date('Y-m')) . "&error=Erro ao salvar cálculo: " . urlencode($e->getMessage()));
    exit;
}