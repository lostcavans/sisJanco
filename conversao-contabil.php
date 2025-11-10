<?php
session_start();

// Função para verificar autenticação (mesma do dashboard)
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

// Buscar informações da empresa para saber o sistema utilizado
$sistema_utilizado = '';
$sql_empresa = "SELECT sistema_utilizado FROM empresas WHERE user_id = ?";
$stmt = mysqli_prepare($conexao, $sql_empresa);
mysqli_stmt_bind_param($stmt, "i", $usuario_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($resultado)) {
    $sistema_utilizado = $row['sistema_utilizado'];
}
mysqli_stmt_close($stmt);

// Processar upload de arquivo
$mensagem = '';
$erro = '';
$dados_convertidos = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo'];
    
    if ($arquivo['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        
        if ($extensao === 'csv' || $extensao === 'xlsx' || $extensao === 'xls') {
            // Simulação de processamento de arquivo
            // Em um sistema real, aqui seria feita a leitura e conversão do arquivo
            // de acordo com o sistema utilizado pela empresa
            
            $mensagem = "Arquivo processado com sucesso!";
            
            // Dados de exemplo (simulando conversão)
            $dados_convertidos = [
                ['data' => '2023-03-01', 'descricao' => 'Venda de produtos', 'valor' => 1500.00, 'conta' => 'Receita de Vendas'],
                ['data' => '2023-03-05', 'descricao' => 'Compra de materiais', 'valor' => -350.00, 'conta' => 'Estoque'],
                ['data' => '2023-03-10', 'descricao' => 'Pagamento de funcionários', 'valor' => -1200.00, 'conta' => 'Folha de Pagamento'],
                ['data' => '2023-03-15', 'descricao' => 'Serviços prestados', 'valor' => 800.00, 'conta' => 'Receita de Serviços'],
                ['data' => '2023-03-20', 'descricao' => 'Aluguel', 'valor' => -500.00, 'conta' => 'Despesas Operacionais'],
            ];
            
        } else {
            $erro = "Formato de arquivo não suportado. Use CSV ou Excel.";
        }
    } else {
        $erro = "Erro no upload do arquivo: " . $arquivo['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversão Contábil - Sistema Contábil Integrado</title>
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
        .drop-zone {
            border: 2px dashed #cbd5e0;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #6366f1;
            background-color: #f0f4ff;
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
                        <a href="conversao-contabil.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
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
                    <!-- Page Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-feather="repeat" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Conversão Contábil
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Converta planilhas do sistema <?php echo htmlspecialchars($sistema_utilizado); ?> para o formato contábil</p>
                            </div>
                        </div>
                    </div>


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Cartão de Opção Santana -->
                        <a href="conversao-contabil-santana.php" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="p-3 bg-indigo-100 rounded-lg">
                                    <i data-feather="file-text" class="w-8 h-8 text-indigo-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="font-semibold text-gray-800">Formato Santana</h3>
                                    <p class="text-sm text-gray-500">Importar planilha no layout Santana</p>
                                </div>
                            </div>
                        </a>

                        <!-- Outras opções de formato podem vir aqui -->
                        <!-- <a href="conversao-contabil-outro.php" class="...">Outro Formato</a> -->
                    </div>

                    <!-- Alertas -->
                    <?php if (!empty($mensagem)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo $mensagem; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($erro)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo $erro; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize feather icons
        feather.replace();
        
        // Drag and drop functionality
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('arquivo');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropZone.classList.add('dragover');
        }
        
        function unhighlight() {
            dropZone.classList.remove('dragover');
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
        }
    </script>
</body>
</html>