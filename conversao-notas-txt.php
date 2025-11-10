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

// Buscar anos disponíveis para filtro
$anos = [];
$sql_anos = "SELECT DISTINCT competencia_ano FROM nfe WHERE usuario_id = ? ORDER BY competencia_ano DESC";
$stmt = mysqli_prepare($conexao, $sql_anos);
mysqli_stmt_bind_param($stmt, "i", $usuario_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($resultado)) {
    $anos[] = $row['competencia_ano'];
}
mysqli_stmt_close($stmt);

// Determinar ano e mês para filtro
$ano_filtro = $_GET['ano'] ?? date('Y');
$mes_filtro = $_GET['mes'] ?? date('m');

// Processar geração do arquivo TXT
if (isset($_GET['gerar_txt'])) {
    // Buscar notas fiscais da competência selecionada
    $sql_notas = "SELECT * FROM nfe
                  WHERE usuario_id = ? 
                  AND competencia_ano = ?
                  AND competencia_mes = ?";
    
    $stmt = mysqli_prepare($conexao, $sql_notas);
    mysqli_stmt_bind_param($stmt, "iii", $usuario_id, $ano_filtro, $mes_filtro);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    
    $notas = [];
    while ($row = mysqli_fetch_assoc($resultado)) {
        $notas[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    if (count($notas) > 0) {
        // Criar conteúdo do arquivo TXT
        $txt_content = "";
        
        foreach ($notas as $nota) {
            // Formatar dados conforme especificado - campos vazios em vez de 0,00
            $documento = $nota['numero'] ?? '';
            $cnpj_fornecedor = $nota['emitente_cnpj'] ?? '';
            $vencimento = date('d/m/Y', strtotime($nota['data_emissao']));
            $pagamento = date('d/m/Y', strtotime($nota['data_emissao']));
            $vr_liquido = number_format($nota['valor_total'], 2, ',', '');
            
            // Campos que devem ficar vazios em vez de 0,00
            $valor_juros = '';
            $valor_multa = '';
            $valor_desconto = (!empty($nota['valor_desconto']) && $nota['valor_desconto'] > 0) ? 
                             number_format($nota['valor_desconto'], 2, ',', '') : '';
            $valor_pis = '';
            $valor_cofins = '';
            $valor_csll = '';
            $valor_irrf = '';
            $banco = '5';
            $nome_cliente = $nota['emitente_nome'] ?? '';
            $historico = "Pagamento de nota fiscal " . $nota['numero'];
            $nota_fiscal = $nota['numero'] ?? '';
            
            // Montar linha com separador ; e campos vazios
            $linha = sprintf("%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;%s;\n",
                $documento,
                $cnpj_fornecedor,
                $vencimento,
                $pagamento,
                $vr_liquido,
                $valor_juros,
                $valor_multa,
                $valor_desconto,
                $valor_pis,
                $valor_cofins,
                $valor_csll,
                $valor_irrf,
                $banco,
                $nome_cliente,
                $historico,
                $nota_fiscal
            );
            
            $txt_content .= $linha;
        }
        
        // Forçar download do arquivo
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="nfe_' . $mes_filtro . '_' . $ano_filtro . '.txt"');
        header('Content-Length: ' . strlen($txt_content));
        echo $txt_content;
        exit;
    } else {
        // Redirecionar com mensagem de erro se não houver notas
        header("Location: exportar-notas-txt.php?erro=nenhuma_nota");
        exit;
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
    <title>Exportar Notas Fiscais - Sistema Contábil Integrado</title>
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
                        <a href="exportar-notas-txt.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
                            <i data-feather="download" class="w-4 h-4 mr-3"></i> Exportar Notas TXT
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
                                    <i data-feather="download" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Exportar Notas Fiscais para TXT
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Gere arquivos TXT das notas fiscais por competência</p>
                            </div>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php if (isset($_GET['erro']) && $_GET['erro'] == 'nenhuma_nota'): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline">Nenhuma nota fiscal encontrada para a competência selecionada.</span>
                    </div>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Selecionar Competência</h3>
                        
                        <form method="GET" class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
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
                            <?php if ($ano_filtro && $mes_filtro): ?>
                            <button type="submit" name="gerar_txt" value="1" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm flex items-center justify-center">
                                <i data-feather="download" class="w-4 h-4 mr-2"></i> Gerar TXT
                            </button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Informações do Formato -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Formato do Arquivo TXT</h3>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i data-feather="info" class="w-5 h-5 text-blue-600 mr-2 mt-0.5"></i>
                                <div>
                                    <p class="text-sm text-blue-800 font-medium">Estrutura do arquivo gerado (separado por ;):</p>
                                    <p class="text-xs text-blue-600 mt-1">
                                        documento ; cnpj ; vencimento ; pagamento ; vr_liquido ; valor_juros ; valor_multa ;<br>
                                        valor_desconto ; valor_pis ; valor_cofins ; valor_csll ; valor_irrf ; banco ;<br>
                                        nome_cliente ; historico ; nota_fiscal ;
                                    </p>
                                    <p class="text-xs text-blue-600 mt-2">
                                        <strong>Nota:</strong> Os campos de impostos (PIS, COFINS, CSLL, IRRF) e juros/multa ficarão vazios em vez de 0,00.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-4">
                            <div class="flex items-start">
                                <i data-feather="alert-circle" class="w-5 h-5 text-yellow-600 mr-2 mt-0.5"></i>
                                <div>
                                    <p class="text-sm text-yellow-800 font-medium">Exemplo de linha gerada:</p>
                                    <p class="text-xs text-yellow-600 mt-1 font-mono">
                                        730;28068117000126;18/08/2025;18/08/2025;48294,16;;;;;;;5;CERRADO GRAOS LTDA;Pagamento de nota fiscal 730;730;
                                    </p>
                                </div>
                            </div>
                        </div>
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