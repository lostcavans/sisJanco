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

if (isset($_SESSION['msg_sucesso'])) {
    $msg = $_SESSION['msg_sucesso'];
    unset($_SESSION['msg_sucesso']);
}

// Incluir config.php - a conexão mysqli já está estabelecida como $conexao
include("config.php");

// Verificar se a conexão foi estabelecida
if ($conexao->connect_error) {
    die("Erro de conexão com o banco de dados. Verifique o arquivo config.php");
}

$logado = $_SESSION['usuario_username'];
$usuario_id = $_SESSION['usuario_id'];
$razao_social = $_SESSION['usuario_razao_social'] ?? '';
$cnpj = $_SESSION['usuario_cnpj'] ?? '';

// Processar filtro de competência
$competencia = isset($_GET['competencia']) ? $_GET['competencia'] : date('Y-m');
list($ano, $mes) = explode('-', $competencia);

// Consulta ao banco para buscar notas de fronteira (UF diferente de 26 - Pernambuco)
$notas_fronteira = [];
$error = '';

$query = "
    SELECT n.*, e.razao_social as emitente 
    FROM nfe n 
    LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj 
    WHERE n.usuario_id = ? 
    AND n.competencia_ano = ? 
    AND n.competencia_mes = ? 
    AND SUBSTRING(n.chave_acesso, 1, 2) != '26'
    AND n.tipo_operacao = 'entrada'
";

if ($stmt = $conexao->prepare($query)) {
    $stmt->bind_param("iis", $usuario_id, $ano, $mes);
    $stmt->execute();
    $result = $stmt->get_result();
    $notas_fronteira = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $error = "Erro ao buscar notas: " . $conexao->error;
}

// Consulta ao banco para buscar cálculos existentes - FILTRAR POR COMPETÊNCIA DA NOTA
$calculos = [];

$query = "
    SELECT c.*, g.descricao as grupo_descricao, n.numero as nota_numero,
           n.valor_total as nota_valor_total, e.razao_social as emitente,
           COUNT(gcp.produto_id) as total_produtos, c.competencia as competencia_mes
    FROM calculos_fronteira c
    LEFT JOIN grupos_calculo g ON c.grupo_id = g.id
    LEFT JOIN nfe n ON g.nota_fiscal_id = n.id
    LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj
    LEFT JOIN grupo_calculo_produtos gcp ON g.id = gcp.grupo_calculo_id
    WHERE c.usuario_id = ?
    AND c.competencia = ?
    GROUP BY c.id
    ORDER BY c.data_calculo DESC
";

if ($stmt = $conexao->prepare($query)) {
    $stmt->bind_param("is", $usuario_id, $competencia);
    $stmt->execute();
    $result = $stmt->get_result();
    $calculos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $error = "Erro ao buscar cálculos: " . $conexao->error;
}

// Consulta ao banco para buscar cálculos de cesta básica existentes - QUERY DEFINITIVA CORRIGIDA
$calculos_cesta = [];

$query_cesta = "
    SELECT cc.*, 
           gc.descricao as grupo_descricao, 
           n.numero as nota_numero,
           n.valor_total as nota_valor_total, 
           e.razao_social as emitente,
           (SELECT COUNT(*) FROM cesta_calculo_produtos ccp WHERE ccp.calculo_cesta_id = cc.id) as total_produtos,
           cc.competencia as competencia_mes
    FROM calculos_cesta_basica cc
    LEFT JOIN grupos_calculo_cesta gc ON cc.grupo_id = gc.id
    LEFT JOIN nfe n ON gc.nota_fiscal_id = n.id
    LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj
    WHERE cc.usuario_id = ?
    AND cc.competencia = ?
    ORDER BY cc.data_calculo DESC
";

error_log("Query cesta básica DEFINITIVA: " . $query_cesta);
error_log("Usuário ID: " . $usuario_id . ", Competência: " . $competencia);

