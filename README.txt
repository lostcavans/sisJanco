Sistema contábil - WEB

Linguagem usada - PHP
Banco usado - MYSQL
Por meio do - VSCODE


# Projeto: Sistema Contábil — Resumo e Documento Técnico

**Versão:** Análise do pacote entregue (sistema_contabil)

**Objetivo deste documento**
Fornecer um resumo executivo e um projeto técnico detalhado do *Sistema Contábil* encontrado no arquivo `sistema_contabil.zip`. O documento descreve o que o sistema faz, cada tela/arquivo relevante, o mapeamento para o banco de dados, fluxo de dados, pontos de atenção, instruções de instalação/implantação e sugestões de melhorias e evolução.

---

## 1. Resumo Executivo

O *Sistema Contábil* é uma aplicação web em PHP com back-end LAMP (PHP + MySQL) destinada a automatizar rotinas contábeis e fiscais: cadastro de empresas, livro-caixa, conversão de planilhas em lançamentos contábeis, processamento e conferência de notas fiscais (NFe/NFCe), cálculos de regimes fiscais (ICMS Fronteira, DIFAL, cálculo cesta básica) e geração de arquivos para integração com sistemas contábeis (ex.: Domínio). A interface atual é baseada em páginas PHP + HTML com estilos em `styles.css` e vários scripts PHP que implementam endpoints para processamentos específicos.

Público-alvo: escritórios de contabilidade e departamentos fiscais que precisam consolidar importação/normalização de dados de planilhas e XMLs e gerar lançamentos contábeis e relatórios.

Principais funcionalidades:

* Login / cadastro de usuários e vínculo usuário ↔ empresa.
* Dashboard administrativo e dashboard fiscal.
* Livro Caixa (preenchimento e armazenamento de lançamentos).
* Conversão de planilhas (contábil e fiscal) para inserção automática no banco de dados.
* Importação e processamento/analise de NFe/NFCe e itens relacionados (tabelas nfe, nfce, seus itens).
* Cálculos fiscais: ICMS Fronteira, DIFAL, Cesta Básica.
* Geração de arquivos txt/relatórios e exportação de XMLs com produtos consolidados.

---

## 2. Tecnologias usadas

* PHP (páginas e endpoints)
* MySQL (dump incluído em `buckup sql/sistema_contabil.sql`)
* HTML/CSS (frontend básico em `styles.css`)
* Arquivos auxiliares: planilhas de exemplo (xls), XMLs de exemplo em `temp/`.

---

## 3. Estrutura de arquivos (visão geral)

Principais arquivos e pastas do pacote analisado (`sistema_contabil/`):

* `config.php` — configura conexão com MySQL. (Contém host, usuário, senha, nome do banco)
* `dashboard.php` — tela principal do sistema / painel.
* `dashboard-fiscal.php` — painel com foco fiscal.
* `cadastro.php` — tela de cadastro de empresas/usuários.
* `conferencia-fiscal.php` — tela / rotina para conferência de notas fiscais.
* `conversao-contabil.php` — rotina para importar e converter planilhas contábeis.
* `conversao-fiscal.php` — rotina para importar e converter planilhas fiscais.
* `processar-comparacao.php` — endpoint para processar comparações / conferências entre relatórios e XMLs.
* `detalhes-nota.php` — visualização detalhada de uma nota.
* `novo-calculo.php`, `calculo-fronteira.php`, `calculo-cesta.php` — telas/rotinas de cálculo.
* `salvar_dados_contabeis.php`, `salvar-calculo.php` — endpoints para persistência.
* `ajax-nota.php` — endpoints AJAX relacionados a notas.
* `xml-produtos.php` — geração / exportação de XML com produtos consolidados.
* `planilhas_exemplos/` — planilhas modeladas para testes.
* `buckup sql/sistema_contabil.sql` — dump do banco com tabelas e estrutura.
* `temp/` — arquivos temporários e XMLs de exemplo.
* `styles.css` — estilos visuais.

> Observação: o `config.php` do pacote contém credenciais em claro (por ex.: usuário `root`, senha `Janco123`) — isso deve ser tratado como risco de segurança e configurado via variáveis de ambiente em produção.

---

## 4. Banco de dados — visão geral

O dump `sistema_contabil.sql` contém as seguintes tabelas principais (resumo):

