-- phpMyAdmin SQL Dump
-- version 4.3.7
-- http://www.phpmyadmin.net
--
-- Host: mysql09-farm10.kinghost.net
-- Tempo de geração: 10/11/2025 às 16:01
-- Versão do servidor: 10.2.36-MariaDB-log
-- Versão do PHP: 5.3.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Banco de dados: `jancoassessori`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `adm`
--

CREATE TABLE IF NOT EXISTS `adm` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nivel` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calculos_cesta_basica`
--

CREATE TABLE IF NOT EXISTS `calculos_cesta_basica` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `regiao_fornecedor` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor_total_produtos` decimal(15,2) NOT NULL,
  `valor_total_icms` decimal(15,2) NOT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_calculo` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `peso_agrupado` decimal(15,3) DEFAULT NULL,
  `unidade_medida` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade` decimal(15,3) DEFAULT NULL,
  `percentual_pauta` decimal(5,2) DEFAULT NULL,
  `carga_tributaria` decimal(5,2) NOT NULL,
  `resultado_pauta` decimal(15,2) DEFAULT NULL,
  `base_calculo` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calculos_difal`
--

CREATE TABLE IF NOT EXISTS `calculos_difal` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_base_calculo` decimal(15,2) DEFAULT NULL,
  `valor_difal` decimal(15,2) DEFAULT NULL,
  `aliquota_interna` decimal(5,2) DEFAULT NULL,
  `aliquota_interestadual` decimal(5,2) DEFAULT NULL,
  `tipo_calculo` enum('normal','simples','reducao') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_calculo` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calculos_difal_itens`
--

CREATE TABLE IF NOT EXISTS `calculos_difal_itens` (
  `id` int(11) NOT NULL,
  `calculo_id` int(11) NOT NULL,
  `numero_item` int(11) DEFAULT NULL,
  `descricao_produto` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncm` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_produto` decimal(15,2) DEFAULT NULL,
  `aliquota_difal` decimal(5,4) DEFAULT NULL,
  `aliquota_fecoep` decimal(5,4) DEFAULT NULL,
  `aliquota_reducao` decimal(5,4) DEFAULT 0.0000,
  `valor_difal` decimal(15,2) DEFAULT NULL,
  `valor_fecoep` decimal(15,2) DEFAULT NULL,
  `valor_total_impostos` decimal(15,2) DEFAULT NULL,
  `data_atualizacao` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calculos_difal_manuais`
--

CREATE TABLE IF NOT EXISTS `calculos_difal_manuais` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nota_fiscal_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_calculo` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `calculos_fronteira`
--

CREATE TABLE IF NOT EXISTS `calculos_fronteira` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grupo_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `considera_desconto` enum('S','N') COLLATE utf8mb4_unicode_ci DEFAULT 'S',
  `tipo_credito_icms` enum('manual','nota') COLLATE utf8mb4_unicode_ci DEFAULT 'nota',
  `valor_produto` decimal(15,2) DEFAULT 0.00,
  `valor_frete` decimal(15,2) DEFAULT 0.00,
  `valor_ipi` decimal(15,2) DEFAULT 0.00,
  `valor_seguro` decimal(15,2) DEFAULT 0.00,
  `valor_desconto` decimal(15,2) DEFAULT 0.00,
  `valor_icms` decimal(15,2) DEFAULT 0.00,
  `valor_gnre` decimal(15,2) DEFAULT 0.00,
  `aliquota_interna` decimal(5,2) DEFAULT 20.50,
  `aliquota_interestadual` decimal(5,2) DEFAULT 0.00,
  `mva_cnae` decimal(5,2) DEFAULT 0.00,
  `mva_original` decimal(5,2) DEFAULT 0.00,
  `difal` decimal(5,2) DEFAULT 0.00,
  `aliquota_credito` decimal(5,2) DEFAULT 0.00,
  `aliquota_reducao` decimal(5,2) DEFAULT 0.00,
  `regime_fornecedor` enum('1','2','3') COLLATE utf8mb4_unicode_ci DEFAULT '3',
  `empresa_regular` enum('S','N') COLLATE utf8mb4_unicode_ci DEFAULT 'S',
  `mva_ajustada` decimal(10,4) DEFAULT 0.0000,
  `icms_st` decimal(15,2) DEFAULT 0.00,
  `icms_tributado_simples` decimal(15,2) DEFAULT 0.00,
  `icms_tributado_real` decimal(15,2) DEFAULT 0.00,
  `icms_uso_consumo` decimal(15,2) DEFAULT 0.00,
  `icms_reducao` decimal(15,2) DEFAULT 0.00,
  `icms_reducao_sn` decimal(15,2) DEFAULT 0.00,
  `icms_reducao_st_sn` decimal(15,2) DEFAULT 0.00,
  `data_calculo` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `diferencial_aliquota` decimal(10,2) DEFAULT 0.00,
  `tipo_calculo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'icms_st',
  `icms_tributado_simples_regular` decimal(10,2) DEFAULT 0.00,
  `icms_tributado_simples_irregular` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cesta_calculo_produtos`
--

CREATE TABLE IF NOT EXISTS `cesta_calculo_produtos` (
  `id` int(11) NOT NULL,
  `calculo_cesta_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `carga_tributaria` decimal(5,2) NOT NULL,
  `pauta_fiscal` decimal(10,2) DEFAULT 0.00,
  `valor_icms` decimal(15,2) NOT NULL,
  `tipo_calculo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `contas_pagar_santana`
--

CREATE TABLE IF NOT EXISTS `contas_pagar_santana` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcela` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_fornecedor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `razao_social_fornecedor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fantasia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota_fiscal` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emissao` date DEFAULT NULL,
  `vencimento` date DEFAULT NULL,
  `saldo` decimal(15,2) DEFAULT 0.00,
  `valor` decimal(15,2) DEFAULT 0.00,
  `juros` decimal(15,2) DEFAULT 0.00,
  `valor_pago` decimal(15,2) DEFAULT 0.00,
  `data_pagamento` date DEFAULT NULL,
  `obs_cp` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `obs_parc` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf_cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `competencia_ano` int(11) DEFAULT NULL,
  `competencia_mes` int(11) DEFAULT NULL,
  `data_importacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `dados_sefaz_difal`
