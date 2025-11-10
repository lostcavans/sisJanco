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

// Processar o formulário de filtro de produtos
$mensagem = '';
$erro = '';
$caminho_xml_gerado = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['codigos_produtos'])) {
    $codigos_produtos = trim($_POST['codigos_produtos']);
    $modelo_nf = $_POST['modelo_nf'] ?? '55';
    
    if (empty($codigos_produtos)) {
        $erro = "Por favor, informe pelo menos um código de produto.";
    } else {
        // Processar códigos (separados por vírgula, espaço ou quebra de linha)
        $codigos_array = preg_split('/[\s,\n]+/', $codigos_produtos);
        $codigos_array = array_map('trim', $codigos_array);
        $codigos_array = array_filter($codigos_array);
        $codigos_array = array_unique($codigos_array);
        
        if (count($codigos_array) === 0) {
            $erro = "Nenhum código válido foi informado.";
        } else {
            // Determinar tabelas baseado no modelo
            if ($modelo_nf == '55') {
                $tabela_nota = 'nfe';
                $tabela_itens = 'nfe_itens';
                $campo_id = 'nfe_id';
            } else {
                $tabela_nota = 'nfce';
                $tabela_itens = 'nfce_itens';
                $campo_id = 'nfce_id';
            }
            
            // Buscar produtos no banco de dados
            $placeholders = implode(',', array_fill(0, count($codigos_array), '?'));
            $sql = "SELECT nf.*, nfi.* 
                    FROM $tabela_nota nf 
                    JOIN $tabela_itens nfi ON nf.id = nfi.$campo_id 
                    WHERE nf.usuario_id = ? 
                    AND nfi.codigo_produto IN ($placeholders)";
            
            $stmt = mysqli_prepare($conexao, $sql);
            if ($stmt) {
                // Bind parameters
                $types = str_repeat('s', count($codigos_array));
                mysqli_stmt_bind_param($stmt, "i" . $types, $usuario_id, ...$codigos_array);
                mysqli_stmt_execute($stmt);
                $resultado = mysqli_stmt_get_result($stmt);
                
                $produtos_encontrados = [];
                while ($row = mysqli_fetch_assoc($resultado)) {
                    $produtos_encontrados[] = $row;
                }
                mysqli_stmt_close($stmt);
                
                if (count($produtos_encontrados) > 0) {
                    // Gerar XML com os produtos encontrados
                    $xml_content = gerar_xml_produtos($produtos_encontrados, $modelo_nf);
                    
                    // Salvar o XML em um arquivo temporário
                    $nome_arquivo = "xml_produtos_" . date('Ymd_His') . ".xml";
                    $caminho_arquivo = "temp/" . $nome_arquivo;
                    
                    if (!file_exists('temp')) {
                        mkdir('temp', 0777, true);
                    }
                    
                    if (file_put_contents($caminho_arquivo, $xml_content)) {
                        $mensagem = "XML gerado com sucesso! " . count($produtos_encontrados) . " produto(s) encontrado(s).";
                        $caminho_xml_gerado = $caminho_arquivo;
                    } else {
                        $erro = "Erro ao salvar o arquivo XML.";
                    }
                } else {
                    $erro = "Nenhum produto encontrado com os códigos informados.";
                }
            } else {
                $erro = "Erro ao preparar a consulta: " . mysqli_error($conexao);
            }
        }
    }
}

