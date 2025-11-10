<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../app/conexao.php';

class ProdutoController
{
    public function index()
    {
        global $pdo;
        $stmt = $pdo->query("
            SELECT p.*, c.nome as categoria_nome, f.nome as fornecedor_nome
            FROM produtos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            LEFT JOIN fornecedores f ON p.fornecedor_id = f.id
            ORDER BY p.nome
        ");
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar dados para os formulários (selects)
        $categorias = $pdo->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
        $fornecedores = $pdo->query("SELECT id, nome FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'produtos' => $produtos,
            'categorias' => $categorias,
            'fornecedores' => $fornecedores
        ];
    }

    public function store()
    {
        global $pdo;
        try {
            $sql = "INSERT INTO produtos (nome, descricao, preco, categoria_id, fornecedor_id) VALUES (:nome, :descricao, :preco, :categoria_id, :fornecedor_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $_POST['nome'],
                ':descricao' => $_POST['descricao'],
                ':preco' => $_POST['preco'],
                ':categoria_id' => $_POST['categoria_id'],
                ':fornecedor_id' => $_POST['fornecedor_id'],
            ]);
            flashMessage('success', 'Produto cadastrado com sucesso.');
        } catch (PDOException $e) {
            flashMessage('error', 'Erro ao cadastrar produto: ' . $e->getMessage());
        }
        header('Location: index.php?page=produtos');
        exit;
    }

    public function update()
    {
        global $pdo;
        try {
            $sql = "UPDATE produtos SET nome = :nome, descricao = :descricao, preco = :preco, categoria_id = :categoria_id, fornecedor_id = :fornecedor_id WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $_POST['id'],
                ':nome' => $_POST['nome'],
                ':descricao' => $_POST['descricao'],
                ':preco' => $_POST['preco'],
                ':categoria_id' => $_POST['categoria_id'],
                ':fornecedor_id' => $_POST['fornecedor_id'],
            ]);
            flashMessage('success', 'Produto atualizado com sucesso.');
        } catch (PDOException $e) {
            flashMessage('error', 'Erro ao atualizar produto: ' . $e->getMessage());
        }
        header('Location: index.php?page=produtos');
        exit;
    }

    public function destroy()
    {
        global $pdo;
        try {
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            flashMessage('success', 'Produto excluído com sucesso.');
        } catch (PDOException $e) {
            // Tratar erro de chave estrangeira, se aplicável
            flashMessage('error', 'Erro ao excluir produto. Verifique se ele não está associado a uma compra.');
        }
        header('Location: index.php?page=produtos');
        exit;
    }
}