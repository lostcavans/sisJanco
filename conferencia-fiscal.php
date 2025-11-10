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
$sql_anos = "SELECT DISTINCT YEAR(data_emissao) as ano FROM nfe WHERE usuario_id = ? 
             UNION SELECT DISTINCT YEAR(data_emissao) as ano FROM nfce WHERE usuario_id = ? 
             ORDER BY ano DESC";
$stmt = mysqli_prepare($conexao, $sql_anos);
mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $usuario_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($resultado)) {
    $anos[] = $row['ano'];
}
mysqli_stmt_close($stmt);

// Adicionar 2025 ao array de anos se ainda não estiver presente
if (!in_array(2025, $anos)) {
    $anos[] = 2025;
    // Ordenar anos em ordem decrescente
    rsort($anos);
}

// Determinar ano e mês para filtro
$competencia_ano = $_POST['competencia_ano'] ?? date('Y');
$competencia_mes = $_POST['competencia_mes'] ?? date('m');

$competencia_mes = (int) $competencia_mes; 

// Processar resultados da comparação se existirem na sessão
$resultados = $_SESSION['resultados_comparacao'] ?? [];
$valores_diferentes = $_SESSION['valores_diferentes'] ?? [];
$total_notas_processadas = $_SESSION['total_notas_processadas'] ?? 0;

