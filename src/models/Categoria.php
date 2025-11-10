<?php
require_once __DIR__ . '/../includes/Model.php';

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $fillable = ['nome', 'descricao'];
    protected $orderBy = 'nome';
}
