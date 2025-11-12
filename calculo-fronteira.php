<?php
session_start();

// Função para verificar autenticação
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

// Verifica autenticação
if (!verificarAutenticacao()) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// Incluir config.php - a conexão mysqli já está estabelecida como $conexao
include("config.php");

if ($conexao->connect_error) {
    header("Location: fronteira-fiscal.php?error=Erro de conexão com o banco de dados");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$action = $_GET['action'] ?? '';

// Processar cálculo final (vindo do formulário de cálculo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'salvar_calculo') {
    // Receber dados do formulário
    $nota_id = $_POST['nota_id'] ?? null;
    $competencia = $_POST['competencia'] ?? date('Y-m');
    $descricao = $_POST['descricao'] ?? '';
    $informacoes_adicionais = $_POST['informacoes_adicionais'] ?? '';
    $valor_produto = floatval($_POST['valor_produto'] ?? 0);
    $valor_frete = floatval($_POST['valor_frete'] ?? 0);
    $valor_ipi = floatval($_POST['valor_ipi'] ?? 0);
    $valor_seguro = floatval($_POST['valor_seguro'] ?? 0);
    $valor_icms = floatval($_POST['valor_icms'] ?? 0);
    $valor_gnre = floatval($_POST['valor_gnre'] ?? 0);
    $aliquota_interestadual = floatval($_POST['aliquota_interestadual'] ?? 0);
    $aliquota_interna = floatval($_POST['aliquota_interna'] ?? 20.5);
    $mva_original = floatval($_POST['mva_original'] ?? 0);
    $mva_cnae = floatval($_POST['mva_cnae'] ?? 0);
    $aliquota_reducao = floatval($_POST['aliquota_reducao'] ?? 0);
    $diferencial_aliquota = floatval($_POST['diferencial_aliquota'] ?? 0);
    $regime_fornecedor = $_POST['regime_fornecedor'] ?? '3';
    $tipo_calculo = $_POST['tipo_calculo'] ?? 'icms_st';
    $tipo_credito_icms = isset($_POST['tipo_credito_icms']) ? 'manual' : 'nota';
    $produtos_ids = $_POST['produtos'] ?? [];
    $competencia_nota = $competencia; // padrão é a competência do formulário
    if ($nota_id) {
        $query_nota = "SELECT competencia_ano, competencia_mes FROM nfe WHERE id = ?";
        if ($stmt_nota = $conexao->prepare($query_nota)) {
            $stmt_nota->bind_param("i", $nota_id);
            $stmt_nota->execute();
            $result_nota = $stmt_nota->get_result();
            if ($nota_data = $result_nota->fetch_assoc()) {
                $competencia_nota = $nota_data['competencia_ano'] . '-' . str_pad($nota_data['competencia_mes'], 2, '0', STR_PAD_LEFT);
            }
            $stmt_nota->close();
        }
    }
    $empresa_regular = $_POST['empresa_regular'] ?? 'S';

    
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
        // ((v.prod + v.ipi + v.frete + v.seguro) (1 + mva ajustada) (aliq. interna) - crédito icms) - GNRE
        $base_st = $base_calculo + $valor_ipi;
        $icms_st = ($base_st * (1 + ($mva_ajustada/100)) * ($aliquota_interna/100)) - $valor_icms - $valor_gnre;
    } else { // Simples Nacional
        // ((v.prod + v.ipi + v.frete + v.seguro) (1 + mva original) (aliq. interna) - crédito icms) - GNRE
        $base_st = $base_calculo + $valor_ipi;
        $icms_st = ($base_st * (1 + ($mva_original/100)) * ($aliquota_interna/100)) - $valor_icms - $valor_gnre;
    }

    // Calcular outros tipos de ICMS
    $icms_tributado_simples_regular = 0;
    $icms_tributado_simples_irregular = 0;
    $icms_tributado_real = 0;
    $icms_uso_consumo = 0;
    $icms_reducao = 0;
    $valor_total_cesta_basica = 0;
    $carga_tributaria_cesta = 0;
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
    
    // Adicione esta variável após as outras definições
    $empresa_regular = $_POST['empresa_regular'] ?? 'S';

    // Calcular ICMS Redução SN (após os outros cálculos)
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
    // ICMS Redução ST SN - NOVO CÁLCULO
    if ($regime_fornecedor == '3' && $aliquota_reducao > 0 && $aliquota_interna > 0) {
        // Fórmula: ((Valor Produto + Valor IPI + Valor Frete + Valor Seguro) × (1 + MVA Ajustada) × Alíquota Redução × Alíquota Interna) - Crédito ICMS - GNRE
        $base_st_sn = $valor_produto + $valor_ipi + $valor_frete + $valor_seguro;
        
        // Calcular MVA Ajustada se não foi calculada ainda
        if ($mva_ajustada == 0 && $regime_fornecedor == '3') {
            $mva_ajustada = ((1 - ($aliquota_interestadual/100)) / (1 - ($aliquota_interna/100)) * (1 + ($mva_original/100))) - 1;
            $mva_ajustada = $mva_ajustada * 100;
        }
        
        $icms_reducao_st_sn = ($base_st_sn * (1 + ($mva_ajustada/100)) * ($aliquota_reducao/100) * ($aliquota_interna/100)) - $valor_icms - $valor_gnre;
        
        // Garantir que o valor não seja negativo
        if ($icms_reducao_st_sn < 0) {
            $icms_reducao_st_sn = 0;
        }
    }

    // Inserir no banco de dados
    try {
        // Iniciar transação
        $conexao->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

        // Sanitizar e definir valores padrão
        $descricao = trim($_POST['descricao'] ?? 'Cálculo sem descrição');
        $informacoes_adicionais = trim($_POST['informacoes_adicionais'] ?? '');
        $nota_id = $_POST['nota_id'] ?? null;
        $competencia_nota = $competencia_nota ?? date('Y-m');

        if (empty($nota_id)) {
            $nota_id = null;
        }

        // ==========================================================
        // 1️⃣ INSERIR GRUPO DE CÁLCULO
        // ==========================================================
        $query_grupo = "
            INSERT INTO grupos_calculo (
                usuario_id, descricao, nota_fiscal_id, informacoes_adicionais, competencia
            ) VALUES (?, ?, ?, ?, ?)
        ";

        if ($stmt = $conexao->prepare($query_grupo)) {
            $stmt->bind_param("isiss", $usuario_id, $descricao, $nota_id, $informacoes_adicionais, $competencia_nota);

            if (!$stmt->execute()) {
                throw new Exception("Erro ao criar grupo de cálculo: " . $stmt->error);
            }

            $grupo_id = $conexao->insert_id;
            $stmt->close();
        } else {
            throw new Exception("Erro ao preparar query do grupo: " . $conexao->error);
        }

        // ==========================================================
        // 2️⃣ INSERIR CÁLCULO BASE
        // ==========================================================
        $query_calculo = "
            INSERT INTO calculos_fronteira (
                usuario_id, grupo_id, descricao, valor_produto, valor_frete, valor_ipi, 
                valor_seguro, valor_icms, aliquota_interna, aliquota_interestadual,
                competencia, empresa_regular
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        if ($stmt = $conexao->prepare($query_calculo)) {
            $stmt->bind_param(
                "iisdddddddss",
                $usuario_id,
                $grupo_id,
                $descricao,
                $valor_produto,
                $valor_frete,
                $valor_ipi,
                $valor_seguro,
                $valor_icms,
                $aliquota_interna,
                $aliquota_interestadual,
                $competencia_nota,
                $empresa_regular
            );

            if (!$stmt->execute()) {
                throw new Exception("Erro ao inserir cálculo base: " . $stmt->error);
            }

            $calculo_id = $conexao->insert_id;
            $stmt->close();
        } else {
            throw new Exception("Erro ao preparar query de cálculo: " . $conexao->error);
        }

        // ==========================================================
        // 3️⃣ ATUALIZAR DEMAIS CAMPOS DO CÁLCULO (VERSÃO SIMPLIFICADA)
        // ==========================================================
        $query_update = "
            UPDATE calculos_fronteira SET 
                regime_fornecedor = '" . $conexao->real_escape_string($regime_fornecedor) . "', 
                tipo_credito_icms = '" . $conexao->real_escape_string($tipo_credito_icms) . "', 
                icms_st = " . floatval($icms_st) . ", 
                mva_original = " . floatval($mva_original) . ", 
                mva_cnae = " . floatval($mva_cnae) . ", 
                aliquota_reducao = " . floatval($aliquota_reducao) . ", 
                diferencial_aliquota = " . floatval($diferencial_aliquota) . ", 
                valor_gnre = " . floatval($valor_gnre) . ", 
                tipo_calculo = '" . $conexao->real_escape_string($tipo_calculo) . "',
                icms_tributado_simples_regular = " . floatval($icms_tributado_simples_regular) . ", 
                icms_tributado_simples_irregular = " . floatval($icms_tributado_simples_irregular) . ",
                icms_tributado_real = " . floatval($icms_tributado_real) . ", 
                icms_uso_consumo = " . floatval($icms_uso_consumo) . ", 
                icms_reducao = " . floatval($icms_reducao) . ", 
                mva_ajustada = " . floatval($mva_ajustada) . ", 
                icms_reducao_sn = " . floatval($icms_reducao_sn) . ", 
                icms_reducao_st_sn = " . floatval($icms_reducao_st_sn) . ", 
                empresa_regular = '" . $conexao->real_escape_string($empresa_regular) . "'
            WHERE id = " . intval($calculo_id);

        if ($conexao->query($query_update)) {
            // Sucesso - continua o código
        } else {
            throw new Exception("Erro ao atualizar cálculo: " . $conexao->error);
        }

        // ==========================================================
        // 4️⃣ SALVAR RELAÇÃO ENTRE GRUPO E PRODUTOS
        // ==========================================================
        if (!empty($produtos_ids)) {
            $query_produtos = "INSERT INTO grupo_calculo_produtos (grupo_calculo_id, produto_id) VALUES (?, ?)";

            if ($stmt = $conexao->prepare($query_produtos)) {
                foreach ($produtos_ids as $produto_id) {
                    $stmt->bind_param("ii", $grupo_id, $produto_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Erro ao vincular produto ID {$produto_id}: " . $stmt->error);
                    }
                }
                $stmt->close();
            } else {
                throw new Exception("Erro ao preparar query de produtos: " . $conexao->error);
            }
        }

        // ==========================================================
        // 5️⃣ FINALIZAR TRANSAÇÃO
        // ==========================================================
        $conexao->commit();

        // Redirecionar para visualização
        header("Location: calculo-fronteira.php?action=visualizar&id={$calculo_id}&competencia={$competencia_nota}");
        exit;

    } catch (Exception $e) {
        // Reverter alterações se algo falhar
        if ($conexao->errno === 0) {
            $conexao->rollback();
        }

        // Registrar erro no log
        error_log("Erro no salvamento de cálculo: " . $e->getMessage());

        // Redirecionar com mensagem
        $erro = urlencode("Erro ao salvar cálculo: " . $e->getMessage());
        header("Location: fronteira-fiscal.php?competencia={$competencia_nota}&error={$erro}");
        exit;
    }
}