// Função para gerar XML a partir dos produtos encontrados
function gerar_xml_produtos($produtos, $modelo) {
    // Criar estrutura básica do XML
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"></nfeProc>');
    
    // Adicionar informações da NFe
    $NFe = $xml->addChild('NFe');
    $infNFe = $NFe->addChild('infNFe');
    $infNFe->addAttribute('Id', 'NFe' . generateRandomKey());
    $infNFe->addAttribute('versao', '4.00');
    
    // Adicionar ide (identificação da NF-e)
    $ide = $infNFe->addChild('ide');
    $ide->addChild('cUF', '35');
    $ide->addChild('cNF', generateRandomNumber(8));
    $ide->addChild('natOp', 'Venda de produção do estabelecimento');
    $ide->addChild('mod', $modelo);
    $ide->addChild('serie', '1');
    $ide->addChild('nNF', generateRandomNumber(9));
    $ide->addChild('dhEmi', date('c'));
    $ide->addChild('dhSaiEnt', date('c'));
    $ide->addChild('tpNF', '1');
    $ide->addChild('idDest', '1');
    $ide->addChild('cMunFG', '3550308');
    $ide->addChild('tpImp', '1');
    $ide->addChild('tpEmis', '1');
    $ide->addChild('cDV', '1');
    $ide->addChild('tpAmb', '2');
    $ide->addChild('finNFe', '1');
    $ide->addChild('indFinal', '1');
    $ide->addChild('indPres', '1');
    $ide->addChild('procEmi', '0');
    $ide->addChild('verProc', '1.0');
    
    // Adicionar emitente (usar informações da primeira nota)
    $emitente = $infNFe->addChild('emit');
    $emitente->addChild('CNPJ', $produtos[0]['emitente_cnpj']);
    $emitente->addChild('xNome', $produtos[0]['emitente_nome']);
    $emitente->addChild('xFant', $produtos[0]['emitente_nome']);
    $enderEmit = $emitente->addChild('enderEmit');
    $enderEmit->addChild('xLgr', 'Rua Exemplo');
    $enderEmit->addChild('nro', '123');
    $enderEmit->addChild('xBairro', 'Centro');
    $enderEmit->addChild('cMun', '3550308');
    $enderEmit->addChild('xMun', 'São Paulo');
    $enderEmit->addChild('UF', 'SP');
    $enderEmit->addChild('CEP', '01001000');
    $enderEmit->addChild('cPais', '1058');
    $enderEmit->addChild('xPais', 'Brasil');
    $enderEmit->addChild('fone', '1133333333');
    $emitente->addChild('IE', '123456789');
    $emitente->addChild('CRT', '1');
    
    // Adicionar destinatário (usar informações da primeira nota)
    $dest = $infNFe->addChild('dest');
    $dest->addChild('CNPJ', $produtos[0]['destinatario_cnpj']);
    $dest->addChild('xNome', $produtos[0]['destinatario_nome']);
    $enderDest = $dest->addChild('enderDest');
    $enderDest->addChild('xLgr', 'Rua Destinatário');
    $enderDest->addChild('nro', '456');
    $enderDest->addChild('xBairro', 'Centro');
    $enderDest->addChild('cMun', '3550308');
    $enderDest->addChild('xMun', 'São Paulo');
    $enderDest->addChild('UF', 'SP');
    $enderDest->addChild('CEP', '01001001');
    $enderDest->addChild('cPais', '1058');
    $enderDest->addChild('xPais', 'Brasil');
    $enderDest->addChild('fone', '1144444444');
    $dest->addChild('indIEDest', '9');
    
    // Adicionar produtos
    $total_produtos = 0;
    foreach ($produtos as $produto) {
        $det = $infNFe->addChild('det');
        $det->addAttribute('nItem', count($infNFe->det) + 1);
        
        $prod = $det->addChild('prod');
        $prod->addChild('cProd', $produto['codigo_produto']);
        $prod->addChild('cEAN', $produto['codigo_gtin'] ?: 'SEM GTIN');
        $prod->addChild('xProd', $produto['descricao']);
        $prod->addChild('NCM', $produto['ncm']);
        $prod->addChild('CFOP', $produto['cfop']);
        $prod->addChild('uCom', $produto['unidade']);
        $prod->addChild('qCom', number_format($produto['quantidade'], 4, '.', ''));
        $prod->addChild('vUnCom', number_format($produto['valor_unitario'], 4, '.', ''));
        $prod->addChild('vProd', number_format($produto['valor_total'], 2, '.', ''));
        $prod->addChild('cEANTrib', $produto['codigo_gtin'] ?: 'SEM GTIN');
        $prod->addChild('uTrib', $produto['unidade']);
        $prod->addChild('qTrib', number_format($produto['quantidade'], 4, '.', ''));
        $prod->addChild('vUnTrib', number_format($produto['valor_unitario'], 4, '.', ''));
        $prod->addChild('indTot', '1');
        
        $imposto = $det->addChild('imposto');
        $icms = $imposto->addChild('ICMS');
        $icms00 = $icms->addChild('ICMS00');
        $icms00->addChild('orig', '0');
        $icms00->addChild('CST', '00');
        $icms00->addChild('modBC', '3');
        $icms00->addChild('vBC', number_format($produto['valor_total'], 2, '.', ''));
        $icms00->addChild('pICMS', '18.00');
        $icms00->addChild('vICMS', number_format($produto['valor_total'] * 0.18, 2, '.', ''));
        
        $pis = $imposto->addChild('PIS');
        $pisnt = $pis->addChild('PISNT');
        $pisnt->addChild('CST', '07');
        
        $cofins = $imposto->addChild('COFINS');
        $cofinsnt = $cofins->addChild('COFINSNT');
        $cofinsnt->addChild('CST', '07');
        
        $total_produtos += $produto['valor_total'];
    }
    
    // Adicionar totais
    $total = $infNFe->addChild('total');
    $icmsTot = $total->addChild('ICMSTot');
    $icmsTot->addChild('vBC', number_format($total_produtos, 2, '.', ''));
    $icmsTot->addChild('vICMS', number_format($total_produtos * 0.18, 2, '.', ''));
    $icmsTot->addChild('vICMSDeson', '0.00');
    $icmsTot->addChild('vFCP', '0.00');
    $icmsTot->addChild('vBCST', '0.00');
    $icmsTot->addChild('vST', '0.00');
    $icmsTot->addChild('vFCPST', '0.00');
    $icmsTot->addChild('vFCPSTRet', '0.00');
    $icmsTot->addChild('vProd', number_format($total_produtos, 2, '.', ''));
    $icmsTot->addChild('vFrete', '0.00');
    $icmsTot->addChild('vSeg', '0.00');
    $icmsTot->addChild('vDesc', '0.00');
    $icmsTot->addChild('vII', '0.00');
    $icmsTot->addChild('vIPI', '0.00');
    $icmsTot->addChild('vIPIDevol', '0.00');
    $icmsTot->addChild('vPIS', '0.00');
    $icmsTot->addChild('vCOFINS', '0.00');
    $icmsTot->addChild('vOutro', '0.00');
    $icmsTot->addChild('vNF', number_format($total_produtos, 2, '.', ''));
    $icmsTot->addChild('vTotTrib', '0.00');
    
    // Adicionar transportadora
    $transp = $infNFe->addChild('transp');
    $transp->addChild('modFrete', '9');
    
    // Adicionar informações adicionais
    $infAdic = $infNFe->addChild('infAdic');
    $infAdic->addChild('infCpl', 'XML gerado automaticamente pelo sistema fiscal. Produtos filtrados por código.');
    
    // Adicionar signature (vazio para simplificação)
    $signature = $NFe->addChild('Signature');
    
    // Formatar o XML
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    
    return $dom->saveXML();
}

