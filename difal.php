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

// Incluir config.php
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

// DEBUG: Verificar se a coluna existe
$check_column = $conexao->query("SHOW COLUMNS FROM nfe LIKE 'indicador_ie_dest'");
$column_exists = $check_column->num_rows > 0;

if (!$column_exists) {
    $error = "ATENÇÃO: A coluna 'indicador_ie_dest' não existe na tabela nfe. Execute: ALTER TABLE nfe ADD COLUMN indicador_ie_dest VARCHAR(2) AFTER destinatario_nome;";
} else {
    // Consulta ao banco para buscar notas com indicador_ie_dest = 2 ou 9 (DIFAL) E UF diferente
    $notas_difal = [];
    $error = '';

    $query = "
        SELECT n.*, e.razao_social as emitente 
        FROM nfe n 
        LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj 
        WHERE n.usuario_id = ? 
        AND n.competencia_ano = ? 
        AND n.competencia_mes = ? 
        AND n.indicador_ie_dest IN ('2', '9')
        AND n.uf_emitente != n.uf_destinatario
    ";

    error_log("Query DIFAL: " . $query);
    error_log("Parâmetros: usuario_id=$usuario_id, ano=$ano, mes=$mes");

    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("iis", $usuario_id, $ano, $mes);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $notas_difal = $result->fetch_all(MYSQLI_ASSOC);
            error_log("Notas DIFAL encontradas: " . count($notas_difal));
            
            // Debug detalhado das notas encontradas
            foreach ($notas_difal as $nota) {
                error_log("Nota DIFAL: " . $nota['numero'] . 
                        " - Indicador: " . $nota['indicador_ie_dest'] . 
                        " - Tipo: " . $nota['tipo_operacao'] .
                        " - Valor: " . $nota['valor_total'] .
                        " - UF Emitente: " . $nota['uf_emitente'] .
                        " - UF Destinatário: " . $nota['uf_destinatario']);
            }
        } else {
            $error = "Erro ao executar consulta: " . $stmt->error;
            error_log("Erro na execução: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $error = "Erro ao preparar consulta: " . $conexao->error;
        error_log("Erro no prepare: " . $conexao->error);
    }

    // Consulta alternativa para debug - ver todas as notas da competência
    $query_debug = "
        SELECT numero, indicador_ie_dest, tipo_operacao, competencia_ano, competencia_mes 
        FROM nfe 
        WHERE usuario_id = ? 
        AND competencia_ano = ? 
        AND competencia_mes = ?
        LIMIT 10
    ";
    
    if ($stmt_debug = $conexao->prepare($query_debug)) {
        $stmt_debug->bind_param("iis", $usuario_id, $ano, $mes);
        $stmt_debug->execute();
        $result_debug = $stmt_debug->get_result();
        $notas_debug = $result_debug->fetch_all(MYSQLI_ASSOC);
        $stmt_debug->close();
        
        error_log("Notas encontradas (debug): " . count($notas_debug));
        foreach ($notas_debug as $nota) {
            error_log("Nota: " . $nota['numero'] . " - Indicador: " . ($nota['indicador_ie_dest'] ?? 'NULL') . " - Tipo: " . $nota['tipo_operacao']);
        }
    }
}

// Consulta ao banco para buscar cálculos DIFAL existentes (ATUALIZADA)
$calculos_difal = [];

$query = "
    SELECT cdm.*, n.id as nota_id, n.numero as nota_numero,
           n.valor_total as nota_valor_total, e.razao_social as emitente,
           n.uf_emitente, n.uf_destinatario, n.data_emissao,
           (SELECT COUNT(*) FROM calculos_difal_itens WHERE calculo_id = cdm.id) as total_produtos,
           (SELECT SUM(valor_difal) FROM calculos_difal_itens WHERE calculo_id = cdm.id) as total_difal,
           (SELECT SUM(valor_fecoep) FROM calculos_difal_itens WHERE calculo_id = cdm.id) as total_fecoep,
           'manual' as tipo_calculo,
           cdm.descricao as grupo_descricao,
           cdm.data_calculo,
           (SELECT SUM(valor_produto) FROM calculos_difal_itens WHERE calculo_id = cdm.id) as valor_base_calculo
    FROM calculos_difal_manuais cdm
    LEFT JOIN nfe n ON cdm.nota_fiscal_id = n.id
    LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj
    WHERE cdm.usuario_id = ?
    AND cdm.competencia = ?
    ORDER BY cdm.data_calculo DESC
