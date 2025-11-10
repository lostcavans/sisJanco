-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: sistema_contabil
-- ------------------------------------------------------
-- Server version	9.3.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `adm`
--

DROP TABLE IF EXISTS `adm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `nivel` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `adm_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calculos_cesta_basica`
--

DROP TABLE IF EXISTS `calculos_cesta_basica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calculos_cesta_basica` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `grupo_id` int NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `regiao_fornecedor` varchar(50) NOT NULL,
  `valor_total_produtos` decimal(15,2) NOT NULL,
  `valor_total_icms` decimal(15,2) NOT NULL,
  `competencia` varchar(7) NOT NULL,
  `data_calculo` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `peso_agrupado` decimal(15,3) DEFAULT NULL,
  `unidade_medida` varchar(10) DEFAULT NULL,
  `quantidade` decimal(15,3) DEFAULT NULL,
  `percentual_pauta` decimal(5,2) DEFAULT NULL,
  `carga_tributaria` decimal(5,2) NOT NULL,
  `resultado_pauta` decimal(15,2) DEFAULT NULL,
  `base_calculo` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grupo_id` (`grupo_id`),
  CONSTRAINT `calculos_cesta_basica_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_calculo_cesta` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calculos_difal`
--

DROP TABLE IF EXISTS `calculos_difal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calculos_difal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `grupo_id` int DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `valor_base_calculo` decimal(15,2) DEFAULT NULL,
  `valor_difal` decimal(15,2) DEFAULT NULL,
  `aliquota_interna` decimal(5,2) DEFAULT NULL,
  `aliquota_interestadual` decimal(5,2) DEFAULT NULL,
  `tipo_calculo` enum('normal','simples','reducao') DEFAULT NULL,
  `competencia` varchar(7) DEFAULT NULL,
  `data_calculo` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `calculos_difal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calculos_difal_itens`
--

DROP TABLE IF EXISTS `calculos_difal_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calculos_difal_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `calculo_id` int NOT NULL,
  `numero_item` int DEFAULT NULL,
  `descricao_produto` text,
  `ncm` varchar(10) DEFAULT NULL,
  `valor_produto` decimal(15,2) DEFAULT NULL,
  `aliquota_difal` decimal(5,4) DEFAULT NULL,
  `aliquota_fecoep` decimal(5,4) DEFAULT NULL,
  `aliquota_reducao` decimal(5,4) DEFAULT '0.0000',
  `valor_difal` decimal(15,2) DEFAULT NULL,
  `valor_fecoep` decimal(15,2) DEFAULT NULL,
  `valor_total_impostos` decimal(15,2) DEFAULT NULL,
  `data_atualizacao` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `calculo_id` (`calculo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=596 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calculos_difal_manuais`
--

DROP TABLE IF EXISTS `calculos_difal_manuais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calculos_difal_manuais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `nota_fiscal_id` int DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `competencia` varchar(7) NOT NULL,
  `data_calculo` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `calculos_difal_manuais_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calculos_difal_manuais_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calculos_fronteira`
--