// Função para gerar resultados dos cálculos (adicionar antes do if ($action === 'visualizar'))
function gerarResultadosCalculos($calculo) {
    $tipo_calculo_selecionado = $calculo['tipo_calculo'] ?? 'icms_st';
    $opacidade_transparente = 'opacity-40';
    
    $nomes_calculos = [
        'icms_st' => 'ICMS ST',
        'icms_simples' => 'ICMS Tributado Simples',
        'icms_real' => 'ICMS Tributado Real/Presumido',
        'icms_consumo' => 'ICMS Uso e Consumo',
        'icms_reducao' => 'ICMS Redução',
        'icms_reducao_sn' => 'ICMS Redução SN',
        'icms_reducao_st_sn' => 'ICMS Redução ST SN',
        'todos' => 'Todos os Cálculos'
    ];
    
    $html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
    
    // ICMS ST
    $classe_icms_st = '';
    $destaque_icms_st = '';
    if ($tipo_calculo_selecionado == 'icms_st') {
        $classe_icms_st = '';
        $destaque_icms_st = 'calculo-selecionado';
    } elseif ($tipo_calculo_selecionado != 'todos') {
        $classe_icms_st = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_icms_st . ' ' . $destaque_icms_st . '">
                <h3 class="font-semibold text-indigo-600">ICMS ST</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_st'], 2, ',', '.') . '</p>
              </div>';
    
    // ICMS Tributado Simples (Regular)
    $classe_simples_regular = '';
    $destaque_simples_regular = '';
    if ($tipo_calculo_selecionado == 'icms_simples' || $tipo_calculo_selecionado == 'todos') {
        $classe_simples_regular = '';
        if ($tipo_calculo_selecionado == 'icms_simples') {
            $destaque_simples_regular = 'calculo-selecionado';
        }
    } else {
        $classe_simples_regular = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_simples_regular . ' ' . $destaque_simples_regular . '">
                <h3 class="font-semibold text-blue-600">ICMS Tributado Simples (Regular)</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_tributado_simples_regular'], 2, ',', '.') . '</p>
              </div>';
    
    // ICMS Tributado Simples (Irregular)
    $classe_simples_irregular = '';
    $destaque_simples_irregular = '';
    if ($tipo_calculo_selecionado == 'icms_simples' || $tipo_calculo_selecionado == 'todos') {
        $classe_simples_irregular = '';
        if ($tipo_calculo_selecionado == 'icms_simples') {
            $destaque_simples_irregular = 'calculo-selecionado';
        }
    } else {
        $classe_simples_irregular = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_simples_irregular . ' ' . $destaque_simples_irregular . '">
                <h3 class="font-semibold text-blue-600">ICMS Tributado Simples (Irregular)</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_tributado_simples_irregular'], 2, ',', '.') . '</p>
              </div>';
    
    // ICMS Tributado Real/Presumido
    $classe_real = '';
    $destaque_real = '';
    if ($tipo_calculo_selecionado == 'icms_real' || $tipo_calculo_selecionado == 'todos') {
        $classe_real = '';
        if ($tipo_calculo_selecionado == 'icms_real') {
            $destaque_real = 'calculo-selecionado';
        }
    } else {
        $classe_real = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_real . ' ' . $destaque_real . '">
                <h3 class="font-semibold text-green-600">ICMS Tributado Real/Presumido</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_tributado_real'], 2, ',', '.') . '</p>
              </div>';
    
    // ICMS Uso e Consumo
    $classe_consumo = '';
    $destaque_consumo = '';
    if ($tipo_calculo_selecionado == 'icms_consumo' || $tipo_calculo_selecionado == 'todos') {
        $classe_consumo = '';
        if ($tipo_calculo_selecionado == 'icms_consumo') {
            $destaque_consumo = 'calculo-selecionado';
        }
    } else {
        $classe_consumo = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_consumo . ' ' . $destaque_consumo . '">
                <h3 class="font-semibold text-purple-600">ICMS Uso e Consumo</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_uso_consumo'], 2, ',', '.') . '</p>
              </div>';
    
    // ICMS Redução
    $classe_reducao = '';
    $destaque_reducao = '';
    if ($tipo_calculo_selecionado == 'icms_reducao' || $tipo_calculo_selecionado == 'todos') {
        $classe_reducao = '';
        if ($tipo_calculo_selecionado == 'icms_reducao') {
            $destaque_reducao = 'calculo-selecionado';
        }
    } else {
        $classe_reducao = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_reducao . ' ' . $destaque_reducao . '">
                <h3 class="font-semibold text-red-600">ICMS Redução</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_reducao'], 2, ',', '.') . '</p>
              </div>';
    
    $html .= '</div>';

    // ICMS Redução SN
    $classe_reducao_sn = '';
    $destaque_reducao_sn = '';
    if ($tipo_calculo_selecionado == 'icms_reducao_sn' || $tipo_calculo_selecionado == 'todos') {
        $classe_reducao_sn = '';
        if ($tipo_calculo_selecionado == 'icms_reducao_sn') {
            $destaque_reducao_sn = 'calculo-selecionado';
        }
    } else {
        $classe_reducao_sn = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_reducao_sn . ' ' . $destaque_reducao_sn . '">
                <h3 class="font-semibold text-pink-600">ICMS Redução SN</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_reducao_sn'], 2, ',', '.') . '</p>
            </div>';
    
    // Na função gerarResultadosCalculos(), atualize esta parte:
    $classe_reducao_st_sn = '';
    $destaque_reducao_st_sn = '';
    if ($tipo_calculo_selecionado == 'icms_reducao_st_sn' || $tipo_calculo_selecionado == 'todos') {
        $classe_reducao_st_sn = '';
        if ($tipo_calculo_selecionado == 'icms_reducao_st_sn') {
            $destaque_reducao_st_sn = 'calculo-selecionado';
        }
    } else {
        $classe_reducao_st_sn = $opacidade_transparente;
    }
    $html .= '<div class="bg-white p-3 rounded-md shadow-sm ' . $classe_reducao_st_sn . ' ' . $destaque_reducao_st_sn . '">
                <h3 class="font-semibold text-orange-600">ICMS Redução ST SN</h3>
                <p class="text-2xl font-bold">R$ ' . number_format($calculo['icms_reducao_st_sn'], 2, ',', '.') . '</p>
            </div>';     
    // Indicador do cálculo selecionado
    $html .= '<div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                <p class="text-sm text-yellow-800">
                    <strong>Cálculo selecionado:</strong> 
                    ' . ($nomes_calculos[$tipo_calculo_selecionado] ?? 'ICMS ST') . '
                </p>';
    
    if ($tipo_calculo_selecionado == 'icms_simples') {
        $html .= '<p class="text-xs text-yellow-700 mt-1">* Para ICMS Tributado Simples, ambos os valores (Regular e Irregular) são exibidos</p>';
    }
    
    $html .= '</div>';
    
    return $html;
}


// Visualizar cálculo existente
if ($action === 'visualizar' && isset($_GET['id'])) {
    $calculo_id = $_GET['id'];
    $competencia = $_GET['competencia'] ?? date('Y-m');
    
    try {
        $query = "
            SELECT c.*, g.descricao as grupo_descricao, g.informacoes_adicionais, 
                   n.numero as nota_numero, n.chave_acesso, e.emitente_nome as emitente
            FROM calculos_fronteira c
            LEFT JOIN grupos_calculo g ON c.grupo_id = g.id
            LEFT JOIN nfe n ON g.nota_fiscal_id = n.id
            LEFT JOIN nfe e ON n.emitente_cnpj = e.emitente_cnpj
            WHERE c.id = ? AND c.usuario_id = ?
        ";
        
        if ($stmt = $conexao->prepare($query)) {
            $stmt->bind_param("ii", $calculo_id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $calculo = $result->fetch_assoc();
            $stmt->close();
            
            if (!$calculo) {
                header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Cálculo não encontrado");
                exit;
            }
            
            // Buscar produtos associados ao grupo de cálculo
            $produtos_calculo = [];
            if ($calculo['grupo_id']) {
                $query_produtos = "
                    SELECT ni.* 
                    FROM grupo_calculo_produtos gcp
                    LEFT JOIN nfe_itens ni ON gcp.produto_id = ni.id
                    WHERE gcp.grupo_calculo_id = ?
                    ORDER BY ni.numero_item
                ";
                
                if ($stmt_produtos = $conexao->prepare($query_produtos)) {
                    $stmt_produtos->bind_param("i", $calculo['grupo_id']);
                    $stmt_produtos->execute();
                    $result_produtos = $stmt_produtos->get_result();
                    $produtos_calculo = $result_produtos->fetch_all(MYSQLI_ASSOC);
                    $stmt_produtos->close();
                }
            }
            
            // Exibir página de visualização
            echo '<!DOCTYPE html>
            <html lang="pt-br">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Resultado do Cálculo - Sistema Contábil Integrado</title>
                <script src="https://cdn.tailwindcss.com"></script>
                <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
            </head>
            <body class="bg-gray-50">
                <div class="container mx-auto p-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold text-gray-800">Resultado do Cálculo</h1>
                            <a href="fronteira-fiscal.php?competencia=' . $competencia . '" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Voltar
                            </a>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="bg-gray-50 p-4 rounded-md">
                                <h2 class="text-lg font-semibold mb-3">Informações do Cálculo</h2>
                                <p><strong>Descrição:</strong> ' . htmlspecialchars($calculo['descricao']) . '</p>
                                <p><strong>Nota Fiscal:</strong> ' . htmlspecialchars($calculo['nota_numero'] ?? 'N/A') . '</p>
                                <p><strong>Data do Cálculo:</strong> ' . date('d/m/Y H:i', strtotime($calculo['data_calculo'])) . '</p>
                                <p><strong>Tipo de Crédito ICMS:</strong> ' . ($calculo['tipo_credito_icms'] == 'manual' ? 'Manual' : 'Nota Fiscal') . '</p>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-md">
                                <h2 class="text-lg font-semibold mb-3">Parâmetros Utilizados</h2>
                                <p><strong>Alíquota Interna:</strong> ' . number_format($calculo['aliquota_interna'], 2, ',', '.') . '%</p>
                                <p><strong>Alíquota Interestadual:</strong> ' . number_format($calculo['aliquota_interestadual'], 2, ',', '.') . '%</p>
                                <p><strong>Regime do Fornecedor:</strong> ' . ($calculo['regime_fornecedor'] == '3' ? 'Normal' : 'Simples Nacional') . '</p>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-md mb-6">
                            <h2 class="text-lg font-semibold mb-3">Valores de Entrada</h2>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div><strong>Valor do Produto:</strong> R$ ' . number_format($calculo['valor_produto'], 2, ',', '.') . '</div>
                                <div><strong>Valor do Frete:</strong> R$ ' . number_format($calculo['valor_frete'], 2, ',', '.') . '</div>
                                <div><strong>Valor do IPI:</strong> R$ ' . number_format($calculo['valor_ipi'], 2, ',', '.') . '</div>
                                <div><strong>Valor do Seguro:</strong> R$ ' . number_format($calculo['valor_seguro'], 2, ',', '.') . '</div>
                                <div><strong>Valor do ICMS:</strong> R$ ' . number_format($calculo['valor_icms'], 2, ',', '.') . '</div>
                            </div>
                        </div>
                        
                                                <style>
                        .opacity-40 {
                            opacity: 0.4;
                        }
                        .calculo-selecionado {
                            border: 2px solid #4f46e5;
                            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
                        }
                        </style>

                        <div class="bg-gray-50 p-4 rounded-md">
                            <h2 class="text-lg font-semibold mb-3">Resultados dos Cálculos</h2>
                            
                            ' . gerarResultadosCalculos($calculo) . '
                            
                            <!-- MVA Ajustada (apenas para regime normal) -->
                            ' . (($calculo['regime_fornecedor'] == '3' && $calculo['mva_ajustada'] > 0) ? '
                            <div class="mt-4 bg-yellow-50 p-3 rounded-md">
                                <h3 class="font-semibold text-yellow-700">MVA Ajustada</h3>
                                <p class="text-xl font-bold">' . number_format($calculo['mva_ajustada'], 2, ',', '.') . '%</p>
                            </div>' : '') . '
                        </div>';
            
            // Seção para mostrar os produtos do cálculo
            if (!empty($produtos_calculo)) {
                echo '<div class="bg-gray-50 p-4 rounded-md mt-6">
                        <h2 class="text-lg font-semibold mb-3">Produtos no Cálculo</h2>
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">NCM</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantidade</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor Unitário</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">';
                
                foreach ($produtos_calculo as $produto) {
                    echo '<tr>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['codigo_produto']) . '</td>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['ncm'] ?? 'N/A') . '</td>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['descricao']) . '</td>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['unidade'] ?? 'N/A') . '</td>
                            <td class="px-4 py-2 text-sm">' . number_format($produto['quantidade'], 4, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_unitario'], 4, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_total'], 2, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_ipi'], 2, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_icms'], 2, ',', '.') . '</td>
                        </tr>';
                }
                
                echo '    </tbody>
                        </table>
                    </div>
                </div>';
            }
            if ($calculo['nota_numero']) {
                echo '<div class="bg-gray-50 p-4 rounded-md mt-6">
                        <h2 class="text-lg font-semibold mb-3">Informações da Nota Fiscal</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p><strong>Número:</strong> ' . htmlspecialchars($calculo['nota_numero']) . '</p>
                                <p><strong>Emissão:</strong> ' . (isset($calculo['data_emissao']) ? date('d/m/Y', strtotime($calculo['data_emissao'])) : 'N/A') . '</p>
                                <p><strong>Chave de Acesso:</strong> ' . htmlspecialchars($calculo['chave_acesso'] ?? 'N/A') . '</p>
                            </div>
                            <div>
                                <p><strong>Emitente:</strong> ' . htmlspecialchars($calculo['emitente'] ?? 'N/A') . '</p>
                                <p><strong>CNPJ:</strong> ' . htmlspecialchars($calculo['emitente_cnpj'] ?? 'N/A') . '</p>
                            </div>
                        </div>
                    </div>';
            }
            // NOVA SEÇÃO: Estrutura dos Cálculos
            echo '<div class="bg-gray-50 p-4 rounded-md mt-6">
                    <h2 class="text-lg font-semibold mb-3">Estrutura dos Cálculos</h2>
                    <div class="space-y-4">';
            
            // Fórmula do ICMS ST
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                    <h3 class="font-semibold text-indigo-600 mb-2">ICMS ST</h3>
                    <p class="text-sm text-gray-600 mb-2">Fórmula: ';
            
            if ($calculo['regime_fornecedor'] == '3') {
                echo '((Valor Produto + Valor IPI + Valor Frete + Valor Seguro) × (1 + MVA Ajustada) × Alíquota Interna) - Crédito ICMS - GNRE';
            } else {
                echo '((Valor Produto + Valor IPI + Valor Frete + Valor Seguro) × (1 + MVA Original) × Alíquota Interna) - Crédito ICMS - GNRE';
            }
            
            echo '</p>
                    <p class="text-sm">Valores: ((R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] + $calculo['valor_frete'] + $calculo['valor_seguro']), 2, ',', '.') . ')';
            
            if ($calculo['regime_fornecedor'] == '3') {
                echo ' × (1 + ' . number_format($calculo['mva_ajustada']/100, 4, ',', '.') . ')';
            } else {
                echo ' × (1 + ' . number_format($calculo['mva_original']/100, 4, ',', '.') . ')';
            }
            
            echo ' × ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ') - R$ ' . number_format($calculo['valor_icms'], 2, ',', '.') . ' - R$ ' . number_format($calculo['valor_gnre'], 2, ',', '.') . '</p>
                  </div>';
            
            // Fórmula do ICMS Tributado Simples (Regular)
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                    <h3 class="font-semibold text-blue-600 mb-2">ICMS Tributado Simples (Regular)</h3>
                    <p class="text-sm text-gray-600 mb-2">Fórmula: ((Valor Produto + Valor IPI - Crédito ICMS) / (1 - Alíquota Interna)) × Diferencial Alíquota</p>
                    <p class="text-sm">Valores: ((R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] - $calculo['valor_icms']), 2, ',', '.') . ') / (1 - ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ')) × ' . number_format($calculo['diferencial_aliquota']/100, 4, ',', '.') . '</p>
                  </div>';
            
            // Fórmula do ICMS Tributado Simples (Irregular)
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                    <h3 class="font-semibold text-blue-600 mb-2">ICMS Tributado Simples (Irregular)</h3>
                    <p class="text-sm text-gray-600 mb-2">Fórmula: ((Valor Produto + Valor IPI - Crédito ICMS) / (1 - Alíquota Interna)) × (Alíquota Interna - Alíquota Interestadual)</p>
                    <p class="text-sm">Valores: ((R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] - $calculo['valor_icms']), 2, ',', '.') . ') / (1 - ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ')) × (' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ' - ' . number_format($calculo['aliquota_interestadual']/100, 4, ',', '.') . ')</p>
                  </div>';
            
            // Fórmula do ICMS Tributado Real/Presumido
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                    <h3 class="font-semibold text-green-600 mb-2">ICMS Tributado Real/Presumido</h3>
                    <p class="text-sm text-gray-600 mb-2">Fórmula: ((Valor Produto + Valor IPI - Crédito ICMS) / (1 - Alíquota Interna)) × (1 + MVA CNAE) × Alíquota Interna - Crédito ICMS</p>
                    <p class="text-sm">Valores: ((R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] - $calculo['valor_icms']), 2, ',', '.') . ') / (1 - ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ')) × (1 + ' . number_format($calculo['mva_cnae']/100, 4, ',', '.') . ') × ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ' - R$ ' . number_format($calculo['valor_icms'], 2, ',', '.') . '</p>
                  </div>';
            
            // Fórmula do ICMS Uso e Consumo
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                    <h3 class="font-semibold text-purple-600 mb-2">ICMS Uso e Consumo</h3>
                    <p class="text-sm text-gray-600 mb-2">Fórmula: ((Valor Produto + Valor IPI - Crédito ICMS) / (1 - Alíquota Interna)) × (Alíquota Interna - Alíquota Interestadual)</p>
                    <p class="text-sm">Valores: ((R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] - $calculo['valor_icms']), 2, ',', '.') . ') / (1 - ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ')) × (' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ' - ' . number_format($calculo['aliquota_interestadual']/100, 4, ',', '.') . ')</p>
                  </div>';
            
            // Fórmula do ICMS Redução
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                    <h3 class="font-semibold text-red-600 mb-2">ICMS Redução</h3>
                    <p class="text-sm text-gray-600 mb-2">Fórmula: ((Valor Produto + Valor IPI - Crédito ICMS) / (1 - Alíquota Interna)) × (1 + MVA CNAE) × Alíquota Redução - Crédito ICMS</p>
                    <p class="text-sm">Valores: ((R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] - $calculo['valor_icms']), 2, ',', '.') . ') / (1 - ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ')) × (1 + ' . number_format($calculo['mva_cnae']/100, 4, ',', '.') . ') × ' . number_format($calculo['aliquota_reducao']/100, 4, ',', '.') . ' - R$ ' . number_format($calculo['valor_icms'], 2, ',', '.') . '</p>
                  </div>';

            // Fórmula do ICMS Redução SN
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                <h3 class="font-semibold text-pink-600 mb-2">ICMS Redução SN</h3>
                <p class="text-sm text-gray-600 mb-2">Fórmula: (Base Redução SN / (1 - Alíquota Interna)) × (Alíquota Redução / Alíquota Interna) × (Alíquota Interna - Alíquota Interestadual)</p>
                <p class="text-sm">Valores: (R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] + $calculo['valor_frete'] + $calculo['valor_seguro'] - $calculo['valor_icms']), 2, ',', '.') . ' / (1 - ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ')) × (' . number_format($calculo['aliquota_reducao']/100, 4, ',', '.') . ' / ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ') × (' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ' - ' . number_format($calculo['aliquota_interestadual']/100, 4, ',', '.') . ')</p>
            </div>';

            // Fórmula da Redução ST SN - NOVO
            echo '<div class="bg-white p-4 rounded-md shadow-sm">
                    <h3 class="font-semibold text-orange-600 mb-2">Redução ST SN</h3>
                    <p class="text-sm text-gray-600 mb-2">Fórmula: ((Valor Produto + Valor IPI + Valor Frete + Valor Seguro) × (1 + MVA Ajustada) × Alíquota Redução × Alíquota Interna) - Crédito ICMS - GNRE</p>
                    <p class="text-sm">Valores: ((R$ ' . number_format(($calculo['valor_produto'] + $calculo['valor_ipi'] + $calculo['valor_frete'] + $calculo['valor_seguro']), 2, ',', '.') . ') × (1 + ' . number_format($calculo['mva_ajustada']/100, 4, ',', '.') . ') × ' . number_format($calculo['aliquota_reducao']/100, 4, ',', '.') . ' × ' . number_format($calculo['aliquota_interna']/100, 4, ',', '.') . ') - R$ ' . number_format($calculo['valor_icms'], 2, ',', '.') . ' - R$ ' . number_format($calculo['valor_gnre'], 2, ',', '.') . '</p>
                </div>';
            
            echo '</div>
                </div>';
                        
            echo '        ' . (!empty($calculo['informacoes_adicionais']) ? '
                        <div class="bg-gray-50 p-4 rounded-md mt-6">
                            <h2 class="text-lg font-semibold mb-3">Informações Adicionais</h2>
                            <p class="text-sm text-gray-600">' . nl2br(htmlspecialchars($calculo['informacoes_adicionais'])) . '</p>
                        </div>
                        ' : '') . '
                    </div>
                </div>
                <script>
                    feather.replace();
                </script>
            </body>
            </html>';
            exit;
            
        }
        
    } catch (Exception $e) {
        header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Erro ao buscar cálculo: " . urlencode($e->getMessage()));
        exit;
    }
}

