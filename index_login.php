<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Notas Fiscais</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem;">
                <i class="fas fa-file-invoice"></i>
            </div>
            <h2>Acessar Sistema</h2>
            <p style="color: var(--gray);">Entre com suas credenciais para acessar o sistema</p>
        </div>
        
        <?php if (isset($_GET['erro'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    if ($_GET['erro'] == 'senha') {
                        echo 'Senha incorreta!';
                    } elseif ($_GET['erro'] == 'usuario') {
                        echo 'Usuário não encontrado!';
                    } else {
                        echo 'Erro ao fazer login!';
                    }
                ?>
            </div>
        <?php endif; ?>
        
        <form action="loginTest.php" method="POST">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username ou Email</label>
                <input type="text" name="username" id="username" placeholder="Seu username ou email" required
                       value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Senha</label>
                <input type="password" name="password" id="password" placeholder="Sua senha" required>
            </div>
            <input class="inputSubmit" type="submit" name="submit" value="Entrar">
            <div class="register-link">
                <p>Não tem uma conta? <a href="cadastro.php">Cadastre-se</a></p>
            </div>
        </form>
    </div>

    <script>
        // Adicionar foco automático no primeiro campo
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>