DROP TABLE IF EXISTS `calculos_fronteira`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calculos_fronteira` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `competencia` varchar(7) NOT NULL,
  `grupo_id` int DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `considera_desconto` enum('S','N') DEFAULT 'S',
  `tipo_credito_icms` enum('manual','nota') DEFAULT 'nota',
  `valor_produto` decimal(15,2) DEFAULT '0.00',
  `valor_frete` decimal(15,2) DEFAULT '0.00',
  `valor_ipi` decimal(15,2) DEFAULT '0.00',
  `valor_seguro` decimal(15,2) DEFAULT '0.00',
  `valor_desconto` decimal(15,2) DEFAULT '0.00',
  `valor_icms` decimal(15,2) DEFAULT '0.00',
  `valor_gnre` decimal(15,2) DEFAULT '0.00',
  `aliquota_interna` decimal(5,2) DEFAULT '20.50',
  `aliquota_interestadual` decimal(5,2) DEFAULT '0.00',
  `mva_cnae` decimal(5,2) DEFAULT '0.00',
  `mva_original` decimal(5,2) DEFAULT '0.00',
  `difal` decimal(5,2) DEFAULT '0.00',
  `aliquota_credito` decimal(5,2) DEFAULT '0.00',
  `aliquota_reducao` decimal(5,2) DEFAULT '0.00',
  `regime_fornecedor` enum('1','2','3') DEFAULT '3',
  `empresa_regular` enum('S','N') DEFAULT 'S',
  `mva_ajustada` decimal(10,4) DEFAULT '0.0000',
  `icms_st` decimal(15,2) DEFAULT '0.00',
  `icms_tributado_simples` decimal(15,2) DEFAULT '0.00',
  `icms_tributado_real` decimal(15,2) DEFAULT '0.00',
  `icms_uso_consumo` decimal(15,2) DEFAULT '0.00',
  `icms_reducao` decimal(15,2) DEFAULT '0.00',
  `icms_reducao_sn` decimal(15,2) DEFAULT '0.00',
  `icms_reducao_st_sn` decimal(15,2) DEFAULT '0.00',
  `data_calculo` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `diferencial_aliquota` decimal(10,2) DEFAULT '0.00',
  `tipo_calculo` varchar(50) DEFAULT 'icms_st',
  `icms_tributado_simples_regular` decimal(10,2) DEFAULT '0.00',
  `icms_tributado_simples_irregular` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `grupo_id` (`grupo_id`),
  CONSTRAINT `calculos_fronteira_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calculos_fronteira_ibfk_2` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_calculo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cesta_calculo_produtos`
--

DROP TABLE IF EXISTS `cesta_calculo_produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cesta_calculo_produtos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `calculo_cesta_id` int NOT NULL,
  `produto_id` int NOT NULL,
  `carga_tributaria` decimal(5,2) NOT NULL,
  `pauta_fiscal` decimal(10,2) DEFAULT '0.00',
  `valor_icms` decimal(15,2) NOT NULL,
  `tipo_calculo` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `calculo_cesta_id` (`calculo_cesta_id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `cesta_calculo_produtos_ibfk_1` FOREIGN KEY (`calculo_cesta_id`) REFERENCES `calculos_cesta_basica` (`id`),
  CONSTRAINT `cesta_calculo_produtos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `nfe_itens` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contas_pagar_santana`
--

DROP TABLE IF EXISTS `contas_pagar_santana`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas_pagar_santana` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `parcela` varchar(20) DEFAULT NULL,
  `cod_fornecedor` varchar(50) DEFAULT NULL,
  `razao_social_fornecedor` varchar(255) DEFAULT NULL,
  `fantasia` varchar(255) DEFAULT NULL,
  `documento` varchar(100) DEFAULT NULL,
  `nota_fiscal` varchar(100) DEFAULT NULL,
  `emissao` date DEFAULT NULL,
  `vencimento` date DEFAULT NULL,
  `saldo` decimal(15,2) DEFAULT '0.00',
  `valor` decimal(15,2) DEFAULT '0.00',
  `juros` decimal(15,2) DEFAULT '0.00',
  `valor_pago` decimal(15,2) DEFAULT '0.00',
  `data_pagamento` date DEFAULT NULL,
  `obs_cp` text,
  `obs_parc` text,
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `competencia_ano` int DEFAULT NULL,
  `competencia_mes` int DEFAULT NULL,
  `data_importacao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_competencia` (`usuario_id`,`competencia_ano`,`competencia_mes`),
  KEY `idx_vencimento` (`vencimento`),
  KEY `idx_fornecedor` (`razao_social_fornecedor`),
  CONSTRAINT `contas_pagar_santana_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dados_sefaz_difal`
--

DROP TABLE IF EXISTS `dados_sefaz_difal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dados_sefaz_difal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `nota_fiscal_id` int DEFAULT NULL,
  `competencia` varchar(7) NOT NULL,
  `numero_item` int DEFAULT NULL,
  `descricao_produto` text,
  `documento_responsavel` varchar(20) DEFAULT NULL,
  `tipo_imposto` varchar(50) DEFAULT NULL,
  `valor_icms` decimal(15,2) DEFAULT NULL,
  `valor_fecoep` decimal(15,2) DEFAULT NULL,
  `aliquota_icms` decimal(5,4) DEFAULT '0.0000',
  `aliquota_fecoep` decimal(5,4) DEFAULT '0.0000',
  `aliquota_reducao` decimal(5,4) DEFAULT '0.0000',
  `mva_valor` decimal(10,4) DEFAULT NULL,
  `redutor_base` decimal(5,4) DEFAULT '0.0000',
  `redutor_credito` decimal(5,4) DEFAULT NULL,
  `segmento` varchar(100) DEFAULT NULL,
  `pauta_fiscal` decimal(10,2) DEFAULT NULL,
  `ncm` varchar(10) DEFAULT NULL,
  `cest` varchar(10) DEFAULT NULL,
  `data_processamento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `origem_dados` varchar(50) DEFAULT 'SEFAZ',
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `dados_sefaz_difal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dados_sefaz_difal_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=336 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `empresas`
--

