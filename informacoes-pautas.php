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

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações de Pautas Fiscais</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Valores de Pauta Fiscal - Cesta Básica</h1>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h2 class="text-lg font-semibold mb-2">Produtos da Cesta Básica</h2>
                    <ul class="list-disc list-inside space-y-2">
                        <li><strong>Feijão:</strong> R$ 8,50/kg</li>
                        <li><strong>Farinha de mandioca:</strong> R$ 4,20/kg</li>
                        <li><strong>Charque:</strong> R$ 25,00/kg</li>
                        <li><strong>Leite em pó:</strong> R$ 35,00/kg</li>
                        <li><strong>Sardinha em lata:</strong> R$ 12,00/un</li>
                        <li><strong>Batata inglesa:</strong> R$ 3,50/kg</li>
                    </ul>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h2 class="text-lg font-semibold mb-2">Como Calcular</h2>
                    <p class="text-sm mb-2"><strong>Fórmula:</strong> Peso (kg) × % Pauta</p>
                    <p class="text-sm mb-2"><strong>Exemplo:</strong> 10 kg × 80% = R$ 8,00</p>
                    <p class="text-sm"><strong>Comparar:</strong> Use o MAIOR valor entre o resultado da pauta e o valor total dos produtos.</p>
                </div>
            </div>
            
            <div class="mt-6 bg-green-50 p-4 rounded-lg">
                <h2 class="text-lg font-semibold mb-2">Cargas Tributárias</h2>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Feijão até 5kg (Norte/Nordeste/C-O/ES):</strong> 5,0%</li>
                    <li><strong>Feijão até 5kg (Sul/Sudeste):</strong> 10,0%</li>
                    <li><strong>Feijão acima 5kg:</strong> 2,5%</li>
                    <li><strong>Demais produtos:</strong> 2,5%</li>
                </ul>
            </div>
            
            <div class="mt-6 text-center">
                <button onclick="window.close()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</body>
</html>