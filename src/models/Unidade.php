<?php
require_once __DIR__ . '/../includes/Model.php';
class Unidade extends Model {
    protected $table = 'unidades';
    protected $fillable = ['nome', 'endereco', 'responsavel'];
    protected $orderBy = 'nome';
}
