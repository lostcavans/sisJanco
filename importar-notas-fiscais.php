<?php
session_start();

// Configuração de logs detalhados
ini_set('log_errors', 1);
ini_set('error_log', 'notas_fiscais_errors.log');
ini_set('display_errors', 0);

// Função para log detalhado
function logDebug($message, $context = []) {
    $logMessage = date('[Y-m-d H:i:s]') . " " . $message;
    if (!empty($context)) {
        $logMessage .= " | Contexto: " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage);
}

// Função para verificar autenticação
function verificarAutenticacao() {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        logDebug("Falha na autenticação: Sessão não está logada");
        return false;
    }
    
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_username'])) {
        logDebug("Falha na autenticação: Dados de usuário incompletos na sessão");
        return false;
    }
    
    if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > 1800)) {
        logDebug("Falha na autenticação: Sessão expirada");
        return false;
    }
    
    $_SESSION['ultimo_acesso'] = time();
    
    return true;
}

// Verifica autenticação
if (!verificarAutenticacao()) {
    logDebug("Usuário não autenticado, redirecionando para login");
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

include("config.php");

// Verificar se a conexão com o banco foi estabelecida
if (!$conexao || mysqli_connect_errno()) {
    logDebug("Erro de conexão com o banco de dados: " . mysqli_connect_error());
    die("Erro de conexão com o banco de dados. Por favor, tente novamente mais tarde.");
}

$logado = $_SESSION['usuario_username'];
$usuario_id = $_SESSION['usuario_id'];
$razao_social = $_SESSION['usuario_razao_social'] ?? '';
$cnpj = $_SESSION['usuario_cnpj'] ?? '';

logDebug("Usuário autenticado", ['usuario_id' => $usuario_id, 'razao_social' => $razao_social]);

// Função auxiliar para extrair valores considerando namespaces
function extractValue($element, $path) {
    if (!$element) {
        logDebug("Elemento vazio na extração de valor", ['path' => $path]);
        return '';
    }
    
    $namespaces = $element->getNamespaces(true);
    $result = $element;
    
    $parts = explode('/', $path);
    foreach ($parts as $part) {
        if (empty($part)) continue;
        
        $found = false;
        if (!empty($namespaces)) {
            foreach ($namespaces as $ns) {
                $children = $result->children($ns);
                if (isset($children->$part)) {
                    $result = $children->$part;
                    $found = true;
                    break;
                }
            }
        }
        
        if (!$found && isset($result->$part)) {
            $result = $result->$part;
            $found = true;
        }
        
        if (!$found) {
            logDebug("Parte do caminho não encontrada", ['part' => $part, 'path' => $path]);
            return '';
        }
    }
    
    return (string)$result;
}

// Função melhorada para extrair valores considerando namespaces
function extractValueNS($element, $path) {
    if (!$element) {
        logDebug("Elemento vazio na extração de valor NS", ['path' => $path]);
        return '';
    }
    
    // Se o path começar com @, é um atributo
    if (strpos($path, '@') === 0) {
        $attributeName = substr($path, 1);
        $attributes = $element->attributes();
        if (isset($attributes[$attributeName])) {
            return (string)$attributes[$attributeName];
        }
        return '';
    }
    
    $parts = explode('/', $path);
    $current = $element;
    
    foreach ($parts as $part) {
        if (empty($part)) continue;
        
        $found = false;
        
        // Primeiro tenta sem namespace
        if (isset($current->$part)) {
            $current = $current->$part;
            $found = true;
        } 
        // Se não encontrou, tenta com namespaces
        else {
            $namespaces = $current->getNamespaces(true);
            foreach ($namespaces as $ns) {
                $ns_children = $current->children($ns);
                if (isset($ns_children->$part)) {
                    $current = $ns_children->$part;
                    $found = true;
                    break;
                }
            }
        }
        
        if (!$found) {
            // Tentar como atributo
            $attributes = $current->attributes();
            if (isset($attributes[$part])) {
                return (string)$attributes[$part];
            }
            
            logDebug("Caminho não encontrado no XML", ['part' => $part, 'path' => $path]);
            return '';
        }
    }
    
    return (string)$current;
}

function extractImpostoValue($item, $impostoPath) {
    $parts = explode('/', $impostoPath);
    $current = $item;
    
    foreach ($parts as $part) {
        if (empty($part)) continue;
        
        $found = false;
        $namespaces = $current->getNamespaces(true);
        
        // Primeiro tenta sem namespace
        if (isset($current->$part)) {
            $current = $current->$part;
            $found = true;
        } 
        // Se não encontrou, tenta com namespaces
        else {
            foreach ($namespaces as $ns) {
                $ns_children = $current->children($ns);
                if (isset($ns_children->$part)) {
                    $current = $ns_children->$part;
                    $found = true;
                    break;
                }
            }
        }
        
        // Se não encontrou, tenta como atributo
        if (!$found) {
            $attributes = $current->attributes();
            if (isset($attributes[$part])) {
                return (float) (string)$attributes[$part];
            }
            return 0;
        }
    }
    
    return (float) (string)$current;
}

// Nova função para extrair valores de impostos específicos
function extractItemImpostoValues($item) {
    $impostos = [];
    
    // Extrair ICMS
    $impostos['valor_icms'] = extractImpostoValue($item, 'imposto/ICMS/ICMS00/vICMS');
    if ($impostos['valor_icms'] == 0) {
        $impostos['valor_icms'] = extractImpostoValue($item, 'imposto/ICMS/ICMS10/vICMS');
    }
    if ($impostos['valor_icms'] == 0) {
        $impostos['valor_icms'] = extractImpostoValue($item, 'imposto/ICMS/ICMS20/vICMS');
    }
    
    // Extrair IPI
    $impostos['valor_ipi'] = extractImpostoValue($item, 'imposto/IPI/IPITrib/vIPI');
    if ($impostos['valor_ipi'] == 0) {
        $impostos['valor_ipi'] = extractImpostoValue($item, 'imposto/IPI/IPINT/vIPI');
    }
    
    // Extrair PIS
    $impostos['valor_pis'] = extractImpostoValue($item, 'imposto/PIS/PISAliq/vPIS');
    
    // Extrair COFINS
    $impostos['valor_cofins'] = extractImpostoValue($item, 'imposto/COFINS/COFINSAliq/vCOFINS');
    
    // Extrair ICMS ST
    $impostos['valor_icms_st'] = extractImpostoValue($item, 'imposto/ICMS/ICMS10/vBCST');
    
    return $impostos;
}

// Configurações para importação em massa
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0);
ini_set('post_max_size', '1024M');
ini_set('upload_max_filesize', '1024M');

// Desativar output buffering
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);
ob_start();

// Processar upload de arquivos
$mensagem = '';
$erro = '';
$notas_importadas = 0;
$notas_com_erro = 0;

// Capturar mensagens de redirecionamento
if (isset($_GET['sucesso']) && $_GET['sucesso'] == 'nota_excluida') {
    $mensagem = "Nota fiscal excluída com sucesso!";
}

if (isset($_GET['erro'])) {
    if ($_GET['erro'] == 'nota_nao_encontrada') {
        $erro = "Nota fiscal não encontrada ou você não tem permissão para excluí-la.";
    } elseif ($_GET['erro'] == 'erro_exclusao') {
        $erro = "Erro ao excluir a nota fiscal.";
    }
}

