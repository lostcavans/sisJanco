<?php
// Configurações de conexão com o banco de dados
$host = 'mysql.jancoassessoriacontabil.com.br';
$usuario = 'jancoasses_add1';
$senha = 'g283116';
$banco = 'jancoassessori';

// Estabelecer conexão
$conexao = new mysqli($host, $usuario, $senha, $banco);

// Verificar conexão
if ($conexao->connect_error) {
    die("Erro de conexão: " . $conexao->connect_error);
}

// Definir charset para utf8
$conexao->set_charset("utf8");

// Função para verificar se um produto é da cesta básica
function isCestaBasica($descricao, $ncm) {
    $descricao = strtolower(trim($descricao));
    $ncm = trim($ncm);
    
    // Produtos da cesta básica conforme decreto
    $produtos_cesta = [
        'feijão' => ['ncm' => [], 'embalagem' => true],
        'farinha de mandioca' => ['ncm' => [], 'isento' => true],
        'goma de mandioca' => ['ncm' => [], 'embalagem' => false],
        'massa de mandioca' => ['ncm' => [], 'embalagem' => false],
        'charque' => ['ncm' => [], 'embalagem' => false],
        'fubá de milho' => ['ncm' => [], 'embalagem' => false],
        'leite em pó' => ['ncm' => [], 'embalagem' => true],
        'sal de cozinha' => ['ncm' => [], 'embalagem' => false],
        'sabão em tabletes' => ['ncm' => [], 'embalagem' => true],
        'sardinha em lata' => ['ncm' => [], 'embalagem' => false],
        'batata inglesa' => ['ncm' => [], 'embalagem' => false],
        'pó para preparo de bebida láctea' => ['ncm' => [], 'embalagem' => true],
        'pescado' => ['ncm' => ['0302', '0303', '0304'], 'embalagem' => false]
    ];
    
    // Verificar por descrição
    foreach ($produtos_cesta as $produto => $info) {
        if (strpos($descricao, strtolower($produto)) !== false) {
            return $info;
        }
    }
    
    // Verificar por NCM (especialmente para pescado)
    if (!empty($ncm)) {
        foreach ($produtos_cesta as $produto => $info) {
            if (!empty($info['ncm'])) {
                foreach ($info['ncm'] as $ncm_base) {
                    if (strpos($ncm, $ncm_base) === 0) {
                        return $info;
                    }
                }
            }
        }
    }
    
    return false;
}

// Função para calcular carga tributária da cesta básica
function calcularCargaTributariaCesta($produto, $valor_unitario, $quantidade, $uf_origem) {
    $carga_tributaria = 0;
    $info_cesta = isCestaBasica($produto['descricao'], $produto['ncm'] ?? '');
    
    if (!$info_cesta) {
        return 0; // Não é cesta básica
    }
    
    // Verificar se é isento
    if (isset($info_cesta['isento']) && $info_cesta['isento']) {
        return 0;
    }
    
    $produto_desc = strtolower($produto['descricao']);
    
    // FEIJÃO - regras específicas
    if (strpos($produto_desc, 'feijão') !== false) {
        // Verificar embalagem (assumindo que a descrição contém informação de peso)
        $embalagem_ate_5kg = false;
        if (preg_match('/(\d+)\s*(kg|g|gr|gramas|quilos)/i', $produto_desc, $matches)) {
            $peso = floatval($matches[1]);
            $unidade = strtolower($matches[2]);
            
            if ($unidade == 'g' || $unidade == 'gr' || $unidade == 'gramas') {
                $peso = $peso / 1000; // converter para kg
            }
            
            $embalagem_ate_5kg = ($peso <= 5);
        }
        
        if ($embalagem_ate_5kg) {
            // Regiões Norte, Nordeste, Centro-Oeste e ES: 5%
            // Regiões Sul e Sudeste (exceto ES): 10%
            $regioes_5pct = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'PA', 'PB', 'PI', 'RN', 'RO', 'RR', 'SE', 'TO'];
            $carga_tributaria = in_array($uf_origem, $regioes_5pct) ? 5 : 10;
        } else {
            // Embalagem acima de 5kg: 2.5%
            $carga_tributaria = 2.5;
        }
    }
    // PESCADO - regras específicas
    elseif (strpos($produto_desc, 'pescado') !== false || 
            (isset($info_cesta['ncm']) && in_array(substr($produto['ncm'] ?? '', 0, 4), $info_cesta['ncm']))) {
        // Verificar se é estabelecimento industrial credenciado (implementação simplificada)
        $is_industrial_credenciado = false; // Isso precisaria ser verificado no banco de dados
        
        if ($is_industrial_credenciado) {
            $carga_tributaria = 2.5;
        } else {
            $carga_tributaria = 4;
        }
    }
    // DEMAIS PRODUTOS - 2.5%
    else {
        $carga_tributaria = 2.5;
    }
    
    return $carga_tributaria;
}