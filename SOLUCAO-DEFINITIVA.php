<?php
// SOLUCAO-DEFINITIVA.php
include("config-gestao.php");

echo "<h1>🔧 SOLUCIÓN DEFINITIVA - Reset Total</h1>";

// 1. Resetear TODAS las contraseñas
$nova_senha = 'admin123';
$hash_correto = password_hash($nova_senha, PASSWORD_DEFAULT);

$sql_update = "UPDATE gestao_usuarios SET password = ?";
$stmt = $conexao->prepare($sql_update);
$stmt->bind_param("s", $hash_correto);

if ($stmt->execute()) {
    echo "✅ <strong>TODAS las contraseñas reseteadas a: $nova_senha</strong><br>";
    echo "Hash utilizado: " . substr($hash_correto, 0, 30) . "...<br><br>";
} else {
    echo "❌ Error: " . $conexao->error . "<br>";
}

// 2. Verificar vínculos actuales
$sql_vinculos = "SELECT 
                    u.username,
                    e.razao_social,
                    e.id as empresa_id,
                    ue.principal
                 FROM gestao_user_empresa ue
                 INNER JOIN gestao_usuarios u ON ue.user_id = u.id
                 INNER JOIN gestao_empresas e ON ue.empresa_id = e.id
                 ORDER BY u.username";
$result = $conexao->query($sql_vinculos);

echo "<h2>✅ Vínculos Actuales:</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Usuario</th><th>Empresa</th><th>Empresa ID</th><th>Principal</th></tr>";

while($row = $result->fetch_assoc()) {
    echo "<tr>
            <td><strong>{$row['username']}</strong></td>
            <td>{$row['razao_social']}</td>
            <td>{$row['empresa_id']}</td>
            <td>" . ($row['principal'] ? '✅ Sí' : '❌ No') . "</td>
          </tr>";
}
echo "</table>";

// 3. Verificar que las contraseñas funcionan
echo "<h2>🔐 Verificación de Contraseñas:</h2>";
$usuarios_verificar = ['admin', 'admin123', 'novousuario'];

foreach ($usuarios_verificar as $username) {
    $sql = "SELECT username, password FROM gestao_usuarios WHERE username = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result_user = $stmt->get_result();
    
    if ($result_user->num_rows > 0) {
        $user = $result_user->fetch_assoc();
        $senha_valida = password_verify($nova_senha, $user['password']);
        
        echo "Usuario: <strong>$username</strong> | ";
        echo "Contraseña '$nova_senha': " . 
             ($senha_valida ? "✅ <span style='color:green;'>VÁLIDA</span>" : "❌ <span style='color:red;'>INVÁLIDA</span>");
        echo "<br>";
    }
}

echo "<br><h2>🚀 INSTRUCCIONES FINALES:</h2>";
echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 10px;'>";
echo "<h3>Para hacer LOGIN exitoso:</h3>";
echo "<p><strong>1. Usuario:</strong> admin</p>";
echo "<p><strong>2. Contraseña:</strong> admin123</p>";
echo "<p><strong>3. Empresa:</strong> Global Services Consultoria Ltda (ID 22)</p>";
echo "<br>";
echo "<p><a href='login-gestao.php' style='background: #4361ee; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px;'>🎯 HACER LOGIN AHORA</a></p>";
echo "</div>";

// 4. Mostrar datos completos para referencia
echo "<h2>📊 Resumen Completo del Sistema:</h2>";

echo "<h3>Usuarios:</h3>";
$sql_usuarios = "SELECT id, username, email, nivel_acesso, ativo FROM gestao_usuarios";
$result_usuarios = $conexao->query($sql_usuarios);
while($user = $result_usuarios->fetch_assoc()) {
    echo "- ID: {$user['id']} | Usuario: {$user['username']} | Nivel: {$user['nivel_acesso']} | " . 
         ($user['ativo'] ? "✅ Activo" : "❌ Inactivo") . "<br>";
}

echo "<h3>Empresas Disponibles:</h3>";
$sql_empresas = "SELECT id, razao_social, nome_fantasia FROM gestao_empresas WHERE ativo = 1";
$result_empresas = $conexao->query($sql_empresas);
while($empresa = $result_empresas->fetch_assoc()) {
    echo "- ID: {$empresa['id']} | Razón Social: {$empresa['razao_social']}<br>";
}
?>