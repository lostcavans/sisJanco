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
$regime_tributario = $_SESSION['usuario_regime_tributario'] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Fiscal - Sistema Contábil Integrado</title>
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
                        <a href="dashboard.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="activity" class="w-4 h-4 mr-3"></i> Dashboard Contábil
                        </a>
                    </li>
                    <li>
                        <a href="dashboard-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
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
                        <a href="fronteira-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
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
                    <!-- Dashboard Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-feather="file-text" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Dashboard Fiscal
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Área dedicada às operações fiscais da empresa</p>
                            </div>
                        </div>
                    </div>

                    <!-- Feature Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <!-- Card XML Produtos -->
                        <a href="xml-produtos.php" class="feature-card bg-white rounded-xl shadow-sm p-6 transition-all duration-300 hover:shadow-md">
                            <div class="flex items-center mb-4">
                                <div class="bg-indigo-100 p-3 rounded-lg mr-4">
                                    <i data-feather="box" class="w-6 h-6 text-indigo-600"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">XML Produtos</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Filtre produtos em notas fiscais através de códigos e gere XMLs específicos.</p>
                            <div class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors">
                                Acessar
                                <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                            </div>
                        </a>

                        <!-- Card Conferência -->
                        <a href="conferencia-fiscal.php" class="feature-card bg-white rounded-xl shadow-sm p-6 transition-all duration-300 hover:shadow-md">
                            <div class="flex items-center mb-4">
                                <div class="bg-green-100 p-3 rounded-lg mr-4">
                                    <i data-feather="check-square" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Conferência</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Realize conferências fiscais e valide informações das notas fiscais.</p>
                            <div class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 transition-colors">
                                Acessar
                                <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                            </div>
                        </a>

                        <!-- Card Fronteira -->
                        <a href="fronteira-fiscal.php" class="feature-card bg-white rounded-xl shadow-sm p-6 transition-all duration-300 hover:shadow-md">
                            <div class="flex items-center mb-4">
                                <div class="bg-purple-100 p-3 rounded-lg mr-4">
                                    <i data-feather="map-pin" class="w-6 h-6 text-purple-600"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800">Fronteira</h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Controle de operações de fronteira e documentação específica.</p>
                            <div class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 transition-colors">
                                Acessar
                                <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                            </div>
                        </a>
                    </div>

                    <!-- Informações da Empresa -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Informações Fiscais da Empresa</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600"><span class="font-medium">Razão Social:</span> <?php echo htmlspecialchars($razao_social); ?></p>
                                <p class="text-sm text-gray-600"><span class="font-medium">CNPJ:</span> <?php echo htmlspecialchars($cnpj); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><span class="font-medium">Inscrição Estadual:</span> - </p>
                                <p class="text-sm text-gray-600"><span class="font-medium">Regime Tributário:</span> <?php echo htmlspecialchars($regime_tributario); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Estatísticas Rápidas -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Estatísticas Fiscais</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 p-2 rounded-full mr-3">
                                        <i data-feather="file-text" class="w-5 h-5 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-blue-800">142</p>
                                        <p class="text-sm text-blue-600">Notas este mês</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded-full mr-3">
                                        <i data-feather="check-circle" class="w-5 h-5 text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-green-800">128</p>
                                        <p class="text-sm text-green-600">Notas validadas</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-yellow-100 p-2 rounded-full mr-3">
                                        <i data-feather="alert-circle" class="w-5 h-5 text-yellow-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-yellow-800">14</p>
                                        <p class="text-sm text-yellow-600">Notas pendentes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize feather icons
        feather.replace();
    </script>
</body>
</html>