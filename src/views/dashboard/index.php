<?php
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/Database.php';
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/header.php';
$pageTitle = 'Dashboard';

// 1. Ponto de Entrada e Autenticação
require_once __DIR__ . '/../../includes/Logger.php';
require_once __DIR__ . '/../../includes/SecurityManager.php';

$securityManager = SecurityManager::getInstance();

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /compras/login.php');
    exit;
}

// Páginas que são arquivos .php independentes na raiz.
$standalonePages = [
    'agua',
    'energia',
    'semparar',
    'telefone',
    'internet',
    'relatorios',
    'recomendacoes',
    'support',
    'cad_fatura_pdf',

];

$page = $_GET['page'] ?? 'dashboard'; // A página padrão é o dashboard

// Se a página solicitada for outra página autônoma, redireciona para o arquivo .php correspondente.
if (in_array($page, $standalonePages)) {
    // Preserva o parâmetro 'module' se ele existir, útil para páginas como 'faturas.php'
    $queryString = !empty($_GET['module']) ? '?module=' . urlencode($_GET['module']) : '';
    header('Location: ' . $page . '.php' . $queryString);
    exit;
}

// Mapeamento de 'page' para o nome da classe do Controller.
// Este é o coração do roteamento MVC.
$controllers = [
    'energia' => 'EnergiaController',
    'fornecedores' => 'FornecedorController',
    'categorias' => 'CategoriaController',
    'produtos' => 'ProdutoController',
    'compras' => 'CompraController',
    'relatorios_mvc' => 'RelatorioController', // Renomeado para evitar conflito com relatorios.php
    'usuarios' => 'UsuarioController',
];

// Define o módulo para que o header e o nav possam usá-lo
$_GET['module'] = $page;

// 3. Tratamento de Requisições POST (Ações de Formulário)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação de Token CSRF (debug)
    error_log('CSRF Debug - POST token: ' . ($_POST['csrf_token'] ?? 'NULL') . ' | SESSION token: ' . ($_SESSION['csrf_token'] ?? 'NULL') . ' | SID: ' . session_id() . ' | HTTPS: ' . ($_SERVER['HTTPS'] ?? ''));

    // Se for o formulário de autenticação admin, permitimos que este arquivo trate
    // o POST abaixo (código de autenticação está mais adiante no mesmo arquivo).
    if (isset($_POST['admin_auth']) && $_POST['admin_auth'] === '1') {
        // Validamos CSRF também para o formulário admin
        if (!isset($_POST['csrf_token']) || !$securityManager->validateCSRF($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Erro de validação de segurança. Por favor, tente novamente.';
            header('Location: index.php?page=' . $page);
            exit;
        }

        // Não executamos o roteamento de controllers aqui; o processamento do
        // login admin continuará abaixo neste mesmo arquivo.
    } else {
        // Comportamento original: somente processar POSTs que pertencem a
        // controllers mapeados. Validação e roteamento continuam como antes.
        if (!isset($_POST['csrf_token']) || !$securityManager->validateCSRF($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Erro de validação de segurança. Por favor, tente novamente.';
            header('Location: index.php?page=' . $page);
            exit;
        }

        if (isset($controllers[$page])) {
            $controllerName = $controllers[$page];
            $controllerPath = __DIR__ . '/../src/controllers/' . $controllerName . '.php';

            if (file_exists($controllerPath)) {
                require_once $controllerPath;
                $controller = new $controllerName();

                $action = $_POST['action'] ?? '';
                if (method_exists($controller, $action)) {
                    $controller->$action(); // Executa a ação (store, update, destroy)
                } else {
                    header('Location: index.php?page=' . $page . '&error=invalid_action');
                    exit;
                }
            } else {
                header('Location: index.php?error=not_found');
                exit;
            }
        } else {
            header('Location: index.php?error=not_found');
            exit;
        }

        // Saímos após manipular um POST destinado a controllers
        exit;
    }
}

// 4. Tratamento de Requisições GET (Exibição de Páginas)
$csrfToken = $securityManager->getCSRFToken();
$pageTitle = ucfirst($page);

// Carrega o controller correspondente à página para buscar dados
if (isset($controllers[$page])) {
    $controllerName = $controllers[$page];
    $controllerPath = __DIR__ . '/../src/controllers/' . $controllerName . '.php';

    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        $controller = new $controllerName();

        if (method_exists($controller, 'index')) {
            $data = $controller->index();
            extract($data);
        }
    }
    // O view correspondente será incluído abaixo

} else if ($page !== 'dashboard') {
    // Se a página não é um controller conhecido e não é o dashboard, redireciona
    // Ou podemos mostrar uma página 404 dedicada
    // Por enquanto, redirecionamos para o dashboard
    header('Location: index.php?page=dashboard&error=not_found');
    exit;
}


setlocale(LC_ALL, "pt_BR", "pt_BR.utf-8", "pt_BR.utf-8", "portuguese");
date_default_timezone_set("America/Sao_Paulo");