if ($stmt_cesta = $conexao->prepare($query_cesta)) {
    $stmt_cesta->bind_param("is", $usuario_id, $competencia);
    if ($stmt_cesta->execute()) {
        $result_cesta = $stmt_cesta->get_result();
        $calculos_cesta = $result_cesta->fetch_all(MYSQLI_ASSOC);
        error_log("Cálculos de cesta básica encontrados (DEFINITIVO): " . count($calculos_cesta));
        
        // Debug detalhado
        foreach ($calculos_cesta as $calculo) {
            error_log("Cálculo: ID=" . $calculo['id'] . 
                     ", Descrição=" . $calculo['descricao'] . 
                     ", Grupo_ID=" . $calculo['grupo_id'] . 
                     ", Nota=" . ($calculo['nota_numero'] ?? 'N/A') .
                     ", Produtos=" . $calculo['total_produtos']);
        }
    } else {
        error_log("Erro ao executar query definitiva: " . $stmt_cesta->error);
    }
    $stmt_cesta->close();
} else {
    error_log("Erro ao preparar query definitiva: " . $conexao->error);
}




// Mensagens de sucesso/erro
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? $error ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fronteira Fiscal - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
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
        .table-responsive {
            overflow-x: auto;
        }
        .btn-action {
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            transform: scale(1.05);
        }
        [x-cloak] { display: none !important; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50" x-data="fronteiraFiscal()" x-cloak>
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
                            <i data-feather="activity" class="w-4 h-4 mr-3"></i> Dashboard Contábil
                        </a>
                    </li>
                    <li>
                        <a href="dashboard-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="file-text" class="w-4 h-4 mr-3"></i> Dashboard Fiscal
                        </a>
                    </li>
                    <li>
                        <a href="xml-produtos.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="box" class="w-4 h-4 mr-3"></i> XML Produtos
                        </a>
                    </li>
                    <li>
                        <a href="conferencia-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="check-square" class="w-4 h-4 mr-3"></i> Conferência
                        </a>
                    </li>
                    <li>
                        <a href="fronteira-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
                            <i data-feather="map-pin" class="w-4 h-4 mr-3"></i> Fronteira
                        </a>
                    </li>
                    <li>
                        <a href="difal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="percent" class="w-4 h-4 mr-3"></i> DIFAL
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Content Area -->
            <main class="flex-1 p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Notificações -->
                    <?php if ($msg === 'excluido'): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline">Cálculo excluído com sucesso.</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Dashboard Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-feather="map-pin" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Fronteira Fiscal
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Controle de operações de fronteira e documentação específica</p>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro de Competência -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filtrar por Competência</h3>
                        <form method="GET" action="fronteira-fiscal.php" class="flex items-end space-x-4">
                            <div class="flex-1">
                                <label for="competencia" class="block text-sm font-medium text-gray-700 mb-1">Competência</label>
                                <input type="month" id="competencia" name="competencia" value="<?php echo htmlspecialchars($competencia); ?>" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Filtrar
                            </button>
                        </form>
                    </div>

                    <!-- Tabela de Notas de Fronteira -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Notas de Fronteira - <?php echo date('m/Y', strtotime($competencia)); ?></h3>
                        
                        <?php if (count($notas_fronteira) > 0): ?>
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emitente</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CNPJ Emitente</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Emissão</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($notas_fronteira as $nota): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($nota['numero']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($nota['emitente']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($nota['emitente_cnpj']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($nota['data_emissao'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($nota['valor_total'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="selecionar-produtos.php?nota_id=<?php echo $nota['id']; ?>&competencia=<?php echo $competencia; ?>" class="text-indigo-600 hover:text-indigo-900 btn-action">
                                                Selecionar para Cálculo
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <i data-feather="file" class="w-12 h-12 text-gray-400 mx-auto"></i>
                            <p class="mt-4 text-sm text-gray-500">Nenhuma nota de fronteira encontrada para a competência selecionada.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tabela de Cálculos -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Cálculos Realizados</h3>
                            <a href="novo-calculo.php?competencia=<?php echo $competencia; ?>" class="mt-2 md:mt-0 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 flex items-center">
                                <i data-feather="plus" class="w-4 h-4 mr-2"></i> Novo Cálculo
                            </a>
                        </div>
                        
                        <?php if (count($calculos) > 0): ?>
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota Fiscal</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emitente</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produtos</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Cálculo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Produtos</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ICMS</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($calculos as $calculo): 
                                        // Determinar qual valor de ICMS mostrar baseado no tipo_calculo
                                        $valor_icms_exibir = 0;
                                        switch ($calculo['tipo_calculo']) {
                                            case 'icms_st':
                                                $valor_icms_exibir = $calculo['icms_st'];
                                                break;
                                            case 'icms_simples':
                                                $valor_icms_exibir = $calculo['icms_tributado_simples_regular'] > 0 ? $calculo['icms_tributado_simples_regular'] : $calculo['icms_tributado_simples_irregular'];
                                                break;
                                            case 'icms_real':
                                                $valor_icms_exibir = $calculo['icms_tributado_real'];
                                                break;
                                            case 'icms_consumo':
                                                $valor_icms_exibir = $calculo['icms_uso_consumo'];
                                                break;
                                            case 'icms_reducao':
                                                $valor_icms_exibir = $calculo['icms_reducao'];
                                                break;
                                            case 'icms_reducao_sn': 
                                                $valor_icms_exibir = $calculo['icms_reducao_sn'];
                                                break;
                                            case 'icms_reducao_st_sn':
                                                $valor_icms_exibir = $calculo['icms_reducao_st_sn'];
                                                break;
                                            default:
                                                $valor_icms_exibir = $calculo['icms_st'];
                                        }
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($calculo['descricao']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($calculo['nota_numero'] ?? 'N/A'); ?>
                                            <?php if (isset($calculo['nota_valor_total'])): ?>
                                            <br><span class="text-xs text-gray-400">R$ <?php echo number_format($calculo['nota_valor_total'], 2, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($calculo['emitente'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $calculo['total_produtos'] > 0 ? $calculo['total_produtos'] . ' produto(s)' : 'Manual'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($calculo['data_calculo'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($calculo['valor_produto'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-semibold">R$ <?php echo number_format($valor_icms_exibir, 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="calculo-fronteira.php?action=visualizar&id=<?php echo $calculo['id']; ?>&competencia=<?php echo $competencia; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3 btn-action" title="Visualizar cálculo">
                                                <i data-feather="eye" class="w-4 h-4 inline"></i>
                                            </a>
                                            <a href="calculo-fronteira.php?action=editar&id=<?php echo $calculo['id']; ?>&competencia=<?php echo $calculo['competencia']; ?>" class="text-blue-600 hover:text-blue-900 mr-3 btn-action" title="Editar cálculo">
                                                <i data-feather="edit" class="w-4 h-4 inline"></i>
                                            </a>
                                            <a href="calculo-fronteira.php?action=excluir&id=<?php echo $calculo['id']; ?>&competencia=<?php echo $competencia; ?>" class="text-red-600 hover:text-red-900 btn-action" onclick="return confirm('Tem certeza que deseja excluir este cálculo?')" title="Excluir cálculo">
                                                <i data-feather="trash-2" class="w-4 h-4 inline"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <i data-feather="calculator" class="w-12 h-12 text-gray-400 mx-auto"></i>
                            <p class="mt-4 text-sm text-gray-500">Nenhum cálculo realizado ainda.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tabela de Cálculos de Cesta Básica -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mt-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Cálculos de Cesta Básica</h3>
                        </div>
                        
                        <?php if (count($calculos_cesta) > 0): ?>
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota Fiscal</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Cálculo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Produtos</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ICMS Cesta</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($calculos_cesta as $calculo): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($calculo['descricao']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($calculo['nota_numero'] ?? 'N/A'); ?>
                                            <?php if (isset($calculo['nota_valor_total'])): ?>
                                            <br><span class="text-xs text-gray-400">R$ <?php echo number_format($calculo['nota_valor_total'], 2, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($calculo['data_calculo'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($calculo['valor_total_produtos'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-semibold">R$ <?php echo number_format($calculo['valor_total_icms'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="calculo-cesta.php?action=visualizar&id=<?php echo $calculo['id']; ?>&competencia=<?php echo $competencia; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3 btn-action" title="Visualizar cálculo">
                                                <i data-feather="eye" class="w-4 h-4 inline"></i>
                                            </a>
                                            <a href="calculo-cesta.php?action=excluir&id=<?php echo $calculo['id']; ?>&competencia=<?php echo $competencia; ?>" class="text-red-600 hover:text-red-900 btn-action" onclick="return confirm('Tem certeza que deseja excluir este cálculo?')" title="Excluir cálculo">
                                                <i data-feather="trash-2" class="w-4 h-4 inline"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <i data-feather="shopping-bag" class="w-12 h-12 text-gray-400 mx-auto"></i>
                            <p class="mt-4 text-sm text-gray-500">Nenhum cálculo de cesta básica realizado ainda.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Inicializar Feather Icons
        feather.replace();
    </script>
</body>
</html>