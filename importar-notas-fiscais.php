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
        echo "<div class='bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4'>";
        echo "<p class='text-blue-800'>Processando " . count($arquivos['name']) . " arquivos... Aguarde.</p>";
        echo "<div class='w-full bg-gray-200 rounded-full h-2.5 mt-2'><div id='progressBar' class='bg-blue-600 h-2.5 rounded-full' style='width: 0%'></div></div>";
        echo "<div id='progressText' class='text-sm text-blue-600 mt-1'>0%</div>";
        echo "</div>";
        ob_flush();
        flush();
        
        $total_arquivos = count($arquivos['name']);
        $notas_importadas = 0;
        $notas_com_erro = 0;
        
        // Processar cada arquivo individualmente
        for ($i = 0; $i < $total_arquivos; $i++) {
            // Atualizar progresso
            $progresso = round(($i / $total_arquivos) * 100);
            echo "<script>
                document.getElementById('progressBar').style.width = '$progresso%';
                document.getElementById('progressText').innerText = '$progresso% (Processando arquivo " . ($i + 1) . " de $total_arquivos)';
            </script>";
            ob_flush();
            flush();
            
            if ($arquivos['error'][$i] === UPLOAD_ERR_OK) {
                $arquivo = [
                    'name' => $arquivos['name'][$i],
                    'type' => $arquivos['type'][$i],
                    'tmp_name' => $arquivos['tmp_name'][$i],
                    'error' => $arquivos['error'][$i],
                    'size' => $arquivos['size'][$i]
                ];
                
                logDebug("Processando arquivo", ['indice' => $i, 'nome' => $arquivo['name']]);
                
                $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
                
                if ($extensao === 'xml') {
                    // Processar cada arquivo individualmente
                    $processado = processarArquivoIndividual($arquivo, $conexao, $usuario_id, $cnpj);
                    
                    if ($processado) {
                        $notas_importadas++;
                        logDebug("Arquivo processado com sucesso", ['arquivo' => $arquivo['name']]);
                    } else {
                        $notas_com_erro++;
                        logDebug("Falha ao processar arquivo", ['arquivo' => $arquivo['name']]);
                    }
                } else {
                    logDebug("Tipo de arquivo não suportado", ['arquivo' => $arquivo['name'], 'extensao' => $extensao]);
                    $notas_com_erro++;
                }
            } else {
                logDebug("Erro no upload do arquivo", [
                    'arquivo' => $arquivos['name'][$i],
                    'erro' => $arquivos['error'][$i]
                ]);
                $notas_com_erro++;
            }
        }
        
        // Atualizar progresso final
        echo "<script>
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('progressText').innerText = '100% - Concluído!';
        </script>";
        ob_flush();
        flush();
        
        // Mensagem de resultado
        if ($notas_importadas > 0) {
            $mensagem = "Importação concluída! $notas_importadas nota(s) importada(s) com sucesso.";
            if ($notas_com_erro > 0) {
                $mensagem .= " $notas_com_erro nota(s) não puderam ser importadas.";
            }
        } else {
            $erro = "Nenhuma nota foi importada. Verifique se os arquivos são XML válidos e se não foram importados anteriormente.";
        }
        
        logDebug("Importação finalizada", [
            'notas_importadas' => $notas_importadas,
            'notas_com_erro' => $notas_com_erro
        ]);
    } else {
        $erro = "Nenhum arquivo selecionado.";
        logDebug("Nenhum arquivo selecionado para upload");
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
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Cabeçalho -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Importar Notas Fiscais</h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">Olá, <?php echo htmlspecialchars($razao_social); ?></span>
                <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
                </a>
                <a href="index.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-sign-out-alt mr-2"></i>Sair
                </a>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if (!empty($mensagem)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($erro)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <!-- Formulário de Upload -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Importar Novas Notas Fiscais</h2>
            <p class="text-gray-600 mb-6">Selecione os arquivos XML das notas fiscais que deseja importar. É possível selecionar múltiplos arquivos.</p>
            
            <form action="importar-notas-fiscais.php" method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2" for="arquivos_nf">Arquivos XML</label>
                    <input type="file" name="arquivos_nf[]" id="arquivos_nf" multiple accept=".xml" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Formatos suportados: XML (NFe e NFCe)</p>
                </div>
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-upload mr-2"></i>Importar Notas Fiscais
                </button>
            </form>
        </div>

        <!-- Resumo de Notas Importadas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total de Notas</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_notas; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-file-invoice-dollar text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Notas Fiscais (NFe)</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_nfe; ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
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
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Últimas Notas Importadas</h2>
            
            <!-- Abas para NFe e NFCe -->
            <div class="mb-6">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="notasTabs" data-tabs-toggle="#notasTabsContent" role="tablist">
                    <li class="mr-2" role="presentation">
                        <button class="inline-block p-4 border-b-2 rounded-t-lg" id="nfe-tab" data-tabs-target="#nfe" type="button" role="tab" aria-controls="nfe" aria-selected="false">
                            Notas Fiscais (NFe) <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded ml-1"><?php echo $total_nfe; ?></span>
                        </button>
                    </li>
                    <li class="mr-2" role="presentation">
                        <button class="inline-block p-4 border-b-2 rounded-t-lg" id="nfce-tab" data-tabs-target="#nfce" type="button" role="tab" aria-controls="nfce" aria-selected="false">
                            Notas do Consumidor (NFCe) <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2.5 py-0.5 rounded ml-1"><?php echo $total_nfce; ?></span>
                        </button>
                    </li>
                </ul>
            </div>
            
            <div id="notasTabsContent">
                <!-- Tab NFe -->
                <div class="hidden p-4 rounded-lg bg-gray-50" id="nfe" role="tabpanel" aria-labelledby="nfe-tab">
                    <?php if (mysqli_num_rows($result_nfe) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100 text-gray-600 uppercase text-sm">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Número</th>
                                        <th class="py-3 px-4 text-left">Série</th>
                                        <th class="py-3 px-4 text-left">Emissão</th>
                                        <th class="py-3 px-4 text-left">Valor</th>
                                        <th class="py-3 px-4 text-left">Tipo</th>
                                        <th class="py-3 px-4 text-left">Status</th>
                                        <th class="py-3 px-4 text-left">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700">
                                    <?php while ($nfe = mysqli_fetch_assoc($result_nfe)): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($nfe['numero']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($nfe['serie']); ?></td>
                                            <td class="py-3 px-4"><?php echo date('d/m/Y', strtotime($nfe['data_emissao'])); ?></td>
                                            <td class="py-3 px-4">R$ <?php echo number_format($nfe['valor_total'], 2, ',', '.'); ?></td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                    <?php echo $nfe['tipo_operacao'] === 'entrada' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo $nfe['tipo_operacao'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                                    <?php echo ucfirst($nfe['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <a href="detalhes-nota.php?tipo=nfe&id=<?php echo $nfe['id']; ?>" class="text-blue-600 hover:text-blue-800 mr-3">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                                <a href="excluir-nota.php?tipo=nfe&id=<?php echo $nfe['id']; ?>" class="text-red-600 hover:text-red-800" onclick="return confirm('Tem certeza que deseja excluir esta nota fiscal?');">
                                                    <i class="fas fa-trash"></i> Excluir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 py-4 text-center">Nenhuma NFe importada ainda.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Tab NFCe -->
                <div class="hidden p-4 rounded-lg bg-gray-50" id="nfce" role="tabpanel" aria-labelledby='nfce-tab'>
                    <?php if (mysqli_num_rows($result_nfce) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100 text-gray-600 uppercase text-sm">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Número</th>
                                        <th class="py-3 px-4 text-left">Série</th>
                                        <th class="py-3 px-4 text-left">Emissão</th>
                                        <th class="py-3 px-4 text-left">Valor</th>
                                        <th class="py-3 px-4 text-left">Tipo</th>
                                        <th class="py-3 px-4 text-left">Status</th>
                                        <th class="py-3 px-4 text-left">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700">
                                    <?php while ($nfce = mysqli_fetch_assoc($result_nfce)): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($nfce['numero']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($nfce['serie']); ?></td>
                                            <td class="py-3 px-4"><?php echo date('d/m/Y', strtotime($nfce['data_emissao'])); ?></td>
                                            <td class="py-3 px-4">R$ <?php echo number_format($nfce['valor_total'], 2, ',', '.'); ?></td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                    <?php echo $nfce['tipo_operacao'] === 'entrada' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo $nfce['tipo_operacao'] === 'entrada' ? 'Entrada' : 'Saída'; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                                    <?php echo ucfirst($nfce['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <a href="detalhes-nota.php?tipo=nfce&id=<?php echo $nfce['id']; ?>" class="text-blue-600 hover:text-blue-800 mr-3">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                                <a href="excluir-nota.php?tipo=nfce&id=<?php echo $nfce['id']; ?>" class="text-red-600 hover:text-red-800" onclick="return confirm('Tem certeza que deseja excluir esta nota fiscal?');">
                                                    <i class="fas fa-trash"></i> Excluir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600 py-4 text-center">Nenhuma NFCe importada ainda.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script para controlar as abas
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('[data-tabs-toggle]');
            const tabButtons = document.querySelectorAll('[data-tabs-target]');
            
            // Mostrar a primeira aba por padrão
            if (tabButtons.length > 0) {
                const firstTab = tabButtons[0];
                const firstTabContent = document.querySelector(firstTab.getAttribute('data-tabs-target'));
                
                firstTab.classList.add('text-blue-600', 'border-blue-600');
                firstTab.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
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
                        btn.classList.remove('text-blue-600', 'border-blue-600');
                        btn.classList.add('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
                        btn.setAttribute('aria-selected', 'false');
                    });
                    
                    // Adicionar estilos ativos ao botão clicado
                    this.classList.add('text-blue-600', 'border-blue-600');
                    this.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
                    this.setAttribute('aria-selected', 'true');
                    
                    // Mostrar o conteúdo da aba clicada
                    if (tabContent) {
                        tabContent.classList.remove('hidden');
                    }
                });
            });
        });
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