// Calcular estatísticas para as novas categorias
$notas_conferidas = array_filter($resultados, function($r) { return $r['status'] === 'encontrada'; });
$notas_divergentes = $valores_diferentes;
$notas_ausentes_banco = array_filter($resultados, function($r) { return $r['status'] === 'nao_encontrada_banco'; });
$notas_ausentes_relatorio = array_filter($resultados, function($r) { return $r['status'] === 'nao_encontrada_relatorio'; });

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
    <title>Conferência Fiscal - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <!-- PDF.js para processamento de PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
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
        .progress-bar {
            transition: width 0.3s ease;
        }
        .nota-encontrada {
            background-color: #f0fdf4;
        }
        .nota-diferente {
            background-color: #fef3c7;
        }
        .nota-ausente-banco {
            background-color: #eff6ff;
        }
        .nota-ausente-relatorio {
            background-color: #fef2f2;
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
                        <a href="xml-produtos.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="box" class="w-4 h-4 mr-3"></i> XML Produtos
                        </a>
                    </li>
                    <li>
                        <a href="conferencia-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
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
                                    <i data-feather="check-square" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Conferência Fiscal
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Compare notas fiscais importadas com relatórios em PDF</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upload de Relatório -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Carregar Relatório para Conferência</h3>
                        
                        <form id="uploadForm" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mês de Competência</label>
                                    <select id="competencia_mes" name="competencia_mes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500" required>
                                        <option value="">Selecione o mês</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i == $competencia_mes ? 'selected' : ''; ?>>
                                                <?php echo $nomes_meses[$i]; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ano de Competência</label>
                                    <select id="competencia_ano" name="competencia_ano" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500" required>
                                        <option value="">Selecione o ano</option>
                                        <?php foreach ($anos as $ano): ?>
                                            <option value="<?php echo $ano; ?>" <?php echo $ano == $competencia_ano ? 'selected' : ''; ?>>
                                                <?php echo $ano; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Relatório em PDF</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg">
                                    <div class="space-y-1 text-center">
                                        <div class="flex text-sm text-gray-600">
                                            <label for="arquivo_pdf" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Carregar arquivo</span>
                                                <input id="arquivo_pdf" name="arquivo_pdf" type="file" accept=".pdf" class="sr-only" required>
                                            </label>
                                            <p class="pl-1">ou arraste e solte</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PDF até 10MB</p>
                                    </div>
                                </div>
                                <div id="nomeArquivo" class="text-sm text-gray-500 mt-2"></div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" id="btnProcessar" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <i data-feather="check-circle" class="w-4 h-4 mr-2"></i>
                                    Processar Conferência
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Progresso do Processamento -->
                    <div id="progressoContainer" class="bg-white rounded-xl shadow-sm p-6 mb-6 hidden">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Processando Relatório</h3>
                        
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div id="progressBar" class="bg-indigo-600 h-2.5 rounded-full progress-bar" style="width: 0%"></div>
                        </div>
                        
                        <div id="statusProcessamento" class="text-sm text-gray-600 mt-2">Aguardando processamento...</div>
                    </div>

                    <!-- Resultados da Conferência -->
                    <?php if (!empty($resultados)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Resultados da Conferência - <?php echo $nomes_meses[(int)$competencia_mes] . ' de ' . $competencia_ano; ?></h3>
                            <a href="exportar-conferencia.php?mes=<?php echo $competencia_mes; ?>&ano=<?php echo $competencia_ano; ?>" 
                               class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 mt-2 md:mt-0">
                                <i data-feather="download" class="w-4 h-4 mr-2"></i>
                                Exportar Planilha
                            </a>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded-full mr-3">
                                        <i data-feather="check-circle" class="w-5 h-5 text-green-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-green-800">
                                            <?php echo count($notas_conferidas); ?>
                                        </p>
                                        <p class="text-sm text-green-600">Notas conferidas</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-yellow-100 p-2 rounded-full mr-3">
                                        <i data-feather="alert-circle" class="w-5 h-5 text-yellow-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-yellow-800">
                                            <?php echo count($notas_divergentes); ?>
                                        </p>
                                        <p class="text-sm text-yellow-600">Valores divergentes</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 p-2 rounded-full mr-3">
                                        <i data-feather="database" class="w-5 h-5 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-blue-800">
                                            <?php echo count($notas_ausentes_banco); ?>
                                        </p>
                                        <p class="text-sm text-blue-600">Não no Banco</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-red-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-red-100 p-2 rounded-full mr-3">
                                        <i data-feather="file-text" class="w-5 h-5 text-red-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-red-800">
                                            <?php echo count($notas_ausentes_relatorio); ?>
                                        </p>
                                        <p class="text-sm text-red-600">Não no Relatório</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Abas de Resultados -->
                        <div class="border-b border-gray-200 mb-4">
                            <nav class="-mb-px flex space-x-8 overflow-x-auto">
                                <button id="tabTodas" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600 whitespace-nowrap">
                                    Todas as Notas
                                </button>
                                <button id="tabDivergentes" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                                    Valores Divergentes
                                </button>
                                <button id="tabAusentesBanco" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                                    Não no Banco
                                </button>
                                <button id="tabAusentesRelatorio" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                                    Não no Relatório
                                </button>
                            </nav>
                        </div>
                        
                        <!-- Tabela de Resultados -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número NF</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor no Relatório (R$)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor no Sistema (R$)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diferença (R$)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emitente</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Emissão</th>
                                    </tr>
                                </thead>
                                <tbody id="tabelaResultados" class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($resultados as $numero => $resultado): ?>
                                    <tr class="<?php 
                                        echo $resultado['status'] === 'encontrada' ? 'nota-encontrada' : 
                                            ($resultado['status'] === 'valor_diferente' ? 'nota-diferente' : 
                                            ($resultado['status'] === 'nao_encontrada_banco' ? 'nota-ausente-banco' : 'nota-ausente-relatorio')); 
                                    ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($numero); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($resultado['status'] === 'encontrada'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Conferida</span>
                                            <?php elseif ($resultado['status'] === 'valor_diferente'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Divergente</span>
                                            <?php elseif ($resultado['status'] === 'nao_encontrada_banco'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Não no Banco</span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Não no Relatório</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php if (isset($resultado['valor_relatorio'])): ?>
                                                R$ <?php echo number_format($resultado['valor_relatorio'], 2, ',', '.'); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php if (isset($resultado['dados']['valor'])): ?>
                                                R$ <?php echo number_format($resultado['dados']['valor'], 2, ',', '.'); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($resultado['status'] === 'valor_diferente'): ?>
                                                <span class="text-red-600">R$ <?php echo number_format(abs($resultado['valor_relatorio'] - $resultado['dados']['valor']), 2, ',', '.'); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php if (isset($resultado['dados']['emitente_nome'])): ?>
                                                <?php echo htmlspecialchars($resultado['dados']['emitente_nome']); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php if (isset($resultado['dados']['data_emissao'])): ?>
                                                <?php echo date('d/m/Y', strtotime($resultado['dados']['data_emissao'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize feather icons
        feather.replace();
        
        // Configurar PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
        
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadForm');
            const arquivoInput = document.getElementById('arquivo_pdf');
            const nomeArquivo = document.getElementById('nomeArquivo');
            const progressoContainer = document.getElementById('progressoContainer');
            const progressBar = document.getElementById('progressBar');
            const statusProcessamento = document.getElementById('statusProcessamento');
            const btnProcessar = document.getElementById('btnProcessar');
            
            // Mostrar nome do arquivo selecionado
            arquivoInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    nomeArquivo.textContent = this.files[0].name;
                } else {
                    nomeArquivo.textContent = '';
                }
            });
            
            // Processar formulário de upload
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const competenciaMes = document.getElementById('competencia_mes').value;
                const competenciaAno = document.getElementById('competencia_ano').value;
                const arquivo = arquivoInput.files[0];
                
                if (!competenciaMes || !competenciaAno || !arquivo) {
                    alert('Por favor, preencha todos os campos e selecione um arquivo.');
                    return;
                }
                
                // Mostrar progresso
                progressoContainer.classList.remove('hidden');
                btnProcessar.disabled = true;
                
                // Processar PDF
                processarPDF(arquivo, competenciaMes, competenciaAno);
            });
            
            // Configurar abas de resultados
            const tabTodas = document.getElementById('tabTodas');
            const tabDivergentes = document.getElementById('tabDivergentes');
            const tabAusentesBanco = document.getElementById('tabAusentesBanco');
            const tabAusentesRelatorio = document.getElementById('tabAusentesRelatorio');
            const tabelaResultados = document.getElementById('tabelaResultados');
            const linhasTabela = tabelaResultados ? tabelaResultados.getElementsByTagName('tr') : [];
            
            if (tabTodas) {
                tabTodas.addEventListener('click', function() {
                    filtrarTabela('todas');
                    ativarAba(this);
                });
                
                tabDivergentes.addEventListener('click', function() {
                    filtrarTabela('divergentes');
                    ativarAba(this);
                });
                
                tabAusentesBanco.addEventListener('click', function() {
                    filtrarTabela('ausentes_banco');
                    ativarAba(this);
                });
                
                tabAusentesRelatorio.addEventListener('click', function() {
                    filtrarTabela('ausentes_relatorio');
                    ativarAba(this);
                });
            }
            
            function filtrarTabela(tipo) {
                for (let i = 0; i < linhasTabela.length; i++) {
                    const linha = linhasTabela[i];
                    const statusCell = linha.cells[1];
                    
                    if (statusCell) {
                        const status = statusCell.textContent.trim();
                        
                        if (tipo === 'todas') {
                            linha.style.display = '';
                        } else if (tipo === 'divergentes' && status === 'Divergente') {
                            linha.style.display = '';
                        } else if (tipo === 'ausentes_banco' && status === 'Não no Banco') {
                            linha.style.display = '';
                        } else if (tipo === 'ausentes_relatorio' && status === 'Não no Relatório') {
                            linha.style.display = '';
                        } else {
                            linha.style.display = 'none';
                        }
                    }
                }
            }
            
            function ativarAba(aba) {
                const abas = document.querySelectorAll('.tab-button');
                abas.forEach(a => {
                    a.classList.remove('border-indigo-500', 'text-indigo-600');
                    a.classList.add('border-transparent', 'text-gray-500');
                });
                
                aba.classList.add('border-indigo-500', 'text-indigo-600');
                aba.classList.remove('border-transparent', 'text-gray-500');
            }
            
            // Função para processar PDF
            function processarPDF(arquivo, competenciaMes, competenciaAno) {
                statusProcessamento.textContent = 'Carregando PDF...';
                progressBar.style.width = '10%';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const pdfData = new Uint8Array(e.target.result);
                    
                    pdfjsLib.getDocument({data: pdfData}).promise.then(function(pdf) {
                        statusProcessamento.textContent = 'Analisando estrutura do PDF...';
                        progressBar.style.width = '20%';
                        
                        const dadosNotas = {};
                        const totalPaginas = pdf.numPages;
                        let paginasProcessadas = 0;
                        
                        const processarPagina = function(pageNum) {
                            return pdf.getPage(pageNum).then(function(page) {
                                return page.getTextContent().then(function(textContent) {
                                    // Processar texto do PDF para extrair números e valores das notas
                                    const itens = textContent.items;
                                    
                                    // Agrupar por linhas (coordenada Y)
                                    const linhas = {};
                                    itens.forEach(item => {
                                        const y = Math.round(item.transform[5] * 100) / 100;
                                        if (!linhas[y]) linhas[y] = [];
                                        linhas[y].push({
                                            text: item.str,
                                            x: item.transform[4]
                                        });
                                    });
                                    
                                    // Processar cada linha
                                    Object.keys(linhas).sort((a, b) => b - a).forEach(y => {
                                        const linha = linhas[y].sort((a, b) => a.x - b.x);
                                        const textoLinha = linha.map(item => item.text).join(' ');
                                        
                                        // Padrões comuns em relatórios fiscais
                                        // 1. Padrão com número da nota e valor
                                        const regexNotaValor = /(\d+)\s+.*?(\d{1,3}(?:\.\d{3})*,\d{2})/g;
                                        let match;
                                        
                                        while ((match = regexNotaValor.exec(textoLinha)) !== null) {
                                            const numeroNota = match[1];
                                            let valorStr = match[2].replace(/\./g, '').replace(',', '.');
                                            const valor = parseFloat(valorStr);
                                            
                                            if (!isNaN(valor) && numeroNota) {
                                                dadosNotas[numeroNota] = {
                                                    numero: numeroNota,
                                                    valor: valor
                                                };
                                            }
                                        }
                                        
                                        // 2. Padrão específico para alguns relatórios (data, número, valor)
                                        if (textoLinha.match(/\d{2}\/\d{2}\/\d{4}.*\d{2}\/\d{2}\/\d{4}.*\d+/)) {
                                            const partes = textoLinha.split(/\s+/).filter(p => p.trim() !== '');
                                            
                                            if (partes.length >= 7) {
                                                const numeroNota = partes[2]; // Ajustar índice conforme necessário
                                                let valorStr = partes[partes.length - 1];
                                                
                                                valorStr = valorStr.replace(/\./g, '').replace(',', '.');
                                                const valor = parseFloat(valorStr);
                                                
                                                if (!isNaN(valor) && numeroNota && numeroNota.match(/^\d+$/)) {
                                                    dadosNotas[numeroNota] = {
                                                        numero: numeroNota,
                                                        valor: valor
                                                    };
                                                }
                                            }
                                        }
                                    });
                                    
                                    paginasProcessadas++;
                                    progressBar.style.width = (20 + (paginasProcessadas / totalPaginas) * 70) + '%';
                                    return true;
                                });
                            });
                        };
                        
                        // Processar todas as páginas sequencialmente
                        let sequencia = Promise.resolve();
                        for (let i = 1; i <= totalPaginas; i++) {
                            sequencia = sequencia.then(() => processarPagina(i));
                        }
                        
                        sequencia.then(() => {
                            console.log("PROCESSAMENTO CONCLUÍDO. NOTAS ENCONTRADAS:", Object.keys(dadosNotas).length);
                            console.log("DADOS:", dadosNotas);
                            
                            if (Object.keys(dadosNotas).length === 0) {
                                statusProcessamento.textContent = 'Nenhuma nota encontrada no PDF. Verifique o formato.';
                                btnProcessar.disabled = false;
                                return;
                            }
                            
                            finalizarProcessamento(Object.values(dadosNotas), competenciaMes, competenciaAno);
                        });
                        
                    }).catch(function(error) {
                        console.error('Erro ao carregar PDF:', error);
                        statusProcessamento.textContent = 'Erro: ' + error.message;
                        btnProcessar.disabled = false;
                    });
                };
                
                reader.readAsArrayBuffer(arquivo);
            }
            
            function finalizarProcessamento(dadosArray, competenciaMes, competenciaAno) {
                statusProcessamento.textContent = 'Enviando dados para o servidor...';
                progressBar.style.width = '95%';
                
                const formData = new FormData();
                formData.append('dados_notas', JSON.stringify(dadosArray));
                formData.append('competencia_mes', competenciaMes);
                formData.append('competencia_ano', competenciaAno);
                formData.append('comparar_pdf', 'true');
                
                fetch('processar-comparacao.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP ${response.status}: ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        progressBar.style.width = '100%';
                        statusProcessamento.textContent = `Processamento concluído! ${data.total} nota(s) processada(s).`;
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        statusProcessamento.textContent = 'Erro: ' + data.message;
                        btnProcessar.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Erro detalhado:', error);
                    statusProcessamento.textContent = 'Erro ao processar: ' + error.message;
                    btnProcessar.disabled = false;
                    
                    // Mostrar mais detalhes do erro
                    if (error.message.includes('HTTP 500')) {
                        statusProcessamento.textContent += '. Verifique o console para mais detalhes.';
                    }
                });
            }
        });
    </script>
</body>
</html>