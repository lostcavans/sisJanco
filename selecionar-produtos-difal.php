<?php
session_start();

// Função para verificar autenticação (mesma do arquivo anterior)
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

// Incluir config.php
include("config.php");

// Verificar se a conexão foi estabelecida
if ($conexao->connect_error) {
    die("Erro de conexão com o banco de dados. Verifique o arquivo config.php");
}

$logado = $_SESSION['usuario_username'];
$usuario_id = $_SESSION['usuario_id'];
$razao_social = $_SESSION['usuario_razao_social'] ?? '';
$cnpj = $_SESSION['usuario_cnpj'] ?? '';

// Obter dados da nota
$nota_id = $_GET['nota_id'] ?? 0;
$competencia = $_GET['competencia'] ?? date('Y-m');

// Buscar dados da nota
$nota = null;
$produtos = [];

if ($nota_id) {
    $query = "SELECT n.*, e.razao_social as emitente_razao_social 
              FROM nfe n 
              LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj 
              WHERE n.id = ? AND n.usuario_id = ?";
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("ii", $nota_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $nota = $result->fetch_assoc();
        $stmt->close();
    }
    
    // Buscar produtos da nota
    $query_produtos = "SELECT * FROM nfe_itens WHERE nfe_id = ?";
    if ($stmt = $conexao->prepare($query_produtos)) {
        $stmt->bind_param("i", $nota_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $produtos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}


// Verificar se veio por cálculo_id em vez de nota_id
$calculo_id = $_GET['calculo_id'] ?? 0;
$action = $_GET['action'] ?? ''; // visualizar ou editar

if ($calculo_id && !$nota_id) {
    // Buscar a nota relacionada ao cálculo
    $query_calculo = "SELECT nf.id as nota_id, nf.* 
                      FROM calculos_difal cd 
                      LEFT JOIN grupos_calculo_difal gd ON cd.grupo_id = gd.id 
                      LEFT JOIN nfe nf ON gd.nota_fiscal_id = nf.id 
                      WHERE cd.id = ? AND cd.usuario_id = ?";
    
    if ($stmt = $conexao->prepare($query_calculo)) {
        $stmt->bind_param("ii", $calculo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $calculo_data = $result->fetch_assoc();
        
        if ($calculo_data && isset($calculo_data['nota_id'])) {
            $nota_id = $calculo_data['nota_id'];
            // Buscar dados da nota com o ID encontrado
            $query_nota = "SELECT n.*, e.razao_social as emitente_razao_social 
                          FROM nfe n 
                          LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj 
                          WHERE n.id = ? AND n.usuario_id = ?";
            
            if ($stmt_nota = $conexao->prepare($query_nota)) {
                $stmt_nota->bind_param("ii", $nota_id, $usuario_id);
                $stmt_nota->execute();
                $result_nota = $stmt_nota->get_result();
                $nota = $result_nota->fetch_assoc();
                $stmt_nota->close();
            }
        }
        $stmt->close();
    }
}

// Se for modo visualização, desabilitar edição
$modo_visualizacao = ($action === 'visualizar');

// Processar dados da SEFAZ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dados_sefaz'])) {
    $dados_sefaz = $_POST['dados_sefaz'];
    $nota_id = $_POST['nota_id'] ?? $nota_id;
    
    // Processar os dados como no HTML anexado
    $linhas = explode("\n", trim($dados_sefaz));
    $dados_processados = [];
    $registros_salvos = 0;
    
    if (count($linhas) > 1) {
        $cabecalho = array_map('trim', explode("\t", trim($linhas[0])));
        
        // Mapeamento das colunas
        $colunas_map = [
            'Número' => 'numero_item',
            'Descrição' => 'descricao_produto', 
            'Num. Doc. Responsável' => 'documento_responsavel',
            'Tipo de Imposto' => 'tipo_imposto',
            'ICMS' => 'valor_icms',
            'FECOEP' => 'valor_fecoep',
            'Alíquota ICMS' => 'aliquota_icms',
            'Alíquota FECOEP' => 'aliquota_fecoep',
            'MVA Valor' => 'mva_valor',
            'Redutor' => 'redutor_base', 
            'Redutor Crédito' => 'redutor_credito',
            'Segmento' => 'segmento',
            'Pauta' => 'pauta_fiscal',
            'NCM' => 'ncm',
            'CEST' => 'cest'
        ];
        
        // Criar mapeamento de índices
        $indices_map = [];
        foreach ($cabecalho as $index => $nome_coluna) {
            if (isset($colunas_map[$nome_coluna])) {
                $indices_map[$colunas_map[$nome_coluna]] = $index;
            }
        }
        
        for ($i = 1; $i < count($linhas); $i++) {
            $linha = trim($linhas[$i]);
            if (empty($linha)) continue;
            
            // Verificar se é linha de total (ignorar)
            if (strpos($linha, 'Total:') !== false || strpos($linha, 'Valor de imposto calculado na nota:') !== false) {
                continue;
            }
            
            $dados = array_map('trim', explode("\t", $linha));
            
            // Preparar dados para inserção
            $dados_insercao = [
                'usuario_id' => $usuario_id,
                'nota_fiscal_id' => $nota_id,
                'competencia' => $competencia
            ];
            
            // Mapear dados conforme cabeçalho
            foreach ($indices_map as $campo => $indice) {
                if (isset($dados[$indice])) {
                    $valor = $dados[$indice];
                    
                    // Converter valores monetários e numéricos
                    if (in_array($campo, ['valor_icms', 'valor_fecoep', 'pauta_fiscal'])) {
                        $dados_insercao[$campo] = converterParaDecimal($valor);
                    } elseif (in_array($campo, ['aliquota_icms', 'aliquota_fecoep', 'mva_valor', 'redutor_credito'])) {
                        $dados_insercao[$campo] = converterParaDecimal($valor, true);
                    } elseif ($campo === 'redutor_base') {
                        // TRATAMENTO ESPECIAL PARA REDUTOR_BASE
                        $redutor = converterParaDecimal($valor);
                        // Se o redutor for maior que 1, converter para decimal
                        if ($redutor > 1) {
                            $redutor = $redutor / 100;
                        }
                        // Garantir que o redutor esteja entre 0 e 1
                        $dados_insercao[$campo] = max(0, min(1, $redutor));
                    } else {
                        $dados_insercao[$campo] = $valor;
                    }
                }
            }
            
            // Inserir no banco de dados
            if (salvarDadosSefaz($conexao, $dados_insercao)) {
                $registros_salvos++;
                $dados_processados[] = $dados_insercao;
            }
        }
        
        if ($registros_salvos > 0) {
            $msg_sefaz = "Dados da SEFAZ processados com sucesso! $registros_salvos registros salvos no banco de dados.";
            
            // Opcional: criar um grupo de cálculo automaticamente
            criarGrupoCalculoAutomatico($conexao, $usuario_id, $nota_id, $competencia, $registros_salvos);
        } else {
            $error = "Nenhum registro foi salvo. Verifique o formato dos dados.";
        }
    } else {
        $error = "Dados insuficientes. Certifique-se de copiar o cabeçalho e os dados.";
    }
}

// Função para converter valores para decimal
function converterParaDecimal($valor, $ehPercentual = false) {
    if (empty($valor)) return 0;
    
    // Remover R$, espaços e converter vírgula para ponto
    $limpo = str_replace(['R$', ' ', '.'], '', $valor);
    $limpo = str_replace(',', '.', $limpo);
    
    $numero = floatval($limpo);
    
    // Se for percentual e estiver em formato decimal (0.19), converter para percentual real
    // O redutor_base deve ser armazenado como decimal (ex: 0.6316 para 63.16%)
    if ($ehPercentual) {
        // Se o número for maior que 1, provavelmente está em formato percentual (63.16)
        if ($numero > 1) {
            $numero = $numero / 100; // Converte 63.16 para 0.6316
        }
        // Se já estiver em formato decimal (0.6316), mantém como está
    }
    
    return $numero;
}

// Função para salvar dados no banco
function salvarDadosSefaz($conexao, $dados) {
    $campos = implode(', ', array_keys($dados));
    $placeholders = implode(', ', array_fill(0, count($dados), '?'));
    
    $query = "INSERT INTO dados_sefaz_difal ($campos) VALUES ($placeholders)";
    
    if ($stmt = $conexao->prepare($query)) {
        $tipos = '';
        $valores = [];
        
        foreach ($dados as $campo => $valor) {
            if (in_array($campo, ['valor_icms', 'valor_fecoep', 'pauta_fiscal', 'aliquota_icms', 'aliquota_fecoep', 'mva_valor', 'redutor_base', 'redutor_credito'])) {
                $tipos .= 'd'; // decimal
            } else {
                $tipos .= 's'; // string
            }
            $valores[] = $valor;
        }
        
        $stmt->bind_param($tipos, ...$valores);
        $resultado = $stmt->execute();
        $stmt->close();
        
        return $resultado;
    }
    
    return false;
}

// Função para criar grupo de cálculo automaticamente
function criarGrupoCalculoAutomatico($conexao, $usuario_id, $nota_id, $competencia, $total_produtos) {
    $descricao = "Grupo SEFAZ - Nota $nota_id - " . date('d/m/Y H:i');
    
    $query = "INSERT INTO grupos_calculo_difal (usuario_id, nota_fiscal_id, descricao) VALUES (?, ?, ?)";
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("iis", $usuario_id, $nota_id, $descricao);
        if ($stmt->execute()) {
            $grupo_id = $conexao->insert_id;
            $stmt->close();
            
            // Criar cálculo DIFAL básico
            $query_calculo = "INSERT INTO calculos_difal 
                             (usuario_id, grupo_id, descricao, valor_base_calculo, valor_difal, competencia) 
                             VALUES (?, ?, ?, 0, 0, ?)";
            
            if ($stmt_calculo = $conexao->prepare($query_calculo)) {
                $desc_calculo = "Cálculo baseado em $total_produtos produtos da SEFAZ";
                $stmt_calculo->bind_param("iiss", $usuario_id, $grupo_id, $desc_calculo, $competencia);
                $stmt_calculo->execute();
                $stmt_calculo->close();
            }
            
            return $grupo_id;
        }
        $stmt->close();
    }
    
    return false;
}

// Função para buscar dados SEFAZ salvos
function buscarDadosSefazSalvos($conexao, $usuario_id, $nota_id, $competencia) {
    $query = "SELECT * FROM dados_sefaz_difal 
              WHERE usuario_id = ? AND nota_fiscal_id = ? AND competencia = ?
              ORDER BY numero_item";
    
    $dados = [];
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("iis", $usuario_id, $nota_id, $competencia);
        $stmt->execute();
        $result = $stmt->get_result();
        $dados = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    return $dados;
}

// Buscar dados já salvos
$dados_sefaz_salvos = buscarDadosSefazSalvos($conexao, $usuario_id, $nota_id, $competencia);

// Função para excluir dados SEFAZ salvos
function excluirDadosSefaz($conexao, $usuario_id, $nota_id, $competencia) {
    // Excluir dados da SEFAZ
    $query = "DELETE FROM dados_sefaz_difal 
              WHERE usuario_id = ? AND nota_fiscal_id = ? AND competencia = ?";
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("iis", $usuario_id, $nota_id, $competencia);
        $resultado = $stmt->execute();
        $linhas_afetadas = $stmt->affected_rows;
        $stmt->close();
        
        // Se excluiu dados, também excluir grupos de cálculo associados
        if ($linhas_afetadas > 0) {
            excluirGruposCalculoSefaz($conexao, $usuario_id, $nota_id, $competencia);
        }
        
        return $linhas_afetadas;
    }
    
    return false;
}

