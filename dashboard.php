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

// Verificar se a tabela notas_fiscais existe
$tabela_existe = false;
$sql_verificar_tabela = "SHOW TABLES LIKE 'nfe'";
$resultado = mysqli_query($conexao, $sql_verificar_tabela);
if ($resultado && mysqli_num_rows($resultado) > 0) {
    $tabela_existe = true;
}

// Buscar anos disponíveis para filtro (se a tabela existir)
$anos = [];
if ($tabela_existe) {
    $sql_anos = "SELECT DISTINCT competencia_ano FROM nfe WHERE usuario_id = ? ORDER BY competencia_ano DESC";
    $stmt = mysqli_prepare($conexao, $sql_anos);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $usuario_id);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($resultado)) {
            $anos[] = $row['competencia_ano'];
        }
        mysqli_stmt_close($stmt);
    }
} else {
    // Adicionar ano atual como padrão se a tabela não existir
    $anos[] = date('Y');
}

// Determinar ano e mês para filtro
$ano_filtro = $_GET['ano'] ?? date('Y');
$mes_filtro = $_GET['mes'] ?? date('m');

// Buscar estatísticas para o dashboard filtrado por competência (se a tabela existir)
// Buscar estatísticas para o dashboard filtrado por competência
$total_notas = 0;
$valor_total = 0;
$notas_pendentes = 0;

