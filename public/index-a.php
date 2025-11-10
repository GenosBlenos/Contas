<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /compras/login.php');
    exit;
}

require_once __DIR__ . '/../src/controllers/EnergiaController.php';
require_once __DIR__ . '/../src/includes/helpers.php';
require_once __DIR__ . '/../src/includes/faturas_helper.php';

$pdo = Database::getInstance()->getConnection();

$pageTitle = 'Cadastro de Usuário';

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $senha2 = $_POST['senha2'] ?? '';

    if (!$nome || !$email || !$senha || !$senha2) {
        $mensagem = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'E-mail inválido.';
    } elseif ($senha !== $senha2) {
        $mensagem = 'As senhas não coincidem.';
    } else {
        // Verifica se já existe
        $stmt = $pdo->prepare('SELECT id FROM usuario WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $mensagem = 'Já existe um usuário com este e-mail.';
        } else {
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuario (nome, email, senha) VALUES (?, ?, ?)');
            if ($stmt->execute([$nome, $email, $senhaHash])) {
                $mensagem = 'Usuário cadastrado com sucesso!';
            } else {
                $mensagem = 'Erro ao cadastrar usuário.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Usuário</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-[#404040] p-6 text-center">
            </div>
            <div class="p-8">
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Cadastro de Usuário</h2>
                <?php if ($mensagem): ?>
                    <div class="mb-4 px-4 py-3 rounded <?php echo (strpos($mensagem, 'sucesso') !== false) ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                        <?= htmlspecialchars($mensagem) ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="nome" class="block text-sm font-medium text-gray-700">Nome:</label>
                        <input type="text" name="nome" id="nome" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">E-mail:</label>
                        <input type="email" name="email" id="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="senha" class="block text-sm font-medium text-gray-700">Senha:</label>
                        <input type="password" name="senha" id="senha" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label for="senha2" class="block text-sm font-medium text-gray-700">Repita a Senha:</label>
                        <input type="password" name="senha2" id="senha2" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#072a3a] hover:bg-[#0a3e56] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#072a3a]">Cadastrar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
