<?php
session_start();
include("config.php");

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

if (!verificarAutenticacao()) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nota_id = $_GET['nota_id'] ?? 0;
$competencia = $_GET['competencia'] ?? date('Y-m');
$valor_produtos = $_GET['valor_produtos'] ?? 0;
$valor_ipi = $_GET['valor_ipi'] ?? 0;
$valor_icms = $_GET['valor_icms'] ?? 0;

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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cálculo de Fronteira - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4 py-6">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl">
            <div class="p-6">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-semibold text-gray-800">Cálculo de Fronteira</h3>
                    <a href="fronteira-fiscal.php?competencia=<?php echo $competencia; ?>" class="text-gray-500 hover:text-gray-700">
                        <i data-feather="x" class="w-6 h-6"></i>
                    </a>
                </div>
                
                <form action="calculo-fronteira.php" method="POST" class="mt-4 space-y-4">
                    <input type="hidden" name="action" value="salvar_calculo">
                    <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                    <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição do Cálculo *</label>
                            <input type="text" id="descricao" name="descricao" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="nota_numero" class="block text-sm font-medium text-gray-700 mb-1">Nota Fiscal</label>
                            <input type="text" id="nota_numero" value="<?php echo htmlspecialchars($nota['numero'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100" readonly>
                        </div>
                    </div>
                    
                    <div>
                        <label for="informacoes_adicionais" class="block text-sm font-medium text-gray-700 mb-1">Informações Adicionais</label>
                        <textarea id="informacoes_adicionais" name="informacoes_adicionais" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_produto" class="block text-sm font-medium text-gray-700 mb-1">Valor do Produto (R$)</label>
                            <input type="number" step="0.01" id="valor_produto" name="valor_produto" value="<?php echo $valor_produtos; ?>" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_frete" class="block text-sm font-medium text-gray-700 mb-1">Valor do Frete (R$)</label>
                            <input type="number" step="0.01" id="valor_frete" name="valor_frete" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_ipi" class="block text-sm font-medium text-gray-700 mb-1">Valor do IPI (R$)</label>
                            <input type="number" step="0.01" id="valor_ipi" name="valor_ipi" value="<?php echo $valor_ipi; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="valor_seguro" class="block text-sm font-medium text-gray-700 mb-1">Valor do Seguro (R$)</label>
                            <input type="number" step="0.01" id="valor_seguro" name="valor_seguro" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="valor_icms" class="block text-sm font-medium text-gray-700 mb-1">Valor do Crédito ICMS (R$)</label>
                            <input type="number" step="0.01" id="valor_icms" name="valor_icms" value="<?php echo $valor_icms; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="aliquota_interestadual" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Interestadual (%)</label>
                            <input type="number" step="0.01" id="aliquota_interestadual" name="aliquota_interestadual" value="12" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="aliquota_interna" class="block text-sm font-medium text-gray-700 mb-1">Alíquota Interna (%)</label>
                            <input type="number" step="0.01" id="aliquota_interna" name="aliquota_interna" value="20.5" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        
                        <div>
                            <label for="regime_fornecedor" class="block text-sm font-medium text-gray-700 mb-1">Regime do Fornecedor</label>
                            <select id="regime_fornecedor" name="regime_fornecedor" required class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="1">Simples Nacional</option>
                                <option value="2">Simples Nacional com Excedência</option>
                                <option value="3" selected>Regime Normal</option>
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