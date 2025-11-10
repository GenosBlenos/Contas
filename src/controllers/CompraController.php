<?php
require_once __DIR__ . '/../includes/helpers.php';

class CompraController
{
    public function index()
    {
        // Lógica para listar compras, possivelmente com JOINs em produtos e fornecedores
        return ['compras' => []];
    }

    public function store()
    {
        // Lógica para registrar uma nova compra e seus itens
        header('Location: index.php?page=compras');
        exit;
    }

    public function update()
    {
        // Lógica para atualizar uma compra (ex: status)
        header('Location: index.php?page=compras');
        exit;
    }

    public function destroy()
    {
        // Lógica para cancelar/excluir uma compra
        header('Location: index.php?page=compras');
        exit;
    }
}