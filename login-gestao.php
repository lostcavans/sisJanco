<?php
// login-gestao.php
session_start();
include("config-gestao.php");

// Función para registrar logs de depuração
function debugLog($message, $data = null) {
    $log_file = 'login_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        $log_message .= " | Data: " . (is_array($data) ? json_encode($data) : $data);
    }
    
    $log_message .= "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

// Redirecionar se já estiver logado
if (isset($_SESSION['logado_gestao']) && $_SESSION['logado_gestao'] === true) {
    header("Location: dashboard-gestao.php");
    exit;
}

$erro = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $empresa_id = $_POST['empresa'] ?? '';

    debugLog("Tentativa de login", [
        'username' => $username,
        'empresa_id' => $empresa_id,
        'password_length' => strlen($password)
    ]);

    if (empty($username) || empty($password) || empty($empresa_id)) {
        $erro = 'Todos os campos são obrigatórios.';
        debugLog("Campos obrigatórios faltando", [
            'username_empty' => empty($username),
            'password_empty' => empty($password),
            'empresa_empty' => empty($empresa_id)
        ]);
    } else {
        try {
            // Buscar usuário no sistema de gestão
            $sql = "SELECT u.*, e.razao_social, e.cnpj, e.regime_tributario 
                    FROM gestao_usuarios u 
                    INNER JOIN gestao_user_empresa ue ON u.id = ue.user_id 
                    INNER JOIN gestao_empresas e ON ue.empresa_id = e.id 
                    WHERE u.username = ? AND u.ativo = 1 AND e.id = ?";
            
            debugLog("SQL preparado", $sql);
            
            $stmt = $conexao->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erro ao preparar consulta: " . $conexao->error);
            }
            
            $stmt->bind_param("si", $username, $empresa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            debugLog("Resultado da consulta", [
                'num_rows' => $result->num_rows,
                'error' => $stmt->error
            ]);

            if ($result->num_rows === 0) {
                // Usuário não encontrado - vamos verificar por que
                debugLog("Usuário não encontrado. Verificando possíveis causas...");
                
                // Verificar se o usuário existe (sem empresa)
                $sql_user_only = "SELECT id, username, ativo FROM gestao_usuarios WHERE username = ?";
                $stmt_user = $conexao->prepare($sql_user_only);
                $stmt_user->bind_param("s", $username);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();
                
                if ($result_user->num_rows === 0) {
                    $debug_info = "Usuário '$username' não existe na base de dados.";
                    debugLog("Usuário não existe na tabela gestao_usuarios");
                } else {
                    $user_data = $result_user->fetch_assoc();
                    if ($user_data['ativo'] == 0) {
                        $debug_info = "Usuário '$username' existe mas está inativo.";
                        debugLog("Usuário inativo", $user_data);
                    } else {
                        // Verificar relacionamento com empresa
                        $sql_relacionamento = "SELECT ue.*, e.razao_social 
                                              FROM gestao_user_empresa ue 
                                              INNER JOIN gestao_empresas e ON ue.empresa_id = e.id 
                                              WHERE ue.user_id = ?";
                        $stmt_rel = $conexao->prepare($sql_relacionamento);
                        $stmt_rel->bind_param("i", $user_data['id']);
                        $stmt_rel->execute();
                        $result_rel = $stmt_rel->get_result();
                        
                        if ($result_rel->num_rows === 0) {
                            $debug_info = "Usuário '$username' não está vinculado a nenhuma empresa.";
                            debugLog("Usuário sem vínculo com empresa");
                        } else {
                            $empresas_vinculadas = $result_rel->fetch_all(MYSQLI_ASSOC);
                            $debug_info = "Usuário '$username' vinculado às empresas: " . 
                                         implode(', ', array_column($empresas_vinculadas, 'razao_social')) . 
                                         " mas não à empresa ID $empresa_id.";
                            debugLog("Usuário vinculado a outras empresas", $empresas_vinculadas);
                        }
                    }
                }
                
                $erro = 'Usuário, senha ou empresa inválidos.';
                
            } else {
                $usuario = $result->fetch_assoc();
                debugLog("Usuário encontrado", [
                    'id' => $usuario['id'],
                    'username' => $usuario['username'],
                    'nivel_acesso' => $usuario['nivel_acesso'],
                    'password_hash' => substr($usuario['password'], 0, 20) . '...'
                ]);

                // Verificar senha
                $senha_correta = password_verify($password, $usuario['password']);
                debugLog("Verificação de senha", [
                    'senha_correta' => $senha_correta,
                    'password_provided' => $password
                ]);

                if ($senha_correta) {
                    // Login bem-sucedido
                    $_SESSION['logado_gestao'] = true;
                    $_SESSION['usuario_id_gestao'] = $usuario['id'];
                    $_SESSION['usuario_username_gestao'] = $usuario['username'];
                    $_SESSION['usuario_nome_gestao'] = $usuario['nome_completo'];
                    $_SESSION['usuario_nivel_gestao'] = $usuario['nivel_acesso'];
                    $_SESSION['usuario_departamento_gestao'] = $usuario['departamento'];
                    $_SESSION['usuario_cargo_gestao'] = $usuario['cargo'];
                    $_SESSION['empresa_id_gestao'] = $empresa_id;
                    $_SESSION['usuario_razao_social_gestao'] = $usuario['razao_social'];
                    $_SESSION['usuario_cnpj_gestao'] = $usuario['cnpj'];
                    $_SESSION['usuario_regime_tributario_gestao'] = $usuario['regime_tributario'];
                    $_SESSION['ultimo_acesso_gestao'] = time();

                    debugLog("Login bem-sucedido", [
                        'user_id' => $usuario['id'],
                        'empresa_id' => $empresa_id
                    ]);

                    // Registrar log
                    registrarLogGestao('LOGIN', 'Usuário ' . $usuario['username'] . ' fez login no sistema de gestão');

                    header("Location: dashboard-gestao.php");
                    exit;
                } else {
                    $erro = 'Senha incorreta.';
                    $debug_info = "A senha fornecida não corresponde ao hash armazenado.";
                    debugLog("Senha incorreta");
                }
            }
            
        } catch (Exception $e) {
            $erro = 'Erro interno do sistema.';
            $debug_info = "Erro: " . $e->getMessage();
            debugLog("Exceção capturada", $e->getMessage());
        }
    }
}

