<?php
session_start();

if (isset($_POST['submit']) && !empty($_POST['username']) && !empty($_POST['password'])) {
    include("config.php");

    $username = $_POST["username"];
    $password = $_POST["password"];

    // Verificar se é email ou username
    $is_email = filter_var($username, FILTER_VALIDATE_EMAIL);
    
    $sql = "SELECT u.*, e.razao_social, e.cnpj 
            FROM users u 
            LEFT JOIN empresas e ON u.id = e.user_id 
            WHERE " . ($is_email ? "u.email = ?" : "u.username = ?") . " LIMIT 1";
    
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            // Login OK
            $_SESSION['logado'] = true;
            $_SESSION['usuario_id'] = $row['id'];
            $_SESSION['usuario_username'] = $row['username'];
            $_SESSION['usuario_email'] = $row['email'];
            $_SESSION['usuario_cnpj'] = $row['cnpj'];
            $_SESSION['usuario_razao_social'] = $row['razao_social'];
            $_SESSION['ultimo_acesso'] = time();
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['username'] = $username;
            header("Location: index.php?erro=senha");
            exit;
        }
    } else {
        $_SESSION['username'] = $username;
        header("Location: index.php?erro=usuario");
        exit;
    }
} else {
    echo "Erro: Username/email ou senha não enviados.";
}
?>