// Função para excluir grupos de cálculo associados aos dados SEFAZ
function excluirGruposCalculoSefaz($conexao, $usuario_id, $nota_id, $competencia) {
    // Encontrar grupos de cálculo associados a esta nota e competência
    $query_grupos = "SELECT id FROM grupos_calculo_difal 
                     WHERE usuario_id = ? AND nota_fiscal_id = ?";
    
    $grupos_ids = [];
    
    if ($stmt = $conexao->prepare($query_grupos)) {
        $stmt->bind_param("ii", $usuario_id, $nota_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $grupos_ids[] = $row['id'];
        }
        $stmt->close();
    }
    
    // Excluir cálculos DIFAL associados
    if (!empty($grupos_ids)) {
        $placeholders = implode(',', array_fill(0, count($grupos_ids), '?'));
        $tipos = str_repeat('i', count($grupos_ids));
        
        $query_calculos = "DELETE FROM calculos_difal 
                          WHERE usuario_id = ? AND grupo_id IN ($placeholders) AND competencia = ?";
        
        if ($stmt = $conexao->prepare($query_calculos)) {
            $params = array_merge([$usuario_id], $grupos_ids, [$competencia]);
            $stmt->bind_param("i" . $tipos . "s", ...$params);
            $stmt->execute();
            $stmt->close();
        }
        
        // Excluir produtos dos grupos
        $query_produtos = "DELETE FROM grupo_difal_produtos 
                          WHERE grupo_difal_id IN ($placeholders)";
        
        if ($stmt = $conexao->prepare($query_produtos)) {
            $stmt->bind_param($tipos, ...$grupos_ids);
            $stmt->execute();
            $stmt->close();
        }
        
        // Excluir os grupos
        $query_excluir_grupos = "DELETE FROM grupos_calculo_difal 
                                WHERE id IN ($placeholders)";
        
        if ($stmt = $conexao->prepare($query_excluir_grupos)) {
            $stmt->bind_param($tipos, ...$grupos_ids);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    return true;
}

// Processar exclusão de dados SEFAZ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'excluir_dados_sefaz') {
    $nota_id = $_POST['nota_id'] ?? 0;
    $competencia = $_POST['competencia'] ?? date('Y-m');
    
    $registros_excluidos = excluirDadosSefaz($conexao, $usuario_id, $nota_id, $competencia);
    
    if ($registros_excluidos !== false) {
        $_SESSION['msg_sefaz'] = "Dados da SEFAZ excluídos com sucesso! $registros_excluidos registros removidos.";
    } else {
        $_SESSION['error_sefaz'] = "Erro ao excluir dados da SEFAZ.";
    }
    
    // Redirecionar para evitar reenvio do formulário
    header("Location: selecionar-produtos-difal.php?nota_id=$nota_id&competencia=$competencia");
    exit;
}




// Função para salvar cálculo DIFAL manual
function salvarCalculoDifal($conexao, $usuario_id, $nota_id, $competencia, $descricao, $itens) {
    // Inserir cabeçalho do cálculo na tabela CORRETA
    $query = "INSERT INTO calculos_difal_manuais (usuario_id, nota_fiscal_id, descricao, competencia) VALUES (?, ?, ?, ?)";
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("iiss", $usuario_id, $nota_id, $descricao, $competencia);
        if ($stmt->execute()) {
            $calculo_id = $conexao->insert_id;
            $stmt->close();
            
            // Inserir itens do cálculo (já está correto - usa calculos_difal_itens que referencia calculos_difal_manuais)
            $query_itens = "INSERT INTO calculos_difal_itens 
                           (calculo_id, numero_item, descricao_produto, ncm, valor_produto, 
                            aliquota_difal, aliquota_fecoep, aliquota_reducao, valor_difal, valor_fecoep, valor_total_impostos) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt_itens = $conexao->prepare($query_itens)) {
                foreach ($itens as $item) {
                    $aliquota_reducao = isset($item['aliquota_reducao']) ? $item['aliquota_reducao'] : 0.6316;
                    
                    $stmt_itens->bind_param(
                        "iissddddddd", 
                        $calculo_id,
                        $item['numero_item'],
                        $item['descricao_produto'],
                        $item['ncm'],
                        $item['valor_produto'],
                        $item['aliquota_difal'],
                        $item['aliquota_fecoep'],
                        $aliquota_reducao,
                        $item['valor_difal'],
                        $item['valor_fecoep'],
                        $item['valor_total_impostos']
                    );
                    $stmt_itens->execute();
                }
                $stmt_itens->close();
                return $calculo_id;
            }
        }
        $stmt->close();
    }
    return false;
}

// Função para buscar cálculos DIFAL salvos
function buscarCalculosDifal($conexao, $usuario_id, $nota_id, $competencia) {
    $query = "SELECT cd.*, 
                     (SELECT COUNT(*) FROM calculos_difal_itens WHERE calculo_id = cd.id) as total_itens,
                     (SELECT SUM(valor_total_impostos) FROM calculos_difal_itens WHERE calculo_id = cd.id) as total_impostos
              FROM calculos_difal_manuais cd
              WHERE cd.usuario_id = ? AND cd.nota_fiscal_id = ? AND cd.competencia = ?
              ORDER BY cd.data_calculo DESC";
    
    $calculos = [];
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("iis", $usuario_id, $nota_id, $competencia);
        $stmt->execute();
        $result = $stmt->get_result();
        $calculos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    return $calculos;
}

// Função para buscar itens de um cálculo específico
function buscarItensCalculoDifal($conexao, $calculo_id) {
    $query = "SELECT * FROM calculos_difal_itens WHERE calculo_id = ? ORDER BY numero_item";
    
    $itens = [];
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("i", $calculo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $itens = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    return $itens;
}

// Função para excluir cálculo DIFAL
function excluirCalculoDifal($conexao, $calculo_id, $usuario_id) {
    // Verificar se o cálculo pertence ao usuário
    $query_verificar = "SELECT id FROM calculos_difal_manuais WHERE id = ? AND usuario_id = ?";
    
    if ($stmt = $conexao->prepare($query_verificar)) {
        $stmt->bind_param("ii", $calculo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false; // Cálculo não pertence ao usuário
        }
        $stmt->close();
    }
    
    // Excluir cálculo (os itens serão excluídos em cascata)
    $query_excluir = "DELETE FROM calculos_difal_manuais WHERE id = ?";
    
    if ($stmt = $conexao->prepare($query_excluir)) {
        $stmt->bind_param("i", $calculo_id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    
    return false;
}

// Processar cálculo DIFAL manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calcular_difal') {
    $itens_calculo = [];
    $calculo_id = $_POST['calculo_id'] ?? 0;
    
    // Coletar dados dos produtos
    foreach ($_POST['produtos'] as $produto_id => $dados) {
        // Converter valor do produto corretamente
        $valor_produto = converterValorMonetario($dados['valor_produto']);
        
        $aliquota_difal = floatval(str_replace(',', '.', $dados['aliquota_difal'])) / 100;
        $aliquota_fecoep = floatval(str_replace(',', '.', $dados['aliquota_fecoep'])) / 100;
        $aliquota_reducao = floatval(str_replace(',', '.', $dados['aliquota_reducao'])) / 100;
        
        // Calcular DIFAL bruto
        $valor_difal_bruto = $valor_produto * $aliquota_difal;
        
        // Aplicar redução
        $valor_reducao = $valor_difal_bruto * $aliquota_reducao;
        $valor_difal = $valor_difal_bruto - $valor_reducao;
        
        $valor_fecoep = $valor_produto * $aliquota_fecoep;
        $valor_total_impostos = $valor_difal + $valor_fecoep;
        
        $itens_calculo[] = [
            'numero_item' => intval($dados['numero_item']),
            'descricao_produto' => $dados['descricao_produto'],
            'ncm' => $dados['ncm'],
            'valor_produto' => $valor_produto,
            'aliquota_difal' => $aliquota_difal,
            'aliquota_fecoep' => $aliquota_fecoep,
            'aliquota_reducao' => $aliquota_reducao,
            'valor_difal' => $valor_difal,
            'valor_fecoep' => $valor_fecoep,
            'valor_total_impostos' => $valor_total_impostos
        ];
    }
    
    $descricao = $_POST['descricao_calculo'] ?: "Cálculo DIFAL - " . date('d/m/Y H:i');
    
    // Se tem calculo_id, atualizar. Senão, criar novo.
    if ($calculo_id) {
        $resultado = atualizarCalculoDifal($conexao, $calculo_id, $itens_calculo, $usuario_id);
        $msg = $resultado ? "Cálculo DIFAL atualizado com sucesso!" : "Erro ao atualizar o cálculo DIFAL.";
    } else {
        $calculo_id = salvarCalculoDifal($conexao, $usuario_id, $nota_id, $competencia, $descricao, $itens_calculo);
        $msg = $calculo_id ? "Cálculo DIFAL salvo com sucesso!" : "Erro ao salvar o cálculo DIFAL.";
    }
    
    if ($calculo_id) {
        $_SESSION['msg_calculo'] = $msg;
    } else {
        $_SESSION['error_calculo'] = $msg;
    }
    
    header("Location: selecionar-produtos-difal.php?nota_id=$nota_id&competencia=$competencia");
    exit;
}

// Processar exclusão de cálculo DIFAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'excluir_calculo_difal') {
    $calculo_id = $_POST['calculo_id'] ?? 0;
    
    if (excluirCalculoDifal($conexao, $calculo_id, $usuario_id)) {
        $_SESSION['msg_calculo'] = "Cálculo DIFAL excluído com sucesso!";
    } else {
        $_SESSION['error_calculo'] = "Erro ao excluir o cálculo DIFAL.";
    }
    
    header("Location: selecionar-produtos-difal.php?nota_id=$nota_id&competencia=$competencia");
    exit;
}

// Buscar cálculos DIFAL salvos
$calculos_difal = buscarCalculosDifal($conexao, $usuario_id, $nota_id, $competencia);


function converterValorMonetario($valor) {
    if (empty($valor)) return 0;
    
    // Remover "R$" e espaços
    $limpo = str_replace(['R$', ' '], '', $valor);
    
    // Se tiver ponto como separador de milhar e vírgula como decimal
    if (strpos($limpo, '.') !== false && strpos($limpo, ',') !== false) {
        // Remover pontos (separadores de milhar) e converter vírgula para ponto
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
    }
    // Se só tem vírgula como separador decimal
    elseif (strpos($limpo, ',') !== false) {
        $limpo = str_replace(',', '.', $limpo);
    }
    
    return floatval($limpo);
}




// Função para atualizar cálculo DIFAL
function atualizarCalculoDifal($conexao, $calculo_id, $itens, $usuario_id) {
    // Verificar se o cálculo pertence ao usuário
    $query_verificar = "SELECT id FROM calculos_difal_manuais WHERE id = ? AND usuario_id = ?";
    
    if ($stmt = $conexao->prepare($query_verificar)) {
        $stmt->bind_param("ii", $calculo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false; // Cálculo não pertence ao usuário
        }
        $stmt->close();
    }
    
    // Atualizar cada item (ATUALIZADO COM REDUTOR)
    foreach ($itens as $item) {
        $query = "UPDATE calculos_difal_itens 
                 SET aliquota_difal = ?, aliquota_fecoep = ?, aliquota_reducao = ?,
                     valor_difal = ?, valor_fecoep = ?, valor_total_impostos = ?,
                     data_atualizacao = NOW()
                 WHERE id = ? AND calculo_id = ?";
        
        if ($stmt = $conexao->prepare($query)) {
            $stmt->bind_param("ddddddii", 
                $item['aliquota_difal'], 
                $item['aliquota_fecoep'],
                $item['aliquota_reducao'], // NOVO CAMPO
                $item['valor_difal'],
                $item['valor_fecoep'],
                $item['valor_total_impostos'],
                $item['item_id'],
                $calculo_id
            );
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Atualizar data de modificação do cálculo
    $query_update = "UPDATE calculos_difal_manuais SET data_atualizacao = NOW() WHERE id = ?";
    if ($stmt = $conexao->prepare($query_update)) {
        $stmt->bind_param("i", $calculo_id);
        $stmt->execute();
        $stmt->close();
    }
    
    return true;
}

// Processar atualização de cálculo DIFAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'atualizar_calculo_difal') {
    $calculo_id = $_POST['calculo_id'] ?? 0;
    $itens_data = $_POST['itens_data'] ?? '[]';
    
    $itens = json_decode($itens_data, true);
    
    if (atualizarCalculoDifal($conexao, $calculo_id, $itens, $usuario_id)) {
        $_SESSION['msg_calculo'] = "Cálculo DIFAL atualizado com sucesso!";
    } else {
        $_SESSION['error_calculo'] = "Erro ao atualizar o cálculo DIFAL.";
    }
    
    header("Location: selecionar-produtos-difal.php?nota_id=$nota_id&competencia=$competencia");
    exit;
}


// Função para comparar dados SEFAZ com cálculo manual
function compararDifalSefazManual($conexao, $usuario_id, $nota_id, $competencia, $calculo_id) {
    $resultado = [
        'produtos_compativeis' => [],
        'produtos_divergentes' => [],
        'produtos_apenas_sefaz' => [],
        'produtos_apenas_manual' => [],
        'totais' => [
            'sefaz_icms' => 0,
            'sefaz_fecoep' => 0,
            'manual_icms' => 0,
            'manual_fecoep' => 0,
            'diferenca_icms' => 0,
            'diferenca_fecoep' => 0
        ]
    ];

    // Buscar dados SEFAZ
    $dados_sefaz = buscarDadosSefazSalvos($conexao, $usuario_id, $nota_id, $competencia);
    
    // Buscar itens do cálculo manual
    $itens_manual = buscarItensCalculoDifal($conexao, $calculo_id);
    
    // Criar arrays indexados por número do item para comparação
    $sefaz_indexado = [];
    foreach ($dados_sefaz as $item) {
        $sefaz_indexado[$item['numero_item']] = $item;
    }
    
    $manual_indexado = [];
    foreach ($itens_manual as $item) {
        $manual_indexado[$item['numero_item']] = $item;
    }

    // Comparar produtos
    $todos_itens = array_unique(array_merge(array_keys($sefaz_indexado), array_keys($manual_indexado)));
    
    foreach ($todos_itens as $numero_item) {
        $tem_sefaz = isset($sefaz_indexado[$numero_item]);
        $tem_manual = isset($manual_indexado[$numero_item]);
        
        if ($tem_sefaz && $tem_manual) {
            $item_sefaz = $sefaz_indexado[$numero_item];
            $item_manual = $manual_indexado[$numero_item];
            
            // Calcular diferenças
            $diferenca_icms = abs($item_sefaz['valor_icms'] - $item_manual['valor_difal']);
            $diferenca_fecoep = abs($item_sefaz['valor_fecoep'] - $item_manual['valor_fecoep']);
            
            // Considerar divergente se diferença > 0.01 (1 centavo)
            $limite_tolerancia = 0.01;
            
            if ($diferenca_icms > $limite_tolerancia || $diferenca_fecoep > $limite_tolerancia) {
                $resultado['produtos_divergentes'][] = [
                    'numero_item' => $numero_item,
                    'descricao' => $item_manual['descricao_produto'],
                    'ncm' => $item_manual['ncm'],
                    'sefaz_icms' => $item_sefaz['valor_icms'],
                    'sefaz_fecoep' => $item_sefaz['valor_fecoep'],
                    'manual_icms' => $item_manual['valor_difal'],
                    'manual_fecoep' => $item_manual['valor_fecoep'],
                    'diferenca_icms' => $diferenca_icms,
                    'diferenca_fecoep' => $diferenca_fecoep
                ];
            } else {
                $resultado['produtos_compativeis'][] = [
                    'numero_item' => $numero_item,
                    'descricao' => $item_manual['descricao_produto'],
                    'ncm' => $item_manual['ncm'],
                    'sefaz_icms' => $item_sefaz['valor_icms'],
                    'sefaz_fecoep' => $item_sefaz['valor_fecoep'],
                    'manual_icms' => $item_manual['valor_difal'],
                    'manual_fecoep' => $item_manual['valor_fecoep']
                ];
            }
        } elseif ($tem_sefaz && !$tem_manual) {
            $resultado['produtos_apenas_sefaz'][] = $sefaz_indexado[$numero_item];
        } elseif (!$tem_sefaz && $tem_manual) {
            $resultado['produtos_apenas_manual'][] = $manual_indexado[$numero_item];
        }
    }

    // Calcular totais
    foreach ($resultado['produtos_compativeis'] as $item) {
        $resultado['totais']['sefaz_icms'] += $item['sefaz_icms'];
        $resultado['totais']['sefaz_fecoep'] += $item['sefaz_fecoep'];
        $resultado['totais']['manual_icms'] += $item['manual_icms'];
        $resultado['totais']['manual_fecoep'] += $item['manual_fecoep'];
    }
    
    foreach ($resultado['produtos_divergentes'] as $item) {
        $resultado['totais']['sefaz_icms'] += $item['sefaz_icms'];
        $resultado['totais']['sefaz_fecoep'] += $item['sefaz_fecoep'];
        $resultado['totais']['manual_icms'] += $item['manual_icms'];
        $resultado['totais']['manual_fecoep'] += $item['manual_fecoep'];
    }
    
    $resultado['totais']['diferenca_icms'] = $resultado['totais']['manual_icms'] - $resultado['totais']['sefaz_icms'];
    $resultado['totais']['diferenca_fecoep'] = $resultado['totais']['manual_fecoep'] - $resultado['totais']['sefaz_fecoep'];

    return $resultado;
}

// Função para salvar relatório de contestação
function salvarRelatorioContestacao($conexao, $usuario_id, $nota_id, $competencia, $dados_relatorio) {
    $query = "INSERT INTO relatorios_contestacao_difal 
              (usuario_id, nota_fiscal_id, competencia, chave_nota, descricao_produtos, 
               icms_sefaz, fecoep_sefaz, icms_manual, fecoep_manual, numero_item, observacoes) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conexao->prepare($query)) {
        foreach ($dados_relatorio as $item) {
            $stmt->bind_param(
                "iisssddddss",
                $usuario_id,
                $nota_id,
                $competencia,
                $item['chave_nota'],
                $item['descricao_produtos'],
                $item['icms_sefaz'],
                $item['fecoep_sefaz'],
                $item['icms_manual'],
                $item['fecoep_manual'],
                $item['numero_item'],
                $item['observacoes']
            );
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }
    
    return false;
}

// Processar geração de comparação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'gerar_comparacao') {
    $calculo_id = $_POST['calculo_id'] ?? 0;
    
    $comparacao = compararDifalSefazManual($conexao, $usuario_id, $nota_id, $competencia, $calculo_id);
    
    // Salvar na sessão para exibição
    $_SESSION['comparacao_difal'] = $comparacao;
    $_SESSION['calculo_comparacao_id'] = $calculo_id;
    
    header("Location: selecionar-produtos-difal.php?nota_id=$nota_id&competencia=$competencia&tab=comparacao");
    exit;
}

// Processar salvamento do relatório de contestação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'salvar_relatorio_contestacao') {
    $dados_relatorio = json_decode($_POST['dados_relatorio'], true);
    
    if (salvarRelatorioContestacao($conexao, $usuario_id, $nota_id, $competencia, $dados_relatorio)) {
        $_SESSION['msg_relatorio'] = "Relatório de contestação salvo com sucesso!";
    } else {
        $_SESSION['error_relatorio'] = "Erro ao salvar relatório de contestação.";
    }
    
    header("Location: selecionar-produtos-difal.php?nota_id=$nota_id&competencia=$competencia&tab=comparacao");
    exit;
}