// Verificar se a tabela notas_fiscais existe
$tabela_existe = false;
$sql_verificar_tabela = "SHOW TABLES LIKE 'nfe'";
$resultado_tabela = mysqli_query($conexao, $sql_verificar_tabela);
if ($resultado_tabela && mysqli_num_rows($resultado_tabela) > 0) {
    $tabela_existe = true;
    
    $sql_estatisticas = "SELECT 
        COUNT(*) as total_notas,
        COALESCE(SUM(valor_total), 0) as valor_total,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as notas_pendentes
        FROM nfe 
        WHERE usuario_id = ? 
        AND competencia_ano = ?
        AND competencia_mes = ?";
    $stmt = mysqli_prepare($conexao, $sql_estatisticas);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $ano_filtro, $mes_filtro);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($resultado)) {
            $total_notas = $row['total_notas'];
            $valor_total = $row['valor_total'];
            $notas_pendentes = $row['notas_pendentes'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Buscar últimas notas da competência selecionada (se a tabela existir)
$notas = [];
if ($tabela_existe) {
    $sql_notas = "SELECT numero, serie, data_emissao, emitente_nome, valor_total 
              FROM nfe
              WHERE usuario_id = ? 
              AND competencia_ano = ?
              AND competencia_mes = ?
              ORDER BY data_importacao DESC 
              LIMIT 5";
    $stmt = mysqli_prepare($conexao, $sql_notas);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $ano_filtro, $mes_filtro);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($resultado)) {
            $notas[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// Nome do mês para exibição
$nomes_meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/animejs/lib/anime.iife.min.js"></script>
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
                        <a href="dashboard.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
                            <i data-feather="activity" class="w-4 h-4 mr-3"></i> Dashboard Contábil
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
                        <a href="importar-notas-fiscais.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
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
                    <!-- Dashboard Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-feather="activity" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Dashboard - <?php echo $nomes_meses[(int)$mes_filtro] . ' de ' . $ano_filtro; ?>
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Visão geral das atividades e estatísticas</p>
                            </div>
                            <form method="GET" class="mt-4 md:mt-0 flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                                <select name="mes" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500 text-sm">
                                    <option value="">Selecione o mês</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == $mes_filtro ? 'selected' : ''; ?>>
                                            <?php echo $nomes_meses[$i]; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="ano" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500 text-sm">
                                    <option value="">Selecione o ano</option>
                                    <?php foreach ($anos as $ano): ?>
                                        <option value="<?php echo $ano; ?>" <?php echo $ano == $ano_filtro ? 'selected' : ''; ?>>
                                            <?php echo $ano; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm flex items-center justify-center">
                                    <i data-feather="filter" class="w-4 h-4 mr-2"></i> Filtrar
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php if (!$tabela_existe): ?>
                    <!-- Aviso se a tabela não existir -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-feather="alert-triangle" class="h-5 w-5 text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    A tabela de notas fiscais não está disponível no momento. 
                                    <?php if ($_SESSION['usuario_tipo'] == 'admin'): ?>
                                        <a href="configuracao.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">
                                            Configurar sistema
                                        </a>
                                    <?php else: ?>
                                        Entre em contato com o administrador do sistema.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Feature Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <!-- Card de Estatísticas -->
                        <div class="feature-card bg-white rounded-xl shadow-sm p-6 transition-all duration-300 hover:shadow-md">
                            <div class="flex items-center mb-4">
                                <div class="bg-indigo-100 p-3 rounded-lg mr-4">
                                    <i data-feather="file-text" class="w-6 h-6 text-indigo-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">Notas Fiscais</h3>
                                    <p class="text-gray-600 text-sm">Resumo do período</p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total de notas:</span>
                                    <span class="font-semibold"><?php echo $total_notas; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Valor total:</span>
                                    <span class="font-semibold">R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Pendentes:</span>
                                    <span class="font-semibold"><?php echo $notas_pendentes; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="feature-card bg-white rounded-xl shadow-sm p-6 transition-all duration-300 hover:shadow-md">
                            <div class="flex items-center mb-4">
                                <div class="bg-indigo-100 p-3 rounded-lg mr-4">
                                    <i data-feather="book" class="w-6 h-6 text-indigo-600"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Livro Caixa</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Sistema de preenchimento centrado no usuário cliente preencher e ficar salvo para nossa equipe visualizar.</p>
                            <a href="livro-caixa.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors">
                                Acessar Livro Caixa
                            </a>
                        </div>

                        <div class="feature-card bg-white rounded-xl shadow-sm p-6 transition-all duration-300 hover:shadow-md">
                            <div class="flex items-center mb-4">
                                <div class="bg-purple-100 p-3 rounded-lg mr-4">
                                    <i data-feather="repeat" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Conversão Contábil</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Converta planilhas preenchidas pelos clientes para dados utilizados no sistema, com suporte a diferentes sistemas contábeis.</p>
                            <a href="conversao-contabil.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 transition-colors">
                                Converter Dados
                            </a>
                        </div>

                        <div class="feature-card bg-white rounded-xl shadow-sm p-6 transition-all duration-300 hover:shadow-md">
                            <div class="flex items-center mb-4">
                                <div class="bg-purple-100 p-3 rounded-lg mr-4">
                                    <i data-feather="file-text" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Notas Fiscais</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Sistema de importação e gestão de notas fiscais com validação de CNPJ.</p>
                            <a href="importar-notas-fiscais.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 transition-colors">
                                Gerenciar Notas Fiscais
                            </a>
                        </div>
                    </div>

                    <!-- Últimas Notas Fiscais -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                            <i data-feather="file-text" class="w-5 h-5 mr-2 text-gray-600"></i>
                            Últimas Notas Fiscais
                        </h2>
                        
                        <?php if (count($notas) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Emissão</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emitente</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor (R$)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($notas as $nota): ?>
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($nota['numero']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($nota['serie']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo date('d/m/Y', strtotime($nota['data_emissao'])); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($nota['emitente_nome']); ?></td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo number_format($nota['valor_total'], 2, ',', '.'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i data-feather="file" class="w-12 h-12 text-gray-300 mx-auto"></i>
                                <p class="mt-4 text-gray-500">
                                    <?php echo $tabela_existe ? 'Nenhuma nota fiscal encontrada para o período selecionado.' : 'Tabela de notas fiscais não disponível.'; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center mb-4">
                            <i data-feather="clock" class="w-5 h-5 mr-2 text-gray-600"></i>
                            Atividade Recente
                        </h2>
                        <ul class="divide-y divide-gray-200">
                            <li class="activity-item py-4 px-3 rounded-lg transition-colors duration-200">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded-full mr-4">
                                        <i data-feather="upload" class="w-4 h-4 text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">Importação de notas fiscais realizada</p>
                                        <p class="text-xs text-gray-500">15/03/2023 - 14:32</p>
                                    </div>
                                </div>
                            </li>
                            <li class="activity-item py-4 px-3 rounded-lg transition-colors duration-200">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 p-2 rounded-full mr-4">
                                        <i data-feather="refresh-cw" class="w-4 h-4 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">Conversão de dados contábeis concluída</p>
                                        <p class="text-xs text-gray-500">14/03/2023 - 16:45</p>
                                    </div>
                                </div>
                            </li>
                            <li class="activity-item py-4 px-3 rounded-lg transition-colors duration-200">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded-full mr-4">
                                        <i data-feather="check-circle" class="w-4 h-4 text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">Livro caixa atualizado com sucesso</p>
                                        <p class="text-xs text-gray-500">14/03/2023 - 10:18</p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize AOS animations
        AOS.init();
        
        // Initialize feather icons
        feather.replace();
        
        // Auto-select current year and month if not selected
        document.addEventListener('DOMContentLoaded', function() {
            const mesSelect = document.querySelector('select[name="mes"]');
            const anoSelect = document.querySelector('select[name="ano"]');
            
            if (!anoSelect.value) {
                const currentYear = new Date().getFullYear();
                anoSelect.value = currentYear;
            }
            
            if (!mesSelect.value) {
                const currentMonth = new Date().getMonth() + 1;
                mesSelect.value = currentMonth;
            }
        });
    </script>
</body>
</html>