// Buscar empresas para o select
$empresas = [];
try {
    $sql = "SELECT id, razao_social, nome_fantasia FROM gestao_empresas WHERE ativo = 1 ORDER BY razao_social";
    $result = $conexao->query($sql);
    
    debugLog("Busca de empresas", [
        'num_empresas' => $result ? $result->num_rows : 0,
        'error' => $conexao->error
    ]);
    
    if ($result) {
        $empresas = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        debugLog("Erro ao buscar empresas", $conexao->error);
    }
} catch (Exception $e) {
    debugLog("Erro na busca de empresas", $e->getMessage());
}

// Verificar se as tabelas existem
try {
    $tabelas_necessarias = ['gestao_usuarios', 'gestao_empresas', 'gestao_user_empresa'];
    $tabelas_existentes = [];
    
    foreach ($tabelas_necessarias as $tabela) {
        $result = $conexao->query("SHOW TABLES LIKE '$tabela'");
        $tabelas_existentes[$tabela] = $result->num_rows > 0;
    }
    
    debugLog("Verificação de tabelas", $tabelas_existentes);
    
    if (in_array(false, $tabelas_existentes)) {
        $debug_info .= " ALERTA: Algumas tabelas não existem: " . 
                      implode(', ', array_keys(array_filter($tabelas_existentes, function($v) { return !$v; })));
    }
} catch (Exception $e) {
    debugLog("Erro na verificação de tabelas", $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestão de Processos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilos anteriores mantidos */
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #adb5bd;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--dark);
            line-height: 1.6;
        }

        .login-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            transition: var(--transition);
        }

        .login-container:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
            display: inline-block;
            padding: 15px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
        }

        h1 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
        }

        .subtitle {
            color: var(--gray);
            font-size: 0.95rem;
        }

        .alert {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border-left: 4px solid #3b82f6;
            font-size: 0.8rem;
        }

        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border-left: 4px solid #f59e0b;
            font-size: 0.8rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
            font-size: 0.9rem;
        }

        select, input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1rem;
            transition: var(--transition);
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .btn {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .debug-section {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--gray);
        }

        .debug-toggle {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 0.8rem;
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-container {
            animation: fadeIn 0.6s ease-out;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <img src="uploads/logo-images/ANTONIO LOGO 2.png" alt="Descrição da imagem" style="width: 150px; height: 100px;">
            </div>
            <h1>Acessar Sistema</h1>
            <p class="subtitle">Sistema de Gestão de Processos</p>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if ($debug_info && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['debug']))): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Informação de Depuração:</strong> <?php echo htmlspecialchars($debug_info); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="empresa">Empresa</label>
                <select id="empresa" name="empresa" required>
                    <option value="">Selecione a empresa...</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?php echo $empresa['id']; ?>" 
                            <?php echo (isset($_POST['empresa']) && $_POST['empresa'] == $empresa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($empresa['razao_social']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($empresas)): ?>
                    <div class="alert alert-warning" style="margin-top: 10px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Nenhuma empresa cadastrada no sistema.
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" placeholder="Seu usuário" required 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" placeholder="Sua senha" required>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Entrar no Sistema
            </button>
        </form>

        <?php if (isset($_GET['debug']) || !empty($debug_info)): ?>
            <div class="debug-section">
                <h4><i class="fas fa-bug"></i> Informações de Depuração</h4>
                <p><strong>Arquivo de Log:</strong> login_debug.log</p>
                <p><strong>Total de Empresas:</strong> <?php echo count($empresas); ?></p>
                <p><strong>Método:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                <p><a href="?debug=1" class="debug-toggle">Atualizar informações de debug</a></p>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="?debug=1" class="debug-toggle">Modo Depuração</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Mostrar alerta se não houver empresas
            const selectEmpresa = document.getElementById('empresa');
            if (selectEmpresa.options.length <= 1) {
                console.warn('Nenhuma empresa disponível para seleção');
            }
        });
    </script>
</body>
</html>