// Recuperar comparação da sessão se existir
$comparacao = $_SESSION['comparacao_difal'] ?? null;
$calculo_comparacao_id = $_SESSION['calculo_comparacao_id'] ?? null;






// Função para buscar relatórios de contestação salvos
function buscarRelatoriosContestacao($conexao, $usuario_id, $nota_id, $competencia) {
    $query = "SELECT * FROM relatorios_contestacao_difal 
              WHERE usuario_id = ? AND nota_fiscal_id = ? AND competencia = ?
              ORDER BY data_criacao DESC";
    
    $relatorios = [];
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("iis", $usuario_id, $nota_id, $competencia);
        $stmt->execute();
        $result = $stmt->get_result();
        $relatorios = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    return $relatorios;
}

// Função para excluir relatório de contestação
function excluirRelatorioContestacao($conexao, $relatorio_id, $usuario_id) {
    // Verificar se o relatório pertence ao usuário
    $query_verificar = "SELECT id FROM relatorios_contestacao_difal WHERE id = ? AND usuario_id = ?";
    
    if ($stmt = $conexao->prepare($query_verificar)) {
        $stmt->bind_param("ii", $relatorio_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false; // Relatório não pertence ao usuário
        }
        $stmt->close();
    }
    
    // Excluir relatório
    $query_excluir = "DELETE FROM relatorios_contestacao_difal WHERE id = ?";
    
    if ($stmt = $conexao->prepare($query_excluir)) {
        $stmt->bind_param("i", $relatorio_id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    
    return false;
}

// Processar exclusão de relatório
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'excluir_relatorio') {
    $relatorio_id = $_POST['relatorio_id'] ?? 0;
    
    if (excluirRelatorioContestacao($conexao, $relatorio_id, $usuario_id)) {
        $_SESSION['msg_relatorio'] = "Relatório excluído com sucesso!";
    } else {
        $_SESSION['error_relatorio'] = "Erro ao excluir relatório.";
    }
    
    header("Location: selecionar-produtos-difal.php?nota_id=$nota_id&competencia=$competencia&tab=comparacao");
    exit;
}

// Buscar relatórios salvos
$relatorios_salvos = buscarRelatoriosContestacao($conexao, $usuario_id, $nota_id, $competencia);


// Buscar cálculos existentes para esta nota (se houver nota_id)
$calculos_existentes = [];
if ($nota_id) {
    $query_calculos = "SELECT cd.*, 
                       (SELECT COUNT(*) FROM calculos_difal_itens WHERE calculo_id = cd.id) as total_itens,
                       (SELECT SUM(valor_total_impostos) FROM calculos_difal_itens WHERE calculo_id = cd.id) as total_impostos
                FROM calculos_difal_manuais cd
                WHERE cd.usuario_id = ? AND cd.nota_fiscal_id = ? AND cd.competencia = ?
                ORDER BY cd.data_calculo DESC";
    
    if ($stmt = $conexao->prepare($query_calculos)) {
        $stmt->bind_param("iis", $usuario_id, $nota_id, $competencia);
        $stmt->execute();
        $result = $stmt->get_result();
        $calculos_existentes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Se veio com calculo_id, buscar os itens desse cálculo específico
$calculo_especifico = null;
$itens_calculo_especifico = [];
if ($calculo_id) {
    $query_calculo_especifico = "SELECT * FROM calculos_difal_manuais WHERE id = ? AND usuario_id = ?";
    if ($stmt = $conexao->prepare($query_calculo_especifico)) {
        $stmt->bind_param("ii", $calculo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $calculo_especifico = $result->fetch_assoc();
        $stmt->close();
    }
    
    // Buscar itens do cálculo específico
    if ($calculo_especifico) {
        $query_itens = "SELECT * FROM calculos_difal_itens WHERE calculo_id = ? ORDER BY numero_item";
        if ($stmt = $conexao->prepare($query_itens)) {
            $stmt->bind_param("i", $calculo_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $itens_calculo_especifico = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}



$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cálculo DIFAL - Sistema Contábil Integrado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active { background-color: #3b82f6; color: white; }
        .table-responsive {
    overflow-x: auto;
    max-height: 400px;
    overflow-y: auto;
}

#tabela-dados {
    min-width: 800px;
}

#tabela-dados th {
    position: sticky;
    top: 0;
    background-color: #f9fafb;
    z-index: 10;
}
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i data-feather="percent" class="text-blue-600 w-6 h-6"></i>
                    <h1 class="text-xl font-bold text-gray-800">Sistema Contábil Integrado</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-blue-600 rounded-full w-10 h-10 flex items-center justify-center text-white">
                            <i data-feather="building" class="w-5 h-5"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($razao_social); ?></span>
                            <span class="text-xs text-gray-500">CNPJ: <?php echo htmlspecialchars($cnpj); ?></span>
                        </div>
                    </div>
                    <a href="difal.php?competencia=<?php echo $competencia; ?>" class="flex items-center text-sm text-gray-600 hover:text-blue-600 transition-colors">
                        <i data-feather="arrow-left" class="w-4 h-4 mr-1"></i> Voltar
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Cabeçalho -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                <i data-feather="percent" class="w-5 h-5 mr-2 text-blue-600"></i>
                                <?php echo $modo_visualizacao ? 'Visualizar' : 'Editar'; ?> Cálculo DIFAL - Nota <?php echo htmlspecialchars($nota['numero'] ?? 'N/A'); ?>
                                <?php if ($modo_visualizacao): ?>
                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Modo Visualização
                                </span>
                                <?php endif; ?>
                            </h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Emitente: <?php echo htmlspecialchars($nota['emitente_razao_social'] ?? 'N/A'); ?> | 
                                Data: <?php echo date('d/m/Y', strtotime($nota['data_emissao'] ?? 'now')); ?> | 
                                Valor: R$ <?php echo number_format($nota['valor_total'] ?? 0, 2, ',', '.'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Abas -->
                <div class="bg-white rounded-xl shadow-sm mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button id="tab-calculo" class="tab-button active py-4 px-6 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors" onclick="switchTab('calculo')">
                                <i data-feather="calculator" class="w-4 h-4 mr-2 inline"></i>
                                Cálculo DIFAL
                            </button>
                            <button id="tab-sefaz" class="tab-button py-4 px-6 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors" onclick="switchTab('sefaz')">
                                <i data-feather="database" class="w-4 h-4 mr-2 inline"></i>
                                Dados SEFAZ
                            </button>
                            <button id="tab-comparacao" class="tab-button py-4 px-6 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors" onclick="switchTab('comparacao')">
                                <i data-feather="git-compare" class="w-4 h-4 mr-2 inline"></i>
                                Comparação
                            </button>
                        </nav>
                    </div>

                    <!-- Conteúdo das Abas -->
                    <div id="tab-content-calculo" class="tab-content active">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <?php echo $modo_visualizacao ? 'Visualizar' : 'Editar'; ?> Cálculo do DIFAL
                            <?php if ($calculo_especifico): ?>
                            - <?php echo htmlspecialchars($calculo_especifico['descricao']); ?>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if (count($produtos) > 0): ?>
                        
                        <!-- Formulário de Cálculo -->
                        <form method="POST" id="form-calculo-difal">
                            <input type="hidden" name="action" value="calcular_difal">
                            <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                            <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                            <?php if ($calculo_especifico): ?>
                            <input type="hidden" name="calculo_id" value="<?php echo $calculo_especifico['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-6">
                                <label for="descricao_calculo" class="block text-sm font-medium text-gray-700 mb-2">
                                    Descrição do Cálculo:
                                </label>
                                <input type="text" id="descricao_calculo" name="descricao_calculo" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Ex: Cálculo DIFAL com alíquotas padrão"
                                    value="<?php echo $calculo_especifico ? htmlspecialchars($calculo_especifico['descricao']) : 'Cálculo DIFAL - ' . date('d/m/Y H:i'); ?>"
                                    <?php echo $modo_visualizacao ? 'readonly' : ''; ?>>
                            </div>

                            <div class="table-responsive mb-6">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">NCM</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor Produto</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">% DIFAL</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">% FECOEP</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">% Redução</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor DIFAL</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor FECOEP</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="tbody-calculo">
                                        <?php foreach ($produtos as $index => $produto): 
                                            // Buscar dados do cálculo específico para este produto
                                            $item_calculo = null;
                                            if ($calculo_especifico) {
                                                foreach ($itens_calculo_especifico as $item) {
                                                    if ($item['numero_item'] == $produto['numero_item']) {
                                                        $item_calculo = $item;
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                        <tr class="produto-calculo" data-valor="<?php echo $produto['valor_total']; ?>">
                                            <input type="hidden" name="produtos[<?php echo $index; ?>][numero_item]" value="<?php echo $produto['numero_item']; ?>">
                                            <input type="hidden" name="produtos[<?php echo $index; ?>][descricao_produto]" value="<?php echo htmlspecialchars($produto['descricao']); ?>">
                                            <input type="hidden" name="produtos[<?php echo $index; ?>][ncm]" value="<?php echo $produto['ncm']; ?>">
                                            <input type="hidden" name="produtos[<?php echo $index; ?>][valor_produto]" value="<?php echo $produto['valor_total']; ?>">
                                            
                                            <td class="px-4 py-3 text-sm text-gray-500"><?php echo $produto['numero_item']; ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-500" title="<?php echo htmlspecialchars($produto['descricao']); ?>">
                                                <?php echo htmlspecialchars(substr($produto['descricao'], 0, 30)); ?>
                                                <?php if (strlen($produto['descricao']) > 30): ?>...<?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500"><?php echo $produto['ncm']; ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-500 valor-produto">
                                                R$ <?php echo number_format($produto['valor_total'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="text" 
                                                    name="produtos[<?php echo $index; ?>][aliquota_difal]" 
                                                    class="w-20 px-2 py-1 border border-gray-300 rounded text-sm aliquota-difal"
                                                    placeholder="7,00"
                                                    value="<?php echo $item_calculo ? number_format($item_calculo['aliquota_difal'] * 100, 2, ',', '') : '7,00'; ?>"
                                                    onchange="calcularImpostos(this)"
                                                    <?php echo $modo_visualizacao ? 'readonly' : ''; ?>>
                                                <span class="text-xs text-gray-500">%</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="text" 
                                                    name="produtos[<?php echo $index; ?>][aliquota_fecoep]" 
                                                    class="w-20 px-2 py-1 border border-gray-300 rounded text-sm aliquota-fecoep"
                                                    placeholder="1,00"
                                                    value="<?php echo $item_calculo ? number_format($item_calculo['aliquota_fecoep'] * 100, 4, ',', '') : '1,00'; ?>"
                                                    onchange="calcularImpostos(this)"
                                                    <?php echo $modo_visualizacao ? 'readonly' : ''; ?>>
                                                <span class="text-xs text-gray-500">%</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="text" 
                                                    name="produtos[<?php echo $index; ?>][aliquota_reducao]" 
                                                    class="w-20 px-2 py-1 border border-gray-300 rounded text-sm aliquota-reducao"
                                                    placeholder="63,16"
                                                    value="<?php echo $item_calculo ? number_format($item_calculo['aliquota_reducao'] * 100, 2, ',', '') : '63,16'; ?>"
                                                    onchange="calcularImpostos(this)"
                                                    <?php echo $modo_visualizacao ? 'readonly' : ''; ?>>
                                                <span class="text-xs text-gray-500">%</span>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-green-600 font-medium valor-difal">
                                                R$ <?php echo $item_calculo ? number_format($item_calculo['valor_difal'], 2, ',', '.') : '0,00'; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-blue-600 font-medium valor-fecoep">
                                                R$ <?php echo $item_calculo ? number_format($item_calculo['valor_fecoep'], 2, ',', '.') : '0,00'; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-purple-600 font-bold valor-total">
                                                R$ <?php echo $item_calculo ? number_format($item_calculo['valor_total_impostos'], 2, ',', '.') : '0,00'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-gray-100">
                                        <tr>
                                            <td colspan="7" class="px-4 py-3 text-right text-sm font-medium text-gray-700">Totais:</td>
                                            <td class="px-4 py-3 text-sm font-bold text-green-600" id="total-difal">
                                                R$ <?php 
                                                $total_difal = 0;
                                                if ($calculo_especifico) {
                                                    foreach ($itens_calculo_especifico as $item) {
                                                        $total_difal += $item['valor_difal'];
                                                    }
                                                }
                                                echo number_format($total_difal, 2, ',', '.'); 
                                                ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-bold text-blue-600" id="total-fecoep">
                                                R$ <?php 
                                                $total_fecoep = 0;
                                                if ($calculo_especifico) {
                                                    foreach ($itens_calculo_especifico as $item) {
                                                        $total_fecoep += $item['valor_fecoep'];
                                                    }
                                                }
                                                echo number_format($total_fecoep, 2, ',', '.'); 
                                                ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-bold text-purple-600" id="total-geral">
                                                R$ <?php echo number_format($total_difal + $total_fecoep, 2, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <?php if (!$modo_visualizacao): ?>
                            <div class="flex space-x-4">
                                <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                                    <i data-feather="save" class="w-4 h-4 mr-2"></i>
                                    <?php echo $calculo_especifico ? 'Atualizar Cálculo' : 'Salvar Cálculo'; ?>
                                </button>
                                <button type="button" onclick="limparCalculos()" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors flex items-center">
                                    <i data-feather="refresh-cw" class="w-4 h-4 mr-2"></i>
                                    Limpar Cálculos
                                </button>
                                <button type="button" onclick="aplicarAliquotasPadrao()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
                                    <i data-feather="refresh-cw" class="w-4 h-4 mr-2"></i>
                                    Restaurar Padrão
                                </button>
                                <!-- Botões para redutor -->
                                <button type="button" onclick="aplicarRedutorZero()" class="px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center text-sm">
                                    <i data-feather="x" class="w-4 h-4 mr-2"></i>
                                    Redutor 0%
                                </button>
                                <button type="button" onclick="aplicarRedutorPadrao()" class="px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center text-sm">
                                    <i data-feather="percent" class="w-4 h-4 mr-2"></i>
                                    Redutor 63,16%
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i data-feather="info" class="w-5 h-5 text-blue-600 mr-2"></i>
                                    <p class="text-sm text-blue-700">Modo de visualização - Para editar, clique no botão "Editar" na lista de cálculos.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>

                            <!-- Dados SEFAZ Salvos (RESTAURADO) -->
                            <?php if (count($dados_sefaz_salvos) > 0): ?>
                            <div class="mt-8 bg-green-50 border border-green-200 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="font-semibold text-green-800 flex items-center">
                                        <i data-feather="database" class="w-4 h-4 mr-2"></i>
                                        Dados SEFAZ Salvos no Banco (<?php echo count($dados_sefaz_salvos); ?> registros)
                                    </h4>
                                    
                                    <!-- Botão para excluir dados -->
                                    <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir todos os dados da SEFAZ salvos para esta nota? Esta ação não pode ser desfeita.');">
                                        <input type="hidden" name="action" value="excluir_dados_sefaz">
                                        <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                                        <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                                        <button type="submit" class="flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm">
                                            <i data-feather="trash-2" class="w-4 h-4 mr-2"></i>
                                            Excluir Dados SEFAZ
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="table-responsive max-h-64 overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-green-100 sticky top-0">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">Item</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">Descrição</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">Documento</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">ICMS</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">Alíq. ICMS</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">FECOEP</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">Alíq. FECOEP</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-green-800 uppercase">Redutor</th></tr>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-green-200">
                                            <?php 
                                            $total_icms = 0;
                                            $total_fecoep = 0;
                                            foreach ($dados_sefaz_salvos as $registro): 
                                                $total_icms += $registro['valor_icms'];
                                                $total_fecoep += $registro['valor_fecoep'];
                                                
                                                // Calcular redutor baseado no valor do campo redutor_base
                                                $redutor_percentual = isset($registro['redutor_base']) ? $registro['redutor_base'] : 0;
                                            ?>
                                            <tr class="hover:bg-green-50">
                                                <td class="px-3 py-2 font-medium"><?php echo $registro['numero_item']; ?></td>
                                                <td class="px-3 py-2" title="<?php echo htmlspecialchars($registro['descricao_produto']); ?>">
                                                    <?php echo htmlspecialchars(substr($registro['descricao_produto'], 0, 30)); ?>
                                                    <?php if (strlen($registro['descricao_produto']) > 30): ?>...<?php endif; ?>
                                                </td>
                                                <td class="px-3 py-2 text-xs"><?php echo $registro['documento_responsavel']; ?></td>
                                                <td class="px-3 py-2 text-green-600 font-medium">R$ <?php echo number_format($registro['valor_icms'], 2, ',', '.'); ?></td>
                                                <td class="px-3 py-2 text-green-500 text-xs"><?php echo number_format($registro['aliquota_icms'] * 1, 2, ',', '.'); ?>%</td>
                                                <td class="px-3 py-2 text-blue-600 font-medium">R$ <?php echo number_format($registro['valor_fecoep'], 2, ',', '.'); ?></td>
                                                <td class="px-3 py-2 text-blue-500 text-xs"><?php echo number_format($registro['aliquota_fecoep'] * 1, 2, ',', '.'); ?>%</td>
                                                <td class="px-3 py-2 text-purple-500 text-xs font-medium">
                                                    <?php echo number_format($redutor_percentual, 2, ',', '.'); ?>%
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="bg-green-100 sticky bottom-0">
                                            <tr>
                                                <td colspan="3" class="px-3 py-2 text-right font-medium text-green-800">Totais:</td>
                                                <td class="px-3 py-2 text-green-700 font-bold">R$ <?php echo number_format($total_icms, 2, ',', '.'); ?></td>
                                                <td class="px-3 py-2 text-green-700">-</td>
                                                <td class="px-3 py-2 text-blue-700 font-bold">R$ <?php echo number_format($total_fecoep, 2, ',', '.'); ?></td>
                                                <td class="px-3 py-2 text-blue-700">-</td>
                                                <td class="px-3 py-2 text-green-700 font-bold">
                                                    Total: R$ <?php echo number_format($total_icms + $total_fecoep, 2, ',', '.'); ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="mt-3 text-xs text-green-600 flex justify-between items-center">
                                    <span>Última atualização: <?php echo date('d/m/Y H:i', strtotime($dados_sefaz_salvos[0]['data_processamento'])); ?></span>
                                    <span><?php echo count($dados_sefaz_salvos); ?> produtos processados</span>
                                </div>

                                <!-- Resumo dos totais -->
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    <div class="bg-white rounded-lg p-3 border border-green-200">
                                        <div class="text-green-700 font-medium">Total ICMS</div>
                                        <div class="text-green-800 font-bold text-lg">R$ <?php echo number_format($total_icms, 2, ',', '.'); ?></div>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-blue-200">
                                        <div class="text-blue-700 font-medium">Total FECOEP</div>
                                        <div class="text-blue-800 font-bold text-lg">R$ <?php echo number_format($total_fecoep, 2, ',', '.'); ?></div>
                                    </div>
                                    <div class="bg-white rounded-lg p-3 border border-purple-200">
                                        <div class="text-purple-700 font-medium">Total Geral</div>
                                        <div class="text-purple-800 font-bold text-lg">R$ <?php echo number_format($total_icms + $total_fecoep, 2, ',', '.'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Cálculos DIFAL Salvos -->
                            <?php if (count($calculos_difal) > 0): ?>
                                <div class="mt-8 bg-white border border-gray-200 rounded-lg p-6">
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                        <i data-feather="calculator" class="w-5 h-5 mr-2 text-blue-600"></i>
                                        Cálculos DIFAL Salvos (Editáveis)
                                    </h4>
                                    
                                    <?php foreach ($calculos_difal as $calculo): 
                                        $itens_calculo = buscarItensCalculoDifal($conexao, $calculo['id']);
                                        $total_difal = 0;
                                        $total_fecoep = 0;
                                        foreach ($itens_calculo as $item) {
                                            $total_difal += $item['valor_difal'];
                                            $total_fecoep += $item['valor_fecoep'];
                                        }
                                    ?>
                                    <div class="mb-6 border border-gray-200 rounded-lg">
                                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                                            <div>
                                                <h5 class="font-semibold text-gray-800"><?php echo htmlspecialchars($calculo['descricao']); ?></h5>
                                                <p class="text-xs text-gray-500">
                                                    Criado em: <?php echo date('d/m/Y H:i', strtotime($calculo['data_calculo'])); ?> | 
                                                    <?php echo $calculo['total_itens']; ?> produtos | 
                                                    Total: R$ <?php echo number_format($calculo['total_impostos'], 2, ',', '.'); ?>
                                                </p>
                                            </div>
                                            <div class="flex space-x-2">
                                                <button type="button" onclick="atualizarCalculo(<?php echo $calculo['id']; ?>)" class="flex items-center px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                                    <i data-feather="save" class="w-3 h-3 mr-1"></i>
                                                    Salvar Alterações
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este cálculo?');">
                                                    <input type="hidden" name="action" value="excluir_calculo_difal">
                                                    <input type="hidden" name="calculo_id" value="<?php echo $calculo['id']; ?>">
                                                    <button type="submit" class="flex items-center px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                                                        <i data-feather="trash-2" class="w-3 h-3 mr-1"></i>
                                                        Excluir
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="table-responsive max-h-64 overflow-y-auto">
                                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead class="bg-gray-100 sticky top-0">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">NCM</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor Produto</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">% DIFAL</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">% FECOEP</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">% Redução</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">DIFAL</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">FECOEP</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200" id="calculo-<?php echo $calculo['id']; ?>">
                                                    <?php foreach ($itens_calculo as $item): ?>
                                                    <tr class="item-calculo" data-calculo-id="<?php echo $calculo['id']; ?>" data-item-id="<?php echo $item['id']; ?>">
                                                        <td class="px-3 py-2"><?php echo $item['numero_item']; ?></td>
                                                        <td class="px-3 py-2" title="<?php echo htmlspecialchars($item['descricao_produto']); ?>">
                                                            <?php echo htmlspecialchars(substr($item['descricao_produto'], 0, 25)); ?>
                                                            <?php if (strlen($item['descricao_produto']) > 25): ?>...<?php endif; ?>
                                                        </td>
                                                        <td class="px-3 py-2"><?php echo $item['ncm']; ?></td>
                                                        <td class="px-3 py-2 valor-produto" data-valor="<?php echo $item['valor_produto']; ?>">
                                                            R$ <?php echo number_format($item['valor_produto'], 2, ',', '.'); ?>
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input type="text" 
                                                                class="w-16 px-1 py-1 border border-gray-300 rounded text-xs aliquota-difal-edit"
                                                                value="<?php echo number_format($item['aliquota_difal'] * 100, 2, ',', ''); ?>"
                                                                onchange="calcularItemEditado(this)">
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input type="text" 
                                                                class="w-16 px-1 py-1 border border-gray-300 rounded text-xs aliquota-fecoep-edit"
                                                                value="<?php echo number_format($item['aliquota_fecoep'] * 100, 4, ',', ''); ?>"
                                                                onchange="calcularItemEditado(this)">
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input type="text" 
                                                                class="w-16 px-1 py-1 border border-gray-300 rounded text-xs aliquota-reducao-edit"
                                                                value="<?php echo number_format($item['aliquota_reducao'] * 100, 2, ',', ''); ?>"
                                                                onchange="calcularItemEditado(this)">
                                                        </td>
                                                        <td class="px-3 py-2 text-green-600 font-medium valor-difal-edit">
                                                            R$ <?php echo number_format($item['valor_difal'], 2, ',', '.'); ?>
                                                        </td>
                                                        <td class="px-3 py-2 text-blue-600 font-medium valor-fecoep-edit">
                                                            R$ <?php echo number_format($item['valor_fecoep'], 2, ',', '.'); ?>
                                                        </td>
                                                        <td class="px-3 py-2 text-purple-600 font-bold valor-total-edit">
                                                            R$ <?php echo number_format($item['valor_total_impostos'], 2, ',', '.'); ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="bg-gray-50 sticky bottom-0">
                                                    <tr>
                                                        <td colspan="7" class="px-3 py-2 text-right font-medium">Totais:</td>
                                                        <td class="px-3 py-2 text-green-600 font-bold total-difal-calculo">
                                                            R$ <?php echo number_format($total_difal, 2, ',', '.'); ?>
                                                        </td>
                                                        <td class="px-3 py-2 text-blue-600 font-bold total-fecoep-calculo">
                                                            R$ <?php echo number_format($total_fecoep, 2, ',', '.'); ?>
                                                        </td>
                                                        <td class="px-3 py-2 text-purple-600 font-bold total-geral-calculo">
                                                            R$ <?php echo number_format($total_difal + $total_fecoep, 2, ',', '.'); ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <!-- Formulários ocultos para atualização -->
                            <?php foreach ($calculos_difal as $calculo): ?>
                            <form id="form-update-<?php echo $calculo['id']; ?>" method="POST" style="display: none;">
                                <input type="hidden" name="action" value="atualizar_calculo_difal">
                                <input type="hidden" name="calculo_id" value="<?php echo $calculo['id']; ?>">
                                <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                                <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                                <input type="hidden" name="itens_data" id="itens-data-<?php echo $calculo['id']; ?>" value="">
                            </form>
                            <?php endforeach; ?>

                            <?php else: ?>
                            <div class="text-center py-8">
                                <i data-feather="alert-circle" class="w-12 h-12 text-gray-400 mx-auto"></i>
                                <p class="mt-4 text-sm text-gray-500">Nenhum produto encontrado para esta nota fiscal.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Aba SEFAZ -->
                        <div id="tab-content-sefaz" class="tab-content">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Importar Dados da SEFAZ</h3>
                            
                            <?php if (isset($msg_sefaz)): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                <?php echo htmlspecialchars($msg_sefaz); ?>
                            </div>
                            <?php endif; ?>

                            <div class="instructions bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                <h4 class="font-semibold text-blue-800 mb-2">Instruções:</h4>
                                <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                                    <li>Acesse o sistema da SEFAZ</li>
                                    <li>Use console.log(document.body.innerText); no console do inspecionar</li>
                                    <li>Exporte os dados de cálculo do DIFAL</li>
                                    <li>Copie e cole os dados completos (incluindo cabeçalho) no campo abaixo</li>
                                    <li>Clique em "Processar Dados"</li>
                                    <li>Os dados serão salvos para comparação com seus cálculos</li>
                                </ol>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                                <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                                
                                <div class="mb-4">
                                    <label for="dados_sefaz" class="block text-sm font-medium text-gray-700 mb-2">
                                        Cole aqui os dados da SEFAZ:
                                    </label>
                                    <textarea id="dados_sefaz" name="dados_sefaz" rows="12" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Cole aqui os dados copiados da SEFAZ..."></textarea>
                                </div>

                                <div class="flex space-x-4">
                                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center">
                                        <i data-feather="check" class="w-4 h-4 mr-2"></i>
                                        Processar e Salvar Dados
                                    </button>
                                    <button type="button" onclick="limparDados()" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors flex items-center">
                                        <i data-feather="trash-2" class="w-4 h-4 mr-2"></i>
                                        Limpar
                                    </button>
                                </div>
                            </form>

                            <!-- Preview da Tabela (será preenchido via JavaScript) -->
                            <div id="tabela-preview" class="mt-8" style="display: none;">
                                <h4 class="font-semibold text-gray-800 mb-4">Preview dos Dados Processados:</h4>
                                <div class="table-responsive">
                                    <table id="tabela-dados" class="min-w-full divide-y divide-gray-200">
                                        <!-- Será preenchido via JavaScript -->
                                    </table>
                                </div>
                            </div>
                        </div>
                        <!-- Aba Comparação -->
                        <div id="tab-content-comparacao" class="tab-content">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Comparação SEFAZ vs Manual</h3>
                            
                            <?php 
                            // Mensagens
                            if (isset($_SESSION['msg_relatorio'])): 
                                echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">' . htmlspecialchars($_SESSION['msg_relatorio']) . '</div>';
                                unset($_SESSION['msg_relatorio']);
                            endif;
                            
                            if (isset($_SESSION['error_relatorio'])): 
                                echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">' . htmlspecialchars($_SESSION['error_relatorio']) . '</div>';
                                unset($_SESSION['error_relatorio']);
                            endif;
                            ?>
                            
                            <?php if (count($dados_sefaz_salvos) > 0 && count($calculos_difal) > 0): ?>
                            
                            <!-- Formulário de Comparação -->
                            <form method="POST" id="form-comparacao">
                                <input type="hidden" name="action" value="gerar_comparacao">
                                <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                                <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                                
                                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="calculo_comparacao" class="block text-sm font-medium text-gray-700 mb-2">
                                            Selecione o Cálculo para Comparar:
                                        </label>
                                        <select id="calculo_comparacao" name="calculo_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                            <?php foreach ($calculos_difal as $calculo): ?>
                                            <option value="<?php echo $calculo['id']; ?>" <?php echo ($calculo_comparacao_id == $calculo['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($calculo['descricao']); ?> 
                                                (<?php echo date('d/m/Y H:i', strtotime($calculo['data_calculo'])); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center">
                                            <i data-feather="git-compare" class="w-4 h-4 mr-2"></i>
                                            Gerar Comparação
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Resultado da Comparação -->
                            <?php if ($comparacao): ?>
                            <div id="resultado-comparacao" class="mt-6">
                                <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <div class="text-green-700 font-medium">Compatíveis</div>
                                        <div class="text-green-800 font-bold text-lg"><?php echo count($comparacao['produtos_compativeis']); ?> produtos</div>
                                    </div>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <div class="text-yellow-700 font-medium">Divergentes</div>
                                        <div class="text-yellow-800 font-bold text-lg"><?php echo count($comparacao['produtos_divergentes']); ?> produtos</div>
                                    </div>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <div class="text-blue-700 font-medium">Só SEFAZ</div>
                                        <div class="text-blue-800 font-bold text-lg"><?php echo count($comparacao['produtos_apenas_sefaz']); ?> produtos</div>
                                    </div>
                                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                        <div class="text-purple-700 font-medium">Só Manual</div>
                                        <div class="text-purple-800 font-bold text-lg"><?php echo count($comparacao['produtos_apenas_manual']); ?> produtos</div>
                                    </div>
                                </div>

                                <div class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <h5 class="font-semibold text-gray-800 mb-3">Totais da Comparação</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 text-sm">
                                        <div>
                                            <div class="text-gray-600">ICMS SEFAZ</div>
                                            <div class="text-green-600 font-bold">R$ <?php echo number_format($comparacao['totais']['sefaz_icms'], 2, ',', '.'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-gray-600">FECOEP SEFAZ</div>
                                            <div class="text-blue-600 font-bold">R$ <?php echo number_format($comparacao['totais']['sefaz_fecoep'], 2, ',', '.'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-gray-600">ICMS Manual</div>
                                            <div class="text-green-600 font-bold">R$ <?php echo number_format($comparacao['totais']['manual_icms'], 2, ',', '.'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-gray-600">FECOEP Manual</div>
                                            <div class="text-blue-600 font-bold">R$ <?php echo number_format($comparacao['totais']['manual_fecoep'], 2, ',', '.'); ?></div>
                                        </div>
                                        <div>
                                            <div class="text-gray-600">Dif. ICMS</div>
                                            <div class="<?php echo ($comparacao['totais']['diferenca_icms'] >= 0) ? 'text-green-600' : 'text-red-600'; ?> font-bold">
                                                R$ <?php echo number_format($comparacao['totais']['diferenca_icms'], 2, ',', '.'); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-gray-600">Dif. FECOEP</div>
                                            <div class="<?php echo ($comparacao['totais']['diferenca_fecoep'] >= 0) ? 'text-green-600' : 'text-red-600'; ?> font-bold">
                                                R$ <?php echo number_format($comparacao['totais']['diferenca_fecoep'], 2, ',', '.'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tabela de Produtos Divergentes (para relatório de contestação) -->
                                <?php if (count($comparacao['produtos_divergentes']) > 0): ?>
                                <div class="mb-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h5 class="font-semibold text-gray-800">Relatório de Contestação - Produtos Divergentes</h5>
                                        <button type="button" onclick="gerarRelatorioContestacao()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center text-sm">
                                            <i data-feather="download" class="w-4 h-4 mr-2"></i>
                                            Salvar Relatório
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="min-w-full divide-y divide-gray-200" id="tabela-contestacao">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Chave Nota</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Competência</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ICMS SEFAZ</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">FECOEP SEFAZ</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ICMS Manual</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">FECOEP Manual</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php 
                                                $produtosDivergentes = array_map(function($p) { return $p['numero_item']; }, $comparacao['produtos_divergentes']);
                                                $produtosDivergentesStr = implode(', ', $produtosDivergentes);
                                                ?>
                                                <tr class="item-contestacao">
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm chave-nota" 
                                                            value="<?php echo htmlspecialchars($nota['chave'] ?? 'NOTA-' . ($nota['numero'] ?? 'N/A')); ?>" readonly>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm competencia" 
                                                            value="<?php echo $competencia; ?>" readonly>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm icms-sefaz" 
                                                            value="<?php echo number_format($comparacao['totais']['sefaz_icms'], 2, '.', ''); ?>" readonly>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm fecoep-sefaz" 
                                                            value="<?php echo number_format($comparacao['totais']['sefaz_fecoep'], 2, '.', ''); ?>" readonly>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm icms-manual" 
                                                            value="<?php echo number_format($comparacao['totais']['manual_icms'], 2, '.', ''); ?>" readonly>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm fecoep-manual" 
                                                            value="<?php echo number_format($comparacao['totais']['manual_fecoep'], 2, '.', ''); ?>" readonly>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <input type="text" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm numero-item" 
                                                            value="6" readonly>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">
                                                        <textarea class="w-full px-2 py-1 border border-gray-300 rounded text-sm descricao-produtos" 
                                                                rows="2" placeholder="Descreva os produtos divergentes...">Produtos <?php echo $produtosDivergentesStr; ?> - [ESCREVA AQUI A JUSTIFICATIVA]</textarea>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <form id="form-relatorio-contestacao" method="POST">
                                        <input type="hidden" name="action" value="salvar_relatorio_contestacao">
                                        <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                                        <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                                        <input type="hidden" name="dados_relatorio" id="dados-relatorio" value="">
                                    </form>
                                </div>
                                <?php endif; ?>

                                <!-- Produtos apenas no manual (para informação) -->
                                <?php if (count($comparacao['produtos_apenas_manual']) > 0): ?>
                                <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <h5 class="font-semibold text-yellow-800 mb-3">Produtos Apenas no Cálculo Manual</h5>
                                    <p class="text-sm text-yellow-700 mb-2">
                                        Estes produtos não foram encontrados nos dados da SEFAZ e não serão incluídos no relatório de contestação:
                                    </p>
                                    <div class="text-sm text-yellow-600">
                                        Itens: <?php echo implode(', ', array_map(function($p) { return $p['numero_item']; }, $comparacao['produtos_apenas_manual'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                            <?php endif; ?>

                            <!-- Relatórios Salvos -->
                            <?php if (count($relatorios_salvos) > 0): ?>
                            <div class="mt-8 bg-white border border-gray-200 rounded-lg p-6">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                    <i data-feather="archive" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Relatórios de Contestação Salvos
                                </h4>
                                
                                <div class="table-responsive">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chave Nota</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Competência</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ICMS SEFAZ</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">FECOEP SEFAZ</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ICMS Manual</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">FECOEP Manual</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição Completa</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Criação</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($relatorios_salvos as $relatorio): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($relatorio['chave_nota']); ?></td>
                                                <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($relatorio['competencia']); ?></td>
                                                <td class="px-4 py-3 text-sm text-green-600 font-medium">R$ <?php echo number_format($relatorio['icms_sefaz'], 2, ',', '.'); ?></td>
                                                <td class="px-4 py-3 text-sm text-blue-600 font-medium">R$ <?php echo number_format($relatorio['fecoep_sefaz'], 2, ',', '.'); ?></td>
                                                <td class="px-4 py-3 text-sm text-green-600 font-medium">R$ <?php echo number_format($relatorio['icms_manual'], 2, ',', '.'); ?></td>
                                                <td class="px-4 py-3 text-sm text-blue-600 font-medium">R$ <?php echo number_format($relatorio['fecoep_manual'], 2, ',', '.'); ?></td>
                                                <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($relatorio['numero_item']); ?></td>
                                                <td class="px-4 py-3 text-sm text-gray-500 max-w-md">
                                                    <!-- Descrição completa com quebra de palavras -->
                                                    <div class="whitespace-pre-wrap break-words max-h-32 overflow-y-auto bg-gray-50 p-2 rounded border">
                                                        <?php echo htmlspecialchars($relatorio['descricao_produtos']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($relatorio['data_criacao'])); ?></td>
                                                <td class="px-4 py-3 text-sm font-medium">
                                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir este relatório?');">
                                                        <input type="hidden" name="action" value="excluir_relatorio">
                                                        <input type="hidden" name="relatorio_id" value="<?php echo $relatorio['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-900 flex items-center" title="Excluir relatório">
                                                            <i data-feather="trash-2" class="w-4 h-4 mr-1"></i>
                                                            Excluir
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3 text-xs text-gray-500">
                                    Total de <?php echo count($relatorios_salvos); ?> relatório(s) salvo(s)
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php else: ?>
                            <div class="text-center py-8">
                                <i data-feather="git-compare" class="w-12 h-12 text-gray-400 mx-auto"></i>
                                <p class="mt-4 text-sm text-gray-500">
                                    <?php if (count($dados_sefaz_salvos) === 0 && count($calculos_difal) === 0): ?>
                                    É necessário ter dados da SEFAZ e cálculos manuais para realizar a comparação.
                                    <?php elseif (count($dados_sefaz_salvos) === 0): ?>
                                    É necessário importar dados da SEFAZ para realizar a comparação.
                                    <?php else: ?>
                                    É necessário ter cálculos manuais salvos para realizar a comparação.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Inicializar Feather Icons
        feather.replace();

        // Função para alternar entre abas
        function switchTab(tabName) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remover classe active de todos os botões
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar conteúdo da aba selecionada
            const tabContent = document.getElementById('tab-content-' + tabName);
            if (tabContent) {
                tabContent.classList.add('active');
            }
            
            const tabButton = document.getElementById('tab-' + tabName);
            if (tabButton) {
                tabButton.classList.add('active');
            }
        }

        // Garantir que a aba cálculo está ativa por padrão, mas outras abas funcionam
        document.addEventListener('DOMContentLoaded', function() {
            // Se veio com action específica, manter na aba cálculo
            if (!<?php echo isset($_GET['action']) ? 'true' : 'false'; ?>) {
                switchTab('calculo');
            }
        });

        // Função para limpar dados do textarea
        function limparDados() {
            document.getElementById('dados_sefaz').value = '';
            document.getElementById('tabela-preview').style.display = 'none';
        }

        // Processamento em tempo real dos dados da SEFAZ (opcional)
        document.getElementById('dados_sefaz').addEventListener('input', function() {
            const dados = this.value.trim();
            if (dados) {
                processarDadosTempoReal(dados);
            } else {
                document.getElementById('tabela-preview').style.display = 'none';
            }
        });

        function processarDadosTempoReal(dados) {
            const tabelaPreview = document.getElementById('tabela-preview');
            const tabelaDados = document.getElementById('tabela-dados');
            
            // Limpar tabela anterior
            tabelaDados.innerHTML = '';
            tabelaPreview.style.display = 'none';

            try {
                const linhas = dados.split('\n');
                if (linhas.length < 2) {
                    return;
                }

                // Processar cabeçalho
                const cabecalho = parseLinha(linhas[0]);
                
                // Processar linhas de dados
                const linhasDados = [];
                let totalICMS = 0;
                let totalFECOEP = 0;

                for (let i = 1; i < linhas.length; i++) {
                    const linha = linhas[i].trim();
                    if (!linha) continue;

                    // Verificar se é linha de total (ignorar)
                    if (linha.includes('Total:') || linha.includes('Valor de imposto calculado na nota:')) {
                        continue;
                    }

                    const dadosLinha = parseLinha(linha);
                    if (dadosLinha.length >= 5) { // Mínimo de colunas esperadas
                        linhasDados.push(dadosLinha);
                        
                        // Calcular totais
                        const icms = extrairNumero(dadosLinha[4]); // ICMS na coluna 4
                        const fecoep = extrairNumero(dadosLinha[5]); // FECOEP na coluna 5
                        
                        if (!isNaN(icms)) totalICMS += icms;
                        if (!isNaN(fecoep)) totalFECOEP += fecoep;
                    }
                }

                if (linhasDados.length === 0) {
                    return;
                }

                // Criar tabela
                // Cabeçalho
                const thead = document.createElement('thead');
                const linhaCabecalho = document.createElement('tr');
                cabecalho.forEach(coluna => {
                    const th = document.createElement('th');
                    th.className = 'px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase bg-gray-50';
                    th.textContent = coluna;
                    linhaCabecalho.appendChild(th);
                });
                thead.appendChild(linhaCabecalho);
                tabelaDados.appendChild(thead);

                // Corpo da tabela
                const tbody = document.createElement('tbody');
                linhasDados.forEach((dadosLinha, index) => {
                    const tr = document.createElement('tr');
                    tr.className = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                    
                    dadosLinha.forEach((celula, cellIndex) => {
                        const td = document.createElement('td');
                        td.className = 'px-4 py-2 text-sm text-gray-700 border-b border-gray-200';
                        
                        // Formatar valores monetários
                        if (cellIndex === 4 || cellIndex === 5) { // Colunas ICMS e FECOEP
                            const valor = extrairNumero(celula);
                            if (!isNaN(valor) && valor !== 0) {
                                td.textContent = `R$ ${valor.toFixed(2).replace('.', ',')}`;
                                td.className += ' font-medium';
                                
                                if (cellIndex === 4) {
                                    td.className += ' text-green-600';
                                } else {
                                    td.className += ' text-blue-600';
                                }
                            } else {
                                td.textContent = celula || '-';
                            }
                        } else {
                            td.textContent = celula || '-';
                        }
                        
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
                tabelaDados.appendChild(tbody);

                // Adicionar linha de totais
                const tfoot = document.createElement('tfoot');
                const linhaTotal = document.createElement('tr');
                linhaTotal.className = 'bg-gray-100 font-semibold';
                
                // Célula vazia para as primeiras colunas
                const celulaVazia = document.createElement('td');
                celulaVazia.colSpan = 4;
                celulaVazia.className = 'px-4 py-3 text-sm text-gray-800 text-right';
                celulaVazia.textContent = 'Totais:';
                linhaTotal.appendChild(celulaVazia);
                
                // Total ICMS
                const celulaTotalICMS = document.createElement('td');
                celulaTotalICMS.className = 'px-4 py-3 text-sm text-green-600 font-bold';
                celulaTotalICMS.textContent = `R$ ${totalICMS.toFixed(2).replace('.', ',')}`;
                linhaTotal.appendChild(celulaTotalICMS);
                
                // Total FECOEP
                const celulaTotalFECOEP = document.createElement('td');
                celulaTotalFECOEP.className = 'px-4 py-3 text-sm text-blue-600 font-bold';
                celulaTotalFECOEP.textContent = `R$ ${totalFECOEP.toFixed(2).replace('.', ',')}`;
                linhaTotal.appendChild(celulaTotalFECOEP);
                
                // Total Geral
                const totalGeral = totalICMS + totalFECOEP;
                const celulaTotalGeral = document.createElement('td');
                celulaTotalGeral.colSpan = cabecalho.length - 6; // Preenche as colunas restantes
                celulaTotalGeral.className = 'px-4 py-3 text-sm text-gray-800 font-bold';
                celulaTotalGeral.textContent = `Total Geral: R$ ${totalGeral.toFixed(2).replace('.', ',')}`;
                linhaTotal.appendChild(celulaTotalGeral);
                
                tfoot.appendChild(linhaTotal);
                tabelaDados.appendChild(tfoot);

                // Mostrar preview
                tabelaPreview.style.display = 'block';

                // Adicionar resumo
                let resumoExistente = document.getElementById('resumo-processamento');
                if (!resumoExistente) {
                    resumoExistente = document.createElement('div');
                    resumoExistente.id = 'resumo-processamento';
                    resumoExistente.className = 'bg-green-50 border border-green-200 rounded-lg p-4 mt-4';
                    tabelaPreview.appendChild(resumoExistente);
                }
                
                resumoExistente.innerHTML = `
                    <h5 class="font-semibold text-green-800 mb-2">Resumo do Processamento:</h5>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-green-700">
                        <div>
                            <span class="font-medium">Registros processados:</span> ${linhasDados.length}
                        </div>
                        <div>
                            <span class="font-medium">Total ICMS:</span> R$ ${totalICMS.toFixed(2).replace('.', ',')}
                        </div>
                        <div>
                            <span class="font-medium">Total FECOEP:</span> R$ ${totalFECOEP.toFixed(2).replace('.', ',')}
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-green-600">
                        Clique em "Processar Dados" para salvar no banco de dados.
                    </div>
                `;

            } catch (error) {
                console.error('Erro ao processar dados:', error);
                
                // Mostrar mensagem de erro
                tabelaDados.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                        <i data-feather="alert-triangle" class="w-8 h-8 text-red-400 mx-auto mb-2"></i>
                        <p class="text-red-700 text-sm">Erro ao processar os dados. Verifique o formato.</p>
                        <p class="text-red-600 text-xs mt-1">${error.message}</p>
                    </div>
                `;
                tabelaPreview.style.display = 'block';
                feather.replace();
            }
        }

        // Funções auxiliares (as mesmas do HTML original)
        function parseLinha(linha) {
            // Dividir por tabulação, mantendo células vazias
            return linha.split('\t').map(celula => celula.trim());
        }

        function extrairNumero(valor) {
            if (!valor) return 0;
            // Remover "R$", espaços e converter vírgula para ponto
            const limpo = valor.replace('R$', '').replace(/\s/g, '').replace(/\./g, '').replace(',', '.').trim();
            const numero = parseFloat(limpo);
            return isNaN(numero) ? 0 : numero;
        }

        // Adicionar também a função de exemplo para preencher o textarea
        function preencherDadosExemplo() {
            const dadosExemplo = `Número	Descrição	Num. Doc. Responsável	Tipo de Imposto	ICMS	FECOEP	Alíquota ICMS	Alíquota FECOEP	MVA Valor	Redutor	Redutor Crédito	Segmento	Pauta	NCM	CEST
        2	ALHO DESCASCADO IMPORTADO	44.658.012/0001-83	DIFAL	R$ 6,65	R$ 0,95	0.19	0.01		0					
        3	ARROZ BRANCO TIPO 1	44.658.012/0001-83	DIFAL	R$ 7,45	R$ 0,00	0.19	0		0.6316	RED_ALIMENTOS					
        4	FEIJÃO CARIOCA	44.658.012/0001-83	DIFAL	R$ 8,20	R$ 0,30	0.19	0.01		0					
        5	AÇÚCAR CRISTAL	44.658.012/0001-83	DIFAL	R$ 5,80	R$ 0,15	0.19	0.005		0					`;
            
            document.getElementById('dados_sefaz').value = dadosExemplo;
            
            // Processar automaticamente o exemplo
            setTimeout(() => {
                processarDadosTempoReal(dadosExemplo);
            }, 100);
        }

        // Adicionar botão de exemplo ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar botão de exemplo na aba SEFAZ
            const abaSefaz = document.getElementById('tab-content-sefaz');
            const form = abaSefaz.querySelector('form');
            
            const botaoExemplo = document.createElement('button');
            botaoExemplo.type = 'button';
            botaoExemplo.className = 'px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center text-sm';
            botaoExemplo.innerHTML = '<i data-feather="file-text" class="w-4 h-4 mr-2"></i> Carregar Exemplo';
            botaoExemplo.onclick = preencherDadosExemplo;
            
            form.querySelector('.flex.space-x-4').appendChild(botaoExemplo);
            feather.replace();
        });

        // Função para calcular impostos em tempo real (ATUALIZADA COM REDUTOR)
        function calcularImpostos(input) {
            const linha = input.closest('tr');
            const valorProdutoTexto = linha.querySelector('.valor-produto').textContent;
            
            // CORREÇÃO: Converter valor do produto corretamente
            const valorProduto = parseFloat(valorProdutoTexto.replace(/[R$\s]/g, '').replace('.', '').replace(',', '.'));
            
            const aliquotaDifal = parseFloat(input.classList.contains('aliquota-difal') ? input.value.replace(',', '.') : linha.querySelector('.aliquota-difal').value.replace(',', '.')) || 0;
            const aliquotaFecoep = parseFloat(input.classList.contains('aliquota-fecoep') ? input.value.replace(',', '.') : linha.querySelector('.aliquota-fecoep').value.replace(',', '.')) || 0;
            const aliquotaReducao = parseFloat(input.classList.contains('aliquota-reducao') ? input.value.replace(',', '.') : linha.querySelector('.aliquota-reducao').value.replace(',', '.')) || 0;
            
            // Calcular DIFAL bruto
            const valorDifalBruto = valorProduto * (aliquotaDifal / 100);
            
            // Aplicar redução
            const valorReducao = valorDifalBruto * (aliquotaReducao / 100);
            const valorDifal = valorDifalBruto - valorReducao;
            
            const valorFecoep = valorProduto * (aliquotaFecoep / 100);
            const valorTotal = valorDifal + valorFecoep;
            
            linha.querySelector('.valor-difal').textContent = formatarMoeda(valorDifal);
            linha.querySelector('.valor-fecoep').textContent = formatarMoeda(valorFecoep);
            linha.querySelector('.valor-total').textContent = formatarMoeda(valorTotal);
            
            calcularTotais();
        }

        // Função para calcular totais
        function calcularTotais() {
            let totalDifal = 0;
            let totalFecoep = 0;
            
            document.querySelectorAll('.produto-calculo').forEach(linha => {
                const valorDifalTexto = linha.querySelector('.valor-difal').textContent;
                const valorFecoepTexto = linha.querySelector('.valor-fecoep').textContent;
                
                // CORREÇÃO: Converter valores corretamente
                const valorDifal = parseFloat(valorDifalTexto.replace(/[R$\s]/g, '').replace('.', '').replace(',', '.'));
                const valorFecoep = parseFloat(valorFecoepTexto.replace(/[R$\s]/g, '').replace('.', '').replace(',', '.'));
                
                if (!isNaN(valorDifal)) totalDifal += valorDifal;
                if (!isNaN(valorFecoep)) totalFecoep += valorFecoep;
            });
            
            document.getElementById('total-difal').textContent = formatarMoeda(totalDifal);
            document.getElementById('total-fecoep').textContent = formatarMoeda(totalFecoep);
            document.getElementById('total-geral').textContent = formatarMoeda(totalDifal + totalFecoep);
        }

        // Função para formatar moeda (CORRIGIDA)
        function formatarMoeda(valor) {
            return 'R$ ' + valor.toLocaleString('pt-BR', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
        }

        // Função para limpar cálculos
        function limparCalculos() {
            document.querySelectorAll('.aliquota-difal, .aliquota-fecoep').forEach(input => {
                input.value = '';
            });
            document.querySelectorAll('.valor-difal, .valor-fecoep, .valor-total').forEach(celula => {
                celula.textContent = 'R$ 0,00';
            });
            calcularTotais();
        }

        // Função para aplicar alíquotas padrão
        function aplicarAliquotasPadrao() {
            document.querySelectorAll('.aliquota-difal').forEach(input => {
                input.value = '7,00';
                calcularImpostos(input);
            });
            document.querySelectorAll('.aliquota-fecoep').forEach(input => {
                input.value = '1,00';
                calcularImpostos(input);
            });
        }

        // Validar formulário antes de enviar
        document.getElementById('form-calculo-difal').addEventListener('submit', function(e) {
            let temAliquota = false;
            document.querySelectorAll('.aliquota-difal, .aliquota-fecoep').forEach(input => {
                if (input.value.trim() !== '') {
                    temAliquota = true;
                }
            });
            
            if (!temAliquota) {
                e.preventDefault();
                alert('Por favor, informe pelo menos uma alíquota para calcular os impostos.');
            }
        });


        // Função para calcular automaticamente ao carregar a página
        function calcularAoCarregar() {
            document.querySelectorAll('.aliquota-difal, .aliquota-fecoep').forEach(input => {
                if (input.value) {
                    calcularImpostos(input);
                }
            });
        }

        // Chamar a função quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(calcularAoCarregar, 100);
        });



        // Função para calcular itens editados nos cálculos salvos (CORRIGIDA COM REDUTOR)
        function calcularItemEditado(input) {
            const linha = input.closest('tr');
            const valorProduto = parseFloat(linha.querySelector('.valor-produto').dataset.valor);
            
            const aliquotaDifalInput = linha.querySelector('.aliquota-difal-edit');
            const aliquotaFecoepInput = linha.querySelector('.aliquota-fecoep-edit');
            const aliquotaReducaoInput = linha.querySelector('.aliquota-reducao-edit');
            
            const aliquotaDifal = parseFloat(aliquotaDifalInput.value.replace(',', '.')) || 0;
            const aliquotaFecoep = parseFloat(aliquotaFecoepInput.value.replace(',', '.')) || 0;
            const aliquotaReducao = parseFloat(aliquotaReducaoInput.value.replace(',', '.')) || 0;
            
            // Calcular DIFAL bruto
            const valorDifalBruto = valorProduto * (aliquotaDifal / 100);
            
            // Aplicar redução ao DIFAL
            const valorReducao = valorDifalBruto * (aliquotaReducao / 100);
            const valorDifal = valorDifalBruto - valorReducao;
            
            // FECOEP não sofre redução
            const valorFecoep = valorProduto * (aliquotaFecoep / 100);
            const valorTotal = valorDifal + valorFecoep;
            
            linha.querySelector('.valor-difal-edit').textContent = formatarMoeda(valorDifal);
            linha.querySelector('.valor-fecoep-edit').textContent = formatarMoeda(valorFecoep);
            linha.querySelector('.valor-total-edit').textContent = formatarMoeda(valorTotal);
            
            // Atualizar totais do cálculo
            const tbodyId = linha.closest('tbody').id;
            if (tbodyId) {
                atualizarTotaisCalculo(tbodyId);
            }
        }

        // Função para atualizar totais de um cálculo específico
        function atualizarTotaisCalculo(tbodyId) {
            const tbody = document.getElementById(tbodyId);
            let totalDifal = 0;
            let totalFecoep = 0;
            
            tbody.querySelectorAll('.item-calculo').forEach(linha => {
                const valorDifalTexto = linha.querySelector('.valor-difal-edit').textContent;
                const valorFecoepTexto = linha.querySelector('.valor-fecoep-edit').textContent;
                
                const valorDifal = parseFloat(valorDifalTexto.replace(/[R$\s]/g, '').replace('.', '').replace(',', '.'));
                const valorFecoep = parseFloat(valorFecoepTexto.replace(/[R$\s]/g, '').replace('.', '').replace(',', '.'));
                
                if (!isNaN(valorDifal)) totalDifal += valorDifal;
                if (!isNaN(valorFecoep)) totalFecoep += valorFecoep;
            });
            
            const tfoot = tbody.closest('table').querySelector('tfoot');
            tfoot.querySelector('.total-difal-calculo').textContent = formatarMoeda(totalDifal);
            tfoot.querySelector('.total-fecoep-calculo').textContent = formatarMoeda(totalFecoep);
            tfoot.querySelector('.total-geral-calculo').textContent = formatarMoeda(totalDifal + totalFecoep);
        }

        // Função para atualizar cálculo no banco de dados (CORRIGIDA)
        function atualizarCalculo(calculoId) {
            const tbody = document.getElementById('calculo-' + calculoId);
            const itens = [];
            
            tbody.querySelectorAll('.item-calculo').forEach(linha => {
                const itemId = linha.dataset.itemId;
                const aliquotaDifalInput = linha.querySelector('.aliquota-difal-edit');
                const aliquotaFecoepInput = linha.querySelector('.aliquota-fecoep-edit');
                const aliquotaReducaoInput = linha.querySelector('.aliquota-reducao-edit');
                
                // Obter valores dos inputs
                const aliquotaDifal = parseFloat(aliquotaDifalInput.value.replace(',', '.')) / 100;
                const aliquotaFecoep = parseFloat(aliquotaFecoepInput.value.replace(',', '.')) / 100;
                const aliquotaReducao = parseFloat(aliquotaReducaoInput.value.replace(',', '.')) / 100;
                
                // Recalcular valores baseados nas novas alíquotas
                const valorProduto = parseFloat(linha.querySelector('.valor-produto').dataset.valor);
                
                // Calcular DIFAL com redução
                const valorDifalBruto = valorProduto * aliquotaDifal;
                const valorReducao = valorDifalBruto * aliquotaReducao;
                const valorDifal = valorDifalBruto - valorReducao;
                
                const valorFecoep = valorProduto * aliquotaFecoep;
                const valorTotal = valorDifal + valorFecoep;
                
                itens.push({
                    item_id: parseInt(itemId),
                    aliquota_difal: aliquotaDifal,
                    aliquota_fecoep: aliquotaFecoep,
                    aliquota_reducao: aliquotaReducao,
                    valor_difal: valorDifal,
                    valor_fecoep: valorFecoep,
                    valor_total_impostos: valorTotal
                });
            });
            
            if (confirm('Deseja salvar as alterações deste cálculo?')) {
                // Preencher o formulário oculto com os dados
                document.getElementById('itens-data-' + calculoId).value = JSON.stringify(itens);
                
                // Enviar o formulário
                document.getElementById('form-update-' + calculoId).submit();
            }
        }

        // Função para inicializar eventos de edição nos cálculos salvos
        function inicializarEdicoesCalculos() {
            document.querySelectorAll('.aliquota-difal-edit, .aliquota-fecoep-edit, .aliquota-reducao-edit').forEach(input => {
                input.addEventListener('change', function() {
                    calcularItemEditado(this);
                });
                
                // Calcular valores iniciais
                if (input.value) {
                    calcularItemEditado(input);
                }
            });
        }

        // Inicializar quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                calcularAoCarregar();
                inicializarEdicoesCalculos();
            }, 100);
        });




        // Função para gerar comparação via AJAX
        function gerarComparacao() {
            const calculoId = document.getElementById('calculo_comparacao').value;
            const formData = new FormData();
            formData.append('action', 'gerar_comparacao');
            formData.append('calculo_id', calculoId);
            formData.append('nota_id', <?php echo $nota_id; ?>);
            formData.append('competencia', '<?php echo $competencia; ?>');

            fetch('selecionar-produtos-difal.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Recarregar a página para mostrar resultados
                window.location.href = 'selecionar-produtos-difal.php?nota_id=<?php echo $nota_id; ?>&competencia=<?php echo $competencia; ?>&tab=comparacao';
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao gerar comparação.');
            });
        }

        // Função para exibir resultados da comparação
        function exibirResultadoComparacao(comparacao) {
            const container = document.getElementById('resultado-comparacao');
            
            let html = `
                <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="text-green-700 font-medium">Compatíveis</div>
                        <div class="text-green-800 font-bold text-lg">${comparacao.produtos_compativeis.length} produtos</div>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="text-yellow-700 font-medium">Divergentes</div>
                        <div class="text-yellow-800 font-bold text-lg">${comparacao.produtos_divergentes.length} produtos</div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="text-blue-700 font-medium">Só SEFAZ</div>
                        <div class="text-blue-800 font-bold text-lg">${comparacao.produtos_apenas_sefaz.length} produtos</div>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="text-purple-700 font-medium">Só Manual</div>
                        <div class="text-purple-800 font-bold text-lg">${comparacao.produtos_apenas_manual.length} produtos</div>
                    </div>
                </div>

                <div class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h5 class="font-semibold text-gray-800 mb-3">Totais da Comparação</h5>
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 text-sm">
                        <div>
                            <div class="text-gray-600">ICMS SEFAZ</div>
                            <div class="text-green-600 font-bold">R$ ${comparacao.totais.sefaz_icms.toFixed(2).replace('.', ',')}</div>
                        </div>
                        <div>
                            <div class="text-gray-600">FECOEP SEFAZ</div>
                            <div class="text-blue-600 font-bold">R$ ${comparacao.totais.sefaz_fecoep.toFixed(2).replace('.', ',')}</div>
                        </div>
                        <div>
                            <div class="text-gray-600">ICMS Manual</div>
                            <div class="text-green-600 font-bold">R$ ${comparacao.totais.manual_icms.toFixed(2).replace('.', ',')}</div>
                        </div>
                        <div>
                            <div class="text-gray-600">FECOEP Manual</div>
                            <div class="text-blue-600 font-bold">R$ ${comparacao.totais.manual_fecoep.toFixed(2).replace('.', ',')}</div>
                        </div>
                        <div>
                            <div class="text-gray-600">Dif. ICMS</div>
                            <div class="${comparacao.totais.diferenca_icms >= 0 ? 'text-green-600' : 'text-red-600'} font-bold">
                                R$ ${comparacao.totais.diferenca_icms.toFixed(2).replace('.', ',')}
                            </div>
                        </div>
                        <div>
                            <div class="text-gray-600">Dif. FECOEP</div>
                            <div class="${comparacao.totais.diferenca_fecoep >= 0 ? 'text-green-600' : 'text-red-600'} font-bold">
                                R$ ${comparacao.totais.diferenca_fecoep.toFixed(2).replace('.', ',')}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Tabela de Produtos Divergentes (para relatório de contestação)
            if (comparacao.produtos_divergentes.length > 0) {
                html += `
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="font-semibold text-gray-800">Relatório de Contestação - Produtos Divergentes</h5>
                            <button type="button" onclick="gerarRelatorioContestacao()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center text-sm">
                                <i data-feather="download" class="w-4 h-4 mr-2"></i>
                                Gerar Relatório
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="min-w-full divide-y divide-gray-200" id="tabela-contestacao">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Chave Nota</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Competência</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ICMS SEFAZ</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">FECOEP SEFAZ</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ICMS Manual</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">FECOEP Manual</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N°</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                `;

                comparacao.produtos_divergentes.forEach((produto, index) => {
                    const produtosDivergentes = comparacao.produtos_divergentes.map(p => p.numero_item).join(', ');
                    
                    html += `
                        <tr class="item-contestacao">
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm chave-nota" 
                                    value="<?php echo htmlspecialchars($nota['chave'] ?? 'NOTA-' . ($nota['numero'] ?? 'N/A')); ?>" readonly>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm competencia" 
                                    value="<?php echo $competencia; ?>" readonly>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm icms-sefaz" 
                                    value="${produto.sefaz_icms.toFixed(2)}" readonly>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm fecoep-sefaz" 
                                    value="${produto.sefaz_fecoep.toFixed(2)}" readonly>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm icms-manual" 
                                    value="${produto.manual_icms.toFixed(2)}" readonly>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <input type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm fecoep-manual" 
                                    value="${produto.manual_fecoep.toFixed(2)}" readonly>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <input type="text" class="w-16 px-2 py-1 border border-gray-300 rounded text-sm numero-item" 
                                    value="6" readonly>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                <textarea class="w-full px-2 py-1 border border-gray-300 rounded text-sm descricao-produtos" 
                                        rows="2" placeholder="Descreva os produtos divergentes...">Produtos ${produtosDivergentes} - [ESCREVA AQUI A JUSTIFICATIVA]</textarea>
                            </td>
                        </tr>
                    `;
                });

                html += `
                                </tbody>
                            </table>
                        </div>
                        
                        <form id="form-relatorio-contestacao" method="POST" style="display: none;">
                            <input type="hidden" name="action" value="salvar_relatorio_contestacao">
                            <input type="hidden" name="nota_id" value="<?php echo $nota_id; ?>">
                            <input type="hidden" name="competencia" value="<?php echo $competencia; ?>">
                            <input type="hidden" name="dados_relatorio" id="dados-relatorio" value="">
                        </form>
                    </div>
                `;
            }

            // Produtos apenas no manual (para informação)
            if (comparacao.produtos_apenas_manual.length > 0) {
                html += `
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <h5 class="font-semibold text-yellow-800 mb-3">Produtos Apenas no Cálculo Manual</h5>
                        <p class="text-sm text-yellow-700 mb-2">
                            Estes produtos não foram encontrados nos dados da SEFAZ e não serão incluídos no relatório de contestação:
                        </p>
                        <div class="text-sm text-yellow-600">
                            Itens: ${comparacao.produtos_apenas_manual.map(p => p.numero_item).join(', ')}
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
            container.style.display = 'block';
            feather.replace();
        }

        // Função para gerar relatório de contestação
        function gerarRelatorioContestacao() {
            const dadosRelatorio = [];
            
            document.querySelectorAll('.item-contestacao').forEach(linha => {
                const dados = {
                    chave_nota: linha.querySelector('.chave-nota').value,
                    competencia: linha.querySelector('.competencia').value,
                    icms_sefaz: parseFloat(linha.querySelector('.icms-sefaz').value),
                    fecoep_sefaz: parseFloat(linha.querySelector('.fecoep-sefaz').value),
                    icms_manual: parseFloat(linha.querySelector('.icms-manual').value),
                    fecoep_manual: parseFloat(linha.querySelector('.fecoep-manual').value),
                    numero_item: linha.querySelector('.numero-item').value,
                    descricao_produtos: linha.querySelector('.descricao-produtos').value,
                    observacoes: 'Relatório gerado automaticamente'
                };
                
                dadosRelatorio.push(dados);
            });
            
            if (confirm('Deseja salvar o relatório de contestação?')) {
                document.getElementById('dados-relatorio').value = JSON.stringify(dadosRelatorio);
                document.getElementById('form-relatorio-contestacao').submit();
            }
        }

        // Verificar parâmetro de URL para abrir aba correta
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam === 'comparacao') {
                switchTab('comparacao');
            }
        });


        // Função para aplicar redutor 0%
        function aplicarRedutorZero() {
            document.querySelectorAll('.aliquota-reducao').forEach(input => {
                input.value = '0,00';
                calcularImpostos(input);
            });
        }

        // Função para aplicar redutor padrão 63,16%
        function aplicarRedutorPadrao() {
            document.querySelectorAll('.aliquota-reducao').forEach(input => {
                input.value = '63,16';
                calcularImpostos(input);
            });
        }


        // Função para inicializar os cálculos quando a página carrega
        function inicializarCalculos() {
            // Se há um cálculo específico carregado, calcular todos os impostos
            if (<?php echo $calculo_especifico ? 'true' : 'false'; ?>) {
                document.querySelectorAll('.aliquota-difal, .aliquota-fecoep, .aliquota-reducao').forEach(input => {
                    if (input.value) {
                        calcularImpostos(input);
                    }
                });
            } else {
                // Se não há cálculo específico, calcular com valores padrão
                calcularAoCarregar();
            }
        }

        // Atualizar a função calcularAoCarregar para forçar o cálculo
        function calcularAoCarregar() {
            document.querySelectorAll('.aliquota-difal, .aliquota-fecoep, .aliquota-reducao').forEach(input => {
                if (input.value) {
                    calcularImpostos(input);
                }
            });
        }

        // Chamar a inicialização quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(inicializarCalculos, 100);
        });
    </script>
</body>
</html>