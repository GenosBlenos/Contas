<?php
require_once __DIR__ . '/../includes/helpers.php';

class ContasController
{
    public function index()
    {
        // Lógica para listar Contas, possivelmente com JOINs em produtos e fornecedores
        return ['Contas' => []];
    }

    public function store()
    {
        // Lógica para registrar uma nova Contas e seus itens
        header('Location: index.php?page=Contas');
        exit;
    }

    public function update()
    {
        // Lógica para atualizar uma Contas (ex: status)
        header('Location: index.php?page=Contas');
        exit;
    }

    public function destroy()
    {
        // Lógica para cancelar/excluir uma Contas
        header('Location: index.php?page=Contas');
        exit;
    }
}