// Função para extrair arquivos XML de um ZIP
function extrairXmlDoZip($caminhoZip) {
    $xmlFiles = [];
    
    if (!class_exists('ZipArchive')) {
        logDebug("Extensão ZipArchive não está habilitada no PHP");
        return $xmlFiles;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($caminhoZip) === TRUE) {
        // Criar diretório temporário para extrair os arquivos
        $tempDir = sys_get_temp_dir() . '/notas_fiscais_' . uniqid();
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        // Extrair todos os arquivos
        $zip->extractTo($tempDir);
        $zip->close();
        
        // Buscar por arquivos XML
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $xmlFiles[] = [
                    'tmp_name' => $file->getPathname(),
                    'name' => $file->getFilename()
                ];
            }
        }
        
        logDebug("Arquivos XML extraídos do ZIP", ['quantidade' => count($xmlFiles)]);
    } else {
        logDebug("Falha ao abrir arquivo ZIP", ['arquivo' => $caminhoZip]);
    }
    
    return $xmlFiles;
}

/**
 * Função para processar um único arquivo XML
 */
function processarArquivoIndividual($arquivo, $conexao, $usuario_id, $cnpj) {
    // Iniciar transação para este arquivo específico
    mysqli_begin_transaction($conexao);
    
    try {
        mysqli_autocommit($conexao, false);
        
        $xml_content = file_get_contents($arquivo['tmp_name']);
        
        if ($xml_content === false) {
            logDebug("Falha ao ler conteúdo do arquivo XML", ['arquivo' => $arquivo['name']]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
        // Verificar se o XML está vazio ou inválido
        if (empty(trim($xml_content))) {
            logDebug("Arquivo XML vazio", ['arquivo' => $arquivo['name']]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        $xml_errors = libxml_get_errors();
        
        if ($xml === false || !empty($xml_errors)) {
            logDebug("Erro ao carregar XML", [
                'arquivo' => $arquivo['name'],
                'erros' => array_map(function($error) {
                    return $error->message;
                }, $xml_errors)
            ]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            libxml_clear_errors();
            return false;
        }
        
        if (!$xml) {
            logDebug("XML inválido ou vazio", ['arquivo' => $arquivo['name']]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
        // BUSCAR infNFe DE FORMA SIMPLIFICADA
        $infNFe = null;
        
        // Método 1: Buscar direto por XPath
        $namespaces = $xml->getNamespaces(true);
        foreach ($namespaces as $prefix => $ns) {
            $xml->registerXPathNamespace($prefix, $ns);
        }
        
        // Tentar caminhos comuns
        $paths = [
            '//infNFe',
            '//nfeProc/NFe/infNFe', 
            '//NFe/infNFe',
            '//*[local-name()="infNFe"]'
        ];
        
        foreach ($paths as $path) {
            $result = $xml->xpath($path);
            if (!empty($result)) {
                $infNFe = $result[0];
                logDebug("infNFe encontrado via XPath", ['path' => $path, 'arquivo' => $arquivo['name']]);
                break;
            }
        }
        
        if (!$infNFe) {
            logDebug("Estrutura XML não reconhecida", ['arquivo' => $arquivo['name']]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
        // Extrair chave de acesso
        $attributes = $infNFe->attributes();
        if (!isset($attributes['Id'])) {
            logDebug("Chave de acesso não encontrada", ['arquivo' => $arquivo['name']]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
        $chave_acesso = (string) $attributes['Id'];
        $chave_acesso = str_replace('NFe', '', $chave_acesso);
        
        if (empty($chave_acesso)) {
            logDebug("Chave de acesso vazia ou inválida", ['arquivo' => $arquivo['name']]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
        // VERIFICAR DUPLICIDADE PRIMEIRO (antes de processar tudo)
        $modelo = extractValueNS($infNFe, 'ide/mod');
        $numero = extractValueNS($infNFe, 'ide/nNF');
        $serie = extractValueNS($infNFe, 'ide/serie');
        $emitente_cnpj = extractValueNS($infNFe, 'emit/CNPJ');
        $emitente_nome = extractValueNS($infNFe, 'emit/xNome'); // CORREÇÃO: Definir $emitente_nome aqui
        
        // Determinar tipo de operação de forma simplificada
        $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);
        $emitente_cnpj_limpo = preg_replace('/[^0-9]/', '', $emitente_cnpj);
        $tipo_operacao = ($cnpj_limpo === $emitente_cnpj_limpo) ? 'saida' : 'entrada';
        
        // Verificar duplicidade
        $tabela = ($modelo == '55') ? 'nfe' : 'nfce';
        $duplicada = false;
        
        // Verificar pela chave de acesso
        if (!empty($chave_acesso)) {
            $check_sql = "SELECT id FROM $tabela WHERE chave_acesso = ? AND usuario_id = ?";
            $stmt_check = mysqli_prepare($conexao, $check_sql);
            
            if ($stmt_check) {
                mysqli_stmt_bind_param($stmt_check, "si", $chave_acesso, $usuario_id);
                
                if (mysqli_stmt_execute($stmt_check)) {
                    mysqli_stmt_store_result($stmt_check);
                    if (mysqli_stmt_num_rows($stmt_check) > 0) {
                        logDebug("Nota já existe (chave acesso)", ['chave' => $chave_acesso]);
                        $duplicada = true;
                    }
                }
                mysqli_stmt_close($stmt_check);
            }
        }
        
        if ($duplicada) {
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
        // Definir status padrão
        $status = 'processada';
        
        if ($modelo == '55') {
            // Processar NFe
            $data_emissao = extractValueNS($infNFe, 'ide/dhEmi');
            $data_emissao = date('Y-m-d', strtotime($data_emissao));
            
            $data_entrada_saida = $data_emissao;
            $dhSaiEnt = extractValueNS($infNFe, 'ide/dhSaiEnt');
            if (!empty($dhSaiEnt)) {
                $data_entrada_saida = date('Y-m-d', strtotime($dhSaiEnt));
            }
            
            // Extrair valores totais
            $valor_total = (float) extractValueNS($infNFe, 'total/ICMSTot/vNF');
            $valor_produtos = (float) extractValueNS($infNFe, 'total/ICMSTot/vProd');
            $valor_desconto = (float) extractValueNS($infNFe, 'total/ICMSTot/vDesc');
            $valor_frete = (float) extractValueNS($infNFe, 'total/ICMSTot/vFrete');
            $valor_seguro = (float) extractValueNS($infNFe, 'total/ICMSTot/vSeg');
            $valor_outras_despesas = (float) extractValueNS($infNFe, 'total/ICMSTot/vOutro');
            $valor_ipi = (float) extractValueNS($infNFe, 'total/ICMSTot/vIPI');
            $valor_icms = (float) extractValueNS($infNFe, 'total/ICMSTot/vICMS');
            $valor_pis = (float) extractValueNS($infNFe, 'total/ICMSTot/vPIS');
            $valor_cofins = (float) extractValueNS($infNFe, 'total/ICMSTot/vCOFINS');
            $valor_icms_st = (float) extractValueNS($infNFe, 'total/ICMSTot/vST');
            
            // Destinatário
            $destinatario_cnpj = extractValueNS($infNFe, 'dest/CNPJ');
            if (empty($destinatario_cnpj)) {
                $destinatario_cnpj = extractValueNS($infNFe, 'dest/CPF');
            }
            $destinatario_nome = extractValueNS($infNFe, 'dest/xNome');

            // EXTRAIR INDICADOR IE DO DESTINATÁRIO
            $indicador_ie_dest = extractValueNS($infNFe, 'dest/indIEDest');
            if (empty($indicador_ie_dest)) {
                $indicador_ie_dest = '9'; // Valor padrão se não encontrado
            }

            // Extrair UF do emitente
            $emitente_uf = extractValueNS($infNFe, 'emit/enderEmit/UF');
            if (empty($emitente_uf)) {
                $emitente_uf = extractValueNS($infNFe, 'emit/UF');
            }

            // Extrair UF do destinatário
            $destinatario_uf = extractValueNS($infNFe, 'dest/enderDest/UF');
            if (empty($destinatario_uf)) {
                $destinatario_uf = extractValueNS($infNFe, 'dest/UF');
            }

            // DEBUG: Log das UFs extraídas
            logDebug("UFs extraídas", [
                'emitente_uf' => $emitente_uf,
                'destinatario_uf' => $destinatario_uf
            ]);
            
            $modalidade_frete = extractValueNS($infNFe, 'transp/modFrete');
            
            // Determinar competência
            $competencia_ano = date('Y', strtotime($data_emissao));
            $competencia_mes = date('m', strtotime($data_emissao));
            
            // DEBUG: Log dos valores extraídos
            logDebug("NFe encontrada", [
                'chave_acesso' => $chave_acesso,
                'numero' => $numero,
                'serie' => $serie,
                'valor_total' => $valor_total,
                'emitente' => $emitente_nome,
                'destinatario' => $destinatario_nome,
                'indicador_ie' => $indicador_ie_dest,
                'data_emissao' => $data_emissao
            ]);
            
            // Inserir NFe
            $sql_nf = "INSERT INTO nfe (
                usuario_id, chave_acesso, numero, serie, data_emissao, 
                data_entrada_saida, emitente_cnpj, emitente_nome, 
                destinatario_cnpj, destinatario_nome, indicador_ie_dest, valor_total, valor_produtos, 
                valor_desconto, valor_frete, valor_seguro, valor_outras_despesas, 
                valor_ipi, valor_icms, valor_pis, valor_cofins, valor_icms_st, 
                modalidade_frete, tipo_operacao, status, competencia_ano, competencia_mes,
                uf_emitente, uf_destinatario
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_nf = mysqli_prepare($conexao, $sql_nf);
            if ($stmt_nf) {
                mysqli_stmt_bind_param(
                    $stmt_nf, 
                    "isssssssssssddddddddddsssiiss",
                    $usuario_id, $chave_acesso, $numero, $serie, $data_emissao,
                    $data_entrada_saida, $emitente_cnpj, $emitente_nome,
                    $destinatario_cnpj, $destinatario_nome, $indicador_ie_dest, $valor_total, $valor_produtos,
                    $valor_desconto, $valor_frete, $valor_seguro, $valor_outras_despesas,
                    $valor_ipi, $valor_icms, $valor_pis, $valor_cofins, $valor_icms_st,
                    $modalidade_frete, $tipo_operacao, $status, $competencia_ano, $competencia_mes,
                    $emitente_uf, $destinatario_uf
                );
                
                if (mysqli_stmt_execute($stmt_nf)) {
                    $nota_id = mysqli_insert_id($conexao);
                    logDebug("NFe inserida com sucesso", ['id' => $nota_id, 'numero' => $numero, 'serie' => $serie, 'indicador_ie_dest' => $indicador_ie_dest]);
                    
                    // Processar itens da NFe
                    $itens = $infNFe->xpath('.//det');
                    if (empty($itens)) {
                        // Tentar métodos alternativos para encontrar itens
                        $itens = $xml->xpath('//det');
                        if (empty($itens)) {
                            // Última tentativa: buscar por qualquer elemento det
                            $itens = $xml->xpath('//*[local-name()="det"]');
                        }
                    }

                    $item_count = 1;
                    $itens_processados = 0;

                    if (!empty($itens)) {
                        logDebug("Itens encontrados na NFe", ['quantidade' => count($itens), 'numero' => $numero, 'serie' => $serie]);
                        
                        foreach ($itens as $item) {
                            // Extrair dados básicos do produto
                            $codigo_produto = extractValueNS($item, 'prod/cProd');
                            $descricao = extractValueNS($item, 'prod/xProd');
                            $ncm = extractValueNS($item, 'prod/NCM');
                            $cfop = extractValueNS($item, 'prod/CFOP');
                            $unidade = extractValueNS($item, 'prod/uCom');
                            $quantidade = (float) extractValueNS($item, 'prod/qCom');
                            $valor_unitario = (float) extractValueNS($item, 'prod/vUnCom');
                            $valor_total_item = (float) extractValueNS($item, 'prod/vProd');
                            $valor_desconto_item = (float) extractValueNS($item, 'prod/vDesc');
                            $codigo_gtin = extractValueNS($item, 'prod/cEAN');
                            
                            // Extrair valores adicionais (se existirem)
                            $valor_frete_item = (float) extractValueNS($item, 'prod/vFrete');
                            $valor_seguro_item = (float) extractValueNS($item, 'prod/vSeg');
                            $valor_outras_despesas_item = (float) extractValueNS($item, 'prod/vOutro');
                            
                            // Extrair valores de impostos usando a nova função
                            $impostos = extractItemImpostoValues($item);
                            
                            $valor_ipi_item = $impostos['valor_ipi'] ?? 0;
                            $valor_icms_item = $impostos['valor_icms'] ?? 0;
                            $valor_pis_item = $impostos['valor_pis'] ?? 0;
                            $valor_cofins_item = $impostos['valor_cofins'] ?? 0;
                            $valor_icms_st_item = $impostos['valor_icms_st'] ?? 0;
                            
                            // Definir valores padrão para campos vazios
                            if (empty($valor_desconto_item)) $valor_desconto_item = 0;
                            if (empty($valor_frete_item)) $valor_frete_item = 0;
                            if (empty($valor_seguro_item)) $valor_seguro_item = 0;
                            if (empty($valor_outras_despesas_item)) $valor_outras_despesas_item = 0;
                            if (empty($valor_ipi_item)) $valor_ipi_item = 0;
                            if (empty($valor_icms_item)) $valor_icms_item = 0;
                            if (empty($valor_pis_item)) $valor_pis_item = 0;
                            if (empty($valor_cofins_item)) $valor_cofins_item = 0;
                            if (empty($valor_icms_st_item)) $valor_icms_st_item = 0;
                            
                            // Pular itens sem informações básicas
                            if (empty($descricao) && empty($codigo_produto)) {
                                logDebug("Item sem informações básicas, pulando", ['item_numero' => $item_count]);
                                $item_count++;
                                continue;
                            }
                            
                            // Inserir item da NFe com todos os campos
                            $sql_item = "INSERT INTO nfe_itens (
                                nfe_id, numero_item, codigo_produto, descricao, ncm, cfop, unidade,
                                quantidade, valor_unitario, valor_total, valor_desconto, valor_frete,
                                valor_seguro, valor_outras_despesas, valor_ipi, valor_icms, valor_pis,
                                valor_cofins, valor_icms_st, codigo_gtin
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $stmt_item = mysqli_prepare($conexao, $sql_item);
                            if ($stmt_item) {
                                mysqli_stmt_bind_param(
                                    $stmt_item,
                                    "iisssssdddddddddddds",
                                    $nota_id, $item_count, $codigo_produto, $descricao, $ncm, $cfop, $unidade,
                                    $quantidade, $valor_unitario, $valor_total_item, $valor_desconto_item, $valor_frete_item,
                                    $valor_seguro_item, $valor_outras_despesas_item, $valor_ipi_item, $valor_icms_item, $valor_pis_item,
                                    $valor_cofins_item, $valor_icms_st_item, $codigo_gtin
                                );
                                
                                if (mysqli_stmt_execute($stmt_item)) {
                                    $itens_processados++;
                                    logDebug("Item da NFe inserido com sucesso", [
                                        'nfe_id' => $nota_id,
                                        'item_numero' => $item_count,
                                        'produto' => $descricao
                                    ]);
                                } else {
                                    logDebug("Erro ao inserir item da NFe", [
                                        'erro' => mysqli_stmt_error($stmt_item),
                                        'nfe_id' => $nota_id,
                                        'item_numero' => $item_count
                                    ]);
                                }
                                
                                mysqli_stmt_close($stmt_item);
                            } else {
                                logDebug("Erro ao preparar inserção de item", [
                                    'erro' => mysqli_error($conexao),
                                    'nfe_id' => $nota_id,
                                    'item_numero' => $item_count
                                ]);
                            }
                            
                            $item_count++;
                        }
                        
                        logDebug("Itens processados na NFe", [
                            'total_itens' => count($itens),
                            'itens_inseridos' => $itens_processados,
                            'numero' => $numero,
                            'serie' => $serie
                        ]);
                    } else {
                        logDebug("Nenhum item encontrado na NFe", ['numero' => $numero, 'serie' => $serie]);
                    }
                    
                    mysqli_stmt_close($stmt_nf);
                    mysqli_commit($conexao);
                    mysqli_autocommit($conexao, true);
                    return true;
                    
                } else {
                    logDebug("Erro ao inserir NFe", [
                        'erro' => mysqli_stmt_error($stmt_nf),
                        'sql' => $sql_nf,
                        'numero' => $numero,
                        'serie' => $serie
                    ]);
                    mysqli_stmt_close($stmt_nf);
                    mysqli_rollback($conexao);
                    mysqli_autocommit($conexao, true);
                    return false;
                }
            } else {
                logDebug("Erro ao preparar inserção de NFe", [
                    'erro' => mysqli_error($conexao),
                    'sql' => $sql_nf,
                    'numero' => $numero,
                    'serie' => $serie
                ]);
                mysqli_rollback($conexao);
                mysqli_autocommit($conexao, true);
                return false;
            }
        } else if ($modelo == '65') {
            // Processar NFCe (sempre considerada como saída)
            $tipo_operacao = 'saida';
            
            $data_emissao = extractValueNS($infNFe, 'ide/dhEmi');
            $data_emissao = date('Y-m-d', strtotime($data_emissao));
            
            $destinatario_cpf_cnpj = '';
            $destinatario_nome = '';
            
            $destinatario_cpf_cnpj = extractValueNS($infNFe, 'dest/CNPJ');
            if (empty($destinatario_cpf_cnpj)) {
                $destinatario_cpf_cnpj = extractValueNS($infNFe, 'dest/CPF');
            }
            $destinatario_nome = extractValueNS($infNFe, 'dest/xNome');
            
            // Extrair valores totais
            $valor_total = (float) extractValueNS($infNFe, 'total/ICMSTot/vNF');
            $valor_produtos = (float) extractValueNS($infNFe, 'total/ICMSTot/vProd');
            $valor_desconto = (float) extractValueNS($infNFe, 'total/ICMSTot/vDesc');
            
            // Informações de pagamento (NFCe)
            $valor_troco = 0;
            $valor_pago = $valor_total;
            $forma_pagamento = 'Dinheiro';
            
            $valor_pagamento = extractValueNS($infNFe, 'pag/detPag/vPag');
            if (!empty($valor_pagamento)) {
                $valor_pago = (float) $valor_pagamento;
                $forma_pagamento = extractValueNS($infNFe, 'pag/detPag/tPag') ?: 'Dinheiro';
            }
            
            $valor_troco = (float) extractValueNS($infNFe, 'pag/vTroco');
            
            // Determinar competência
            $competencia_ano = date('Y', strtotime($data_emissao));
            $competencia_mes = date('m', strtotime($data_emissao));
            
            logDebug("Processando NFCe", [
                'numero' => $numero,
                'serie' => $serie,
                'valor_total' => $valor_total,
                'emitente' => $emitente_nome
            ]);

            // Extrair UFs para NFCe
            $emitente_uf = extractValueNS($infNFe, 'emit/enderEmit/UF');
            $destinatario_uf = extractValueNS($infNFe, 'dest/enderDest/UF');
            
            // Inserir NFCe
            $sql_nf = "INSERT INTO nfce (
                usuario_id, chave_acesso, numero, serie, data_emissao, 
                emitente_cnpj, emitente_nome, destinatario_cpf_cnpj, destinatario_nome, 
                valor_total, valor_produtos, valor_desconto, valor_troco, valor_pago,
                forma_pagamento, tipo_operacao, status, competencia_ano, competencia_mes,
                uf_emitente, uf_destinatario
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_nf = mysqli_prepare($conexao, $sql_nf);
            
            if (!$stmt_nf) {
                logDebug("Erro ao preparar inserção de NFCe", [
                    'erro' => mysqli_error($conexao),
                    'sql' => $sql_nf,
                    'numero' => $numero,
                    'serie' => $serie
                ]);
                mysqli_rollback($conexao);
                mysqli_autocommit($conexao, true);
                return false;
            }
            
            mysqli_stmt_bind_param(
                $stmt_nf, 
                "issssssssdddddsssiiss",
                $usuario_id, $chave_acesso, $numero, $serie, $data_emissao,
                $emitente_cnpj, $emitente_nome, $destinatario_cpf_cnpj, $destinatario_nome,
                $valor_total, $valor_produtos, $valor_desconto, $valor_troco, $valor_pago,
                $forma_pagamento, $tipo_operacao, $status, $competencia_ano, $competencia_mes,
                $emitente_uf, $destinatario_uf
            );
            
            if (mysqli_stmt_execute($stmt_nf)) {
                $nota_id = mysqli_insert_id($conexao);
                logDebug("NFCe inserida com sucesso", ['id' => $nota_id, 'numero' => $numero, 'serie' => $serie]);
                
                // Processar itens da NFCe
                $itens = $infNFe->xpath('.//det');
                if (empty($itens)) {
                    $itens = $xml->xpath('//det');
                    if (empty($itens)) {
                        $itens = $xml->xpath('//*[local-name()="det"]');
                    }
                }

                $item_count = 1;
                $itens_processados = 0;

                if (!empty($itens)) {
                    logDebug("Itens encontrados na NFCe", ['quantidade' => count($itens), 'numero' => $numero, 'serie' => $serie]);
                    
                    foreach ($itens as $item) {
                        // Extrair dados básicos do produto (NFCe tem estrutura mais simples)
                        $codigo_produto = extractValueNS($item, 'prod/cProd');
                        $descricao = extractValueNS($item, 'prod/xProd');
                        $ncm = extractValueNS($item, 'prod/NCM');
                        $cfop = extractValueNS($item, 'prod/CFOP');
                        $unidade = extractValueNS($item, 'prod/uCom');
                        $quantidade = (float) extractValueNS($item, 'prod/qCom');
                        $valor_unitario = (float) extractValueNS($item, 'prod/vUnCom');
                        $valor_total_item = (float) extractValueNS($item, 'prod/vProd');
                        $valor_desconto_item = (float) extractValueNS($item, 'prod/vDesc');
                        $codigo_gtin = extractValueNS($item, 'prod/cEAN');
                        
                        if (empty($valor_desconto_item)) $valor_desconto_item = 0;
                        
                        // Pular itens sem informações básicas
                        if (empty($descricao) && empty($codigo_produto)) {
                            logDebug("Item sem informações básicas, pulando", ['item_numero' => $item_count]);
                            $item_count++;
                            continue;
                        }
                        
                        // Inserir item da NFCe
                        $sql_item = "INSERT INTO nfce_itens (
                            nfce_id, numero_item, codigo_produto, descricao, ncm, cfop, unidade,
                            quantidade, valor_unitario, valor_total, valor_desconto, codigo_gtin
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt_item = mysqli_prepare($conexao, $sql_item);
                        if ($stmt_item) {
                            mysqli_stmt_bind_param(
                                $stmt_item,
                                "iisssssdddds",
                                $nota_id, $item_count, $codigo_produto, $descricao, $ncm, $cfop, $unidade,
                                $quantidade, $valor_unitario, $valor_total_item, $valor_desconto_item, $codigo_gtin
                            );
                            
                            if (mysqli_stmt_execute($stmt_item)) {
                                $itens_processados++;
                                logDebug("Item da NFCe inserido com sucesso", [
                                    'nfce_id' => $nota_id,
                                    'item_numero' => $item_count,
                                    'produto' => $descricao
                                ]);
                            } else {
                                logDebug("Erro ao inserir item da NFCe", [
                                    'erro' => mysqli_stmt_error($stmt_item),
                                    'nfce_id' => $nota_id,
                                    'item_numero' => $item_count
                                ]);
                            }
                            
                            mysqli_stmt_close($stmt_item);
                        } else {
                            logDebug("Erro ao preparar inserção de item da NFCe", [
                                'erro' => mysqli_error($conexao),
                                'nfce_id' => $nota_id,
                                'item_numero' => $item_count
                            ]);
                        }
                        
                        $item_count++;
                    }
                    
                    logDebug("Itens processados na NFCe", [
                        'total_itens' => count($itens),
                        'itens_inseridos' => $itens_processados,
                        'numero' => $numero,
                        'serie' => $serie
                    ]);
                } else {
                    logDebug("Nenhum item encontrado na NFCe", ['numero' => $numero, 'serie' => $serie]);
                }

                mysqli_stmt_close($stmt_nf);
                mysqli_commit($conexao);
                mysqli_autocommit($conexao, true);
                return true;
                
            } else {
                logDebug("Erro ao inserir NFCe", [
                    'erro' => mysqli_stmt_error($stmt_nf),
                    'sql' => $sql_nf,
                    'numero' => $numero,
                    'serie' => $serie
                ]);
                mysqli_stmt_close($stmt_nf);
                mysqli_rollback($conexao);
                mysqli_autocommit($conexao, true);
                return false;
            }
        } else {
            logDebug("Modelo de nota não suportado", ['modelo' => $modelo, 'arquivo' => $arquivo['name']]);
            mysqli_rollback($conexao);
            mysqli_autocommit($conexao, true);
            return false;
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conexao);
        mysqli_autocommit($conexao, true);
        
        logDebug("Erro ao processar arquivo", [
            'arquivo' => $arquivo['name'],
            'erro' => $e->getMessage()
        ]);
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['arquivos_nf'])) {
    $arquivos = $_FILES['arquivos_nf'];
    
    logDebug("Iniciando processamento de upload", ['num_arquivos' => count($arquivos['name'])]);
    
    // Verificar se há arquivos
    if (count($arquivos['name']) > 0 && !empty($arquivos['name'][0])) {
        // HTML para a tela de processamento bonita
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Processando Importação - Notas Fiscais</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                .progress-ring__circle {
                    transition: stroke-dashoffset 0.35s;
                    transform: rotate(-90deg);
                    transform-origin: 50% 50%;
                }
                
                .fade-in {
                    animation: fadeIn 0.5s ease-in-out;
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
            </style>
        </head>
        <body class="bg-gradient-to-br from-blue-50 to-gray-100 min-h-screen">
            <div class="container mx-auto px-4 py-12 max-w-4xl">
                <div class="bg-white rounded-2xl shadow-xl p-8 fade-in">
                    <div class="text-center mb-10">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-100 mb-6">
                            <i class="fas fa-file-import text-3xl text-blue-600"></i>
                        </div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-3">Processando Notas Fiscais</h1>
                        <p class="text-gray-600">Aguarde enquanto importamos seus arquivos. Não feche esta página.</p>
                    </div>
                    
                    <div class="mb-10">
                        <div class="flex justify-center mb-8">
                            <div class="relative">
                                <svg class="w-48 h-48" viewBox="0 0 100 100">
                                    <!-- Fundo do círculo -->
                                    <circle class="text-gray-200" stroke-width="8" stroke="currentColor" fill="transparent" r="42" cx="50" cy="50" />
                                    <!-- Círculo de progresso -->
                                    <circle class="text-blue-600 progress-ring__circle" stroke-width="8" stroke-linecap="round" stroke="currentColor" fill="transparent" r="42" cx="50" cy="50"
                                            stroke-dasharray="264" stroke-dashoffset="264" id="progressCircle" />
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span id="progressPercent" class="text-3xl font-bold text-gray-800">0%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mb-6">
                            <h2 id="currentAction" class="text-xl font-semibold text-gray-800 mb-2">Preparando importação...</h2>
                            <p id="currentFile" class="text-gray-600">Carregando arquivos</p>
                        </div>
                        
                        <div class="bg-gray-100 rounded-lg p-6">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="text-2xl font-bold text-blue-600" id="totalFiles">0</div>
                                    <div class="text-sm text-gray-600">Arquivos</div>
                                </div>
                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="text-2xl font-bold text-green-600" id="importedNotes">0</div>
                                    <div class="text-sm text-gray-600">Importadas</div>
                                </div>
                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="text-2xl font-bold text-yellow-600" id="processedFiles">0</div>
                                    <div class="text-sm text-gray-600">Processados</div>
                                </div>
                                <div class="bg-white rounded-lg p-4 shadow-sm">
                                    <div class="text-2xl font-bold text-red-600" id="errorFiles">0</div>
                                    <div class="text-sm text-gray-600">Com Erro</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4" id="logsContainer">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Logs de processamento:</span>
                            <span id="currentTime"><?php echo date('H:i:s'); ?></span>
                        </div>
                        <div class="bg-gray-900 text-gray-100 rounded-lg p-4 h-64 overflow-y-auto font-mono text-sm" id="logMessages">
                            <div class="text-green-400">[<?php echo date('H:i:s'); ?>] Iniciando processo de importação...</div>
                        </div>
                    </div>
                    
                    <div class="mt-10 text-center">
                        <div class="inline-flex items-center space-x-2 text-gray-500">
                            <i class="fas fa-info-circle"></i>
                            <span class="text-sm">Esta tela fechará automaticamente quando o processo terminar</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                // Atualizar contadores
                document.getElementById('totalFiles').textContent = '<?php echo count($arquivos["name"]); ?>';
                
                // Função para atualizar progresso
                function updateProgress(progress, action, file, imported, processed, errors) {
                    // Atualizar círculo de progresso
                    const circle = document.getElementById('progressCircle');
                    const radius = 42;
                    const circumference = 2 * Math.PI * radius;
                    const offset = circumference - (progress / 100) * circumference;
                    circle.style.strokeDashoffset = offset;
                    
                    // Atualizar texto
                    document.getElementById('progressPercent').textContent = progress + '%';
                    document.getElementById('currentAction').textContent = action;
                    document.getElementById('currentFile').textContent = file;
                    document.getElementById('importedNotes').textContent = imported;
                    document.getElementById('processedFiles').textContent = processed;
                    document.getElementById('errorFiles').textContent = errors;
                    document.getElementById('currentTime').textContent = new Date().toLocaleTimeString();
                }
                
                // Função para adicionar log
                function addLog(message, type = 'info') {
                    const logContainer = document.getElementById('logMessages');
                    const time = new Date().toLocaleTimeString();
                    let color = 'text-gray-300';
                    
                    switch(type) {
                        case 'success': color = 'text-green-400'; break;
                        case 'error': color = 'text-red-400'; break;
                        case 'warning': color = 'text-yellow-400'; break;
                        case 'info': color = 'text-blue-400'; break;
                    }
                    
                    const logEntry = document.createElement('div');
                    logEntry.className = `${color} mb-1`;
                    logEntry.innerHTML = `[${time}] ${message}`;
                    logContainer.appendChild(logEntry);
                    logContainer.scrollTop = logContainer.scrollHeight;
                }
                
                // Iniciar com 0%
                updateProgress(0, 'Preparando importação...', 'Carregando arquivos', 0, 0, 0);
            </script>
        </body>
        </html>
        <?php
        
        ob_flush();
        flush();
        
        $total_arquivos = count($arquivos['name']);
        $notas_importadas = 0;
        $notas_com_erro = 0;
        $arquivos_processados = 0;
        
        // Array para armazenar todos os arquivos XML a serem processados
        $todos_xml_files = [];
        
        // Primeiro, coletar todos os arquivos XML (dos arquivos individuais e dos ZIPs)
        for ($i = 0; $i < $total_arquivos; $i++) {
            if ($arquivos['error'][$i] === UPLOAD_ERR_OK) {
                $arquivo = [
                    'name' => $arquivos['name'][$i],
                    'type' => $arquivos['type'][$i],
                    'tmp_name' => $arquivos['tmp_name'][$i],
                    'error' => $arquivos['error'][$i],
                    'size' => $arquivos['size'][$i]
                ];
                
                $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
                
                if ($extensao === 'xml') {
                    $todos_xml_files[] = $arquivo;
                } elseif ($extensao === 'zip') {
                    // Extrair XMLs do ZIP
                    $xmls_do_zip = extrairXmlDoZip($arquivo['tmp_name']);
                    $todos_xml_files = array_merge($todos_xml_files, $xmls_do_zip);
                    
                    echo "<script>addLog('Extraídos " . count($xmls_do_zip) . " arquivos XML do ZIP: " . htmlspecialchars($arquivo['name']) . "', 'info');</script>";
                    ob_flush();
                    flush();
                } else {
                    echo "<script>addLog('Tipo de arquivo não suportado: " . htmlspecialchars($arquivo['name']) . "', 'warning');</script>";
                    ob_flush();
                    flush();
                }
            }
        }
        
        $total_xml_files = count($todos_xml_files);
        
        echo "<script>addLog('Total de arquivos XML para processar: " . $total_xml_files . "', 'info');</script>";
        ob_flush();
        flush();
        
        // Processar cada arquivo XML
        foreach ($todos_xml_files as $index => $arquivo_xml) {
            $arquivos_processados++;
            $progresso = round(($arquivos_processados / $total_xml_files) * 100);
            
            echo "<script>
                updateProgress($progresso, 'Processando nota fiscal...', '" . htmlspecialchars($arquivo_xml['name']) . "', $notas_importadas, $arquivos_processados, $notas_com_erro);
                addLog('Processando: " . htmlspecialchars($arquivo_xml['name']) . "', 'info');
            </script>";
            ob_flush();
            flush();
            
            // Processar o arquivo XML
            $processado = processarArquivoIndividual($arquivo_xml, $conexao, $usuario_id, $cnpj);
            
            if ($processado) {
                $notas_importadas++;
                echo "<script>
                    addLog('✓ Nota importada com sucesso: " . htmlspecialchars($arquivo_xml['name']) . "', 'success');
                </script>";
            } else {
                $notas_com_erro++;
                echo "<script>
                    addLog('✗ Falha ao importar: " . htmlspecialchars($arquivo_xml['name']) . "', 'error');
                </script>";
            }
            
            ob_flush();
            flush();
        }
        
        // Atualizar progresso final
        echo "<script>
            updateProgress(100, 'Importação concluída!', 'Processamento finalizado', $notas_importadas, $arquivos_processados, $notas_com_erro);
            addLog('Processamento finalizado! $notas_importadas nota(s) importada(s), $notas_com_erro erro(s).', 'success');
        </script>";
        ob_flush();
        flush();
        
        // Aguardar 3 segundos e redirecionar
        echo "<script>
            setTimeout(function() {
                window.location.href = 'importar-notas-fiscais.php?sucesso=importacao&importadas=$notas_importadas&erros=$notas_com_erro';
            }, 3000);
        </script>";
        ob_flush();
        flush();
        
        exit;
    } else {
        $erro = "Nenhum arquivo selecionado.";
        logDebug("Nenhum arquivo selecionado para upload");
    }
}

// Verificar se há parâmetros de sucesso da importação
if (isset($_GET['sucesso']) && $_GET['sucesso'] == 'importacao') {
    $importadas = $_GET['importadas'] ?? 0;
    $erros = $_GET['erros'] ?? 0;
    
    if ($importadas > 0) {
        $mensagem = "Importação concluída! $importadas nota(s) importada(s) com sucesso.";
        if ($erros > 0) {
            $mensagem .= " $erros nota(s) não puderam ser importadas.";
        }
    } else {
        $erro = "Nenhuma nota foi importada. Verifique se os arquivos são XML válidos e se não foram importados anteriormente.";
    }
}

// Consultar notas importadas
$sql_nfe = "SELECT id, chave_acesso, numero, serie, data_emissao, valor_total, tipo_operacao, status 
            FROM nfe 
            WHERE usuario_id = ? 
            ORDER BY data_emissao DESC, numero DESC 
            LIMIT 100";
$stmt_nfe = mysqli_prepare($conexao, $sql_nfe);
mysqli_stmt_bind_param($stmt_nfe, "i", $usuario_id);
mysqli_stmt_execute($stmt_nfe);
$result_nfe = mysqli_stmt_get_result($stmt_nfe);

$sql_nfce = "SELECT id, chave_acesso, numero, serie, data_emissao, valor_total, tipo_operacao, status 
             FROM nfce 
             WHERE usuario_id = ? 
             ORDER BY data_emissao DESC, numero DESC 
             LIMIT 100";
$stmt_nfce = mysqli_prepare($conexao, $sql_nfce);
mysqli_stmt_bind_param($stmt_nfce, "i", $usuario_id);
mysqli_stmt_execute($stmt_nfce);
$result_nfce = mysqli_stmt_get_result($stmt_nfce);

// Contar total de notas
$sql_total_nfe = "SELECT COUNT(*) as total FROM nfe WHERE usuario_id = ?";
$stmt_total_nfe = mysqli_prepare($conexao, $sql_total_nfe);
mysqli_stmt_bind_param($stmt_total_nfe, "i", $usuario_id);
mysqli_stmt_execute($stmt_total_nfe);
$result_total_nfe = mysqli_stmt_get_result($stmt_total_nfe);
$total_nfe = mysqli_fetch_assoc($result_total_nfe)['total'];

$sql_total_nfce = "SELECT COUNT(*) as total FROM nfce WHERE usuario_id = ?";
$stmt_total_nfce = mysqli_prepare($conexao, $sql_total_nfce);
mysqli_stmt_bind_param($stmt_total_nfce, "i", $usuario_id);
mysqli_stmt_execute($stmt_total_nfce);
$result_total_nfce = mysqli_stmt_get_result($stmt_total_nfce);
$total_nfce = mysqli_fetch_assoc($result_total_nfce)['total'];

$total_notas = $total_nfe + $total_nfce;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Notas Fiscais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        
        .file-list-item {
            transition: all 0.2s ease;
        }
        
        .file-list-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .tab-active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Cabeçalho -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Importar Notas Fiscais</h1>
                <p class="text-gray-600 mt-1">Gerencie e importe suas notas fiscais eletrônicas</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-gray-600 bg-gray-100 px-3 py-1 rounded-full text-sm">Olá, <?php echo htmlspecialchars($razao_social); ?></span>
                <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
                </a>
                <a href="index.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200 flex items-center">
                    <i class="fas fa-sign-out-alt mr-2"></i>Sair
                </a>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if (!empty($mensagem)): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-green-800"><?php echo $mensagem; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($erro)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-red-800"><?php echo $erro; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Formulário de Upload -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-2">Importar Novas Notas Fiscais</h2>
            <p class="text-gray-600 mb-6">Selecione os arquivos XML das notas fiscais ou envie um arquivo ZIP com múltiplas notas.</p>
            
            <form action="importar-notas-fiscais.php" method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-6">
                    <div class="upload-area" id="dropArea">
                        <div class="flex flex-col items-center justify-center">
                            <div class="w-16 h-16 mb-4 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-cloud-upload-alt text-2xl text-blue-600"></i>
                            </div>
                            <p class="text-lg font-medium text-gray-700 mb-2">Arraste e solte seus arquivos aqui</p>
                            <p class="text-gray-500 mb-4">ou clique para selecionar</p>
                            <input type="file" name="arquivos_nf[]" id="arquivos_nf" multiple accept=".xml,.zip" 
                                   class="hidden" onchange="updateFileList()">
                            <button type="button" onclick="document.getElementById('arquivos_nf').click()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium transition duration-200">
                                <i class="fas fa-folder-open mr-2"></i>Selecionar Arquivos
                            </button>
                            <p class="text-sm text-gray-500 mt-4">Formatos suportados: XML (NFe, NFCe) e ZIP</p>
                            <p class="text-xs text-gray-400">Máximo: 100 arquivos por vez</p>
                        </div>
                    </div>
                    
                    <!-- Lista de arquivos selecionados -->
                    <div class="mt-4" id="fileListContainer" style="display: none;">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Arquivos selecionados:</h3>
                        <div class="space-y-2" id="fileList"></div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>Arquivos ZIP serão automaticamente extraídos e processados</span>
                    </div>
                    <button type="submit" id="submitBtn" 
                            class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-3 rounded-lg font-medium transition duration-200 shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-upload mr-2"></i>Iniciar Importação
                    </button>
                </div>
            </form>
        </div>

        <!-- Resumo de Notas Importadas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow-md p-6 border border-blue-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-500 text-white mr-4 shadow-sm">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total de Notas</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_notas; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow-md p-6 border border-green-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-500 text-white mr-4 shadow-sm">
                        <i class="fas fa-file-invoice-dollar text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Notas Fiscais (NFe)</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_nfe; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl shadow-md p-6 border border-purple-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-500 text-white mr-4 shadow-sm">
                        <i class="fas fa-receipt text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Notas do Consumidor (NFCe)</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_nfce; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Notas Importadas -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Últimas Notas Importadas</h2>
                <div class="text-sm text-gray-500">
                    <i class="fas fa-history mr-1"></i>
                    <span>Mostrando últimas 100 notas</span>
                </div>
            </div>
            
            <!-- Abas para NFe e NFCe -->
            <div class="mb-6 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px text-sm font-medium" id="notasTabs" role="tablist">
                    <li class="mr-2" role="presentation">
                        <button class="inline-block py-3 px-4 rounded-t-lg border-b-2" id="nfe-tab" data-tabs-target="#nfe" type="button" role="tab" aria-controls="nfe" aria-selected="false">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>Notas Fiscais (NFe)
                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded"><?php echo $total_nfe; ?></span>
                        </button>
                    </li>
                    <li class="mr-2" role="presentation">
                        <button class="inline-block py-3 px-4 rounded-t-lg border-b-2" id="nfce-tab" data-tabs-target="#nfce" type="button" role="tab" aria-controls="nfce" aria-selected="false">
                            <i class="fas fa-receipt mr-2"></i>Notas do Consumidor (NFCe)
                            <span class="ml-2 bg-purple-100 text-purple-800 text-xs font-medium px-2.5 py-0.5 rounded"><?php echo $total_nfce; ?></span>
                        </button>
                    </li>
                </ul>
            </div>
            
            <div id="notasTabsContent">
                <!-- Tab NFe -->
                <div class="hidden p-4 rounded-lg bg-gray-50" id="nfe" role="tabpanel" aria-labelledby="nfe-tab">
                    <?php if (mysqli_num_rows($result_nfe) > 0): ?>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="py-3 px-4 text-left font-semibold">Número</th>
                                        <th class="py-3 px-4 text-left font-semibold">Série</th>
                                        <th class="py-3 px-4 text-left font-semibold">Emissão</th>
                                        <th class="py-3 px-4 text-left font-semibold">Valor</th>
                                        <th class="py-3 px-4 text-left font-semibold">Tipo</th>
                                        <th class="py-3 px-4 text-left font-semibold">Status</th>
                                        <th class="py-3 px-4 text-left font-semibold">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 divide-y divide-gray-200">
                                    <?php while ($nfe = mysqli_fetch_assoc($result_nfe)): ?>
                                        <tr class="hover:bg-gray-50 transition duration-150">
                                            <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($nfe['numero']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($nfe['serie']); ?></td>
                                            <td class="py-3 px-4"><?php echo date('d/m/Y', strtotime($nfe['data_emissao'])); ?></td>
                                            <td class="py-3 px-4 font-semibold">R$ <?php echo number_format($nfe['valor_total'], 2, ',', '.'); ?></td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo $nfe['tipo_operacao'] === 'entrada' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo $nfe['tipo_operacao'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <span class="w-2 h-2 mr-1 bg-yellow-500 rounded-full"></span>
                                                    <?php echo ucfirst($nfe['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="flex items-center space-x-3">
                                                    <a href="detalhes-nota.php?tipo=nfe&id=<?php echo $nfe['id']; ?>" 
                                                       class="text-blue-600 hover:text-blue-800 transition duration-150 flex items-center">
                                                        <i class="fas fa-eye mr-1 text-sm"></i> Detalhes
                                                    </a>
                                                    <a href="excluir-nota.php?tipo=nfe&id=<?php echo $nfe['id']; ?>" 
                                                       class="text-red-600 hover:text-red-800 transition duration-150 flex items-center"
                                                       onclick="return confirm('Tem certeza que deseja excluir esta nota fiscal?');">
                                                        <i class="fas fa-trash mr-1 text-sm"></i> Excluir
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <i class="fas fa-file-invoice text-2xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">Nenhuma NFe importada</h3>
                            <p class="text-gray-500">Importe suas notas fiscais para vê-las aqui.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab NFCe -->
                <div class="hidden p-4 rounded-lg bg-gray-50" id="nfce" role="tabpanel" aria-labelledby='nfce-tab'>
                    <?php if (mysqli_num_rows($result_nfce) > 0): ?>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                                    <tr>
                                        <th class="py-3 px-4 text-left font-semibold">Número</th>
                                        <th class="py-3 px-4 text-left font-semibold">Série</th>
                                        <th class="py-3 px-4 text-left font-semibold">Emissão</th>
                                        <th class="py-3 px-4 text-left font-semibold">Valor</th>
                                        <th class="py-3 px-4 text-left font-semibold">Tipo</th>
                                        <th class="py-3 px-4 text-left font-semibold">Status</th>
                                        <th class="py-3 px-4 text-left font-semibold">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 divide-y divide-gray-200">
                                    <?php while ($nfce = mysqli_fetch_assoc($result_nfce)): ?>
                                        <tr class="hover:bg-gray-50 transition duration-150">
                                            <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($nfce['numero']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($nfce['serie']); ?></td>
                                            <td class="py-3 px-4"><?php echo date('d/m/Y', strtotime($nfce['data_emissao'])); ?></td>
                                            <td class="py-3 px-4 font-semibold">R$ <?php echo number_format($nfce['valor_total'], 2, ',', '.'); ?></td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo $nfce['tipo_operacao'] === 'entrada' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo $nfce['tipo_operacao'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <span class="w-2 h-2 mr-1 bg-yellow-500 rounded-full"></span>
                                                    <?php echo ucfirst($nfce['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="flex items-center space-x-3">
                                                    <a href="detalhes-nota.php?tipo=nfce&id=<?php echo $nfce['id']; ?>" 
                                                       class="text-blue-600 hover:text-blue-800 transition duration-150 flex items-center">
                                                        <i class="fas fa-eye mr-1 text-sm"></i> Detalhes
                                                    </a>
                                                    <a href="excluir-nota.php?tipo=nfce&id=<?php echo $nfce['id']; ?>" 
                                                       class="text-red-600 hover:text-red-800 transition duration-150 flex items-center"
                                                       onclick="return confirm('Tem certeza que deseja excluir esta nota fiscal?');">
                                                        <i class="fas fa-trash mr-1 text-sm"></i> Excluir
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                <i class="fas fa-receipt text-2xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">Nenhuma NFCe importada</h3>
                            <p class="text-gray-500">Importe suas notas do consumidor para vê-las aqui.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script para controlar as abas
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('[data-tabs-target]');
            
            // Mostrar a primeira aba por padrão
            if (tabButtons.length > 0) {
                const firstTab = tabButtons[0];
                const firstTabContent = document.querySelector(firstTab.getAttribute('data-tabs-target'));
                
                firstTab.classList.add('tab-active', 'text-blue-600', 'border-blue-600');
                firstTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-600', 'hover:border-gray-300');
                firstTab.setAttribute('aria-selected', 'true');
                
                if (firstTabContent) {
                    firstTabContent.classList.remove('hidden');
                }
            }
            
            // Adicionar event listeners para das abas
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const target = this.getAttribute('data-tabs-target');
                    const tabContent = document.querySelector(target);
                    
                    // Esconder todo o conteúdo das abas
                    document.querySelectorAll('[role="tabpanel"]').forEach(tab => {
                        tab.classList.add('hidden');
                    });
                    
                    // Remover estilos ativos de todos os botões
                    tabButtons.forEach(btn => {
                        btn.classList.remove('tab-active', 'text-blue-600', 'border-blue-600');
                        btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-600', 'hover:border-gray-300');
                        btn.setAttribute('aria-selected', 'false');
                    });
                    
                    // Adicionar estilos ativos ao botão clicado
                    this.classList.add('tab-active', 'text-blue-600', 'border-blue-600');
                    this.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-600', 'hover:border-gray-300');
                    this.setAttribute('aria-selected', 'true');
                    
                    // Mostrar o conteúdo da aba clicada
                    if (tabContent) {
                        tabContent.classList.remove('hidden');
                    }
                });
            });
            
            // Drag and drop functionality
            const dropArea = document.getElementById('dropArea');
            const fileInput = document.getElementById('arquivos_nf');
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropArea.classList.add('dragover');
            }
            
            function unhighlight() {
                dropArea.classList.remove('dragover');
            }
            
            dropArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                // Atualizar o input file
                const dataTransfer = new DataTransfer();
                const existingFiles = fileInput.files;
                
                // Adicionar arquivos existentes
                for (let i = 0; i < existingFiles.length; i++) {
                    dataTransfer.items.add(existingFiles[i]);
                }
                
                // Adicionar novos arquivos
                for (let i = 0; i < files.length; i++) {
                    dataTransfer.items.add(files[i]);
                }
                
                fileInput.files = dataTransfer.files;
                
                // Atualizar lista de arquivos
                updateFileList();
            }
            
            // Inicializar botão de submit
            updateSubmitButton();
        });
        
        // Função para atualizar lista de arquivos
        function updateFileList() {
            const fileInput = document.getElementById('arquivos_nf');
            const fileListContainer = document.getElementById('fileListContainer');
            const fileList = document.getElementById('fileList');
            const submitBtn = document.getElementById('submitBtn');
            
            fileList.innerHTML = '';
            
            if (fileInput.files.length > 0) {
                fileListContainer.style.display = 'block';
                
                for (let i = 0; i < fileInput.files.length; i++) {
                    const file = fileInput.files[i];
                    const fileSize = formatFileSize(file.size);
                    const fileExtension = file.name.split('.').pop().toLowerCase();
                    
                    let iconClass = 'fas fa-file';
                    let bgColor = 'bg-gray-100';
                    
                    if (fileExtension === 'xml') {
                        iconClass = 'fas fa-file-code';
                        bgColor = 'bg-blue-100 text-blue-600';
                    } else if (fileExtension === 'zip') {
                        iconClass = 'fas fa-file-archive';
                        bgColor = 'bg-yellow-100 text-yellow-600';
                    }
                    
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-list-item bg-white rounded-lg border border-gray-200 p-4 flex items-center justify-between';
                    fileItem.innerHTML = `
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-lg ${bgColor} flex items-center justify-center mr-3">
                                <i class="${iconClass}"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 truncate max-w-xs">${file.name}</p>
                                <p class="text-sm text-gray-500">${fileSize}</p>
                            </div>
                        </div>
                        <button type="button" onclick="removeFile(${i})" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    fileList.appendChild(fileItem);
                }
            } else {
                fileListContainer.style.display = 'none';
            }
            
            updateSubmitButton();
        }
        
        // Função para remover arquivo da lista
        function removeFile(index) {
            const fileInput = document.getElementById('arquivos_nf');
            const dataTransfer = new DataTransfer();
            
            for (let i = 0; i < fileInput.files.length; i++) {
                if (i !== index) {
                    dataTransfer.items.add(fileInput.files[i]);
                }
            }
            
            fileInput.files = dataTransfer.files;
            updateFileList();
        }
        
        // Função para formatar tamanho do arquivo
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Função para atualizar botão de submit
        function updateSubmitButton() {
            const fileInput = document.getElementById('arquivos_nf');
            const submitBtn = document.getElementById('submitBtn');
            
            if (fileInput.files.length > 0) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<i class="fas fa-upload mr-2"></i>Importar ${fileInput.files.length} Arquivo(s)`;
            } else {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<i class="fas fa-upload mr-2"></i>Iniciar Importação`;
            }
        }
    </script>
</body>
</html>

<?php
// Fechar statements e conexão
if (isset($stmt_nfe)) mysqli_stmt_close($stmt_nfe);
if (isset($stmt_nfce)) mysqli_stmt_close($stmt_nfce);
if (isset($stmt_total_nfe)) mysqli_stmt_close($stmt_total_nfe);
if (isset($stmt_total_nfce)) mysqli_stmt_close($stmt_total_nfce);
mysqli_close($conexao);
?>