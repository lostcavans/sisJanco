<?php
// logout-gestao.php
session_start();
include("config-gestao.php");

// Registrar log de logout
if (isset($_SESSION['usuario_username_gestao'])) {
    registrarLogGestao('LOGOUT', 'Usuário ' . $_SESSION['usuario_username_gestao'] . ' fez logout');
}

// Destruir sessão do sistema de gestão
unset($_SESSION['logado_gestao']);
unset($_SESSION['usuario_id_gestao']);
unset($_SESSION['usuario_username_gestao']);
unset($_SESSION['usuario_nome_gestao']);
unset($_SESSION['usuario_nivel_gestao']);
unset($_SESSION['usuario_departamento_gestao']);
unset($_SESSION['usuario_cargo_gestao']);
unset($_SESSION['empresa_id_gestao']);
unset($_SESSION['usuario_razao_social_gestao']);
unset($_SESSION['usuario_cnpj_gestao']);
unset($_SESSION['usuario_regime_tributario_gestao']);
unset($_SESSION['ultimo_acesso_gestao']);

// Redirecionar para login
header("Location: index.php");
exit;
?>