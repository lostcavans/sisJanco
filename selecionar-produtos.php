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

include("config.php");

$logado = $_SESSION['usuario_username'];
$usuario_id = $_SESSION['usuario_id'];
$razao_social = $_SESSION['usuario_razao_social'] ?? '';
$cnpj = $_SESSION['usuario_cnpj'] ?? '';

$usuario_id = $_SESSION['usuario_id'];
$nota_id = $_GET['nota_id'] ?? 0;
$competencia = $_GET['competencia'] ?? date('Y-m');
$aba = $_GET['aba'] ?? 'fronteira'; // Nova variável para controlar a aba

// Buscar dados da nota
$nota = [];
$query = "SELECT n.*, e.razao_social as emitente FROM nfe n LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj WHERE n.id = ? AND n.usuario_id = ?";
if ($stmt = $conexao->prepare($query)) {
    $stmt->bind_param("ii", $nota_id, $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $nota = $result->fetch_assoc();
    $stmt->close();
}

// Buscar produtos da nota
$produtos = [];
$query = "SELECT * FROM nfe_itens WHERE nfe_id = ? ORDER BY numero_item";
if ($stmt = $conexao->prepare($query)) {
    $stmt->bind_param("i", $nota_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $produtos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Lista de produtos da cesta básica (NCMs e descrições aproximadas)
$produtos_cesta_basica = [
    'feijao' => ['ncm' => ['0713', '0713.33'], 'descricao' => 'Feijão'],
    'farinha_mandioca' => ['ncm' => ['1106', '1106.20'], 'descricao' => 'Farinha de mandioca'],
    'goma_mandioca' => ['ncm' => ['1106', '1106.20'], 'descricao' => 'Goma de mandioca'],
    'massa_mandioca' => ['ncm' => ['1902', '1902.30'], 'descricao' => 'Massa de mandioca'],
    'charque' => ['ncm' => ['0210', '0210.20'], 'descricao' => 'Charque'],
    'fuba_milho' => ['ncm' => ['1102', '1102.20'], 'descricao' => 'Fubá de milho'],
    'leite_po' => ['ncm' => ['0402', '0402.10'], 'descricao' => 'Leite em pó'],
    'sal_cozinha' => ['ncm' => ['2501', '2501.00'], 'descricao' => 'Sal de cozinha'],
    'pescado' => ['ncm' => ['0302', '0303', '0304', '0305'], 'descricao' => 'Pescado'],
    'sabao_tablete' => ['ncm' => ['3401', '3401.19'], 'descricao' => 'Sabão em tabletes'],
    'sardinha_lata' => ['ncm' => ['1604', '1604.13'], 'descricao' => 'Sardinha em lata'],
    'batata_inglesa' => ['ncm' => ['0701', '0701.90'], 'descricao' => 'Batata inglesa'],
    'po_bebida_lactea' => ['ncm' => ['1901', '1901.90'], 'descricao' => 'Pó para bebida láctea'],
    'carne_moida' => ['ncm' => ['0202', '0202.30'], 'descricao' => 'Carne moída']
];

// Cargas tributárias da cesta básica (em %)
$cargas_tributarias = [
    'feijao_ate_5kg_norte_nordeste_centro_oeste_es' => 5.0,
    'feijao_ate_5kg_sul_sudeste' => 10.0,
    'feijao_acima_5kg' => 2.5,
    'pescado_industrial_credenciado' => 2.5,
    'pescado_demais_casos' => 4.0,
    'demais_produtos' => 2.5
];

// Pautas fiscais (valores de referência por kg/litro/unidade)
$pautas_fiscais = [
    'feijao' => 8.50, // R$/kg
    'farinha_mandioca' => 4.20, // R$/kg
    'charque' => 25.00, // R$/kg
    'carne_moida' => 16.92, // R$/kg
    'leite_po' => 35.00, // R$/kg
    'sardinha_lata' => 12.00, // R$/unidade
    'batata_inglesa' => 3.50, // R$/kg
    // Adicionar outras pautas conforme necessário
];

// Função para verificar se um produto é da cesta básica
function isProdutoCestaBasica($produto, $produtos_cesta_basica) {
    $ncm = substr($produto['ncm'] ?? '', 0, 4);
    $descricao = strtolower($produto['descricao']);
    
    foreach ($produtos_cesta_basica as $item) {
        foreach ($item['ncm'] as $ncm_cesta) {
            if (strpos($ncm, $ncm_cesta) === 0) {
                return true;
            }
        }
        
        // Verificar também por palavras-chave na descrição
        $palavras_chave = explode(' ', strtolower($item['descricao']));
        $match_count = 0;
        foreach ($palavras_chave as $palavra) {
            if (strpos($descricao, $palavra) !== false) {
                $match_count++;
            }
        }
        if ($match_count >= 2) { // Pelo menos 2 palavras correspondem
            return true;
        }
    }
    
    return false;
}

// Função para determinar a carga tributária do produto
function getCargaTributaria($produto, $cargas_tributarias, $regiao_fornecedor = 'sul_sudeste') {
    $descricao = strtolower($produto['descricao']);
    $unidade = strtolower($produto['unidade'] ?? '');
    
    // Feijão
    if (strpos($descricao, 'feijão') !== false || strpos($descricao, 'feijao') !== false) {
        // Verificar se é embalagem até 5kg (simplificado - verificar na descrição ou unidade)
        if (strpos($descricao, '1kg') !== false || strpos($descricao, '2kg') !== false || 
            strpos($descricao, '5kg') !== false || $unidade == 'pacote' || $unidade == 'embalagem') {
            
            if ($regiao_fornecedor == 'norte_nordeste_centro_oeste_es') {
                return $cargas_tributarias['feijao_ate_5kg_norte_nordeste_centro_oeste_es'];
            } else {
                return $cargas_tributarias['feijao_ate_5kg_sul_sudeste'];
            }
        } else {
            return $cargas_tributarias['feijao_acima_5kg'];
        }
    }
    
    // Pescado
    if (strpos($descricao, 'peixe') !== false || strpos($descricao, 'pescado') !== false) {
        // Simplificação - considerar como demais casos
        return $cargas_tributarias['pescado_demais_casos'];
    }
    
    // Demais produtos
    return $cargas_tributarias['demais_produtos'];
}

// Função para calcular ICMS da cesta básica (nova lógica)
function calcularIcmsCestaBasica($valor_total_produtos, $peso_agrupado, $unidade_medida, $quantidade, $percentual_pauta, $carga_tributaria) {
    // Converter para kg se necessário
    $peso_kg = $peso_agrupado;
    if ($unidade_medida === 'g') {
        $peso_kg = $peso_agrupado / 1000;
    } elseif ($unidade_medida === 'un') {
        // Se for unidade, considerar 1 unidade = 1 kg para cálculo (ou ajuste conforme necessário)
        $peso_kg = $peso_agrupado;
    }
    
    // Calcular resultado: Peso Agrupado × Quantidade × % Pauta
    $resultado_pauta = $peso_kg * $quantidade * ($percentual_pauta);
    
    // Usar o maior valor entre o resultado da pauta e o valor total dos produtos
    $base_calculo = max($resultado_pauta, $valor_total_produtos);
    
    // Calcular ICMS: Base de Cálculo × Alíquota Efetiva
    $icms_calculado = $base_calculo * ($carga_tributaria / 100);
    
    // Garantir que não retorne valores negativos
    $icms_calculado = max(0, $icms_calculado);
    
    return [
        'icms_calculado' => $icms_calculado,
        'base_calculo' => $base_calculo,
        'resultado_pauta' => $resultado_pauta,
        'peso_kg' => $peso_kg
    ];
}

// Processar e salvar cálculo da cesta básica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calcular_cesta_basica') {
    $produtos_selecionados_ids = $_POST['produtos_cesta'] ?? [];
    $regiao_fornecedor = $_POST['regiao_fornecedor'] ?? 'sul_sudeste';
    $peso_agrupado = floatval($_POST['peso_agrupado'] ?? 0);
    $unidade_medida = $_POST['unidade_medida'] ?? 'kg';
    $quantidade = floatval($_POST['quantidade'] ?? 0);
    $percentual_pauta = floatval($_POST['percentual_pauta'] ?? 0);
    $descricao_calculo = $_POST['descricao_calculo'] ?? 'Cálculo Cesta Básica - ' . date('d/m/Y H:i');
    
    try {
        // Validações
        if (empty($produtos_selecionados_ids)) {
            throw new Exception("Selecione pelo menos um produto da cesta básica.");
        }
        
        if ($peso_agrupado <= 0) {
            throw new Exception("Peso agrupado deve ser maior que zero.");
        }
        
        if ($quantidade <= 0) {
            throw new Exception("Quantidade deve ser maior que zero.");
        }
        
        if ($percentual_pauta < 0 || $percentual_pauta > 100) {
            throw new Exception("Percentual da pauta deve estar entre 0 e 100.");
        }

        $conexao->begin_transaction();
        
        // Calcular valor total dos produtos selecionados
        $total_valor_produtos = 0;
        foreach ($produtos_selecionados_ids as $produto_id) {
            foreach ($produtos as $produto) {
                if ($produto['id'] == $produto_id && isProdutoCestaBasica($produto, $produtos_cesta_basica)) {
                    $total_valor_produtos += floatval($produto['valor_total']);
                    break;
                }
            }
        }
        
        if ($total_valor_produtos <= 0) {
            throw new Exception("Valor total dos produtos deve ser maior que zero.");
        }
        
        // Determinar carga tributária
        $carga_tributaria = 2.5; // padrão
        if (!empty($produtos_selecionados_ids)) {
            $primeiro_produto_id = $produtos_selecionados_ids[0];
            foreach ($produtos as $produto) {
                if ($produto['id'] == $primeiro_produto_id) {
                    $carga_tributaria = getCargaTributaria($produto, $cargas_tributarias, $regiao_fornecedor);
                    break;
                }
            }
        }
        
        // Calcular ICMS
        $resultado_calculo = calcularIcmsCestaBasica(
            $total_valor_produtos, 
            $peso_agrupado, 
            $unidade_medida, 
            $quantidade, 
            $percentual_pauta, 
            $carga_tributaria
        );
        
        $total_icms_cesta = $resultado_calculo['icms_calculado'];

        $detalhes_calculo = [
            'valor_total_produtos' => $total_valor_produtos,
            'peso_kg' => $resultado_calculo['peso_kg'],
            'quantidade' => $quantidade,
            'percentual_pauta' => $percentual_pauta,
            'resultado_pauta' => $resultado_calculo['resultado_pauta'],
            'base_calculo' => $resultado_calculo['base_calculo'],
            'carga_tributaria' => $carga_tributaria,
            'icms_calculado' => $total_icms_cesta
        ];
        
        // 1. Criar grupo de cálculo
        $query_grupo = "INSERT INTO grupos_calculo_cesta (usuario_id, descricao, nota_fiscal_id, competencia) VALUES (?, ?, ?, ?)";
        
        if ($stmt_grupo = $conexao->prepare($query_grupo)) {
            $stmt_grupo->bind_param("isis", $usuario_id, $descricao_calculo, $nota_id, $competencia);
            if (!$stmt_grupo->execute()) {
                throw new Exception("Erro ao salvar grupo: " . $stmt_grupo->error);
            }
            $grupo_id = $conexao->insert_id;
            $stmt_grupo->close();
        } else {
            throw new Exception("Erro ao preparar query do grupo: " . $conexao->error);
        }
        
        // 2. Criar cálculo principal - VERSÃO COMPLETA COM TODOS OS CAMPOS
        $query_calculo = "INSERT INTO calculos_cesta_basica 
            (usuario_id, grupo_id, descricao, regiao_fornecedor, peso_agrupado, unidade_medida, 
            quantidade, percentual_pauta, carga_tributaria, valor_total_produtos, valor_total_icms, 
            base_calculo, resultado_pauta, competencia) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt_calculo = $conexao->prepare($query_calculo)) {
            $stmt_calculo->bind_param("iissdsidddddss", 
                $usuario_id, 
                $grupo_id, 
                $descricao_calculo, 
                $regiao_fornecedor,
                $peso_agrupado,
                $unidade_medida,
                $quantidade,
                $percentual_pauta,
                $carga_tributaria,
                $total_valor_produtos,
                $total_icms_cesta,
                $resultado_calculo['base_calculo'], // base_calculo
                $resultado_calculo['resultado_pauta'], // resultado_pauta
                $competencia
            );
            
            if ($stmt_calculo->execute()) {
                $calculo_cesta_id = $conexao->insert_id;
                error_log("Cálculo principal salvo com ID: $calculo_cesta_id");
            } else {
                throw new Exception("Erro ao executar inserção do cálculo: " . $stmt_calculo->error);
            }
            $stmt_calculo->close();
        } else {
            throw new Exception("Erro ao preparar query do cálculo: " . $conexao->error);
        }
        
        // 3. Salvar produtos do cálculo
        $query_produtos = "INSERT INTO cesta_calculo_produtos (calculo_cesta_id, produto_id, carga_tributaria, valor_icms, tipo_calculo) VALUES (?, ?, ?, ?, ?)";

        if ($stmt_produtos = $conexao->prepare($query_produtos)) {
            $tipo_calculo_produto = "Cesta Básica"; // Defina o tipo de cálculo para os produtos
            
            foreach ($produtos_selecionados_ids as $produto_id) {
                foreach ($produtos as $produto) {
                    if ($produto['id'] == $produto_id) {
                        // Distribuir o ICMS proporcionalmente
                        $percentual_produto = floatval($produto['valor_total']) / $total_valor_produtos;
                        $icms_produto = $total_icms_cesta * $percentual_produto;
                        
                        $stmt_produtos->bind_param("iidds", $calculo_cesta_id, $produto['id'], $carga_tributaria, $icms_produto, $tipo_calculo_produto);
                        if (!$stmt_produtos->execute()) {
                            throw new Exception("Erro ao salvar produto ID $produto_id: " . $stmt_produtos->error);
                        }
                        break;
                    }
                }
            }
            $stmt_produtos->close();
        }
        
        $conexao->commit();
        $_SESSION['msg_sucesso'] = "Cálculo da cesta básica salvo com sucesso!";
        header("Location: fronteira-fiscal.php?competencia=" . $competencia);
        exit;
        
    } catch (Exception $e) {
        $conexao->rollback();
        $error = "Erro ao salvar cálculo: " . $e->getMessage();
        error_log("ERRO Cesta Básica: " . $e->getMessage());
    }
}

// Debug - verificar se o POST está chegando
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST recebido em selecionar-produtos.php");
    error_log("Action: " . ($_POST['action'] ?? 'Nenhum'));
    error_log("Produtos selecionados: " . count($_POST['produtos_cesta'] ?? []));
}

