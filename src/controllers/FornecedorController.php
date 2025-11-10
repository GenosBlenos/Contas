<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Database.php';
$pdo = Database::getInstance()->getConnection();

class FornecedorController
{
    /**
     * Exibe a lista de fornecedores.
     */
    public function index()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM fornecedores ORDER BY nome");
        $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['fornecedores' => $fornecedores];
    }

    /**
     * Salva um novo fornecedor no banco de dados.
     */
    public function store()
    {
        global $pdo;
        try {
            $sql = "INSERT INTO fornecedores (nome, contato, telefone, email) VALUES (:nome, :contato, :telefone, :email)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $_POST['nome'],
                ':contato' => $_POST['contato'],
                ':telefone' => $_POST['telefone'],
                ':email' => $_POST['email'],
            ]);
            flashMessage('success', 'Fornecedor cadastrado com sucesso.');
        } catch (PDOException $e) {
            flashMessage('error', 'Erro ao cadastrar fornecedor: ' . $e->getMessage());
        }
        header('Location: index.php?page=fornecedores');
        exit;
    }

    /**
     * Atualiza um fornecedor existente.
     */
    public function update()
    {
        global $pdo;
        try {
            $sql = "UPDATE fornecedores SET nome = :nome, contato = :contato, telefone = :telefone, email = :email WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $_POST['id'],
                ':nome' => $_POST['nome'],
                ':contato' => $_POST['contato'],
                ':telefone' => $_POST['telefone'],
                ':email' => $_POST['email'],
            ]);
            flashMessage('success', 'Fornecedor atualizado com sucesso.');
        } catch (PDOException $e) {
            flashMessage('error', 'Erro ao atualizar fornecedor: ' . $e->getMessage());
        }
        header('Location: index.php?page=fornecedores');
        exit;
    }

    /**
     * Exclui um fornecedor.
     */
    public function destroy()
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("DELETE FROM fornecedores WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            flashMessage('success', 'Fornecedor excluído com sucesso.');
        } catch (PDOException $e) {
            flashMessage('error', 'Erro ao excluir fornecedor: ' . $e->getMessage());
        }
        header('Location: index.php?page=fornecedores');
        exit;
    }
}