* `users`, `adm`, `user_empresa` — controle de usuários e vínculo com empresas.
* `empresas` — cadastro de empresas clientes.
* `livro_caixa` — lançamentos do livro-caixa.
* `notas_fiscais`, `nfe`, `nfce` e tabelas `*_itens` — armazenamento de notas fiscais e itens.
* `tabela_contabil`, `tabela_fiscal` — tabelas auxiliares de classificação.
* `calculos_*` — tabelas para armazenar cálculos de fronteira, DIFAL, cesta básica e itens associados.
* `contas_pagar_santana` — indicação de integração/planilha específica para cliente (ex.: Santana).

O banco contém cerca de 27 tabelas cobrindo módulos fiscais, contábeis, cálculos e usuários.

Mapeamento rápido de entidades:

* Usuário → empresas (vinculação via `user_empresa`).
* Empresa → Notas (tabelas `nfe`, `nfce`, `notas_fiscais`) → Itens.
* Notas / Planilhas → Conversão → lançamentos em `livro_caixa` e tabelas contábeis.

---

## 5. Fluxos de uso (user journeys) — telas e funcionalidades detalhadas

Abaixo descrevo cada tela/arquivo público importante, o que faz, campos principais, validações esperadas, e como interage com o banco.

### 5.1 `cadastro.php` (Cadastro de Usuário / Empresa)

**Propósito:** permitir cadastro de usuários e possivelmente cadastro de empresas vinculadas.
**Campos esperados:** nome, e-mail, senha, nome da empresa, CNPJ, regime, dados bancários básicos.
**Ações:** validação front/back, criação de registros em `users` e `empresas`, criação de vínculo em `user_empresa`.
**Pontos de atenção:** validação de CNPJ, hash seguro da senha (password_hash), prevenção de criação de contas duplicadas.

---

### 5.2 `dashboard.php` e `dashboard-fiscal.php` (Painéis)

**Propósito:** visão geral com métricas, atalhos para importações, relatórios e pendências.
**Funcionalidades típicas:** indicadores (nº notas pendentes, cálculos pendentes, próximos prazos), lista de últimas notas importadas, botões para `conversao-contabil.php`, `conferencia-fiscal.php`, geração de relatórios.
**Interação:** consulta às tabelas principais (`notas_fiscais`, `calculos_*`, `livro_caixa`).

---

### 5.3 `conversao-contabil.php` / `conversao-fiscal.php`

**Propósito:** importar arquivos Excel (.xls/.xlsx) de clientes e converter linhas em lançamentos contábeis ou registros fiscais.
**Funcionalidade:** upload de planilha, mapeamento de colunas (possível interface para mapear colunas do cliente para o modelo), classificação automática de linhas como 'nota fiscal' ou 'contábil puro', persistência em `tabela_contabil`, `tabela_fiscal`, `livro_caixa`, `notas_fiscais`.
**Validações:** formatos de data, número, CNPJ/CPF, valores negativos, única importação (evitar duplicidade).

---

### 5.4 `processar-comparacao.php` / `conferencia-fiscal.php`

**Propósito:** comparar relatórios (PDF/planilha) com os XMLs de notas e indicar divergências/conferências.
**Funcionalidade:** importação de arquivo de relatório, leitura/extração (se houver OCR ou parse de PDF), comparação por chave de acesso / número da nota, geração de relatórios de diferenças, movimentação de notas para pastas (p.ex. `conferidas`, `para proximo mes`).
**Output:** relatório em PDF (detalhado) + .zip com pastas separadas por grupo (conforme já existia em outro requisito).

---

### 5.5 `detalhes-nota.php` / `ajax-nota.php`

**Propósito:** visualizar uma nota específica com todos os seus itens, impostos e histórico de conferência.
**Funcionalidade:** chamada AJAX para `ajax-nota.php` que retorna JSON com detalhes (nota, itens, impostos calculados, status de importação), exibidos em `detalhes-nota.php`.

---

### 5.6 Módulos de cálculo (`novo-calculo.php`, `calculo-fronteira.php`, `calculo-cesta.php`, `difal.php`)

**Propósito:** realizar cálculos fiscais específicos (ICMS Fronteira, DIFAL, cálculos para cesta básica, etc.).
**Funcionalidade:** formulário para selecionar empresa / competência / notas, parâmetros editáveis (MVA, alíquota, redutor, segmentos), execução do cálculo e persistência em tabelas `calculos_*`.
**Entrada:** itens da nota (base, alíquotas internas/externas, CST/CFOP).
**Saída:** resultados por item e total, possibilidade de salvar e gerar relatório/exportar.

