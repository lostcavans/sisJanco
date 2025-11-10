<?php
include("config.php");
session_start();

$error = "";
$success = "";

// Garantir que o formulário foi enviado via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm-password"] ?? "";
    $razao_social = trim($_POST["razao_social"] ?? "");
    $cnpj = trim($_POST["cnpj"] ?? "");
    $regime_tributario = $_POST["regime_tributario"] ?? "";
    $sistema_utilizado = trim($_POST["sistema_utilizado"] ?? "");
    $atividade = $_POST["atividade"] ?? "";

    // Validações básicas
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || 
        empty($razao_social) || empty($cnpj) || empty($regime_tributario) || empty($atividade)) {
        $error = "Preencha todos os campos obrigatórios!";
    } 
    elseif ($password !== $confirm_password) {
        $error = "As senhas não coincidem!";
    }
    elseif (strlen($password) < 6) {
        $error = "A senha deve ter pelo menos 6 caracteres!";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido!";
    }
    elseif (!validarCNPJ($cnpj)) {
        $error = "CNPJ inválido!";
    }
    else {
        // Verificar se username ou email já existem
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conexao, $check_sql);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Já existe um usuário com este username ou email!";
        } else {
            // Verificar se CNPJ já existe na tabela empresas
            $check_cnpj_sql = "SELECT id FROM empresas WHERE cnpj = ?";
            $stmt_cnpj = mysqli_prepare($conexao, $check_cnpj_sql);
            mysqli_stmt_bind_param($stmt_cnpj, "s", $cnpj);
            mysqli_stmt_execute($stmt_cnpj);
            mysqli_stmt_store_result($stmt_cnpj);
            
            if (mysqli_stmt_num_rows($stmt_cnpj) > 0) {
                $error = "Já existe uma empresa cadastrada com este CNPJ!";
                mysqli_stmt_close($stmt_cnpj);
            } else {
                mysqli_stmt_close($stmt_cnpj);
                
                // Hash da senha
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Iniciar transação
                mysqli_begin_transaction($conexao);
                
                try {
                    // Inserir usuário
                    $sql_user = "INSERT INTO users (username, password, email) VALUES (?, ?, ?)";
                    $stmt_user = mysqli_prepare($conexao, $sql_user);
                    mysqli_stmt_bind_param($stmt_user, "sss", $username, $hashed_password, $email);
                    
                    if (!mysqli_stmt_execute($stmt_user)) {
                        throw new Exception("Erro ao cadastrar usuário: " . mysqli_error($conexao));
                    }
                    
                    $user_id = mysqli_insert_id($conexao);
                    
                    // Inserir empresa
                    $sql_empresa = "INSERT INTO empresas (razao_social, cnpj, regime_tributario, sistema_utilizado, atividade, user_id) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_empresa = mysqli_prepare($conexao, $sql_empresa);
                    mysqli_stmt_bind_param($stmt_empresa, "sssssi", $razao_social, $cnpj, $regime_tributario, $sistema_utilizado, $atividade, $user_id);
                    
                    if (!mysqli_stmt_execute($stmt_empresa)) {
                        throw new Exception("Erro ao cadastrar empresa: " . mysqli_error($conexao));
                    }
                    
                    // Commit da transação
                    mysqli_commit($conexao);
                    
                    $success = "Usuário e empresa cadastrados com sucesso!";
                    // Redirecionar após 2 segundos
                    header("refresh:2; url=index.php");
                    
                } catch (Exception $e) {
                    // Rollback em caso de erro
                    mysqli_rollback($conexao);
                    $error = $e->getMessage();
                }
                
                if (isset($stmt_user)) mysqli_stmt_close($stmt_user);
                if (isset($stmt_empresa)) mysqli_stmt_close($stmt_empresa);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Função para validar CNPJ (mantida igual)
function validarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
    
    // Valida tamanho
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os digitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Valida primeiro dígito verificador
    for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    
    $resto = $soma % 11;
    
    if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
        return false;
    }
    
    // Valida segundo dígito verificador
    for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    
    $resto = $soma % 11;
    
    return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Sistema de Notas Fiscais</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="register-container">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="font-size: 2.5rem; color: var(--primary); margin-bottom: 0.5rem;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h2>Criar Conta</h2>
            <p style="color: var(--gray);">Preencha os dados abaixo para criar sua conta</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
                <p>Redirecionando para login...</p>
            </div>
        <?php endif; ?>
        
        <form action="cadastro.php" method="POST" onsubmit="return validarFormulario()">
            <h3><i class="fas fa-key"></i> Dados de Acesso</h3>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" placeholder="Seu nome de usuário" required 
                       value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="seu@email.com" required 
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" id="password" placeholder="Crie uma senha (mín. 6 caracteres)" 
                       oninput="validarSenha()" minlength="6" required>
                <small style="color: var(--gray); font-size: 0.8rem; display: block; margin-top: 0.3rem;">Mínimo de 6 caracteres</small>
            </div>
            <div class="form-group">
                <label for="confirm-password">Confirmar Senha</label>
                <input type="password" name="confirm-password" id="confirm-password" 
                       placeholder="Confirme sua senha" oninput="validarSenha()" required>
            </div>
            
            <h3><i class="fas fa-building"></i> Dados da Empresa</h3>
            <div class="form-group">
                <label for="razao_social">Razão Social</label>
                <input type="text" name="razao_social" id="razao_social" placeholder="Nome da empresa" required 
                       value="<?php echo isset($razao_social) ? htmlspecialchars($razao_social) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="cnpj">CNPJ</label>
                <input type="text" name="cnpj" id="cnpj" placeholder="00.000.000/0000-00" 
                       oninput="formatarCNPJ(this)" required
                       value="<?php echo isset($cnpj) ? htmlspecialchars($cnpj) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="regime_tributario">Regime Tributário</label>
                <select name="regime_tributario" id="regime_tributario" required>
                    <option value="">Selecione o regime</option>
                    <option value="MEI" <?php echo (isset($regime_tributario) && $regime_tributario == 'MEI') ? 'selected' : ''; ?>>MEI</option>
                    <option value="Simples Nacional" <?php echo (isset($regime_tributario) && $regime_tributario == 'Simples Nacional') ? 'selected' : ''; ?>>Simples Nacional</option>
                    <option value="Lucro Real" <?php echo (isset($regime_tributario) && $regime_tributario == 'Lucro Real') ? 'selected' : ''; ?>>Lucro Real</option>
                    <option value="Lucro Presumido" <?php echo (isset($regime_tributario) && $regime_tributario == 'Lucro Presumido') ? 'selected' : ''; ?>>Lucro Presumido</option>
                </select>
            </div>
            <div class="form-group">
                <label for="sistema_utilizado">Sistema Utilizado (opcional)</label>
                <input type="text" name="sistema_utilizado" id="sistema_utilizado" placeholder="Ex: ContaAzul, Bling, etc." 
                       value="<?php echo isset($sistema_utilizado) ? htmlspecialchars($sistema_utilizado) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="atividade">Atividade Principal</label>
                <select name="atividade" id="atividade" required>
                    <option value="">Selecione a atividade</option>
                    <option value="Serviço" <?php echo (isset($atividade) && $atividade == 'Serviço') ? 'selected' : ''; ?>>Serviço</option>
                    <option value="Comércio" <?php echo (isset($atividade) && $atividade == 'Comércio') ? 'selected' : ''; ?>>Comércio</option>
                    <option value="Indústria" <?php echo (isset($atividade) && $atividade == 'Indústria') ? 'selected' : ''; ?>>Indústria</option>
                </select>
            </div>
            
            <button type="submit" name="submit" class="btn"><i class="fas fa-user-plus"></i> Cadastrar</button>
            <div class="login-link">
                <p>Já tem uma conta? <a href="index.php">Faça login</a></p>
            </div>
        </form>
    </div>

    <script>
        // Função para validar todo o formulário antes de enviar
        function validarFormulario() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm-password');
            
            if (password.value !== confirmPassword.value) {
                alert('As senhas não coincidem!');
                return false;
            }
            
            if (password.value.length < 6) {
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
            
            return true;
        }
        
        // Esconder alertas após 5 segundos
        setTimeout(function() {
            const errorAlert = document.getElementById('errorAlert');
            const successAlert = document.getElementById('successAlert');
            
            if (errorAlert) {
                errorAlert.style.display = 'none';
            }
            
            if (successAlert) {
                successAlert.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>