// Editar cálculo existente
if ($action === 'editar' && isset($_GET['id'])) {
    $calculo_id = $_GET['id'];
    $competencia = $_GET['competencia'] ?? date('Y-m');
    
    try {
        $query = "
            SELECT c.*, g.descricao as grupo_descricao, g.informacoes_adicionais, 
                   n.numero as nota_numero, n.id as nota_id, n.chave_acesso, 
                   n.data_emissao, 
                   e.emitente_nome as emitente, e.emitente_cnpj as cnpj
            FROM calculos_fronteira c
            LEFT JOIN grupos_calculo g ON c.grupo_id = g.id
            LEFT JOIN nfe n ON g.nota_fiscal_id = n.id
            LEFT JOIN nfe e ON n.emitente_cnpj = e.emitente_cnpj
            WHERE c.id = ? AND c.usuario_id = ?
        ";
        
        if ($stmt = $conexao->prepare($query)) {
            $stmt->bind_param("ii", $calculo_id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $calculo = $result->fetch_assoc();
            $stmt->close();
            
            if (!$calculo) {
                header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Cálculo não encontrado");
                exit;
            }

            // Buscar produtos associados ao grupo de cálculo
            $produtos_calculo = [];
            if ($calculo['grupo_id']) {
                $query_produtos = "
                    SELECT ni.* 
                    FROM grupo_calculo_produtos gcp
                    LEFT JOIN nfe_itens ni ON gcp.produto_id = ni.id
                    WHERE gcp.grupo_calculo_id = ?
                    ORDER BY ni.numero_item
                ";
                
                if ($stmt_produtos = $conexao->prepare($query_produtos)) {
                    $stmt_produtos->bind_param("i", $calculo['grupo_id']);
                    $stmt_produtos->execute();
                    $result_produtos = $stmt_produtos->get_result();
                    $produtos_calculo = $result_produtos->fetch_all(MYSQLI_ASSOC);
                    $stmt_produtos->close();
                }
            }

            // Exibir formulário de edição
            echo '<!DOCTYPE html>
            <html lang="pt-br">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Editar Cálculo - Sistema Contábil Integrado</title>
                <script src="https://cdn.tailwindcss.com"></script>
                <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
            </head>
            <body class="bg-gray-50">
                <div class="min-h-screen flex items-center justify-center px-4 py-6">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl">
                        <div class="p-6">
                            <div class="flex justify-between items-center pb-3 border-b">
                                <h3 class="text-xl font-semibold text-gray-800">Editar Cálculo de Fronteira</h3>
                                <a href="fronteira-fiscal.php?competencia=' . $competencia . '" class="text-gray-500 hover:text-gray-700">
                                    <i data-feather="x" class="w-6 h-6"></i>
                                </a>
                            </div>';
            
            // Informações da Nota Fiscal (se houver)
            if ($calculo['nota_id']) {
                echo '<div class="bg-gray-50 p-4 rounded-md mt-4">
                        <h4 class="text-lg font-medium text-gray-800 mb-2">Informações da Nota Fiscal</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm"><strong>Número:</strong> ' . htmlspecialchars($calculo['nota_numero'] ?? 'N/A') . '</p>
                                <p class="text-sm"><strong>Emissão:</strong> ' . (isset($calculo['data_emissao']) ? date('d/m/Y', strtotime($calculo['data_emissao'])) : 'N/A') . '</p>
                                <p class="text-sm"><strong>Chave de Acesso:</strong> ' . htmlspecialchars($calculo['chave_acesso'] ?? 'N/A') . '</p>
                            </div>
                            <div>
                                <p class="text-sm"><strong>Emitente:</strong> ' . htmlspecialchars($calculo['emitente'] ?? 'N/A') . '</p>
                                <p class="text-sm"><strong>CNPJ:</strong> ' . htmlspecialchars($calculo['emitente_cnpj'] ?? 'N/A') . '</p>
                            </div>
                        </div>
                    </div>';
            }
            
            // Produtos do Cálculo (se houver)
            if (!empty($produtos_calculo)) {
                echo '<div class="bg-blue-50 p-4 rounded-md mt-4">
                        <h4 class="text-lg font-medium text-blue-800 mb-2">Produtos no Cálculo</h4>
                        <div class="table-responsive overflow-x-auto">
                            <table class="min-w-full divide-y divide-blue-200">
                                <thead class="bg-blue-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Código</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">NCM</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Descrição</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Unidade</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Quantidade</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor Unit.</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor Total</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor IPI</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor ICMS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-blue-200">';
                
                $total_produtos = 0;
                $total_ipi = 0;
                $total_icms = 0;
                
                foreach ($produtos_calculo as $produto) {
                    $total_produtos += floatval($produto['valor_total']);
                    $total_ipi += floatval($produto['valor_ipi']);
                    $total_icms += floatval($produto['valor_icms']);
                    
                    echo '<tr>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['codigo_produto']) . '</td>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['ncm'] ?? 'N/A') . '</td>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['descricao']) . '</td>
                            <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['unidade'] ?? 'N/A') . '</td>
                            <td class="px-4 py-2 text-sm">' . number_format($produto['quantidade'], 4, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_unitario'], 4, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_total'], 2, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_ipi'], 2, ',', '.') . '</td>
                            <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_icms'], 2, ',', '.') . '</td>
                          </tr>';
                }
                
                echo '<tr class="bg-blue-100 font-semibold">
                        <td class="px-4 py-2" colspan="6">TOTAL</td>
                        <td class="px-4 py-2">R$ ' . number_format($total_produtos, 2, ',', '.') . '</td>
                        <td class="px-4 py-2">R$ ' . number_format($total_ipi, 2, ',', '.') . '</td>
                        <td class="px-4 py-2">R$ ' . number_format($total_icms, 2, ',', '.') . '</td>
                      </tr>
                    </tbody>
                </table>
            </div>
        </div>';
            }
            
            echo '<form action="calculo-fronteira.php" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="atualizar_calculo">
                    <input type="hidden" name="calculo_id" value="' . $calculo_id . '">
                    <input type="hidden" name="competencia" value="' . $competencia . '">
                    <input type="hidden" name="nota_id" value="' . $calculo['nota_id'] . '">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição do Cálculo *</label>
                            <input type="text" id="descricao" name="descricao" value="' . htmlspecialchars($calculo['descricao']) . '" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="nota_numero" class="block text-sm font-medium text-gray-700 mb-1">Nota Fiscal</label>
                            <input type="text" id="nota_numero" value="' . htmlspecialchars($calculo['nota_numero'] ?? '') . '" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                        </div>
                    </div>
                    
                    <div>
                        <label for="informacoes_adicionais" class="block text-sm font-medium text-gray-700 mb-1">Informações Adicionais</label>
                        <textarea id="informacoes_adicionais" name="informacoes_adicionais" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">' . htmlspecialchars($calculo['informacoes_adicionais'] ?? '') . '</textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_produto" class="block text-sm font-medium text-gray-700 mb-1">Valor do Produto (R$)</label>
                            <input type="number" step="0.01" id="valor_produto" name="valor_produto" value="' . $calculo['valor_produto'] . '" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_frete" class="block text-sm font-medium text-gray-700 mb-1">Valor do Frete (R$)</label>
                            <input type="number" step="0.01" id="valor_frete" name="valor_frete" value="' . $calculo['valor_frete'] . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_ipi" class="block text-sm font-medium text-gray-700 mb-1">Valor do IPI (R$)</label>
                            <input type="number" step="0.01" id="valor_ipi" name="valor_ipi" value="' . $calculo['valor_ipi'] . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_seguro" class="block text-sm font-medium text-gray-700 mb-1">Valor do Seguro (R$)</label>
                            <input type="number" step="0.01" id="valor_seguro" name="valor_seguro" value="' . $calculo['valor_seguro'] . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_icms" class="block text-sm font-medium text-gray-700 mb-1">Valor do Crédito ICMS (R$)</label>
                            <input type="number" step="0.01" id="valor_icms" name="valor_icms" value="' . $calculo['valor_icms'] . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_gnre" class="block text-sm font-medium text-gray-700 mb-1">Valor GNRE (R$)</label>
                            <input type="number" step="0.01" id="valor_gnre" name="valor_gnre" value="' . ($calculo['valor_gnre'] ?? 0) . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="aliquota_interestadual" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Interestadual (%)</label>
                            <input type="number" step="0.01" id="aliquota_interestadual" name="aliquota_interestadual" value="' . $calculo['aliquota_interestadual'] . '" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="aliquota_interna" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Interna (%)</label>
                            <input type="number" step="0.01" id="aliquota_interna" name="aliquota_interna" value="' . $calculo['aliquota_interna'] . '" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="mva_original" class="block text-sm font-medium text-gray-700 mb-1">MVA Original (%)</label>
                            <input type="number" step="0.01" id="mva_original" name="mva_original" value="' . ($calculo['mva_original'] ?? 0) . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="mva_cnae" class="block text-sm font-medium text-gray-700 mb-1">MVA CNAE (%)</label>
                            <input type="number" step="0.01" id="mva_cnae" name="mva_cnae" value="' . ($calculo['mva_cnae'] ?? 0) . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="aliquota_reducao" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Redução (%)</label>
                            <input type="number" step="0.01" id="aliquota_reducao" name="aliquota_reducao" value="' . ($calculo['aliquota_reducao'] ?? 0) . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="diferencial_aliquota" class="block text-sm font-medium text-gray-700 mb-1">Diferencial Alíquota Simples (%)</label>
                            <input type="number" step="0.01" id="diferencial_aliquota" name="diferencial_aliquota" value="' . ($calculo['diferencial_aliquota'] ?? 0) . '" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="regime_fornecedor" class="block text-sm font-medium text-gray-700 mb-1">Regime do Fornecedor</label>
                            <select id="regime_fornecedor" name="regime_fornecedor" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="1" ' . ($calculo['regime_fornecedor'] == '1' ? 'selected' : '') . '>Simples Nacional</option>
                                <option value="2" ' . ($calculo['regime_fornecedor'] == '2' ? 'selected' : '') . '>Simples Nacional com Excedência</option>
                                <option value="3" ' . ($calculo['regime_fornecedor'] == '3' ? 'selected' : '') . '>Regime Normal</option>
                            </select>
                        </div>

                        <div>
                            <label for="empresa_regular" class="block text-sm font-medium text-gray-700 mb-1">Situação da Empresa</label>
                            <select id="empresa_regular" name="empresa_regular" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="S" ' . (($calculo['empresa_regular'] ?? 'S') == 'S' ? 'selected' : '') . '>Regular</option>
                                <option value="N" ' . (($calculo['empresa_regular'] ?? 'S') == 'N' ? 'selected' : '') . '>Irregular</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="tipo_calculo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Cálculo</label>
                            <select id="tipo_calculo" name="tipo_calculo" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="icms_st" ' . (($calculo['tipo_calculo'] ?? 'icms_st') == 'icms_st' ? 'selected' : '') . '>ICMS ST</option>
                                <option value="icms_simples" ' . (($calculo['tipo_calculo'] ?? '') == 'icms_simples' ? 'selected' : '') . '>ICMS Tributado Simples</option>
                                <option value="icms_real" ' . (($calculo['tipo_calculo'] ?? '') == 'icms_real' ? 'selected' : '') . '>ICMS Tributado Real/Presumido</option>
                                <option value="icms_consumo" ' . (($calculo['tipo_calculo'] ?? '') == 'icms_consumo' ? 'selected' : '') . '>ICMS Uso e Consumo</option>
                                <option value="icms_reducao" ' . (($calculo['tipo_calculo'] ?? '') == 'icms_reducao' ? 'selected' : '') . '>ICMS Redução</option>
                                <option value="icms_reducao_sn" ' . (($calculo['tipo_calculo'] ?? '') == 'icms_reducao_sn' ? 'selected' : '') . '>ICMS Redução SN</option>
                                <option value="icms_reducao_st_sn" ' . (($calculo['tipo_calculo'] ?? '') == 'icms_reducao_st_sn' ? 'selected' : '') . '>ICMS Redução ST SN</option>
                                <option value="todos" ' . (($calculo['tipo_calculo'] ?? '') == 'todos' ? 'selected' : '') . '>Todos os Cálculos</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-center mt-4">
                        <input type="checkbox" id="tipo_credito_icms" name="tipo_credito_icms" value="manual" ' . ($calculo['tipo_credito_icms'] == 'manual' ? 'checked' : '') . ' class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="tipo_credito_icms" class="ml-2 block text-sm text-gray-700">Usar ICMS crédito manual (em vez do destacado na nota)</label>
                    </div>
                    
                    <div class="flex justify-end pt-4 border-t mt-6">
                        <a href="fronteira-fiscal.php?competencia=' . $competencia . '" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 mr-3">
                            Cancelar
                        </a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Atualizar Cálculo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
    </script>