// Processar produtos selecionados para cesta básica (apenas para exibição)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calcular_cesta_basica') {
        error_log("Iniciando processamento do cálculo da cesta básica");
    
    // Debug dos dados recebidos
    error_log("Dados recebidos:");
    error_log("Produtos selecionados: " . print_r($_POST['produtos_cesta'] ?? [], true));
    error_log("Peso agrupado: " . ($_POST['peso_agrupado'] ?? 'N/A'));
    error_log("Quantidade: " . ($_POST['quantidade'] ?? 'N/A'));
    $produtos_selecionados_ids = $_POST['produtos_cesta'] ?? [];
    $regiao_fornecedor = $_POST['regiao_fornecedor'] ?? 'sul_sudeste';
    
    $resultados_cesta = [];
    $total_icms_cesta = 0;
    
    foreach ($produtos_selecionados_ids as $produto_id) {
        // Buscar dados do produto
        foreach ($produtos as $produto) {
            if ($produto['id'] == $produto_id && isProdutoCestaBasica($produto, $produtos_cesta_basica)) {
                $carga_tributaria = getCargaTributaria($produto, $cargas_tributarias, $regiao_fornecedor);
                
                // Determinar pauta fiscal (simplificado)
                $pauta = 0;
                foreach ($pautas_fiscais as $produto_pauta => $valor) {
                    if (strpos(strtolower($produto['descricao']), $produto_pauta) !== false) {
                        $pauta = $valor;
                        break;
                    }
                }
                
                // Usar o cálculo já realizado
                if (isset($resultado_calculo)) {
                    $icms_cesta = $resultado_calculo['icms_calculado'];
                    $total_icms_cesta += $icms_cesta;
                    
                    $resultados_cesta[] = [
                        'produto' => $produto,
                        'carga_tributaria' => $carga_tributaria,
                        'pauta_fiscal' => $pauta,
                        'icms_calculado' => $icms_cesta,
                        'tipo_calculo' => $pauta > 0 ? 'Com pauta fiscal' : 'Sem pauta fiscal'
                    ];
                }
                
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Produtos - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl">
            <div class="p-6">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-semibold text-gray-800">Selecionar Produtos para Cálculo</h3>
                    <a href="fronteira-fiscal.php?competencia=<?php echo $competencia; ?>" class="text-gray-500 hover:text-gray-700">
                        <i data-feather="x" class="w-6 h-6"></i>
                    </a>
                </div>
                
                <!-- Abas -->
                <div class="mt-4 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <a href="?nota_id=<?php echo $nota_id; ?>&competencia=<?php echo $competencia; ?>&aba=fronteira" 
                           class="<?php echo $aba == 'fronteira' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Cálculo Fronteira
                        </a>
                        <a href="?nota_id=<?php echo $nota_id; ?>&competencia=<?php echo $competencia; ?>&aba=cesta" 
                           class="<?php echo $aba == 'cesta' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Cesta Básica
                        </a>
                    </nav>
                </div>
                
                <div class="mt-4">
                    <h4 class="text-lg font-medium text-gray-800 mb-2">Nota: <?php echo htmlspecialchars($nota['numero'] ?? ''); ?></h4>
                    
                    <!-- Mensagens de erro/sucesso -->
                    <?php if (isset($error)): ?>
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($msg_sucesso)): ?>
                    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        <?php echo htmlspecialchars($msg_sucesso); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($aba == 'fronteira'): ?>
                    <!-- Aba Fronteira (original) -->
                    <p class="text-sm text-gray-600 mb-4">Selecione os produtos que deseja incluir no cálculo de fronteira:</p>
                    
                    <form action="novo-calculo.php" method="GET">
                        <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                        <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                        
                        <div class="bg-gray-50 p-4 rounded-md mb-4">
                            <div class="flex items-center mb-2">
                                <input type="checkbox" id="selecionar_todos" onclick="selecionarTodosProdutos(this.checked, 'fronteira')" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="selecionar_todos" class="ml-2 block text-sm text-gray-700">Selecionar todos os produtos</label>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selecionar</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NCM</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Unitário</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor IPI</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor ICMS</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($produtos as $produto): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <input type="checkbox" name="produtos[]" value="<?php echo $produto['id']; ?>" class="produto-checkbox fronteira h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($produto['codigo_produto']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($produto['ncm'] ?? ''); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($produto['descricao']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($produto['quantidade'], 4, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($produto['valor_unitario'], 4, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($produto['valor_total'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($produto['valor_ipi'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($produto['valor_icms'], 2, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-end pt-4 border-t mt-6">
                            <a href="fronteira-fiscal.php?competencia=<?php echo $competencia; ?>" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 mr-3">
                                Cancelar
                            </a>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Agrupar e Calcular
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- Aba Cesta Básica -->
                    <p class="text-sm text-gray-600 mb-4">Selecione os produtos da cesta básica para cálculo do ICMS:</p>
                    
                    <form action="" method="POST" onsubmit="return validarFormCesta()">
                        <input type="hidden" name="action" value="calcular_cesta_basica">
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <div>
                                <label for="regiao_fornecedor" class="block text-sm font-medium text-gray-700 mb-1">Região do Fornecedor</label>
                                <select id="regiao_fornecedor" name="regiao_fornecedor" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="sul_sudeste">Sul/Sudeste (exceto ES)</option>
                                    <option value="norte_nordeste_centro_oeste_es">Norte/Nordeste/Centro-Oeste/ES</option>
                                </select>
                            </div>
                            <div>
                                <label for="peso_agrupado" class="block text-sm font-medium text-gray-700 mb-1">Peso Agrupado *</label>
                                <input type="number" id="peso_agrupado" name="peso_agrupado" step="0.001" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="unidade_medida" class="block text-sm font-medium text-gray-700 mb-1">Unidade de Medida *</label>
                                <select id="unidade_medida" name="unidade_medida" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="kg">Quilograma (kg)</option>
                                    <option value="g">Grama (g)</option>
                                    <option value="un">Unidade (un)</option>
                                </select>
                            </div>
                            <div>
                                <label for="quantidade" class="block text-sm font-medium text-gray-700 mb-1">Quantidade *</label>
                                <input type="number" id="quantidade" name="quantidade" step="0.001" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="percentual_pauta" class="block text-sm font-medium text-gray-700 mb-1">
                                    % da Pauta Fiscal *
                                    <a href="https://www.sefaz.pe.gov.br/Legislacao/Tributaria/Documents/legislacao/Tabelas/Pauta_Fiscal_Eletronica.pdf" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs ml-1" title="Consultar valores de pauta">
                                        (Consultar Pautas)
                                    </a>
                                </label>
                                <input type="number" id="percentual_pauta" name="percentual_pauta" step="0.01" min="0" max="100" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="descricao_calculo" class="block text-sm font-medium text-gray-700 mb-1">Descrição do Cálculo *</label>
                                <input type="text" id="descricao_calculo" name="descricao_calculo" value="Cálculo Cesta Básica - <?php echo date('d/m/Y H:i'); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-md mb-4">
                            <div class="flex items-center mb-2">
                                <input type="checkbox" id="selecionar_todos_cesta" onclick="selecionarTodosProdutos(this.checked, 'cesta')" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="selecionar_todos_cesta" class="ml-2 block text-sm text-gray-700">Selecionar todos os produtos da cesta básica</label>
                            </div>
                            <p class="text-xs text-gray-500">Apenas produtos identificados como cesta básica serão selecionados</p>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selecionar</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NCM</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidade</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cesta Básica</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($produtos as $produto): 
                                        $is_cesta_basica = isProdutoCestaBasica($produto, $produtos_cesta_basica);
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($is_cesta_basica): ?>
                                            <input type="checkbox" name="produtos_cesta[]" value="<?php echo $produto['id']; ?>" class="produto-checkbox cesta h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                            <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($produto['codigo_produto']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($produto['ncm'] ?? ''); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($produto['descricao']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($produto['unidade'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($produto['quantidade'], 4, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($produto['valor_total'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($is_cesta_basica): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Sim</span>
                                            <?php else: ?>
                                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Não</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-end pt-4 border-t mt-6">
                            <a href="fronteira-fiscal.php?competencia=<?php echo $competencia; ?>" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 mr-3">
                                Cancelar
                            </a>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                Calcular ICMS Cesta Básica
                            </button>
                        </div>
                    </form>
                    
                    <!-- Resultados do cálculo da cesta básica -->
                    <?php if (isset($detalhes_calculo)): ?>
                    <div class="mt-6 bg-blue-50 p-4 rounded-md">
                        <h4 class="text-lg font-medium text-blue-800 mb-2">Detalhes do Cálculo - ICMS Cesta Básica</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="bg-white p-3 rounded-md">
                                <h5 class="font-semibold text-blue-700 mb-2">Valores de Entrada</h5>
                                <p class="text-sm"><strong>Valor Total dos Produtos:</strong> R$ <?php echo number_format($detalhes_calculo['valor_total_produtos'], 2, ',', '.'); ?></p>
                                <p class="text-sm"><strong>Peso Agrupado:</strong> <?php echo number_format($detalhes_calculo['peso_kg'], 3, ',', '.'); ?> kg</p>
                                <p class="text-sm"><strong>Quantidade:</strong> <?php echo number_format($detalhes_calculo['quantidade'], 3, ',', '.'); ?></p>
                                <p class="text-sm"><strong>% da Pauta:</strong> <?php echo number_format($detalhes_calculo['percentual_pauta'], 2, ',', '.'); ?>%</p>
                                <p class="text-sm"><strong>Alíquota Efetiva:</strong> <?php echo number_format($detalhes_calculo['carga_tributaria'], 2, ',', '.'); ?>%</p>
                            </div>
                            
                            <div class="bg-white p-3 rounded-md">
                                <h5 class="font-semibold text-blue-700 mb-2">Cálculo Realizado</h5>
                                <p class="text-sm"><strong>Peso × Quantidade × % Pauta:</strong></p>
                                <p class="text-sm"><?php echo number_format($detalhes_calculo['peso_kg'], 3, ',', '.'); ?> × <?php echo number_format($detalhes_calculo['quantidade'], 3, ',', '.'); ?> × <?php echo number_format($detalhes_calculo['percentual_pauta']/100, 4, ',', '.'); ?></p>
                                <p class="text-sm"><strong>Resultado da Pauta:</strong> R$ <?php echo number_format($detalhes_calculo['resultado_pauta'], 2, ',', '.'); ?></p>
                                <p class="text-sm"><strong>Base de Cálculo (Maior valor):</strong> R$ <?php echo number_format($detalhes_calculo['base_calculo'], 2, ',', '.'); ?></p>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 p-3 rounded-md">
                            <h5 class="font-semibold text-green-700 mb-2">Resultado Final</h5>
                            <p class="text-lg font-bold">ICMS Cesta Básica: R$ <?php echo number_format($detalhes_calculo['icms_calculado'], 2, ',', '.'); ?></p>
                            <p class="text-sm">Calculado como: R$ <?php echo number_format($detalhes_calculo['base_calculo'], 2, ',', '.'); ?> × <?php echo number_format($detalhes_calculo['carga_tributaria'], 2, ',', '.'); ?>%</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        feather.replace();
        
        function selecionarTodosProdutos(selecionar, tipo) {
            const checkboxes = document.querySelectorAll('.produto-checkbox.' + tipo);
            checkboxes.forEach(checkbox => {
                checkbox.checked = selecionar;
            });
        }
        
        function validarFormCesta() {
            const produtosSelecionados = document.querySelectorAll('input[name="produtos_cesta[]"]:checked');
            const pesoAgrupado = document.getElementById('peso_agrupado').value;
            const quantidade = document.getElementById('quantidade').value;
            const percentualPauta = document.getElementById('percentual_pauta').value;
            
            if (produtosSelecionados.length === 0) {
                alert('Selecione pelo menos um produto da cesta básica.');
                return false;
            }
            
            if (parseFloat(pesoAgrupado) <= 0) {
                alert('Peso agrupado deve ser maior que zero.');
                return false;
            }
            
            if (parseFloat(quantidade) <= 0) {
                alert('Quantidade deve ser maior que zero.');
                return false;
            }
            
            if (parseFloat(percentualPauta) < 0 || parseFloat(percentualPauta) > 100) {
                alert('Percentual da pauta deve estar entre 0 e 100.');
                return false;
            }
            
            return true;
        }
        
        // Auto-selecionar produtos da cesta básica
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($aba == 'cesta'): ?>
            const checkboxesCesta = document.querySelectorAll('.produto-checkbox.cesta');
            checkboxesCesta.forEach(checkbox => {
                checkbox.checked = true;
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>