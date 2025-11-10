<?php
echo "<h3>🔍 Teste Detalhado de Conexão MySQL</h3>";

$host = 'localhost';
$usuario = 'Janco';
$senha = 'ZG3011#cdz';
$banco = 'sistemacontabil';

echo "Tentando conectar com:<br>";
echo "Host: $host<br>";
echo "Usuário: $usuario<br>";
echo "Banco: $banco<br>";
echo "Senha: " . str_repeat('*', strlen($senha)) . "<br><br>";

// Teste 1: Conexão sem banco específico
try {
    $conexao = new mysqli($host, $usuario, $senha);
    if (!$conexao->connect_error) {
        echo "✅ CONEXÃO BEM-SUCEDIDA (sem banco específico)<br>";
        
        // Listar bancos disponíveis
        $result = $conexao->query("SHOW DATABASES");
        echo "<h4>Bancos disponíveis:</h4>";
        while ($row = $result->fetch_array()) {
            echo $row[0] . "<br>";
        }
        $conexao->close();
    }
} catch (Exception $e) {
    echo "❌ ERRO (sem banco): " . $e->getMessage() . "<br><br>";
}

// Teste 2: Conexão COM banco específico
try {
    $conexao = new mysqli($host, $usuario, $senha, $banco);
    if (!$conexao->connect_error) {
        echo "✅ CONEXÃO BEM-SUCEDIDA (com banco '$banco')<br>";
        $conexao->close();
    }
} catch (Exception $e) {
    echo "❌ ERRO (com banco '$banco'): " . $e->getMessage() . "<br><br>";
}

// Teste 3: Verificar se o usuário existe
try {
    $conexao = new mysqli('localhost', 'root', ''); // Tenta como root sem senha
    if (!$conexao->connect_error) {
        $result = $conexao->query("SELECT user, host FROM mysql.user WHERE user = 'janco'");
        if ($result->num_rows > 0) {
            echo "✅ Usuário 'janco' EXISTE no MySQL<br>";
            while ($row = $result->fetch_assoc()) {
                echo " - " . $row['user'] . "@" . $row['host'] . "<br>";
            }
        } else {
            echo "❌ Usuário 'janco' NÃO ENCONTRADO no MySQL<br>";
        }
        $conexao->close();
    }
} catch (Exception $e) {
    echo "⚠️ Não foi possível verificar usuários: " . $e->getMessage() . "<br>";
}
?>