--

CREATE TABLE IF NOT EXISTS `dados_sefaz_difal` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nota_fiscal_id` int(11) DEFAULT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_item` int(11) DEFAULT NULL,
  `descricao_produto` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_responsavel` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_imposto` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_icms` decimal(15,2) DEFAULT NULL,
  `valor_fecoep` decimal(15,2) DEFAULT NULL,
  `aliquota_icms` decimal(5,4) DEFAULT 0.0000,
  `aliquota_fecoep` decimal(5,4) DEFAULT 0.0000,
  `aliquota_reducao` decimal(5,4) DEFAULT 0.0000,
  `mva_valor` decimal(10,4) DEFAULT NULL,
  `redutor_base` decimal(5,4) DEFAULT 0.0000,
  `redutor_credito` decimal(5,4) DEFAULT NULL,
  `segmento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pauta_fiscal` decimal(10,2) DEFAULT NULL,
  `ncm` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cest` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_processamento` timestamp NULL DEFAULT current_timestamp(),
  `origem_dados` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'SEFAZ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresas`
--

CREATE TABLE IF NOT EXISTS `empresas` (
  `id` int(11) NOT NULL,
  `razao_social` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(18) COLLATE utf8mb4_unicode_ci NOT NULL,
  `regime_tributario` enum('MEI','Simples Nacional','Lucro Real','Lucro Presumido') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sistema_utilizado` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atividade` enum('Serviço','Comércio','Indústria') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_anexos_processo`
--

CREATE TABLE IF NOT EXISTS `gestao_anexos_processo` (
  `id` int(11) NOT NULL,
  `processo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nome_arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_arquivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_arquivo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int(11) DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_atividades`
--

CREATE TABLE IF NOT EXISTS `gestao_atividades` (
  `id` int(11) NOT NULL,
  `processo_id` int(11) NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsavel_id` int(11) NOT NULL,
  `tipo_periodicidade` enum('unica','diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') COLLATE utf8mb4_unicode_ci DEFAULT 'unica',
  `data_prevista` date DEFAULT NULL,
  `data_conclusao` date DEFAULT NULL,
  `status` enum('pendente','em_andamento','concluida','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `prioridade` enum('baixa','media','alta','urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'media',
  `ordem` int(11) DEFAULT 0,
  `depende_de` int(11) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_categorias_processo`
--

CREATE TABLE IF NOT EXISTS `gestao_categorias_processo` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cor` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#4361ee',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_comentarios_processo`
--

CREATE TABLE IF NOT EXISTS `gestao_comentarios_processo` (
  `id` int(11) NOT NULL,
  `processo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('comentario','observacao','alerta') COLLATE utf8mb4_unicode_ci DEFAULT 'comentario',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_configuracoes_sistema`
--

CREATE TABLE IF NOT EXISTS `gestao_configuracoes_sistema` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('string','integer','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_documentacoes_empresa`
--

CREATE TABLE IF NOT EXISTS `gestao_documentacoes_empresa` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo_documentacao_id` int(11) NOT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pendente','recebido','atrasado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `data_recebimento` date DEFAULT NULL,
  `usuario_recebimento_id` int(11) DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_empresas`
--

CREATE TABLE IF NOT EXISTS `gestao_empresas` (
  `id` int(11) NOT NULL,
  `razao_social` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_fantasia` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(18) COLLATE utf8mb4_unicode_ci NOT NULL,
  `regime_tributario` enum('MEI','Simples Nacional','Lucro Real','Lucro Presumido') COLLATE utf8mb4_unicode_ci NOT NULL,
  `atividade` enum('Serviço','Comércio','Indústria') COLLATE utf8mb4_unicode_ci NOT NULL,
  `endereco` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Fazendo dump de dados para tabela `gestao_empresas`
--

INSERT INTO `gestao_empresas` (`id`, `razao_social`, `nome_fantasia`, `cnpj`, `regime_tributario`, `atividade`, `endereco`, `telefone`, `email`, `ativo`, `created_at`, `updated_at`) VALUES
(22, 'Janco Assessoria Contábil Ltda', 'Janco Contabilidade', '12.345.678/0001-99', 'Simples Nacional', 'Serviço', NULL, NULL, NULL, 1, '2025-11-10 18:29:24', '2025-11-10 18:50:07'),
(26, 'Tech Solutions Brasil Ltda', 'Tech Solutions', '12.388.678/0001-99', 'Simples Nacional', 'Serviço', NULL, NULL, NULL, 1, '2025-11-10 18:30:21', '2025-11-10 18:50:07'),
(33, 'Global Services Consultoria Ltda', 'Global Services', '12.345.978/0001-99', 'Simples Nacional', 'Serviço', NULL, NULL, NULL, 1, '2025-11-10 18:39:53', '2025-11-10 18:50:07');

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_historicos_documentacao`
--

CREATE TABLE IF NOT EXISTS `gestao_historicos_documentacao` (
  `id` int(11) NOT NULL,
  `documentacao_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `acao` enum('criacao','edicao','recebimento','exclusao') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_historicos_processo`
--

CREATE TABLE IF NOT EXISTS `gestao_historicos_processo` (
  `id` int(11) NOT NULL,
  `processo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `acao` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dados_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_anteriores`)),
  `dados_novos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_novos`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_logs_sistema`
--

CREATE TABLE IF NOT EXISTS `gestao_logs_sistema` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Fazendo dump de dados para tabela `gestao_logs_sistema`
--

INSERT INTO `gestao_logs_sistema` (`id`, `usuario_id`, `acao`, `descricao`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'LOGIN', 'Usuário admin fez login no sistema de gestão', '181.115.172.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-10 18:59:19'),
(2, 2, 'LOGIN', 'Usuário admin fez login no sistema de gestão', '138.219.241.81', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0', '2025-11-10 19:00:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_processos`
--

CREATE TABLE IF NOT EXISTS `gestao_processos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `empresa_id` int(11) DEFAULT NULL,
  `responsavel_id` int(11) NOT NULL,
  `criador_id` int(11) NOT NULL,
  `prioridade` enum('baixa','media','alta','urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'media',
  `status` enum('rascunho','pendente','em_andamento','concluido','cancelado','pausado') COLLATE utf8mb4_unicode_ci DEFAULT 'rascunho',
  `data_inicio` date DEFAULT NULL,
  `data_prevista` date DEFAULT NULL,
  `data_conclusao` date DEFAULT NULL,
  `percentual_conclusao` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `recorrente` enum('nao','semanal','mensal','trimestral') COLLATE utf8mb4_unicode_ci DEFAULT 'nao',
  `data_inicio_recorrente` date DEFAULT NULL,
  `proxima_execucao` date DEFAULT NULL,
  `processo_original_id` int(11) DEFAULT NULL,
  `progresso` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_processo_checklist`
--

CREATE TABLE IF NOT EXISTS `gestao_processo_checklist` (
  `id` int(11) NOT NULL,
  `processo_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `concluido` tinyint(1) DEFAULT 0,
  `data_conclusao` datetime DEFAULT NULL,
  `usuario_conclusao_id` int(11) DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_processo_empresas`
--

CREATE TABLE IF NOT EXISTS `gestao_processo_empresas` (
  `id` int(11) NOT NULL,
  `processo_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_tipos_documentacao`
--

CREATE TABLE IF NOT EXISTS `gestao_tipos_documentacao` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorrencia` enum('unica','mensal','trimestral','semestral','anual') COLLATE utf8mb4_unicode_ci DEFAULT 'unica',
  `prazo_dias` int(11) DEFAULT 30,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_user_empresa`
--

CREATE TABLE IF NOT EXISTS `gestao_user_empresa` (
  `user_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `principal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Fazendo dump de dados para tabela `gestao_user_empresa`
--

INSERT INTO `gestao_user_empresa` (`user_id`, `empresa_id`, `principal`, `created_at`) VALUES
(1, 33, 1, '2025-11-10 18:39:53'),
(2, 22, 1, '2025-11-10 18:36:42'),
(12, 22, 1, '2025-11-10 18:42:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `gestao_usuarios`
--

CREATE TABLE IF NOT EXISTS `gestao_usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_completo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel_acesso` enum('admin','analista','auxiliar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auxiliar',
  `departamento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Fazendo dump de dados para tabela `gestao_usuarios`
--

INSERT INTO `gestao_usuarios` (`id`, `username`, `password`, `email`, `nome_completo`, `nivel_acesso`, `departamento`, `cargo`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'novousuario', '$2y$10$ebfduxMc.Z1rCd8o4pbomu.8yjsCfWxRXi2yZYVhxwjx23tKZHqOK', 'novousuario@empresa.com', 'Nome Completo do Usuário', 'admin', 'Administrativo', 'Gerente', 1, '2025-11-10 18:04:41', '2025-11-10 18:56:53'),
(2, 'admin', '$2y$10$ebfduxMc.Z1rCd8o4pbomu.8yjsCfWxRXi2yZYVhxwjx23tKZHqOK', 'admin@empresa.com', 'Nome Completo do Usuário', 'admin', 'Administrativo', 'Gerente', 1, '2025-11-10 18:06:31', '2025-11-10 18:56:53'),
(12, 'admin123', '$2y$10$ebfduxMc.Z1rCd8o4pbomu.8yjsCfWxRXi2yZYVhxwjx23tKZHqOK', 'admin@jancodev.com', 'Administrador do Sistema', 'admin', 'TI', 'Administrador', 1, '2025-11-10 18:26:24', '2025-11-10 18:56:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos_calculo`
--

CREATE TABLE IF NOT EXISTS `grupos_calculo` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `informacoes_adicionais` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota_fiscal_id` int(11) DEFAULT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos_calculo_cesta`
--

CREATE TABLE IF NOT EXISTS `grupos_calculo_cesta` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota_fiscal_id` int(11) DEFAULT NULL,
  `informacoes_adicionais` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupos_calculo_difal`
--

CREATE TABLE IF NOT EXISTS `grupos_calculo_difal` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nota_fiscal_id` int(11) DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_calculo_produtos`
--

CREATE TABLE IF NOT EXISTS `grupo_calculo_produtos` (
  `id` int(11) NOT NULL,
  `grupo_calculo_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_difal_produtos`
--

CREATE TABLE IF NOT EXISTS `grupo_difal_produtos` (
  `id` int(11) NOT NULL,
  `grupo_difal_id` int(11) DEFAULT NULL,
  `produto_id` int(11) DEFAULT NULL,
  `valor_base` decimal(15,2) DEFAULT NULL,
  `valor_difal` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `grupo_itens`
--

CREATE TABLE IF NOT EXISTS `grupo_itens` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `nfe_item_id` int(11) NOT NULL,
  `quantidade` decimal(15,4) DEFAULT 1.0000,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `livro_caixa`
--

CREATE TABLE IF NOT EXISTS `livro_caixa` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data` date NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('receita','despesa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_registro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `nfce`
--

CREATE TABLE IF NOT EXISTS `nfce` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `chave_acesso` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_emissao` date DEFAULT NULL,
  `emitente_cnpj` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emitente_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatario_cpf_cnpj` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatario_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `valor_produtos` decimal(15,2) DEFAULT NULL,
  `valor_desconto` decimal(15,2) DEFAULT NULL,
  `valor_troco` decimal(15,2) DEFAULT NULL,
  `valor_pago` decimal(15,2) DEFAULT NULL,
  `forma_pagamento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_operacao` enum('entrada','saida') COLLATE utf8mb4_unicode_ci DEFAULT 'saida',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `competencia_ano` int(11) DEFAULT NULL,
  `competencia_mes` int(11) DEFAULT NULL,
  `data_importacao` timestamp NULL DEFAULT current_timestamp(),
  `uf_emitente` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf_destinatario` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `nfce_itens`
--

CREATE TABLE IF NOT EXISTS `nfce_itens` (
  `id` int(11) NOT NULL,
  `nfce_id` int(11) NOT NULL,
  `numero_item` int(11) DEFAULT NULL,
  `codigo_produto` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncm` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cfop` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade` decimal(15,4) DEFAULT NULL,
  `valor_unitario` decimal(15,4) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `valor_desconto` decimal(15,2) DEFAULT NULL,
  `codigo_gtin` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `nfe`
--

CREATE TABLE IF NOT EXISTS `nfe` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `chave_acesso` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_emissao` date DEFAULT NULL,
  `data_entrada_saida` date DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `emitente_cnpj` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emitente_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatario_cnpj` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destinatario_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indicador_ie_dest` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `valor_produtos` decimal(15,2) DEFAULT NULL,
  `valor_desconto` decimal(15,2) DEFAULT NULL,
  `valor_frete` decimal(15,2) DEFAULT NULL,
  `valor_seguro` decimal(15,2) DEFAULT NULL,
  `valor_outras_despesas` decimal(15,2) DEFAULT NULL,
  `valor_ipi` decimal(15,2) DEFAULT NULL,
  `valor_ii` decimal(15,2) DEFAULT 0.00,
  `valor_ipi_devol` decimal(15,2) DEFAULT 0.00,
  `valor_icms` decimal(15,2) DEFAULT NULL,
  `valor_icms_deson` decimal(15,2) DEFAULT 0.00,
  `valor_fcp` decimal(15,2) DEFAULT 0.00,
  `valor_bc_st` decimal(15,2) DEFAULT 0.00,
  `valor_pis` decimal(15,2) DEFAULT NULL,
  `valor_cofins` decimal(15,2) DEFAULT NULL,
  `valor_icms_st` decimal(15,2) DEFAULT NULL,
  `valor_fcp_st` decimal(15,2) DEFAULT 0.00,
  `valor_fcp_st_ret` decimal(15,2) DEFAULT 0.00,
  `modalidade_frete` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_operacao` enum('entrada','saida') COLLATE utf8mb4_unicode_ci DEFAULT 'entrada',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  `competencia_ano` int(11) DEFAULT NULL,
  `competencia_mes` int(11) DEFAULT NULL,
  `data_importacao` timestamp NULL DEFAULT current_timestamp(),
  `pICMS` decimal(5,2) DEFAULT 0.00,
  `uf_emitente` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf_destinatario` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `nfe_itens`
--

CREATE TABLE IF NOT EXISTS `nfe_itens` (
  `id` int(11) NOT NULL,
  `nfe_id` int(11) NOT NULL,
  `numero_item` int(11) DEFAULT NULL,
  `codigo_produto` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncm` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cfop` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidade` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade` decimal(15,4) DEFAULT NULL,
  `valor_unitario` decimal(15,4) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `valor_desconto` decimal(15,2) DEFAULT NULL,
  `valor_frete` decimal(15,2) DEFAULT NULL,
  `valor_seguro` decimal(15,2) DEFAULT NULL,
  `valor_outras_despesas` decimal(15,2) DEFAULT NULL,
  `valor_ipi` decimal(15,2) DEFAULT NULL,
  `valor_icms` decimal(15,2) DEFAULT NULL,
  `valor_pis` decimal(15,2) DEFAULT NULL,
  `valor_cofins` decimal(15,2) DEFAULT NULL,
  `valor_icms_st` decimal(15,2) DEFAULT NULL,
  `codigo_gtin` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pICMS` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notas_fiscais`
--

CREATE TABLE IF NOT EXISTS `notas_fiscais` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `numero` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_emissao` date DEFAULT NULL,
  `emitente_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `competencia_ano` int(11) DEFAULT NULL,
  `competencia_mes` int(11) DEFAULT NULL,
  `data_importacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `relatorios_contestacao_difal`
--

CREATE TABLE IF NOT EXISTS `relatorios_contestacao_difal` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nota_fiscal_id` int(11) NOT NULL,
  `competencia` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chave_nota` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao_produtos` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icms_sefaz` decimal(10,2) DEFAULT 0.00,
  `fecoep_sefaz` decimal(10,2) DEFAULT 0.00,
  `icms_manual` decimal(10,2) DEFAULT 0.00,
  `fecoep_manual` decimal(10,2) DEFAULT 0.00,
  `numero_item` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '6',
  `observacoes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `responsibles`
--

CREATE TABLE IF NOT EXISTS `responsibles` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tabela_contabil`
--

CREATE TABLE IF NOT EXISTS `tabela_contabil` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `pagamento` date DEFAULT NULL,
  `cod_conta_debito` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conta_credito` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vr_liquido` decimal(15,2) DEFAULT NULL,
  `cod_historico` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento_historico` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicia_lote` enum('S','N') COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `data_importacao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tabela_fiscal`
--

CREATE TABLE IF NOT EXISTS `tabela_fiscal` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `documento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vencimento` date DEFAULT NULL,
  `pagamento` date DEFAULT NULL,
  `vr_liquido` decimal(15,2) DEFAULT NULL,
  `valor_juros` decimal(15,2) DEFAULT 0.00,
  `valor_multa` decimal(15,2) DEFAULT 0.00,
  `valor_desconto` decimal(15,2) DEFAULT 0.00,
  `valor_pis` decimal(15,2) DEFAULT 0.00,
  `valor_cofins` decimal(15,2) DEFAULT 0.00,
  `valor_csll` decimal(15,2) DEFAULT 0.00,
  `valor_irrf` decimal(15,2) DEFAULT 0.00,
  `banco` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome_cliente` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `historico` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota_fiscal` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_importacao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_empresa`
--

CREATE TABLE IF NOT EXISTS `user_empresa` (
  `user_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices de tabelas apagadas
--

--
-- Índices de tabela `adm`
--
ALTER TABLE `adm`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Índices de tabela `calculos_cesta_basica`
--
ALTER TABLE `calculos_cesta_basica`
  ADD PRIMARY KEY (`id`), ADD KEY `grupo_id` (`grupo_id`);

--
-- Índices de tabela `calculos_difal`
--
ALTER TABLE `calculos_difal`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `calculos_difal_itens`
--
ALTER TABLE `calculos_difal_itens`
  ADD PRIMARY KEY (`id`), ADD KEY `calculo_id` (`calculo_id`);

--
-- Índices de tabela `calculos_difal_manuais`
--
ALTER TABLE `calculos_difal_manuais`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`), ADD KEY `nota_fiscal_id` (`nota_fiscal_id`);

--
-- Índices de tabela `calculos_fronteira`
--
ALTER TABLE `calculos_fronteira`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`), ADD KEY `grupo_id` (`grupo_id`);

--
-- Índices de tabela `cesta_calculo_produtos`
--
ALTER TABLE `cesta_calculo_produtos`
  ADD PRIMARY KEY (`id`), ADD KEY `calculo_cesta_id` (`calculo_cesta_id`), ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `contas_pagar_santana`
--
ALTER TABLE `contas_pagar_santana`
  ADD PRIMARY KEY (`id`), ADD KEY `idx_usuario_competencia` (`usuario_id`,`competencia_ano`,`competencia_mes`), ADD KEY `idx_vencimento` (`vencimento`), ADD KEY `idx_fornecedor` (`razao_social_fornecedor`);

--
-- Índices de tabela `dados_sefaz_difal`
--
ALTER TABLE `dados_sefaz_difal`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`), ADD KEY `nota_fiscal_id` (`nota_fiscal_id`);

--
-- Índices de tabela `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `cnpj` (`cnpj`), ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `gestao_anexos_processo`
--
ALTER TABLE `gestao_anexos_processo`
  ADD PRIMARY KEY (`id`), ADD KEY `processo_id` (`processo_id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `gestao_atividades`
--
ALTER TABLE `gestao_atividades`
  ADD PRIMARY KEY (`id`), ADD KEY `processo_id` (`processo_id`), ADD KEY `responsavel_id` (`responsavel_id`), ADD KEY `depende_de` (`depende_de`);

--
-- Índices de tabela `gestao_categorias_processo`
--
ALTER TABLE `gestao_categorias_processo`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `gestao_comentarios_processo`
--
ALTER TABLE `gestao_comentarios_processo`
  ADD PRIMARY KEY (`id`), ADD KEY `processo_id` (`processo_id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `gestao_configuracoes_sistema`
--
ALTER TABLE `gestao_configuracoes_sistema`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `gestao_documentacoes_empresa`
--
ALTER TABLE `gestao_documentacoes_empresa`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `unique_documentacao_competencia` (`empresa_id`,`tipo_documentacao_id`,`competencia`), ADD KEY `tipo_documentacao_id` (`tipo_documentacao_id`), ADD KEY `usuario_recebimento_id` (`usuario_recebimento_id`);

--
-- Índices de tabela `gestao_empresas`
--
ALTER TABLE `gestao_empresas`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `cnpj` (`cnpj`);

--
-- Índices de tabela `gestao_historicos_documentacao`
--
ALTER TABLE `gestao_historicos_documentacao`
  ADD PRIMARY KEY (`id`), ADD KEY `documentacao_id` (`documentacao_id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `gestao_historicos_processo`
--
ALTER TABLE `gestao_historicos_processo`
  ADD PRIMARY KEY (`id`), ADD KEY `processo_id` (`processo_id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `gestao_logs_sistema`
--
ALTER TABLE `gestao_logs_sistema`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `gestao_processos`
--
ALTER TABLE `gestao_processos`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `codigo` (`codigo`), ADD KEY `categoria_id` (`categoria_id`), ADD KEY `empresa_id` (`empresa_id`), ADD KEY `responsavel_id` (`responsavel_id`), ADD KEY `criador_id` (`criador_id`);

--
-- Índices de tabela `gestao_processo_checklist`
--
ALTER TABLE `gestao_processo_checklist`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `unique_processo_empresa` (`processo_id`,`empresa_id`), ADD KEY `empresa_id` (`empresa_id`), ADD KEY `usuario_conclusao_id` (`usuario_conclusao_id`);

--
-- Índices de tabela `gestao_processo_empresas`
--
ALTER TABLE `gestao_processo_empresas`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `processo_empresa_unique` (`processo_id`,`empresa_id`), ADD KEY `empresa_id` (`empresa_id`);

--
-- Índices de tabela `gestao_tipos_documentacao`
--
ALTER TABLE `gestao_tipos_documentacao`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `gestao_user_empresa`
--
ALTER TABLE `gestao_user_empresa`
  ADD PRIMARY KEY (`user_id`,`empresa_id`), ADD KEY `empresa_id` (`empresa_id`);

--
-- Índices de tabela `gestao_usuarios`
--
ALTER TABLE `gestao_usuarios`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`), ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `grupos_calculo`
--
ALTER TABLE `grupos_calculo`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`), ADD KEY `nota_fiscal_id` (`nota_fiscal_id`);

--
-- Índices de tabela `grupos_calculo_cesta`
--
ALTER TABLE `grupos_calculo_cesta`
  ADD PRIMARY KEY (`id`), ADD KEY `nota_fiscal_id` (`nota_fiscal_id`);

--
-- Índices de tabela `grupos_calculo_difal`
--
ALTER TABLE `grupos_calculo_difal`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`), ADD KEY `nota_fiscal_id` (`nota_fiscal_id`);

--
-- Índices de tabela `grupo_calculo_produtos`
--
ALTER TABLE `grupo_calculo_produtos`
  ADD PRIMARY KEY (`id`), ADD KEY `grupo_calculo_id` (`grupo_calculo_id`), ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `grupo_difal_produtos`
--
ALTER TABLE `grupo_difal_produtos`
  ADD PRIMARY KEY (`id`), ADD KEY `grupo_difal_id` (`grupo_difal_id`), ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `grupo_itens`
--
ALTER TABLE `grupo_itens`
  ADD PRIMARY KEY (`id`), ADD KEY `grupo_id` (`grupo_id`), ADD KEY `nfe_item_id` (`nfe_item_id`);

--
-- Índices de tabela `livro_caixa`
--
ALTER TABLE `livro_caixa`
  ADD PRIMARY KEY (`id`), ADD KEY `fk_livro_caixa_usuario` (`usuario_id`);

--
-- Índices de tabela `nfce`
--
ALTER TABLE `nfce`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `chave_acesso` (`chave_acesso`), ADD UNIQUE KEY `idx_unique_nfce` (`usuario_id`,`chave_acesso`,`tipo_operacao`);

--
-- Índices de tabela `nfce_itens`
--
ALTER TABLE `nfce_itens`
  ADD PRIMARY KEY (`id`), ADD KEY `nfce_id` (`nfce_id`);

--
-- Índices de tabela `nfe`
--
ALTER TABLE `nfe`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `idx_unique_nfe` (`usuario_id`,`chave_acesso`,`tipo_operacao`);

--
-- Índices de tabela `nfe_itens`
--
ALTER TABLE `nfe_itens`
  ADD PRIMARY KEY (`id`), ADD KEY `nfe_id` (`nfe_id`);

--
-- Índices de tabela `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `relatorios_contestacao_difal`
--
ALTER TABLE `relatorios_contestacao_difal`
  ADD PRIMARY KEY (`id`), ADD KEY `nota_fiscal_id` (`nota_fiscal_id`);

--
-- Índices de tabela `responsibles`
--
ALTER TABLE `responsibles`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `tabela_contabil`
--
ALTER TABLE `tabela_contabil`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `tabela_fiscal`
--
ALTER TABLE `tabela_fiscal`
  ADD PRIMARY KEY (`id`), ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`), ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `user_empresa`
--
ALTER TABLE `user_empresa`
  ADD PRIMARY KEY (`user_id`,`empresa_id`), ADD KEY `empresa_id` (`empresa_id`);

--
-- AUTO_INCREMENT de tabelas apagadas
--

--
-- AUTO_INCREMENT de tabela `adm`
--
ALTER TABLE `adm`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `calculos_cesta_basica`
--
ALTER TABLE `calculos_cesta_basica`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `calculos_difal`
--
ALTER TABLE `calculos_difal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `calculos_difal_itens`
--
ALTER TABLE `calculos_difal_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `calculos_difal_manuais`
--
ALTER TABLE `calculos_difal_manuais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `calculos_fronteira`
--
ALTER TABLE `calculos_fronteira`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `cesta_calculo_produtos`
--
ALTER TABLE `cesta_calculo_produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `contas_pagar_santana`
--
ALTER TABLE `contas_pagar_santana`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `dados_sefaz_difal`
--
ALTER TABLE `dados_sefaz_difal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_anexos_processo`
--
ALTER TABLE `gestao_anexos_processo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_atividades`
--
ALTER TABLE `gestao_atividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_categorias_processo`
--
ALTER TABLE `gestao_categorias_processo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_comentarios_processo`
--
ALTER TABLE `gestao_comentarios_processo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_configuracoes_sistema`
--
ALTER TABLE `gestao_configuracoes_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_documentacoes_empresa`
--
ALTER TABLE `gestao_documentacoes_empresa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_empresas`
--
ALTER TABLE `gestao_empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=34;
--
-- AUTO_INCREMENT de tabela `gestao_historicos_documentacao`
--
ALTER TABLE `gestao_historicos_documentacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_historicos_processo`
--
ALTER TABLE `gestao_historicos_processo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_logs_sistema`
--
ALTER TABLE `gestao_logs_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT de tabela `gestao_processos`
--
ALTER TABLE `gestao_processos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_processo_checklist`
--
ALTER TABLE `gestao_processo_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_processo_empresas`
--
ALTER TABLE `gestao_processo_empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_tipos_documentacao`
--
ALTER TABLE `gestao_tipos_documentacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `gestao_usuarios`
--
ALTER TABLE `gestao_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=19;
--
-- AUTO_INCREMENT de tabela `grupos_calculo`
--
ALTER TABLE `grupos_calculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `grupos_calculo_cesta`
--
ALTER TABLE `grupos_calculo_cesta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `grupos_calculo_difal`
--
ALTER TABLE `grupos_calculo_difal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `grupo_calculo_produtos`
--
ALTER TABLE `grupo_calculo_produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `grupo_difal_produtos`
--
ALTER TABLE `grupo_difal_produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `grupo_itens`
--
ALTER TABLE `grupo_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `livro_caixa`
--
ALTER TABLE `livro_caixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `nfce`
--
ALTER TABLE `nfce`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `nfce_itens`
--
ALTER TABLE `nfce_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `nfe`
--
ALTER TABLE `nfe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `nfe_itens`
--
ALTER TABLE `nfe_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `relatorios_contestacao_difal`
--
ALTER TABLE `relatorios_contestacao_difal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `responsibles`
--
ALTER TABLE `responsibles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `tabela_contabil`
--
ALTER TABLE `tabela_contabil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `tabela_fiscal`
--
ALTER TABLE `tabela_fiscal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Restrições para dumps de tabelas
--

--
-- Restrições para tabelas `adm`
--
ALTER TABLE `adm`
ADD CONSTRAINT `adm_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `calculos_cesta_basica`
--
ALTER TABLE `calculos_cesta_basica`
ADD CONSTRAINT `calculos_cesta_basica_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_calculo_cesta` (`id`);

--
-- Restrições para tabelas `calculos_difal`
--
ALTER TABLE `calculos_difal`
ADD CONSTRAINT `calculos_difal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `calculos_difal_manuais`
--
ALTER TABLE `calculos_difal_manuais`
ADD CONSTRAINT `calculos_difal_manuais_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `calculos_difal_manuais_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `calculos_fronteira`
--
ALTER TABLE `calculos_fronteira`
ADD CONSTRAINT `calculos_fronteira_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `calculos_fronteira_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_calculo` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `cesta_calculo_produtos`
--
ALTER TABLE `cesta_calculo_produtos`
ADD CONSTRAINT `cesta_calculo_produtos_ibfk_1` FOREIGN KEY (`calculo_cesta_id`) REFERENCES `calculos_cesta_basica` (`id`),
ADD CONSTRAINT `cesta_calculo_produtos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `nfe_itens` (`id`);

--
-- Restrições para tabelas `contas_pagar_santana`
--
ALTER TABLE `contas_pagar_santana`
ADD CONSTRAINT `contas_pagar_santana_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `dados_sefaz_difal`
--
ALTER TABLE `dados_sefaz_difal`
ADD CONSTRAINT `dados_sefaz_difal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `dados_sefaz_difal_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `empresas`
--
ALTER TABLE `empresas`
ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `gestao_anexos_processo`
--
ALTER TABLE `gestao_anexos_processo`
ADD CONSTRAINT `gestao_anexos_processo_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `gestao_anexos_processo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`);

--
-- Restrições para tabelas `gestao_atividades`
--
ALTER TABLE `gestao_atividades`
ADD CONSTRAINT `gestao_atividades_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `gestao_atividades_ibfk_2` FOREIGN KEY (`responsavel_id`) REFERENCES `gestao_usuarios` (`id`),
ADD CONSTRAINT `gestao_atividades_ibfk_3` FOREIGN KEY (`depende_de`) REFERENCES `gestao_atividades` (`id`);

--
-- Restrições para tabelas `gestao_comentarios_processo`
--
ALTER TABLE `gestao_comentarios_processo`
ADD CONSTRAINT `gestao_comentarios_processo_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `gestao_comentarios_processo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`);

--
-- Restrições para tabelas `gestao_documentacoes_empresa`
--
ALTER TABLE `gestao_documentacoes_empresa`
ADD CONSTRAINT `gestao_documentacoes_empresa_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
ADD CONSTRAINT `gestao_documentacoes_empresa_ibfk_2` FOREIGN KEY (`tipo_documentacao_id`) REFERENCES `gestao_tipos_documentacao` (`id`),
ADD CONSTRAINT `gestao_documentacoes_empresa_ibfk_3` FOREIGN KEY (`usuario_recebimento_id`) REFERENCES `gestao_usuarios` (`id`);

--
-- Restrições para tabelas `gestao_historicos_documentacao`
--
ALTER TABLE `gestao_historicos_documentacao`
ADD CONSTRAINT `gestao_historicos_documentacao_ibfk_1` FOREIGN KEY (`documentacao_id`) REFERENCES `gestao_documentacoes_empresa` (`id`),
ADD CONSTRAINT `gestao_historicos_documentacao_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`);

--
-- Restrições para tabelas `gestao_historicos_processo`
--
ALTER TABLE `gestao_historicos_processo`
ADD CONSTRAINT `gestao_historicos_processo_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `gestao_historicos_processo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`);

--
-- Restrições para tabelas `gestao_logs_sistema`
--
ALTER TABLE `gestao_logs_sistema`
ADD CONSTRAINT `gestao_logs_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `gestao_processos`
--
ALTER TABLE `gestao_processos`
ADD CONSTRAINT `gestao_processos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `gestao_categorias_processo` (`id`),
ADD CONSTRAINT `gestao_processos_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON UPDATE CASCADE,
ADD CONSTRAINT `gestao_processos_ibfk_3` FOREIGN KEY (`responsavel_id`) REFERENCES `gestao_usuarios` (`id`),
ADD CONSTRAINT `gestao_processos_ibfk_4` FOREIGN KEY (`criador_id`) REFERENCES `gestao_usuarios` (`id`);

--
-- Restrições para tabelas `gestao_processo_checklist`
--
ALTER TABLE `gestao_processo_checklist`
ADD CONSTRAINT `gestao_processo_checklist_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`),
ADD CONSTRAINT `gestao_processo_checklist_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
ADD CONSTRAINT `gestao_processo_checklist_ibfk_3` FOREIGN KEY (`usuario_conclusao_id`) REFERENCES `gestao_usuarios` (`id`);

--
-- Restrições para tabelas `gestao_processo_empresas`
--
ALTER TABLE `gestao_processo_empresas`
ADD CONSTRAINT `gestao_processo_empresas_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `gestao_processo_empresas_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `gestao_user_empresa`
--
ALTER TABLE `gestao_user_empresa`
ADD CONSTRAINT `gestao_user_empresa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `gestao_usuarios` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `gestao_user_empresa_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `gestao_empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `grupos_calculo`
--
ALTER TABLE `grupos_calculo`
ADD CONSTRAINT `grupos_calculo_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `grupos_calculo_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `grupos_calculo_cesta`
--
ALTER TABLE `grupos_calculo_cesta`
ADD CONSTRAINT `grupos_calculo_cesta_ibfk_1` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`);

--
-- Restrições para tabelas `grupos_calculo_difal`
--
ALTER TABLE `grupos_calculo_difal`
ADD CONSTRAINT `grupos_calculo_difal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`),
ADD CONSTRAINT `grupos_calculo_difal_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`);

--
-- Restrições para tabelas `grupo_calculo_produtos`
--
ALTER TABLE `grupo_calculo_produtos`
ADD CONSTRAINT `grupo_calculo_produtos_ibfk_1` FOREIGN KEY (`grupo_calculo_id`) REFERENCES `grupos_calculo` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `grupo_calculo_produtos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `nfe_itens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `grupo_difal_produtos`
--
ALTER TABLE `grupo_difal_produtos`
ADD CONSTRAINT `grupo_difal_produtos_ibfk_1` FOREIGN KEY (`grupo_difal_id`) REFERENCES `grupos_calculo_difal` (`id`),
ADD CONSTRAINT `grupo_difal_produtos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `nfe_itens` (`id`);

--
-- Restrições para tabelas `grupo_itens`
--
ALTER TABLE `grupo_itens`
ADD CONSTRAINT `grupo_itens_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_calculo` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `grupo_itens_ibfk_2` FOREIGN KEY (`nfe_item_id`) REFERENCES `nfe_itens` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `livro_caixa`
--
ALTER TABLE `livro_caixa`
ADD CONSTRAINT `fk_livro_caixa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `nfce`
--
ALTER TABLE `nfce`
ADD CONSTRAINT `nfce_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `nfce_itens`
--
ALTER TABLE `nfce_itens`
ADD CONSTRAINT `nfce_itens_ibfk_1` FOREIGN KEY (`nfce_id`) REFERENCES `nfce` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `nfe`
--
ALTER TABLE `nfe`
ADD CONSTRAINT `nfe_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `nfe_itens`
--
ALTER TABLE `nfe_itens`
ADD CONSTRAINT `nfe_itens_ibfk_1` FOREIGN KEY (`nfe_id`) REFERENCES `nfe` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notas_fiscais`
--
ALTER TABLE `notas_fiscais`
ADD CONSTRAINT `notas_fiscais_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `relatorios_contestacao_difal`
--
ALTER TABLE `relatorios_contestacao_difal`
ADD CONSTRAINT `relatorios_contestacao_difal_ibfk_1` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`);

--
-- Restrições para tabelas `tabela_contabil`
--
ALTER TABLE `tabela_contabil`
ADD CONSTRAINT `tabela_contabil_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `tabela_fiscal`
--
ALTER TABLE `tabela_fiscal`
ADD CONSTRAINT `tabela_fiscal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `user_empresa`
--
ALTER TABLE `user_empresa`
ADD CONSTRAINT `user_empresa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `user_empresa_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
