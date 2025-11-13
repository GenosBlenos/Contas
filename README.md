# Contas

Descrição curta
---------------

Sistema para gestão de faturas/contas (PHP + componentes ML). Este repositório contém a aplicação web (PHP), scripts auxiliares, e um pequeno módulo de machine learning para classificação de faturas.

**Estrutura de Pastas**

- `app/` : arquivos de configuração e conexão inicial (ex: `conexao.php`, `configuracoes.php`).
- `public/` : ponto de entrada público da aplicação (views/rotas PHP acessíveis pelo navegador).
  - arquivos notáveis: `index.php`, `login.php`, `salva_fatura.php`, `processa_pdf.php`, `uploads/`.
- `src/` : código organizado em MVC (controllers, includes, models, views)
  - `src/controllers/` : controladores principais (ex: `FaturasController.php`, `AguaController.php`, `EnergiaController.php`).
  - `src/includes/` : utilitários e helpers (ex: `Database.php`, `SecurityManager.php`, `Logger.php`, `faturas_helper.php`).
  - `src/models/` : classes de modelo correspondendo às tabelas (ex: `Fatura.php`, `Documento.php`, `Unidade.php`).
- `db/` : dumps e migrações SQL (ex: `faturas.sql`, `menu_tables.sql`).
- `ml_model/` : código Python para extração/treinamento/classificação de faturas.
  - `app.py` : API ou runner do modelo (pode expor endpoints para classificação).
  - `extractor.py`, `pdf_processor.py` : utilitários para extrair texto de PDFs e pré-processamento.
  - `prepare_data.py`, `preprocessing.py` : transformação e limpeza dos dados para treino.
  - `train_classifier.py` : script de treinamento do classificador.
  - `requirements.txt` : dependências Python para o módulo ML.
  - artefatos: `fatura_classifier_model.pkl.bak`, `tfidf_vectorizer.pkl.bak` (modelos e vetorizadores salvos).
- `vendor/` : dependências gerenciadas pelo Composer.
- `assets/` : JS/CSS públicos (ex: `assets/js/funcoes.js`).
- `scripts/` : scripts utilitários/CLI (ex: `apply_migrations.php`, `cli_upload.php`).

**Principais arquivos e funções (resumo)**

- `app/conexao.php` : inicializa conexão com o banco (PDO/MySQL). Funções esperadas: `getConnection()` ou configuração global de `$db`.
- `src/includes/Database.php` : wrapper da conexão; métodos típicos: `connect()`, `query()`, `beginTransaction()`, `commit()`, `rollback()`.
- `src/includes/SecurityManager.php` : autenticação/autorização; funções: `checkLogin()`, `requireRole()`, `login()`, `logout()`.
- `src/includes/Logger.php` : registro de eventos; métodos: `logInfo()`, `logError()`, `logDebug()`.
- `src/controllers/FaturasController.php` : ações CRUD para faturas. Métodos comuns: `index()`, `show($id)`, `create()`, `store()`, `edit($id)`, `update($id)`, `delete($id)`.
- `public/processa_pdf.php` : ponto de integração para upload/processamento de PDFs. Geralmente chama `ml_model/pdf_processor.py` ou endpoints internos.
- `public/salva_fatura.php` : roteador que recebe POSTs para salvar faturas; valida e persiste no banco via `Model`/`FaturasController`.
- `ml_model/train_classifier.py` : treina o modelo de classificação de faturas e gera `*.pkl`.
- `ml_model/app.py` : (se existir) roda um serviço Flask/FastAPI que recebe texto de faturas e retorna a classe prevista.

**Como rodar localmente (rápido)**

- Requisitos principais: WAMP (Apache + PHP + MySQL), Composer, Python 3.8+ para a pasta `ml_model`.

- Configurar o PHP app:

```powershell
# Importar DB (executar no MySQL):
mysql -u root -p < db\faturas.sql
mysql -u root -p < db\menu_tables.sql

# Instalar deps PHP (a partir da raíz do projeto):
composer install

# Configurar `src/includes/config.php` ou `app/configuracoes.php` com credenciais DB
# Start do WAMP/Apache e abrir `http://localhost/Contas/public` ou equivalente
```

- Configurar o módulo ML (opcional):

```powershell
# Entrar na pasta ml_model
cd ml_model
# criar e ativar virtualenv (PowerShell)
python -m venv .venv
.\\.venv\\Scripts\\Activate.ps1
pip install -r requirements.txt
# Treinar (opcional)
python train_classifier.py
# Rodar API (se aplicável)
python app.py
```

**Pontos importantes / Observações**

- Os controladores em `src/controllers` seguem um padrão REST-like; verifique `BaseController.php` para convenções de resposta e renderização.
- Helpers em `src/includes` centralizam validação, upload de arquivos e controle de sessão. Procure por `Validator.php`, `FileUpload.php` e `session_config.php`.
- A integração entre o PHP e o módulo ML pode ser via chamada de subprocesso (ex: `shell_exec('python ml_model/app.py ...')`) ou via HTTP se `ml_model/app.py` expõe uma API.

**Contribuição / Desenvolvimento**

- Fazer fork/branch, seguir padrões de código (PSR para PHP se aplicável).
- Rodar linter e testes (se houver) antes de PR.

**Contato / Suporte**

Se precisar que eu detalhe funções específicas (por arquivo), ou gere documentação mais completa (ex: listagem de métodos de cada controller/model), diga quais arquivos quer que eu analise e eu extraio automaticamente a assinatura das funções.

