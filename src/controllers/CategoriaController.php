<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/Categoria.php';

class CategoriaController
{
    private $categoriaModel;

    public function __construct()
    {
        $this->categoriaModel = new Categoria();
    }

    public function index()
    {
        $categorias = $this->categoriaModel->all();
        return ['categorias' => $categorias];
    }

    public function store()
    {
        try {
            $this->categoriaModel->create($_POST);
            flashMessage('success', 'Categoria cadastrada com sucesso.');
        } catch (\Exception $e) {
            flashMessage('error', 'Erro ao cadastrar categoria: ' . $e->getMessage());
        }
        header('Location: index.php?page=categorias');
        exit;
    }

    public function update()
    {
        try {
            $this->categoriaModel->update($_POST['id'], $_POST);
            flashMessage('success', 'Categoria atualizada com sucesso.');
        } catch (\Exception $e) {
            flashMessage('error', 'Erro ao atualizar categoria: ' . $e->getMessage());
        }
        header('Location: index.php?page=categorias');
        exit;
    }

    public function destroy()
    {
        try {
            $this->categoriaModel->delete($_POST['id']);
            flashMessage('success', 'Categoria excluída com sucesso.');
        } catch (\Exception $e) {
            flashMessage('error', 'Erro ao excluir categoria: ' . $e->getMessage());
        }
        header('Location: index.php?page=categorias');
        exit;
    }
}