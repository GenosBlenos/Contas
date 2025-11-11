<?php
require_once __DIR__ . '/../src/includes/session_config.php';
session_start();
require_once __DIR__ . '/../src/includes/auth.php';

if (!isset($_SESSION['logado']) || !$_SESSION['logado']) {
    header('Location: /Contas/login.php');
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
            $stmt = $pdo->prepare('INSERT INTO usuario (nome, email, senha, admin) VALUES (?, ?, ?, ?)');
            if ($stmt->execute([$nome, $email, $senhaHash, isset($_POST['admin']) ? 1 : 0])) {
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

<body class="bg-gray-800 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-gray-100 rounded-lg shadow-lg overflow-hidden">
            <div class="bg-[#147cac] p-6 text-center">
                <h1 class="text-3xl font-bold text-white">Sistema de Controle de Gastos</h1>
            </div>
            <div class="p-8">
                <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Cadastro de Usuário</h2>
                <?php if ($mensagem): ?>
                    <div
                        class="mb-4 px-4 py-3 rounded <?php echo (strpos($mensagem, 'sucesso') !== false) ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                        <?= htmlspecialchars($mensagem) ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="space-y-6">
                    <div class="px-3 py-3">
                        <div>
                            <label for="nome" class="block text-lg font-medium text-gray-700">Nome:</label>
                            <input type="text" name="nome" id="nome" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="email" class="block mt-2 text-lg font-medium text-gray-700">E-mail:</label>
                            <input type="email" name="email" id="email" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="senha" class="block mt-2 text-lg font-medium text-gray-700">Senha:</label>
                            <input type="password" name="senha" id="senha" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="senha2" class="block mt-2 text-lg font-medium text-gray-700">Repita a Senha:</label>
                            <input type="password" name="senha2" id="senha2" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="admin" class="inline-flex items-center">
                                <input type="checkbox" name="admin" id="admin"
                                    class="h-4 w-4 mt-2 text-indigo-600 focus:ring-indigo-500 border-gray-500 rounded">
                                <span class="mt-2 ml-2 text-lg font-medium text-gray-700">Administrador</span>
                        </div>
                        <button type="submit"
                            class="w-full mt-5 flex justify-center py-5 px-4 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-[#147cac] hover:bg-[#0f5f85] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0a3e57]">Cadastrar</button>
                </form>
                <a href="index.php"
                            class="w-full mt-5 flex justify-center py-1 px-2 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-[#cc0e00] hover:bg-[#9e0b00] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#700901]">Cancelar</a>
            </div>
        </div>
    </div>
    </div>
</body>
</html>