</body>
</html>';
            exit;
        }
        
    } catch (Exception $e) {
        header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Erro ao buscar cálculo: " . urlencode($e->getMessage()));
        exit;
    }
}

// Processar atualização de cálculo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'atualizar_calculo') {
    $calculo_id = $_POST['calculo_id'] ?? null;
    $competencia = $_POST['competencia'] ?? date('Y-m');
    $nota_id = $_POST['nota_id'] ?? null;
    $descricao = $_POST['descricao'] ?? '';
    $informacoes_adicionais = $_POST['informacoes_adicionais'] ?? '';
    $valor_produto = floatval($_POST['valor_produto'] ?? 0);
    $valor_frete = floatval($_POST['valor_frete'] ?? 0);
    $valor_ipi = floatval($_POST['valor_ipi'] ?? 0);
    $valor_seguro = floatval($_POST['valor_seguro'] ?? 0);
    $valor_icms = floatval($_POST['valor_icms'] ?? 0);
    $valor_gnre = floatval($_POST['valor_gnre'] ?? 0);
    $aliquota_interestadual = floatval($_POST['aliquota_interestadual'] ?? 0);
    $aliquota_interna = floatval($_POST['aliquota_interna'] ?? 20.5);
    $mva_original = floatval($_POST['mva_original'] ?? 0);
    $mva_cnae = floatval($_POST['mva_cnae'] ?? 0);
    $aliquota_reducao = floatval($_POST['aliquota_reducao'] ?? 0);
    $diferencial_aliquota = floatval($_POST['diferencial_aliquota'] ?? 0);
    $regime_fornecedor = $_POST['regime_fornecedor'] ?? '3';
    $tipo_calculo = $_POST['tipo_calculo'] ?? 'icms_st';
    $tipo_credito_icms = isset($_POST['tipo_credito_icms']) ? 'manual' : 'nota';
    $query_competencia = "SELECT competencia FROM calculos_fronteira WHERE id = ?";
    if ($stmt_comp = $conexao->prepare($query_competencia)) {
        $stmt_comp->bind_param("i", $calculo_id);
        $stmt_comp->execute();
        $result_comp = $stmt_comp->get_result();
        if ($calculo_comp = $result_comp->fetch_assoc()) {
            $competencia = $calculo_comp['competencia'];
        }
        $stmt_comp->close();
    }
    
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
    $icms_tributado_simples_regular = (($base_calculo + $valor_ipi - $valor_icms) / (1 - ($aliquota_interna/100))) * ($diferencial_aliquota/100);
    // Garantir que o valor não seja negativo
        if ($icms_tributado_simples_regular < 0) {
            $icms_tributado_simples_regular = 0;
        }

    // ICMS Tributado Simples (empresa irregular)
    $icms_tributado_simples_irregular = (($base_calculo + $valor_ipi - $valor_icms) / (1 - ($aliquota_interna/100))) * (($aliquota_interna - $aliquota_interestadual)/100);
    // Garantir que o valor não seja negativo
        if ($icms_tributado_simples_irregular < 0) {
            $icms_tributado_simples_irregular = 0;
        }

    // ICMS Tributado Real/Presumido
    $icms_tributado_real = (($base_calculo + $valor_ipi - $valor_icms) / (1 - ($aliquota_interna/100))) * (1 + ($mva_cnae/100)) * ($aliquota_interna/100) - $valor_icms;
    // Garantir que o valor não seja negativo
        if ($icms_tributado_real < 0) {
            $icms_tributado_real = 0;
        }

    // ICMS Uso e Consumo/Ativo Fixo
    $icms_uso_consumo = (($base_calculo + $valor_ipi - $valor_icms) / (1 - ($aliquota_interna/100))) * (($aliquota_interna - $aliquota_interestadual)/100);
    // Garantir que o valor não seja negativo
        if ($icms_uso_consumo < 0) {
            $icms_uso_consumo = 0;
        }

    // ICMS Redução
    $icms_reducao = (($base_calculo + $valor_ipi - $valor_icms) / (1 - ($aliquota_interna/100))) * (1 + ($mva_cnae/100)) * ($aliquota_reducao/100) - $valor_icms;
    // Garantir que o valor não seja negativo
        if ($icms_reducao < 0) {
            $icms_reducao = 0;
        }
    
    // ICMS redução SN
    if ($aliquota_interna > 0) {
        // Base para redução SN (valor total dos produtos + IPI + frete + seguro - ICMS)
        $base_reducao_sn = $valor_produto + $valor_ipi + $valor_frete + $valor_seguro - $valor_icms;
        
        // Fórmula CORRETA do ICMS Redução SN
        $base_ajustada = $base_reducao_sn / (1 - ($aliquota_interna/100));
        $coeficiente_reducao = ($aliquota_reducao/100) / ($aliquota_interna/100);
        $diferencial_aliquotas = ($aliquota_interna - $aliquota_interestadual) / 100;
        
        $icms_reducao_sn = $base_ajustada * $coeficiente_reducao * $diferencial_aliquotas;
        
        if ($icms_reducao_sn < 0) {
            $icms_reducao_sn = 0;
        }
    }

    // ICMS Redução ST SN 
    
    if ($regime_fornecedor == '3' && $aliquota_reducao > 0) {
        // Fórmula: ((Valor Produto + Valor IPI + Valor Frete + Valor Seguro) × (1 + MVA Ajustada) x (aliquota redução) × Alíquota Interna) - Crédito ICMS - GNRE
        $base_st_sn = $valor_produto + $valor_ipi + $valor_frete + $valor_seguro;
        $icms_reducao_st_sn = ($base_st_sn * (1 + ($mva_ajustada/100)) * ($aliquota_reducao/100) * ($aliquota_interna/100)) - $valor_icms - $valor_gnre;
        
        // Garantir que o valor não seja negativo
        if ($icms_reducao_st_sn < 0) {
            $icms_reducao_st_sn = 0;
        }
    }
    // Atualizar no banco de dados
    try {
        $conexao->begin_transaction();
        
        // Atualizar grupo de cálculo
        $query = "UPDATE grupos_calculo SET descricao = ?, informacoes_adicionais = ?, competencia = ? WHERE id = (SELECT grupo_id FROM calculos_fronteira WHERE id = ?)";
        if ($stmt = $conexao->prepare($query)) {
            $stmt->bind_param("sssi", $descricao, $informacoes_adicionais, $competencia, $calculo_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // CÓDIGO CORRIGIDO - ATUALIZAÇÃO DE CÁLCULO
        $query = "
            UPDATE calculos_fronteira 
            SET descricao = ?, valor_produto = ?, valor_frete = ?, valor_ipi = ?, 
                valor_seguro = ?, valor_icms = ?, aliquota_interna = ?, 
                aliquota_interestadual = ?, regime_fornecedor = ?, tipo_credito_icms = ?, 
                icms_st = ?, mva_original = ?, mva_cnae = ?, aliquota_reducao = ?,
                diferencial_aliquota = ?, valor_gnre = ?, tipo_calculo = ?,
                icms_tributado_simples_regular = ?, icms_tributado_simples_irregular = ?,
                icms_tributado_real = ?, icms_uso_consumo = ?, icms_reducao = ?, 
                mva_ajustada = ?, icms_reducao_sn = ?, icms_reducao_st_sn = ?, empresa_regular = ?, data_atualizacao = NOW()
            WHERE id = ? AND usuario_id = ?
        ";

        if ($stmt = $conexao->prepare($query)) {
            // DEBUG: Verificar parâmetros
            $params = [
                $descricao,                          // s
                $valor_produto,                      // d
                $valor_frete,                        // d
                $valor_ipi,                          // d
                $valor_seguro,                       // d
                $valor_icms,                         // d
                $aliquota_interna,                   // d
                $aliquota_interestadual,             // d
                $regime_fornecedor,                  // s
                $tipo_credito_icms,                  // s
                $icms_st,                            // d
                $mva_original,                       // d
                $mva_cnae,                           // d
                $aliquota_reducao,                   // d
                $diferencial_aliquota,               // d
                $valor_gnre,                         // d
                $tipo_calculo,                       // s
                $icms_tributado_simples_regular,     // d
                $icms_tributado_simples_irregular,   // d
                $icms_tributado_real,                // d
                $icms_uso_consumo,                   // d
                $icms_reducao,                       // d
                $mva_ajustada,                       // d
                $icms_reducao_sn,                    // d
                $icms_reducao_st_sn,                 // d
                $empresa_regular,                    // s
                $calculo_id,                         // i
                $usuario_id                          // i
            ];
            
            // String de tipos: 26 parâmetros + 2 para WHERE = 28 caracteres
            $types = "sdddddddssddddddsddddddddsii";
            
            if (strlen($types) !== count($params)) {
                throw new Exception("Erro UPDATE: Número de tipos (" . strlen($types) . ") não corresponde ao número de parâmetros (" . count($params) . ")");
            }
            
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                error_log("Erro ao atualizar cálculo: " . $stmt->error);
                throw new Exception($stmt->error);
            }
            
            $stmt->close();
        }
            
        
        $conexao->commit();
        
        // Redirecionar para a competência original do cálculo
        header("Location: calculo-fronteira.php?action=visualizar&id=" . $calculo_id . "&competencia=" . $competencia . "&msg=atualizado");
        exit;
        
    } catch (Exception $e) {
        $conexao->rollback();
        header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Erro ao atualizar cálculo: " . urlencode($e->getMessage()));
        exit;
    }
}