// Funções auxiliares
function generateRandomKey() {
    return substr(str_shuffle(str_repeat('0123456789', 44)), 0, 44);
}

function generateRandomNumber($length) {
    return substr(str_shuffle(str_repeat('0123456789', $length)), 0, $length);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XML Produtos - Sistema Contábil Integrado</title>
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
                            <i data-feather="activity" class="w-4 h-4 mr-3"></i> Dashboard Contábil
                        </a>
                    </li>
                    <li>
                        <a href="dashboard-fiscal.php" class="flex items-center px-6 py-3 text-sm font-medium text-gray-600 hover:text-indigo-600 hover:bg-gray-50">
                            <i data-feather="file-text" class="w-4 h-4 mr-3"></i> Dashboard Fiscal
                        </a>
                    </li>
                    <li>
                        <a href="xml-produtos.php" class="flex items-center px-6 py-3 text-sm font-medium text-indigo-700 bg-indigo-50">
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
                    <!-- Page Header -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-feather="box" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    XML Produtos
                                </h2>
                                <p class="text-sm text-gray-500 mt-1">Filtre produtos em notas fiscais através de códigos e gere XMLs específicos</p>
                            </div>
                        </div>
                    </div>

                    <!-- Formulário de Filtro -->
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filtrar Produtos</h3>
                        
                        <?php if (!empty($mensagem)): ?>
                            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                                <i data-feather="check-circle" class="w-5 h-5 mr-2"></i>
                                <span><?php echo htmlspecialchars($mensagem); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($erro)): ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                                <i data-feather="alert-circle" class="w-5 h-5 mr-2"></i>
                                <span><?php echo htmlspecialchars($erro); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Códigos dos Produtos</label>
                                <textarea 
                                    name="codigos_produtos" 
                                    rows="6" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500" 
                                    placeholder="Digite os códigos dos produtos, separados por vírgula, espaço ou quebra de linha"
                                ><?php echo isset($_POST['codigos_produtos']) ? htmlspecialchars($_POST['codigos_produtos']) : ''; ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">Exemplo: 12345, 67890, 54321 ou um código por linha</p>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Modelo da Nota Fiscal</label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="modelo_nf" value="55" class="text-indigo-600 focus:ring-indigo-500" checked>
                                        <span class="ml-2">NFe (Modelo 55)</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="modelo_nf" value="65" class="text-indigo-600 focus:ring-indigo-500">
                                        <span class="ml-2">NFCe (Modelo 65)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 flex items-center">
                                    <i data-feather="search" class="w-4 h-4 mr-2"></i>
                                    Buscar e Gerar XML
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (!empty($caminho_xml_gerado)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">XML Gerado</h3>
                        <div class="flex space-x-4">
                            <a href="<?php echo htmlspecialchars($caminho_xml_gerado); ?>" download class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 flex items-center">
                                <i data-feather="download" class="w-4 h-4 mr-2"></i>
                                Baixar XML
                            </a>
                            <button onclick="visualizarXML()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center">
                                <i data-feather="eye" class="w-4 h-4 mr-2"></i>
                                Visualizar XML
                            </button>
                        </div>
                        
                        <div id="xml-preview" class="mt-4 hidden">
                            <pre class="bg-gray-100 p-4 rounded-lg overflow-auto max-h-96 text-xs"><?php 
                                echo htmlspecialchars(file_get_contents($caminho_xml_gerado)); 
                            ?></pre>
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
        
        // Função para visualizar XML
        function visualizarXML() {
            const preview = document.getElementById('xml-preview');
            preview.classList.toggle('hidden');
        }
    </script>
</body>
</html>