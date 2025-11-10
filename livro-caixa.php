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

// Processar formulário de adicionar lançamento
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['adicionar_lancamento'])) {
    $data = $_POST['data'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    
    if (!empty($data) && !empty($descricao) && !empty($tipo) && !empty($valor)) {
        $sql = "INSERT INTO livro_caixa (usuario_id, data, descricao, tipo, valor, categoria) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "isssds", $usuario_id, $data, $descricao, $tipo, $valor, $categoria);
        
        if (mysqli_stmt_execute($stmt)) {
            $sucesso = "Lançamento adicionado com sucesso!";
        } else {
            $erro = "Erro ao adicionar lançamento: " . mysqli_error($conexao);
        }
        mysqli_stmt_close($stmt);
    } else {
        $erro = "Preencha todos os campos obrigatórios!";
    }
}

// Processar exclusão de lançamento
if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    
    $sql = "DELETE FROM livro_caixa WHERE id = ? AND usuario_id = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $id, $usuario_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $sucesso = "Lançamento excluído com sucesso!";
    } else {
        $erro = "Erro ao excluir lançamento: " . mysqli_error($conexao);
    }
    mysqli_stmt_close($stmt);
}

// Buscar lançamentos do livro caixa
$lancamentos = [];
$sql = "SELECT * FROM livro_caixa WHERE usuario_id = ? ORDER BY data DESC";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, "i", $usuario_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($resultado)) {
    $lancamentos[] = $row;
}
mysqli_stmt_close($stmt);

// Calcular totais
$total_receitas = 0;
$total_despesas = 0;

foreach ($lancamentos as $lancamento) {
    if ($lancamento['tipo'] == 'receita') {
        $total_receitas += $lancamento['valor'];
    } else {
        $total_despesas += $lancamento['valor'];
    }
}

$saldo = $total_receitas - $total_despesas;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livro Caixa - Sistema Contábil Integrado</title>
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
        .valor-receita {
            color: #10b981;
        }
        .valor-despesa {
            color: #ef4444;
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
                        <a href="livro-caixa.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
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
                    <!-- Page Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-feather="book" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Livro Caixa
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Controle de receitas e despesas da empresa</p>
                            </div>
                            <button onclick="document.getElementById('modal-adicionar').classList.remove('hidden')" class="mt-4 md:mt-0 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm flex items-center justify-center">
                                <i data-feather="plus" class="w-4 h-4 mr-2"></i> Novo Lançamento
                            </button>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php if (isset($sucesso)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo $sucesso; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($erro)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?php echo $erro; ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Resumo Financeiro -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center">
                                <div class="bg-green-100 p-3 rounded-lg mr-4">
                                    <i data-feather="trending-up" class="w-6 h-6 text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Total Receitas</p>
                                    <p class="text-2xl font-bold text-green-600">R$ <?php echo number_format($total_receitas, 2, ',', '.'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center">
                                <div class="bg-red-100 p-3 rounded-lg mr-4">
                                    <i data-feather="trending-down" class="w-6 h-6 text-red-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Total Despesas</p>
                                    <p class="text-2xl font-bold text-red-600">R$ <?php echo number_format($total_despesas, 2, ',', '.'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <div class="flex items-center">
                                <div class="bg-blue-100 p-3 rounded-lg mr-4">
                                    <i data-feather="dollar-sign" class="w-6 h-6 text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Saldo</p>
                                    <p class="text-2xl font-bold <?php echo $saldo >= 0 ? 'text-blue-600' : 'text-red-600'; ?>">
                                        R$ <?php echo number_format($saldo, 2, ',', '.'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Lançamentos -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Lançamentos</h3>
                        
                        <?php if (count($lancamentos) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($lancamentos as $lancamento): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($lancamento['data'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($lancamento['descricao']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($lancamento['categoria']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $lancamento['tipo'] == 'receita' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo $lancamento['tipo'] == 'receita' ? 'Receita' : 'Despesa'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $lancamento['tipo'] == 'receita' ? 'text-green-600' : 'text-red-600'; ?>">
                                            R$ <?php echo number_format($lancamento['valor'], 2, ',', '.'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <a href="livro-caixa.php?excluir=<?php echo $lancamento['id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Tem certeza que deseja excluir este lançamento?')">
                                                <i data-feather="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <i data-feather="book-open" class="w-12 h-12 text-gray-400 mx-auto"></i>
                            <p class="mt-4 text-gray-500">Nenhum lançamento encontrado.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Adicionar Lançamento -->
    <div id="modal-adicionar" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-lg font-medium text-gray-900">Adicionar Lançamento</h3>
                    <button onclick="document.getElementById('modal-adicionar').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                        <i data-feather="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form action="livro-caixa.php" method="POST" class="mt-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="data">Data</label>
                        <input type="date" name="data" id="data" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="descricao">Descrição</label>
                        <input type="text" name="descricao" id="descricao" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="tipo">Tipo</label>
                        <select name="tipo" id="tipo" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Selecione o tipo</option>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="valor">Valor (R$)</label>
                        <input type="number" step="0.01" name="valor" id="valor" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="categoria">Categoria</label>
                        <input type="text" name="categoria" id="categoria" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button type="button" onclick="document.getElementById('modal-adicionar').classList.add('hidden')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Cancelar
                        </button>
                        <button type="submit" name="adicionar_lancamento" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Adicionar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize feather icons
        feather.replace();
    </script>
</body>
</html>