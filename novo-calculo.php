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

$competencia = $_GET['competencia'] ?? date('Y-m');
$usuario_id = $_SESSION['usuario_id'];
$nota_id = $_GET['nota_id'] ?? 0;
$produtos_ids = $_GET['produtos'] ?? [];

// Buscar produtos selecionados se houver
$produtos_selecionados = [];
if (!empty($produtos_ids) && $nota_id) {
    $placeholders = implode(',', array_fill(0, count($produtos_ids), '?'));
    $query = "SELECT * FROM nfe_itens WHERE id IN ($placeholders) AND nfe_id = ?";
    if ($stmt = $conexao->prepare($query)) {
        $types = str_repeat('i', count($produtos_ids)) . 'i';
        $params = array_merge($produtos_ids, [$nota_id]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $produtos_selecionados = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$competencia_nota = $competencia;
// Buscar informações completas da nota
$nota_info = [];
if ($nota_id) {
    $query_nota = "SELECT n.*, e.razao_social as emitente, e.cnpj as emitente_cnpj 
                   FROM nfe n 
                   LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj 
                   WHERE n.id = ?";
    if ($stmt_nota = $conexao->prepare($query_nota)) {
        $stmt_nota->bind_param("i", $nota_id);
        $stmt_nota->execute();
        $result_nota = $stmt_nota->get_result();
        $nota_info = $result_nota->fetch_assoc();
        $stmt_nota->close();
    }
}

// Calcular totais dos produtos selecionados
$valor_total_produtos = 0;
$valor_total_ipi = 0;
$valor_total_icms = 0;

foreach ($produtos_selecionados as $produto) {
    $valor_total_produtos += floatval($produto['valor_total']);
    $valor_total_ipi += floatval($produto['valor_ipi']);
    $valor_total_icms += floatval($produto['valor_icms']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Cálculo - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4 py-6">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl">
            <div class="p-6">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-semibold text-gray-800">Novo Cálculo <?php echo $nota_id ? 'de Produtos Selecionados' : 'Manual'; ?></h3>
                    <a href="fronteira-fiscal.php?competencia=<?php echo $competencia; ?>" class="text-gray-500 hover:text-gray-700">
                        <i data-feather="x" class="w-6 h-6"></i>
                    </a>
                </div>

                <?php if (!empty($nota_info)): ?>
                <div class="bg-gray-50 p-4 rounded-md mb-4">
                    <h4 class="text-lg font-medium text-gray-800 mb-2">Informações da Nota Fiscal</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm"><strong>Número:</strong> <?php echo htmlspecialchars($nota_info['numero']); ?></p>
                            <p class="text-sm"><strong>Emissão:</strong> <?php echo date('d/m/Y', strtotime($nota_info['data_emissao'])); ?></p>
                            <p class="text-sm"><strong>Chave de Acesso:</strong> <?php echo htmlspecialchars($nota_info['chave_acesso']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm"><strong>Emitente:</strong> <?php echo htmlspecialchars($nota_info['emitente']); ?></p>
                            <p class="text-sm"><strong>CNPJ:</strong> <?php echo htmlspecialchars($nota_info['emitente_cnpj']); ?></p>
                            <p class="text-sm"><strong>Valor Total da Nota:</strong> R$ <?php echo number_format($nota_info['valor_total'], 2, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Lista de produtos selecionados -->
                <?php if (!empty($produtos_selecionados)): ?>
                <div class="bg-blue-50 p-4 rounded-md mb-4">
                    <h4 class="text-lg font-medium text-blue-800 mb-2">Produtos Selecionados</h4>
                    <div class="table-responsive">
                        <table class="min-w-full divide-y divide-blue-200">
                            <thead class="bg-blue-100">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Código</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">NCM</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Descrição</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Unidade</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Quantidade</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor Unitário</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor Total</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor IPI</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-blue-600 uppercase">Valor ICMS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-blue-200">
                                <?php foreach ($produtos_selecionados as $produto): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($produto['codigo_produto']); ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($produto['ncm'] ?? ''); ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($produto['descricao']); ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($produto['unidade'] ?? ''); ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo number_format($produto['quantidade'], 4, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-sm">R$ <?php echo number_format($produto['valor_unitario'], 4, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-sm">R$ <?php echo number_format($produto['valor_total'], 2, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-sm">R$ <?php echo number_format($produto['valor_ipi'], 2, ',', '.'); ?></td>
                                    <td class="px-4 py-2 text-sm">R$ <?php echo number_format($produto['valor_icms'], 2, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="bg-blue-100 font-semibold">
                                    <td class="px-4 py-2" colspan="6">TOTAL</td>
                                    <td class="px-4 py-2">R$ <?php echo number_format($valor_total_produtos, 2, ',', '.'); ?></td>
                                    <td class="px-4 py-2">R$ <?php echo number_format($valor_total_ipi, 2, ',', '.'); ?></td>
                                    <td class="px-4 py-2">R$ <?php echo number_format($valor_total_icms, 2, ',', '.'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <form action="calculo-fronteira.php" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="salvar_calculo">
                    <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                    <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                    <input type="hidden" name="competencia" value="<?php echo $competencia_nota; ?>">
                    
                    <!-- Campos ocultos para produtos selecionados -->
                    <?php foreach ($produtos_ids as $produto_id): ?>
                    <input type="hidden" name="produtos[]" value="<?php echo $produto_id; ?>">
                    <?php endforeach; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição do Cálculo *</label>
                            <input type="text" id="descricao" name="descricao" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="nota_numero" class="block text-sm font-medium text-gray-700 mb-1">Número da Nota (opcional)</label>
                            <input type="text" id="nota_numero" name="nota_numero" class="w-full px-4 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>
                    
                    <div>
                        <label for="informacoes_adicionais" class="block text-sm font-medium text-gray-700 mb-1">Informações Adicionais</label>
                        <textarea id="informacoes_adicionais" name="informacoes_adicionais" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_produto" class="block text-sm font-medium text-gray-700 mb-1">Valor do Produto (R$)</label>
                            <input type="number" step="0.01" id="valor_produto" name="valor_produto" value="<?php echo $valor_total_produtos; ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_frete" class="block text-sm font-medium text-gray-700 mb-1">Valor do Frete (R$)</label>
                            <input type="number" step="0.01" id="valor_frete" name="valor_frete" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_ipi" class="block text-sm font-medium text-gray-700 mb-1">Valor do IPI (R$)</label>
                            <input type="number" step="0.01" id="valor_ipi" name="valor_ipi" value="<?php echo $valor_total_ipi; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_seguro" class="block text-sm font-medium text-gray-700 mb-1">Valor do Seguro (R$)</label>
                            <input type="number" step="0.01" id="valor_seguro" name="valor_seguro" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_icms" class="block text-sm font-medium text-gray-700 mb-1">Valor do Crédito ICMS (R$)</label>
                            <input type="number" step="0.01" id="valor_icms" name="valor_icms" value="<?php echo $valor_total_icms; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_gnre" class="block text-sm font-medium text-gray-700 mb-1">Valor GNRE (R$)</label>
                            <input type="number" step="0.01" id="valor_gnre" name="valor_gnre" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="aliquota_interestadual" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Interestadual (%)</label>
                            <input type="number" step="0.01" id="aliquota_interestadual" name="aliquota_interestadual" value="12" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="aliquota_interna" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Interna (%)</label>
                            <input type="number" step="0.01" id="aliquota_interna" name="aliquota_interna" value="20.5" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="mva_original" class="block text-sm font-medium text-gray-700 mb-1">MVA Original (%)</label>
                            <input type="number" step="0.01" id="mva_original" name="mva_original" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="mva_cnae" class="block text-sm font-medium text-gray-700 mb-1">MVA CNAE (%)</label>
                            <input type="number" step="0.01" id="mva_cnae" name="mva_cnae" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="aliquota_reducao" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Redução (%)</label>
                            <input type="number" step="0.01" id="aliquota_reducao" name="aliquota_reducao" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="diferencial_aliquota" class="block text-sm font-medium text-gray-700 mb-1">Diferencial Alíquota Simples (%)</label>
                            <input type="number" step="0.01" id="diferencial_aliquota" name="diferencial_aliquota" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="regime_fornecedor" class="block text-sm font-medium text-gray-700 mb-1">Regime do Fornecedor</label>
                            <select id="regime_fornecedor" name="regime_fornecedor" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="1">Simples Nacional</option>
                                <option value="2">Simples Nacional com Excedência</option>
                                <option value="3" selected>Regime Normal</option>
                            </select>
                        </div>

                        <div>
                            <label for="empresa_regular" class="block text-sm font-medium text-gray-700 mb-1">Situação da Empresa</label>
                            <select id="empresa_regular" name="empresa_regular" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="S" selected>Regular</option>
                                <option value="N">Irregular</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="tipo_calculo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Cálculo</label>
                            <select id="tipo_calculo" name="tipo_calculo" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="icms_st">ICMS ST</option>
                                <option value="icms_simples">ICMS Tributado Simples</option>
                                <option value="icms_real">ICMS Tributado Real/Presumido</option>
                                <option value="icms_consumo">ICMS Uso e Consumo</option>
                                <option value="icms_reducao">ICMS Redução</option>
                                <option value="icms_reducao_sn">ICMS Redução SN</option>
                                <option value="todos">Todos os Cálculos</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-center mt-4">
                        <input type="checkbox" id="tipo_credito_icms" name="tipo_credito_icms" value="manual" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="tipo_credito_icms" class="ml-2 block text-sm text-gray-700">Usar ICMS crédito manual (em vez do destacado na nota)</label>
                    </div>
                    
                    <div class="flex justify-end pt-4 border-t mt-6">
                        <a href="fronteira-fiscal.php?competencia=<?php echo $competencia; ?>" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 mr-3">
                            Cancelar
                        </a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Calcular e Salvar
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
</html>