DROP TABLE IF EXISTS `empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `razao_social` varchar(255) NOT NULL,
  `cnpj` varchar(18) NOT NULL,
  `regime_tributario` enum('MEI','Simples Nacional','Lucro Real','Lucro Presumido') NOT NULL,
  `sistema_utilizado` varchar(100) DEFAULT NULL,
  `atividade` enum('Serviço','Comércio','Indústria') NOT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnpj` (`cnpj`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_anexos_processo`
--

DROP TABLE IF EXISTS `gestao_anexos_processo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_anexos_processo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processo_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(500) NOT NULL,
  `tipo_arquivo` varchar(50) DEFAULT NULL,
  `tamanho` int DEFAULT NULL,
  `descricao` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `processo_id` (`processo_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `gestao_anexos_processo_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gestao_anexos_processo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_atividades`
--

DROP TABLE IF EXISTS `gestao_atividades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_atividades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processo_id` int NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text,
  `responsavel_id` int NOT NULL,
  `tipo_periodicidade` enum('unica','diaria','semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') DEFAULT 'unica',
  `data_prevista` date DEFAULT NULL,
  `data_conclusao` date DEFAULT NULL,
  `status` enum('pendente','em_andamento','concluida','cancelada') DEFAULT 'pendente',
  `prioridade` enum('baixa','media','alta','urgente') DEFAULT 'media',
  `ordem` int DEFAULT '0',
  `depende_de` int DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `processo_id` (`processo_id`),
  KEY `responsavel_id` (`responsavel_id`),
  KEY `depende_de` (`depende_de`),
  CONSTRAINT `gestao_atividades_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gestao_atividades_ibfk_2` FOREIGN KEY (`responsavel_id`) REFERENCES `gestao_usuarios` (`id`),
  CONSTRAINT `gestao_atividades_ibfk_3` FOREIGN KEY (`depende_de`) REFERENCES `gestao_atividades` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_categorias_processo`
--

DROP TABLE IF EXISTS `gestao_categorias_processo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_categorias_processo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text,
  `cor` varchar(7) DEFAULT '#4361ee',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_comentarios_processo`
--

DROP TABLE IF EXISTS `gestao_comentarios_processo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_comentarios_processo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processo_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `comentario` text NOT NULL,
  `tipo` enum('comentario','observacao','alerta') DEFAULT 'comentario',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `processo_id` (`processo_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `gestao_comentarios_processo_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gestao_comentarios_processo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_configuracoes_sistema`
--

DROP TABLE IF EXISTS `gestao_configuracoes_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_configuracoes_sistema` (
  `id` int NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text,
  `descricao` text,
  `tipo` enum('string','integer','boolean','json') DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_documentacoes_empresa`
--

DROP TABLE IF EXISTS `gestao_documentacoes_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_documentacoes_empresa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa_id` int NOT NULL,
  `tipo_documentacao_id` int NOT NULL,
  `competencia` varchar(7) DEFAULT NULL,
  `status` enum('pendente','recebido','atrasado') DEFAULT 'pendente',
  `data_recebimento` date DEFAULT NULL,
  `usuario_recebimento_id` int DEFAULT NULL,
  `observacoes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_documentacao_competencia` (`empresa_id`,`tipo_documentacao_id`,`competencia`),
  KEY `tipo_documentacao_id` (`tipo_documentacao_id`),
  KEY `usuario_recebimento_id` (`usuario_recebimento_id`),
  CONSTRAINT `gestao_documentacoes_empresa_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `gestao_documentacoes_empresa_ibfk_2` FOREIGN KEY (`tipo_documentacao_id`) REFERENCES `gestao_tipos_documentacao` (`id`),
  CONSTRAINT `gestao_documentacoes_empresa_ibfk_3` FOREIGN KEY (`usuario_recebimento_id`) REFERENCES `gestao_usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_empresas`
--

DROP TABLE IF EXISTS `gestao_empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_empresas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `razao_social` varchar(255) NOT NULL,
  `nome_fantasia` varchar(255) NOT NULL,
  `cnpj` varchar(18) NOT NULL,
  `regime_tributario` enum('MEI','Simples Nacional','Lucro Real','Lucro Presumido') NOT NULL,
  `atividade` enum('Serviço','Comércio','Indústria') NOT NULL,
  `endereco` text,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnpj` (`cnpj`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_historicos_documentacao`
--

DROP TABLE IF EXISTS `gestao_historicos_documentacao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_historicos_documentacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `documentacao_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `acao` enum('criacao','edicao','recebimento','exclusao') NOT NULL,
  `descricao` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `documentacao_id` (`documentacao_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `gestao_historicos_documentacao_ibfk_1` FOREIGN KEY (`documentacao_id`) REFERENCES `gestao_documentacoes_empresa` (`id`),
  CONSTRAINT `gestao_historicos_documentacao_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_historicos_processo`
--

DROP TABLE IF EXISTS `gestao_historicos_processo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_historicos_processo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processo_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `acao` varchar(50) NOT NULL,
  `descricao` text,
  `dados_anteriores` json DEFAULT NULL,
  `dados_novos` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `processo_id` (`processo_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `gestao_historicos_processo_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gestao_historicos_processo_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_logs_sistema`
--

DROP TABLE IF EXISTS `gestao_logs_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_logs_sistema` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `descricao` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `gestao_logs_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `gestao_usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_processo_checklist`
--

DROP TABLE IF EXISTS `gestao_processo_checklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_processo_checklist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processo_id` int NOT NULL,
  `empresa_id` int NOT NULL,
  `concluido` tinyint(1) DEFAULT '0',
  `data_conclusao` datetime DEFAULT NULL,
  `usuario_conclusao_id` int DEFAULT NULL,
  `observacao` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_processo_empresa` (`processo_id`,`empresa_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `usuario_conclusao_id` (`usuario_conclusao_id`),
  CONSTRAINT `gestao_processo_checklist_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`),
  CONSTRAINT `gestao_processo_checklist_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `gestao_processo_checklist_ibfk_3` FOREIGN KEY (`usuario_conclusao_id`) REFERENCES `gestao_usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_processo_empresas`
--

DROP TABLE IF EXISTS `gestao_processo_empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_processo_empresas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processo_id` int NOT NULL,
  `empresa_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `processo_empresa_unique` (`processo_id`,`empresa_id`),
  KEY `empresa_id` (`empresa_id`),
  CONSTRAINT `gestao_processo_empresas_ibfk_1` FOREIGN KEY (`processo_id`) REFERENCES `gestao_processos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gestao_processo_empresas_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_processos`
--

DROP TABLE IF EXISTS `gestao_processos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_processos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text,
  `categoria_id` int DEFAULT NULL,
  `empresa_id` int DEFAULT NULL,
  `responsavel_id` int NOT NULL,
  `criador_id` int NOT NULL,
  `prioridade` enum('baixa','media','alta','urgente') DEFAULT 'media',
  `status` enum('rascunho','pendente','em_andamento','concluido','cancelado','pausado') DEFAULT 'rascunho',
  `data_inicio` date DEFAULT NULL,
  `data_prevista` date DEFAULT NULL,
  `data_conclusao` date DEFAULT NULL,
  `percentual_conclusao` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `recorrente` enum('nao','semanal','mensal','trimestral') DEFAULT 'nao',
  `data_inicio_recorrente` date DEFAULT NULL,
  `proxima_execucao` date DEFAULT NULL,
  `processo_original_id` int DEFAULT NULL,
  `progresso` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `categoria_id` (`categoria_id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `responsavel_id` (`responsavel_id`),
  KEY `criador_id` (`criador_id`),
  CONSTRAINT `gestao_processos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `gestao_categorias_processo` (`id`),
  CONSTRAINT `gestao_processos_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `gestao_processos_ibfk_3` FOREIGN KEY (`responsavel_id`) REFERENCES `gestao_usuarios` (`id`),
  CONSTRAINT `gestao_processos_ibfk_4` FOREIGN KEY (`criador_id`) REFERENCES `gestao_usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_tipos_documentacao`
--

DROP TABLE IF EXISTS `gestao_tipos_documentacao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_tipos_documentacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `descricao` text,
  `recorrencia` enum('unica','mensal','trimestral','semestral','anual') DEFAULT 'unica',
  `prazo_dias` int DEFAULT '30',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_user_empresa`
--

DROP TABLE IF EXISTS `gestao_user_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_user_empresa` (
  `user_id` int NOT NULL,
  `empresa_id` int NOT NULL,
  `principal` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`empresa_id`),
  KEY `empresa_id` (`empresa_id`),
  CONSTRAINT `gestao_user_empresa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `gestao_usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gestao_user_empresa_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `gestao_empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gestao_usuarios`
--

DROP TABLE IF EXISTS `gestao_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gestao_usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `nivel_acesso` enum('admin','analista','auxiliar') NOT NULL DEFAULT 'auxiliar',
  `departamento` varchar(100) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grupo_calculo_produtos`
--

DROP TABLE IF EXISTS `grupo_calculo_produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupo_calculo_produtos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grupo_calculo_id` int NOT NULL,
  `produto_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grupo_calculo_id` (`grupo_calculo_id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `grupo_calculo_produtos_ibfk_1` FOREIGN KEY (`grupo_calculo_id`) REFERENCES `grupos_calculo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grupo_calculo_produtos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `nfe_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=447 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grupo_difal_produtos`
--

DROP TABLE IF EXISTS `grupo_difal_produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupo_difal_produtos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grupo_difal_id` int DEFAULT NULL,
  `produto_id` int DEFAULT NULL,
  `valor_base` decimal(15,2) DEFAULT NULL,
  `valor_difal` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grupo_difal_id` (`grupo_difal_id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `grupo_difal_produtos_ibfk_1` FOREIGN KEY (`grupo_difal_id`) REFERENCES `grupos_calculo_difal` (`id`),
  CONSTRAINT `grupo_difal_produtos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `nfe_itens` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grupo_itens`
--

DROP TABLE IF EXISTS `grupo_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupo_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grupo_id` int NOT NULL,
  `nfe_item_id` int NOT NULL,
  `quantidade` decimal(15,4) DEFAULT '1.0000',
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grupo_id` (`grupo_id`),
  KEY `nfe_item_id` (`nfe_item_id`),
  CONSTRAINT `grupo_itens_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos_calculo` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grupo_itens_ibfk_2` FOREIGN KEY (`nfe_item_id`) REFERENCES `nfe_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grupos_calculo`
--

DROP TABLE IF EXISTS `grupos_calculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_calculo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `informacoes_adicionais` text,
  `nota_fiscal_id` int DEFAULT NULL,
  `competencia` varchar(7) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `grupos_calculo_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grupos_calculo_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grupos_calculo_cesta`
--

DROP TABLE IF EXISTS `grupos_calculo_cesta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_calculo_cesta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `nota_fiscal_id` int DEFAULT NULL,
  `informacoes_adicionais` text,
  `competencia` varchar(7) NOT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `grupos_calculo_cesta_ibfk_1` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grupos_calculo_difal`
--

DROP TABLE IF EXISTS `grupos_calculo_difal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos_calculo_difal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `nota_fiscal_id` int DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `grupos_calculo_difal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`),
  CONSTRAINT `grupos_calculo_difal_ibfk_2` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `livro_caixa`
--

DROP TABLE IF EXISTS `livro_caixa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `livro_caixa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `data` date NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `data_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_livro_caixa_usuario` (`usuario_id`),
  CONSTRAINT `fk_livro_caixa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nfce`
--

DROP TABLE IF EXISTS `nfce`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfce` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `chave_acesso` varchar(44) DEFAULT NULL,
  `numero` varchar(15) DEFAULT NULL,
  `serie` varchar(5) DEFAULT NULL,
  `data_emissao` date DEFAULT NULL,
  `emitente_cnpj` varchar(18) DEFAULT NULL,
  `emitente_nome` varchar(255) DEFAULT NULL,
  `destinatario_cpf_cnpj` varchar(18) DEFAULT NULL,
  `destinatario_nome` varchar(255) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `valor_produtos` decimal(15,2) DEFAULT NULL,
  `valor_desconto` decimal(15,2) DEFAULT NULL,
  `valor_troco` decimal(15,2) DEFAULT NULL,
  `valor_pago` decimal(15,2) DEFAULT NULL,
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `tipo_operacao` enum('entrada','saida') DEFAULT 'saida',
  `status` varchar(20) DEFAULT 'ativa',
  `competencia_ano` int DEFAULT NULL,
  `competencia_mes` int DEFAULT NULL,
  `data_importacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `uf_emitente` varchar(2) DEFAULT NULL,
  `uf_destinatario` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave_acesso` (`chave_acesso`),
  UNIQUE KEY `idx_unique_nfce` (`usuario_id`,`chave_acesso`,`tipo_operacao`),
  CONSTRAINT `nfce_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=656 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nfce_itens`
--

DROP TABLE IF EXISTS `nfce_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfce_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nfce_id` int NOT NULL,
  `numero_item` int DEFAULT NULL,
  `codigo_produto` varchar(30) DEFAULT NULL,
  `descricao` text,
  `ncm` varchar(10) DEFAULT NULL,
  `cfop` varchar(10) DEFAULT NULL,
  `unidade` varchar(10) DEFAULT NULL,
  `quantidade` decimal(15,4) DEFAULT NULL,
  `valor_unitario` decimal(15,4) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `valor_desconto` decimal(15,2) DEFAULT NULL,
  `codigo_gtin` varchar(14) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nfce_id` (`nfce_id`),
  CONSTRAINT `nfce_itens_ibfk_1` FOREIGN KEY (`nfce_id`) REFERENCES `nfce` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13669 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nfe`
--

DROP TABLE IF EXISTS `nfe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `chave_acesso` varchar(44) DEFAULT NULL,
  `numero` varchar(15) DEFAULT NULL,
  `serie` varchar(5) DEFAULT NULL,
  `data_emissao` date DEFAULT NULL,
  `data_entrada_saida` date DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `emitente_cnpj` varchar(18) DEFAULT NULL,
  `emitente_nome` varchar(255) DEFAULT NULL,
  `destinatario_cnpj` varchar(18) DEFAULT NULL,
  `destinatario_nome` varchar(255) DEFAULT NULL,
  `indicador_ie_dest` varchar(2) DEFAULT NULL,
  `valor_total` decimal(15,2) DEFAULT NULL,
  `valor_produtos` decimal(15,2) DEFAULT NULL,
  `valor_desconto` decimal(15,2) DEFAULT NULL,
  `valor_frete` decimal(15,2) DEFAULT NULL,
  `valor_seguro` decimal(15,2) DEFAULT NULL,
  `valor_outras_despesas` decimal(15,2) DEFAULT NULL,
  `valor_ipi` decimal(15,2) DEFAULT NULL,
  `valor_ii` decimal(15,2) DEFAULT '0.00',
  `valor_ipi_devol` decimal(15,2) DEFAULT '0.00',
  `valor_icms` decimal(15,2) DEFAULT NULL,
  `valor_icms_deson` decimal(15,2) DEFAULT '0.00',
  `valor_fcp` decimal(15,2) DEFAULT '0.00',
  `valor_bc_st` decimal(15,2) DEFAULT '0.00',
  `valor_pis` decimal(15,2) DEFAULT NULL,
  `valor_cofins` decimal(15,2) DEFAULT NULL,
  `valor_icms_st` decimal(15,2) DEFAULT NULL,
  `valor_fcp_st` decimal(15,2) DEFAULT '0.00',
  `valor_fcp_st_ret` decimal(15,2) DEFAULT '0.00',
  `modalidade_frete` varchar(50) DEFAULT NULL,
  `tipo_operacao` enum('entrada','saida') DEFAULT 'entrada',
  `status` varchar(20) DEFAULT 'ativa',
  `competencia_ano` int DEFAULT NULL,
  `competencia_mes` int DEFAULT NULL,
  `data_importacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `pICMS` decimal(5,2) DEFAULT '0.00',
  `uf_emitente` varchar(2) DEFAULT NULL,
  `uf_destinatario` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_nfe` (`usuario_id`,`chave_acesso`,`tipo_operacao`),
  CONSTRAINT `nfe_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1674 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nfe_itens`
--

DROP TABLE IF EXISTS `nfe_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfe_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nfe_id` int NOT NULL,
  `numero_item` int DEFAULT NULL,
  `codigo_produto` varchar(30) DEFAULT NULL,
  `descricao` text,
  `ncm` varchar(10) DEFAULT NULL,
  `cfop` varchar(10) DEFAULT NULL,
  `unidade` varchar(10) DEFAULT NULL,
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
  `codigo_gtin` varchar(14) DEFAULT NULL,
  `pICMS` decimal(5,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `nfe_id` (`nfe_id`),
  CONSTRAINT `nfe_itens_ibfk_1` FOREIGN KEY (`nfe_id`) REFERENCES `nfe` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notas_fiscais`
--

DROP TABLE IF EXISTS `notas_fiscais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_fiscais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `serie` varchar(20) DEFAULT NULL,
  `data_emissao` date DEFAULT NULL,
  `emitente_nome` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `competencia_ano` int DEFAULT NULL,
  `competencia_mes` int DEFAULT NULL,
  `data_importacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `notas_fiscais_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `relatorios_contestacao_difal`
--

DROP TABLE IF EXISTS `relatorios_contestacao_difal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `relatorios_contestacao_difal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `nota_fiscal_id` int NOT NULL,
  `competencia` varchar(7) NOT NULL,
  `chave_nota` varchar(100) NOT NULL,
  `descricao_produtos` text,
  `icms_sefaz` decimal(10,2) DEFAULT '0.00',
  `fecoep_sefaz` decimal(10,2) DEFAULT '0.00',
  `icms_manual` decimal(10,2) DEFAULT '0.00',
  `fecoep_manual` decimal(10,2) DEFAULT '0.00',
  `numero_item` varchar(10) DEFAULT '6',
  `observacoes` text,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nota_fiscal_id` (`nota_fiscal_id`),
  CONSTRAINT `relatorios_contestacao_difal_ibfk_1` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `nfe` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `responsibles`
--

DROP TABLE IF EXISTS `responsibles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `responsibles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tabela_contabil`
--

DROP TABLE IF EXISTS `tabela_contabil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tabela_contabil` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `pagamento` date DEFAULT NULL,
  `cod_conta_debito` varchar(50) DEFAULT NULL,
  `conta_credito` varchar(50) DEFAULT NULL,
  `vr_liquido` decimal(15,2) DEFAULT NULL,
  `cod_historico` varchar(50) DEFAULT NULL,
  `complemento_historico` text,
  `inicia_lote` enum('S','N') DEFAULT 'N',
  `data_importacao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `tabela_contabil_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tabela_fiscal`
--

DROP TABLE IF EXISTS `tabela_fiscal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tabela_fiscal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `vencimento` date DEFAULT NULL,
  `pagamento` date DEFAULT NULL,
  `vr_liquido` decimal(15,2) DEFAULT NULL,
  `valor_juros` decimal(15,2) DEFAULT '0.00',
  `valor_multa` decimal(15,2) DEFAULT '0.00',
  `valor_desconto` decimal(15,2) DEFAULT '0.00',
  `valor_pis` decimal(15,2) DEFAULT '0.00',
  `valor_cofins` decimal(15,2) DEFAULT '0.00',
  `valor_csll` decimal(15,2) DEFAULT '0.00',
  `valor_irrf` decimal(15,2) DEFAULT '0.00',
  `banco` varchar(50) DEFAULT NULL,
  `nome_cliente` varchar(255) DEFAULT NULL,
  `historico` text,
  `nota_fiscal` varchar(50) DEFAULT NULL,
  `data_importacao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `tabela_fiscal_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_empresa`
--

DROP TABLE IF EXISTS `user_empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_empresa` (
  `user_id` int NOT NULL,
  `empresa_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`empresa_id`),
  KEY `empresa_id` (`empresa_id`),
  CONSTRAINT `user_empresa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_empresa_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-04 15:14:35
