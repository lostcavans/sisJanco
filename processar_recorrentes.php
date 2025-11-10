<?php
include("config-gestao.php");

// Buscar todas as empresas
$sql_empresas = "SELECT id FROM gestao_empresas WHERE ativo = 1";
$empresas = $conexao->query($sql_empresas)->fetch_all(MYSQLI_ASSOC);

foreach ($empresas as $empresa) {
    processarProcessosRecorrentes($conexao, $empresa['id']);
}

echo "Processos recorrentes processados com sucesso!\n";
?>