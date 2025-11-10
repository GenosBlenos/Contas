<?php
require_once __DIR__ . '/../includes/Model.php';
class Recomendacao extends Model {
    protected $table = 'recomendacoes';
    protected $fillable = ['titulo', 'descricao'];
    protected $orderBy = 'id';
}