// Iniciar sessão no início do arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$acesso_permitido = false;
$erro_autenticacao = false;
$login_sucesso = false;

// Verificar se é admin autenticado via sessão
if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    $acesso_permitido = true;
    // error_log("Acesso permitido via sessão");
}


if (isset($_POST['senha_admin']) && !empty($_POST['senha_admin'])) {

    try {
        $pdo = Database::getInstance()->getConnection();
        $senha_digitada = trim($_POST['senha_admin']);

        $stmt = $pdo->prepare("SELECT id, nome, senha FROM usuario WHERE admin = 1");
        $stmt->execute();

        // 1. Renomeie para $admins (plural) para clareza
        $admins = $stmt->fetchAll(mode: PDO::FETCH_ASSOC);

        $acesso_permitido = false; // Começa como falso

        if ($admins) {
            // 2. Faça um loop em cada admin encontrado
            foreach ($admins as $admin_user) {

                // 3. Verifique a senha (COM A CORREÇÃO DE SEGURANÇA)
                if (password_verify($senha_digitada, $admin_user['senha'])) {

                    // Encontramos uma correspondência!
                    $acesso_permitido = true;
                    $login_sucesso = true;

                    // 4. Salve os dados do admin que teve a senha correspondente
                    $_SESSION['admin_authenticated'] = true;
                    $_SESSION['admin_id'] = $admin_user['id'];
                    $_SESSION['admin_nome'] = $admin_user['nome'];

                    // 5. Pare o loop, já encontramos
                    break;
                }
            }
        }

        // 6. Verifique o resultado *depois* do loop
        if (!$acesso_permitido) {
            // Nenhuma senha correspondeu ou nenhum admin foi encontrado
            $erro_autenticacao = true;
        }

    } catch (Exception $e) {
        error_log("Erro na autenticação admin: " . $e->getMessage());
        $erro_autenticacao = true;
    }
}