// Excluir cálculo
if ($action === 'excluir' && isset($_GET['id'])) {
    $calculo_id = $_GET['id'];
    $competencia = $_GET['competencia'] ?? date('Y-m');
    
    try {
        $conexao->begin_transaction();
        
        // Verificar se o cálculo pertence ao usuário
        $query = "SELECT id, grupo_id FROM calculos_fronteira WHERE id = ? AND usuario_id = ?";
        if ($stmt = $conexao->prepare($query)) {
            $stmt->bind_param("ii", $calculo_id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $calculo = $result->fetch_assoc();
            $stmt->close();
            
            if (!$calculo) {
                header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Cálculo não encontrado");
                exit;
            }
            
            // Primeiro excluir os produtos associados ao grupo
            if ($calculo['grupo_id']) {
                $query = "DELETE FROM grupo_calculo_produtos WHERE grupo_calculo_id = ?";
                if ($stmt = $conexao->prepare($query)) {
                    $stmt->bind_param("i", $calculo['grupo_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Depois excluir o cálculo
            $query = "DELETE FROM calculos_fronteira WHERE id = ?";
            if ($stmt = $conexao->prepare($query)) {
                $stmt->bind_param("i", $calculo_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Por último, excluir o grupo de cálculo
            if ($calculo['grupo_id']) {
                $query = "DELETE FROM grupos_calculo WHERE id = ?";
                if ($stmt = $conexao->prepare($query)) {
                    $stmt->bind_param("i", $calculo['grupo_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $conexao->commit();
            
            // Redirecionar de volta para a página de fronteira
            header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&msg=excluido");
            exit;
        }
        
    } catch (Exception $e) {
        $conexao->rollback();
        header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Erro ao excluir cálculo: " . urlencode($e->getMessage()));
        exit;
    }
}

// Se nenhuma ação específica foi solicitada, redirecionar para a página principal
header("Location: fronteira-fiscal.php");
exit;
?>