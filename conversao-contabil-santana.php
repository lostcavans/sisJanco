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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversão Contábil - Santana - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Incluindo a biblioteca SheetJS para manipulação de Excel -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        .progress-bar {
            height: 4px;
            width: 0%;
            background-color: #6366f1;
            transition: width 0.3s ease;
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
                                    Conversão Contábil - Formato Santana
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Importe planilhas no formato Santana para o sistema</p>
                            </div>
                            <a href="conversao-contabil.php" class="mt-4 md:mt-0 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm flex items-center justify-center">
                                <i data-feather="arrow-left" class="w-4 h-4 mr-2"></i> Voltar
                            </a>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <div id="alertContainer"></div>

                    <!-- Upload de Arquivo -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Upload de Arquivo Santana</h3>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <div class="flex items-start">
                                <i data-feather="info" class="w-5 h-5 text-blue-600 mr-2 mt-0.5"></i>
                                <div>
                                    <p class="text-sm text-blue-800 font-medium">Formato esperado (Santana):</p>
                                    <p class="text-xs text-blue-600 mt-1">
                                        Colunas: Código | Parc. | Cód. | Razão Social | Fantasia | Documento | NF. | Emissão | Vencimento | Saldo | Valor | Juros | Valor Pago | Data Pgto | Obs C.P. | Obs. Parc. | CPF/CNPJ
                                    </p>
                                    <p class="text-xs text-blue-600 mt-2">
                                        <strong>Nota:</strong> O sistema irá converter os dados para o formato contábil seguindo as regras específicas.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="drop-zone" id="dropZone">
                                <div class="space-y-2">
                                    <i data-feather="upload-cloud" class="w-12 h-12 text-gray-400 mx-auto"></i>
                                    <p class="text-gray-600">Arraste et solte o arquivo ContasPagar.xls aqui ou</p>
                                    <label for="arquivo" class="cursor-pointer bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors inline-block">
                                        Selecione o arquivo
                                    </label>
                                    <input type="file" name="arquivo" id="arquivo" class="hidden" accept=".xlsx,.xls" required>
                                    <p class="text-sm text-gray-500">Formatos suportados: XLSX, XLS</p>
                                </div>
                            </div>
                            <div class="progress-bar-container bg-gray-200 rounded-full overflow-hidden">
                                <div id="progressBar" class="progress-bar"></div>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" id="processarBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline flex items-center">
                                    <i data-feather="refresh-cw" class="w-4 h-4 mr-2"></i> Processar Arquivo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Resultados da Conversão -->
                    <div id="resultadosContainer" class="hidden bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Dados Contábeis Processados (<span id="totalContabil">0</span> registros)</h3>
                        
                        <!-- Tabela Contábil -->
                        <div class="table-container">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pagamento</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cód. Conta Débito</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conta Crédito</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Líquido</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cód. Histórico</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Complemento</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inicia Lote</th>
                                    </tr>
                                </thead>
                                <tbody id="dadosContabeisBody" class="bg-white divide-y divide-gray-200">
                                    <!-- Os dados contábeis serão inseridos aqui via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button id="salvarDadosBtn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline flex items-center">
                                <i data-feather="save" class="w-4 h-4 mr-2"></i> Salvar no Banco
                            </button>
                            <button id="exportarBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline flex items-center">
                                <i data-feather="download" class="w-4 h-4 mr-2"></i> Exportar
                            </button>
                            <button id="exportarTxtBtn" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline flex items-center">
                                <i data-feather="file-text" class="w-4 h-4 mr-2"></i> Exportar TXT
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Inicializar feather icons
        feather.replace();
        
        // Variáveis globais
        let dadosContabeis = [];
        let arquivoSelecionado = null;
        
        // Elementos DOM
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('arquivo');
        const processarBtn = document.getElementById('processarBtn');
        const progressBar = document.getElementById('progressBar');
        const alertContainer = document.getElementById('alertContainer');
        const resultadosContainer = document.getElementById('resultadosContainer');
        const salvarDadosBtn = document.getElementById('salvarDadosBtn');
        const exportarBtn = document.getElementById('exportarBtn');
        
        // Funções de manipulação de arquivos
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
            handleFileSelection(files[0]);
        }
        
        fileInput.addEventListener('change', (e) => {
            handleFileSelection(e.target.files[0]);
        });
        
        function handleFileSelection(file) {
            if (file) {
                const validExtensions = ['.xlsx', '.xls'];
                const fileExtension = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
                
                if (validExtensions.includes(fileExtension)) {
                    arquivoSelecionado = file;
                    dropZone.querySelector('p').textContent = `Arquivo: ${file.name}`;
                    mostrarAlerta('Arquivo selecionado com sucesso!', 'success');
                } else {
                    mostrarAlerta('Formato de arquivo não suportado. Use XLSX ou XLS.', 'error');
                }
            }
        }
        
        // Processar arquivo Excel
        processarBtn.addEventListener('click', () => {
            if (!arquivoSelecionado) {
                mostrarAlerta('Por favor, selecione um arquivo primeiro.', 'error');
                return;
            }
            
            processarBtn.disabled = true;
            processarBtn.innerHTML = '<i data-feather="loader" class="w-4 h-4 mr-2 animate-spin"></i> Processando...';
            feather.replace();
            
            // Simular progresso
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 5;
                progressBar.style.width = `${progress}%`;
                
                if (progress >= 100) {
                    clearInterval(progressInterval);
                    processarArquivo(arquivoSelecionado);
                }
            }, 100);
        });
        
        function processarArquivo(file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    
                    // Obter a primeira planilha
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];
                    
                    // Converter para JSON
                    const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
                    
                    // Processar os dados (a partir da linha 3, ignorando cabeçalhos)
                    dadosContabeis = [];
                    
                    for (let i = 2; i < jsonData.length; i++) {
                        const row = jsonData[i];
                        
                        // Verificar se a linha não está vazia
                        if (row && row.length > 0 && row.some(cell => cell !== null && cell !== '')) {
                            // Extrair dados conforme a estrutura Santana
                            const codigo = row[0] ? String(row[0]).trim() : '';
                            const parcela = row[2] ? String(row[2]).trim() : '';
                            const cod_fornecedor = row[3] ? String(row[3]).trim() : '';
                            const razao_social_fornecedor = row[4] ? String(row[4]).trim() : '';
                            const fantasia = row[5] ? String(row[5]).trim() : '';
                            const documento = row[6] ? String(row[6]).trim() : '';
                            const nota_fiscal = row[7] ? String(row[7]).trim() : '';
                            const emissao = row[8] ? String(row[8]).trim() : '';
                            const vencimento = row[9] ? String(row[9]).trim() : '';
                            const saldo = row[10] ? parseFloat(String(row[10]).replace(/\./g, '').replace(',', '.')) || 0 : 0;
                            const valor = row[11] ? parseFloat(String(row[11]).replace(/\./g, '').replace(',', '.')) || 0 : 0;
                            const juros = row[12] ? parseFloat(String(row[12]).replace(/\./g, '').replace(',', '.')) || 0 : 0;
                            const valor_pago = row[13] ? parseFloat(String(row[13]).replace(/\./g, '').replace(',', '.')) || 0 : 0;
                            const data_pagamento = row[14] ? String(row[14]).trim() : '';
                            const obs_cp = row[15] ? String(row[15]).trim() : '';
                            const obs_parc = row[16] ? String(row[16]).trim() : '';
                            const cpf_cnpj = row[17] ? String(row[17]).trim() : '';
                            
                            // Definir complemento_historico (NF. se tiver, senão documento)
                            const complemento_historico = nota_fiscal && nota_fiscal !== '' ? nota_fiscal : documento;
                            
                            // Processar conforme as regras específicas
                            processarRegistroContabil(
                                data_pagamento,
                                cpf_cnpj, // cod_conta_debito = CNPJ do fornecedor
                                obs_parc, // conta_credito = banco (Obs. Parc.)
                                valor_pago > 0 ? valor_pago : valor,
                                juros,
                                complemento_historico,
                                obs_parc // banco (Obs. Parc.)
                            );
                        }
                    }
                    
                    // Exibir resultados
                    exibirResultados(dadosContabeis);
                    mostrarAlerta(`Arquivo processado com sucesso! ${dadosContabeis.length} registros contábeis gerados.`, 'success');
                    
                } catch (error) {
                    console.error('Erro ao processar arquivo:', error);
                    mostrarAlerta('Erro ao processar arquivo: ' + error.message, 'error');
                } finally {
                    processarBtn.disabled = false;
                    processarBtn.innerHTML = '<i data-feather="refresh-cw" class="w-4 h-4 mr-2"></i> Processar Arquivo';
                    feather.replace();
                    progressBar.style.width = '0%';
                }
            };
            
            reader.onerror = function() {
                mostrarAlerta('Erro ao ler o arquivo.', 'error');
                processarBtn.disabled = false;
                processarBtn.innerHTML = '<i data-feather="refresh-cw" class="w-4 h-4 mr-2"></i> Processar Arquivo';
                feather.replace();
                progressBar.style.width = '0%';
            };
            
            reader.readAsArrayBuffer(file);
        }
        
        function processarRegistroContabil(pagamento, cod_conta_debito, conta_credito, vr_liquido, juros, complemento_historico, banco) {
            // Linha principal
            dadosContabeis.push({
                pagamento: pagamento,
                cod_conta_debito: cod_conta_debito, // CNPJ do fornecedor
                conta_credito: '', // Vazio na linha principal
                vr_liquido: vr_liquido,
                cod_historico: '15', // padrão
                complemento_historico: complemento_historico,
                inicia_lote: '0' // 0 na linha principal
            });
            
            // Se houver juros, adicionar duas linhas extras
            if (juros > 0) {
                // Linha de juros
                dadosContabeis.push({
                    pagamento: pagamento,
                    cod_conta_debito: '368', // código do juros
                    conta_credito: '', // Vazio
                    vr_liquido: juros,
                    cod_historico: '16', // para juros
                    complemento_historico: complemento_historico,
                    inicia_lote: '' // Vazio
                });
                
                // Linha do banco (origem do dinheiro)
                dadosContabeis.push({
                    pagamento: pagamento,
                    cod_conta_debito: '', // Vazio
                    conta_credito: banco, // código de onde está saindo o dinheiro (Obs. Parc.)
                    vr_liquido: vr_liquido + juros, // valor total
                    cod_historico: '15', // padrão
                    complemento_historico: complemento_historico,
                    inicia_lote: '' // Vazio
                });
            }
        }
        
        function exibirResultados(contabeis) {
            // Atualizar contador
            document.getElementById('totalContabil').textContent = contabeis.length;
            
            // Exibir dados contábeis
            const contabeisBody = document.getElementById('dadosContabeisBody');
            contabeisBody.innerHTML = '';
            
            contabeis.forEach(dado => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${dado.pagamento}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${dado.cod_conta_debito}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${dado.conta_credito}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                        R$ ${dado.vr_liquido.toFixed(2).replace('.', ',')}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${dado.cod_historico}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${dado.complemento_historico}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${dado.inicia_lote}</td>
                `;
                
                contabeisBody.appendChild(row);
            });
            
            resultadosContainer.classList.remove('hidden');
        }
        
        // Salvar dados no banco
        salvarDadosBtn.addEventListener('click', () => {
            if (dadosContabeis.length === 0) {
                mostrarAlerta('Não há dados para salvar.', 'error');
                return;
            }
            
            salvarDadosBtn.disabled = true;
            salvarDadosBtn.innerHTML = '<i data-feather="loader" class="w-4 h-4 mr-2 animate-spin"></i> Salvando...';
            feather.replace();
            
            // Enviar dados para o servidor via AJAX
            fetch('salvar_dados_contabeis.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    dados_contabeis: dadosContabeis,
                    usuario_id: <?php echo $usuario_id; ?>
                })
            })
            .then(response => {
                // Verificar se a resposta é JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error(`Resposta não é JSON: ${text.substring(0, 100)}...`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    mostrarAlerta(data.message, 'success');
                } else {
                    mostrarAlerta(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                mostrarAlerta('Erro ao salvar dados: ' + error.message, 'error');
            })
            .finally(() => {
                salvarDadosBtn.disabled = false;
                salvarDadosBtn.innerHTML = '<i data-feather="save" class="w-4 h-4 mr-2"></i> Salvar no Banco';
                feather.replace();
            });
        });
        
        // Exportar dados
        exportarBtn.addEventListener('click', () => {
            if (dadosContabeis.length === 0) {
                mostrarAlerta('Não há dados para exportar.', 'error');
                return;
            }
            
            // Criar uma nova planilha
            const wb = XLSX.utils.book_new();
            
            if (dadosContabeis.length > 0) {
                const wsContabil = XLSX.utils.json_to_sheet(dadosContabeis);
                XLSX.utils.book_append_sheet(wb, wsContabil, "Dados Contábeis");
            }
            
            // Exportar para Excel
            XLSX.writeFile(wb, "dados_contabeis_processados.xlsx");
            mostrarAlerta('Dados exportados com sucesso!', 'success');
        });
        
        // Função para mostrar alertas
        function mostrarAlerta(mensagem, tipo) {
            const alert = document.createElement('div');
            alert.className = `mb-4 p-4 rounded-lg ${tipo === 'error' ? 'bg-red-100 text-red-700 border border-red-400' : 'bg-green-100 text-green-700 border border-green-400'}`;
            alert.innerHTML = `
                <div class="flex items-center">
                    <i data-feather="${tipo === 'error' ? 'alert-circle' : 'check-circle'}" class="w-5 h-5 mr-2"></i>
                    <span>${mensagem}</span>
                </div>
            `;
            
            alertContainer.appendChild(alert);
            feather.replace();
            
            // Remover alerta após 5 segundos
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Exportar dados para TXT (CSV com ;) sem cabeçalho
        exportarTxtBtn.addEventListener('click', () => {
            if (dadosContabeis.length === 0) {
                mostrarAlerta('Não há dados para exportar.', 'error');
                return;
            }
            
            // Criar conteúdo sem cabeçalho
            let txtContent = "";
            
            // Adicionar cada linha de dados
            dadosContabeis.forEach(dado => {
                txtContent += `${dado.pagamento || ''};${dado.cod_conta_debito || ''};${dado.conta_credito || ''};${dado.vr_liquido || ''};${dado.cod_historico || ''};${dado.complemento_historico || ''};${dado.inicia_lote || ''};;;;\n`;
            });
            
            // Criar blob e fazer download
            const blob = new Blob([txtContent], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'dados_contabeis.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            mostrarAlerta('Arquivo TXT exportado com sucesso!', 'success');
        });
    </script>
</body>
</html>