";

if ($stmt = $conexao->prepare($query)) {
    $stmt->bind_param("is", $usuario_id, $competencia);
    $stmt->execute();
    $result = $stmt->get_result();
    $calculos_difal = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $error = "Erro ao buscar cálculos DIFAL: " . $conexao->error;
}

// Mensagens de sucesso/erro
$msg = $_GET['msg'] ?? '';
$error = $error ?? $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cálculo DIFAL - Sistema Contábil Integrado</title>
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
        .btn-action {
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i data-feather="percent" class="text-blue-600 w-6 h-6"></i>
                    <h1 class="text-xl font-bold text-gray-800">Sistema Contábil Integrado</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-blue-600 rounded-full w-10 h-10 flex items-center justify-center text-white">
                            <i data-feather="building" class="w-5 h-5"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($razao_social); ?></span>
                            <span class="text-xs text-gray-500">CNPJ: <?php echo htmlspecialchars($cnpj); ?></span>
                        </div>
                    </div>
                    <a href="index.php" class="flex items-center text-sm text-gray-600 hover:text-blue-600 transition-colors">
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
                        <a href="dashboard.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 hover:bg-gray-50">
                            <i data-feather="activity" class="w-4 h-4 mr-3"></i> Dashboard Contábil
                        </a>
                    </li>
                    <li>
                        <a href="dashboard-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 hover:bg-gray-50">
                            <i data-feather="file-text" class="w-4 h-4 mr-3"></i> Dashboard Fiscal
                        </a>
                    </li>
                    <li>
                        <a href="xml-produtos.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 hover:bg-gray-50">
                            <i data-feather="box" class="w-4 h-4 mr-3"></i> XML Produtos
                        </a>
                    </li>
                    <li>
                        <a href="conferencia-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 hover:bg-gray-50">
                            <i data-feather="check-square" class="w-4 h-4 mr-3"></i> Conferência
                        </a>
                    </li>
                    <li>
                        <a href="fronteira-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-blue-600 hover:bg-gray-50">
                            <i data-feather="map-pin" class="w-4 h-4 mr-3"></i> Fronteira
                        </a>
                    </li>
                    <li>
                        <a href="difal.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
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
                        <span class="block sm:inline">Cálculo DIFAL excluído com sucesso.</span>
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
                                    <i data-feather="percent" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Cálculo DIFAL
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Controle de operações com DIFAL - Diferencial de Alíquota</p>
                            </div>
                        </div>
                    </div>

                    <!-- Filtro de Competência -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filtrar por Competência</h3>
                        <form method="GET" action="difal.php" class="flex items-end space-x-4">
                            <div class="flex-1">
                                <label for="competencia" class="block text-sm font-medium text-gray-700 mb-1">Competência</label>
                                <input type="month" id="competencia" name="competencia" value="<?php echo htmlspecialchars($competencia); ?>" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Filtrar
                            </button>
                        </form>
                    </div>

                    

                    <!-- Tabela de Notas para DIFAL -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Notas para Cálculo DIFAL - <?php echo date('m/Y', strtotime($competencia)); ?></h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Notas com indicador IE do destinatário = 2 (Isento) ou 9 (Não Contribuinte)
                        </p>
                        
                        <?php if (count($notas_difal) > 0): ?>
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emitente</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CNPJ Emitente</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Emissão</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Indicador IE</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($notas_difal as $nota): 
                                        $indicador_desc = '';
                                        $indicador_class = '';
                                        switch($nota['indicador_ie_dest']) {
                                            case '2':
                                                $indicador_desc = 'Isento';
                                                $indicador_class = 'bg-blue-100 text-blue-800';
                                                break;
                                            case '9':
                                                $indicador_desc = 'Não Contribuinte';
                                                $indicador_class = 'bg-orange-100 text-orange-800';
                                                break;
                                            default:
                                                $indicador_desc = $nota['indicador_ie_dest'];
                                                $indicador_class = 'bg-gray-100 text-gray-800';
                                        }
                                    ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($nota['numero']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($nota['emitente']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($nota['emitente_cnpj']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($nota['data_emissao'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $nota['tipo_operacao'] === 'entrada' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo $nota['tipo_operacao'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $indicador_class; ?>">
                                                <?php echo $indicador_desc; ?> (<?php echo $nota['indicador_ie_dest']; ?>)
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R$ <?php echo number_format($nota['valor_total'], 2, ',', '.'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="selecionar-produtos-difal.php?nota_id=<?php echo $nota['id']; ?>&competencia=<?php echo $competencia; ?>" class="text-blue-600 hover:text-blue-900 btn-action">
                                                Calcular DIFAL
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
                            <p class="mt-4 text-sm text-gray-500">Nenhuma nota para cálculo DIFAL encontrada para a competência selecionada.</p>
                            <p class="text-xs text-gray-400 mt-2">Notas com indicador IE do destinatário = 2 ou 9</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tabela de Cálculos DIFAL -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Cálculos DIFAL Realizados</h3>
                                <p class="text-sm text-gray-500 mt-1">Resumo dos cálculos realizados para a competência <?php echo date('m/Y', strtotime($competencia)); ?></p>
                            </div>
                            <a href="novo-calculo-difal.php?competencia=<?php echo $competencia; ?>" class="mt-2 md:mt-0 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center">
                                <i data-feather="plus" class="w-4 h-4 mr-2"></i> Novo Cálculo DIFAL
                            </a>
                        </div>
                        
                        <?php if (count($calculos_difal) > 0): ?>
                        
                        <!-- Cards de Resumo -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                        <i data-feather="calculator" class="w-5 h-5 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-blue-600 font-medium">Total de Cálculos</p>
                                        <p class="text-xl font-bold text-blue-800"><?php echo count($calculos_difal); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded-lg mr-3">
                                        <i data-feather="dollar-sign" class="w-5 h-5 text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-green-600 font-medium">Total DIFAL</p>
                                        <p class="text-xl font-bold text-green-800">
                                            R$ <?php 
                                            $total_geral_difal = array_sum(array_column($calculos_difal, 'total_difal'));
                                            echo number_format($total_geral_difal, 2, ',', '.');
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="bg-purple-100 p-2 rounded-lg mr-3">
                                        <i data-feather="trending-up" class="w-5 h-5 text-purple-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-purple-600 font-medium">Total FECOEP</p>
                                        <p class="text-xl font-bold text-purple-800">
                                            R$ <?php 
                                            $total_geral_fecoep = array_sum(array_column($calculos_difal, 'total_fecoep'));
                                            echo number_format($total_geral_fecoep, 2, ',', '.');
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="bg-orange-100 p-2 rounded-lg mr-3">
                                        <i data-feather="file-text" class="w-5 h-5 text-orange-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-orange-600 font-medium">Total Geral</p>
                                        <p class="text-xl font-bold text-orange-800">
                                            R$ <?php 
                                            $total_geral = $total_geral_difal + $total_geral_fecoep;
                                            echo number_format($total_geral, 2, ',', '.');
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota Fiscal</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emitente / Destino</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produtos</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valores</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($calculos_difal as $calculo): 
                                        $total_difal = $calculo['total_difal'] ?? $calculo['valor_difal'];
                                        $total_fecoep = $calculo['total_fecoep'] ?? 0;
                                        $total_impostos = $total_difal + $total_fecoep;
                                    ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <!-- Coluna Descrição -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($calculo['descricao']); ?></div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo $calculo['grupo_descricao'] ?? 'Cálculo Manual'; ?>
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <!-- Coluna Nota Fiscal -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($calculo['nota_numero'] ?? 'N/A'); ?></div>
                                            <?php if (isset($calculo['nota_valor_total'])): ?>
                                            <div class="text-xs text-gray-500">R$ <?php echo number_format($calculo['nota_valor_total'], 2, ',', '.'); ?></div>
                                            <?php endif; ?>
                                            <?php if (isset($calculo['data_emissao'])): ?>
                                            <div class="text-xs text-gray-400"><?php echo date('d/m/Y', strtotime($calculo['data_emissao'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Coluna Emitente / Destino -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($calculo['emitente'] ?? 'N/A'); ?></div>
                                            <?php if (isset($calculo['uf_emitente']) && isset($calculo['uf_destinatario'])): ?>
                                            <div class="flex items-center space-x-1 mt-1">
                                                <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded"><?php echo $calculo['uf_emitente']; ?></span>
                                                <i data-feather="arrow-right" class="w-3 h-3 text-gray-400"></i>
                                                <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded"><?php echo $calculo['uf_destinatario']; ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Coluna Produtos -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="bg-gray-100 p-2 rounded-lg mr-3">
                                                    <i data-feather="package" class="w-4 h-4 text-gray-600"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo $calculo['total_produtos'] > 0 ? $calculo['total_produtos'] . ' produto(s)' : 'Manual'; ?></div>
                                                    <div class="text-xs text-gray-500">Base: R$ <?php echo number_format($calculo['valor_base_calculo'], 2, ',', '.'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Coluna Data -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($calculo['data_calculo'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($calculo['data_calculo'])); ?></div>
                                        </td>
                                        
                                        <!-- Coluna Valores -->
                                        <td class="px-6 py-4">
                                            <div class="space-y-1">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500">DIFAL:</span>
                                                    <span class="text-sm font-semibold text-green-600">R$ <?php echo number_format($total_difal, 2, ',', '.'); ?></span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500">FECOEP:</span>
                                                    <span class="text-sm font-semibold text-blue-600">R$ <?php echo number_format($total_fecoep, 2, ',', '.'); ?></span>
                                                </div>
                                                <div class="border-t pt-1 flex justify-between items-center">
                                                    <span class="text-xs font-medium text-gray-700">Total:</span>
                                                    <span class="text-sm font-bold text-purple-600">R$ <?php echo number_format($total_impostos, 2, ',', '.'); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Coluna Ações -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col space-y-2">
                                                
                                                
                                                <!-- Ações Rápidas -->
                                                <div class="flex space-x-3 text-xs">
                                                    <?php if (isset($calculo['nota_id'])): ?>
                                                    <a href="selecionar-produtos-difal.php?nota_id=<?php echo $calculo['nota_id']; ?>&competencia=<?php echo $competencia; ?>" 
                                                    class="text-gray-500 hover:text-gray-700 flex items-center" 
                                                    title="Ver produtos da nota">
                                                        <i data-feather="package" class="w-3 h-3 mr-1"></i>
                                                        Produtos
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="exportar-calculo.php?calculo_id=<?php echo $calculo['id']; ?>" 
                                                    class="text-gray-500 hover:text-gray-700 flex items-center" 
                                                    title="Exportar para Excel">
                                                        <i data-feather="download" class="w-3 h-3 mr-1"></i>
                                                        Exportar
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Resumo Final -->
                        <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div class="text-center">
                                    <div class="text-gray-600">Total de Cálculos</div>
                                    <div class="text-xl font-bold text-gray-800"><?php echo count($calculos_difal); ?></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-gray-600">Valor Total DIFAL</div>
                                    <div class="text-xl font-bold text-green-600">R$ <?php echo number_format($total_geral_difal, 2, ',', '.'); ?></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-gray-600">Valor Total FECOEP</div>
                                    <div class="text-xl font-bold text-blue-600">R$ <?php echo number_format($total_geral_fecoep, 2, ',', '.'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <div class="text-center py-12">
                            <div class="bg-gray-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                                <i data-feather="calculator" class="w-10 h-10 text-gray-400"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-700 mb-2">Nenhum cálculo DIFAL realizado</h4>
                            <p class="text-sm text-gray-500 mb-6">Comece criando seu primeiro cálculo DIFAL para esta competência.</p>
                            <a href="novo-calculo-difal.php?competencia=<?php echo $competencia; ?>" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i data-feather="plus" class="w-4 h-4 mr-2"></i>
                                Criar Primeiro Cálculo
                            </a>
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
    
    // Função para confirmar exclusões
    function confirmarExclusao() {
        return confirm('Tem certeza que deseja excluir este cálculo? Esta ação não pode ser desfeita.');
    }
    
    // Adicionar confirmação para todos os links de exclusão
    document.querySelectorAll('a[onclick*="confirm"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirmarExclusao()) {
                e.preventDefault();
            }
        });
    });
    
    // Tooltips para ações
    document.querySelectorAll('.btn-action').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            // Aqui você pode adicionar tooltips customizados se quiser
        });
    });
</script>
</body>
</html>