---

### 5.7 `xml-produtos.php` e `selecionar-produtos.php`

**Propósito:** consolidar produtos extraídos de múltiplos XMLs e gerar um XML de saída com códigos modificados (ex.: prefixo `FOR`).
**Funcionalidade:** leitura de XMLs de `temp/` ou pasta selecionada, montagem de um único XML com produtos transformados, gravação em `temp/resultado` ou download.

---

### 5.8 `conversao-notas-txt.php` / `salvar_dados_contabeis.php`

**Propósito:** gerar arquivos `.txt` estruturados para importação em sistemas contábeis externos (formato requerido pelo Domínio ou similares).
**Funcionalidade:** agrupar lançamentos por conta, gerar linhas com campos fixos, fornecer botão para download do `.txt`.

---

## 6. Instalação e configuração (passo-a-passo profissional)

1. **Pré-requisitos**

   * Servidor com PHP 8.x e extensões comuns (mysqli, mbstring, zip, xml, gd se necessário).
   * MySQL 5.7+ ou MariaDB compatível.
   * Composer (opcional para dependências PHP extras).
   * Servidor web (Apache / Nginx). Recomendado rodar em VPS ou container Docker.

2. **Restaurar banco de dados**

   * Criar banco `sistema_contabil` (ou nome desejado).
   * Importar `buckup sql/sistema_contabil.sql` via linha de comando: `mysql -u root -p sistema_contabil < sistema_contabil.sql`.

3. **Configurar `config.php`** (trocar credenciais em ambiente local):

   * Em produção, **NÃO** deixar credenciais no arquivo. Usar variáveis de ambiente e um loader (ex.: `getenv('DB_USER')`).

4. **Permissões**

   * Dar permissão de escrita na pasta `temp/` para geração de arquivos temporários.

5. **URLs amigáveis / .htaccess**

   * Se for necessário, criar `RewriteRules` para limpar rotas e proteger diretórios sensíveis.

6. **Executar**

   * Acessar `http://seu-servidor/sistema_contabil/` e usar a tela de login/cadastro.

---

## 7. Segurança e riscos detectados

1. **Credenciais no repositório** (`config.php`) — risco grave. Substituir por variáveis de ambiente.
2. **Hashes de senha desconhecidos** — confirmar uso de `password_hash` / `password_verify`. Se armazenadas sem hash, migrar imediatamente.
3. **Validação insuficiente** — endpoints que recebem uploads/valores devem validar tamanho, tipo, e sanitizar entradas para prevenir SQL injection (usar prepared statements) e XSS.
4. **Uploads e arquivos temporários** — garantir que pastas `temp/` não sirvam arquivos executáveis e que tenham proteção via `.htaccess` ou regras do servidor.
5. **Restrições de acesso** — rotas administrativas devem checar sessão/roles sempre (verificar `adm` tabela e `user_empresa`).

---

## 8. Boas práticas e melhorias recomendadas (priorizadas)

**Alta prioridade**

* Remover credenciais do repositório e configurar `.env` ou variáveis de ambiente.
* Substituir queries por *prepared statements* para evitar SQL injection.
* Garantir hashing seguro de senhas (`password_hash`) e política de senhas.
* Remover/ocultar dump de banco com dados sensíveis antes de versionar.

**Média prioridade**

* Implementar sistema de permissões mais granular (roles: admin, contábil, fiscal, cliente).
* Implementar logs de auditoria (quem fez o quê) nas tabelas críticas.
* Melhorar UI/UX: migrar para front-end moderno (React/Vue) ou pelo menos melhorar HTML/CSS para responsividade.

**Baixa prioridade / Evolução**

* Disponibilizar Web API RESTful para integrações (token JWT) em vez de endpoints PHP cru.
* Criar testes automatizados (unitários e de integração) para cargas e cálculos fiscais.
* Containerizar aplicação (Docker) e criar playbook de deploy (CI/CD).



Todo dia que se mecheu no codigo, quando ce tiver pra sair se da git add .
git commit -m "Descripción del cambio"
git push origin nombre-del-dev





atualizando o sistema gestão 2.0