<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../app/conexao.php';

class UsuarioController
{
    public function index()
    {
        global $pdo;
        // Nunca retorne a senha, mesmo que hasheada
        $stmt = $pdo->query("SELECT id, nome, email, perfil FROM usuarios ORDER BY nome");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['usuarios' => $usuarios];
    }

    public function store()
    {
        global $pdo;
        // Validação CSRF
        if (!isset($_POST['csrf_token']) || !validarCSRFToken($_POST['csrf_token'])) {
            gerarLog('Falha CSRF', 'Tentativa de cadastro de usuário sem token CSRF válido.');
            flashMessage('error', 'Erro de segurança. Tente novamente.');
            header('Location: index.php?page=usuarios');
            exit;
        }
        try {
            $nome = sanitizeInput($_POST['nome'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $perfil = sanitizeInput($_POST['perfil'] ?? '');
            $senhaHash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nome, email, senha, perfil) VALUES (:nome, :email, :senha, :perfil)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => $senhaHash,
                ':perfil' => $perfil,
            ]);
            gerarLog('Usuário cadastrado', $email);
            flashMessage('success', 'Usuário cadastrado com sucesso.');
        } catch (PDOException $e) {
            gerarLog('Erro cadastro usuário', $e->getMessage());
            flashMessage('error', 'Erro ao cadastrar usuário: ' . $e->getMessage());
        }
        header('Location: index.php?page=usuarios');
        exit;
    }

    public function update()
    {
        global $pdo;
        // Validação CSRF
        if (!isset($_POST['csrf_token']) || !validarCSRFToken($_POST['csrf_token'])) {
            gerarLog('Falha CSRF', 'Tentativa de atualização de usuário sem token CSRF válido.');
            flashMessage('error', 'Erro de segurança. Tente novamente.');
            header('Location: index.php?page=usuarios');
            exit;
        }
        try {
            $id = sanitizeInput($_POST['id'] ?? '');
            $nome = sanitizeInput($_POST['nome'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $perfil = sanitizeInput($_POST['perfil'] ?? '');
            $sql = "UPDATE usuarios SET nome = :nome, email = :email, perfil = :perfil WHERE id = :id";
            $params = [
                ':id' => $id,
                ':nome' => $nome,
                ':email' => $email,
                ':perfil' => $perfil,
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            gerarLog('Usuário atualizado', $email);
            flashMessage('success', 'Usuário atualizado com sucesso.');
        } catch (PDOException $e) {
            gerarLog('Erro atualização usuário', $e->getMessage());
            flashMessage('error', 'Erro ao atualizar usuário: ' . $e->getMessage());
        }
        header('Location: index.php?page=usuarios');
        exit;
    }

    public function destroy()
    {
        // Implementar lógica de exclusão, talvez com confirmação
        header('Location: index.php?page=usuarios');
        exit;
    }
}