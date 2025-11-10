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

// Verificar se o tipo e ID da nota foram passados
if (!isset($_GET['tipo']) || !isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: importar-notas-fiscais.php");
    exit;
}

$tipo_nota = $_GET['tipo'];
$nota_id = $_GET['id'];

// Buscar dados da nota fiscal baseado no tipo
if ($tipo_nota == 'nfe') {
    $sql_nota = "SELECT * FROM nfe WHERE id = ? AND usuario_id = ?";
    $tabela_itens = 'nfe_itens';
    $campo_id = 'nfe_id';
} else {
    $sql_nota = "SELECT * FROM nfce WHERE id = ? AND usuario_id = ?";
    $tabela_itens = 'nfce_itens';
    $campo_id = 'nfce_id';
}

$stmt = mysqli_prepare($conexao, $sql_nota);
mysqli_stmt_bind_param($stmt, "ii", $nota_id, $usuario_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) === 0) {
    header("Location: importar-notas-fiscais.php");
    exit;
}

$nota = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

// Buscar itens da nota fiscal
$itens = [];
$sql_itens = "SELECT * FROM $tabela_itens WHERE $campo_id = ? ORDER BY numero_item";
$stmt = mysqli_prepare($conexao, $sql_itens);
mysqli_stmt_bind_param($stmt, "i", $nota_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($resultado)) {
    $itens[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Nota Fiscal - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
        }
        .sidebar {
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .activity-item:hover {
            background-color: #f1f5f9;
        }
        .avatar {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i data-feather="file-text" class="text-indigo-600 w-6 h-6"></i>
                    <h1 class="text-xl font-bold text-gray-800">Sistema Contábil Integrado</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3">
                        <div class="avatar rounded-full w-10 h-10 flex items-center justify-center text-white">
                            <i data-feather="building" class="w-5 h-5"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($razao_social); ?></span>
                            <span class="text-xs text-gray-500">CNPJ: <?php echo htmlspecialchars($cnpj); ?></span>
                        </div>
                    </div>
                    <a href="index.php" class="flex items-center text-sm text-gray-600 hover:text-indigo-600 transition-colors">
                        <i data-feather="log-out" class="w-4 h-4 mr-1"></i> Sair
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex flex-1">
            <!-- Sidebar -->
            <nav class="hidden md:block w-64 bg-white shadow-sm border-r border-gray-200">
                <ul class="py-4">
                    <li>
                        <a href="dashboard.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="activity" class="w-4 h-4 mr-3"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="dashboard-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="file-text" class="w-4 h-4 mr-3"></i> Dashboard Fiscal
                        </a>
                    </li>
                    <li>
                        <a href="livro-caixa.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="book" class="w-4 h-4 mr-3"></i> Livro Caixa
                        </a>
                    </li>
                    <li>
                        <a href="conversao-contabil.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="repeat" class="w-4 h-4 mr-3"></i> Conversão Contábil
                        </a>
                    </li>
                    <li>
                        <a href="importar-notas-fiscais.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
                            <i data-feather="file-text" class="w-4 h-4 mr-3"></i> Notas Fiscais
                        </a>
                    </li>
                    <li>
                        <a href="conversao-notas-txt.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="file-text" class="w-4 h-4 mr-3"></i> Conversão Notas TXT
                        </a>
                    </li>
                    
                </ul>
            </nav>

            <!-- Content Area -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-feather="file-text" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Detalhes da <?php echo $tipo_nota == 'nfe' ? 'NFe' : 'NFCe'; ?>
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Nº <?php echo htmlspecialchars($nota['numero']); ?> - Série <?php echo htmlspecialchars($nota['serie']); ?></p>
                            </div>
                            <a href="importar-notas-fiscais.php" class="mt-4 md:mt-0 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm flex items-center justify-center">
                                <i data-feather="arrow-left" class="w-4 h-4 mr-2"></i> Voltar
                            </a>
                        </div>
                    </div>

                    <!-- Informações da Nota Fiscal -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Informações da Nota Fiscal</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Chave de Acesso</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['chave_acesso']); ?></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Número</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['numero']); ?></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Série</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['serie']); ?></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Data de Emissão</h4>
                                    <p class="text-sm text-gray-800"><?php echo date('d/m/Y', strtotime($nota['data_emissao'])); ?></p>
                                </div>
                                <?php if ($tipo_nota == 'nfe'): ?>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Data de Entrada/Saída</h4>
                                    <p class="text-sm text-gray-800"><?php echo date('d/m/Y', strtotime($nota['data_entrada_saida'])); ?></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Data de Vencimento</h4>
                                    <p class="text-sm text-gray-800"><?php echo date('d/m/Y', strtotime($nota['data_vencimento'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Competência</h4>
                                    <p class="text-sm text-gray-800"><?php echo $nota['competencia_mes'] . '/' . $nota['competencia_ano']; ?></p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Emitente</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['emitente_nome']); ?></p>
                                    <p class="text-xs text-gray-500">CNPJ: <?php echo htmlspecialchars($nota['emitente_cnpj']); ?></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Destinatário</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['destinatario_nome']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo $tipo_nota == 'nfe' ? 'CNPJ: ' . htmlspecialchars($nota['destinatario_cnpj']) : 'CPF/CNPJ: ' . htmlspecialchars($nota['destinatario_cpf_cnpj']); ?>
                                    </p>
                                </div>
                                <?php if ($tipo_nota == 'nfe'): ?>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Modalidade de Frete</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['modalidade_frete']); ?></p>
                                </div>
                                <?php else: ?>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Forma de Pagamento</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['forma_pagamento']); ?></p>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Tipo de Operação</h4>
                                    <p class="text-sm text-gray-800">
                                        <?php echo $nota['tipo_operacao'] == 'entrada' ? 'Entrada' : 'Saída'; ?>
                                    </p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Status</h4>
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($nota['status']); ?></p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-500">Data de Importação</h4>
                                    <p class="text-sm text-gray-800"><?php echo date('d/m/Y H:i', strtotime($nota['data_importacao'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Valores da Nota Fiscal -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Valores da Nota Fiscal</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor Total</h4>
                                <p class="text-xl font-bold text-green-600">R$ <?php echo number_format($nota['valor_total'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor dos Produtos</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_produtos'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do Desconto</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_desconto'], 2, ',', '.'); ?></p>
                            </div>
                            
                            <?php if ($tipo_nota == 'nfe'): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do Frete</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_frete'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do Seguro</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_seguro'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Outras Despesas</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_outras_despesas'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do IPI</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_ipi'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do ICMS</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_icms'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do ICMS ST</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_icms_st'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do PIS</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_pis'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do COFINS</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_cofins'], 2, ',', '.'); ?></p>
                            </div>
                            <?php else: ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor Pago</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_pago'], 2, ',', '.'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500">Valor do Troco</h4>
                                <p class="text-lg font-medium text-gray-800">R$ <?php echo number_format($nota['valor_troco'], 2, ',', '.'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Itens da Nota Fiscal -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Itens da Nota Fiscal (<?php echo count($itens); ?> itens)</h3>
                        
                        <?php if (count($itens) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NCM</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CFOP</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Unit.</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($itens as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $item['numero_item']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['codigo_produto']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($item['descricao']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['ncm']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($item['cfop']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($item['quantidade'], 4, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($item['valor_unitario'], 4, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">R$ <?php echo number_format($item['valor_total'], 2, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <i data-feather="package" class="w-12 h-12 text-gray-300 mx-auto"></i>
                            <p class="mt-4 text-gray-500">Nenhum item encontrado para esta nota fiscal.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Inicializar feather icons
        feather.replace();
    </script>
</body>
</html>