// Logout admin
if (isset($_GET['logout_admin'])) {
    unset($_SESSION['admin_authenticated']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_nome']);
    header('Location: ' . str_replace('?logout_admin=1', '', $_SERVER['REQUEST_URI']));
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gerenciamento de Contas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div id="authModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl w-80">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Acesso Restrito - Administrador</h3>

                <?php if ($erro_autenticacao): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                        Senha incorreta.
                    </div>
                <?php endif; ?>

                <?php if ($login_sucesso): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                        Login realizado com sucesso! Redirecionando...
                    </div>
                <?php endif; ?>

                <form method="POST" id="adminAuthForm">
                    <div class="mb-4">
                        <label for="senha_admin" class="block text-sm font-medium text-gray-700 mb-2">
                            Senha de Administrador
                        </label>
                        <input type="password" id="senha_admin" name="senha_admin"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Digite a senha" required autocomplete="off" autofocus>
                    </div>
                    <!-- CSRF token e flag para identificar o formulário de admin -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="admin_auth" value="1">
                    <div class="flex justify-between">
                        <button type="button" id="cancelAuth"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                            Cancelar
                        </button>
                        <button type="button" onclick="EnviarFormulario()"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                            Acessar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Painel Secreto (só aparece para admins autenticados) -->
        <?php if ($acesso_permitido): ?>
            <div id="secretPanel" class="mt-8 bg-gray-800 text-white p-6 rounded-lg shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">🔧 Painel de Desenvolvedor</h2>
                    <a href="?logout_admin=1"
                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm font-bold">
                        Sair do Admin
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-gray-700 p-4 rounded">
                        <h3 class="font-bold mb-2">Status do Sistema</h3>
                        <ul class="text-sm">
                            <li>🟢 Serviços Principais: Online</li>
                            <li>🟡 Banco de Dados: Conexão Estável</li>
                            <li>🔵 Último Backup: <?php echo date('d/m/Y H:i'); ?></li>
                        </ul>
                    </div>
                    <div class="bg-gray-700 p-4 rounded flex flex-col">
                        <h3 class="font-bold mb-2">Ações Rápidas</h3>
                        <div class="flex flex-col space-y-2 flex-grow">
                            <button
                                class="btn-action bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded text-sm font-bold h-full flex items-center justify-center"
                                data-url="../logs/system.log">
                                Logs
                            </button>
                        </div>
                    </div>
                    <div class="bg-gray-700 p-4 rounded flex flex-col">
                        <h3 class="font-bold mb-2">Cadastro</h3>
                        <div class="flex flex-col space-y-2 flex-grow">
                            <button
                                class="btn-action bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm font-bold h-full flex items-center justify-center"
                                data-url="../public/index-a.php">
                                Cadastrar Novo Usuário
                            </button>
                        </div>
                    </div>
                    <div class="bg-gray-700 p-4 rounded flex flex-col">
                        <h3 class="font-bold mb-2">Abrir SQL Server</h3>
                        <div class="flex flex-col space-y-2 flex-grow">
                            <button
                                class="btn-action bg-cyan-500 hover:bg-cyan-600 px-4 py-2 rounded text-sm font-bold h-full flex items-center justify-center"
                                data-url="http://localhost/phpmyadmin/index.php?route=/database/structure&db=compras">
                                localhost:1433
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-8 bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Sistema de Gerenciamento de Contas a Pagar</h2>
            <p class="text-gray-600">
                Bem-vindo ao Sistema de Controle de Gastos. Aqui você pode gerenciar suas contas de água, energia
                elétrica,
                telefonia fixa, internet predial e serviços de Sem Parar de forma eficiente e organizada.
            </p>
            <p class="mt-2 text-gray-600">
                A seção de <strong>Relatórios</strong> consolida os dados de todos os módulos para uma análise global,
                enquanto
                a seção de <strong>Recomendações</strong> utiliza inteligência para apontar possíveis economias e
                otimizações.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mt-5">
            <!-- Card Água -->
            <a href="agua.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/water.png" alt="Água" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Água Predial</h3>
            </a>

            <!-- Card Energia -->
            <a href="energia.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/flash.png" alt="Energia" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Energia Elétrica</h3>
            </a>

            <!-- Card Sem Parar -->
            <a href="semparar.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/car.png" alt="Sem Parar" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Sem Parar</h3>
            </a>

            <!-- Card Telefone -->
            <a href="telefone.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/phone.png" alt="Telefone" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Telefonia Fixa</h3>
            </a>

            <!-- Card Relatórios -->
            <a href="relatorios.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/report.png" alt="Relatórios" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Relatórios</h3>
            </a>

            <!-- Card Recomendações -->
            <a href="recomendacoes.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/recommendation.png" alt="Recomendações" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Recomendações</h3>
            </a>

            <!-- Card Unidades  -->
            <a href="unidades.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/casa.png" alt="Unidades" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Unidades</h3>
            </a>

            <!-- Card Suporte -->
            <a href="support.php"
                class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:shadow-xl transition-shadow duration-300">
                <img src="../assets/support.png" alt="Ajuda" class="w-16 h-16 mb-4">
                <h3 class="text-lg font-semibold text-gray-800">Ajuda e Suporte</h3>
            </a>

            <!-- Card Cadastrar Fatura PDF -->
            <a href="cad_fatura_pdf.php"
                class="bg-blue-500 text-white rounded-lg shadow-lg p-6 flex flex-col items-center justify-center hover:bg-blue-600 transition-colors duration-300 col-span-1 sm:col-span-2 md:col-span-3 lg:col-span-4">
                <div class="flex items-center">
                    <img src="../assets/conta.png" alt="Upload" class="w-12 h-12 mr-4">
                    <div>
                        <h3 class="text-xl font-bold">Cadastrar Fatura por PDF</h3>
                        <p class="text-sm">Envie um arquivo PDF para extrair os dados da fatura automaticamente.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <style>
        .flex-grow {
            flex-grow: 1;
        }
    </style>

    <script>
        function EnviarFormulario() {
            console.log("Enviando formulário de autenticação admin...");
            document.getElementById("adminAuthForm").submit();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const authModal = document.getElementById('authModal');
            const adminAuthForm = document.getElementById('adminAuthForm');
            const cancelAuth = document.getElementById('cancelAuth');
            const secretPanel = document.getElementById('secretPanel');

            // Atalho para mostrar/ocultar o painel (Ctrl + Ç)
            document.addEventListener('keydown', function (e) {
                if (e.ctrlKey && e.key === 'ç') {
                    e.preventDefault();

                    <?php if (!$acesso_permitido): ?>
                        // Se não está autenticado como admin, mostra modal
                        authModal.classList.remove('hidden');
                        document.getElementById('senha_admin').focus();
                    <?php else: ?>

                        // Se já está autenticado como admin, mostra/oculta o painel
                        if (secretPanel) {
                            secretPanel.classList.toggle('hidden');
                            if (!secretPanel.classList.contains('hidden')) {
                                secretPanel.scrollIntoView({ behavior: 'smooth' });
                            }
                        }
                    <?php endif; ?>
                }
            });

            // Fechar modal ao clicar em cancelar
            cancelAuth.addEventListener('click', function () {
                authModal.classList.add('hidden');
            });

            // Fechar modal ao clicar fora dele
            authModal.addEventListener('click', function (e) {
                if (e.target === authModal) {
                    authModal.classList.add('hidden');
                }
            });

            // Adicionar eventos de clique para todos os botões de ação
            document.querySelectorAll('.btn-action').forEach(button => {
                button.addEventListener('click', function () {
                    const url = this.getAttribute('data-url');
                    if (url) {
                        window.location.href = url;
                    }
                });
            });


            // Focar no campo de senha quando o modal abrir
            if (authModal) {
                authModal.addEventListener('transitionend', function () {
                    if (!authModal.classList.contains('hidden')) {
                        document.getElementById('senha_admin').focus();
                    }
                });
            }
        });

        //         adminAuthForm.addEventListener('submit', function (e) {
        //     console.log("Formulário enviado");
        //     e.preventDefault();
        //     this.submit(); // força o envio normal do form
        // });

    </script>
</body>

</html>