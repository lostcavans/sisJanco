<?php
session_start();
include("config.php");

function verificarAutenticacao() {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) return false;
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_username'])) return false;
    if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso'] > 1800)) return false;
    $_SESSION['ultimo_acesso'] = time();
    return true;
}

if (!verificarAutenticacao()) {
    header("Location: index.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$action = $_GET['action'] ?? '';
$calculo_id = $_GET['id'] ?? 0;
$competencia = $_GET['competencia'] ?? date('Y-m');

if ($action === 'visualizar' && $calculo_id) {

    // Buscar cálculo da cesta básica
    $query = "
        SELECT cc.*, gc.descricao as grupo_descricao, gc.informacoes_adicionais,
            n.numero as nota_numero, n.chave_acesso, n.data_emissao,
            e.razao_social as emitente, e.cnpj as emitente_cnpj
        FROM calculos_cesta_basica cc
        LEFT JOIN grupos_calculo_cesta gc ON cc.grupo_id = gc.id
        LEFT JOIN nfe n ON gc.nota_fiscal_id = n.id
        LEFT JOIN empresas e ON n.emitente_cnpj = e.cnpj
        WHERE cc.id = ? AND cc.usuario_id = ?
    ";
    
    if ($stmt = $conexao->prepare($query)) {
        $stmt->bind_param("ii", $calculo_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $calculo = $result->fetch_assoc();
        $stmt->close();
        
        if (!$calculo) {
            header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Cálculo não encontrado");
            exit;
        }
        
        // Buscar produtos do cálculo
        $produtos_calculo = [];
        $query_produtos = "
            SELECT ccp.*, ni.* 
            FROM cesta_calculo_produtos ccp
            LEFT JOIN nfe_itens ni ON ccp.produto_id = ni.id
            WHERE ccp.calculo_cesta_id = ?
            ORDER BY ni.numero_item
        ";
        
        if ($stmt_produtos = $conexao->prepare($query_produtos)) {
            $stmt_produtos->bind_param("i", $calculo_id);
            $stmt_produtos->execute();
            $result_produtos = $stmt_produtos->get_result();
            $produtos_calculo = $result_produtos->fetch_all(MYSQLI_ASSOC);
            $stmt_produtos->close();
        }
        
        // Exibir página de visualização
        echo '<!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Resultado do Cálculo - Cesta Básica</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
        </head>
        <body class="bg-gray-50">
            <div class="container mx-auto p-6">
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">Cálculo de Cesta Básica</h1>
                        <a href="fronteira-fiscal.php?competencia=' . $competencia . '" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Voltar
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-gray-50 p-4 rounded-md">
                            <h2 class="text-lg font-semibold mb-3">Informações do Cálculo</h2>
                            <p><strong>Descrição:</strong> ' . htmlspecialchars($calculo['descricao']) . '</p>
                            <p><strong>Nota Fiscal:</strong> ' . htmlspecialchars($calculo['nota_numero'] ?? 'N/A') . '</p>
                            <p><strong>Região Fornecedor:</strong> ' . ($calculo['regiao_fornecedor'] == 'sul_sudeste' ? 'Sul/Sudeste' : 'Norte/Nordeste/C-O/ES') . '</p>
                            <p><strong>Data do Cálculo:</strong> ' . date('d/m/Y H:i', strtotime($calculo['data_calculo'])) . '</p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-md">
                            <h2 class="text-lg font-semibold mb-3">Resultados</h2>
                            <p><strong>Valor Total dos Produtos:</strong> R$ ' . number_format($calculo['valor_total_produtos'], 2, ',', '.') . '</p>
                            <p><strong>ICMS Cesta Básica:</strong> R$ ' . number_format($calculo['valor_total_icms'], 2, ',', '.') . '</p>
                            <p><strong>Total de Produtos:</strong> ' . count($produtos_calculo) . '</p>
                        </div>
                    </div>';

        echo '<div class="bg-gray-50 p-4 rounded-md">
            <h2 class="text-lg font-semibold mb-3">Detalhes do Cálculo</h2>
            <p><strong>Peso Agrupado:</strong> ' . number_format($calculo['peso_agrupado'], 3, ',', '.') . ' ' . htmlspecialchars($calculo['unidade_medida']) . '</p>
            <p><strong>Quantidade:</strong> ' . number_format($calculo['quantidade'], 3, ',', '.') . '</p>
            <p><strong>% da Pauta:</strong> ' . number_format($calculo['percentual_pauta'], 2, ',', '.') . '%</p>
            <p><strong>Resultado Pauta:</strong> R$ ' . number_format($calculo['resultado_pauta'], 2, ',', '.') . '</p>
            <p><strong>Base de Cálculo:</strong> R$ ' . number_format($calculo['base_calculo'], 2, ',', '.') . '</p>
            <p><strong>Alíquota Efetiva:</strong> ' . number_format($calculo['carga_tributaria'], 2, ',', '.') . '%</p>
        </div>';
        
        if (!empty($produtos_calculo)) {
            echo '<div class="bg-gray-50 p-4 rounded-md mt-6">
                    <h2 class="text-lg font-semibold mb-3">Produtos no Cálculo</h2>
                    <div class="table-responsive">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Quantidade</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Carga Trib.</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo Cálculo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">';
            
            foreach ($produtos_calculo as $produto) {
                echo '<tr>
                        <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['descricao']) . '</td>
                        <td class="px-4 py-2 text-sm">' . number_format($produto['quantidade'], 4, ',', '.') . '</td>
                        <td class="px-4 py-2 text-sm">R$ ' . number_format($produto['valor_total'], 2, ',', '.') . '</td>
                        <td class="px-4 py-2 text-sm">' . number_format($produto['carga_tributaria'], 2, ',', '.') . '%</td>
                        <td class="px-4 py-2 text-sm">' . htmlspecialchars($produto['tipo_calculo']) . '</td>
                      </tr>';
            }
            
            echo '</tbody></table></div></div>';
        }
        
        echo '</div></div><script>feather.replace();</script></body></html>';
        exit;
    }
}

// Excluir cálculo
if ($action === 'excluir' && $calculo_id) {
    try {
        $conexao->begin_transaction();
        
        // Verificar se o cálculo pertence ao usuário
        $query = "SELECT id, grupo_id FROM calculos_cesta_basica WHERE id = ? AND usuario_id = ?";
        if ($stmt = $conexao->prepare($query)) {
            $stmt->bind_param("ii", $calculo_id, $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $calculo = $result->fetch_assoc();
            $stmt->close();
            
            if (!$calculo) {
                header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Cálculo não encontrado");
                exit;
            }
            
            // Excluir produtos do cálculo
            $query = "DELETE FROM cesta_calculo_produtos WHERE calculo_cesta_id = ?";
            if ($stmt = $conexao->prepare($query)) {
                $stmt->bind_param("i", $calculo_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Excluir cálculo
            $query = "DELETE FROM calculos_cesta_basica WHERE id = ?";
            if ($stmt = $conexao->prepare($query)) {
                $stmt->bind_param("i", $calculo_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Excluir grupo
            if ($calculo['grupo_id']) {
                $query = "DELETE FROM grupos_calculo_cesta WHERE id = ?";
                if ($stmt = $conexao->prepare($query)) {
                    $stmt->bind_param("i", $calculo['grupo_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $conexao->commit();
            header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&msg=excluido");
            exit;
        }
    } catch (Exception $e) {
        $conexao->rollback();
        header("Location: fronteira-fiscal.php?competencia=" . $competencia . "&error=Erro ao excluir cálculo");
        exit;
    }
}

header("Location: fronteira-fiscal.php");
exit;
?>