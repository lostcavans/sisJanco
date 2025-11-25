<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleção de Empresa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        .container {
            max-width: 1200px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            color: var(--white);
        }

        .logo {
            font-size: 4rem;
            color: var(--white);
            display: inline-block;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .subtitle {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .company-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .company-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary);
        }

        .company-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .company-card.janco::before {
            background: linear-gradient(90deg, #4361ee, #3a56d4);
        }

        .company-card.outra::before {
            background: linear-gradient(90deg, #7209b7, #5a08a1);
        }

        .company-icon {
            font-size: 3.5rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: inline-block;
            padding: 15px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
        }

        .company-card.janco .company-icon {
            color: #4361ee;
            background: rgba(67, 97, 238, 0.1);
        }

        .company-card.outra .company-icon {
            color: #7209b7;
            background: rgba(114, 9, 183, 0.1);
        }

        .company-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .company-description {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: var(--primary);
            color: var(--white);
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: 2px solid transparent;
            margin-top: auto;
        }

        .company-card.janco .btn {
            background: var(--primary);
        }

        .company-card.outra .btn {
            background: var(--secondary);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn:active {
            transform: translateY(0);
        }

        .features {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--white);
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
        }

        .feature i {
            color: #4cc9f0;
        }

        footer {
            text-align: center;
            margin-top: 3rem;
            color: var(--white);
            opacity: 0.7;
            font-size: 0.9rem;
        }

        /* Animações */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header, .companies-grid {
            animation: fadeIn 0.8s ease-out;
        }

        .company-card {
            animation: fadeIn 0.8s ease-out;
        }

        .company-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }
            
            .subtitle {
                font-size: 1rem;
            }
            
            .companies-grid {
                grid-template-columns: 1fr;
            }
            
            .logo {
                font-size: 3rem;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="uploads/logo-images/ANTONIO LOGO 3.png" alt="Descrição da imagem" style="width: 300px; height: 200px;">
            </div>
            <h1>Sistema de Gestão de Processos</h1>
            <p class="subtitle">Selecione a empresa que deseja acessar para gerenciar seus processos de forma eficiente</p>
        </div>
        
        <div class="companies-grid">
            <div class="company-card janco">
                <i class="fas fa-building company-icon"></i>
                <h3 class="company-name">Janco</h3>
                <p class="company-description">Sistema completo de gestão de processos empresariais</p>
                <a href="index_login.php" class="btn">Acessar Sistema</a>
            </div>
            
            <div class="company-card outra">
                <i class="fas fa-industry company-icon"></i>
                <h3 class="company-name">Gestão Janco</h3>
                <p class="company-description">Sistema de gestão empresarial integrado</p>
                <a href="login-gestao.php" class="btn">Acessar Sistema</a>
            </div>
        </div>
        
        <div class="features">
            <div class="feature">
                <i class="fas fa-shield-alt"></i>
                <span>Sistema Seguro</span>
            </div>
            <div class="feature">
                <i class="fas fa-bolt"></i>
                <span>Rápido e Eficiente</span>
            </div>
            <div class="feature">
                <i class="fas fa-mobile-alt"></i>
                <span>Totalmente Responsivo</span>
            </div>
        </div>
        

        <footer>
            <p>&copy; Versão 2.0.5</p>
            <p>&copy; 2023 Sistema de Gestão de Processos. Todos os direitos reservados.</p>
        </footer>
    </div>
</body>
</html>